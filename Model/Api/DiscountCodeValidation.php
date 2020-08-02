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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebApiException;
use Magento\Quote\Model\Quote;
use Magento\SalesRule\Model\Coupon;
use Magento\SalesRule\Model\RuleRepository;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\SalesRule\Model\ResourceModel\Coupon\UsageFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\SalesRule\Model\Rule\CustomerFactory;
use Bolt\Boltpay\Api\DiscountCodeValidationInterface;
use Bolt\Boltpay\Model\Api\UpdateCartCommon;
use Bolt\Boltpay\Model\Api\UpdateCartContext;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;

/**
 * Discount Code Validation class
 * @api
 */
class DiscountCodeValidation extends UpdateCartCommon implements DiscountCodeValidationInterface
{
    /**
     * @var RuleRepository
     */
    protected $ruleRepository;

    /**
     * @var UsageFactory
     */
    private $usageFactory;

    /**
     * @var DataObjectFactory
     */
    protected $objectFactory;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var CheckoutSession
     */
    private $checkoutSessionForUnirgyGiftCert;

    /**
     * @var DiscountHelper
     */
    private $discountHelper;

    /**
     * @var TotalsCollector
     */
    private $totalsCollector;

    /**
     * DiscountCodeValidation constructor.
     *
     * @param CheckoutSession         $checkoutSessionForUnirgyGiftCert
     * @param RuleRepository          $ruleRepository
     * @param UsageFactory            $usageFactory
     * @param DataObjectFactory       $objectFactory
     * @param TimezoneInterface       $timezone
     * @param CustomerFactory         $customerFactory
     * @param ConfigHelper            $configHelper
     * @param DiscountHelper          $discountHelper
     * @param TotalsCollector         $totalsCollector
     * @param UpdateCartContext       $updateCartContext
     */
    public function __construct(
        CheckoutSession $checkoutSessionForUnirgyGiftCert,
        UpdateCartContext $updateCartContext
    ) {
        parent::__construct($updateCartContext);
        $this->checkoutSessionForUnirgyGiftCert = $checkoutSessionForUnirgyGiftCert;
        $this->ruleRepository = $updateCartContext->getRuleRepository();
        $this->usageFactory = $updateCartContext->getUsageFactory();
        $this->objectFactory = $updateCartContext->getObjectFactory();
        $this->timezone = $updateCartContext->getTimezone();
        $this->customerFactory = $updateCartContext->getCustomerFactory();
        $this->configHelper = $updateCartContext->getConfigHelper();
        $this->discountHelper = $updateCartContext->getDiscountHelper();
        $this->totalsCollector = $updateCartContext->getTotalsCollector();
    }

