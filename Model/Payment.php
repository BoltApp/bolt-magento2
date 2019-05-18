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
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order\Payment\Transaction;
use Bolt\Boltpay\Helper\Bugsnag;
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

    const METHOD_CODE = 'boltpay';

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

    protected $_areaCode;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param TimezoneInterface $localeDate
     * @param ConfigHelper $configHelper
     * @param ApiHelper $apiHelper
     * @param OrderHelper $orderHelper
     * @param Bugsnag $bugsnag
     * @param DataObjectFactory $dataObjectFactory
     * @param CartHelper $cartHelper
     * @param TransactionRepository $transactionRepository
     * @param AbstractResource $resource
     * @param AbstractDb $resourceCollection
     * @param array $data
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
        DataObjectFactory $dataObjectFactory,
        CartHelper $cartHelper,
        TransactionRepository $transactionRepository,
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
        $this->dataObjectFactory = $dataObjectFactory;
        $this->cartHelper = $cartHelper;
        $this->transactionRepository = $transactionRepository;

        $this->_areaCode = $context->getAppState()->getAreaCode();
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
            $transactionId = $payment->getAdditionalInformation('real_transaction_id');

            if (empty($transactionId)) {
                throw new LocalizedException(
                    __('Please wait while transaction gets updated from Bolt.')
                );
            }

            //Get transaction data
            $transactionData = ['transaction_id' => $transactionId];
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

            if (@$response->status != 'cancelled') {
                throw new LocalizedException(__('Payment void error.'));
            }

            $order = $payment->getOrder();

            $this->orderHelper->updateOrderPayment($order, null, $response->reference);

            return $this;
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }
    }

    /**
     * Fetch transaction details info
     *
     * Update transaction info if there is one placing transaction only
     *
     * @param InfoInterface $payment
     * @param string $transactionId
     *
     * @return array
     * @throws \Exception
     */
    public function fetchTransactionInfo(InfoInterface $payment, $transactionId)
    {
        try {

            $transaction = $this->transactionRepository->getByTransactionId(
                $transactionId,
                $payment->getId(),
                $payment->getOrder()->getId()
            );

            $transactionDetails = $transaction->getAdditionalInformation(Transaction::RAW_DETAILS);
            $transactionReference = $transactionDetails['Reference'];

            if (!empty($transactionReference)) {
                $order = $payment->getOrder();
                $this->orderHelper->updateOrderPayment($order, null, $transactionReference);
            }

        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
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

            $captureAmount = $amount * 100;

            //Get capture data
            $capturedData = [
                'transaction_id' => $realTransactionId,
                'amount'         => $captureAmount,
                'currency'       => $order->getOrderCurrencyCode()
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

            return $this;
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
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
            $order = $payment->getOrder();

            if ($amount <= 0) {
                throw new LocalizedException(__('Invalid amount for refund.'));
            }

            $realTransactionId = $payment->getAdditionalInformation('real_transaction_id');

            if (empty($realTransactionId)) {
                throw new LocalizedException(
                    __('Please wait while transaction get updated from Bolt.')
                );
            }

            $refundAmount = $amount * 100;

            //Get refund data
            $refundData = [
                'transaction_id' => $realTransactionId,
                'amount'         => $refundAmount,
                'currency'       => $order->getOrderCurrencyCode()
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

            return $this;
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
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
        if ($this->_areaCode === 'adminhtml') {
            if ($this->getData('store')) {
                $storeId = $this->getData('store');
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
}
