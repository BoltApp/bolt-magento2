<?php
/**
 * Bolt magento2 plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebApiException;
use Magento\Quote\Model\Quote;
use Magento\SalesRule\Model\RuleRepository;
use Magento\SalesRule\Model\Coupon;
use Magento\SalesRule\Model\ResourceModel\Coupon\UsageFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\SalesRule\Model\Rule\CustomerFactory;
use Magento\Quote\Model\Quote\TotalsCollector;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Model\Api\UpdateCartContext;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;

/**
 * Trait UpdateDiscountTrait
 * 
 * @package Bolt\Boltpay\Model\Api
 */
trait UpdateDiscountTrait
{  
    /**
     * @var RuleRepository
     */
    protected $ruleRepository;

    /**
     * @var LogHelper
     */
    protected $logHelper;

    /**
     * @var UsageFactory
     */
    protected $usageFactory;

    /**
     * @var DataObjectFactory
     */
    protected $objectFactory;

    /**
     * @var TimezoneInterface
     */
    protected $timezone;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var Bugsnag
     */
    protected $bugsnag;

    /**
     * @var DiscountHelper
     */
    protected $discountHelper;

    /**
     * @var TotalsCollector
     */
    protected $totalsCollector;
    
    /**
     * @var EventsForThirdPartyModules
     */
    protected $eventsForThirdPartyModules;

    /**
     * UpdateDiscountTrait constructor.
     *
     * @param UpdateCartContext $updateCartContext
     */
    public function __construct(
        UpdateCartContext $updateCartContext
    ) {
        $this->ruleRepository = $updateCartContext->getRuleRepository();
        $this->logHelper = $updateCartContext->getLogHelper();
        $this->usageFactory = $updateCartContext->getUsageFactory();
        $this->objectFactory = $updateCartContext->getObjectFactory();
        $this->timezone = $updateCartContext->getTimezone();
        $this->customerFactory = $updateCartContext->getCustomerFactory();
        $this->bugsnag = $updateCartContext->getBugsnag();
        $this->discountHelper = $updateCartContext->getDiscountHelper();
        $this->totalsCollector = $updateCartContext->getTotalsCollector();
        $this->eventsForThirdPartyModules = $updateCartContext->getEventsForThirdPartyModules();
    }
    
    /**
     * Verify if the code is coupon or gift card and return proper object
     *
     * @param string $couponCode
     * @param string|int $websiteId
     * @param string|int $storeId
     *
     * @return object|null
     */
    protected function verifyCouponCode( $couponCode, $websiteId, $storeId )
    {
        // Check if empty coupon was sent
        if ($couponCode === '') {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_CODE_INVALID,
                'No coupon code provided',
                422
            );

            return false;
        }

        // Load the Magento_GiftCardAccount object
        $giftCard = $this->discountHelper->loadMagentoGiftCardAccount($couponCode, $websiteId);

        // Load Amasty Gift Card account object
        if (empty($giftCard)) {
            $giftCard = $this->discountHelper->loadAmastyGiftCard($couponCode, $websiteId);
        }
        
        if (empty($giftCard)) {
            $giftCard = $this->eventsForThirdPartyModules->runFilter("loadGiftcard", null, $couponCode, $storeId);
        }

        $coupon = null;
        if (empty($giftCard)) {
            // Load the coupon
            $coupon = $this->discountHelper->loadCouponCodeData($couponCode);
        }