    /**
     * @api
     * @return bool
     * @throws \Exception
     */
    public function validate()
    {
        try {
            $request = $this->getRequestContent();

            $requestArray = json_decode(json_encode($request), true);
            
            $parentQuoteId = (isset($requestArray['cart']['order_reference'])) ? $requestArray['cart']['order_reference'] : '';
            $displayId = isset($requestArray['cart']['display_id']) ? $requestArray['cart']['display_id'] : '';
            list($incrementId, $immutableQuoteId) = array_pad(
                explode(' / ', $displayId),
                2,
                null
            );
            
            $result = $this->validateQuote($parentQuoteId, $immutableQuoteId, $incrementId);
            
            if( ! $result ){
                return false;
            }
            
            list($parentQuote, $immutableQuote) = $result;

            $storeId = $parentQuote->getStoreId();
            $websiteId = $parentQuote->getStore()->getWebsiteId();

            $this->preProcessWebhook($storeId);
            $parentQuote->getStore()->setCurrentCurrencyCode($parentQuote->getQuoteCurrencyCode());

            // get the coupon code
            $discount_code = @$request->discount_code ?: @$request->cart->discount_code;
            $couponCode = trim($discount_code);

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

            // Load Unirgy_GiftCert object
            if (empty($giftCard)) {
                $giftCard = $this->discountHelper->loadUnirgyGiftCert($couponCode, $storeId);
            }

            // Load Amasty Gift Card account object
            if (empty($giftCard)) {
                $giftCard = $this->discountHelper->loadAmastyGiftCard($couponCode, $websiteId);
            }

            // Apply Mageplaza_GiftCard
            if (empty($giftCard)) {
                // Load the gift card by code
                $giftCard = $this->discountHelper->loadMageplazaGiftCard($couponCode, $storeId);
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
                    404
                );

                return false;
            }

            // check if the order has already been created
            if ($this->orderHelper->getExistingOrder($incrementId)) {
                $this->sendErrorResponse(
                    BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                    sprintf('The order #%s has already been created.', $incrementId),
                    422
                );
                return false;
            }

            // check the existence of child quote
            /** @var Quote $immutableQuote */
            $immutableQuote = $this->cartHelper->getQuoteById($immutableQuoteId);
            if (!$immutableQuote) {
                $this->sendErrorResponse(
                    BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                    sprintf('The cart reference [%s] is not found.', $immutableQuoteId),
                    404
                );
                return false;
            }

            // check if cart is empty
            if (!$immutableQuote->getItemsCount()) {
                $this->sendErrorResponse(
                    BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                    sprintf('The cart for order reference [%s] is empty.', $immutableQuoteId),
                    422
                );

                return false;
            }

            // Set the shipment if request payload has that info.
            if (isset($requestArray['cart']['shipments'][0]['reference'])) {
                $this->setShipment($requestArray['cart']['shipments'][0], $immutableQuote);
            }

            if ($coupon && $coupon->getCouponId()) {
                if ($this->shouldUseParentQuoteShippingAddressDiscount($couponCode, $immutableQuote, $parentQuote)) {
                    $result = $this->getParentQuoteDiscountResult($couponCode, $coupon, $parentQuote);
                } else {
                    $result = $this->applyingCouponCode($couponCode, $coupon, $immutableQuote, $parentQuote);
                }
            } elseif ($giftCard && $giftCard->getId()) {
                $result = $this->applyingGiftCardCode($couponCode, $giftCard, $immutableQuote, $parentQuote);
            } else {
                throw new WebApiException(__('Something happened with current code.'));
            }

            if (!$result || (isset($result['status']) && $result['status'] === 'error')) {
                // Already sent a response with error, so just return.
                return false;
            }
            
            $result['cart'] = $this->getCartTotals($immutableQuote);
            $this->sendSuccessResponse($result);
        } catch (WebApiException $e) {
            $this->bugsnag->notifyException($e);
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                $e->getHttpCode(),
                ($immutableQuote) ? $immutableQuote : null
            );

