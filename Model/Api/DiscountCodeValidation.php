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
use Magento\Checkout\Exception;
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

/**
 * Discount Code Validation class
 * @api
 */
class DiscountCodeValidation implements DiscountCodeValidationInterface
{
    const ERR_INSUFFICIENT_INFORMATION     = 6200;
    const ERR_CODE_INVALID                 = 6201;
    const ERR_CODE_EXPIRED                 = 6202;
    const ERR_CODE_NOT_AVAILABLE           = 6203;
    const ERR_CODE_LIMIT_REACHED           = 6204;
    const ERR_MINIMUM_CART_AMOUNT_REQUIRED = 6205;
    const ERR_UNIQUE_EMAIL_REQUIRED        = 6206;
    const ERR_ITEMS_NOT_ELIGIBLE           = 6207;
    // TODO: move this to a global const within the plugin.
    const ERR_SERVICE                      = 6001;

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
     * @param Request           $request
     * @param Response          $response
     * @param CouponFactory     $couponFactory
     * @param ThirdPartyModuleFactory   $moduleGiftCardAccount
     * @param RuleFactory       $ruleFactory
     * @param LogHelper         $logHelper
     * @param UsageFactory      $usageFactory
     * @param DataObjectFactory $objectFactory
     * @param TimezoneInterface $timezone
     * @param CustomerFactory   $customerFactory
     * @param Bugsnag           $bugsnag
     * @param CartHelper        $cartHelper
     * @param ConfigHelper      $configHelper
     * @param HookHelper        $hookHelper
     */
    public function __construct(
        Request $request,
        Response $response,
        CouponFactory $couponFactory,
        ThirdPartyModuleFactory $moduleGiftCardAccount,
        RuleFactory $ruleFactory,
        LogHelper $logHelper,
        UsageFactory $usageFactory,
        DataObjectFactory $objectFactory,
        TimezoneInterface $timezone,
        CustomerFactory $customerFactory,
        Bugsnag $bugsnag,
        CartHelper $cartHelper,
        ConfigHelper $configHelper,
        HookHelper $hookHelper
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->couponFactory = $couponFactory;
        $this->moduleGiftCardAccount = $moduleGiftCardAccount;
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
    }