        // Check if the coupon and gift card does not exist.
        if ((empty($coupon) || $coupon->isObjectNew()) && empty($giftCard)) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_CODE_INVALID,
                sprintf('The coupon code %s is not found', $couponCode),
                422
            );

            return false;
        }
        
        return [$coupon, $giftCard];
    }
    
    /**
     * Apply discount to quote
     *
     * @param string $couponCode
     * @param object $coupon
     * @param object $giftCard
     * @param Quote $quote
     *
     * @return boolean
     */
    protected function applyDiscount( $couponCode, $coupon, $giftCard, $quote )
    {
        if ($coupon && $coupon->getCouponId()) {
            $result = $this->applyingCouponCode($couponCode, $coupon, $quote);
        } elseif ($giftCard && $giftCard->getId()) {
            $result = $this->applyingGiftCardCode($couponCode, $giftCard, $quote);
        } else {
            throw new WebApiException(__('Something happened with current code.'));
        }
        
        return $result;
    }

    /**
     * Applying coupon code to quote.
     *
     * @param string $couponCode
     * @param Coupon $coupon
     * @param Quote  $quote
     *
     * @return array|false
     * @throws LocalizedException
     * @throws \Exception
     */
    protected function applyingCouponCode($couponCode, $coupon, $quote, $addQuote = null)
    {
        // get coupon entity id and load the coupon discount rule
        $couponId = $coupon->getId();
        try {
            /** @var \Magento\SalesRule\Model\Rule $rule */
            $rule = $this->ruleRepository->getById($coupon->getRuleId());
        } catch (NoSuchEntityException $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_CODE_INVALID,
                sprintf('The coupon code %s is not found', $couponCode),
                422,
                $quote
            );

             return false;
        }
        $websiteId = $quote->getStore()->getWebsiteId();
        $ruleWebsiteIDs = $rule->getWebsiteIds();

        if (!in_array($websiteId, $ruleWebsiteIDs)) {
            $this->logHelper->addInfoLog('Error: coupon from another website.');
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_CODE_INVALID,
                sprintf('The coupon code %s is not found', $couponCode),
                422,
                $quote
            );

            return false;
        }

        // get the rule id
        $ruleId = $rule->getRuleId();

        // Check date validity if "To" date is set for the rule
        $date = $rule->getToDate();
        if ($date && date('Y-m-d', strtotime($date)) < date('Y-m-d')) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_CODE_EXPIRED,
                sprintf('The code [%s] has expired.', $couponCode),
                422,
                $quote
            );

            return false;
        }

        // Check date validity if "From" date is set for the rule
        $date = $rule->getFromDate();
        if ($date && date('Y-m-d', strtotime($date)) > date('Y-m-d')) {
            $desc = 'Code available from ' . $this->timezone->formatDate(
                new \DateTime($rule->getFromDate()),
                \IntlDateFormatter::MEDIUM
            );
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_CODE_NOT_AVAILABLE,
                $desc,
                422,
                $quote
            );

            return false;
        }

        // Check coupon usage limits.
        if ($coupon->getUsageLimit() && $coupon->getTimesUsed() >= $coupon->getUsageLimit()) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_CODE_LIMIT_REACHED,
                sprintf('The code [%s] has exceeded usage limit.', $couponCode),
                422,
                $quote
            );

            return false;
        }

        // Check per customer usage limits
        if ($customerId = $quote->getCustomerId()) {
            // coupon per customer usage
            if ($usagePerCustomer = $coupon->getUsagePerCustomer()) {
                $couponUsage = $this->objectFactory->create();
                $this->usageFactory->create()->loadByCustomerCoupon(
                    $couponUsage,
                    $customerId,
                    $couponId
                );
                if ($couponUsage->getCouponId() && $couponUsage->getTimesUsed() >= $usagePerCustomer) {
                    $this->sendErrorResponse(
                        BoltErrorResponse::ERR_CODE_LIMIT_REACHED,
                        sprintf('The code [%s] has exceeded usage limit.', $couponCode),
                        422,
                        $quote
                    );

                    return false;
                }
            }
            // rule per customer usage
            if ($usesPerCustomer = $rule->getUsesPerCustomer()) {
                $ruleCustomer = $this->customerFactory->create()->loadByCustomerRule($customerId, $ruleId);
                if ($ruleCustomer->getId() && $ruleCustomer->getTimesUsed() >= $usesPerCustomer) {
                    $this->sendErrorResponse(
                        BoltErrorResponse::ERR_CODE_LIMIT_REACHED,
                        sprintf('The code [%s] has exceeded usage limit.', $couponCode),
                        422,
                        $quote
                    );

                    return false;
                }
            }
        } else {
            // If coupon requires logged-in users and our user is guest show special error
            $groupIds = $rule->getCustomerGroupIds();
            if (!in_array(0, $groupIds)) {
                $this->logHelper->addInfoLog('Error: coupon requires login.');
                $this->sendErrorResponse(
                    BoltErrorResponse::ERR_CODE_REQUIRES_LOGIN,
                    sprintf('The coupon code %s requires login', $couponCode),
                    422,
                    $quote
                );
                return false;
            }
        }

        try {
            if (!is_null($addQuote)) {
                $this->discountHelper->setCouponCode($addQuote, $couponCode);
            }

            $this->discountHelper->setCouponCode($quote, $couponCode);
        } catch (\Exception $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                422,
                $quote
            );

            return false;
        }

        if ($quote->getCouponCode() != $couponCode) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                __('Coupon code does not equal with a quote code!'),
                422,
                $quote
            );
            return false;
        }

        $address = $quote->isVirtual() ?
            $quote->getBillingAddress() :
            $quote->getShippingAddress();

        $result = [
            'status'          => 'success',
            'discount_code'   => $couponCode,
            'discount_amount' => abs(CurrencyUtils::toMinor($address->getDiscountAmount(), $quote->getQuoteCurrencyCode())),
            'description'     => trim(__('Discount ') . $rule->getDescription()),
            'discount_type'   => $this->discountHelper->convertToBoltDiscountType($couponCode),
        ];
       
        return $result;
    }

    /**
     * @param string $couponCode
     * @param object $giftCard
     * @param Quote $quote
     * 
     * @return boolean
     * @throws \Exception
     */
    private function applyingGiftCardCode($couponCode, $giftCard, $quote)
    {
        try {
            $result = $this->eventsForThirdPartyModules->runFilter("filterApplyingGiftCardCode", false, $couponCode, $giftCard, $quote);

            if ($result) {
                return true;
            }

            if ($giftCard instanceof \Amasty\GiftCard\Model\Account || $giftCard instanceof \Amasty\GiftCardAccount\Model\GiftCardAccount\Account) {
                // Remove Amasty Gift Card if already applied
                // to avoid errors on multiple calls to discount validation API
                // from the Bolt checkout (changing the address, going back and forth)
                $this->discountHelper->removeAmastyGiftCard($giftCard->getCodeId(), $quote);
                // Apply Amasty Gift Card to the parent quote
                $giftAmount = $this->discountHelper->applyAmastyGiftCard($couponCode, $giftCard, $quote);
            } else {
                try {
                    // on subsequest validation calls from Bolt checkout
                    // try removing the gift card before adding it
                    $giftCard->removeFromCart(true, $quote);
                } catch (\Exception $e) {

                } finally {
                    $giftCard->addToCart(true, $quote);
                }
            }
        } catch (\Exception $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                422,
                $quote
            );

            return false;
        }

        return true;
    }
    
    /**
     * Remove discount from quote
     *
     * @param string $couponCode
     * @param array $discounts
     * @param Quote $quote
     * @param string|int $websiteId
     * @param string|int $storeId
     *
     * @return boolean
     */
    protected function removeDiscount($couponCode, $discounts, $quote, $websiteId, $storeId)
    {
        try{
            if(array_key_exists($couponCode, $discounts)){
                if ($discounts[$couponCode] == 'coupon') {
                    $this->removeCouponCode($quote);
                } else if ($discounts[$couponCode] == DiscountHelper::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT) {
                    $this->eventsForThirdPartyModules->dispatchEvent("removeAppliedStoreCredit", $couponCode, $quote, $websiteId, $storeId);
                } else {
                    $result = $this->verifyCouponCode($couponCode, $websiteId, $storeId);        
                    list(, $giftCard) = $result;
                    $this->removeGiftCardCode($couponCode, $giftCard, $quote);
                }
            } else {
                throw new \Exception(__('Coupon code %1 does not exist!', $couponCode));
            }
        } catch (\Exception $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                422,
                $quote
            );

            return false;
        }
        
        return true;
    }
    
    /**
     * Remove coupon from quote
     *
     * @param Quote $quote
     *
     * @return boolean
     */
    protected function removeCouponCode($quote)
    {
        try {
            $this->discountHelper->setCouponCode($quote, '');
        } catch (\Exception $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                422,
                $quote
            );

            return false;
        }

        return true;
    }
    
    /**
     * Remove gift card from quote
     *
     * @param string $couponCode
     * @param object $giftCard
     * @param Quote $quote
     *
     * @return boolean
     */
    protected function removeGiftCardCode($couponCode, $giftCard, $quote)
    {
        try {
            $filterRemoveGiftCardCode = $this->eventsForThirdPartyModules->runFilter("filterRemovingGiftCardCode", false, $giftCard, $quote);
            if ($filterRemoveGiftCardCode) {
                return true;
            }

            if ($giftCard instanceof \Amasty\GiftCard\Model\Account || $giftCard instanceof \Amasty\GiftCardAccount\Model\GiftCardAccount\Account) {              
                $this->discountHelper->removeAmastyGiftCard($giftCard->getCodeId(), $quote);
            } elseif ($giftCard instanceof \Magento\GiftCardAccount\Model\Giftcardaccount) {
                $giftCard->removeFromCart(true, $quote);
            } else {
                throw new \Exception(__('The GiftCard %1 does not support removal', $couponCode));             
            }
        } catch (\Exception $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                422,
                $quote
            );

            return false;
        }

        return true;
    }
    
    /**
     *
     * @param string $couponCode
     * @param Quote $quote
     *
     * @return array|false
     */
    protected function getAppliedStoreCredit($couponCode, $quote)
    {
        try {
            $availableStoreCredits = $this->eventsForThirdPartyModules->runFilter("filterVerifyAppliedStoreCredit", [], $couponCode, $quote);
 
            if (in_array($couponCode, $availableStoreCredits)) {
                return [
                    [
                        'discount_category' => DiscountHelper::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                        'reference'         => $couponCode,
                    ]
                ];
            } else {
                return false;
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);

            return false;
        }
    }

}
