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
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Quote\Model\CouponManagement;
use Magento\Quote\Api\CartRepositoryInterface;
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
     * @var CouponManagement
     */
    private $couponManagement;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var CouponFactory
     */
    protected $couponFactory;

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
     * @param Request $request
     * @param Response $response
     * @param CouponManagement $couponManagement
     * @param CartRepositoryInterface $quoteRepository
     * @param CouponFactory $couponFactory
     * @param RuleFactory $ruleFactory
     * @param LogHelper $logHelper
     * @param UsageFactory $usageFactory
     * @param DataObjectFactory $objectFactory
     * @param TimezoneInterface $timezone
     * @param CustomerFactory $customerFactory
     * @param Bugsnag $bugsnag
     * @param CartHelper $cartHelper
     * @param ConfigHelper $configHelper
     * @param HookHelper $hookHelper
     */
    public function __construct(
        Request $request,
        Response $response,
        CouponManagement $couponManagement,
        CartRepositoryInterface $quoteRepository,
        CouponFactory $couponFactory,
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
        $this->couponManagement = $couponManagement;
        $this->quoteRepository = $quoteRepository;
        $this->couponFactory = $couponFactory;
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
     * @return void
     */
    public function validate() {

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

            $this->hookHelper->verifyWebhook();

            $request = json_decode($this->request->getContent());

            $couponCode = trim($request->discount_code);
            if ($couponCode === '') {
                return $this->sendErrorResponse(self::ERR_CODE_INVALID, 'No coupon code provided', 422);
            }

            $coupon = $this->couponFactory->create()->loadByCode($couponCode);
            if (empty($coupon)) {
                return $this->sendErrorResponse(self::ERR_CODE_INVALID, sprintf('The coupon code %s is not found', $couponCode), 404);
            }

            $rule = $this->ruleFactory->create()->load($coupon->getRuleId());


            $couponId= $coupon->getId();

            // Load the and validate the quote.
            $quote_id = $request->cart->order_reference;
            if (empty($quote_id)) {
                return $this->sendErrorResponse(self::ERR_INSUFFICIENT_INFORMATION, 'The order reference is invalid.', 422);
            }
            try {
                /** @var  \Magento\Quote\Model\Quote $quote */
                $quote = $this->quoteRepository->getActive($quote_id);
            } catch (\Exception $e) {
                $quote = null;
            }
            if (empty($quote)) {
                return $this->sendErrorResponse(self::ERR_INSUFFICIENT_INFORMATION, sprintf('The order reference [%s] is not found.', $quote_id), 404);
            }
            if (!$quote->getItemsCount()) {
                return $this->sendErrorResponse(self::ERR_INSUFFICIENT_INFORMATION, sprintf('The cart for order reference [%s] is empty.', $quote_id), 422);
            }

            // Check date validity.
            if (date('Y-m-d', strtotime($rule->getToDate())) < date('Y-m-d')) {
                return $this->sendErrorResponse(self::ERR_CODE_EXPIRED, sprintf('The code [%s] has expired.', $couponCode), 422);
            }
            if (date('Y-m-d', strtotime($rule->getFromDate())) > date('Y-m-d')) {
                $desc = 'Code available from ' . $this->timezone->formatDate(
                    new \DateTime($rule->getFromDate()),
                    \IntlDateFormatter::MEDIUM
                );
                return $this->sendErrorResponse(self::ERR_CODE_NOT_AVAILABLE, $desc, 422);
            }

            // Check usage limits.
            if ($coupon->getUsageLimit() && $coupon->getTimesUsed() >= $coupon->getUsageLimit()) {
                return $this->sendErrorResponse(self::ERR_CODE_LIMIT_REACHED, sprintf('The code [%s] has exceeded usage limit.', $couponCode), 422);
            }

            if ($customerId = $quote->getCustomerId()) {
                if ($usagePerCustomer = $coupon->getUsagePerCustomer()) {
                    $couponUsage = $this->objectFactory->create();
                    $this->usageFactory->create()->loadByCustomerCoupon(
                        $couponUsage,
                        $customerId,
                        $couponId
                    );
                    if ($couponUsage->getCouponId() &&
                        $couponUsage->getTimesUsed() >= $usagePerCustomer) {
                        $result['error'] = [
                            'code' => self::ERR_CODE_LIMIT_REACHED,
                            'description' => "$usagePerCustomer use per cutomer limit reached",
                        ];
                    }
                }
                if (empty($result['error']) && $usesPerCustomer = $rule->getUsesPerCustomer()) {
                    $ruleCustomer = $this->customerFactory->create()->loadByCustomerRule($customerId, $ruleId);
                    if ($ruleCustomer->getId() && $ruleCustomer->getTimesUsed() >= $rule->getUsesPerCustomer()) {
                        $result['error'] = [
                            'code' => self::ERR_CODE_LIMIT_REACHED,
                            'description' => "$usesPerCustomer use per cutomer limit reached",
                        ];
                    }
                }
            }

            $result = [
                'discount_code' => $couponCode,
                'status'        => 'failure',
            ];

            if ($ruleId = $rule->getId()) {
                // TODO set discount type
                // $result['discount_type'] = "flat_amount|percentage|shipping"
                $result['type'] = $rule->getSimpleAction();
            }

            try {
                $oldCouponCode = $quote->getCouponCode();
                $this->couponManagement->set($quote_id, $couponCode);
                $shippingAddress = $quote->getShippingAddress();
                $result['description'] = __('Discount ') . $shippingAddress->getDiscountDescription();
                $result['discount_amount'] = abs(round($shippingAddress->getDiscountAmount() * 100));
                $result['status'] = 'success';
            } catch (\Exception $e) {
                // try to reapply the old coupon code
                try {
                    $this->couponManagement->set($quote_id, $oldCouponCode);
                } catch (\Exception $e) {
                    // do nothing. The result will be visible in cart data
                }
                $this->sendErrorResponse(self::ERR_SERVICE, $e->getMessage(), 422);
            }
            $this->sendSuccessResponse($result, $quote);

        } catch (\Magento\Framework\Webapi\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->sendErrorResponse(self::ERR_SERVICE, $e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            $errMsg = 'Unprocessable Entity: ' . $e->getMessage();
            $this->sendErrorResponse(self::ERR_SERVICE, $errMsg, 422);
        }
    }

    private function sendErrorResponse($errCode, $message, $httpStatusCode) {
        $errResponse = [
            'status' => 'error',
            'error' => [
                'code' => $errCode,
                'message' => $message,
            ],
        ];
        $this->response->setHttpResponseCode($httpStatusCode);
        $this->response->setBody(json_encode($errResponse));
        $this->response->sendResponse();
        return;
    }

    private function sendSuccessResponse($result, $quote) {
        if ($quote) {
            $payment_only = (bool)$quote->getShippingAddress()->getShippingMethod();
            $result['cart'] = $this->cartHelper->getCartData($payment_only, null, $quote);
        }
        $this->response->setBody(json_encode($result));
        $this->response->sendResponse();
        return;
    }
}
