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

namespace Bolt\Boltpay\Model;

use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\App\Area as AppArea;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order as ModelOrder;
use Magento\Sales\Model\Order\Payment\Transaction;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use \Magento\Sales\Model\Order\Payment\Transaction\Repository as TransactionRepository;

/**
 * Class Payment.
 * Bolt Payment method model.
 *
 * @package Bolt\Boltpay\Model
 */
class Payment extends AbstractMethod
{
    const TRANSACTION_AUTHORIZED = 'authorized';
    const TRANSACTION_COMPLETED = 'completed';
    const TRANSACTION_CANCELLED = 'cancelled';

    const METHOD_CODE = 'boltpay';

    const DECISION_APPROVE = 'approve';
    const DECISION_REJECT = 'reject';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'boltpay';

    /**
     * @var string
     */
    protected $_formBlockType = \Bolt\Boltpay\Block\Form::class;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canVoid = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canFetchTransactionInfo = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapturePartial = true;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var TimezoneInterface
     */
    private $localeDate;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var MetricsClient
     */
    private $metricsClient;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var TransactionRepository
     */
    protected $transactionRepository;

    /**
     * @var string
     */
    protected $areaCode;
    /**
     * @var ModelOrder
     */
    protected $registryCurrentOrder;

    /**
     * @var AuthSession
     */
    protected $authSession;

    /**
     * @param Context                    $context
     * @param Registry                   $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory      $customAttributeFactory
     * @param Data                       $paymentData
     * @param ScopeConfigInterface       $scopeConfig
     * @param Logger                     $logger
     * @param TimezoneInterface          $localeDate
     * @param ConfigHelper               $configHelper
     * @param ApiHelper                  $apiHelper
     * @param OrderHelper                $orderHelper
     * @param Bugsnag                    $bugsnag
     * @param MetricsClient            $metricsClient
     * @param DataObjectFactory          $dataObjectFactory
     * @param CartHelper                 $cartHelper
     * @param TransactionRepository      $transactionRepository
     * @param AuthSession                $authSession
     * @param AbstractResource           $resource
     * @param AbstractDb                 $resourceCollection
     * @param array                      $data
     * @throws LocalizedException
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        TimezoneInterface $localeDate,
        ConfigHelper $configHelper,
        ApiHelper $apiHelper,
        OrderHelper $orderHelper,
        Bugsnag $bugsnag,
        MetricsClient $metricsClient,
        DataObjectFactory $dataObjectFactory,
        CartHelper $cartHelper,
        TransactionRepository $transactionRepository,
        AuthSession $authSession,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->configHelper = $configHelper;
        $this->apiHelper = $apiHelper;
        $this->localeDate = $localeDate;
        $this->orderHelper = $orderHelper;
        $this->bugsnag = $bugsnag;
        $this->metricsClient = $metricsClient;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->cartHelper = $cartHelper;
        $this->transactionRepository = $transactionRepository;
        $this->areaCode = $context->getAppState()->getAreaCode();
        $this->registryCurrentOrder = $registry->registry('current_order');
        $this->authSession = $authSession;
    }

    /**
     * Cancel the payment through gateway
     *
     * @param  InfoInterface $payment
     *
     * @return $this
     * @throws \Exception
     */
    public function cancel(InfoInterface $payment)
    {
        return $this->void($payment);
    }

    /**
     * Void the payment through gateway
     *
     * @param DataObject|InfoInterface $payment
     *
     * @return $this
     * @throws \Exception
     */
    public function void(InfoInterface $payment)
    {
        try {
            $startTime = $this->metricsClient->getCurrentTime();
            $transactionId = $payment->getAdditionalInformation('real_transaction_id');

            if (empty($transactionId)) {
                throw new LocalizedException(
                    __('Please wait while transaction gets updated from Bolt.')
                );
            }

            //Get transaction data
            $transactionData = [
                'transaction_id' => $transactionId,
                'skip_hook_notification' => true
            ];
            $storeId = $payment->getOrder()->getStoreId();
            $apiKey = $this->configHelper->getApiKey($storeId);

            //Request Data
            $requestData = $this->dataObjectFactory->create();
            $requestData->setApiData($transactionData);
            $requestData->setDynamicApiUrl(ApiHelper::API_VOID_TRANSACTION);
            $requestData->setApiKey($apiKey);
            //Build Request
            $request = $this->apiHelper->buildRequest($requestData);
            $result = $this->apiHelper->sendRequest($request);
            $response = $result->getResponse();

            if (empty($response)) {
                throw new LocalizedException(
                    __('Bad void response from boltpay')
                );
            }

            if (!in_array(@$response->status,['cancelled','completed'])) {
                throw new LocalizedException(__('Payment void error.'));
            }

            $order = $payment->getOrder();

            $this->orderHelper->updateOrderPayment($order, null, $response->reference);

            $this->metricsClient->processMetric("order_void.success", 1, "order_void.latency", $startTime);
            return $this;
        } catch (\Exception $e) {
            $this->metricsClient->processMetric("order_void.failure", 1, "order_void.latency", $startTime);
            $this->bugsnag->notifyException($e);
            throw $e;
        }
    }

