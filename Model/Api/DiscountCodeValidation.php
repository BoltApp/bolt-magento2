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

use Bolt\Boltpay\Api\DiscountCodeValidationInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\SalesRule\Model\CouponFactory;
use Magento\SalesRule\Model\RuleFactory;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\SalesRule\Model\ResourceModel\Coupon\UsageFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\SalesRule\Model\Rule\CustomerFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Magento\Framework\Webapi\Exception as WebApiException;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\Quote\Api\CartRepositoryInterface as QuoteRepository;
use Magento\Checkout\Model\Session as CheckoutSession;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Quote\Model\Quote\TotalsCollector;

/**
 * Discount Code Validation class
 * @api
 */
class DiscountCodeValidation implements DiscountCodeValidationInterface
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var CouponFactory
     */
    protected $couponFactory;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $moduleGiftCardAccount;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $moduleUnirgyGiftCert;

    /**
     * @var RuleFactory
     */
    protected $ruleFactory;

    /**
     * @var LogHelper
     */
    private $logHelper;

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
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var HookHelper
     */
    private $hookHelper;

    /**
     * @var BoltErrorResponse
     */
    private $errorResponse;

    /**
     * @var ThirdPartyModuleFactory|\Unirgy\Giftcert\Helper\Data
     */
    private $moduleUnirgyGiftCertHelper;
    /**
     * @var QuoteRepository
     */
    private $quoteRepositoryForUnirgyGiftCert;

    /**
     * @var CheckoutSession
     */
    private $checkoutSessionForUnirgyGiftCert;

    /**
     * @var DiscountHelper
     */
    private $discountHelper;
    
    /**
     * @var RegionModel
     */
    private $regionModel;
    
    /**
     * @var TotalsCollector
     */
    private $totalsCollector;

    /**
     * DiscountCodeValidation constructor.
     *
     * @param Request                 $request
     * @param Response                $response
     * @param CouponFactory           $couponFactory
     * @param ThirdPartyModuleFactory $moduleGiftCardAccount
     * @param ThirdPartyModuleFactory $moduleUnirgyGiftCert
     * @param ThirdPartyModuleFactory $moduleUnirgyGiftCertHelper
     * @param QuoteRepository         $quoteRepositoryForUnirgyGiftCert
     * @param CheckoutSession         $checkoutSessionForUnirgyGiftCert
     * @param RuleFactory             $ruleFactory
     * @param LogHelper               $logHelper
     * @param BoltErrorResponse       $errorResponse
     * @param UsageFactory            $usageFactory
     * @param DataObjectFactory       $objectFactory
     * @param TimezoneInterface       $timezone
     * @param CustomerFactory         $customerFactory
     * @param Bugsnag                 $bugsnag
     * @param CartHelper              $cartHelper
     * @param ConfigHelper            $configHelper
     * @param HookHelper              $hookHelper
     * @param DiscountHelper          $discountHelper
     * @param RegionModel             $regionModel
     * @param TotalsCollector         $totalsCollector
     */
    public function __construct(
        Request $request,
        Response $response,
        CouponFactory $couponFactory,
        ThirdPartyModuleFactory $moduleGiftCardAccount,
        ThirdPartyModuleFactory $moduleUnirgyGiftCert,
        ThirdPartyModuleFactory $moduleUnirgyGiftCertHelper,
        QuoteRepository $quoteRepositoryForUnirgyGiftCert,
        CheckoutSession $checkoutSessionForUnirgyGiftCert,
        RuleFactory $ruleFactory,
        LogHelper $logHelper,
        BoltErrorResponse $errorResponse,
        UsageFactory $usageFactory,
        DataObjectFactory $objectFactory,
        TimezoneInterface $timezone,
        CustomerFactory $customerFactory,
        Bugsnag $bugsnag,
        CartHelper $cartHelper,
        ConfigHelper $configHelper,
        HookHelper $hookHelper,
        DiscountHelper $discountHelper,
        RegionModel $regionModel,
        TotalsCollector $totalsCollector
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->couponFactory = $couponFactory;
        $this->moduleGiftCardAccount = $moduleGiftCardAccount;
        $this->moduleUnirgyGiftCert = $moduleUnirgyGiftCert;
        $this->moduleUnirgyGiftCertHelper = $moduleUnirgyGiftCertHelper;
        $this->quoteRepositoryForUnirgyGiftCert = $quoteRepositoryForUnirgyGiftCert;
        $this->checkoutSessionForUnirgyGiftCert = $checkoutSessionForUnirgyGiftCert;
        $this->ruleFactory = $ruleFactory;
        $this->logHelper = $logHelper;
        $this->usageFactory = $usageFactory;
        $this->objectFactory = $objectFactory;
        $this->timezone = $timezone;
        $this->customerFactory = $customerFactory;
        $this->bugsnag = $bugsnag;
        $this->cartHelper = $cartHelper;
        $this->configHelper = $configHelper;
        $this->hookHelper = $hookHelper;
        $this->errorResponse = $errorResponse;
        $this->discountHelper = $discountHelper;
        $this->regionModel = $regionModel;
        $this->totalsCollector = $totalsCollector;
    }

    /**
     * @api
     * @return bool
     * @throws \Exception
     */
    public function validate()
    {
        try {
            $this->hookHelper->setCommonMetaData();
            $this->hookHelper->setHeaders();

            $this->hookHelper->verifyWebhook();

            $request = $this->getRequestContent();

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

            // Load the coupon
            $coupon = $this->loadCouponCodeData($couponCode);

            $giftCard = null;
            if (empty($coupon) || $coupon->isObjectNew()) {
                // Load the gift card by code
                $giftCard = $this->loadGiftCardData($couponCode);
            }

            // Apply Unirgy_GiftCert
            if (empty($giftCard)) {
                // Load the gift cert by code
                $giftCard = $this->loadGiftCertData($couponCode);
            }

            // Load Amasty Gift Card account object
            if (empty($giftCard)) {
                $giftCard = $this->discountHelper->loadAmastyGiftCard($couponCode);
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

            // get parent quote id, order increment id and child quote id
            // the latter two are transmited as display_id field, separated by " / "
            $parentQuoteId = $request->cart->order_reference;
            list($incrementId, $immutableQuoteId) = array_pad(
                explode(' / ', $request->cart->display_id),
                2,
                null
            );

            // check if cart identification data is sent
            if (empty($parentQuoteId) || empty($incrementId) || empty($immutableQuoteId)) {
                $this->sendErrorResponse(
                    BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                    'The order reference is invalid.',
                    422
                );

                return false;
            }

            // check if the order has already been created
            if ($this->cartHelper->getOrderByIncrementId($incrementId)) {
                $this->sendErrorResponse(
                    BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                    sprintf('The order #%s has already been created.', $incrementId),
                    422
                );

                return false;
            }

            // check if the cart / quote exists and it is active
            try {
                /** @var Quote $parentQuote */
                $parentQuote = $this->cartHelper->getActiveQuoteById($parentQuoteId);
            } catch (\Exception $e) {
                $this->bugsnag->notifyException($e);
                $this->sendErrorResponse(
                    BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                    sprintf('The cart reference [%s] is not found.', $parentQuoteId),
                    404
                );
                return false;
            }

            // check the existence of child quote
            try {
                /** @var Quote $immutableQuote */
                $immutableQuote = $this->cartHelper->getQuoteById($immutableQuoteId);
            } catch (\Exception $e) {
                $this->bugsnag->notifyException($e);
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
            if(isset($request->cart->shipments[0]->reference)){
                $shippingAddress = $immutableQuote->getShippingAddress();
                $address = $request->cart->shipments[0]->shipping_address;
                $address = $this->cartHelper->handleSpecialAddressCases($address);
                $region = $this->regionModel->loadByName(@$address->region, @$address->country_code);
                $addressData = [
                            'firstname'    => @$address->first_name,
                            'lastname'     => @$address->last_name,
                            'street'       => trim(@$address->street_address1 . "\n" . @$address->street_address2),
                            'city'         => @$address->locality,
                            'country_id'   => @$address->country_code,
                            'region'       => @$address->region,
                            'postcode'     => @$address->postal_code,
                            'telephone'    => @$address->phone_number,
                            'region_id'    => $region ? $region->getId() : null,
                            'company'      => @$address->company,
                        ];
                if ($this->cartHelper->validateEmail(@$address->email_address)) {
                    $addressData['email'] = $address->email_address;
                }
                
                $shippingAddress->setShouldIgnoreValidation(true);
                $shippingAddress->addData($addressData);
                        
                $shippingAddress->setShippingMethod($request->cart->shipments[0]->reference)
                                ->setCollectShippingRates(true)
                                ->collectShippingRates()->save();               
            }

            if ($coupon && $coupon->getCouponId()) {
                if ($this->shouldUseParentQuoteShippingAddressDiscount($couponCode, $immutableQuote, $parentQuote)){
                    $result = $this->getParentQuoteDiscountResult($couponCode, $coupon, $parentQuote);
                }else{
                    $result = $this->applyingCouponCode($couponCode, $coupon, $immutableQuote, $parentQuote);
                }
            } elseif ($giftCard && $giftCard->getId()) {
                $result = $this->applyingGiftCardCode($couponCode, $giftCard, $immutableQuote, $parentQuote);
            } else {
                throw new WebApiException(__('Something happened with current code.'));
            }

            if (isset($result['status']) && $result['status'] === 'error') {
                // Already sent a response with error, so just return.
                return false;
            }

            $this->sendSuccessResponse($result, $immutableQuote);
        } catch (WebApiException $e) {
            $this->bugsnag->notifyException($e);
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                $e->getHttpCode(),
                @$immutableQuote
            );

            return false;
        } catch (LocalizedException $e) {
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
     * @return string
     */
    private function getRequestContent()
    {
        $this->logHelper->addInfoLog($this->request->getContent());

        return json_decode($this->request->getContent());
    }

    /**
     * @param $couponCode
     * @param $coupon
     * @param $immutableQuote
     * @param $parentQuote
     * @return array
     * @throws \Exception
     */
    private function applyingCouponCode($couponCode, $coupon, $immutableQuote, $parentQuote)
    {
        // get coupon entity id and load the coupon discount rule
        $couponId = $coupon->getId();
        $rule = $this->ruleFactory->create()->load($coupon->getRuleId());

        // check if the rule exists
        if (empty($rule) || $rule->isObjectNew()) {
            return $this->sendErrorResponse(
                BoltErrorResponse::ERR_CODE_INVALID,
                sprintf('The coupon code %s is not found', $couponCode),
                404
            );
        }

        // get the rule id
        $ruleId = $rule->getId();

        // Check date validity if "To" date is set for the rule
        $date = $rule->getToDate();
        if ($date && date('Y-m-d', strtotime($date)) < date('Y-m-d')) {
            return $this->sendErrorResponse(
                BoltErrorResponse::ERR_CODE_EXPIRED,
                sprintf('The code [%s] has expired.', $couponCode),
                422,
                $immutableQuote
            );
        }

        // Check date validity if "From" date is set for the rule
        $date = $rule->getFromDate();
        if ($date && date('Y-m-d', strtotime($date)) > date('Y-m-d')) {
            $desc = 'Code available from ' . $this->timezone->formatDate(
                new \DateTime($rule->getFromDate()),
                \IntlDateFormatter::MEDIUM
            );
            return $this->sendErrorResponse(
                BoltErrorResponse::ERR_CODE_NOT_AVAILABLE,
                $desc,
                422,
                $immutableQuote
            );
        }

        // Check coupon usage limits.
        if ($coupon->getUsageLimit() && $coupon->getTimesUsed() >= $coupon->getUsageLimit()) {
            return $this->sendErrorResponse(
                BoltErrorResponse::ERR_CODE_LIMIT_REACHED,
                sprintf('The code [%s] has exceeded usage limit.', $couponCode),
                422,
                $immutableQuote
            );
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
                    return $this->sendErrorResponse(
                        BoltErrorResponse::ERR_CODE_LIMIT_REACHED,
                        sprintf('The code [%s] has exceeded usage limit.', $couponCode),
                        422,
                        $immutableQuote
                    );
                }
            }
            // rule per customer usage
            if ($usesPerCustomer = $rule->getUsesPerCustomer()) {
                $ruleCustomer = $this->customerFactory->create()->loadByCustomerRule($customerId, $ruleId);
                if ($ruleCustomer->getId() && $ruleCustomer->getTimesUsed() >= $usesPerCustomer) {
                    return $this->sendErrorResponse(
                        BoltErrorResponse::ERR_CODE_LIMIT_REACHED,
                        sprintf('The code [%s] has exceeded usage limit.', $couponCode),
                        422,
                        $immutableQuote
                    );
                }
            }
        }

        try {
            // try applying to parent first
            $this->setCouponCode($parentQuote, $couponCode);
            // apply coupon to clone
            $this->setCouponCode($immutableQuote, $couponCode);
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            return $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                422,
                $immutableQuote
            );
        }

        if ($immutableQuote->getCouponCode() != $couponCode) {
            return $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                __('Coupon code does not equal with a quote code!'),
                422,
                $immutableQuote
            );
        }

        $address = $immutableQuote->isVirtual() ?
            $immutableQuote->getBillingAddress() :
            $immutableQuote->getShippingAddress();
        $this->totalsCollector->collectAddressTotals($immutableQuote, $address); 

        $result = [
            'status'          => 'success',
            'discount_code'   => $couponCode,
            'discount_amount' => abs($this->cartHelper->getRoundAmount($address->getDiscountAmount())),
            'description'     => trim( __('Discount ') . $rule->getDescription() ),
            'discount_type'   => $this->convertToBoltDiscountType($rule->getSimpleAction()),
        ];

        $this->logHelper->addInfoLog('### Coupon Result');
        $this->logHelper->addInfoLog(json_encode($result));

        return $result;
    }

    /**
     * @param $code
     * @param \Magento\GiftCardAccount\Model\Giftcardaccount|\Unirgy\Giftcert\Model\Cert $giftCard
     * @param Quote $immutableQuote
     * @param Quote $parentQuote
     * @return array
     * @throws \Exception
     */
    private function applyingGiftCardCode($code, $giftCard, $immutableQuote, $parentQuote)
    {
        try {
            if ($giftCard instanceof \Amasty\GiftCard\Model\Account) {
                // Remove Amasty Gift Card if already applied
                // to avoid errors on multiple calls to discount validation API
                // from the Bolt checkout (changing the address, going back and forth)
                $this->discountHelper->removeAmastyGiftCard($giftCard->getCodeId(), $parentQuote);
                // Apply Amasty Gift Card to the parent quote
                $giftAmount = $this->discountHelper->applyAmastyGiftCard($code, $giftCard, $parentQuote);
                // Reset and apply Amasty Gift Cards to the immutable quote
                $this->discountHelper->cloneAmastyGiftCards($parentQuote->getId(), $immutableQuote->getId());
            } elseif ($giftCard instanceof \Unirgy\Giftcert\Model\Cert) {
                /** @var \Unirgy\Giftcert\Helper\Data $unirgyHelper */
                $unirgyHelper = $this->moduleUnirgyGiftCertHelper->getInstance();
                /** @var CheckoutSession $checkoutSession */
                $checkoutSession = $this->checkoutSessionForUnirgyGiftCert;

                if (empty($immutableQuote->getData($giftCard::GIFTCERT_CODE))) {
                    $unirgyHelper->addCertificate(
                        $giftCard->getCertNumber(),
                        $immutableQuote,
                        $this->quoteRepositoryForUnirgyGiftCert
                    );
                }

                if (empty($parentQuote->getData($giftCard::GIFTCERT_CODE))) {
                    $unirgyHelper->addCertificate(
                        $giftCard->getCertNumber(),
                        $parentQuote,
                        $this->quoteRepositoryForUnirgyGiftCert
                    );
                }

                // The Unirgy_GiftCert require double call the function addCertificate().
                // Look on Unirgy/Giftcert/Controller/Checkout/Add::execute()
                $unirgyHelper->addCertificate(
                    $giftCard->getCertNumber(),
                    $checkoutSession->getQuote(),
                    $this->quoteRepositoryForUnirgyGiftCert
                );

                $giftAmount = $giftCard->getBalance();
            } else {
                if ($immutableQuote->getGiftCardsAmountUsed() == 0) {
                    $giftCard->addToCart(true, $immutableQuote);
                }

                if ($parentQuote->getGiftCardsAmountUsed() == 0) {
                    $giftCard->addToCart(true, $parentQuote);
                }

                // Send the whole GiftCard Amount.
                $giftAmount = $parentQuote->getGiftCardsAmount();
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            return $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                422,
                $immutableQuote
            );
        }

        $result = [
            'status'          => 'success',
            'discount_code'   => $code,
            'discount_amount' => abs($this->cartHelper->getRoundAmount($giftAmount)),
            'description'     =>  __('Gift Card'),
            'discount_type'   => $this->convertToBoltDiscountType('by_fixed'),
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
        $is_has_shipment = isset($request->cart->shipments[0]->reference) ? true : false;
        $cart = $this->cartHelper->getCartData($is_has_shipment, null, $quote);
        return [
            'total_amount' => $cart['total_amount'],
            'tax_amount'   => $cart['tax_amount'],
            'discounts'    => $cart['discounts'],
        ];
    }

    /**
     * @param int    $errCode
     * @param string $message
     * @param int    $httpStatusCode
     * @param null   $quote
     * @throws \Exception
     */
    private function sendErrorResponse($errCode, $message, $httpStatusCode, $quote = null)
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
    private function sendSuccessResponse($result, $quote)
    {
        $result['cart'] = $this->getCartTotals($quote);

        $this->response->setBody(json_encode($result));
        $this->response->sendResponse();

        $this->logHelper->addInfoLog('### sendSuccessResponse');
        $this->logHelper->addInfoLog(json_encode($result));
        $this->logHelper->addInfoLog('=== END ===');

        return $result;
    }

    /**
     * @param string $type
     * @return string
     */
    private function convertToBoltDiscountType($type)
    {
        switch ($type) {
            case "by_fixed":
            case "cart_fixed":
                return "fixed_amount";
            case "by_percent":
                return "percentage";
            case "by_shipping":
                return "shipping";
        }

        return "";
    }

    /**
     * @param Quote $quote
     * @param string $couponCode
     */
    private function setCouponCode($quote, $couponCode)
    {
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setCouponCode($couponCode)->collectTotals()->save();
        $this->cartHelper->saveQuote($quote);
    }

    /**
     * Load the coupon data by code
     *
     * @param $couponCode
     * @return mixed
     */
    private function loadCouponCodeData($couponCode)
    {
        return $this->couponFactory->create()->loadByCode($couponCode);
    }

    /**
     * Load the gift card data by code
     *
     * @param $code
     * @return \Magento\GiftCardAccount\Model\Giftcardaccount|null
     */
    public function loadGiftCardData($code)
    {
        $result = null;

        /** @var \Magento\GiftCardAccount\Model\ResourceModel\Giftcardaccount\Collection $giftCardAccount */
        $giftCardAccountResource = $this->moduleGiftCardAccount->getInstance();

        if ($giftCardAccountResource) {
            $this->logHelper->addInfoLog('### GiftCard ###');
            $this->logHelper->addInfoLog('# Code: ' . $code);

            /** @var \Magento\GiftCardAccount\Model\ResourceModel\Giftcardaccount\Collection $giftCardsCollection */
            $giftCardsCollection = $giftCardAccountResource
                ->addFieldToFilter('code', ['eq' => $code]);

            /** @var \Magento\GiftCardAccount\Model\Giftcardaccount $giftCard */
            $giftCard = $giftCardsCollection->getFirstItem();

            $result = (!$giftCard->isEmpty() && $giftCard->isValid()) ? $giftCard : null;
        }

        $this->logHelper->addInfoLog('# loadGiftCertData Result is empty: '. ((!$result) ? 'yes' : 'no'));

        return $result;
    }

    /**
     * @param $code
     * @return null|\Unirgy\Giftcert\Model\Cert
     * @throws NoSuchEntityException
     */
    public function loadGiftCertData($code)
    {
        $result = null;

        /** @var \Unirgy\Giftcert\Model\GiftcertRepository $giftCertRepository */
        $giftCertRepository = $this->moduleUnirgyGiftCert->getInstance();

        if ($giftCertRepository) {
            $this->logHelper->addInfoLog('### GiftCert ###');
            $this->logHelper->addInfoLog('# Code: ' . $code);

            try {
                /** @var \Unirgy\Giftcert\Model\Cert $giftCert */
                $giftCert = $giftCertRepository->get($code);

                $result = ($giftCert->getData('status') !== 'I') ? $giftCert : null;
            } catch (NoSuchEntityException $e) {
                //We must ignore the exception, because it is thrown when data does not exist.
                $result = null;
            }
        }

        $this->logHelper->addInfoLog('# loadGiftCertData Result is empty: ' . ((!$result) ? 'yes' : 'no'));

        return $result;
    }

    /**
     * @param $couponCode
     * @param Quote $immutableQuote
     * @param Quote $parentQuote
     * @return bool
     */
    protected function shouldUseParentQuoteShippingAddressDiscount($couponCode, Quote $immutableQuote, Quote $parentQuote)
    {
        $ignoredShippingAddressCoupons = $this->configHelper->getIgnoredShippingAddressCoupons();

        return $immutableQuote->getCouponCode() == $couponCode &&
               $immutableQuote->getCouponCode() == $parentQuote->getCouponCode() &&
               in_array($couponCode, $ignoredShippingAddressCoupons);
    }

    /**
     * @param $parentQuote
     * @param $coupon
     * @param $couponCode
     * @return array
     * @throws \Exception
     */
    protected function getParentQuoteDiscountResult($couponCode, $coupon, $parentQuote)
    {
        // Load the coupon discount rule
        $rule = $this->ruleFactory->create()->load($coupon->getRuleId());

        // Check if the rule exists
        if (empty($rule) || $rule->isObjectNew()) {
            return $this->sendErrorResponse(
                BoltErrorResponse::ERR_CODE_INVALID,
                sprintf('The coupon code %s is not found', $couponCode),
                404
            );
        }

        $address = $parentQuote->isVirtual() ? $parentQuote->getBillingAddress() : $parentQuote->getShippingAddress();

        return $result = [
            'status'          => 'success',
            'discount_code'   => $couponCode,
            'discount_amount' => abs($this->cartHelper->getRoundAmount($address->getDiscountAmount())),
            'description'     =>  __('Discount ') . $address->getDiscountDescription(),
            'discount_type'   => $this->convertToBoltDiscountType($rule->getSimpleAction()),
        ];
    }
}