    /**
     * @api
     * @return array
     * @throws \Exception
     */
    public function validate()
    {
        try {

            if ($bolt_trace_id = $this->request->getHeader(ConfigHelper::BOLT_TRACE_ID_HEADER)) {
                $this->bugsnag->registerCallback(function ($report) use ($bolt_trace_id) {
                    $report->setMetaData([
                        'BREADCRUMBS_' => [
                            'bolt_trace_id' => $bolt_trace_id,
                        ]
                    ]);
                });
            }

            $this->response->getHeaders()->addHeaders([
                'User-Agent' => 'BoltPay/Magento-'.$this->configHelper->getStoreVersion(),
                'X-Bolt-Plugin-Version' => $this->configHelper->getModuleVersion()
            ]);

            $this->logHelper->addInfoLog($this->request->getContent());

            $this->hookHelper->verifyWebhook();

            $request = json_decode($this->request->getContent());

            // get the coupon code
            $discount_code = @$request->discount_code ?: @$request->cart->discount_code;
            $couponCode = trim($discount_code);

            // check if empty coupon was sent
            if ($couponCode === '') {
                return $this->sendErrorResponse(
                    self::ERR_CODE_INVALID,
                    'No coupon code provided',
                    422
                );
            }

            // load the coupon
            $coupon = $this->loadCouponCodeData($couponCode);

            $giftCard = null;
            if (empty($coupon) || $coupon->isObjectNew()) {
                // load the gift card by code
                $giftCard = $this->loadGiftCardData($couponCode);
            }

            $this->logHelper->addInfoLog(__METHOD__);
            $this->logHelper->addInfoLog('Coupon is empty: '. (empty($coupon) || $coupon->isObjectNew()) ? 'yes - Coupon' : 'no - Coupon');
            $this->logHelper->addInfoLog('GiftCArd is empty: '. ((empty($giftCard)) ? 'yes - Giftcard' : 'no - Giftcard'));
            $this->logHelper->addInfoLog((bool)((empty($coupon) || $coupon->isObjectNew()) && empty($giftCard)));
            // check if the coupon exists
            if ((empty($coupon) || $coupon->isObjectNew()) && empty($giftCard)) {
                return $this->sendErrorResponse(
                    self::ERR_CODE_INVALID,
                    sprintf('The coupon code %s is not found', $couponCode),
                    404
                );
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
                return $this->sendErrorResponse(
                    self::ERR_INSUFFICIENT_INFORMATION,
                    'The order reference is invalid.',
                    422
                );
            }

            // check if the order has already been created
            if ($this->cartHelper->getOrderByIncrementId($incrementId)) {
                return $this->sendErrorResponse(
                    self::ERR_INSUFFICIENT_INFORMATION,
                    sprintf('The order #%s has already been created.', $incrementId),
                    422
                );
            }

            // check if the cart / quote exists and it is active
            try {
                /** @var Quote $parentQuote */
                $parentQuote = $this->cartHelper->getActiveQuoteById($parentQuoteId);
            } catch (\Exception $e) {
                $this->bugsnag->notifyException($e);
                return $this->sendErrorResponse(
                    self::ERR_INSUFFICIENT_INFORMATION,
                    sprintf('The cart reference [%s] is not found.', $parentQuoteId),
                    404
                );
            }

            // check the existence of child quote
            try {
                /** @var Quote $immutableQuote */
                $immutableQuote = $this->cartHelper->getQuoteById($immutableQuoteId);
            } catch (\Exception $e) {
                $this->bugsnag->notifyException($e);
                return $this->sendErrorResponse(
                    self::ERR_INSUFFICIENT_INFORMATION,
                    sprintf('The cart reference [%s] is not found.', $immutableQuoteId),
                    404
                );
            }

            // check if cart is empty
            if (!$immutableQuote->getItemsCount()) {
                return $this->sendErrorResponse(
                    self::ERR_INSUFFICIENT_INFORMATION,
                    sprintf('The cart for order reference [%s] is empty.', $immutableQuoteId),
                    422
                );
            }

            $this->logHelper->addInfoLog('# Coupon: '.($coupon && $coupon->getCouponId() ? 'yes' : 'no'));
            $this->logHelper->addInfoLog('# GiftCard: '.($giftCard && $giftCard->getId() ? 'yes' : 'no'));

            if ($coupon && $coupon->getCouponId()) {
                $result = $this->applyingCouponCode($couponCode, $coupon, $immutableQuote, $parentQuote);
            } else if ($giftCard && $giftCard->getId()) {
                $result = $this->applyingGiftCardCode($couponCode, $giftCard, $immutableQuote, $parentQuote);
            } else {
                throw new Exception(__('Something happened with current code.'));
            }

            if (isset($result['status']) && $result['status'] === 'error') {
                return $result;
            }

            $this->logHelper->addInfoLog(json_encode($result));
            $this->logHelper->addInfoLog('=== END ===');

            return $this->sendSuccessResponse($result, $immutableQuote);

        } catch (\Magento\Framework\Webapi\Exception $e) {
            $this->bugsnag->notifyException($e);
            return $this->sendErrorResponse(
                self::ERR_SERVICE,
                $e->getMessage(),
                $e->getHttpCode(),
                @$immutableQuote
            );
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            $errMsg = 'Unprocessable Entity: ' . $e->getMessage();
            return $this->sendErrorResponse(
                self::ERR_SERVICE,
                $errMsg,
                422,
                @$immutableQuote
            );
        }
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
                self::ERR_CODE_INVALID,
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
                self::ERR_CODE_EXPIRED,
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
                self::ERR_CODE_NOT_AVAILABLE,
                $desc,
                422,
                $immutableQuote
            );
        }

        // Check coupon usage limits.
        if ($coupon->getUsageLimit() && $coupon->getTimesUsed() >= $coupon->getUsageLimit()) {
            return $this->sendErrorResponse(
                self::ERR_CODE_LIMIT_REACHED,
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
                        self::ERR_CODE_LIMIT_REACHED,
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
                        self::ERR_CODE_LIMIT_REACHED,
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
            $this->sendErrorResponse(
                self::ERR_SERVICE,
                $e->getMessage(),
                422,
                $immutableQuote
            );
        }

        if ($immutableQuote->getCouponCode() != $couponCode) {
            return $this->sendErrorResponse(
                self::ERR_SERVICE,
                __('Coupon code does not equal with a quote code!'),
                422,
                $immutableQuote
            );
        }

        $address = $immutableQuote->isVirtual() ?
            $immutableQuote->getBillingAddress() :
            $immutableQuote->getShippingAddress();

        $result = [
            'status'          => 'success',
            'discount_code'   => $couponCode,
            'discount_amount' => abs($this->cartHelper->getRoundAmount($address->getDiscountAmount())),
            'description'     =>  __('Discount ') . $address->getDiscountDescription(),
            'discount_type'   => $this->convertToBoltDiscountType($rule->getSimpleAction()),
        ];

        $this->logHelper->addInfoLog('### Coupon Result');
        $this->logHelper->addInfoLog(json_encode($result));

        return $result;
    }

    /**
     * @param $code
     * @param Magento\GiftCardAccount\Model\Giftcardaccount $giftCard
     * @param Quote $immutableQuote
     * @param Quote $parentQuote
     * @return array
     * @throws \Exception
     */
    private function applyingGiftCardCode($code, $giftCard, $immutableQuote, $parentQuote)
    {
        try {
            if ($immutableQuote->getGiftCardsAmountUsed() == 0) {
                $giftCard->addToCart(true, $immutableQuote);
            }

            if ($parentQuote->getGiftCardsAmountUsed() == 0) {
                $giftCard->addToCart(true, $parentQuote);
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            return $this->sendErrorResponse(
                self::ERR_SERVICE,
                $e->getMessage(),
                422,
                $immutableQuote
            );
        }

        $result = [
            'status'          => 'success',
            'discount_code'   => $code,
            'discount_amount' => abs($this->cartHelper->getRoundAmount($parentQuote->getGiftCardsAmountUsed())),
            'description'     =>  __('Discount Gift Card'),
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
    private function getCartTotals($quote) {

        $cart = $this->cartHelper->getCartData(false, null, $quote);
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
     * @return array
     * @throws \Exception
     */
    private function sendErrorResponse($errCode, $message, $httpStatusCode, $quote = null)
    {
        $errResponse = [
            'status' => 'error',
            'error' => [
                'code' => $errCode,
                'message' => $message,
            ],
        ];
        if ($quote) $errResponse['cart'] = $this->getCartTotals($quote);

        $this->response->setHttpResponseCode($httpStatusCode);
        $this->response->setBody(json_encode($errResponse));
        $this->response->sendResponse();

        return $errResponse;
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

        return $result;
    }

    /**
     * @param string $type
     * @return string
     */
    private function convertToBoltDiscountType($type) {
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
    private function setCouponCode($quote, $couponCode) {
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setCouponCode($couponCode);
        $this->cartHelper->saveQuote($quote->collectTotals());
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
     * @return Magento\GiftCardAccount\Model\Giftcardaccount|null
     */
    private function loadGiftCardData($code)
    {
        $result = null;

        $giftCardAccount = $this->moduleGiftCardAccount->getInstance();
        if ($giftCardAccount) {
            /** @var Magento\GiftCardAccount\Model\Giftcardaccount $giftCardAccount */
            $giftCard = $giftCardAccount->loadByCode($code);

            $result = ($giftCard->isValid()) ? $giftCard : null;
        }
        $this->logHelper->addInfoLog('# loadGiftCardData Result is empty: '. (empty($result) ? "yes" : 'no'));

        return $result;
    }
}
