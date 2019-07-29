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
use Magento\SalesRule\Model\RuleRepository;
use Magento\SalesRule\Model\Coupon;
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
     * @var RuleRepository
     */
    protected $ruleRepository;

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
     * @param RuleRepository          $ruleRepository
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
        RuleRepository $ruleRepository,
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
        $this->ruleRepository = $ruleRepository;
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
            $request = $this->getRequestContent();

            $requestArray = json_decode(json_encode($request), true);
            if (isset($requestArray['cart']['order_reference'])) {
                $parentQuoteId = $requestArray['cart']['order_reference'];
                $displayId = isset($requestArray['cart']['display_id']) ? $requestArray['cart']['display_id'] : '';
                // check if the cart / quote exists and it is active
                try {
                    /** @var Quote $parentQuote */
                    $parentQuote = $this->cartHelper->getActiveQuoteById($parentQuoteId);

                    // get parent quote id, order increment id and child quote id
                    // the latter two are transmitted as display_id field, separated by " / "
                    list($incrementId, $immutableQuoteId) = array_pad(
                        explode(' / ', $displayId),
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

                } catch (\Exception $e) {
                    $this->bugsnag->notifyException($e);
                    $this->sendErrorResponse(
                        BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                        sprintf('The cart reference [%s] is not found.', $parentQuoteId),
                        404
                    );
                    return false;
                }
            } else {
                $this->bugsnag->notifyError(
                    BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                    'The cart.order_reference is not set or empty.'
                );
                $this->sendErrorResponse(
                    BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                    'The cart reference is not found.',
                    404
                );
                return false;
            }

            $storeId = $parentQuote->getStoreId();
            $websiteId = $parentQuote->getStore()->getWebsiteId();

            $this->preProcessWebhook($storeId);

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

            // Load the gift card by code
            $giftCard = $this->loadGiftCardData($couponCode, $websiteId);

            // Apply Unirgy_GiftCert
            if (empty($giftCard)) {
                // Load the gift cert by code
                $giftCard = $this->loadGiftCertData($couponCode, $storeId);
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
                $coupon = $this->loadCouponCodeData($couponCode);
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
            if ($this->cartHelper->getOrderByIncrementId($incrementId)) {
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
            if (isset($request->cart->shipments[0]->reference)) {
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

                $shippingAddress
                    ->setShippingMethod($request->cart->shipments[0]->reference)
                    ->setCollectShippingRates(true)
                    ->collectShippingRates()
                    ->save();
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

            $this->sendSuccessResponse($result, $immutableQuote);
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
     * @param null|int $storeId
     * @throws LocalizedException
     * @throws WebApiException
     */
    public function preProcessWebhook($storeId = null)
    {
        $this->hookHelper->preProcessWebhook($storeId);
    }

    /**
     * @return array
     */
    private function getRequestContent()
    {
        $this->logHelper->addInfoLog($this->request->getContent());

        return json_decode($this->request->getContent());
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
            $this->setCouponCode($parentQuote, $couponCode);
            // apply coupon to clone
            $this->setCouponCode($immutableQuote, $couponCode);
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
            'discount_amount' => abs($this->cartHelper->getRoundAmount($address->getDiscountAmount())),
            'description'     => trim(__('Discount ') . $rule->getDescription()),
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
            }elseif ($giftCard instanceof \Mageplaza\GiftCard\Model\GiftCard) {
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
     * @param Quote  $quote
     * @param string $couponCode
     * @throws \Exception
     */
    private function setCouponCode($quote, $couponCode)
    {
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setCouponCode($couponCode)->collectTotals()->save();
    }

    /**
     * Load the coupon data by code
     *
     * @param $couponCode
     *
     * @return Coupon
     */
    private function loadCouponCodeData($couponCode)
    {
        return $this->couponFactory->create()->loadByCode($couponCode);
    }

    /**
     * Load the gift card data by code
     *
     * @param string $code
     * @param string|int $websiteId
     *
     * @return \Magento\GiftCardAccount\Model\Giftcardaccount|null
     */
    public function loadGiftCardData($code, $websiteId)
    {
        $result = null;

        /** @var \Magento\GiftCardAccount\Model\ResourceModel\Giftcardaccount\Collection $giftCardAccountResource */
        $giftCardAccountResource = $this->moduleGiftCardAccount->getInstance();

        if ($giftCardAccountResource) {
            $this->logHelper->addInfoLog('### GiftCard ###');
            $this->logHelper->addInfoLog('# Code: ' . $code);

            /** @var \Magento\GiftCardAccount\Model\ResourceModel\Giftcardaccount\Collection $giftCardsCollection */
            $giftCardsCollection = $giftCardAccountResource
                ->addFieldToFilter('code', ['eq' => $code])
                ->addWebsiteFilter([0, $websiteId]);

            /** @var \Magento\GiftCardAccount\Model\Giftcardaccount $giftCard */
            $giftCard = $giftCardsCollection->getFirstItem();

            $result = (!$giftCard->isEmpty() && $giftCard->isValid()) ? $giftCard : null;
        }

        $this->logHelper->addInfoLog('# loadGiftCertData Result is empty: '. ((!$result) ? 'yes' : 'no'));

        return $result;
    }

    /**
     * @param string $code
     * @param string|int $storeId
     *
     * @return null|\Unirgy\Giftcert\Model\Cert
     * @throws NoSuchEntityException
     */
    public function loadGiftCertData($code, $storeId)
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

                $gcStoreId = $giftCert->getStoreId();

                $result = ((!$gcStoreId || $gcStoreId == $storeId) && $giftCert->getData('status') === 'A')
                          ? $giftCert : null;

            } catch (NoSuchEntityException $e) {
                //We must ignore the exception, because it is thrown when data does not exist.
                $result = null;
            }
        }

        $this->logHelper->addInfoLog('# loadGiftCertData Result is empty: ' . ((!$result) ? 'yes' : 'no'));

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
            'discount_amount' => abs($this->cartHelper->getRoundAmount($address->getDiscountAmount())),
            'description'     =>  __('Discount ') . $address->getDiscountDescription(),
            'discount_type'   => $this->convertToBoltDiscountType($rule->getSimpleAction()),
        ];
    }
}
