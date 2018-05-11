<?php

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

/**
 * Class Payment.
 * Bolt Payment method model.
 *
 * @package Bolt\Boltpay\Model
 */
class Payment extends AbstractMethod
{

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
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param TimezoneInterface $localeDate
     * @param configHelper $configHelper
     * @param ApiHelper $apiHelper
     * @param OrderHelper $orderHelper
     * @param Bugsnag $bugsnag
     * @param DataObjectFactory $dataObjectFactory
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
        configHelper $configHelper,
        ApiHelper $apiHelper,
        OrderHelper $orderHelper,
        Bugsnag $bugsnag,
        DataObjectFactory $dataObjectFactory,
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
            $hookTransactionId = $payment->getAdditionalInformation('real_transaction_id');

            if (empty($hookTransactionId)) {
                throw new LocalizedException(
                    __('Please wait while transaction get updated from Bolt.')
                );
            }

            //Get transaction data
            $transactionData = ['transaction_id' => $hookTransactionId];
            $apiKey = $this->configHelper->getApiKey();

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

            if (isset($response->status)) {
                if ($response->status != 'cancelled') {
                    throw new LocalizedException(__('Payment void error.'));
                }
                $payment->setAdditionalInformation('transaction_status', $response->status);
                $payment->setIsTransactionClosed(true);
            }
            $this->fetchTransactionInfo($payment, $hookTransactionId);
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
            $transactionReference = $payment->getAdditionalInformation('transaction_reference');

            if (!empty($transactionReference)) {
                $order = $payment->getOrder();
                $this->orderHelper->updateOrderPayment($order, null, $transactionReference);
            }
            return [];
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }
    }

    /**
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
                throw new LocalizedException(__('Invalid amount for refund.'));
            }

            $realTransactionId = $payment->getAdditionalInformation('real_transaction_id');

            if (empty($realTransactionId)) {
                throw new LocalizedException(
                    __('Please wait while transaction get updated from Bolt.')
                );
            }

            $captureAmount = $amount * 100;

            //Get refund data
            $capturedData = [
                'transaction_id' => $realTransactionId,
                'amount'         => $captureAmount,
                'currency'       => $order->getOrderCurrencyCode()
            ];

            $apiKey = $this->configHelper->getApiKey();

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

            if (isset($response->status)) {
                if ($response->status != 'completed') {
                    throw new LocalizedException(__('Payment capture error.'));
                }
                $payment->setIsTransactionClosed(true);
            }
            $this->fetchTransactionInfo($payment, $realTransactionId);
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

            $apiKey = $this->configHelper->getApiKey();

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

            if (isset($response->status)) {
                if ($response->status != 'completed') {
                    throw new LocalizedException(__('Payment refund error.'));
                }
                $paymentData = [
                    'last_transaction_timestamp' => $response->date,
                    'real_transaction_id'        => $payment->getAdditionalInformation('real_transaction_id'),
                    'transaction_reference'      => $payment->getAdditionalInformation('transaction_reference'),
                ];
                $formattedPrice = $order->getBaseCurrency()->formatTxt($response->amount->amount / 100);
                $transactionData = [
                    'Time'      => $result = $this->localeDate->formatDateTime(
                        date('Y-m-d H:i:s', $response->date/1000),
                        2,
                        2
                    ),
                    'Reference' => $response->reference,
                    'Amount'    => $formattedPrice,
                    'Real ID'   => $response->id,
                ];
                $payment->setIsTransactionClosed(true);
                $payment->setTransactionAdditionalInfo(Transaction::RAW_DETAILS, $transactionData);
                $payment->setAdditionalInformation($paymentData);
                $payment->save();

                $order->addStatusHistoryComment(
                    __(
                        'BOLTPAY INFO :: Reference: %1 Status: %2 Amount: %3 Transaction ID: "%4"',
                        $response->reference,
                        'REFUNDED',
                        $formattedPrice,
                        $realTransactionId.'-capture-refund'
                    )
                )->save();
            }
            $this->fetchTransactionInfo($payment, $realTransactionId);
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
}
