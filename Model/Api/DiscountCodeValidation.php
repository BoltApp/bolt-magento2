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

            $couponCode = $request->discount_code;

            $coupon = $this->couponFactory->create()->loadByCode($couponCode);
            $rule = $this->ruleFactory->create()->load($coupon->getRuleId());

            $result = [
                'discount_code' => $couponCode,
                'status'        => 'failure',
            ];

            if ($ruleId = $rule->getId()) {
                $result['type'] = $rule->getSimpleAction();
            }

            $couponId= $coupon->getId();

            $quote_id = $request->cart->order_reference;

            try {
                /** @var  \Magento\Quote\Model\Quote $quote */
                $quote = $this->quoteRepository->getActive($quote_id);
            } catch (\Exception $e) {
                $quote = null;
            }

            if (!$quote) {
                $result['error'] = [
                    'code' => 5,
                    'description' => 'The cart does not exist',
                ];
            } elseif (!$quote->getItemsCount()) {
                $result['error'] = [
                    'code' => 5,
                    'description' => 'The cart is empty',
                ];
            } elseif (!$couponId || !$ruleId) {
                $result['error'] = [
                    'code' => 7,
                    'description' => 'Code does not exist',
                ];
            } elseif (date('Y-m-d', strtotime($rule->getToDate())) < date('Y-m-d')) {
                $result['error'] = [
                    'code' => 1,
                    'description' => 'Code has expired',
                ];
            } elseif (date('Y-m-d', strtotime($rule->getFromDate())) > date('Y-m-d')) {
                $result['error'] = [
                    'code' => 8,
                    'description' => 'Code available from ' . $this->timezone->formatDate(
                        new \DateTime($rule->getFromDate()),
                        \IntlDateFormatter::MEDIUM
                    ),
                ];
            } elseif ($coupon->getUsageLimit() && $coupon->getTimesUsed() >= $coupon->getUsageLimit()) {
                $result['error'] = [
                    'code' => 4,
                    'description' => 'Usage limit reached',
                ];
            } elseif ($customerId = $quote->getCustomerId()) {
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
                            'code' => 4,
                            'description' => "$usagePerCustomer use per cutomer limit reached",
                        ];
                    }
                }
                if (empty($result['error']) && $usesPerCustomer = $rule->getUsesPerCustomer()) {
                    $ruleCustomer = $this->customerFactory->create()->loadByCustomerRule($customerId, $ruleId);
                    if ($ruleCustomer->getId() && $ruleCustomer->getTimesUsed() >= $rule->getUsesPerCustomer()) {
                        $result['error'] = [
                            'code' => 4,
                            'description' => "$usesPerCustomer use per cutomer limit reached",
                        ];
                    }
                }
            }

            $sendResponse = function ($result) use ($quote) {
                if ($quote) {
                    $payment_only = (bool)$quote->getShippingAddress()->getShippingMethod();
                    $result['cart'] = $this->cartHelper->getCartData($payment_only,null, $quote);
                }
                $this->response->setBody(json_encode($result));
                $this->response->sendResponse();
            };

            if (@$result['error']) {
                $sendResponse($result);
                return;
            }

            try {
                $oldCouponCode = $quote->getCouponCode();
                $this->couponManagement->set($quote_id, $couponCode);
                $shippingAddress = $quote->getShippingAddress();
                $result['description'] = __('Discount ') . $shippingAddress->getDiscountDescription();
                $result['amount'] = abs(round($shippingAddress->getDiscountAmount() * 100));
                $result['status'] = 'success';
            } catch (\Exception $e) {
                $result['error'] = [
                    'code' => 7,
                    'description' => $e->getMessage()
                ];
                // try to reapply the old coupon code
                try {
                    $this->couponManagement->set($quote_id, $oldCouponCode);
                } catch (\Exception $e) {
                    // do nothing. The result will be visible in cart data
                }
            }

            $sendResponse($result);

        } catch (\Magento\Framework\Webapi\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->response->setHttpResponseCode($e->getHttpCode());
            $this->response->setBody(json_encode([
                'status' => 'error',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ]));
            $this->response->sendResponse();
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->response->setHttpResponseCode(422);
            $this->response->setBody(json_encode([
                'status' => 'error',
                'code' => '6009',
                'message' => 'Unprocessable Entity: ' . $e->getMessage(),
            ]));
            $this->response->sendResponse();
        }
    }
}
