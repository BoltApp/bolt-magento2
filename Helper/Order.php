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

use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Model\Api\CreateOrder;
use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Model\Response;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface as OrderRepository;
use Magento\Sales\Model\Order as OrderModel;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use Bolt\Boltpay\Model\Service\InvoiceService;
use Zend_Http_Client_Exception;
use Magento\Sales\Model\Order\Invoice;
use Magento\Framework\DataObjectFactory;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use \Magento\Framework\Stdlib\DateTime\DateTime;

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
    // Bolt transaction states
    const TS_PENDING               = 'cc_payment:pending';
    const TS_AUTHORIZED            = 'cc_payment:authorized';
    const TS_CAPTURED              = 'cc_payment:captured';
    const TS_PARTIAL_VOIDED        = 'cc_payment:partial_voided';
    const TS_COMPLETED             = 'cc_payment:completed';
    const TS_CANCELED              = 'cc_payment:cancelled';
    const TS_REJECTED_REVERSIBLE   = 'cc_payment:rejected_reversible';
    const TS_REJECTED_IRREVERSIBLE = 'cc_payment:rejected_irreversible';
    const TS_ZERO_AMOUNT           = 'zero_amount:completed';
    const TS_CREDIT_COMPLETED      = 'cc_credit:completed';

    const MISMATCH_TOLERANCE = 1;

    const BOLT_ORDER_STATE_NEW = 'bolt_new';
    const BOLT_ORDER_STATUS_PENDING = 'bolt_pending';
    const MAGENTO_ORDER_STATUS_PENDING = 'pending';

    const TT_PAYMENT = 'cc_payment';
    const TT_CREDIT = 'cc_credit';
    const TT_PAYPAL_PAYMENT = 'paypal_payment';
    const TT_PAYPAL_REFUND = 'paypal_refund';
    const TT_APM_PAYMENT = 'apm_payment';
    const TT_APM_REFUND = 'apm_refund';

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

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
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

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

    /** @var DiscountHelper */
    private $discountHelper;

    /** @var DateTime */
    protected $date;

    /**
     * @param Context $context
     * @param ApiHelper $apiHelper
     * @param Config $configHelper
     * @param RegionModel $regionModel
     * @param QuoteManagement $quoteManagement
     * @param OrderSender $emailSender
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param TransactionBuilder $transactionBuilder
     * @param TimezoneInterface $timezone
     * @param DataObjectFactory $dataObjectFactory
     * @param LogHelper $logHelper
     * @param Bugsnag $bugsnag
     * @param CartHelper $cartHelper
     * @param ResourceConnection $resourceConnection
     * @param SessionHelper $sessionHelper
     * @param DiscountHelper $discountHelper
     * @param DateTime $date
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        ApiHelper $apiHelper,
        ConfigHelper $configHelper,
        RegionModel $regionModel,
        QuoteManagement $quoteManagement,
        OrderSender $emailSender,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderRepository $orderRepository,
        TransactionBuilder $transactionBuilder,
        TimezoneInterface $timezone,
        DataObjectFactory $dataObjectFactory,
        LogHelper $logHelper,
        Bugsnag $bugsnag,
        CartHelper $cartHelper,
        ResourceConnection $resourceConnection,
        SessionHelper $sessionHelper,
        DiscountHelper $discountHelper,
        DateTime $date
    ) {
        parent::__construct($context);
        $this->apiHelper = $apiHelper;
        $this->configHelper = $configHelper;
        $this->regionModel = $regionModel;
        $this->quoteManagement = $quoteManagement;
        $this->emailSender = $emailSender;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->transactionBuilder = $transactionBuilder;
        $this->timezone = $timezone;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->logHelper = $logHelper;
        $this->bugsnag = $bugsnag;
        $this->cartHelper = $cartHelper;
        $this->resourceConnection = $resourceConnection;
        $this->sessionHelper = $sessionHelper;
        $this->discountHelper = $discountHelper;
        $this->date = $date;
    }

    /**
     * Fetch transaction details info
     *
     * @param string $reference
     * @param        $storeId
     *
     * @return Response
     * @throws LocalizedException
     * @throws Zend_Http_Client_Exception
     *
     * @api
     */
    public function fetchTransactionInfo($reference, $storeId = null)
    {
        //Request Data
        $requestData = $this->dataObjectFactory->create();
        $requestData->setDynamicApiUrl(ApiHelper::API_FETCH_TRANSACTION . "/" . $reference);
        $requestData->setApiKey($this->configHelper->getApiKey($storeId));
        $requestData->setRequestMethod('GET');
        //Build Request
        $request = $this->apiHelper->buildRequest($requestData);

        $result = $this->apiHelper->sendRequest($request);
        $response = $result->getResponse();

        return $response;
    }

    /**
     * Set quote shipping method from transaction data
     *
     * @param Quote $quote
     * @param $transaction
     *
     * @throws \Exception
     */
    private function setShippingMethod($quote, $transaction)
    {
        if ($quote->isVirtual()) {
            return;
        }

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
     * @throws \Exception
     */
    private function setAddress($quoteAddress, $address)
    {
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

        // discard empty address fields
        foreach ($addressData as $key => $value) {
            if (empty($value)) {
                unset($addressData[$key]);
            }
        }

        $quoteAddress->setShouldIgnoreValidation(true);
        $quoteAddress->addData($addressData)->save();
    }

    /**
     * Set Quote shipping address data.
     *
     * @param Quote $quote
     * @param $transaction
     *
     * @return void
     * @throws \Exception
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
     * @throws \Exception
     */
    private function setBillingAddress($quote, $transaction)
    {
        $address = @$transaction->order->cart->billing_address;
        if ($address) {
            $this->setAddress($quote->getBillingAddress(), $address);
        }
    }

    /**
     * Set quote customer email and guest checkout parameters
     *
     * @param Quote $quote
     * @param string $email
     *
     * @return void
     */
    private function addCustomerDetails($quote, $email)
    {
        $quote->setCustomerEmail($email);
        if (!$quote->getCustomerId()) {
            $quote->setCustomerId(null);
            $quote->setCheckoutMethod('guest');
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
     * @throws \Exception
     */
    private function setPaymentMethod($quote)
    {
        $quote->setPaymentMethod(Payment::METHOD_CODE);
        // Set Sales Order Payment
        $quote->getPayment()->importData(['method' => Payment::METHOD_CODE])->save();
    }

    /**
     * Check for Tax mismatch between Bolt and Magento.
     * Override store value with the Bolt one if a mismatch was found.
     *
     * @param \stdClass $transaction
     * @param OrderModel $order
     * @param Quote $quote
     */
    private function adjustTaxMismatch($transaction, $order, $quote)
    {
        $boltTaxAmount = round($transaction->order->cart->tax_amount->amount / 100, 2);
        $boltTotalAmount = round($transaction->order->cart->total_amount->amount / 100, 2);

        $orderTaxAmount = round($order->getTaxAmount(), 2);

        if ($boltTaxAmount != $orderTaxAmount) {
            $order->setTaxAmount($boltTaxAmount);
            $order->setBaseGrandTotal($boltTotalAmount);
            $order->setGrandTotal($boltTotalAmount);

            $this->bugsnag->registerCallback(function ($report) use ($quote, $boltTaxAmount, $orderTaxAmount) {

                $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();

                $report->setMetaData([
                    'TAX MISMATCH' => [
                        'Store Applied Taxes' => $address->getAppliedTaxes(),
                        'Bolt Tax Amount' => $boltTaxAmount,
                        'Store Tax Amount' => $orderTaxAmount,
                        'Order #' => $quote->getReservedOrderId(),
                        'Quote ID' => $quote->getId(),

                    ]
                ]);
            });

            $diff = round($boltTaxAmount - $orderTaxAmount, 2);
            $this->bugsnag->notifyError('Tax Mismatch', "Totals adjusted by $diff");
        }
    }

    /**
     * Check if the order has been created in the meanwhile
     * from another request, hook vs. frontend on a slow network / server
     *
     * @param int|string $incrementId
     * @return bool|OrderModel
     */
    private function checkExistingOrder($incrementId)
    {
        /** @var OrderModel $order */
        if ($order = $this->getExistingOrder($incrementId)) {
            $this->bugsnag->notifyError(
                'Duplicate Order Creation Attempt',
                null
            );
            return $order;
        }
        return false;
    }

    /**
     * Transform Quote to Order and send email to the customer.
     *
     * @param Quote $immutableQuote
     * @param \stdClass $transaction
     *
     * @param string|null $boltTraceId
     * @return AbstractExtensibleModel|OrderInterface|null|object
     * @throws LocalizedException
     * @throws \Exception
     */
    protected function createOrder($immutableQuote, $transaction, $boltTraceId = null)
    {
        // Load and prepare parent quote
        /** @var Quote $quote */
        $quote = $this->prepareQuote($immutableQuote, $transaction);

        if ($order = $this->checkExistingOrder($quote->getReservedOrderId())) {
            return $order;
        }
        try {
            /** @var OrderModel $order */
            $order = $this->quoteManagement->submit($quote);
        } catch (\Exception $e) {
            if ($order = $this->checkExistingOrder($quote->getReservedOrderId())) {
                return $order;
            }
            throw $e;
        }
        if ($order === null) {
            throw new LocalizedException(__(
                'Quote Submit Error. Order #: %1 Parent Quote ID: %2 Immutable Quote ID: %3',
                $quote->getReservedOrderId(),
                $quote->getId(),
                $immutableQuote->getId()
            ));
        }
        if (Hook::$fromBolt) {
            $order->addStatusHistoryComment(
                "BOLTPAY INFO :: This order was created via Bolt Webhook<br>Bolt traceId: $boltTraceId"
            );
        }
        $this->orderPostprocess($order, $quote, $transaction);
        return $order;
    }

    /**
     * Save additional order data and dispatch the after submit event
     *
     * @param OrderModel $order
     * @param Quote $quote
     * @param \stdClass $transaction
     */
    protected function orderPostprocess($order, $quote, $transaction)
    {
        // Check and fix tax mismatch
        if ($this->configHelper->shouldAdjustTaxMismatch()) {
            $this->adjustTaxMismatch($transaction, $order, $quote);
        }
        // Save reference to the Bolt transaction with the order
        if (isset($transaction->reference)) {
            $order->addStatusHistoryComment(
                __('Bolt transaction: %1', $this->formatReferenceUrl($transaction->reference))
            );
        }
        // Add the user_note to the order comments and make it visible for the customer.
        if (isset($transaction->order->user_note)) {
            $this->setOrderUserNote($order, $transaction->order->user_note);
        }
        $order->save();
    }

    /**
     * Assign data to the quote payment info instance
     *
     * @param Quote $quote
     * @param array $data
     * @return void
     */
    private function setQuotePaymentInfoData($quote, $data)
    {
        foreach ($data as $key => $value) {
            $this->getQuotePaymentInfoInstance($quote)->setData($key, $value)->save();
        }
    }

    /**
     * Returns quote payment info object
     *
     * @param Quote $quote
     * @return \Magento\Payment\Model\Info
     */
    private function getQuotePaymentInfoInstance($quote)
    {
        return $this->quotePaymentInfoInstance ?:
            $this->quotePaymentInfoInstance = $quote->getPayment()->getMethodInstance()->getInfoInstance();
    }

    /**
     * Delete redundant immutable quotes.
     *
     * @param Quote $quote
     */
    private function deleteRedundantQuotes($quote)
    {
        $connection = $this->resourceConnection->getConnection();

        // get table name with prefix
        $tableName = $this->resourceConnection->getTableName('quote');

        $sql = "DELETE FROM {$tableName} WHERE bolt_parent_quote_id = :bolt_parent_quote_id AND entity_id != :entity_id";
        $bind = [
            'bolt_parent_quote_id' => $quote->getBoltParentQuoteId(),
            'entity_id' => $quote->getBoltParentQuoteId()
        ];

        $connection->query($sql, $bind);
    }

    /**
     * After the order gets approved by Bolt set its state/status
     * to Magento default for the new orders "new"/"Pending".
     * Required for the order to be imported by some ERP systems.
     *
     * @param OrderModel $order
     * @param string $state
     */
    public function resetOrderState($order) {
        $order->setState(self::BOLT_ORDER_STATE_NEW);
        $order->setStatus(self::BOLT_ORDER_STATUS_PENDING);
        $order->addStatusHistoryComment(
            "BOLTPAY INFO :: This order was approved by Bolt"
        );
        $order->save();
    }

    /**
     * Save/create the order (checkout, orphaned transaction),
     * Update order payment / transaction data (checkout, web hooks)
     *
     * @param string $reference Bolt transaction reference
     * @param null   $boltTraceId
     * @param null   $hookType
     * @param null|int   $storeId
     *
     * @return array|mixed
     * @throws LocalizedException
     * @throws Zend_Http_Client_Exception
     */
    public function saveUpdateOrder($reference, $storeId = null, $boltTraceId = null, $hookType = null)
    {
        $transaction = $this->fetchTransactionInfo($reference, $storeId);

        $parentQuoteId = $transaction->order->cart->order_reference;

        ///////////////////////////////////////////////////////////////
        // Get order id and immutable quote id stored with transaction.
        // Take into account orders created with data in old format
        // where only reserved_order_id was stored in display_id field
        // and the immutable quote_id in order_reference
        ///////////////////////////////////////////////////////////////
        list($incrementId, $quoteId) = $this->getDataFromDisplayID($transaction->order->cart->display_id);

        if (!$quoteId) {
            $quoteId = $parentQuoteId;
        }
        ///////////////////////////////////////////////////////////////

        ///////////////////////////////////////////////////////////////
        // try loading (immutable) quote from entity id. if called from
        // hook the quote might have been cleared, resulting in error.
        // prevent failure and log event to bugsnag.
        ///////////////////////////////////////////////////////////////
        $quote = $this->cartHelper->getQuoteById($quoteId);
        if (!$quote) {
            $this->bugsnag->registerCallback(function ($report) use ($incrementId, $quoteId, $storeId) {
                $report->setMetaData([
                    'ORDER' => [
                        'incrementId' => $incrementId,
                        'quoteId' => $quoteId,
                        'Magento StoreId' => $storeId
                    ]
                ]);
            });
        }
        ///////////////////////////////////////////////////////////////

        // check if the order exists
        $order = $this->cartHelper->getOrderByIncrementId($incrementId);

        // if not create the order
        if (!$order || !$order->getId()) {
            if (!$quote) {
                throw new LocalizedException(__('Unknown quote id: %1', $quoteId));
            }
            $order = $this->createOrder($quote, $transaction, $boltTraceId);
        }

        if ($quote) {
            if ($order->getState() === OrderModel::STATE_PENDING_PAYMENT) {
                $this->resetOrderState($order);
            }
            $this->dispatchPostCheckoutEvents($order, $quote);
            // If Amasty Gif Cart Extension is present
            // clear gift carts applied to immutable quotes
            $this->discountHelper->deleteRedundantAmastyGiftCards($quote);

            // If Amasty Reward Points Extension is present
            // clear reward points applied to immutable quotes
            $this->discountHelper->deleteRedundantAmastyRewardPoints($quote);

            // Delete redundant cloned quotes
            $this->deleteRedundantQuotes($quote);
        }

        if (Hook::$fromBolt) {
            // if called from hook update order payment transactions
            $this->updateOrderPayment($order, $transaction, null, $hookType);
            // Check for total amount mismatch between magento and bolt order.
            $this->holdOnTotalsMismatch($order, $transaction);
        } else {
            // if called from the store controller return quote and order
            // wait for the hook call to update the payment
            return [$quote, $order];
        }
    }

    /**
     * Fetch and apply external quote data, not stored within the quote or totals
     * (usually in 3rd party modules DB tables)
     *
     * @param Quote $quote
     */
    public function applyExternalQuoteData($quote) {
        $this->discountHelper->applyExternalDiscountData($quote);
    }

    /**
     * @param OrderInterface $order
     * @param Quote $quote
     */
    public function dispatchPostCheckoutEvents($order, $quote) {
        // Use existing bolt_reserved_order_id quote field, not needed anymore for it's primary purpose,
        // as a flag to determine if the events were dispatched
        if (! $quote->getBoltReservedOrderId()) return; // already dispatched

        $this->applyExternalQuoteData($quote);

        // do not verify inventory, it is already reserved
        $quote->setInventoryProcessed(true);

        $this->logHelper->addInfoLog('[-= dispatchPostCheckoutEvents =-]');
        $this->_eventManager->dispatch(
            'checkout_submit_all_after', [
                'order' => $order,
                'quote' => $quote
            ]
        );

        // Nullify bolt_reserved_order_id. Prevents dispatching more then once.
        $quote->setBoltReservedOrderId(null);
        $this->cartHelper->quoteResourceSave($quote);
    }

    /**
     * Try to fetch and price-validate already existing order.
     * Use case: order has been created but the payment failes due to the ivnalid credit card data.
     * Then the customer enters the correct card info, the previously created order is used if the amounts match.
     *
     * @param Quote $quote
     * @param \stdClass $transaction
     * @return bool|false|OrderModel
     * @throws \Exception
     */
    public function processExistingOrder($quote, $transaction)
    {
        // check if the order has been created in the meanwhile
        if ($order = $this->getExistingOrder($quote->getReservedOrderId())) {

            if($order->isCanceled()) {
                throw new BoltException(
                    __('Order has been canceled due to the previously declined payment. Order #: %1 Quote ID: %2',
                        $quote->getReservedOrderId(),
                        $quote->getId()),
                    null,
                    CreateOrder::E_BOLT_REJECTED_ORDER
                );
            }

            if ($order->getState() === OrderModel::STATE_PENDING_PAYMENT) {
                throw new BoltException(
                    __('Order is in pending payment. Waiting for the hook update. Order #: %1 Quote ID: %2',
                        $quote->getReservedOrderId(),
                        $quote->getId()),
                    null,
                    CreateOrder::E_BOLT_GENERAL_ERROR
                );
            }

            if ($this->hasSamePrice($order, $transaction)) {
                return $order;
            }
            $this->deleteOrder($order);
        }
        return false;
    }

    /**
     * @param Quote $quote
     * @param \stdClass $transaction
     * @return OrderModel
     * @throws BoltException
     * @throws LocalizedException
     */
    public function processNewOrder($quote, $transaction)
    {
        /** @var OrderModel $order */
        $order = $this->quoteManagement->submit($quote);
        if ($order === null) {
            $this->bugsnag->registerCallback(function ($report) use ($quote) {
                $report->setMetaData([
                    'CREATE ORDER' => [
                        'pre-auth order.create' => true,
                        'order increment ID' => $quote->getReservedOrderId(),
                        'parent quote ID' => $quote->getId(),
                    ]
                ]);
            });
            throw new BoltException(
                __(
                    'Quote Submit Error. Order #: %1 Parent Quote ID: %2',
                    $quote->getReservedOrderId(),
                    $quote->getId()
                ),
                null,
                CreateOrder::E_BOLT_GENERAL_ERROR
            );
        }
        $order->addStatusHistoryComment(
            "BOLTPAY INFO :: This order was created via Bolt Pre-Auth Webhook"
        );
        $this->orderPostprocess($order, $quote, $transaction);
        return $order;
    }

    /**
     * @param OrderModel $order
     * @param \stdClass $transaction
     * @return bool
     * @throws \Exception
     */
    protected function hasSamePrice($order, $transaction)
    {

        $this->cartHelper->getRoundAmount($order->getTaxAmount());
        /** @var OrderModel $order */
        $taxAmount = $this->cartHelper->getRoundAmount($order->getTaxAmount());
        $shippingAmount = $this->cartHelper->getRoundAmount($order->getShippingAmount());
        $grandTotalAmount = $this->cartHelper->getRoundAmount($order->getGrandTotal());

        $transactionTaxAmount = $transaction->order->cart->tax_amount->amount;
        $transactionShippingAmount = $transaction->order->cart->shipping_amount->amount;
        $transactionGrandTotalAmount = $transaction->order->cart->total_amount->amount;

        return abs($taxAmount - $transactionTaxAmount) <= self::MISMATCH_TOLERANCE
            && abs($shippingAmount - $transactionShippingAmount) <= self::MISMATCH_TOLERANCE
            && $grandTotalAmount == $transactionGrandTotalAmount;
    }

    /**
     * Cancel and delete the order
     *
     * @param OrderModel $order
     * @throws \Exception
     */
    protected function deleteOrder($order)
    {
        try {
            $order->cancel()->save()->delete();
        } catch (\Exception $e) {
            $this->bugsnag->registerCallback(function ($report) use ($order) {
                $report->setMetaData([
                    'DELETE ORDER' => [
                        'order increment ID' => $order->getIncrementId(),
                        'order entity ID' => $order->getId(),
                    ]
                ]);
            });
            $this->bugsnag->notifyException($e);
            $order->delete();
        }
    }

    /**
     * Try to cancel the order. Covers the case when the payment was declined before authorization (blacklisted cc).
     * It is called upon rejected_irreversible hook.
     *
     * @param $displayId
     * @return bool
     * @throws BoltException
     */
    public function tryDeclinedPaymentCancelation($displayId)
    {
        list($incrementId, $quoteId) = $this->getDataFromDisplayID($displayId);
        $order = $this->getExistingOrder($incrementId);

        if (!$order) {
            throw new BoltException(
                __(
                    'Order Cancelation Error. Order does not exist. Order #: %1 Immutable Quote ID: %2',
                    $incrementId,
                    $quoteId
                ),
                null,
                CreateOrder::E_BOLT_GENERAL_ERROR
            );
        }

        if ($order->getState() === OrderModel::STATE_PENDING_PAYMENT) {
            $this->cancelOrder($order);
        }
        return $order->getState() === OrderModel::STATE_CANCELED;
    }

    /**
     * @param $displayId
     * @throws \Exception
     */
    public function deleteOrderByIncrementId($displayId)
    {
        list($incrementId, $quoteId) = $this->getDataFromDisplayID($displayId);

        $order = $this->getExistingOrder($incrementId);

        if (!$order) {
            $this->bugsnag->notifyError(
                "Order Delete Error",
                "Order does not exist. Order #: $incrementId, Immutable Quote ID: $quoteId"
            );
            return;
        }

        $state = $order->getState();
        if ($state !== OrderModel::STATE_PENDING_PAYMENT) {
            throw new BoltException(
                __(
                    'Order Delete Error. Order is in invalid state. Order #: %1 State: %2 Immutable Quote ID: %3',
                    $incrementId,
                    $state,
                    $quoteId
                ),
                null,
                CreateOrder::E_BOLT_GENERAL_ERROR
            );
        }

        $this->deleteOrder($order);
    }

    /**
     * @param $orderIncrementId
     * @return OrderModel|false
     */
    protected function getExistingOrder($orderIncrementId)
    {
        /** @var OrderModel $order */
        return $this->cartHelper->getOrderByIncrementId($orderIncrementId, true);
    }

    /**
     * Update quote change time and dispatch after save event
     *
     * @param Quote $quote
     */
    protected function quoteAfterChange($quote)
    {
        $quote->setUpdatedAt($this->date->gmtDate());
        $this->_eventManager->dispatch(
            'sales_quote_save_after', [
                'quote' => $quote
            ]
        );
    }

    /**
     * Load and prepare parent quote
     *
     * @param Quote $immutableQuote
     * @param  \stdClass $transaction
     * @return Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\SessionException
     * @throws \Zend_Validate_Exception
     */
    public function prepareQuote($immutableQuote, $transaction)
    {
        /** @var Quote $quote */
        $quote = $this->cartHelper->getQuoteById($immutableQuote->getBoltParentQuoteId());
        $this->cartHelper->replicateQuoteData($immutableQuote, $quote);
        $this->quoteAfterChange($quote);

        // Load logged in customer checkout and customer sessions from cached session id.
        // Replace quote in checkout session.
        $this->sessionHelper->loadSession($quote);

        $this->setShippingAddress($quote, $transaction);
        $this->setBillingAddress($quote, $transaction);
        $this->quoteAfterChange($quote);

        $this->setShippingMethod($quote, $transaction);
        $this->quoteAfterChange($quote);

        // Check if Mageplaza Gift Card data exist and apply it to the parent quote
        $this->discountHelper->applyMageplazaDiscountToQuote($quote);

        $this->setPaymentMethod($quote);
        $this->quoteAfterChange($quote);

        // assign credit card info to the payment info instance
        $this->setQuotePaymentInfoData(
            $quote,
            [
                'cc_last_4' => @$transaction->from_credit_card->last4,
                'cc_type' => @$transaction->from_credit_card->network
            ]
        );

        $email = @$transaction->order->cart->billing_address->email_address ?:
            @$transaction->order->cart->shipments[0]->shipping_address->email_address;
        $this->addCustomerDetails($quote, $email);

        $quote->setReservedOrderId($quote->getBoltReservedOrderId());
        $this->cartHelper->quoteResourceSave($quote);

        $this->bugsnag->registerCallback(function ($report) use ($quote, $immutableQuote) {
            $report->setMetaData([
                'CREATE ORDER' => [
                    'order increment ID' => $quote->getReservedOrderId(),
                    'parent quote ID' => $quote->getId(),
                    'immutable quote ID' => $immutableQuote->getId()
                ]
            ]);
        });

        return $quote;
    }

    /**
     * Add user note as status history comment
     *
     * @param OrderModel $order
     * @param string     $userNote
     */
    public function setOrderUserNote($order, $userNote)
    {
        $order
            ->addStatusHistoryComment($userNote)
            ->setIsVisibleOnFront(true)
            ->setIsCustomerNotified(false);
    }

    /**
     * Creates a link to the transaction page on the Bolt merchant dasboard
     * to be saved with the order payment info message.
     *
     * @param string $reference
     * @return string
     */
    public function formatReferenceUrl($reference)
    {
        $url = $this->configHelper->getMerchantDashboardUrl().'/transaction/'.$reference;
        return '<a href="'.$url.'">'.$reference.'</a>';
    }

    /**
     * Get processed items (captures or refunds) as an array
     *
     * @param OrderPaymentInterface $payment
     * @param string $itemType 'captures' | 'refunds'
     * @return array
     */
    private function getProcessedItems($payment, $itemType)
    {
        return array_filter(explode(',', $payment->getAdditionalInformation($itemType)));
    }

    /**
     * Get processed capture ids array
     *
     * @param OrderPaymentInterface $payment
     * @return array
     */
    private function getProcessedCaptures($payment)
    {
        return $this->getProcessedItems($payment, 'captures');
    }

    /**
     * Get processed refund ids array
     *
     * @param OrderPaymentInterface $payment
     * @return array
     */
    private function getProcessedRefunds($payment)
    {
        return $this->getProcessedItems($payment, 'refunds');
    }

    /**
     * Format the (class internal) unambiguous transaction state out of the type, status and previously recordered status.
     * Infer eventually missing states. Represent Bolt partial capture AUTHORIZED state as CAPTURED.
     *
     * @param \stdClass $transaction
     * @param OrderPaymentInterface $payment
     * @param null $hookType
     * @return string
     */
    public function getTransactionState($transaction, $payment, $hookType = null)
    {
        $transactionType = $transaction->type;
        // If it is an apm type, it needs to behave as regular payment/credit.
        // Since there are previous states saved, it needs to mimic "cc_payment"/"cc_credit"
        if (in_array($transactionType, [self::TT_PAYPAL_PAYMENT, self::TT_APM_PAYMENT])) {
            $transactionType = self::TT_PAYMENT;
        }
        if (in_array($transactionType, [self::TT_PAYPAL_REFUND, self::TT_APM_REFUND])) {
            $transactionType = self::TT_CREDIT;
        }
        $transactionState = $transactionType.":".$transaction->status;
        $prevTransactionState = $payment->getAdditionalInformation('transaction_state');
        $transactionReference = $payment->getAdditionalInformation('transaction_reference');
        $transactionId = $payment->getAdditionalInformation('real_transaction_id');
        $processedCaptures = $this->getProcessedCaptures($payment);

        // No previous state recorded.
        // Unless the state is TS_ZERO_AMOUNT, TS_COMPLETED (valid start transaction states, as well as TS_PENDING)
        // or TS_CREDIT_COMPLETED (for historical reasons, old orders refund,
        // legacy code when order was created, no state recorded) put it in TS_PENDING state.
        // It can corelate with the $transactionState or not in case the hook is late due connection problems and
        // the status has changed in the meanwhile.
        if (!$prevTransactionState && !$transactionReference && !$transactionId) {
            if (in_array($transactionState, [self::TS_ZERO_AMOUNT, self::TS_COMPLETED, self::TS_CREDIT_COMPLETED])) {
                return $transactionState;
            }
            return self::TS_PENDING;
        }

        // The previously recorded state is either TS_PENDING or TS_REJECTED_REVERSIBLE. Authorization occured but also
        // some of the funds were captured before the TS_AUTHORIZED state is recorded in Magento. Mark the transaction
        // as TS_AUTHORIZED and wait for the next hook to record the CAPTURE.
        if (in_array($prevTransactionState, [self::TS_PENDING, self::TS_REJECTED_REVERSIBLE]) &&
            $transactionState == self::TS_AUTHORIZED &&
            $transaction->captures
        ) {
            return self::TS_AUTHORIZED;
        }

        // The previously recorded state is either TS_PENDING or TS_REJECTED_REVERSIBLE and the transaction state is
        // TS_COMPLETED. If there is more than one capture in $transaction->captures array then the TS_AUTHORIZED state
        // is missing. Set the state to TS_AUTHORIZED and process captures on next hook requests.
        if (in_array($prevTransactionState, [self::TS_PENDING, self::TS_REJECTED_REVERSIBLE]) &&
            $transactionState == self::TS_COMPLETED &&
            count($transaction->captures) > 1
        ) {
            return self::TS_AUTHORIZED;
        }

        // The transaction was in TS_AUTHORIZED state, now it's TS_COMPLETED but not all partial captures are
        // processed. Mark it TS_CAPTURED.
        if ($prevTransactionState == self::TS_AUTHORIZED &&
            $transactionState == self::TS_COMPLETED &&
            count($transaction->captures) - count($processedCaptures) > 1
        ) {
            return self::TS_CAPTURED;
        }

        // The transaction was TS_AUTHORIZED, now it has captures, put it in TS_CAPTURED state.
        if ($transactionState == self::TS_AUTHORIZED &&
            $transaction->captures
        ) {
            return self::TS_CAPTURED;
        }

        // Previous partial capture was partially or fully refunded. Transaction is still TS_AUTHORIZED on Bolt side.
        // Set it to TS_CAPTURED.
        if ($prevTransactionState == self::TS_CREDIT_COMPLETED &&
            $transactionState == self::TS_AUTHORIZED
        ) {
            return self::TS_CAPTURED;
        }

        // The transaction was in TS_CAPTURED state, now it's TS_COMPLETED and hook type is TYPE_VOID
        // Mark it TS_PARTIAL_VOIDED.
        if ($prevTransactionState == self::TS_CAPTURED &&
            $transactionState == self::TS_COMPLETED &&
            $hookType == Transaction::TYPE_VOID
        ) {
            return self::TS_PARTIAL_VOIDED;
        }

        // return transaction state as it is in fetched transaction info. No need to change it.
        return $transactionState;
    }

    /**
     * Record total amount mismatch between magento and bolt order.
     * Log the error in order comments and report via bugsnag.
     * Put the order ON HOLD if it's a mismatch.
     *
     * @param OrderModel $order
     * @param \stdClass $transaction
     * @return bool true if the order was placed on hold, otherwise false
     */
    private function holdOnTotalsMismatch($order, $transaction)
    {
        $boltTotal = $transaction->order->cart->total_amount->amount;
        $storeTotal = round($order->getGrandTotal() * 100);

        // Stop if no mismatch
        if ($boltTotal == $storeTotal) {
            return;
        }

        // Put the order ON HOLD and add the status message.
        // Do it once only, skip on subsequent hooks
        if ($order->getState() != OrderModel::STATE_HOLDED) {
            // Add order status history comment
            $comment = __(
                'BOLTPAY INFO :: THERE IS A MISMATCH IN THE ORDER PAID AND ORDER RECORDED.<br>
             Paid amount: %1 Recorded amount: %2<br>Bolt transaction: %3',
                $boltTotal / 100,
                $order->getGrandTotal(),
                $this->formatReferenceUrl($transaction->reference)
            );
            $order->addStatusHistoryComment($comment);

            // Put the order on hold
            $this->setOrderState($order, OrderModel::STATE_HOLDED);
        }

        // Get the order and quote id
        list($incrementId, $quoteId) = $this->getDataFromDisplayID($transaction->order->cart->display_id);

        if (!$quoteId) {
            $quoteId = $transaction->order->cart->order_reference;
        }

        // If the quote exists collect cart data for bugsnag
        if ($quote = $this->cartHelper->getQuoteById($quoteId)) {
            $cart = $this->cartHelper->getCartData(true, false, $quote);
        } else {
            $cart = ['The quote does not exist.'];
        }

        // Log the debug info
        $this->bugsnag->registerCallback(function ($report) use (
            $transaction,
            $cart,
            $incrementId,
            $boltTotal,
            $storeTotal
        ) {
            $report->setMetaData([
                'TOTALS_MISMATCH' => [
                    'Reference' => $transaction->reference,
                    'Order ID' => $incrementId,
                    'Bolt Total' => $boltTotal,
                    'Store Total' => $storeTotal,
                    'Bolt Cart' => $transaction->order->cart,
                    'Store Cart' => $cart
                ]
            ]);
        });

        throw new LocalizedException(__(
            'Order Totals Mismatch Reference: %1 Order: %2 Bolt Total: %3 Store Total: %4',
            $transaction->reference, $incrementId, $boltTotal, $storeTotal
        ));
    }

    /**
     * Get (informal) transaction status to be stored with status history comment
     *
     * @param string $transactionState
     * @return string
     */
    private function getBoltTransactionStatus($transactionState)
    {
        return [
            self::TS_ZERO_AMOUNT => 'ZERO AMOUNT COMPLETED',
            self::TS_PENDING => 'UNDER REVIEW',
            self::TS_AUTHORIZED => 'AUTHORIZED',
            self::TS_CAPTURED => 'CAPTURED',
            self::TS_COMPLETED => 'COMPLETED',
            self::TS_CANCELED => 'CANCELED',
            self::TS_REJECTED_REVERSIBLE => 'REVERSIBLE REJECTED',
            self::TS_REJECTED_IRREVERSIBLE => 'IRREVERSIBLE REJECTED',
            self::TS_CREDIT_COMPLETED => Hook::$fromBolt ? 'REFUNDED UNSYNCHRONISED' : 'REFUNDED'
        ][$transactionState];
    }

    /**
     * Generate data to be stored with the transaction
     *
     * @param OrderModel $order
     * @param \stdClass $transaction
     * @param null|int $amount
     */
    private function formatTransactionData($order, $transaction, $amount)
    {
        return [
            'Time' => $this->timezone->formatDateTime(
                date('Y-m-d H:i:s', $transaction->date / 1000),
                2,
                2
            ),
            'Reference' => $transaction->reference,
            'Amount' => $order->getBaseCurrency()->formatTxt($amount / 100),
            'Transaction ID' => $transaction->id
        ];
    }

    /**
     * Return the first unprocessed capture from the captures array (or null)
     *
     * @param OrderPaymentInterface $payment
     * @param \stdClass $transaction
     * @return mixed
     */
    private function getUnprocessedCapture($payment, $transaction)
    {
        $processedCaptures = $this->getProcessedCaptures($payment);
        return @end(array_filter(
                $transaction->captures,
                function($capture) use ($processedCaptures) {
                    return !in_array($capture->id, $processedCaptures) && $capture->status == 'succeeded';
                })
        );
    }

    /**
     * Change order state taking transition constraints into account.
     *
     * @param OrderModel $order
     * @param string $state
     */
    public function setOrderState($order, $state)
    {
        $prevState = $order->getState();
        if ($state == OrderModel::STATE_HOLDED) {
            // Ensure order is in one of the "can hold" states [STATE_NEW | STATE_PROCESSING]
            // to avoid no state on admin order unhold
            if ($prevState !== OrderModel::STATE_PROCESSING) {
                $order->setState(OrderModel::STATE_PROCESSING);
                $order->setStatus($order->getConfig()->getStateDefaultStatus(OrderModel::STATE_PROCESSING));
            }
            try {
                $order->hold();
            } catch (\Exception $e) {
                // Put the order in "on hold" state even if the previous call fails
                $order->setState(OrderModel::STATE_HOLDED);
                $order->setStatus($order->getConfig()->getStateDefaultStatus(OrderModel::STATE_HOLDED));
            }
        } elseif ($state == OrderModel::STATE_CANCELED) {
            if ($order->canCancel()){
                $this->cancelOrder($order);
                return;
            }

            try{
                // Restock product quantity when the payment is irreversibly rejected
                $order->registerCancellation('', false);
            } catch (\Exception $e){
                // Put the order in "cancelled" state even if the previous call fails
                $order->setState(OrderModel::STATE_CANCELED);
                $order->setStatus($order->getConfig()->getStateDefaultStatus(OrderModel::STATE_CANCELED));
            }
        } else {
            $order->setState($state);
            $order->setStatus($order->getConfig()->getStateDefaultStatus($state));
        }
        $order->save();
    }

    /**
     * @param OrderModel $order
     */
    protected function cancelOrder($order)
    {
        try {
            $order->cancel();
            $order->setState(OrderModel::STATE_CANCELED);
            $order->setStatus($order->getConfig()->getStateDefaultStatus(OrderModel::STATE_CANCELED));
        } catch (\Exception $e) {
            // Put the order in "canceled" state even if the previous call fails
            $this->bugsnag->notifyException($e);
            $order->setState(OrderModel::STATE_CANCELED);
            $order->setStatus($order->getConfig()->getStateDefaultStatus(OrderModel::STATE_CANCELED));
        }
        $order->save();
    }

    /**
     * Check if order payment method was set to 'boltpay'
     *
     * @param OrderPaymentInterface $payment
     * @throws LocalizedException
     */
    private function checkPaymentMethod($payment)
    {
        $paymentMethod = $payment->getMethod();

        if ($paymentMethod != Payment::METHOD_CODE) {
            throw new LocalizedException(__(
                'Payment method assigned to order is: %1',
                $paymentMethod
            ));
        }
    }

    /**
     * Map class internal Bolt transaction state representation to Magento order state
     *
     * @param string $transactionState
     * @return string
     */
    public function transactionToOrderState($transactionState)
    {
        return [
            self::TS_ZERO_AMOUNT => OrderModel::STATE_PROCESSING,
            self::TS_PENDING => OrderModel::STATE_PAYMENT_REVIEW,
            self::TS_AUTHORIZED => OrderModel::STATE_PROCESSING,
            self::TS_CAPTURED => OrderModel::STATE_PROCESSING,
            self::TS_COMPLETED => OrderModel::STATE_PROCESSING,
            self::TS_CANCELED => OrderModel::STATE_CANCELED,
            self::TS_REJECTED_REVERSIBLE => OrderModel::STATE_PAYMENT_REVIEW,
            self::TS_REJECTED_IRREVERSIBLE => OrderModel::STATE_CANCELED,
            // Refunds need to be initiated from the store admin (Invoice -> Credit Memo)
            // If called from Bolt merchant dashboard there is no enough info to sync the totals
            self::TS_CREDIT_COMPLETED => Hook::$fromBolt ? OrderModel::STATE_HOLDED : OrderModel::STATE_PROCESSING
        ][$transactionState];
    }

    /**
     * Update order payment / transaction data
     *
     * @param OrderModel $order
     * @param null|\stdClass $transaction
     * @param null|string $reference
     * @param null $hookType
     *
     * @throws \Exception
     * @throws LocalizedException
     * @throws Zend_Http_Client_Exception
     */
    public function updateOrderPayment($order, $transaction = null, $reference = null, $hookType = null)
    {
        // Fetch transaction info if transaction is not passed as a parameter
        if ($reference && !$transaction) {
            $storeId = $order->getStoreId() ?: null;
            $transaction = $this->fetchTransactionInfo($reference, $storeId);
        } else {
            $reference = $transaction->reference;
        }

        /** @var OrderPaymentInterface $payment */
        $payment = $order->getPayment();

        $this->checkPaymentMethod($payment);

        // Get the last stored transaction parameters
        $prevTransactionState = $payment->getAdditionalInformation('transaction_state');
        $prevTransactionReference = $payment->getAdditionalInformation('transaction_reference');

        // Get the transaction state
        $transactionState = $this->getTransactionState($transaction, $payment, $hookType);

        $newCapture = in_array($transactionState, [self::TS_CAPTURED, self::TS_COMPLETED])
            ? $this->getUnprocessedCapture($payment, $transaction)
            : null;

        // Skip if there is no state change (i.e. fetch transaction call from admin panel / Payment model)
        // Reference check and $newCapture were added to support multiple refunds and captures,
        // valid same state transitions
        if (
            $transactionState == $prevTransactionState &&
            $reference == $prevTransactionReference &&
            !$newCapture
        ) {
            if ($this->isAnAllowedUpdateFromAdminPanel($order, $transactionState)){
                $payment->setIsTransactionApproved(true);
            }
            return;
        }

        // The order has already been canceled, i.e. PERMANENTLY REJECTED
        // Discard subsequent REJECTED_IRREVERSIBLE hooks processing and return (resulting in 200 OK response to Bolt)
        if ($transactionState == self::TS_REJECTED_IRREVERSIBLE &&
            $prevTransactionState == self::TS_REJECTED_IRREVERSIBLE
        ) {
            return;
        }

        // preset default payment / transaction values
        // before more specific changes below
        if ($newCapture) {
            $amount = $newCapture->amount->amount;
        } else {
            $amount = $transaction->amount->amount;
        }
        $transactionId = $transaction->id;

        $realTransactionId = $parentTransactionId = $payment->getAdditionalInformation('real_transaction_id');
        $realTransactionId = $realTransactionId ?: $transaction->id;
        $paymentAuthorized = (bool)$payment->getAdditionalInformation('authorized');

        $processedCaptures = $this->getProcessedCaptures($payment);
        $processedRefunds = $this->getProcessedRefunds($payment);

        switch ($transactionState) {

            case self::TS_ZERO_AMOUNT:
                $transactionType = Transaction::TYPE_ORDER;
                break;

            case self::TS_PENDING:
                $transactionType = Transaction::TYPE_ORDER;
                break;

            case self::TS_AUTHORIZED:
                $transactionType = Transaction::TYPE_AUTH;
                $transactionId = $transaction->id.'-auth';
                break;

            case self::TS_CAPTURED:
                if (!$newCapture) return;
                $transactionType = Transaction::TYPE_CAPTURE;
                $transactionId = $transaction->id.'-capture-'.$newCapture->id;
                $parentTransactionId = $transaction->id.'-auth';
                break;

            case self::TS_PARTIAL_VOIDED:
                $authorizationTransaction = $payment->getAuthorizationTransaction();
                $authorizationTransaction->closeAuthorization();
                $order->addCommentToStatusHistory($this->getVoidMessage($payment));
                $order->save();
                return;

            case self::TS_COMPLETED:
                if (!$newCapture) return;
                $transactionType = Transaction::TYPE_CAPTURE;
                if ($paymentAuthorized) {
                    $transactionId = $transaction->id.'-capture-'.$newCapture->id;
                    $parentTransactionId = $transaction->id.'-auth';
                } else {
                    $transactionId = $transaction->id.'-payment';
                }
                break;

            case self::TS_CANCELED:
                $transactionType = Transaction::TYPE_VOID;
                $transactionId = $transaction->id.'-void';
                $parentTransactionId = $paymentAuthorized ? $transaction->id.'-auth' : $transaction->id;
                break;

            case self::TS_REJECTED_REVERSIBLE:
                $transactionType = Transaction::TYPE_ORDER;
                $transactionId = $transaction->id.'-rejected_reversible';
                break;

            case self::TS_REJECTED_IRREVERSIBLE:
                $transactionType = Transaction::TYPE_ORDER;
                $transactionId = $transaction->id.'-rejected_irreversible';
                break;

            case self::TS_CREDIT_COMPLETED:
                if (in_array($transaction->id, $processedRefunds)) return;
                $transactionType = Transaction::TYPE_REFUND;
                $transactionId = $transaction->id.'-refund';
                break;

            default:
                throw new LocalizedException(__(
                    'Unhandled transaction state : %1',
                    $transactionState
                ));
                break;
        }

        // set order state and status
        $orderState = $this->transactionToOrderState($transactionState);
        $this->setOrderState($order, $orderState);

        // Send order confirmation email to customer.
        if ( ! $order->getEmailSent() ) {
            try {
                $this->emailSender->send($order);
            } catch (\Exception $e) {
                $this->bugsnag->notifyException($e);
            }
        }

        // format the last transaction data for storing within the order payment record instance

        if ($newCapture) {
            array_push($processedCaptures, $newCapture->id);
        }

        if ($transactionState == self::TS_CREDIT_COMPLETED) {
            array_push($processedRefunds, $transaction->id);
        }

        $paymentData = [
            'real_transaction_id' => $realTransactionId,
            'transaction_reference' => $transaction->reference,
            'transaction_state' => $transactionState,
            'authorized' => $paymentAuthorized || in_array($transactionState, [self::TS_AUTHORIZED, self::TS_CAPTURED]),
            'captures' => implode(',', $processedCaptures),
            'refunds' => implode(',', $processedRefunds)
        ];

        // format the price with currency symbol
        $formattedPrice = $order->getBaseCurrency()->formatTxt($amount / 100);

        $message = __(
            'BOLTPAY INFO :: PAYMENT Status: %1 Amount: %2<br>Bolt transaction: %3',
            $this->getBoltTransactionStatus($transactionState),
            $formattedPrice,
            $this->formatReferenceUrl($transaction->reference)
        );

        $transactionData = $this->formatTransactionData($order, $transaction, $amount);

        // update order payment instance
        $payment->setParentTransactionId($parentTransactionId);
        $payment->setTransactionId($transactionId);
        $payment->setLastTransId($transactionId);
        $payment->setAdditionalInformation($paymentData);
        $payment->setIsTransactionClosed($transactionType != Transaction::TYPE_AUTH);

        // We will create an invoice if we have zero amount or new capture.
        if ($this->isCaptureHookRequest($newCapture) || $this->isZeroAmountHook($transactionState)) {
            $this->validateCaptureAmount($order, $amount / 100);
            $invoice = $this->createOrderInvoice($order, $realTransactionId, $amount / 100);
        }

        if (!$order->getTotalDue()) {
            $payment->setShouldCloseParentTransaction(true);
        }

        if ($newCapture && @$invoice) {
            $this->_eventManager->dispatch(
                'sales_order_payment_capture',
                ['payment' => $payment, 'invoice' => $invoice]
            );
        }

        // build a new transaction record and assign it to the order and payment
        /** @var Transaction $payment_transaction */
        $payment_transaction = $this->transactionBuilder->setPayment($payment)
            ->setOrder($order)
            ->setTransactionId($transactionId)
            ->setAdditionalInformation([
                Transaction::RAW_DETAILS => $transactionData
            ])
            ->setFailSafe(true)
            ->build($transactionType);

        $payment->addTransactionCommentsToOrder(
            $payment_transaction,
            $message
        );

        $payment_transaction->save();

        // save payment and order
        $payment->save();
        $order->save();
    }

    /**
     * Create an invoice for the order.
     *
     * @param OrderModel $order
     * @param string $transactionId
     * @param float $amount
     *
     * @return bool
     * @throws \Exception
     * @throws LocalizedException
     */
    private function createOrderInvoice($order, $transactionId, $amount)
    {
        try {
            if ($this->cartHelper->getRoundAmount($order->getTotalInvoiced() + $amount) === $this->cartHelper->getRoundAmount($order->getGrandTotal())) {
                $invoice = $this->invoiceService->prepareInvoice($order);
            } else {
                $invoice = $this->invoiceService->prepareInvoiceWithoutItems($order, $amount);
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        $invoice->setTransactionId($transactionId);
        $invoice->setBaseGrandTotal($amount);
        $invoice->setGrandTotal($amount);
        $invoice->register();
        $invoice->save();

        $order->addRelatedObject($invoice);

        if (!$invoice->getEmailSent()) {
            try {
                $this->invoiceSender->send($invoice);
            } catch (\Exception $e) {
                $this->bugsnag->notifyException($e);
            }
        }

        //Add notification comment to order
        $order->addStatusHistoryComment(
            __('Invoice #%1 is created. Notification email is sent to customer.', $invoice->getId())
        )->setIsCustomerNotified(true)->save();

        return $invoice;
    }

    /**
     * @param $transactionState
     * @return bool
     */
    public function isZeroAmountHook($transactionState)
    {
        return Hook::$fromBolt && ($transactionState === self::TS_ZERO_AMOUNT);
    }

    /**
     * Check if the hook is a capture request
     *
     * @param \stdClass $newCapture first unprocessed capture from the captures array
     *
     * @return bool
     */
    protected function isCaptureHookRequest($newCapture)
    {
        return  Hook::$fromBolt && $newCapture;
    }

    /**
     * @param OrderInterface $order
     * @param float          $captureAmount
     *
     * @return void
     * @throws \Exception
     */
    protected function validateCaptureAmount(OrderInterface $order, $captureAmount)
    {
        if (!isset($captureAmount) || !is_numeric($captureAmount) || $captureAmount < 0) {
            throw new \Exception( __('Capture amount is invalid'));
        }

        /**
         * Due to grand total sent to Bolt is rounded, the same operation should be used
         * when validating captured amount.
         * Rounding operations are applied to each operand in order to avoid cases when the grand total
         * is formally less (before it has been rounded) than the sum of the captured amount and the total invoiced.
         */
        $captured = $this->cartHelper->getRoundAmount($order->getTotalInvoiced())
            + $this->cartHelper->getRoundAmount($captureAmount);
        $grandTotal = $this->cartHelper->getRoundAmount($order->getGrandTotal());

        if ($captured > $grandTotal) {
            throw new \Exception(
                __('Capture amount is invalid: captured [%s], grand total [%s]', $captured, $grandTotal)
            );
        }
    }

    /**
     * @param OrderInterface $order
     * @param $transactionState
     * @return bool
     */
    protected function isAnAllowedUpdateFromAdminPanel(OrderInterface $order, $transactionState)
    {
        return !Hook::$fromBolt &&
               in_array($transactionState, [self::TS_AUTHORIZED, self::TS_COMPLETED]) &&
               $order->getState() === OrderModel::STATE_PAYMENT_REVIEW;
    }

    /**
     * @param OrderPaymentInterface $payment
     *
     * @return \Magento\Framework\Phrase|string
     */
    protected function getVoidMessage(OrderPaymentInterface $payment)
    {
        $authorizationTransaction = $payment->getAuthorizationTransaction();
        /** @var OrderModel $order */
        $order = $payment->getOrder();
        $voidAmount = $order->getGrandTotal() - $order->getTotalPaid();

        $message = __('BOLT notification: Transaction authorization has been voided.');
        $message .= ' ' .__('Amount: %1.', $order->getBaseCurrency()->formatTxt($voidAmount, array()));
        $message .= ' ' .__('Transaction ID: %1.', $authorizationTransaction->getHtmlTxnId());

        return $message;
    }

    /**
     * @param $displayId
     *
     * @return array - [incrementId, quoteId]
     */
    public function getDataFromDisplayID($displayId)
    {
        return array_pad(
            explode(' / ', $displayId),
            2,
            null
        );
    }

    /**
     * @param $quoteId
     * @return int|null
     */
    public function getStoreIdByQuoteId($quoteId)
    {
        if (empty($quoteId)) {
            return null;
        }
        $quote = $this->cartHelper->getQuoteById($quoteId);
        return $quote ? $quote->getStoreId() : null;
    }

    /**
     * @param string $displayId
     * @return int|null
     */
    public function getOrderStoreIdByDisplayId($displayId)
    {
        if (empty($displayId)) {
            return null;
        }

        list($incrementId) = $this->getDataFromDisplayID($displayId);

        if (empty($incrementId)) {
            return null;
        }
        $order = $this->cartHelper->getOrderByIncrementId(trim($incrementId));

        return ($order && $order->getStoreId()) ? $order->getStoreId() : null;
    }
}