    /**
     * Fetch transaction details info. This will fetch the latest transaction information from Bolt and update the
     * payment status in magento if needed.
     *
     * @param InfoInterface $payment
     * @param string $transactionId
     *
     * @return array
     * @throws \Exception
     */
    public function fetchTransactionInfo(InfoInterface $payment, $transactionId) {
        try {
            $startTime = $this->metricsClient->getCurrentTime();

            $transaction = $this->transactionRepository->getByTransactionId(
                $transactionId,
                $payment->getId(),
                $payment->getOrder()->getId()
            );

            $transactionDetails   = $transaction->getAdditionalInformation( Transaction::RAW_DETAILS );
            $transactionReference = $transactionDetails['Reference'];

            if ( ! empty( $transactionReference ) ) {
                $order = $payment->getOrder();
                $this->orderHelper->updateOrderPayment( $order, null, $transactionReference );
            }
            $this->metricsClient->processMetric("order_fetch.success", 1, "order_fetch.latency", $startTime);
        } catch ( \Exception $e ) {
            $this->metricsClient->processMetric("order_fetch.failure", 1, "order_fetch.latency", $startTime);
            $this->bugsnag->notifyException( $e );
        } finally {
            return [];
        }
    }

    /**
     * Capture the authorized transaction through the gateway
     *
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     * @throws \Exception
     */
    public function capture(InfoInterface $payment, $amount)
    {
        try {
            $startTime = $this->metricsClient->getCurrentTime();
            $order = $payment->getOrder();

            if ($amount <= 0) {
                throw new LocalizedException(__('Invalid amount for capture.'));
            }

            $realTransactionId = $payment->getAdditionalInformation('real_transaction_id');

            if (empty($realTransactionId)) {
                throw new LocalizedException(
                    __('Please wait while transaction get updated from Bolt.')
                );
            }

            //Get capture data
            $capturedData = [
                'transaction_id' => $realTransactionId,
                'amount'         => $this->getCaptureAmount($order, $amount),
                'currency'       => $order->getOrderCurrencyCode(),
                'skip_hook_notification' => true
            ];

            $storeId = $order->getStoreId();
            $apiKey = $this->configHelper->getApiKey($storeId);

            //Request Data
            $requestData = $this->dataObjectFactory->create();
            $requestData->setApiData($capturedData);
            $requestData->setDynamicApiUrl(ApiHelper::API_CAPTURE_TRANSACTION);
            $requestData->setApiKey($apiKey);

            //Build Request
            $request = $this->apiHelper->buildRequest($requestData);
            $result = $this->apiHelper->sendRequest($request);
            $response = $result->getResponse();

            if (empty($response)) {
                throw new LocalizedException(
                    __('Bad capture response from boltpay')
                );
            }

            if (!in_array(@$response->status, [self::TRANSACTION_AUTHORIZED, self::TRANSACTION_COMPLETED])) {
                throw new LocalizedException(__('Payment capture error.'));
            }

            $this->orderHelper->updateOrderPayment($order, null, $response->reference);
            $this->metricsClient->processMetric("order_capture.success", 1, "order_capture.latency", $startTime);
            return $this;
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->metricsClient->processMetric("order_capture.failure", 1, "order_capture.latency", $startTime);
            throw $e;
        }
    }

    private function getCaptureAmount($order, $amountInStoreCurrency)
    {
        $orderCurrency = $order->getOrderCurrencyCode();
        if ($order->getStoreCurrencyCode() == $orderCurrency) {
            return CurrencyUtils::toMinor( $amountInStoreCurrency, $orderCurrency );
        } else {
            // Magento passes $amount in store currency but not in order currency - we have to grab amount from invoice
            $latestInvoice = $order->getInvoiceCollection()->getLastItem();
            return CurrencyUtils::toMinor( $latestInvoice->getGrandTotal(),$orderCurrency );
        }
    }