            return false;
        } catch (LocalizedException $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                500
            );

            return false;
        } catch (\Exception $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                500
            );

            return false;
        }

        return true;
    }

    /**
     * Applying coupon code to immutable and parent quote.
     *
     * @param string $couponCode
     * @param Coupon $coupon
     * @param Quote  $immutableQuote
     * @param Quote  $parentQuote
     *
     * @return array|false
     * @throws LocalizedException
     * @throws \Exception
     */
    private function applyingCouponCode($couponCode, $coupon, $immutableQuote, $parentQuote)
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
                404
            );

             return false;
        }
        $websiteId = $parentQuote->getStore()->getWebsiteId();
        $ruleWebsiteIDs = $rule->getWebsiteIds();

        if (!in_array($websiteId, $ruleWebsiteIDs)) {
            $this->logHelper->addInfoLog('Error: coupon from another website.');
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_CODE_INVALID,
                sprintf('The coupon code %s is not found', $couponCode),
                404
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
                $immutableQuote
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
                $immutableQuote
            );

            return false;
        }

        // Check coupon usage limits.
        if ($coupon->getUsageLimit() && $coupon->getTimesUsed() >= $coupon->getUsageLimit()) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_CODE_LIMIT_REACHED,
                sprintf('The code [%s] has exceeded usage limit.', $couponCode),
                422,
                $immutableQuote
            );

            return false;
        }

        // Check per customer usage limits
        if ($customerId = $immutableQuote->getCustomerId()) {
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
                        $immutableQuote
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
                        $immutableQuote
                    );

                    return false;
                }
            }
        }

        try {
            // try applying to parent first
            $this->discountHelper->setCouponCode($parentQuote, $couponCode);
            // apply coupon to clone
            $this->discountHelper->setCouponCode($immutableQuote, $couponCode);
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                422,
                $immutableQuote
            );

            return false;
        }

        if ($immutableQuote->getCouponCode() != $couponCode) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                __('Coupon code does not equal with a quote code!'),
                422,
                $immutableQuote
            );
            return false;
        }

        $address = $immutableQuote->isVirtual() ?
            $immutableQuote->getBillingAddress() :
            $immutableQuote->getShippingAddress();
        $this->totalsCollector->collectAddressTotals($immutableQuote, $address);

        $result = [
            'status'          => 'success',
            'discount_code'   => $couponCode,
            'discount_amount' => abs(CurrencyUtils::toMinor($address->getDiscountAmount(), $immutableQuote->getQuoteCurrencyCode())),
            'description'     => trim(__('Discount ') . $rule->getDescription()),
            'discount_type'   => $this->discountHelper->convertToBoltDiscountType($couponCode),
        ];

        $this->logHelper->addInfoLog('### Coupon Result');
        $this->logHelper->addInfoLog(json_encode($result));

        return $result;
    }

    /**
     * @param string $code
     * @param object $giftCard
     * @param Quote $immutableQuote
     * @param Quote $parentQuote
     * @return array
     * @throws \Exception
     */
    private function applyingGiftCardCode($code, $giftCard, $immutableQuote, $parentQuote)
    {
        try {
            if ($giftCard instanceof \Amasty\GiftCard\Model\Account || $giftCard instanceof \Amasty\GiftCardAccount\Model\GiftCardAccount\Account) {
                // Remove Amasty Gift Card if already applied
                // to avoid errors on multiple calls to discount validation API
                // from the Bolt checkout (changing the address, going back and forth)
                $this->discountHelper->removeAmastyGiftCard($giftCard->getCodeId(), $parentQuote);
                // Apply Amasty Gift Card to the parent quote
                $giftAmount = $this->discountHelper->applyAmastyGiftCard($code, $giftCard, $parentQuote);
                // Reset and apply Amasty Gift Cards to the immutable quote
                $this->discountHelper->cloneAmastyGiftCards($parentQuote->getId(), $immutableQuote->getId());
            } elseif ($giftCard instanceof \Unirgy\Giftcert\Model\Cert) {
                $this->discountHelper->applyUnirgyGiftCert($giftCard, $immutableQuote);
                $this->discountHelper->applyUnirgyGiftCert($giftCard, $parentQuote);
                // The Unirgy_GiftCert require double call the function addCertificate().
                // Look on Unirgy/Giftcert/Controller/Checkout/Add::execute()
                $checkoutSession = $this->checkoutSessionForUnirgyGiftCert;
                $this->discountHelper->applyUnirgyGiftCert($giftCard, $checkoutSession->getQuote());
                
                $giftAmount = $giftCard->getBalance();
            } elseif ($giftCard instanceof \Mageplaza\GiftCard\Model\GiftCard) {
                // Remove Mageplaza Gift Card if it was already applied
                // to avoid errors on multiple calls to the discount validation API
                // (e.g. changing the address, going back and forth)
                $this->discountHelper->removeMageplazaGiftCard($giftCard->getId(), $immutableQuote);
                $this->discountHelper->removeMageplazaGiftCard($giftCard->getId(), $parentQuote);

                // Apply Mageplaza Gift Card to the parent quote
                $this->discountHelper->applyMageplazaGiftCard($code, $immutableQuote);
                $this->discountHelper->applyMageplazaGiftCard($code, $parentQuote);

                $giftAmount = $giftCard->getBalance();
            } else {
                if ($immutableQuote->getGiftCardsAmountUsed() == 0) {
                    try {
                        // on subsequest validation calls from Bolt checkout
                        // try removing the gift card before adding it
                        $giftCard->removeFromCart(true, $immutableQuote);
                    } catch (\Exception $e) {
                        // gift card not added yet
                    } finally {
                        $giftCard->addToCart(true, $immutableQuote);
                    }
                }

                if ($parentQuote->getGiftCardsAmountUsed() == 0) {
                    try {
                        // on subsequest validation calls from Bolt checkout
                        // try removing the gift card before adding it
                        $giftCard->removeFromCart(true, $parentQuote);
                    } catch (\Exception $e) {
                        // gift card not added yet
                    } finally {
                        $giftCard->addToCart(true, $parentQuote);
                    }
                }

                // Send the whole GiftCard Amount.
                $giftAmount = $parentQuote->getGiftCardsAmount();
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                422,
                $immutableQuote
            );

            return false;
        }

        $result = [
            'status'          => 'success',
            'discount_code'   => $code,
            'discount_amount' => abs(CurrencyUtils::toMinor($giftAmount, $immutableQuote->getQuoteCurrencyCode())),
            'description'     =>  __('Gift Card'),
            'discount_type'   => $this->discountHelper->getBoltDiscountType('by_fixed'),
        ];

        $this->logHelper->addInfoLog('### Gift Card Result');
        $this->logHelper->addInfoLog(json_encode($result));

        return $result;
    }

    /**
     * @param Quote $quote
     * @return array
     * @throws \Exception
     */
    private function getCartTotals($quote)
    {
        $request = $this->getRequestContent();
        $is_has_shipment = isset($request->cart->shipments[0]->reference);
        $cart = $this->cartHelper->getCartData($is_has_shipment, null, $quote);
        return [
            'total_amount' => $cart['total_amount'],
            'tax_amount'   => $cart['tax_amount'],
            'discounts'    => $cart['discounts'],
        ];
    }

    /**
     * @param int        $errCode
     * @param string     $message
     * @param int        $httpStatusCode
     * @param null|Quote $quote
     *
     * @return void
     * @throws \Exception
     */
    protected function sendErrorResponse($errCode, $message, $httpStatusCode, $quote = null)
    {
        $additionalErrorResponseData = [];
        if ($quote) {
            $additionalErrorResponseData['cart'] = $this->getCartTotals($quote);
        }

        $encodeErrorResult = $this->errorResponse
            ->prepareErrorMessage($errCode, $message, $additionalErrorResponseData);

        $this->logHelper->addInfoLog('### sendErrorResponse');
        $this->logHelper->addInfoLog($encodeErrorResult);

        $this->response->setHttpResponseCode($httpStatusCode);
        $this->response->setBody($encodeErrorResult);
        $this->response->sendResponse();
    }

    /**
     * @param array $result
     * @param Quote $quote
     * @return array
     * @throws \Exception
     */
    protected function sendSuccessResponse($result)
    {  
        $this->response->setBody(json_encode($result));
        $this->response->sendResponse();

        $this->logHelper->addInfoLog('### sendSuccessResponse');
        $this->logHelper->addInfoLog(json_encode($result));
        $this->logHelper->addInfoLog('=== END ===');

        return $result;
    }

    /**
     * @param string $couponCode
     * @param Quote  $immutableQuote
     * @param Quote  $parentQuote
     *
     * @return bool
     */
    protected function shouldUseParentQuoteShippingAddressDiscount(
        $couponCode,
        Quote $immutableQuote,
        Quote $parentQuote
    ) {
        $ignoredShippingAddressCoupons = $this->configHelper->getIgnoredShippingAddressCoupons(
            $parentQuote->getStoreId()
        );

        return $immutableQuote->getCouponCode() == $couponCode &&
               $immutableQuote->getCouponCode() == $parentQuote->getCouponCode() &&
               in_array($couponCode, $ignoredShippingAddressCoupons);
    }

    /**
     * @param string $couponCode
     * @param Quote  $parentQuote
     * @param Coupon $coupon
     *
     * @return array|false
     * @throws \Exception
     */
    protected function getParentQuoteDiscountResult($couponCode, $coupon, $parentQuote)
    {
        try {
            // Load the coupon discount rule
            $rule = $this->ruleRepository->getById($coupon->getRuleId());
        } catch (NoSuchEntityException $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_CODE_INVALID,
                sprintf('The coupon code %s is not found', $couponCode),
                404
            );

            return false;
        }

        $address = $parentQuote->isVirtual() ? $parentQuote->getBillingAddress() : $parentQuote->getShippingAddress();

        return $result = [
            'status'          => 'success',
            'discount_code'   => $couponCode,
            'discount_amount' => abs(CurrencyUtils::toMinor($address->getDiscountAmount(), $parentQuote->getQuoteCurrencyCode())),
            'description'     =>  __('Discount ') . $address->getDiscountDescription(),
            'discount_type'   => $this->discountHelper->convertToBoltDiscountType($couponCode),
        ];
    }
}
