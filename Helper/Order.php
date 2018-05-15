<?php
/**
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bolt\Boltpay\Helper;

use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Model\Response;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DB\Transaction as DbTransaction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order as OrderModel;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment as PaymentModel;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;
use Zend_Http_Client_Exception;
use Exception;
use Magento\Sales\Model\Order\Invoice;
use Magento\Framework\DataObjectFactory;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\State;

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
     * @var QuoteFactory
     */
    private $quoteFactory;

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
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

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
     * @var Session
     */
    private $checkoutSession;

    /**
     * @param Context $context
     * @param ApiHelper $apiHelper
     * @param Config $configHelper
     * @param QuoteFactory $quoteFactory
     * @param ShippingMethodConverter $converter
     * @param RegionModel $regionModel
     * @param QuoteManagement $quoteManagement
     * @param OrderSender $emailSender
     * @param OrderRepository $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param QuoteRepository $quoteRepository
     * @param InvoiceService $invoiceService
     * @param DbTransaction $dbTransaction
     * @param InvoiceSender $invoiceSender
     * @param TransactionBuilder $transactionBuilder
     * @param TimezoneInterface $timezone
     * @param DataObjectFactory $dataObjectFactory
     * @param Session $checkoutSession
     * @param State $appState
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        ApiHelper $apiHelper,
        ConfigHelper $configHelper,
        QuoteFactory $quoteFactory,
        ShippingMethodConverter $converter,
        RegionModel $regionModel,
        QuoteManagement $quoteManagement,
        OrderSender $emailSender,
        OrderRepository $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        QuoteRepository $quoteRepository,
        InvoiceService $invoiceService,
        DbTransaction $dbTransaction,
        InvoiceSender $invoiceSender,
        TransactionBuilder $transactionBuilder,
        TimezoneInterface $timezone,
        DataObjectFactory $dataObjectFactory,
        Session $checkoutSession,
        State $appState
    ) {
        parent::__construct($context);
        $this->apiHelper             = $apiHelper;
        $this->configHelper          = $configHelper;
        $this->quoteFactory          = $quoteFactory;
        $this->converter             = $converter;
        $this->regionModel           = $regionModel;
        $this->quoteManagement       = $quoteManagement;
        $this->emailSender           = $emailSender;
        $this->orderRepository       = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->quoteRepository       = $quoteRepository;
        $this->invoiceService        = $invoiceService;
        $this->dbTransaction         = $dbTransaction;
        $this->invoiceSender         = $invoiceSender;
        $this->transactionBuilder    = $transactionBuilder;
        $this->timezone              = $timezone;
        $this->dataObjectFactory     = $dataObjectFactory;
        $this->checkoutSession       = $checkoutSession;
        $this->appState              = $appState;
    }

    /**
     * Load Order by increment id
     * @param $incrementId
     *
     * @return OrderModel
     */
    public function loadOrder($incrementId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId, 'eq')->create();
        $order = $this->orderRepository->getList($searchCriteria)->getFirstItem();

        return $order;
    }

    /**
     * Load Quote by increment id, reserved_order_id
     * @param $incrementId
     *
     * @return \Magento\Quote\Api\Data\CartInterface|Quote
     */
    public function loadQuote($incrementId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('reserved_order_id', $incrementId, 'eq')->create();
        $collection = $this->quoteRepository->getList($searchCriteria)->getItems();
        foreach ($collection as $quote) {
            return $quote;
        }
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
        $region = $this->regionModel->loadByName(@$address->region, @$address->country_code);

        $address_data = [
            'firstname'    => @$address->first_name,
            'lastname'     => @$address->last_name,
            'street'       => @$address->street_address1,
            'city'         => @$address->locality,
            'country_id'   => @$address->country_code,
            'region'       => @$address->region,
            'postcode'     => @$address->postal_code,
            'telephone'    => @$address->phone_number,
            'region_id'    => $region ? $region->getId() : null,
            'email'        => @$address->email_address,
        ];
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
    private function addCutomerDetails($quote, $guestEmail)
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
     * @return AbstractExtensibleModel|OrderInterface|null|object
     * @throws LocalizedException
     * @throws Exception
     */
    private function createOrder($quote, $transaction, $frontend)
    {
        $this->checkoutSession->replaceQuote($quote);

        $this->setShippingAddress($quote, $transaction);
        $this->setBillingAddress($quote, $transaction);
        $this->setShippingMethod($quote, $transaction);

        $email = @$transaction->order->cart->billing_address->email_address ?:
            @$transaction->order->cart->shipments[0]->shipping_address->email_address;

        if ($email) {
            $this->addCutomerDetails($quote, $email);
        }

        $this->setPaymentMethod($quote);
        $quote->collectTotals();

        $this->quoteRepository->save($quote);

        $order = $this->quoteManagement->submit($quote);

        // Send order confirmation email to customer.
        // Emulate frontend area in order for email
        // template to be loaded from the correct path
        // even if run from the hook.
        $this->appState->emulateAreaCode('frontend', function () use ($order){
            $this->emailSender->send($order);
        });

        return $order;
    }

    /**
     * Save/create the order (checkout, orphaned transaction),
     * Update order payment / transaction data (checkout, web hooks)
     *
     * @param string $reference     Bolt transaction reference
     * @param bool $frontend        true if called from front end page, bolt checkout
     *
     * @return mixed
     * @throws Exception
     * @throws LocalizedException
     * @throws Zend_Http_Client_Exception
     */
    public function saveUpdateOrder($reference, $frontend = true)
    {

        $transaction = $this->fetchTransactionInfo($reference);
        $incrementId = $transaction->order->cart->display_id;

        // load the quote from reserved order id
        $quote = $this->loadQuote($incrementId);

        if (!$quote || !$quote->getId()) {
            throw new LocalizedException(__('Unknown order id.'));
        }

        // check if the order exists
        $order = $this->loadOrder($incrementId);

        // if not create the order
        if (!$order || !$order->getId()) {
            $order = $this->createOrder($quote, $transaction, $frontend);
        }

        // update order payment transactions
        $this->updateOrderPayment($order, $transaction);

//        if ($frontend) {
            return [$quote, $order];
//        }
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
                'BOLTPAY INFO :: ZERO AMOUNT TRANSACTION :: ID: %1 Reference: %2 Status: %3 Amount: 0',
                $transaction->id,
                $transaction->reference,
                strtoupper($transaction->status)
            );

            $order->setStatus(OrderModel::STATE_PROCESSING)
                  ->setState(OrderModel::STATE_PROCESSING);

            $order->addStatusHistoryComment($comment);

            $paymentData = [
                'last_transaction_timestamp' => $date,
                'real_transaction_id'        => $transaction->id,
                'transaction_reference'      => $transaction->reference,
            ];

            $payment->setAdditionalInformation($paymentData);

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
                    $order_state = OrderModel::STATE_PAYMENT_REVIEW;
                    // REVIEW, IRREVERSIBLE_REJECT, REVERSIBLE_REJECT
                    $status = strtoupper($record->review->decision);
                    $comment = __(
                        'BOLTPAY INFO :: THE PAYMENT IS UNDER REVIEW. Reference: %1 Status: %2',
                        $transaction_reference,
                        $status
                    );
                    break;
                case 'failed':
                    $order_state = OrderModel::STATE_CANCELED;
                    $status = 'FAILED';
                    $comment = __('BOLTPAY INFO :: THE TRANSACTION HAS FAILED');
                    break;
                case 'authorized':
                    $order_state = OrderModel::STATE_PROCESSING;
                    $transaction_type = Transaction::TYPE_AUTH;
                    $transaction_id = $transaction->id;
                    $parent_transaction_id = null;
                    $status = 'AUTHORIZED';
                    $close_transaction = false;
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
                    if (!$payment_authorized) {
                        $parent_transaction_id = null;
                        $invoice_transaction = $transaction_id;
                    } else {
                        $invoice_transaction = $parent_transaction_id;
                    }
                    break;
                case 'credited':
                    $order_state = OrderModel::STATE_PROCESSING;
                    $transaction_type = Transaction::TYPE_REFUND;
                    $transaction_id = $transaction->id.'-capture-refund';
                    $parent_transaction_id = $transaction->id.'-capture';
                    $amount = $record->transaction->amount;
                    $transaction_reference = $record->transaction->reference;
                    $status = 'REFUNDED';
                    $comment =__(
                        'BOLTPAY INFO :: PAYMENT WAS REFUNDED FROM THE MERCHANT DASHBOARD. IT DOES NOT REFLECT IN THE ORDER TOTALS. TO SYNC THE DATA DO THE OFFLINE REFUND. AMOUNT REFUNDED: %1',
                        $amount->amount / 100
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

            // set the order status
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
                    'Time'      => $result = $this->timezone->formatDateTime(date('Y-m-d H:i:s', $date / 1000), 2, 2),
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
                    'BOLTPAY INFO :: Reference: %1 Status: %2 Amount: %3',
                    $transaction_reference,
                    $status,
                    $formattedPrice
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

            //Send invoice mail
            $this->invoiceSender->send($invoice);

            //Add notification comment to order
            $order->addStatusHistoryComment(
                __('Invoice #%1 is created. Notification email is sent to customer.', $invoice->getId())
            )->setIsCustomerNotified(true)->save();
        }

        return true;
    }
}
