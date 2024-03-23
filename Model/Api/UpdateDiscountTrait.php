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
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Helper\Session as SessionHelper;

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
     * @var DataObjectFactory|mixed
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
     * @var SessionHelper
     */
    protected $sessionHelper;

    /**
     * @var EventsForThirdPartyModules
     */
    protected $eventsForThirdPartyModules;

    /**
     * @var CartHelper
     */
    protected $cartHelper;

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
        $this->sessionHelper = $updateCartContext->getSessionHelper();
        $this->eventsForThirdPartyModules = $updateCartContext->getEventsForThirdPartyModules();
        $this->cartHelper = $updateCartContext->getCartHelper();
    }

    /**
     * Verify if the code is coupon or gift card and return proper object
     *
     * @param string $couponCode
     * @param Quote $quote
     *
     * @throws BoltException
     *
     * @return object|null
     */
    protected function verifyCouponCode($couponCode, $quote)
    {
        // Check if empty coupon was sent
        if ($couponCode === '') {
            throw new BoltException(
                __('No coupon code provided'), // @phpstan-ignore-line
                null,
                BoltErrorResponse::ERR_CODE_INVALID
            );
        }

        $storeId = $quote->getStoreId();
        $websiteId = $quote->getStore()->getWebsiteId();

        // Load the Unirgy_GiftCert object
        $giftCard = $this->discountHelper->loadUnirgyGiftCertData($couponCode, $storeId);


        if (empty($giftCard)) {
            $giftCard = $this->eventsForThirdPartyModules->runFilter("loadGiftcard", null, $couponCode, $quote);
            if ($giftCard instanceof \Exception) {
                throw new BoltException(
                    __($giftCard->getMessage()),// @phpstan-ignore-line
                    null,
                    BoltErrorResponse::ERR_SERVICE
                );
            }
        }

        $coupon = null;
        if (empty($giftCard)) {
            // Load the coupon
            $coupon = $this->discountHelper->loadCouponCodeData($couponCode);
        }

        // Check if the coupon and gift card does not exist.
        if ((empty($coupon) || $coupon->isObjectNew()) && empty($giftCard)) {
            throw new BoltException(
                __(sprintf('The coupon code %s is not found', $couponCode)),// @phpstan-ignore-line
                null,
                BoltErrorResponse::ERR_CODE_INVALID
            );
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
    protected function applyDiscount($couponCode, $coupon, $giftCard, $quote)
    {
        if ($coupon && ($coupon->getCouponId() || $this->eventsForThirdPartyModules->runFilter("isValidCouponObj", false, $coupon, $couponCode))) {
            $result = $this->applyingCouponCode($couponCode, $coupon, $quote);
        } elseif ($giftCard && $giftCard->getId()) {
            $result = $this->applyGiftCardCode($couponCode, $giftCard, $quote);
        } else {
            throw new WebApiException(__('Something happened with current code.')); // @phpstan-ignore-line
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
        $result = $this->eventsForThirdPartyModules->runFilter(
            "filterApplyingCouponCode",
            false,
            $couponCode,
            $coupon,
            $quote,
            $addQuote
        );
        
        if ($result) {
            return $result;
        }
        
        // get coupon entity id and load the coupon discount rule
        $couponId = $coupon->getId();
        try {
            /** @var \Magento\SalesRule\Model\Rule $rule */
            $rule = $this->ruleRepository->getById($coupon->getRuleId());
        } catch (NoSuchEntityException $e) {
            throw new BoltException(
                __('The coupon code %1 is not found', $couponCode), // @phpstan-ignore-line
                null,
                BoltErrorResponse::ERR_CODE_INVALID,
                $quote
            );
        }
        $websiteId = $quote->getStore()->getWebsiteId();
        $ruleWebsiteIDs = $rule->getWebsiteIds();

        if (!in_array($websiteId, $ruleWebsiteIDs)) {
            $this->logHelper->addInfoLog('Error: coupon from another website.');
            throw new BoltException(
                __('The coupon code %1 is not found', $couponCode), // @phpstan-ignore-line
                null,
                BoltErrorResponse::ERR_CODE_INVALID,
                $quote
            );
        }

        if (!$rule->getIsActive()) {
            $this->logHelper->addInfoLog('Error: coupon is inactive.');
            throw new BoltException(
                __('The coupon code %1 is inactive', $couponCode), // @phpstan-ignore-line
                null,
                BoltErrorResponse::ERR_CODE_INVALID,
                $quote
            );
        }

        // get the rule id
        $ruleId = $rule->getRuleId();

        if ($this->eventsForThirdPartyModules->runFilter("verifyRuleTimeFrame", true)) {
            // Check date validity if "To" date is set for the rule
            $date = $rule->getToDate();
            if ($date && date('Y-m-d', strtotime($date)) < date('Y-m-d')) {
                throw new BoltException(
                    __('The code [%1] has expired', $couponCode), // @phpstan-ignore-line
                    null,
                    BoltErrorResponse::ERR_CODE_EXPIRED,
                    $quote
                );
            }

            // Check date validity if "From" date is set for the rule
            $date = $rule->getFromDate();
            if ($date && date('Y-m-d', strtotime($date)) > date('Y-m-d')) {
                $desc = 'Code available from ' . $this->timezone->formatDate(
                    new \DateTime($rule->getFromDate()),
                    \IntlDateFormatter::MEDIUM
                );
                throw new BoltException(
                    __($desc), // @phpstan-ignore-line
                    null,
                    BoltErrorResponse::ERR_CODE_NOT_AVAILABLE,
                    $quote
                );
            }
        }

        // Check coupon usage limits.
        if ($coupon->getUsageLimit() && $coupon->getTimesUsed() >= $coupon->getUsageLimit()) {
            throw new BoltException(
                __('The code [%1] has exceeded usage limit.', $couponCode), // @phpstan-ignore-line
                null,
                BoltErrorResponse::ERR_CODE_LIMIT_REACHED,
                $quote
            );
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
                    throw new BoltException(
                        __('The code [%1] has exceeded usage limit', $couponCode), // @phpstan-ignore-line
                        null,
                        BoltErrorResponse::ERR_CODE_LIMIT_REACHED,
                        $quote
                    );
                }
            }
            // rule per customer usage
            if ($usesPerCustomer = $rule->getUsesPerCustomer()) {
                $ruleCustomer = $this->customerFactory->create()->loadByCustomerRule($customerId, $ruleId);
                if ($ruleCustomer->getId() && $ruleCustomer->getTimesUsed() >= $usesPerCustomer) {
                    throw new BoltException(
                        __('The code [%1] has exceeded usage limit', $couponCode), // @phpstan-ignore-line
                        null,
                        BoltErrorResponse::ERR_CODE_LIMIT_REACHED,
                        $quote
                    );
                }
            }
        } else {
            // If coupon requires logged-in users and our user is guest show special error
            $groupIds = $rule->getCustomerGroupIds();
            if (!in_array(0, $groupIds)) {
                $this->logHelper->addInfoLog('Error: coupon requires login.');
                throw new BoltException(
                    __('The coupon code %1 requires login', $couponCode), // @phpstan-ignore-line
                    null,
                    BoltErrorResponse::ERR_CODE_REQUIRES_LOGIN,
                    $quote
                );
            }
        }

        if (!is_null($addQuote)) {
            $this->discountHelper->setCouponCode($addQuote, $couponCode);
        }

        $this->discountHelper->setCouponCode($quote, $couponCode);

        if ($quote->getCouponCode() === '') {
            $this->logHelper->addInfoLog('Error: quote does not meet rule\'s conditions.');
            throw new BoltException(
                __('The quote does not meet rule\'s conditions of coupon code %1.', $couponCode), // @phpstan-ignore-line
                null,
                BoltErrorResponse::ERR_CODE_INVALID,
                $quote
            );
        }

        if ($quote->getCouponCode() != $couponCode) {
            throw new BoltException(
                __('Coupon code %1 does not equal with the quote code %2.', $couponCode, $quote->getCouponCode()), // @phpstan-ignore-line
                null,
                BoltErrorResponse::ERR_SERVICE,
                $quote
            );
        }

        $ruleDiscountDetails = $this->eventsForThirdPartyModules->runFilter(
            "filterGetBoltCollectSaleRuleDiscounts",
            $this->cartHelper->getSaleRuleDiscounts($quote),
            $rule
        );

        if (!isset($ruleDiscountDetails[$ruleId])) {
            $this->discountHelper->setCouponCode($quote, '');
            throw new BoltException(
                __('Failed to apply the coupon code %1', $couponCode), // @phpstan-ignore-line
                null,
                BoltErrorResponse::ERR_SERVICE,
                $quote
            );
        }
        
        $description = $rule->getDescription();
        $display = $description != '' ? $description : 'Discount (' . $couponCode . ')';

        $result = [
            'status'          => 'success',
            'discount_code'   => $couponCode,
            'discount_amount' => abs(CurrencyUtils::toMinor($ruleDiscountDetails[$ruleId], $quote->getQuoteCurrencyCode())),
            'description'     => $display,
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
     * @throws BoltException
     */
    private function applyGiftCardCode($couponCode, $giftCard, $quote)
    {
        try {
            $result = $this->eventsForThirdPartyModules->runFilter(
                "filterApplyingGiftCardCode",
                false,
                $couponCode,
                $giftCard,
                $quote
            );

            if ($result) {
                return true;
            }
        } catch (\Exception $e) {
            throw new BoltException(
                $e->getMessage(),
                null,
                BoltErrorResponse::ERR_SERVICE
            );
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
        try {
            if (array_key_exists($couponCode, $discounts)) {
                if ($discounts[$couponCode] == 'coupon') {
                    //sends response
                    $this->removeCouponCode($quote);
                } elseif ($discounts[$couponCode] == DiscountHelper::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT) {
                    //handles exceptions already, no return value
                    $this->eventsForThirdPartyModules->dispatchEvent(
                        "removeAppliedStoreCredit",
                        $couponCode,
                        $quote,
                        $websiteId,
                        $storeId
                    );
                } else {
                    //throws BoltException that will be caught in UpdateCart::execute()
                    $result = $this->verifyCouponCode($couponCode, $quote);
                    list(, $giftCard) = $result;
                    //sends response
                    $this->removeGiftCardCode($couponCode, $giftCard, $quote);
                }
            } else {
                throw new \Exception(__('Coupon code %1 does not exist!', $couponCode)); // @phpstan-ignore-line
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
            $filterRemoveGiftCardCode = $this->eventsForThirdPartyModules->runFilter(
                "filterRemovingGiftCardCode",
                false,
                $giftCard,
                $quote
            );
            if ($filterRemoveGiftCardCode) {
                return true;
            }

            throw new \Exception(__('Failed to apply the GiftCard %1', $couponCode)); // @phpstan-ignore-line
        } catch (\Exception $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                422,
                $quote
            );

            return false;
        }
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
            $this->bugsnag->notifyException($e);

            return false;
        }
    }
}
