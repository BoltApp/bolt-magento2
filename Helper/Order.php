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

namespace Bolt\Boltpay\Helper;

use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Model\Response;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DB\Transaction as DbTransaction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order as OrderModel;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment as PaymentModel;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use Magento\Sales\Model\Service\InvoiceService;
use Zend_Http_Client_Exception;
use Exception;
use Magento\Sales\Model\Order\Invoice;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\App\State;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Bolt\Boltpay\Helper\Session as SessionHelper;

/**
 * Class Order
 * Boltpay Order helper
 *
 * @package Bolt\Boltpay\Helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Order extends AbstractHelper
{

    /** @var State */
    private $appState;

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * Shipping method converter
     *
     * @var ShippingMethodConverter
     */
    private $converter;

    /**
     * @var RegionModel
     */
    private $regionModel;

    /**
     * @var QuoteManagement
     */
    private $quoteManagement;

    /**
     * @var OrderSender
     */
    private $emailSender;

    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var DbTransaction
     */
    private $dbTransaction;

    /**
     * @var InvoiceSender
     */
    private $invoiceSender;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var \Magento\Payment\Model\Info
     */
    private $quotePaymentInfoInstance = null;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /** @var SessionHelper */
    private $sessionHelper;

    /**
     * @param Context $context
     * @param ApiHelper $apiHelper
     * @param Config $configHelper
     * @param ShippingMethodConverter $converter
     * @param RegionModel $regionModel
     * @param QuoteManagement $quoteManagement
     * @param OrderSender $emailSender
     * @param InvoiceService $invoiceService
     * @param DbTransaction $dbTransaction
     * @param InvoiceSender $invoiceSender
     * @param TransactionBuilder $transactionBuilder
     * @param TimezoneInterface $timezone
     * @param DataObjectFactory $dataObjectFactory
     * @param State $appState
     * @param LogHelper $logHelper
     * @param Bugsnag $bugsnag
     * @param CartHelper $cartHelper
     * @param ResourceConnection $resourceConnection
     * @param SessionHelper $sessionHelper
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        ApiHelper $apiHelper,
        ConfigHelper $configHelper,
        ShippingMethodConverter $converter,
        RegionModel $regionModel,
        QuoteManagement $quoteManagement,
        OrderSender $emailSender,
        InvoiceService $invoiceService,
        DbTransaction $dbTransaction,
        InvoiceSender $invoiceSender,
        TransactionBuilder $transactionBuilder,
        TimezoneInterface $timezone,
        DataObjectFactory $dataObjectFactory,
        State $appState,
        LogHelper $logHelper,
        Bugsnag $bugsnag,
        CartHelper $cartHelper,
        ResourceConnection $resourceConnection,
        SessionHelper $sessionHelper
    ) {
        parent::__construct($context);
        $this->apiHelper = $apiHelper;
        $this->configHelper = $configHelper;
        $this->converter = $converter;
        $this->regionModel = $regionModel;
        $this->quoteManagement = $quoteManagement;
        $this->emailSender = $emailSender;
        $this->invoiceService = $invoiceService;
        $this->dbTransaction = $dbTransaction;
        $this->invoiceSender = $invoiceSender;
        $this->transactionBuilder = $transactionBuilder;
        $this->timezone = $timezone;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->appState = $appState;
        $this->logHelper = $logHelper;
        $this->bugsnag = $bugsnag;
        $this->cartHelper = $cartHelper;
        $this->resourceConnection = $resourceConnection;
        $this->sessionHelper = $sessionHelper;
    }

    /**
     * Fetch transaction details info
     *
     * @api
     *
     * @param string $reference
     *
     * @return Response
     * @throws LocalizedException
     * @throws Zend_Http_Client_Exception
     */
    public function fetchTransactionInfo($reference)
    {

        //Request Data
        $requestData = $this->dataObjectFactory->create();
        $requestData->setDynamicApiUrl(ApiHelper::API_FETCH_TRANSACTION . "/" . $reference);
        $requestData->setApiKey($this->configHelper->getApiKey());
        $requestData->setRequestMethod('GET');
        //Build Request
        $request = $this->apiHelper->buildRequest($requestData);

        $result = $this->apiHelper->sendRequest($request);
        $response = $result->getResponse();

//		$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/transaction.log');
//		$logger = new \Zend\Log\Logger();
//		$logger->addWriter($writer);
//		$logger->info(json_encode($response, JSON_PRETTY_PRINT));

        return $response;
    }

    /**
     * Set quote shipping method from transaction data
     *
     * @param Quote $quote
     * @param $transaction
     *
     * @throws Exception
     */
    private function setShippingMethod($quote, $transaction)
    {
        if ($quote->isVirtual()) return;

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true);

        $shippingMethod = $transaction->order->cart->shipments[0]->reference;

        $shippingAddress->setShippingMethod($shippingMethod)->save();
    }

    /**
     * Set Quote address data helper method.
     *
     * @param Address $quoteAddress
     * @param $address
     *
     * @throws Exception
     */
    private function setAddress($quoteAddress, $address)
    {
        $address = $this->cartHelper->handleSpecialAddressCases($address);

        $region = $this->regionModel->loadByName(@$address->region, @$address->country_code);

        $address_data = [
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
            $address_data['email'] = $address->email_address;
        }

        $quoteAddress->setShouldIgnoreValidation(true);
        $quoteAddress->addData($address_data)->save();
    }

    /**
     * Set Quote shipping address data.
     *
     * @param Quote $quote
     * @param $transaction
     *
     * @return void
     * @throws Exception
     */
    private function setShippingAddress($quote, $transaction)
    {
        $address = @$transaction->order->cart->shipments[0]->shipping_address;
        if ($address) {
            $this->setAddress($quote->getShippingAddress(), $address);
        }
    }

    /**
     * Set Quote billing address data.
     *
     * @param Quote $quote
     * @param $transaction
     *
     * @throws Exception
     */
    private function setBillingAddress($quote, $transaction)
    {
        $address = @$transaction->order->cart->billing_address;
        if ($address) {
            $this->setAddress($quote->getBillingAddress(), $address);
        }
    }

    /**
     * For guest checkout set customer email in quote
     *
     * @param Quote $quote
     * @param string $guestEmail
     *
     * @return void
     */
    private function addCustomerDetails($quote, $guestEmail)
    {
        if (!$quote->getCustomerId()) {
            $quote->setCustomerId(null);
            $quote->setCheckoutMethod('guest');
            $quote->setCustomerEmail($guestEmail);
            $quote->setCustomerIsGuest(true);
            $quote->setCustomerGroupId(GroupInterface::NOT_LOGGED_IN_ID);
        }
    }

    /**
     * Set Quote payment method, 'boltpay'
     *
     * @param Quote $quote
     *
     * @throws LocalizedException
     * @throws Exception
     */
    private function setPaymentMethod($quote)
    {
        $quote->setPaymentMethod(Payment::METHOD_CODE);
        // Set Sales Order Payment
        $quote->getPayment()->importData(['method' => Payment::METHOD_CODE])->save();
    }

    /**
     * Transform Quote to Order and send email to the customer.
     *
     * @param Quote $quote
     * @param mixed $transaction
     * @param bool $frontend
     *
     * @param string|null $bolt_trace_id
     * @return AbstractExtensibleModel|OrderInterface|null|object
     * @throws LocalizedException
     * @throws Exception
     */
    private function createOrder($quote, $transaction, $frontend, $bolt_trace_id = null)
    {
        // Load logged in customer checkout and customer sessions from cached session id.
        // Replace parent quote with immutable quote in checkout session.
        $this->sessionHelper->loadSession($quote);

        $this->setShippingAddress($quote, $transaction);
        $this->setBillingAddress($quote, $transaction);
        $this->setShippingMethod($quote, $transaction);

        $email = @$transaction->order->cart->billing_address->email_address ?:
            @$transaction->order->cart->shipments[0]->shipping_address->email_address;

        $this->addCustomerDetails($quote, $email);

        $this->setPaymentMethod($quote);

        $this->cartHelper->saveQuote($quote);

        // assign credit card info to the payment info instance
        $this->setQuotePaymentInfoData(
            $quote, [
                'cc_last_4' => @$transaction->from_credit_card->last4,
                'cc_type' => @$transaction->from_credit_card->network
            ]
        );

        // check if the order has been created in the meanwhile
        /** @var OrderModel $order */
        $order = $this->cartHelper->getOrderByIncrementId($quote->getReservedOrderId());

        if ($order && $order->getId()) {
            throw new LocalizedException(__(
                'Duplicate Order Creation Attempt. Order #: %1',
                $quote->getReservedOrderId()
            ));
        }

        $order = $this->quoteManagement->submit($quote);

        if ($frontend) {
            // Send order confirmation email to customer.
            $this->emailSender->send($order);
        } else {
            $order->addStatusHistoryComment(
                "BOLTPAY INFO :: THIS ORDER WAS CREATED VIA WEBHOOK<br>Bolt traceId: $bolt_trace_id"
            );
            $order->save();

            // Send order confirmation email to customer.
            // Emulate frontend area in order for email
            // template to be loaded from the correct path
            // even if run from the hook.
            $this->appState->emulateAreaCode('frontend', function () use ($order){
                $this->emailSender->send($order);
            });
        }

        return $order;
    }

    /**
     * @param Quote $quote
     * @param array $data
     * @return void
     */
    private function setQuotePaymentInfoData($quote, $data) {
        foreach ($data as $key => $value) {
            $this->getQuotePaymentInfoInstance($quote)->setData($key, $value);
        }
    }

    /**
     * @param Quote $quote
     * @return \Magento\Payment\Model\Info
     */
    private function getQuotePaymentInfoInstance($quote) {
        return $this->quotePaymentInfoInstance ?:
            $this->quotePaymentInfoInstance = $quote->getPayment()->getMethodInstance()->getInfoInstance();
    }

    /**
     * Delete redundant clones and parent quote.
     *
     * @param Quote $quote
     */
    private function deleteRedundantQuotes($quote) {

        $connection = $this->resourceConnection->getConnection();

        // get table name with prefix
        $tableName = $this->resourceConnection->getTableName('quote');

        $sql = "DELETE FROM {$tableName} WHERE bolt_parent_quote_id = :bolt_parent_quote_id AND entity_id != :entity_id";
        $bind = [
            'bolt_parent_quote_id' => $quote->getBoltParentQuoteId(),
            'entity_id' => $quote->getId()
        ];

        $connection->query($sql, $bind);
    }

    /**
     * Set parent quote as inactive.
     *
     * @param Quote $quote
     */
    public function deactivateParentQuote($quote) {
        try {
            $parentQuote = $this->cartHelper->getQuoteById($quote->getBoltParentQuoteId());
            $parentQuote->setIsActive(false);
        } catch (NoSuchEntityException $e) {
            $this->bugsnag->registerCallback(function ($report) use ($quote) {
                $report->setMetaData([
                    'QUOTE' => [
                        'quoteId' => $quote->getId(),
                        'parentId' => $quote->getBoltParentQuoteId(),
                        'orderId' => $quote->getReservedOrderId()
                    ]
                ]);
            });
        }
    }

    /**
     * Save/create the order (checkout, orphaned transaction),
     * Update order payment / transaction data (checkout, web hooks)
     *
     * @param string $reference     Bolt transaction reference
     * @param bool $frontend        false if called from api
     *
     * @return mixed
     * @throws Exception
     * @throws LocalizedException
     * @throws Zend_Http_Client_Exception
     */
    public function saveUpdateOrder($reference, $frontend = true, $bolt_trace_id = null)
    {
        $transaction = $this->fetchTransactionInfo($reference);

        ///////////////////////////////////////////////////////////////
        // Get order id and immutable quote id stored with transaction.
        // Take into account orders created with data in old format
        // where only reserved_order_id was stored in display_id field
        // and the immutable quote_id in order_reference
        ///////////////////////////////////////////////////////////////
        list($incrementId, $quoteId) = array_pad(
            explode(' / ', $transaction->order->cart->display_id),
            2,
            null
        );
        if (!$quoteId) $quoteId = $transaction->order->cart->order_reference;
        ///////////////////////////////////////////////////////////////

        ///////////////////////////////////////////////////////////////
        // try loading (immutable) quote from entity id. if called from
        // hook the quote might have been cleared, resulting in error.
        // prevent failure and log event to bugsnag.
        ///////////////////////////////////////////////////////////////
        try {
            $quote = $this->cartHelper->getQuoteById($quoteId);
        } catch (NoSuchEntityException $e) {
            $this->bugsnag->registerCallback(function ($report) use ($incrementId, $quoteId) {
                $report->setMetaData([
                    'ORDER' => [
                        'incrementId' => $incrementId,
                        'quoteId' => $quoteId,
                    ]
                ]);
            });
            $quote = null;
        }
        ///////////////////////////////////////////////////////////////

        // check if the order exists
        $order = $this->cartHelper->getOrderByIncrementId($incrementId);

        // if not create the order
        if (!$order || !$order->getId()) {

            if (!$quote || !$quote->getId()) {
                throw new LocalizedException(__('Unknown quote id: %1', $quoteId));
            }

            $order = $this->createOrder($quote, $transaction, $frontend, $bolt_trace_id);

            // Add the user_note to the order comments and make it visible for customer.
            if (isset($transaction->order->user_note)) {

                $this->setOrderUserNote($order, $transaction->order->user_note);

            }
        }

        if ($quote) {
            // Set parent quote as inactive
            $this->deactivateParentQuote($quote);

            // Delete redundant clones and parent quote
            $this->deleteRedundantQuotes($quote);
        }

        // update order payment transactions
        $this->updateOrderPayment($order, $transaction);

        if ($frontend) {
            return [$quote, $order];
        }
    }

    /**
     * Add user note as status history comment
     *
     * @param OrderModel $order
     * @param string     $userNote
     *
     * @return OrderModel
     */
    public function setOrderUserNote($order, $userNote){

        $order
            ->addStatusHistoryComment($userNote)
            ->setIsVisibleOnFront(true)
            ->setIsCustomerNotified(false);

        return $order;
    }

    public function formatReferenceUrl($reference) {
        $url = $this->configHelper->getMerchantDashboardUrl().'/transaction/'.$reference;
        return '<a target="_blank" href="'.$url.'">'.$reference.'</a>';
    }

    /**
     * Update order payment / transaction data
     *
     * @param OrderModel $order
     * @param \stdClass $transaction
     * @param null $reference
     *
     * @throws Exception
     * @throws LocalizedException
     * @throws Zend_Http_Client_Exception
     */
    public function updateOrderPayment($order, $transaction = null, $reference = null)
    {
        // fetch transaction info if transaction is not passed as a parameter
        if ($reference && !$transaction) {
            $transaction = $this->fetchTransactionInfo($reference);
        }

        ////////////////////////////////////////////////////////////////////////////
        /// Record total amount mismatch between magento and bolt order.
        /// Log the error in order comments and report via bugsnag.
        ////////////////////////////////////////////////////////////////////////////
        $record_order_mismatch = function ($bolt_total) use ($order, $transaction) {

            $order->setStatus(OrderModel::STATE_HOLDED);
            $order->setState(OrderModel::STATE_HOLDED);

            $comment = __(
                'BOLTPAY INFO :: THERE IS A MISMATCH IN THE ORDER PAID AND ORDER RECORDED.<br>
                Paid amount: %1 Recorded amount: %2<br>Bolt transaction: %3',
                $bolt_total / 100,
                $order->getGrandTotal(),
                $this->formatReferenceUrl($transaction->reference)
            );

            $order->addStatusHistoryComment($comment);

            list(, $quoteId) = array_pad(
                explode(' / ', $transaction->order->cart->display_id),
                2,
                null
            );
            if (!$quoteId) $quoteId = $transaction->order->cart->order_reference;

            try {
                $quote = $this->cartHelper->getQuoteById($quoteId);
                $cart = $this->cartHelper->getCartData(true, false, $quote);
            } catch (NoSuchEntityException $e) {
                // Old quote cleaned by cron
                $cart = ['The quote does not exist.'];
            }

            $this->bugsnag->registerCallback(function ($report) use ($transaction, $cart) {
                $report->setMetaData([
                    'ORDER_MISMATCH' => [
                        'Bolt' => $transaction->order->cart,
                        'Store' => $cart,
                    ]
                ]);
            });
            $this->bugsnag->notifyError('Order Data Mismatch', $comment);
        };
        ////////////////////////////////////////////////////////////////////////////

        /** @var PaymentModel $payment */
        $payment = $order->getPayment();

        ////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // Process zero-amount transactions
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////
        if ($transaction->type == 'zero_amount') {

            $last_transaction_timestamp = (int)$payment->getAdditionalInformation('last_transaction_timestamp');
            $date = $transaction->date;

            if ($date <= $last_transaction_timestamp) {
                return;
            }

            $comment = __(
                'BOLTPAY INFO :: ZERO AMOUNT TRANSACTION :: ID: %1 Status: %2 Amount: 0<br>Bolt transaction: %3',
                $transaction->id,
                strtoupper($transaction->status),
                $this->formatReferenceUrl($transaction->reference)
            );

            $order->setStatus(OrderModel::STATE_PROCESSING);
            $order->setState(OrderModel::STATE_PROCESSING);

            $order->addStatusHistoryComment($comment);

            $paymentData = [
                'last_transaction_timestamp' => $date,
                'real_transaction_id'        => $transaction->id,
                'transaction_reference'      => $transaction->reference,
            ];

            $payment->setAdditionalInformation($paymentData);

            // Check for total amount mismatch between magento and bolt order.
            if (round($order->getGrandTotal() * 100) > 0) {
                $record_order_mismatch(0);
            }

            $payment->save();
            $order->save();

            return;
        }
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////

        // sort transaction timeline data ascendingly, it comes in descending order
        $timeline = array_reverse($transaction->timeline);

        // walk through the timeline records
        foreach ($timeline as $record) {
            // get last transaction timestamp stored with the order
            $last_transaction_timestamp = (int)$payment->getAdditionalInformation('last_transaction_timestamp');

            // get the transaction timestamp.
            // if the transaction is of type credit use the internal transactions date field
            $date = $record->type == 'credited' ? $record->transaction->date : $record->date;

            // check and skip if transaction is already saved
            if ($date <= $last_transaction_timestamp) {
                continue;
            }

            // preset default payment / transaction values
            // before more specific, record type related, changes below
            $amount                = $transaction->amount;
            $transaction_reference = $transaction->reference;
            $close_transaction     = true;
            $parent_transaction_id = $transaction->id;
            $transaction_type      = null;
            $transaction_id        = null;
            $invoice_transaction   = null;

            // flag represents if the transaction was previously authorized
            $payment_authorized = (bool)$payment->getAdditionalInformation('authorized');

            // order status history comment, set for the non transactions info records, review, failed...
            // when new transaction is saved, comment is added with the transaction, no separate comment is needed
            $comment = null;

            // determine the type of the timeline record and accordingly
            // set the order state, message data, status, transaction type,
            // transaction and parent transaction ids, close transaction flag,
            // amount, invoice transaction id, transaction reference
            switch ($record->type) {
                case 'review':
                    // REVIEW, IRREVERSIBLE_REJECT, REVERSIBLE_REJECT
                    $status = strtoupper($record->review->decision);
                    switch ($status) {
                        case 'IRREVERSIBLE_REJECT':
                            $order_state = OrderModel::STATE_CANCELED;
                            break;
                        case 'REVERSIBLE_REJECT':
                            $order_state = OrderModel::STATE_HOLDED;
                            break;
                        default:
                            $order_state = OrderModel::STATE_PAYMENT_REVIEW;
                    }
                    $comment = __(
                        'BOLTPAY INFO :: PAYMENT Status: %1<br>Bolt transaction: %2',
                        $status,
                        $this->formatReferenceUrl($transaction_reference)
                    );
                    break;
                case 'failed':
                    $order_state = OrderModel::STATE_CANCELED;
                    $status = 'FAILED';
                    $comment = __(
                        'BOLTPAY INFO :: THE TRANSACTION HAS FAILED.<br>Bolt transaction: %1',
                        $this->formatReferenceUrl($transaction_reference)
                    );
                    break;
                case 'authorized':
                    $order_state = OrderModel::STATE_PROCESSING;
                    $transaction_type = Transaction::TYPE_AUTH;
                    $transaction_id = $transaction->id;
                    $parent_transaction_id = null;
                    $status = 'AUTHORIZED';
                    $close_transaction = false;
                    // Bolt payment amount to check against Magento order total
                    $payment_amount = $amount->amount;
                    break;
                case 'voided':
                    $order_state = OrderModel::STATE_CANCELED;
                    $transaction_type = Transaction::TYPE_VOID;
                    $transaction_id = $transaction->id.'-void';
                    $status = 'CANCELED';
                    break;
                case 'completed':
                    $order_state = OrderModel::STATE_PROCESSING;
                    $transaction_type = Transaction::TYPE_CAPTURE;
                    $transaction_id = $transaction->id.'-capture';
                    $status = 'COMPLETED';
                    $amount = $transaction->capture->amount;
                    // Bolt payment amount to check against Magento order total
                    $payment_amount = $amount->amount;
                    if (!$payment_authorized) {
                        $parent_transaction_id = null;
                        $invoice_transaction = $transaction_id;
                    } else {
                        $invoice_transaction = $parent_transaction_id;
                    }
                    break;
                case 'credited':
                    $order_state = OrderModel::STATE_HOLDED;
                    $transaction_type = Transaction::TYPE_REFUND;
                    $transaction_id = $transaction->id.'-capture-refund';
                    $parent_transaction_id = $transaction->id.'-capture';
                    $amount = $record->transaction->amount;
                    $transaction_reference = $record->transaction->reference;
                    $status = 'REFUNDED';
                    $comment =__(
                        'BOLTPAY INFO :: PAYMENT WAS REFUNDED FROM THE MERCHANT DASHBOARD.
                         IT DOES NOT REFLECT IN THE ORDER TOTALS. TO SYNC THE DATA DO THE OFFLINE REFUND.
                         <br>AMOUNT REFUNDED: %1<br>Bolt transaction: %2',
                        $amount->amount / 100,
                        $this->formatReferenceUrl($transaction_reference)
                    );
                    break;
                // if type is "credit_completed" fetch the parent transaction info,
                // the matching record type being "credited", simplifies the logic
                case 'credit_completed':
                    $this->updateOrderPayment($order, null, $record->transaction->reference);
                    continue 2;
                default:
                    continue 2;
            }

            // set order status
            $order->setStatus($order_state);
            $order->setState($order_state);

            // add order history comment if any
            if ($comment) {
                $order->addStatusHistoryComment($comment)->save();
            }

            // format the last transaction data for storing within the order payment record instance
            $paymentData = [
                'last_transaction_timestamp' => $date,
                'real_transaction_id'        => $transaction->id,
                'transaction_reference'      => $transaction_reference,
                'authorized'                 => $payment_authorized || $status == 'AUTHORIZED',
            ];

            // there is a new transaction to be recorded
            if ($transaction_type) {
                // create the invoice for payment / capture transaction
                if ($invoice_transaction) {
                    $this->createOrderInvoice($order, $invoice_transaction, $amount->amount / 100);
                }

                // format the price with currency symbol
                $formattedPrice = $order->getBaseCurrency()->formatTxt($amount->amount / 100);

                // format the additional transaction data
                $transactionData = [
                    'Time'      => $result = $this->timezone->formatDateTime(
                        date('Y-m-d H:i:s', $date / 1000),
                        2,
                        2
                    ),
                    'Reference' => $transaction_reference,
                    'Amount'    => $formattedPrice,
                    'Real ID'   => $transaction->id,
                ];

                // update order payment instance
                $payment->setParentTransactionId($parent_transaction_id);
                $payment->setShouldCloseParentTransaction($parent_transaction_id ? true : false);
                $payment->setTransactionId($transaction_id);
                $payment->setLastTransId($transaction_id);
                $payment->setAdditionalInformation($paymentData);
                $payment->setIsTransactionClosed($close_transaction);

                // build a new transaction record and assign it to the order and payment
                /** @var Transaction $payment_transaction */
                $payment_transaction = $this->transactionBuilder->setPayment($payment)
                    ->setOrder($order)
                    ->setTransactionId($transaction_id)
                    ->setAdditionalInformation([ Transaction::RAW_DETAILS => $transactionData ])
                    ->setFailSafe(true)
                    ->build($transaction_type);

                // format transaction info message and add it to the order comments
                $message = __(
                    'BOLTPAY INFO :: PAYMENT Status: %1 Amount: %2<br>Bolt transaction: %3',
                    $status,
                    $formattedPrice,
                    $this->formatReferenceUrl($transaction_reference)
                );

                $payment->addTransactionCommentsToOrder(
                    $payment_transaction,
                    $message
                );
                $payment_transaction->save();
            } else {
                // just store the last transaction data.
                // the last_transaction_timestamp field is mandatory for avoiding duplicates
                // so this data is stored regardless of creating the new transaction record
                $payment->setAdditionalInformation($paymentData);
            }

            // Check for total amount mismatch between magento and bolt order.
            if (isset($payment_amount) && $payment_amount != round($order->getGrandTotal() * 100)) {
                $record_order_mismatch($payment_amount);
            }

            // save payment and order
            $payment->save();
            $order->save();
        }
    }

    /**
     * Create an invoice for the order.
     *
     * @param OrderModel $order
     * @param string $transaction_id
     * @param float $amount
     *
     * @return bool
     * @throws Exception
     * @throws LocalizedException
     */
    private function createOrderInvoice($order, $transaction_id, $amount)
    {
        //Check order invoices availability
        if ($order->hasInvoices()) {
            return false;
        }

        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
            $invoice->setTransactionId($transaction_id);
            $invoice->setBaseGrandTotal($amount);
            $invoice->setGrandTotal($amount);
            $invoice->register();

            $transactionSave = $this->dbTransaction->addObject(
                $invoice
            )->addObject(
                $invoice->getOrder()
            );
            $transactionSave->save();

            // Send invoice mail to customer.
            $this->invoiceSender->send($invoice);

            //Add notification comment to order
            $order->addStatusHistoryComment(
                __('Invoice #%1 is created. Notification email is sent to customer.', $invoice->getId())
            )->setIsCustomerNotified(true)->save();
        }
        return true;
    }
}