    /**
     * Refund the amount
     *
     * @param DataObject|InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     * @throws \Exception
     */
    public function refund(InfoInterface $payment, $amount)
    {
        try {
            $startTime = $this->metricsClient->getCurrentTime();

            if ($amount < 0) {
                throw new LocalizedException(__('Invalid amount for refund.'));
            }

            $order = $payment->getOrder();

            $orderCurrency = $order->getOrderCurrencyCode();
            // $amount argument of refund method is in store currency,
            // we need to get amount from credit memo to get the value in order's currency.
            $refundAmount = CurrencyUtils::toMinor(
                $payment->getCreditMemo()->getGrandTotal(),
                $orderCurrency
            );

            if ($refundAmount < 1) {
                ////////////////////////////////////////////////////////////////////////////////
                // In certain circumstances, an amount's value of zero can be sent, for
                // example if the complete invoice already being refunded using
                // store credit. This will then result in an exception by the Bolt API.  In
                // these instances, there is no need to call the Bolt API, so we simply return
                ////////////////////////////////////////////////////////////////////////////////
                return $this;
            }

            $realTransactionId = $payment->getAdditionalInformation('real_transaction_id');

            if (empty($realTransactionId)) {
                throw new LocalizedException(
                    __('Please wait while transaction get updated from Bolt.')
                );
            }

            $refundData = [
                'transaction_id' => $realTransactionId,
                'amount'         => $refundAmount,
                'currency'       => $orderCurrency,
                'skip_hook_notification' => true
            ];

            $storeId = $order->getStoreId();
            $apiKey = $this->configHelper->getApiKey($storeId);

            //Request Data
            $requestData = $this->dataObjectFactory->create();
            $requestData->setApiData($refundData);
            $requestData->setDynamicApiUrl(ApiHelper::API_REFUND_TRANSACTION);
            $requestData->setApiKey($apiKey);

            //Build Request
            $request = $this->apiHelper->buildRequest($requestData);
            $result = $this->apiHelper->sendRequest($request);
            $response = $result->getResponse();

            if (empty($response)) {
                throw new LocalizedException(
                    __('Bad refund response from boltpay')
                );
            }

            if (@$response->status != self::TRANSACTION_COMPLETED) {
                throw new LocalizedException(__('Payment refund error.'));
            }

            $this->orderHelper->updateOrderPayment($order, null, $response->reference);
            $this->metricsClient->processMetric("order_refund.success", 1, "order_refund.latency", $startTime);

            return $this;
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->metricsClient->processMetric("order_refund.failure", 1, "order_refund.latency", $startTime);
            throw $e;
        }
    }

    /**
     * Do not validate payment method is allowed for billing country or not
     *
     * @return $this
     */
    public function validate()
    {
        return $this;
    }

    /**
     * Check whether payment method can be used
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     *
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        // check for product restrictions
        if ($this->cartHelper->hasProductRestrictions($quote)) {
            return false;
        }
        return parent::isAvailable();
    }

    public function getTitle()
    {
        if ($this->areaCode === AppArea::AREA_ADMINHTML) {
            if ($this->getData('store')) {
                $storeId = $this->getData('store');
            } elseif ($this->registryCurrentOrder && $this->registryCurrentOrder->getStoreId()) {
                $storeId = $this->registryCurrentOrder->getStoreId();
            } else {
                $storeId = null;
            }
            $path = 'payment/' . $this->getCode() . '/title';
            $configTitle = $this->_scopeConfig->getValue(
                $path,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeId
            );
            return $configTitle;
        } else {
            return parent::getTitle();
        }
    }

    /**
     * Whether this method can accept or deny payment
     * @return bool
     * @throws LocalizedException
     */
    public function canReviewPayment()
    {
        return $this->getInfoInstance()->getAdditionalInformation('transaction_state') == OrderHelper::TS_REJECTED_REVERSIBLE;
    }

    /**
     * Attempt to approve the order
     *
     * @param InfoInterface $payment
     *
     * @return bool
     */
    public function acceptPayment(InfoInterface $payment)
    {
        return $this->review($payment, self::DECISION_APPROVE);
    }

    /**
     * Attempt to deny the order
     *
     * @param InfoInterface $payment
     *
     * @return bool
     */
    public function denyPayment(InfoInterface $payment)
    {
        return $this->review($payment, self::DECISION_REJECT);
    }

    /**
     * Function to process the review (approve/reject), sends data to Bolt API
     * And update order history
     *
     * @param InfoInterface $payment
     * @param               $review
     *
     * @return bool
     */
    protected function review(InfoInterface $payment, $review)
    {
        try {
            $transId = $payment->getAdditionalInformation('real_transaction_id');

            if (empty($transId)) {
                throw new LocalizedException(__('Please wait while transaction gets updated from Bolt.'));
            }

            $transactionData = [
                'transaction_id' => $transId,
                'decision'       => $review,
            ];

            $storeId = $payment->getOrder()->getStoreId();
            $apiKey = $this->configHelper->getApiKey($storeId);

            //Request Data
            $requestData = $this->dataObjectFactory->create();
            $requestData->setApiData($transactionData);
            $requestData->setDynamicApiUrl(ApiHelper::API_REVIEW_TRANSACTION);
            $requestData->setApiKey($apiKey);

            //Build Request
            $request = $this->apiHelper->buildRequest($requestData);
            $result = $this->apiHelper->sendRequest($request);
            $response = $result->getResponse();

            if (strlen($response->reference) == 0) {
                throw new LocalizedException(__('Bad review response. Empty transaction reference'));
            }

            $this->updateReviewedOrderHistory($payment, $review);

            return true;
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }

        return false;
    }

    /**
     * @param InfoInterface $payment
     * @param               $review
     */
    protected function updateReviewedOrderHistory(InfoInterface $payment, $review)
    {
        $statusMessage = ($review == self::DECISION_APPROVE) ?
            'Force approve order by %1 %2.' : 'Confirm order rejection by %1 %2.';

        $adminUser = $this->authSession->getUser();
        $message = __($statusMessage, $adminUser->getFirstname(), $adminUser->getLastname());

        $order = $payment->getOrder();
        $order->addStatusHistoryComment($message);
        $order->save();
    }
}
