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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Helper\Hook;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Model\Api\CreateOrder;
use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Model\Request;
use Bolt\Boltpay\Model\Response;
use Bolt\Boltpay\Model\Service\InvoiceService;
use Bugsnag\Report;
use Exception;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Event\Manager;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\SessionException;
use Magento\Payment\Model\Info;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface as TransactionInterface;
use Magento\Sales\Model\Order\Invoice as Invoice;
use Magento\Sales\Model\Order as OrderModel;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Directory\Model\Currency;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\OrderRepositoryInterface as OrderRepository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Exception\BoltException;
use ReflectionException;
use stdClass;
use Zend_Http_Client_Exception;
use Zend_Validate_Exception;
use Bolt\Boltpay\Test\Unit\TestHelper;

/**
 * @coversDefaultClass \Bolt\Boltpay\Helper\Order
 */
class OrderTest extends TestCase
{
    const INCREMENT_ID = 1234;
    const PARENT_QUOTE_ID = 5678;
    const IMMUTABLE_QUOTE_ID = self::PARENT_QUOTE_ID + 1;
    const DISPLAY_ID = self::INCREMENT_ID . " / " . self::IMMUTABLE_QUOTE_ID;
    const REFERENCE_ID = '1123123123';
    const STORE_ID = 1;
    const API_KEY = 'aaaabbbbcccc';
    const BOLT_TRACE_ID = 'aaaabbbbcccc';
    const ORDER_ID = 1233;
    const CURRENCY_CODE = 'USD';
    const TRANSACTION_ID = 'ABCD-1234-XXXX';
    const ADDRESS_DATA = [
        'region'                     => 'CA',
        'country_code'               => 'US',
        'email_address'              => 'test@bolt.com',
        'street_address1'            => 'Test Street 1',
        'street_address2'            => 'Test Street 2',
        'locality'                   => 'Beverly Hills',
        'postal_code'                => '90210',
        'phone_number'               => '0123456789',
        'company'                    => 'Bolt',
        'random_empty_field'         => '',
        'another_random_empty_field' => [],
    ];

    /** @var MockObject|ApiHelper */
    private $apiHelper;

    /** @var MockObject|ConfigHelper */
    private $configHelper;

    /** @var MockObject|RegionModel */
    private $regionModel;

    /** @var MockObject|QuoteManagement */
    private $quoteManagement;

    /** @var MockObject|OrderSender */
    private $emailSender;

    /** @var MockObject|InvoiceService */
    private $invoiceService;

    /** @var MockObject|InvoiceSender */
    private $invoiceSender;

    /** @var MockObject|TransactionBuilder */
    private $transactionBuilder;

    /** @var MockObject|TimezoneInterface */
    private $timezone;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var OrderRepository */
    private $orderRepository;

    /** @var MockObject|DataObjectFactory */
    private $dataObjectFactory;

    /** @var MockObject|LogHelper */
    private $logHelper;

    /** @var MockObject|Bugsnag */
    private $bugsnag;

    /** @var MockObject|CartHelper */
    private $cartHelper;

    /** @var MockObject|ResourceConnection */
    private $resourceConnection;

    /** @var MockObject|SessionHelper */
    private $sessionHelper;

    /** @var MockObject|DiscountHelper */
    private $discountHelper;

    /** @var MockObject|DateTime */
    protected $date;

    /** @var MockObject|OrderHelper */
    private $currentMock;

    /** @var MockObject|Order */
    private $orderMock;

    /** @var MockObject|Config */
    private $orderConfigMock;

    /** @var MockObject */
    private $context;

    /** @var MockObject|Quote */
    private $quoteMock;

    /** @var MockObject|InfoInterface */
    private $paymentMock;

    /** @var stdClass */
    private $transactionMock;

    /** @var MockObject|Manager */
    private $eventManager;

    /** @var MockObject|Mysql */
    private $connection;

    /**
     * @inheritdoc
     * @throws ReflectionException
     */
    protected function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock(
            [
                'getExistingOrder',
                'deleteOrder',
                'cancelOrder',
                'hasSamePrice',
                'orderPostprocess',
                'getUnprocessedCapture',
                'isCaptureHookRequest',
                'checkPaymentMethod',
                'getProcessedCaptures',
                'getProcessedRefunds',
            ]
        );
    }

    /**
     *
     */
    protected function tearDown()
    {
        Hook::$fromBolt = false;
    }

    /**
     * @param array $methods
     *
     * @param bool  $disableOriginalConstructor
     *
     * @throws ReflectionException
     */
    private function initCurrentMock($methods, $disableOriginalConstructor = false)
    {
        $currentMockBuilder = $this->getMockBuilder(OrderHelper::class)
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->apiHelper,
                    $this->configHelper,
                    $this->regionModel,
                    $this->quoteManagement,
                    $this->emailSender,
                    $this->invoiceService,
                    $this->invoiceSender,
                    $this->searchCriteriaBuilder,
                    $this->orderRepository,
                    $this->transactionBuilder,
                    $this->timezone,
                    $this->dataObjectFactory,
                    $this->logHelper,
                    $this->bugsnag,
                    $this->cartHelper,
                    $this->resourceConnection,
                    $this->sessionHelper,
                    $this->discountHelper,
                    $this->date
                ]
            )
            ->setMethods($methods);
        if ($disableOriginalConstructor) {
            $currentMockBuilder->disableOriginalConstructor();
            $currentMockBuilder->disableProxyingToOriginalMethods();
            $currentMockBuilder->disableOriginalClone();
        }
        $this->currentMock = $currentMockBuilder->getMock();
        TestHelper::setProperty($this->currentMock, 'cartHelper', $this->cartHelper);
        TestHelper::setProperty($this->currentMock, 'discountHelper', $this->discountHelper);
    }

    /**
     *
     */
    private function initRequiredMocks()
    {
        $this->eventManager = $this->createMock(Manager::class);
        $this->context = $this->createMock(Context::class);
        $this->apiHelper = $this->createMock(ApiHelper::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->regionModel = $this->createMock(RegionModel::class);
        $this->quoteManagement = $this->createMock(QuoteManagement::class);
        $this->emailSender = $this->createMock(OrderSender::class);
        $this->invoiceService = $this->createPartialMock(
            InvoiceService::class,
            [
                'prepareInvoice',
                'prepareInvoiceWithoutItems',
            ]
        );
        $this->invoiceSender = $this->createMock(InvoiceSender::class);
        $this->transactionBuilder = $this->createMock(TransactionBuilder::class);
        $this->timezone = $this->createMock(TimezoneInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->connection = $this->createMock(Mysql::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->sessionHelper = $this->createMock(SessionHelper::class);
        $this->discountHelper = $this->createMock(DiscountHelper::class);
        $this->date = $this->createMock(DateTime::class);

        $this->quoteMock = $this->createMock(Quote::class);

        $this->orderMock = $this->createPartialMock(
            Order::class,
            [
                'getConfig',
                'getState',
                'save',
                'hold',
                'setState',
                'setStatus',
                'isCanceled',
                'canCancel',
                'registerCancellation',
                'addStatusHistoryComment',
                'getTotalInvoiced',
                'getGrandTotal',
                'getPayment',
                'setIsCustomerNotified',
                'getOrderCurrencyCode',
                'getId',
                'getTaxAmount',
                'getShippingAmount',
                'cancel',
                'delete',
                'getIncrementId',
                'getTotalPaid',
                'setIsVisibleOnFront',
                'getTotalDue',
                'addCommentToStatusHistory',
                'setTaxAmount',
                'setBaseGrandTotal',
                'setGrandTotal',
                'getOrderCurrency'
            ]
        );
        $this->orderConfigMock = $this->createPartialMock(
            Config::class,
            [
                'getStateDefaultStatus'
            ]
        );
        $this->orderMock->method('getConfig')->willReturn($this->orderConfigMock);
        $this->orderMock->method('getOrderCurrencyCode')->willReturn(self::CURRENCY_CODE);

        $this->context->method('getEventManager')->willReturn($this->eventManager);
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
    }

    /**
     * @test
     *
     * @covers ::fetchTransactionInfo
     */
    public function fetchTransactionInfo()
    {
        /** @var MockObject|DataObject $requestObject */
        $requestObject = $this->createPartialMock(
            DataObject::class,
            [
                'setDynamicApiUrl',
                'setApiKey',
                'setRequestMethod'
            ]
        );
        $this->dataObjectFactory->expects(static::once())->method('create')->willReturn($requestObject);
        $requestObject->expects(static::once())->method('setDynamicApiUrl')
            ->with(ApiHelper::API_FETCH_TRANSACTION . "/" . static::REFERENCE_ID);

        $this->configHelper->expects(static::once())->method('getApiKey')->with(static::STORE_ID)
            ->willReturn(static::API_KEY);
        $requestObject->expects(static::once())->method('setApiKey')->with(static::API_KEY);
        $requestObject->expects(static::once())->method('setRequestMethod')->with('GET');

        $request = $this->createMock(Request::class);

        $this->apiHelper->expects(static::once())->method('buildRequest')->with($requestObject)
            ->willReturn($request);

        /** @var MockObject|Response $result */
        $result = $this->createPartialMock(Response::class, ['getResponse']);

        $this->apiHelper->expects(static::once())->method('sendRequest')->with($request)->willReturn($result);

        $response = json_encode(['display_id' => static::DISPLAY_ID]);

        $result->expects(static::once())->method('getResponse')->willReturn($response);

        static::assertEquals(
            $response,
            $this->currentMock->fetchTransactionInfo(static::REFERENCE_ID, static::STORE_ID)
        );
    }

    /**
     * @param bool $isVirtual
     *
     * @return array
     */
    private function setShippingMethodSetUp($isVirtual = false)
    {
        $quote = $this->createPartialMock(Quote::class, ['isVirtual', 'getShippingAddress']);
        $shippingAddress = $this->createPartialMock(
            Address::class,
            ['setCollectShippingRates', 'setShippingMethod', 'save']
        );
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->expects(static::once())->method('isVirtual')->willReturn($isVirtual);
        return [$quote, $shippingAddress];
    }

    /**
     * @test
     *
     * @covers ::setShippingMethod
     *
     * @throws ReflectionException
     */
    public function setShippingMethod_withVirtualQuote_doesNothing()
    {
        list($quote, $shippingAddress) = $this->setShippingMethodSetUp(true);

        $shippingAddress->expects(static::never())->method('setCollectShippingRates');
        $shippingAddress->expects(static::never())->method('setShippingMethod');
        $shippingAddress->expects(static::never())->method('save');

        TestHelper::invokeMethod(
            $this->currentMock, 'setShippingMethod', [$quote, new stdClass()]
        );
    }

    /**
     * @test
     *
     * @covers ::setShippingMethod
     *
     * @throws ReflectionException
     */
    public function setShippingMethod_withPhysicalQuote()
    {
        $shippingMethod = 'flatrate_flatrate';
        $transaction = json_decode(
            json_encode(
                [
                    'order' => [
                        'cart' => [
                            'shipments' => [
                                [
                                    'reference' => $shippingMethod
                                ]
                            ]
                        ]
                    ]
                ]
            )
        );

        /**
         * @var MockObject|Quote   $quote
         * @var MockObject|Address $shippingAddress
         */
        list($quote, $shippingAddress) = $this->setShippingMethodSetUp(false);

        $shippingAddress->expects(static::once())->method('setCollectShippingRates')->with(true);
        $shippingAddress->expects(static::once())->method('setShippingMethod')->with($shippingMethod)
            ->willReturnSelf();
        $shippingAddress->expects(static::once())->method('save');

        TestHelper::invokeMethod($this->currentMock, 'setShippingMethod', [$quote, $transaction]);
    }

    /**
     * @return array
     */
    private function setAddressSetUp()
    {
        $quoteAddressMock = $this->createPartialMock(
            Address::class,
            [
                'setShouldIgnoreValidation',
                'addData',
                'save'
            ]
        );
        $addressData = self::ADDRESS_DATA;
        return [$quoteAddressMock, $addressData];
    }

    /**
     * @test
     *
     * @covers ::setAddress
     *
     * @throws ReflectionException
     */
    public function setAddress()
    {
        list($quoteAddress, $address) = $this->setAddressSetUp();

        $addressObject = (object)$address;

        $this->cartHelper->expects(static::once())->method('handleSpecialAddressCases')->with($addressObject)
            ->willReturn($addressObject);
        $this->regionModel->expects(static::once())->method('loadByName')
            ->with($address['region'], $address['country_code'])->willReturnSelf();
        $this->regionModel->expects(static::once())->method('getId')->willReturn(12);

        $this->cartHelper->expects(static::once())->method('validateEmail')->with($address['email_address'])
            ->willReturn(true);

        $quoteAddress->expects(static::once())->method('setShouldIgnoreValidation')->with(true);
        $quoteAddress->expects(static::once())->method('addData')
            ->with(
                [
                    'street'     => "Test Street 1\nTest Street 2",
                    'city'       => 'Beverly Hills',
                    'country_id' => 'US',
                    'region'     => 'CA',
                    'postcode'   => '90210',
                    'telephone'  => '0123456789',
                    'region_id'  => 12,
                    'company'    => 'Bolt',
                    'email'      => 'test@bolt.com',
                ]
            )
            ->willReturnSelf();
        $quoteAddress->expects(static::once())->method('save');

        TestHelper::invokeMethod(
            $this->currentMock, 'setAddress', [$quoteAddress, $addressObject]
        );
    }

    /**
     * @test
     *
     * @covers ::adjustTaxMismatch
     *
     * @throws ReflectionException
     */
    public function adjustTaxMismatch()
    {
        $transaction = json_decode(
            json_encode(
                [
                    'order' => [
                        'cart' => [
                            'tax_amount'   => [
                                'amount' => 1000
                            ],
                            'total_amount' => [
                                'amount' => 1000
                            ]
                        ]
                    ]
                ]
            )
        );
        $quoteAddressMock = $this->createMock(Address::class);

        $this->orderMock->expects(self::once())->method('getOrderCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $this->orderMock->expects(self::once())->method('getTaxAmount')->willReturn(50);
        $this->orderMock->expects(self::once())->method('setTaxAmount')->willReturn(10);
        $this->orderMock->expects(self::once())->method('setBaseGrandTotal')->willReturn(10);
        $this->orderMock->expects(self::once())->method('setGrandTotal')->willReturn(10);

        $this->quoteMock->expects(self::once())->method('isVirtual')->willReturn(true);
        $this->quoteMock->expects(self::once())->method('getBillingAddress')->willReturn($quoteAddressMock);
        $this->quoteMock->expects(self::once())->method('getReservedOrderId')->willReturn(self::ORDER_ID);
        $this->quoteMock->expects(self::once())->method('getId')->willReturn(self::PARENT_QUOTE_ID);

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function ($callback) {
                $report = $this->createMock(Report::class);
                $report->expects(self::once())->method('setMetaData')->with(
                    [
                        'TAX MISMATCH' => [
                            'Store Applied Taxes' => null,
                            'Bolt Tax Amount'     => (float)10,
                            'Store Tax Amount'    => (float)50,
                            'Order #'             => self::ORDER_ID,
                            'Quote ID'            => self::PARENT_QUOTE_ID,
                        ]
                    ]
                );
                $callback($report);
            }
        );

        TestHelper::invokeMethod(
            $this->currentMock,
            'adjustTaxMismatch',
            [$transaction, $this->orderMock, $this->quoteMock]
        );
    }

    /**
     * @test
     *
     * @covers ::checkExistingOrder
     *
     * @throws ReflectionException
     */
    public function checkExistingOrder_orderAlreadyExists_notifiesError()
    {
        $this->initCurrentMock([]);
        $this->cartHelper->expects(self::once())->method('getOrderByIncrementId')
            ->with(self::INCREMENT_ID, true)->willReturn($this->orderMock);

        $this->bugsnag->expects(self::once())->method('notifyError')
            ->with('Duplicate Order Creation Attempt', null);

        static::assertSame(
            $this->orderMock,
            TestHelper::invokeMethod(
                $this->currentMock, 'checkExistingOrder', [self::INCREMENT_ID]
            )
        );
    }

    /**
     * @test
     *
     * @covers ::checkExistingOrder
     *
     * @throws ReflectionException
     */
    public function checkExistingOrder_orderDoesntExist_returnsFalse()
    {
        $this->initCurrentMock([]);
        $this->cartHelper->expects(self::once())->method('getOrderByIncrementId')
            ->with(self::INCREMENT_ID, true)->willReturn(false);

        $this->bugsnag->expects(self::never())->method('notifyError');

        static::assertFalse(
            TestHelper::invokeMethod(
                $this->currentMock, 'checkExistingOrder', [self::INCREMENT_ID]
            )
        );
    }

    /**
     * @param $quote
     *
     * @throws ReflectionException
     */
    private function createOrderSetUp($quote)
    {
        $this->initCurrentMock(['getExistingOrder', 'prepareQuote', 'orderPostprocess']);
        $this->currentMock->method('prepareQuote')->willReturn($quote);
    }

    /**
     * @test
     *
     * @covers ::createOrder
     *
     * @throws ReflectionException
     */
    public function createOrder_existingOrder()
    {
        $this->createOrderSetUp($this->quoteMock);
        $this->currentMock->method('getExistingOrder')->willReturn($this->orderMock);

        static::assertSame(
            $this->orderMock,
            TestHelper::invokeMethod($this->currentMock, 'createOrder', [$this->quoteMock, []])
        );
    }

    /**
     * @test
     *
     * @covers ::createOrder
     *
     * @throws ReflectionException
     */
    public function createOrder_newOrderQuoteSubmitExceptionWithOrderCreated_returnsOrder()
    {
        $this->createOrderSetUp($this->quoteMock);
        $this->currentMock->method('getExistingOrder')->willReturnOnConsecutiveCalls(null, $this->orderMock);
        $this->quoteManagement->expects(self::once())->method('submit')->with($this->quoteMock)
            ->willThrowException(new Exception(''));

        static::assertSame(
            $this->orderMock,
            TestHelper::invokeMethod($this->currentMock, 'createOrder', [$this->quoteMock, []])
        );
    }

    /**
     * @test
     *
     * @covers ::createOrder
     *
     * @throws ReflectionException
     */
    public function createOrder_newOrderQuoteSubmitExceptionWithOrderNotCreated_reThrowsException()
    {
        $this->createOrderSetUp($this->quoteMock);
        $this->currentMock->method('getExistingOrder')->willReturn(null);
        $this->quoteManagement->expects(self::once())->method('submit')->with($this->quoteMock)
            ->willThrowException(new Exception('Quote Submit Exception'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Quote Submit Exception');

        TestHelper::invokeMethod($this->currentMock, 'createOrder', [$this->quoteMock, []]);
    }

    /**
     * @test
     *
     * @covers ::createOrder
     *
     * @throws ReflectionException
     */
    public function createOrder_newOrderNotCreated_throwsException()
    {
        $immutableQuoteMock = $this->createPartialMock(Quote::class, ['getId']);
        $immutableQuoteMock->method('getId')->willReturn(self::IMMUTABLE_QUOTE_ID);

        $quoteMock = $this->createPartialMock(Quote::class, ['getReservedOrderId','getId']);
        $quoteMock->method('getReservedOrderId')->willReturn(self::INCREMENT_ID);
        $quoteMock->method('getId')->willReturn(self::PARENT_QUOTE_ID);

        $this->createOrderSetUp($quoteMock);
        $this->currentMock->method('getExistingOrder')->willReturn(null);
        $this->quoteManagement->expects(self::once())->method('submit')->with($quoteMock)
            ->willReturn(null);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Quote Submit Error. Order #: '.self::INCREMENT_ID.
            ' Parent Quote ID: '.self::PARENT_QUOTE_ID.
            ' Immutable Quote ID: '.self::IMMUTABLE_QUOTE_ID
        );

        TestHelper::invokeMethod($this->currentMock, 'createOrder', [$immutableQuoteMock, []]);
    }

    /**
     * @test
     *
     * @covers ::createOrder
     *
     * @throws ReflectionException
     */
    public function createOrder_newOrder_returnsOrder()
    {
        Hook::$fromBolt = true;
        $this->createOrderSetUp($this->quoteMock);
        $this->currentMock->method('getExistingOrder')->willReturn(null);
        $this->quoteManagement->expects(self::once())->method('submit')->with($this->quoteMock)
            ->willReturn($this->orderMock);

        $transaction = [];
        $this->currentMock
            ->expects(self::once())
            ->method('orderPostprocess')->with($this->orderMock, $this->quoteMock, $transaction);
        static::assertSame(
            $this->orderMock,
            TestHelper::invokeMethod(
                $this->currentMock, 'createOrder', [$this->quoteMock, $transaction]
            )
        );
    }

    /**
     * @test
     *
     * @covers ::orderPostprocess
     * @covers ::adjustTaxMismatch
     *
     * @throws ReflectionException
     */
    public function orderPostprocess()
    {
        $this->initCurrentMock(['setOrderUserNote']);
        $userNote = 'Test User Note';
        $transaction = json_decode(
            json_encode(
                [
                    'order'     => [
                        'cart'      => [
                            'tax_amount'   => [
                                'amount' => 500
                            ],
                            'total_amount' => [
                                'amount' => 500
                            ]
                        ],
                        'user_note' => $userNote
                    ],
                    'reference' => self::REFERENCE_ID
                ]
            )
        );
        $this->configHelper->expects(self::once())->method('shouldAdjustTaxMismatch')->willReturn(true);
        //adjust tax mismatch start
        $this->orderMock->expects(self::once())->method('getOrderCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $this->orderMock->expects(self::once())->method('getTaxAmount')->willReturn(5);
        //adjust tax mismatch end
        $this->configHelper->expects(self::once())->method('getMerchantDashboardUrl')
            ->willReturn('https://merchant-sandbox.bolt.com');
        $this->orderMock->expects(self::once())->method('addStatusHistoryComment')
            ->with(
                __(
                    'Bolt transaction: %1',
                    sprintf(
                        '<a href="https://merchant-sandbox.bolt.com/transaction/%1$s">%1$s</a>',
                        self::REFERENCE_ID
                    )
                )
            );

        $this->currentMock->expects(self::once())->method('setOrderUserNote')->with($this->orderMock, $userNote);
        $this->orderMock->expects(self::once())->method('save');
        TestHelper::invokeMethod(
            $this->currentMock,
            'orderPostprocess',
            [$this->orderMock, $this->quoteMock, $transaction]
        );
    }

    /**
     * @test
     *
     * @covers ::deleteRedundantQuotes
     *
     * @throws ReflectionException
     */
    public function deleteRedundantQuotes()
    {
        $quoteMock = $this->createPartialMock(Quote::class, ['getBoltParentQuoteId', 'getId']);
        $quoteMock->method('getBoltParentQuoteId')->willReturn(self::PARENT_QUOTE_ID);
        $quoteMock->method('getId')->willReturn(self::IMMUTABLE_QUOTE_ID);
        $this->resourceConnection->expects(self::once())->method('getTableName')->with('quote')
            ->willReturn('quote');
        $this->connection->expects(self::once())->method('delete')->with(
            'quote',
            [
                'bolt_parent_quote_id = ?' => self::PARENT_QUOTE_ID,
                'entity_id != ?'           => self::IMMUTABLE_QUOTE_ID
            ]
        );
        TestHelper::invokeMethod($this->currentMock, 'deleteRedundantQuotes', [$quoteMock]);
    }

    /**
     * @test
     *
     * @covers ::resetOrderState
     */
    public function resetOrderState()
    {
        /** @var MockObject|Order $orderMock */
        $orderMock = $this->createPartialMock(
            Order::class,
            ['setState', 'setStatus', 'addStatusHistoryComment', 'save']
        );
        $orderMock->expects(static::once())->method('setState')->with(OrderHelper::BOLT_ORDER_STATE_NEW);
        $orderMock->expects(static::once())->method('setStatus')->with(OrderHelper::BOLT_ORDER_STATUS_PENDING);
        $orderMock->expects(static::once())->method('addStatusHistoryComment')
            ->with('BOLTPAY INFO :: This order was approved by Bolt');
        $orderMock->expects(static::once())->method('save');
        $this->currentMock->resetOrderState($orderMock);
    }

    /**
     * Setup method for {@see saveUpdateOrder}
     *
     * @throws ReflectionException
     */
    private function saveUpdateOrderSetUp()
    {
        $this->initCurrentMock(
            [
                'fetchTransactionInfo',
                'getDataFromDisplayID',
                'createOrder',
                'resetOrderState',
                'dispatchPostCheckoutEvents',
                'updateOrderPayment'
            ],
            false
        );

        $transaction = json_decode(
            json_encode(
                [
                    'order' => [
                        'cart' => [
                            'order_reference' => self::PARENT_QUOTE_ID,
                            'display_id'      => self::DISPLAY_ID,
                            'total_amount'    => ['amount' => 100]
                        ]
                    ]
                ]
            )
        );

        $this->currentMock->expects(self::once())->method('fetchTransactionInfo')
            ->with(self::REFERENCE_ID, self::STORE_ID)->willReturn($transaction);
        $this->currentMock->expects(self::once())->method('getDataFromDisplayID')
            ->with(self::DISPLAY_ID)->willReturn([self::INCREMENT_ID, null]);
        return $transaction;
    }

    /**
     * @test
     *
     * @covers ::saveUpdateOrder
     */
    public function saveUpdateOrder()
    {
        $this->saveUpdateOrderSetUp();

        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::PARENT_QUOTE_ID)->willReturn($this->quoteMock);

        $this->bugsnag->expects(self::never())->method('registerCallback')->willReturnCallback(
            function ($callback) {
                $report = $this->createMock(Report::class);
                $report->expects(self::once())->method('setMetaData')->with(
                    [
                        'ORDER' => [
                            'incrementId'     => self::INCREMENT_ID,
                            'quoteId'         => self::PARENT_QUOTE_ID,
                            'Magento StoreId' => self::STORE_ID
                        ]
                    ]
                );
                $callback($report);
            }
        );

        $this->cartHelper->expects(self::once())->method('getOrderByIncrementId')
            ->with(self::INCREMENT_ID)->willReturn($this->orderMock);
        $this->orderMock->expects(self::once())->method('getId')->willReturn(self::ORDER_ID);
        $this->orderMock->expects(self::once())->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);
        $this->discountHelper->expects(self::once())->method('deleteRedundantAmastyGiftCards')->with($this->quoteMock);
        $this->discountHelper->expects(self::once())->method('deleteRedundantAmastyRewardPoints')->with(
            $this->quoteMock
        );

        static::assertEquals(
            [$this->quoteMock, $this->orderMock],
            $this->currentMock->saveUpdateOrder(
                self::REFERENCE_ID, self::STORE_ID, self::BOLT_TRACE_ID
            )
        );
    }

    /**
     * @test
     *
     * @covers ::saveUpdateOrder
     */
    public function saveUpdateOrder_noOrderNoQuote()
    {
        $this->saveUpdateOrderSetUp();

        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::PARENT_QUOTE_ID)->willReturn(null);

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function ($callback) {
                $report = $this->createMock(Report::class);
                $report->expects(self::once())->method('setMetaData')->with(
                    [
                        'ORDER' => [
                            'incrementId'     => self::INCREMENT_ID,
                            'quoteId'         => self::PARENT_QUOTE_ID,
                            'Magento StoreId' => self::STORE_ID
                        ]
                    ]
                );
                $callback($report);
            }
        );

        $this->cartHelper->expects(self::once())->method('getOrderByIncrementId')
            ->with(self::INCREMENT_ID)->willReturn(null);
        $this->orderMock->expects(self::never())->method('getId')->willReturn(self::ORDER_ID);
        $this->orderMock->expects(self::never())->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown quote id: ' . self::PARENT_QUOTE_ID);

        $this->currentMock->saveUpdateOrder(
            self::REFERENCE_ID, self::STORE_ID, self::BOLT_TRACE_ID
        );
    }

    /**
     * @test
     *
     * @covers ::saveUpdateOrder
     * @covers ::holdOnTotalsMismatch
     */
    public function saveUpdateOrder_createOrderFromWebhook()
    {
        Hook::$fromBolt = true;
        $transaction = $this->saveUpdateOrderSetUp();

        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::PARENT_QUOTE_ID)->willReturn($this->quoteMock);

        $this->bugsnag->expects(self::never())->method('registerCallback');

        $this->cartHelper->expects(self::once())->method('getOrderByIncrementId')
            ->with(self::INCREMENT_ID)->willReturn(null);

        $this->currentMock->expects(self::once())->method('createOrder')
            ->with($this->quoteMock, $transaction, self::BOLT_TRACE_ID)->willReturn($this->orderMock);

        $this->orderMock->expects(self::never())->method('getId')->willReturn(self::ORDER_ID);
        $this->orderMock->expects(self::atLeastOnce())->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);
        $this->orderMock->expects(self::atLeastOnce())->method('getGrandTotal')->willReturn(1);
        $this->currentMock->expects(self::once())->method('updateOrderPayment')
            ->with($this->orderMock, $transaction, null, $type = 'pending')->willReturn($this->orderMock);

        $this->currentMock->saveUpdateOrder(
            self::REFERENCE_ID, self::STORE_ID, self::BOLT_TRACE_ID, $type
        );
    }

    /**
     * @test
     *
     * @covers ::applyExternalQuoteData
     */
    public function applyExternalQuoteData()
    {
        /** @var MockObject|Quote $quoteMock */
        $quoteMock = $this->createMock(Quote::class);
        $this->discountHelper->expects(static::once())->method('applyExternalDiscountData')->with($quoteMock);
        $this->currentMock->applyExternalQuoteData($quoteMock);
    }

    /**
     * @return MockObject[]|Order[]|Quote[]
     */
    private function dispatchPostCheckoutEventsSetUp()
    {
        $orderMock = $this->createMock(Order::class);
        $quoteMock = $this->createPartialMock(
            Quote::class,
            [
                'getBoltReservedOrderId',
                'setBoltReservedOrderId',
                'setInventoryProcessed',
            ]
        );

        return [$orderMock, $quoteMock];
    }

    /**
     * @test
     *
     * @covers ::dispatchPostCheckoutEvents
     */
    public function dispatchPostCheckoutEvents()
    {
        list($orderMock, $immutableQuoteMock) = $this->dispatchPostCheckoutEventsSetUp();
        $immutableQuoteMock->expects(self::once())->method('getBoltReservedOrderId')->willReturn(true);
        $immutableQuoteMock->expects(self::once())->method('setInventoryProcessed')->with(true);

        $orderMock->expects(self::once())->method('getAppliedRuleIds')->willReturn(null);
        $orderMock->expects(self::once())->method('setAppliedRuleIds')->with('');
        $orderMock->expects(self::once())->method('setQuoteId')->with($immutableQuoteMock->getId())->willReturnSelf();

        $this->logHelper->expects(self::once())->method('addInfoLog')->with('[-= dispatchPostCheckoutEvents =-]');
        $this->eventManager->expects(self::once())->method('dispatch')
            ->with(
                'checkout_submit_all_after',
                [
                    'order' => $orderMock,
                    'quote' => $immutableQuoteMock
                ]
            );

        $immutableQuoteMock->expects(self::once())->method('setBoltReservedOrderId')->with(null);
        $this->cartHelper->expects(self::once())->method('quoteResourceSave')->with($immutableQuoteMock);

        $this->currentMock->dispatchPostCheckoutEvents($orderMock, $immutableQuoteMock);
    }

    /**
     * @test
     *
     * @covers ::processExistingOrder
     *
     * @throws Exception
     */
    public function processExistingOrder_noOrder()
    {
        $this->quoteMock->expects(self::once())->method('getReservedOrderId')
            ->willReturn(self::INCREMENT_ID);
        $this->currentMock->expects(self::once())->method('getExistingOrder')
            ->with(self::INCREMENT_ID)->willReturn(false);
        self::assertFalse($this->currentMock->processExistingOrder($this->quoteMock, new stdClass()));
    }

    /**
     * @test
     *
     * @covers ::processExistingOrder
     *
     * @throws Exception
     */
    public function processExistingOrder_canceledOrder()
    {
        $this->quoteMock->expects(self::exactly(2))->method('getReservedOrderId')
            ->willReturn(self::INCREMENT_ID);
        $this->quoteMock->expects(self::once())->method('getId')
            ->willReturn(self::PARENT_QUOTE_ID);
        $this->currentMock->expects(self::once())->method('getExistingOrder')
            ->with(self::INCREMENT_ID)->willReturn($this->orderMock);
        $this->orderMock->expects(self::once())->method('isCanceled')->willReturn(true);
        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Order has been canceled due to the previously declined payment. Order #: %s Quote ID: %s',
                self::INCREMENT_ID,
                self::PARENT_QUOTE_ID
            )
        );
        $this->expectExceptionCode(CreateOrder::E_BOLT_REJECTED_ORDER);
        self::assertFalse($this->currentMock->processExistingOrder($this->quoteMock, new stdClass()));
    }

    /**
     * @test
     *
     * @covers ::processExistingOrder
     *
     * @throws Exception
     */
    public function processExistingOrder_pendingOrder()
    {
        $this->quoteMock->expects(self::exactly(2))->method('getReservedOrderId')
            ->willReturn(self::INCREMENT_ID);
        $this->quoteMock->expects(self::once())->method('getId')
            ->willReturn(self::PARENT_QUOTE_ID);
        $this->currentMock->expects(self::once())->method('getExistingOrder')
            ->with(self::INCREMENT_ID)->willReturn($this->orderMock);
        $this->orderMock->expects(self::once())->method('isCanceled')
            ->willReturn(false);
        $this->orderMock->expects(self::once())->method('getState')
            ->willReturn(Order::STATE_PENDING_PAYMENT);
        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Order is in pending payment. Waiting for the hook update. Order #: %s Quote ID: %s',
                self::INCREMENT_ID,
                self::PARENT_QUOTE_ID
            )
        );
        $this->expectExceptionCode(CreateOrder::E_BOLT_GENERAL_ERROR);
        self::assertFalse($this->currentMock->processExistingOrder($this->quoteMock, new stdClass()));
    }

    /**
     * @test
     *
     * @covers ::processExistingOrder
     *
     * @throws Exception
     */
    public function processExistingOrder_samePriceOrder()
    {
        $transaction = new stdClass();
        $this->quoteMock->expects(self::once())->method('getReservedOrderId')
            ->willReturn(self::INCREMENT_ID);
        $this->currentMock->expects(self::once())->method('getExistingOrder')
            ->with(self::INCREMENT_ID)->willReturn($this->orderMock);
        $this->orderMock->expects(self::once())->method('isCanceled')
            ->willReturn(false);
        $this->orderMock->expects(self::once())->method('getState')
            ->willReturn(Order::STATE_CANCELED);
        $this->currentMock->expects(self::once())->method('hasSamePrice')
            ->with($this->orderMock, $transaction)->willReturn(true);

        self::assertEquals(
            $this->currentMock->processExistingOrder($this->quoteMock, $transaction),
            $this->orderMock
        );
    }

    /**
     * @test
     *
     * @covers ::processExistingOrder
     *
     * @throws Exception
     */
    public function processExistingOrder_deleteOrder()
    {
        $transaction = new stdClass();
        $this->quoteMock->expects(self::once())->method('getReservedOrderId')
            ->willReturn(self::INCREMENT_ID);
        $this->currentMock->expects(self::once())->method('getExistingOrder')
            ->with(self::INCREMENT_ID)->willReturn($this->orderMock);
        $this->orderMock->expects(self::once())->method('isCanceled')
            ->willReturn(false);
        $this->orderMock->expects(self::once())->method('getState')
            ->willReturn(Order::STATE_CANCELED);
        $this->currentMock->expects(self::once())->method('hasSamePrice')
            ->with($this->orderMock, $transaction)->willReturn(false);
        $this->currentMock->expects(self::once())->method('deleteOrder')
            ->with($this->orderMock);
        self::assertFalse(
            $this->currentMock->processExistingOrder($this->quoteMock, $transaction)
        );
    }

    /**
     * @test
     *
     * @covers ::processNewOrder
     */
    public function processNewOrder_fail()
    {
        $transaction = new stdClass();
        $this->quoteManagement->expects(self::once())->method('submit')
            ->with($this->quoteMock)->willReturn(null);
        $this->bugsnag->expects(self::once())->method('registerCallback');
        $this->quoteMock->expects(self::atLeastOnce())->method('getReservedOrderId')
            ->willReturn(self::INCREMENT_ID);
        $this->quoteMock->expects(self::atLeastOnce())->method('getId')
            ->willReturn(self::PARENT_QUOTE_ID);

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function ($callback) {
                $report = $this->createMock(Report::class);
                $report->expects(self::once())->method('setMetaData')->with(
                    [
                        'CREATE ORDER' => [
                            'pre-auth order.create' => true,
                            'order increment ID'    => self::INCREMENT_ID,
                            'parent quote ID'       => self::PARENT_QUOTE_ID,
                        ]
                    ]
                );
                $callback($report);
            }
        );

        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Quote Submit Error. Order #: %s Parent Quote ID: %s',
                self::INCREMENT_ID,
                self::PARENT_QUOTE_ID
            )
        );
        $this->expectExceptionCode(CreateOrder::E_BOLT_GENERAL_ERROR);
        $this->currentMock->processNewOrder($this->quoteMock, $transaction);
    }

    /**
     * @test
     *
     * @covers ::processNewOrder
     */
    public function processNewOrder_success()
    {
        $transaction = new stdClass();
        $this->quoteManagement->expects(self::once())->method('submit')
            ->with($this->quoteMock)->willReturn($this->orderMock);
        $this->orderMock->expects(self::once())->method('addStatusHistoryComment')
            ->with('BOLTPAY INFO :: This order was created via Bolt Pre-Auth Webhook');
        $this->currentMock->expects(self::once())->method('orderPostprocess')
            ->with($this->orderMock, $this->quoteMock, $transaction);
        self::assertEquals(
            $this->currentMock->processNewOrder($this->quoteMock, $transaction),
            $this->orderMock
        );
    }

    /**
     * @test
     *
     * @dataProvider hasSamePriceProvider
     *
     * @covers ::hasSamePrice
     *
     * @param float $orderTax
     * @param float $orderShipping
     * @param float $orderTotal
     * @param int   $txTax
     * @param int   $txShipping
     * @param int   $txTotal
     * @param bool  $expectedResult
     *
     * @throws ReflectionException
     */
    public function hasSamePrice($orderTax, $orderShipping, $orderTotal, $txTax, $txShipping, $txTotal, $expectedResult)
    {
        $this->orderMock->expects(self::once())->method('getOrderCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $this->orderMock->expects(self::once())->method('getTaxAmount')->willReturn($orderTax);
        $this->orderMock->expects(self::once())->method('getShippingAmount')->willReturn($orderShipping);
        $this->orderMock->expects(self::once())->method('getGrandTotal')->willReturn($orderTotal);
        $transaction = json_decode(
            json_encode(
                [
                    'order' => [
                        'cart' => [
                            'tax_amount'      => [
                                'amount' => $txTax
                            ],
                            'shipping_amount' => [
                                'amount' => $txShipping
                            ],
                            'total_amount'    => [
                                'amount' => $txTotal
                            ],
                        ]
                    ]
                ]
            )
        );

        static::assertSame(
            $expectedResult,
            TestHelper::invokeMethod($this->currentMock, 'hasSamePrice', [$this->orderMock, $transaction])
        );
    }

    /**
     * Data provider for {@see hasSamePrice}
     */
    public function hasSamePriceProvider()
    {
        return [
            "All totals zero"                      => [
                'orderTax'       => 0,
                'orderShipping'  => 0,
                'orderTotal'     => 0,
                'txTax'          => 0,
                'txShipping'     => 0,
                'txTotal'        => 0,
                'expectedResult' => true
            ],
            "Totals match"                         => [
                'orderTax'       => 100.55,
                'orderShipping'  => 100.66,
                'orderTotal'     => 100.77,
                'txTax'          => 10055,
                'txShipping'     => 10066,
                'txTotal'        => 10077,
                'expectedResult' => true
            ],
            "Complete mismatch"                    => [
                'orderTax'       => 100,
                'orderShipping'  => 100,
                'orderTotal'     => 100,
                'txTax'          => 2,
                'txShipping'     => 2,
                'txTotal'        => 2,
                'expectedResult' => false
            ],
            "Tax mismatch"                         => [
                'orderTax'       => 200,
                'orderShipping'  => 100,
                'orderTotal'     => 100,
                'txTax'          => 10000,
                'txShipping'     => 10000,
                'txTotal'        => 10000,
                'expectedResult' => false
            ],
            "Shipping mismatch"                    => [
                'orderTax'       => 100,
                'orderShipping'  => 345,
                'orderTotal'     => 100,
                'txTax'          => 10000,
                'txShipping'     => 10000,
                'txTotal'        => 10000,
                'expectedResult' => false
            ],
            "Grand total mismatch"                 => [
                'orderTax'       => 100,
                'orderShipping'  => 100,
                'orderTotal'     => 456,
                'txTax'          => 10000,
                'txShipping'     => 10000,
                'txTotal'        => 10000,
                'expectedResult' => false
            ],
            "Mismatch below tolerance"             => [
                'orderTax'       => 100,
                'orderShipping'  => 100,
                'orderTotal'     => 100,
                'txTax'          => 10001,
                'txShipping'     => 10001,
                'txTotal'        => 10000,
                'expectedResult' => true
            ],
            "Grand total mismatch below tolerance" => [
                'orderTax'       => 100.00,
                'orderShipping'  => 100.00,
                'orderTotal'     => 100.00,
                'txTax'          => 10001,
                'txShipping'     => 10001,
                'txTotal'        => 10001,
                'expectedResult' => false
            ],
        ];
    }

    /**
     * @test
     *
     * @covers ::deleteOrder
     *
     * @throws ReflectionException
     */
    public function deleteOrder()
    {
        $this->orderMock->expects(self::once())->method('cancel')->willReturnSelf();
        $this->orderMock->expects(self::once())->method('save')->willReturnSelf();
        $this->orderMock->expects(self::once())->method('delete')->willReturnSelf();
        TestHelper::invokeMethod($this->currentMock, 'deleteOrder', [$this->orderMock]);
    }

    /**
     * @test
     *
     * @covers ::deleteOrder
     *
     * @throws ReflectionException
     */
    public function deleteOrder_exception()
    {
        $exception = new Exception('');
        $this->orderMock->expects(self::once())->method('cancel')->willReturnSelf();
        $this->orderMock->expects(self::once())->method('save')->willThrowException($exception);
        $this->orderMock->expects(self::once())->method('getIncrementId')->willReturn(self::INCREMENT_ID);
        $this->orderMock->expects(self::once())->method('getId')->willReturn(self::ORDER_ID);

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function ($callback) {
                $report = $this->createMock(Report::class);
                $report->expects(self::once())->method('setMetaData')->with(
                    [
                        'DELETE ORDER' => [
                            'order increment ID' => self::INCREMENT_ID,
                            'order entity ID'    => self::ORDER_ID,
                        ]
                    ]
                );
                $callback($report);
            }
        );
        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->orderMock->expects(self::once())->method('delete');
        TestHelper::invokeMethod($this->currentMock, 'deleteOrder', [$this->orderMock]);
    }

    /**
     * @test
     *
     * @covers ::tryDeclinedPaymentCancelation
     *
     * @throws BoltException
     */
    public function tryDeclinedPaymentCancelation_noOrder()
    {
        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Order Cancelation Error. Order does not exist. Order #: %s Immutable Quote ID: %s',
                self::INCREMENT_ID,
                self::IMMUTABLE_QUOTE_ID
            )
        );
        $this->expectExceptionCode(CreateOrder::E_BOLT_GENERAL_ERROR);
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn(false);

        $this->currentMock->tryDeclinedPaymentCancelation(self::DISPLAY_ID);
    }

    /**
     * @test
     *
     * @covers ::tryDeclinedPaymentCancelation
     *
     * @throws BoltException
     */
    public function tryDeclinedPaymentCancelation_pendingOrder()
    {
        $this->orderMock->expects(static::exactly(2))->method('getState')
            ->willReturnOnConsecutiveCalls(Order::STATE_PENDING_PAYMENT, Order::STATE_CANCELED);
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn($this->orderMock);
        $this->currentMock->expects(static::once())->method('cancelOrder')->with($this->orderMock);

        self::assertTrue($this->currentMock->tryDeclinedPaymentCancelation(self::DISPLAY_ID));
    }

    /**
     * @test
     *
     * @covers ::tryDeclinedPaymentCancelation
     *
     * @throws BoltException
     */
    public function tryDeclinedPaymentCancelation_canceledOrder()
    {
        $this->orderMock->expects(static::exactly(2))->method('getState')->willReturn(Order::STATE_CANCELED);
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn($this->orderMock);
        $this->currentMock->expects(static::never())->method('cancelOrder')->with($this->orderMock);
        $this->orderMock->expects(static::never())->method('save');

        self::assertTrue($this->currentMock->tryDeclinedPaymentCancelation(self::DISPLAY_ID));
    }

    /**
     * @test
     *
     * @covers ::tryDeclinedPaymentCancelation
     *
     * @throws BoltException
     */
    public function tryDeclinedPaymentCancelation_completeOrder()
    {
        $this->orderMock->expects(static::exactly(2))->method('getState')->willReturn(Order::STATE_COMPLETE);
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn($this->orderMock);
        $this->currentMock->expects(static::never())->method('cancelOrder')->with($this->orderMock);
        $this->orderMock->expects(static::never())->method('save');

        self::assertFalse($this->currentMock->tryDeclinedPaymentCancelation(self::DISPLAY_ID));
    }

    /**
     * @test
     *
     * @covers ::deleteOrderByIncrementId
     *
     * @throws Exception
     */
    public function deleteOrderByIncrementId_noOrder()
    {
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn(null);
        $this->bugsnag->expects(self::once())->method('notifyError');
        $this->orderMock->expects(static::never())->method('getState');
        $this->currentMock->expects(static::never())->method('deleteOrder');
        $this->currentMock->deleteOrderByIncrementId(self::DISPLAY_ID);
    }

    /**
     * @test
     *
     * @covers ::deleteOrderByIncrementId
     *
     * @throws Exception
     */
    public function deleteOrderByIncrementId_invalidState()
    {
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn($this->orderMock);
        $state = Order::STATE_NEW;
        $this->orderMock->expects(static::once())->method('getState')->willReturn($state);
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(CreateOrder::E_BOLT_GENERAL_ERROR);
        $this->expectExceptionMessage(
            sprintf(
                'Order Delete Error. Order is in invalid state. Order #: %s State: %s Immutable Quote ID: %s',
                self::INCREMENT_ID,
                $state,
                self::IMMUTABLE_QUOTE_ID
            )
        );
        $this->currentMock->expects(static::never())->method('deleteOrder');
        $this->currentMock->deleteOrderByIncrementId(self::DISPLAY_ID);
    }

    /**
     * @test
     *
     * @covers ::deleteOrderByIncrementId
     *
     * @throws Exception
     */
    public function deleteOrderByIncrementId_noError()
    {
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn($this->orderMock);
        $state = Order::STATE_PENDING_PAYMENT;
        $this->orderMock->expects(static::once())->method('getState')->willReturn($state);
        $this->currentMock->expects(static::once())->method('deleteOrder')->with($this->orderMock);
        $this->currentMock->deleteOrderByIncrementId(self::DISPLAY_ID);
    }

    /**
     * @test
     *
     * @covers ::getExistingOrder
     *
     * @throws ReflectionException
     */
    public function getExistingOrder()
    {
        $this->cartHelper->expects(self::once())->method('getOrderByIncrementId')->with(self::INCREMENT_ID, true)
            ->willReturn($this->orderMock);
        static::assertSame(
            $this->orderMock,
            TestHelper::invokeMethod($this->currentMock, 'getExistingOrder', [self::INCREMENT_ID])
        );
    }

    /**
     * @test
     *
     * @covers ::quoteAfterChange
     *
     * @throws ReflectionException
     */
    public function quoteAfterChange()
    {
        $time = date('Y-m-d H:i:s');

        $this->date->expects(self::once())->method('gmtDate')->willReturn($time);
        $this->quoteMock->expects(self::once())->method('setUpdatedAt')->with($time);
        $this->eventManager->expects(self::once())->method('dispatch')->with(
            'sales_quote_save_after',
            [
                'quote' => $this->quoteMock
            ]
        );
        TestHelper::invokeMethod($this->currentMock, 'quoteAfterChange', [$this->quoteMock]);
    }

    public function trueAndFalseDataProvider()
    {
        return [[true],[false]];
    }

    /**
     * @test
     * @dataProvider trueAndFalseDataProvider
     *
     * @covers ::prepareQuote
     * @covers ::setQuotePaymentInfoData
     * @covers ::getQuotePaymentInfoInstance
     * @covers ::addCustomerDetails
     * @covers ::setPaymentMethod
     *
     * @throws LocalizedException
     * @throws ReflectionException
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     * @throws SessionException
     * @throws Zend_Validate_Exception
     */
    public function prepareQuote($isProductPage)
    {
        $this->initCurrentMock(
            [
                'setQuotePaymentInfoData',
                'addCustomerDetails',
                'quoteAfterChange',
                'setShippingAddress',
                'setBillingAddress',
                'setShippingMethod',
            ]
        );
        $transaction = json_decode(
            json_encode(
                [
                    'from_credit_card' => [
                        'last4'   => 1111,
                        'network' => 'visa'
                    ],
                    'order'            => [
                        'cart' => [
                            'billing_address' => [
                                'email_address' => 'test@bolt.com'
                            ]
                        ]
                    ]
                ]
            )
        );
        $quoteMockMethodsList = ['getBoltParentQuoteId', 'getPayment', 'setPaymentMethod', 'getReservedOrderId', 'getId'];
        /** @var MockObject|Quote $immutableQuote */
        $immutableQuote = $this->createPartialMock(Quote::class, $quoteMockMethodsList);
        $immutableQuote->expects(self::any())->method('getId')->willReturn(self::IMMUTABLE_QUOTE_ID);

        if ($isProductPage) {
            // immutableQuote the same object as parentQuote
            $immutableQuote->expects(self::once())->method('getBoltParentQuoteId')->willReturn(null);
            /** @var MockObject|Quote $parentQuote */
            $parentQuote = $immutableQuote;
            $bugsnagParentQuoteId = self::IMMUTABLE_QUOTE_ID;
        } else {
            // we have two Quotes: parent and immutable
            $immutableQuote->expects(self::once())->method('getBoltParentQuoteId')->willReturn(self::PARENT_QUOTE_ID);
            $parentQuote = $this->createPartialMock(Quote::class, $quoteMockMethodsList);
            $parentQuote->expects(self::once())->method('getId')->willReturn(self::PARENT_QUOTE_ID);
            $bugsnagParentQuoteId = self::PARENT_QUOTE_ID;

            $this->cartHelper->expects(self::once())->method('getQuoteById')->with(self::PARENT_QUOTE_ID)
                ->willReturn($parentQuote);
            $this->cartHelper->expects(static::once())->method('replicateQuoteData')
                ->willReturn($immutableQuote, $parentQuote);
        }

        $this->currentMock->expects(self::exactly(4))->method('quoteAfterChange')->with($parentQuote);

        $this->sessionHelper->expects(static::once())->method('loadSession')->with($parentQuote);
        $this->currentMock->expects(self::once())->method('setShippingAddress')->with($parentQuote, $transaction);
        $this->currentMock->expects(self::once())->method('setBillingAddress')->with($parentQuote, $transaction);
        $this->currentMock->expects(self::once())->method('setShippingMethod')->with($parentQuote);

        $this->discountHelper->expects(self::once())->method('applyMageplazaDiscountToQuote')->with($parentQuote);

        $parentQuote->expects(self::once())->method('setPaymentMethod')->with(Payment::METHOD_CODE);

        $quotePayment = $this->createMock(Quote\Payment::class);
        $methodInstance = $this->createMock(Payment::class);
        $infoInstance = $this->createPartialMock(
            Info::class,
            ['setData', 'save']
        );

        $methodInstance->expects(self::atLeastOnce())->method('getInfoInstance')->willReturn($infoInstance);
        $quotePayment->expects(self::atLeastOnce())->method('getMethodInstance')->willReturn($methodInstance);

        $parentQuote->expects(self::atLeastOnce())->method('getPayment')->willReturn($quotePayment);

        $quotePayment->expects(self::once())->method('importData')->with(['method' => Payment::METHOD_CODE])
            ->willReturnSelf();

        $infoInstance->expects(self::exactly(2))->method('setData')
            ->withConsecutive(['cc_last_4', 1111], ['cc_type', 'visa'])->willReturnSelf();
        $infoInstance->expects(self::exactly(2))->method('save');

        $quotePayment->expects(self::once())->method('save');

        $parentQuote->expects(self::once())->method('getReservedOrderId')->willReturn(self::ORDER_ID);

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function ($callback) use ($bugsnagParentQuoteId) {
                $report = $this->createMock(Report::class);
                $report->expects(self::once())->method('setMetaData')->with(
                    [
                        'CREATE ORDER' => [
                            'order increment ID' => self::ORDER_ID,
                            'parent quote ID'    => $bugsnagParentQuoteId,
                            'immutable quote ID' => self::IMMUTABLE_QUOTE_ID
                        ]
                    ]
                );
                $callback($report);
            }
        );

        static::assertSame($parentQuote, $this->currentMock->prepareQuote($immutableQuote, $transaction));
    }

    /**
     * @test
     *
     * @covers ::setOrderUserNote
     */
    public function setOrderUserNote()
    {
        $userNote = 'Test note';
        $this->orderMock->expects(self::once())->method('addStatusHistoryComment')->with($userNote)->willReturnSelf();
        $this->orderMock->expects(self::once())->method('setIsVisibleOnFront')->with(true)->willReturnSelf();
        $this->orderMock->expects(self::once())->method('setIsCustomerNotified')->with(false)->willReturnSelf();
        $this->currentMock->setOrderUserNote($this->orderMock, $userNote);
    }

    /**
     * @test
     *
     * @covers ::formatReferenceUrl
     */
    public function formatReferenceUrl()
    {
        $this->configHelper->expects(self::once())->method('getMerchantDashboardUrl')
            ->willReturn('https://merchant-sandbox.bolt.com');
        static::assertEquals(
            sprintf(
                '<a href="https://merchant-sandbox.bolt.com/transaction/%1$s">%1$s</a>',
                self::REFERENCE_ID
            ),
            $this->currentMock->formatReferenceUrl(self::REFERENCE_ID)
        );
    }

    /**
     * @test
     *
     * @covers ::getProcessedItems
     *
     * @dataProvider getProcessedItems_withVariousItemTypes_returnsFromPaymentAdditionalInformationProvider
     *
     * @param string $itemType
     * @param string $itemTypeAdditionalInformation
     * @param array  $expectedResult
     *
     * @throws ReflectionException
     */
    public function getProcessedItems_withVariousItemTypes_returnsFromPaymentAdditionalInformation(
        $itemType, $itemTypeAdditionalInformation, $expectedResult
    ) {
        $paymentMock = $this->createMock(OrderPaymentInterface::class);
        $paymentMock->expects(self::once())->method('getAdditionalInformation')->with($itemType)->willReturn(
            $itemTypeAdditionalInformation
        );
        static::assertEquals(
            $expectedResult,
            TestHelper::invokeMethod($this->currentMock, 'getProcessedItems', [$paymentMock, $itemType])
        );
    }

    public function getProcessedItems_withVariousItemTypes_returnsFromPaymentAdditionalInformationProvider()
    {
        return [
            'Empty value'                   => [
                'itemType'                      => 'captures',
                'itemTypeAdditionalInformation' => '',
                'expectedResult'                => []
            ],
            'One value with trailing comma' => [
                'itemType'                      => 'captures',
                'itemTypeAdditionalInformation' => 'test,',
                'expectedResult'                => ['test']
            ],
            'Multiple values'               => [
                'itemType'                      => 'refunds',
                'itemTypeAdditionalInformation' => 'test1,test2',
                'expectedResult'                => ['test1', 'test2']
            ],
            'Only comma'                    => [
                'itemType'                      => 'refunds',
                'itemTypeAdditionalInformation' => ',',
                'expectedResult'                => []
            ],
        ];
    }

    /**
     * @test
     *
     * @covers ::getProcessedRefunds
     *
     * @throws ReflectionException
     */
    public function getProcessedRefunds()
    {
        $paymentMock = $this->createMock(OrderPayment::class);
        $paymentMock->expects(self::once())->method('getAdditionalInformation')->with('refunds')
            ->willReturn('refund1,refund2');
        static::assertEquals(
            [
                'refund1',
                'refund2',
            ],
            TestHelper::invokeMethod(
                $this->currentMock,
                'getProcessedRefunds',
                [$paymentMock]
            )
        );
    }

    /**
     * @test
     */
    public function getTransactionState_CreditCardAuthorize()
    {
        $this->paymentMock = $this->getMockBuilder(InfoInterface::class)->setMethods(['getId', 'getOrder'])
            ->getMockForAbstractClass();
        $map = [
            ['transaction_state', OrderHelper::TS_PENDING],
            ['transaction_reference', '000123'],
            ['real_transaction_id', self::TRANSACTION_ID],
            ['captures', ""],
        ];
        $this->paymentMock->expects(static::exactly(4))
            ->method('getAdditionalInformation')
            ->will(static::returnValueMap($map));

        $this->transactionMock = (object)([
            'type'     => OrderHelper::TT_PAYMENT,
            'status'   => "authorized",
            'captures' => [],
        ]);
        $state = $this->currentMock->getTransactionState($this->transactionMock, $this->paymentMock, null);
        static::assertEquals($state, OrderHelper::TS_AUTHORIZED);
    }

    /**
     * @test
     */
    public function getTransactionState_PaypalCompleted()
    {
        $this->paymentMock = $this->getMockBuilder(InfoInterface::class)->setMethods(['getId', 'getOrder'])
            ->getMockForAbstractClass();
        $map = [
            ['transaction_state', OrderHelper::TS_PENDING],
            ['transaction_reference', '000123'],
            ['real_transaction_id', self::TRANSACTION_ID],
            ['captures', ""],
        ];
        $this->paymentMock->expects(static::exactly(4))
            ->method('getAdditionalInformation')
            ->will(static::returnValueMap($map));

        $this->transactionMock = (object)([
            'type'     => OrderHelper::TT_PAYPAL_PAYMENT,
            'status'   => "completed",
            'captures' => [],
        ]);
        $state = $this->currentMock->getTransactionState($this->transactionMock, $this->paymentMock, null);
        static::assertEquals($state, "cc_payment:completed");
    }

    /**
     * @test
     */
    public function getTransactionState_APMInitialAuthorized()
    {
        list($paymentMock, $transaction) = $this->getTransactionStateSetUp(
            null, OrderHelper::TT_APM_PAYMENT, 'authorized', []
        );
        static::assertEquals(
            $this->currentMock->getTransactionState($transaction, $paymentMock, null),
            OrderHelper::TS_PENDING
        );
    }

    /**
     * Setup method for {@see getTransactionState}
     *
     * @param string $prevTransactionState
     * @param string $transactionType
     * @param string $transactionStatus
     * @param array  $captures
     *
     * @return array
     */
    private function getTransactionStateSetUp($prevTransactionState, $transactionType, $transactionStatus, $captures = [])
    {
        /** @var MockObject|OrderPaymentInterface $paymentMock */
        $paymentMock = $this->getMockBuilder(InfoInterface::class)->setMethods(['getId', 'getOrder'])
            ->getMockForAbstractClass();
        $paymentMock->expects(static::exactly(4))
            ->method('getAdditionalInformation')
            ->willReturnMap(
                [
                    ['transaction_state', $prevTransactionState],
                    ['transaction_reference', null],
                    ['real_transaction_id', null],
                    ['captures', ""],
                ]
            );

        $transaction = (object)([
            'type'     => $transactionType,
            'status'   => $transactionStatus,
            'captures' => $captures,
        ]);
        return [$paymentMock, $transaction];
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     */
    public function getTransactionState_TSCreditCompleted()
    {
        list($paymentMock, $transaction) = $this->getTransactionStateSetUp(
            null,
            OrderHelper::TT_PAYPAL_REFUND,
            "completed"
        );
        static::assertEquals(
            OrderHelper::TS_CREDIT_COMPLETED,
            $this->currentMock->getTransactionState($transaction, $paymentMock)
        );
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     */
    public function getTransactionState_TSAuthorized()
    {
        list($paymentMock, $transaction) = $this->getTransactionStateSetUp(
            OrderHelper::TS_PENDING,
            OrderHelper::TT_PAYMENT,
            'authorized',
            true
        );
        static::assertEquals(
            OrderHelper::TS_AUTHORIZED,
            $this->currentMock->getTransactionState($transaction, $paymentMock)
        );
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     */
    public function getTransactionState_TSAuthorizedFromPending()
    {
        list($paymentMock, $transaction) = $this->getTransactionStateSetUp(
            OrderHelper::TS_PENDING,
            OrderHelper::TT_PAYMENT,
            'completed',
            [1, 2]
        );
        static::assertEquals(
            OrderHelper::TS_AUTHORIZED,
            $this->currentMock->getTransactionState($transaction, $paymentMock)
        );
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     */
    public function getTransactionState_TSCaptured()
    {
        list($paymentMock, $transaction) = $this->getTransactionStateSetUp(
            OrderHelper::TS_AUTHORIZED,
            OrderHelper::TT_PAYMENT,
            'completed',
            [1, 2]
        );
        static::assertEquals(
            OrderHelper::TS_CAPTURED,
            $this->currentMock->getTransactionState($transaction, $paymentMock)
        );
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     */
    public function getTransactionState_TSCapturedFromAuthorized()
    {
        list($paymentMock, $transaction) = $this->getTransactionStateSetUp(
            OrderHelper::TS_AUTHORIZED,
            OrderHelper::TT_PAYMENT,
            'authorized',
            [1]
        );
        static::assertEquals(
            OrderHelper::TS_CAPTURED,
            $this->currentMock->getTransactionState($transaction, $paymentMock)
        );
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     */
    public function getTransactionState_TSCapturedFromACompleted()
    {
        list($paymentMock, $transaction) = $this->getTransactionStateSetUp(
            OrderHelper::TS_CREDIT_COMPLETED,
            OrderHelper::TT_PAYMENT,
            'authorized',
            []
        );
        static::assertEquals(
            OrderHelper::TS_CAPTURED,
            $this->currentMock->getTransactionState($transaction, $paymentMock)
        );
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     */
    public function getTransactionState_TSPartialVoided()
    {
        list($paymentMock, $transaction) = $this->getTransactionStateSetUp(
            OrderHelper::TS_CAPTURED,
            OrderHelper::TT_PAYMENT,
            'completed',
            [1]
        );
        static::assertEquals(
            OrderHelper::TS_PARTIAL_VOIDED,
            $this->currentMock->getTransactionState($transaction, $paymentMock, Transaction::TYPE_VOID)
        );
    }

    /**
     * @test
     *
     * @covers ::holdOnTotalsMismatch
     *
     * @throws ReflectionException
     */
    public function holdOnTotalsMismatch_withTotalsMismatch_throwsLocalizedException()
    {
        $this->initCurrentMock(['setOrderState']);
        $transaction = json_decode(
            json_encode(
                [
                    'order'     => [
                        'cart' => [
                            'total_amount'    => [
                                'amount' => 1000
                            ],
                            'order_reference' => self::ORDER_ID,
                            'display_id'      => self::INCREMENT_ID
                        ]
                    ],
                    'reference' => self::REFERENCE_ID
                ]
            )
        );

        $this->orderMock->expects(self::atLeastOnce())->method('getGrandTotal')->willReturn(50);
        $this->orderMock->expects(self::once())->method('addStatusHistoryComment')
            ->with(
                __(
                    'BOLTPAY INFO :: THERE IS A MISMATCH IN THE ORDER PAID AND ORDER RECORDED.<br>
             Paid amount: %1 Recorded amount: %2<br>Bolt transaction: %3',
                    10.0,
                    50,
                    sprintf('<a href="/transaction/%1$s">%1$s</a>', self::REFERENCE_ID)
                )
            );

        $this->currentMock->expects(self::once())->method('setOrderState')->with($this->orderMock, Order::STATE_HOLDED);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Order Totals Mismatch Reference: %s Order: %s Bolt Total: %s Store Total: %s',
                self::REFERENCE_ID,
                self::INCREMENT_ID,
                1000,
                5000
            )
        );

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function ($callback) use ($transaction) {
                $report = $this->createMock(Report::class);
                $report->expects(self::once())->method('setMetaData')->with(
                    [
                        'TOTALS_MISMATCH' => [
                            'Reference'   => self::REFERENCE_ID,
                            'Order ID'    => (string)self::INCREMENT_ID,
                            'Bolt Total'  => 1000,
                            'Store Total' => 5000,
                            'Bolt Cart'   => $transaction->order->cart,
                            'Store Cart'  => ['The quote does not exist.']
                        ]
                    ]
                );
                $callback($report);
            }
        );

        TestHelper::invokeMethod(
            $this->currentMock,
            'holdOnTotalsMismatch',
            [$this->orderMock, $transaction]
        );
    }

    /**
     * @test
     *
     * @covers ::holdOnTotalsMismatch
     *
     * @throws ReflectionException
     */
    public function holdOnTotalsMismatch_cartFromQuoteId()
    {
        $this->initCurrentMock(['setOrderState']);
        $transaction = json_decode(
            json_encode(
                [
                    'order'     => [
                        'cart' => [
                            'total_amount'    => [
                                'amount' => 1000
                            ],
                            'order_reference' => self::PARENT_QUOTE_ID,
                            'display_id'      => self::INCREMENT_ID
                        ]
                    ],
                    'reference' => self::REFERENCE_ID
                ]
            )
        );

        $cartData = [
            'total_amount' => 500
        ];

        $this->cartHelper->expects(self::once())->method('getQuoteById')->with(self::PARENT_QUOTE_ID)
            ->willReturn($this->quoteMock);
        $this->cartHelper->expects(self::once())->method('getCartData')->with(
            true,
            false,
            $this->quoteMock
        )->willReturn($cartData);

        $this->orderMock->expects(self::atLeastOnce())->method('getGrandTotal')->willReturn(50);
        $this->orderMock->expects(self::once())->method('addStatusHistoryComment')
            ->with(
                __(
                    'BOLTPAY INFO :: THERE IS A MISMATCH IN THE ORDER PAID AND ORDER RECORDED.<br>
             Paid amount: %1 Recorded amount: %2<br>Bolt transaction: %3',
                    10.0,
                    50,
                    sprintf('<a href="/transaction/%1$s">%1$s</a>', self::REFERENCE_ID)
                )
            );

        $this->currentMock->expects(self::once())->method('setOrderState')->with($this->orderMock, Order::STATE_HOLDED);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Order Totals Mismatch Reference: %s Order: %s Bolt Total: %s Store Total: %s',
                self::REFERENCE_ID,
                self::INCREMENT_ID,
                1000,
                5000
            )
        );

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function ($callback) use ($cartData, $transaction) {
                $report = $this->createMock(Report::class);
                $report->expects(self::once())->method('setMetaData')->with(
                    [
                        'TOTALS_MISMATCH' => [
                            'Reference'   => self::REFERENCE_ID,
                            'Order ID'    => (string)self::INCREMENT_ID,
                            'Bolt Total'  => 1000,
                            'Store Total' => 5000,
                            'Bolt Cart'   => $transaction->order->cart,
                            'Store Cart'  => $cartData
                        ]
                    ]
                );
                $callback($report);
            }
        );

        TestHelper::invokeMethod(
            $this->currentMock,
            'holdOnTotalsMismatch',
            [$this->orderMock, $transaction]
        );
    }

    /**
     * @test
     *
     * @covers ::getBoltTransactionStatus
     *
     * @dataProvider getBoltTransactionStatus_withVariousStates_returnsExpectedStatusProvider
     *
     * @param string $transactionState
     * @param string $orderState
     * @param bool   $isHookFromBolt
     *
     * @throws ReflectionException
     */
    public function getBoltTransactionStatus_withVariousStates_returnsExpectedStatus(
        $transactionState,
        $orderState,
        $isHookFromBolt = false
    ) {
        Hook::$fromBolt = $isHookFromBolt;
        static::assertEquals(
            $orderState,
            TestHelper::invokeMethod($this->currentMock, 'getBoltTransactionStatus', [$transactionState])
        );
    }

    /**
     * Data provider for {@see getBoltTransactionStatus_withVariousStates_returnsExpectedStatus}
     */
    public function getBoltTransactionStatus_withVariousStates_returnsExpectedStatusProvider()
    {
        return [
            [OrderHelper::TS_ZERO_AMOUNT, 'ZERO AMOUNT COMPLETED'],
            [OrderHelper::TS_PENDING, 'UNDER REVIEW'],
            [OrderHelper::TS_AUTHORIZED, 'AUTHORIZED'],
            [OrderHelper::TS_CAPTURED, 'CAPTURED'],
            [OrderHelper::TS_COMPLETED, 'COMPLETED'],
            [OrderHelper::TS_CANCELED, 'CANCELED'],
            [OrderHelper::TS_REJECTED_REVERSIBLE, 'REVERSIBLE REJECTED'],
            [OrderHelper::TS_REJECTED_IRREVERSIBLE, 'IRREVERSIBLE REJECTED'],
            [OrderHelper::TS_CREDIT_COMPLETED, 'REFUNDED UNSYNCHRONISED', true],
            [OrderHelper::TS_CREDIT_COMPLETED, 'REFUNDED', false],
        ];
    }

    /**
     * @test
     *
     * @covers ::formatTransactionData
     *
     * @throws ReflectionException
     */
    public function formatTransactionData()
    {
        $this->initCurrentMock(['formatAmountForDisplay']);
        $this->currentMock
            ->expects(self::once())
            ->method('formatAmountForDisplay')
            ->with($this->orderMock, 10)
            ->willReturn('$10');
        $this->timezone
            ->expects(self::once())
            ->method('formatDateTime')
            ->willReturn('1970-01-01 00:00:00');
        $timestamp = microtime(true);
        $transaction = (object)[
            'date'      => $timestamp * 1000,
            'reference' => self::REFERENCE_ID,
            'id'        => self::TRANSACTION_ID
        ];
        static::assertEquals(
            [
                'Time'           => '1970-01-01 00:00:00',
                'Reference'      => '1123123123',
                'Amount'         => '$10',
                'Transaction ID' => 'ABCD-1234-XXXX',
            ],
            TestHelper::invokeMethod(
                $this->currentMock,
                'formatTransactionData',
                [$this->orderMock, $transaction, 1000]
            )
        );
    }

    /**
     * @test
     *
     * @covers ::setOrderState
     */
    public function setOrderState_holdedOrder()
    {
        $prevState = Order::STATE_PENDING_PAYMENT;
        $this->orderMock->expects(static::once())->method('getState')->willReturn($prevState);
        $this->orderMock->expects(static::once())->method('hold');
        $this->orderMock->expects(static::once())->method('setState')->with(Order::STATE_PROCESSING);
        $this->orderConfigMock->expects(static::once())->method('getStateDefaultStatus')
            ->with(Order::STATE_PROCESSING)->willReturn(Order::STATE_PROCESSING);
        $this->orderMock->expects(static::once())->method('setStatus')->with(Order::STATE_PROCESSING);
        $this->orderMock->expects(static::once())->method('save');
        $this->currentMock->setOrderState($this->orderMock, Order::STATE_HOLDED);
    }

    /**
     * @test
     *
     * @covers ::setOrderState
     */
    public function setOrderState_holdedOrderWithException()
    {
        $prevState = Order::STATE_PENDING_PAYMENT;
        $this->orderMock->expects(static::once())->method('getState')->willReturn($prevState);
        $this->orderMock->expects(static::once())->method('hold')->willThrowException(new Exception());
        $this->orderMock->expects(static::exactly(2))->method('setState')
            ->withConsecutive([Order::STATE_PROCESSING], [Order::STATE_HOLDED]);
        $this->orderConfigMock->expects(static::exactly(2))->method('getStateDefaultStatus')
            ->withConsecutive([Order::STATE_PROCESSING], [Order::STATE_HOLDED])
            ->willReturnArgument(0);
        $this->orderMock->expects(static::exactly(2))->method('setStatus')
            ->withConsecutive([Order::STATE_PROCESSING], [Order::STATE_HOLDED]);
        $this->orderMock->expects(static::once())->method('save');
        $this->currentMock->setOrderState($this->orderMock, Order::STATE_HOLDED);
    }

    /**
     * @test
     *
     * @covers ::setOrderState
     */
    public function setOrderState_canceledOrder()
    {
        $state = Order::STATE_CANCELED;
        $this->orderMock->expects(static::once())->method('getState')->willReturn($state);
        $this->orderMock->expects(static::once())->method('canCancel')->willReturn(true);
        $this->currentMock->expects(static::once())->method('cancelOrder')->with($this->orderMock);
        $this->orderMock->expects(static::never())->method('hold');
        $this->orderMock->expects(static::never())->method('setState');
        $this->orderMock->expects(static::never())->method('setStatus');
        $this->orderMock->expects(static::never())->method('save');
        $this->currentMock->setOrderState($this->orderMock, $state);
    }

    /**
     * @test
     *
     * @covers ::setOrderState
     */
    public function setOrderState_canceledOrderForRejectedIrreversibleHook()
    {
        $prevState = Order::STATE_PAYMENT_REVIEW;
        $this->orderMock->expects(static::once())->method('getState')->willReturn($prevState);
        $this->orderMock->expects(static::once())->method('canCancel')->willReturn(false);
        $this->currentMock->expects(static::never())->method('cancelOrder')->with($this->orderMock);
        $this->orderMock->expects(static::once())->method('registerCancellation')->willReturn($this->orderMock);
        $this->orderMock->expects(static::never())->method('setState');
        $this->orderMock->expects(static::never())->method('setStatus');
        $this->orderMock->expects(static::once())->method('save');
        $this->currentMock->setOrderState($this->orderMock, Order::STATE_CANCELED);
    }

    /**
     * @test
     *
     * @covers ::setOrderState
     */
    public function setOrderState_canceledOrderForRejectedIrreversibleHookWithException()
    {
        $prevState = Order::STATE_PAYMENT_REVIEW;
        $this->orderMock->expects(static::once())->method('getState')->willReturn($prevState);
        $this->orderMock->expects(static::once())->method('canCancel')->willReturn(false);
        $this->currentMock->expects(static::never())->method('cancelOrder')->with($this->orderMock);
        $this->orderMock->expects(static::once())->method('registerCancellation')->willThrowException(new Exception());
        $this->orderMock->expects(static::once())->method('setState');
        $this->orderMock->expects(static::once())->method('setStatus');
        $this->orderMock->expects(static::once())->method('save');
        $this->currentMock->setOrderState($this->orderMock, Order::STATE_CANCELED);
    }

    /**
     * @test
     *
     * @covers ::setOrderState
     */
    public function setOrderState_nonSpecialStateOrder()
    {
        $state = Order::STATE_PAYMENT_REVIEW;
        $this->orderConfigMock->expects(static::once())->method('getStateDefaultStatus')
            ->with($state)->willReturn($state);
        $this->orderMock->expects(static::never())->method('hold');
        $this->currentMock->expects(static::never())->method('cancelOrder');
        $this->orderMock->expects(static::once())->method('getState')->willReturn($state);
        $this->orderMock->expects(static::once())->method('setState')->with($state);
        $this->orderMock->expects(static::once())->method('setStatus')->with($state);
        $this->orderMock->expects(static::once())->method('save');
        $this->currentMock->setOrderState($this->orderMock, $state);
    }

    /**
     * @test
     *
     * @covers ::cancelOrder
     *
     * @throws ReflectionException
     */
    public function cancelOrder()
    {
        $this->orderMock->expects(self::once())->method('cancel');
        $this->orderMock->expects(self::once())->method('setState')->with(Order::STATE_CANCELED);
        $this->orderConfigMock->expects(self::once())->method('getStateDefaultStatus')->with(Order::STATE_CANCELED)
            ->willReturn(OrderModel::STATE_CANCELED);
        $this->orderMock->expects(self::once())->method('setState')->with(Order::STATE_CANCELED);
        $this->orderMock->expects(self::once())->method('save');
        TestHelper::invokeMethod($this->currentMock, 'cancelOrder', [$this->orderMock]);
    }

    /**
     * @test
     *
     * @covers ::cancelOrder
     *
     * @throws ReflectionException
     */
    public function cancelOrder_cancelThrowsException_notifiesExceptionAndSetsStatusAndState()
    {
        $exception = new Exception('');
        $this->orderMock->expects(self::once())->method('cancel')->willThrowException($exception);
        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->orderMock->expects(self::once())->method('setState')->with(Order::STATE_CANCELED);
        $this->orderConfigMock->expects(self::once())->method('getStateDefaultStatus')->with(Order::STATE_CANCELED)
            ->willReturn(OrderModel::STATE_CANCELED);
        $this->orderMock->expects(self::once())->method('setState')->with(Order::STATE_CANCELED);
        $this->orderMock->expects(self::once())->method('save');
        TestHelper::invokeMethod($this->currentMock, 'cancelOrder', [$this->orderMock]);
    }

    /**
     * @test
     *
     * @covers ::checkPaymentMethod
     *
     * @throws ReflectionException
     */
    public function checkPaymentMethod()
    {
        $paymentMock = $this->createMock(OrderPayment::class);
        $paymentMock->expects(self::once())->method('getMethod')->willReturn(Payment::METHOD_CODE);
        TestHelper::invokeMethod($this->currentMock, 'checkPaymentMethod', [$paymentMock]);
    }

    /**
     * @test
     *
     * @covers ::checkPaymentMethod
     *
     * @throws ReflectionException
     */
    public function checkPaymentMethod_notBolt_throwsException()
    {
        $paymentMock = $this->createMock(OrderPayment::class);
        $paymentMock->expects(self::once())->method('getMethod')->willReturn('checkmo');
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Payment method assigned to order is: checkmo');
        TestHelper::invokeMethod($this->currentMock, 'checkPaymentMethod', [$paymentMock]);
    }

    /**
     * @test
     *
     * @covers ::transactionToOrderState
     *
     * @dataProvider transactionToOrderStateProvider
     *
     * @param string $transactionState
     * @param string $orderState
     * @param bool   $isHookFromBolt
     */
    public function transactionToOrderState($transactionState, $orderState, $isHookFromBolt = false)
    {
        Hook::$fromBolt = $isHookFromBolt;
        static::assertEquals($orderState, $this->currentMock->transactionToOrderState($transactionState));
    }

    /**
     * Data provider for @see transactionToOrderState
     */
    public function transactionToOrderStateProvider()
    {
        return [
            [OrderHelper::TS_ZERO_AMOUNT, OrderModel::STATE_PROCESSING],
            [OrderHelper::TS_PENDING, OrderModel::STATE_PAYMENT_REVIEW],
            [OrderHelper::TS_AUTHORIZED, OrderModel::STATE_PROCESSING],
            [OrderHelper::TS_CAPTURED, OrderModel::STATE_PROCESSING],
            [OrderHelper::TS_COMPLETED, OrderModel::STATE_PROCESSING],
            [OrderHelper::TS_CANCELED, OrderModel::STATE_CANCELED],
            [OrderHelper::TS_REJECTED_REVERSIBLE, OrderModel::STATE_PAYMENT_REVIEW],
            [OrderHelper::TS_REJECTED_IRREVERSIBLE, OrderModel::STATE_CANCELED],
            [OrderHelper::TS_CREDIT_COMPLETED, OrderModel::STATE_HOLDED, true],
            [OrderHelper::TS_CREDIT_COMPLETED, OrderModel::STATE_PROCESSING, false],
        ];
    }

    /**
     * @param $transactionState
     *
     * @return array
     *
     * @throws ReflectionException
     */
    private function updateOrderPaymentSetUp($transactionState)
    {
        $this->initCurrentMock(
            [
                'getTransactionState',
                'isAnAllowedUpdateFromAdminPanel',
                'formatAmountForDisplay',
                'fetchTransactionInfo',
                'getVoidMessage',
                'isCaptureHookRequest',
                'validateCaptureAmount',
                'createOrderInvoice',
            ]
        );
        $transaction = json_decode(
            json_encode(
                [
                    'id'        => self::TRANSACTION_ID,
                    'reference' => self::REFERENCE_ID,
                    'amount'    => [
                        'amount' => 1
                    ],
                    'date'      => microtime(true) * 1000,
                    'captures'  => [
                        [
                            'id'     => 123123123,
                            'status' => 'succeeded',
                            'amount' => [
                                'amount' => 10
                            ]
                        ]
                    ]
                ]
            )
        );
        $paymentMock = $this->createPartialMock(
            OrderPayment::class,
            [
                'getMethod',
                'setIsTransactionApproved',
                'getAdditionalInformation',
                'getAuthorizationTransaction',
                'closeAuthorization',
                'addTransactionCommentsToOrder',
                'save',
            ]
        );
        $paymentMock->expects(self::once())->method('getMethod')->willReturn(Payment::METHOD_CODE);
        $this->currentMock->expects(self::once())->method('getTransactionState')
            ->with($transaction, $paymentMock, static::anything())->willReturn($transactionState);
        $this->currentMock->method('formatAmountForDisplay')->willReturnArgument(1);
        $this->orderMock->expects(self::once())->method('getPayment')->willReturn($paymentMock);
        return [$transaction, $paymentMock];
    }

    /**
     * @test
     *
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_sameState()
    {
        $transactionState = OrderHelper::TS_AUTHORIZED;
        $prevTransactionState = OrderHelper::TS_AUTHORIZED;
        list($transaction, $paymentMock) = $this->updateOrderPaymentSetUp($transactionState);
        $prevTransactionReference = self::REFERENCE_ID;

        $this->currentMock->expects(self::once())->method('isAnAllowedUpdateFromAdminPanel')
            ->with($this->orderMock, $transactionState)->willReturn(true);
        $paymentMock->expects(self::once())->method('setIsTransactionApproved')->with(true);
        $paymentMock->expects(self::atLeastOnce())->method('getAdditionalInformation')
            ->withConsecutive(
                ['transaction_state'],
                ['transaction_reference'],
                ['real_transaction_id'],
                ['authorized'],
                ['captures']
            )
            ->willReturnOnConsecutiveCalls(
                $prevTransactionState,
                $prevTransactionReference,
                self::TRANSACTION_ID,
                true,
                ''
            );

        $this->currentMock->updateOrderPayment($this->orderMock, $transaction);
    }

    /**
     * @test
     *
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_rejectedIrreversible()
    {
        list($transaction, $paymentMock) =
            $this->updateOrderPaymentSetUp(OrderHelper::TS_REJECTED_IRREVERSIBLE);
        $paymentMock->expects(self::atLeastOnce())->method('getAdditionalInformation')
            ->withConsecutive(['transaction_state'])
            ->willReturnOnConsecutiveCalls(OrderHelper::TS_REJECTED_IRREVERSIBLE);
        $this->currentMock->updateOrderPayment($this->orderMock, $transaction);
    }

    /**
     * @test
     *
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_withUnhandledState_throwsException()
    {
        list($transaction, $paymentMock) = $this->updateOrderPaymentSetUp('Unhandled state');
        $paymentMock->expects(self::atLeastOnce())->method('getAdditionalInformation')
            ->withConsecutive(['transaction_state'])
            ->willReturnOnConsecutiveCalls(OrderHelper::TS_AUTHORIZED);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unhandled transaction state : Unhandled state');
        $this->currentMock->updateOrderPayment($this->orderMock, $transaction);
    }

    /**
     * @test
     *
     * @covers ::updateOrderPayment
     * @covers ::getUnprocessedCapture
     *
     * @dataProvider updateOrderPayment_variousTxStatesProvider
     *
     * @param $transactionState
     * @param $transactionType
     * @param $transactionId
     *
     * @throws LocalizedException
     * @throws Zend_Http_Client_Exception
     * @throws ReflectionException
     */
    public function updateOrderPayment_variousTxStates($transactionState, $transactionType, $transactionId)
    {
        /**
         * @var MockObject|OrderPayment $paymentMock
         */
        list($transaction, $paymentMock) = $this->updateOrderPaymentSetUp($transactionState);
        $paymentMock->expects(self::atLeastOnce())->method('getAdditionalInformation')
            ->withConsecutive(['transaction_state'])
            ->willReturnOnConsecutiveCalls('');

        $this->transactionBuilder->expects(self::once())->method('setPayment')->with($paymentMock)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setOrder')->with($this->orderMock)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setTransactionId')->with($transactionId)
            ->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setAdditionalInformation')->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setFailSafe')->with(true)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('build')
            ->with($transactionType)->willThrowException(new Exception(''));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('');
        $this->currentMock->updateOrderPayment($this->orderMock, $transaction);
    }

    /**
     * @return array
     */
    public function updateOrderPayment_variousTxStatesProvider()
    {
        return [
            [
                'transactionState' => OrderHelper::TS_ZERO_AMOUNT,
                'transactionType'  => TransactionInterface::TYPE_ORDER,
                'transactionId'    => self::TRANSACTION_ID
            ],
            [
                'transactionState' => OrderHelper::TS_PENDING,
                'transactionType'  => TransactionInterface::TYPE_ORDER,
                'transactionId'    => self::TRANSACTION_ID
            ],
            [
                'transactionState' => OrderHelper::TS_AUTHORIZED,
                'transactionType'  => TransactionInterface::TYPE_AUTH,
                'transactionId'    => self::TRANSACTION_ID . '-auth'
            ],
            [
                'transactionState' => OrderHelper::TS_CAPTURED,
                'transactionType'  => TransactionInterface::TYPE_CAPTURE,
                'transactionId'    => self::TRANSACTION_ID . '-capture-' . 123123123
            ],
            [
                'transactionState' => OrderHelper::TS_COMPLETED,
                'transactionType'  => TransactionInterface::TYPE_CAPTURE,
                'transactionId'    => self::TRANSACTION_ID . '-payment'
            ],
            [
                'transactionState' => OrderHelper::TS_CANCELED,
                'transactionType'  => TransactionInterface::TYPE_VOID,
                'transactionId'    => self::TRANSACTION_ID . '-void'
            ],
            [
                'transactionState' => OrderHelper::TS_REJECTED_REVERSIBLE,
                'transactionType'  => TransactionInterface::TYPE_ORDER,
                'transactionId'    => self::TRANSACTION_ID . '-rejected_reversible'
            ],
            [
                'transactionState' => OrderHelper::TS_REJECTED_IRREVERSIBLE,
                'transactionType'  => TransactionInterface::TYPE_ORDER,
                'transactionId'    => self::TRANSACTION_ID . '-rejected_irreversible'
            ],
            [
                'transactionState' => OrderHelper::TS_CREDIT_COMPLETED,
                'transactionType'  => TransactionInterface::TYPE_REFUND,
                'transactionId'    => self::TRANSACTION_ID . '-refund'
            ],
        ];
    }

    /**
     * @test
     *
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_partialVoid()
    {
        /**
         * @var MockObject|OrderPayment $paymentMock
         */
        list($transaction, $paymentMock) = $this->updateOrderPaymentSetUp(OrderHelper::TS_PARTIAL_VOIDED);
        $paymentMock->expects(self::atLeastOnce())->method('getAdditionalInformation')
            ->withConsecutive(['transaction_state'])
            ->willReturnOnConsecutiveCalls('');
        $paymentMock->expects(self::once())->method('getAuthorizationTransaction')->willReturnSelf();
        $paymentMock->expects(self::once())->method('closeAuthorization')->willReturnSelf();
        $this->currentMock->expects(self::once())->method('getVoidMessage')->with($paymentMock)->willReturn('test');
        $this->orderMock->expects(self::once())->method('addCommentToStatusHistory')->with('test');
        $this->orderMock->expects(self::once())->method('save');
        $this->currentMock->updateOrderPayment($this->orderMock, $transaction);
    }

    /**
     * @test
     *
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_createInvoice()
    {
        /**
         * @var MockObject|OrderPayment $paymentMock
         */
        list($transaction, $paymentMock) = $this->updateOrderPaymentSetUp(OrderHelper::TS_COMPLETED);
        $invoiceMock = $this->createMock(Invoice::class);
        $paymentMock->expects(self::atLeastOnce())->method('getAdditionalInformation')
            ->willReturnMap([['authorized', true]]);
        $this->currentMock->expects(self::once())->method('fetchTransactionInfo')->willReturn($transaction);
        $this->currentMock->expects(self::once())->method('isCaptureHookRequest')->willReturn(true);

        $this->currentMock->expects(self::once())->method('createOrderInvoice')->willReturn($invoiceMock);
        $this->currentMock->expects(self::once())->method('createOrderInvoice')->willReturn($invoiceMock);

        $exception = new Exception('');
        $this->emailSender->expects(self::once())->method('send')->with($this->orderMock)
            ->willThrowException($exception);
        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);

        $this->transactionBuilder->expects(self::once())->method('setPayment')->with($paymentMock)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setOrder')->with($this->orderMock)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setTransactionId')
            ->with(self::TRANSACTION_ID . '-capture-' . 123123123)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setAdditionalInformation')->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setFailSafe')->with(true)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('build')->willReturn($paymentMock);

        $this->currentMock->updateOrderPayment($this->orderMock, null, self::REFERENCE_ID);
    }

    /**
     * @test
     *
     * @covers ::createOrderInvoice
     *
     * @dataProvider createOrderInvoice_amountWithDifferentDecimalsProvider
     *
     * @param float $amount
     * @param float $grandTotal
     * @param bool  $isSame
     *
     * @throws ReflectionException
     */
    public function createOrderInvoice_amountWithDifferentDecimals($amount, $grandTotal, $isSame)
    {
        $totalInvoiced = 0;
        $invoice = $this->createPartialMock(
            Invoice::class,
            [
                'setRequestedCaptureCase',
                'setTransactionId',
                'setBaseGrandTotal',
                'setGrandTotal',
                'register',
                'save',
            ]
        );
        $this->orderMock->expects(static::once())->method('getTotalInvoiced')->willReturn($totalInvoiced);
        $this->orderMock->expects(static::once())->method('getGrandTotal')->willReturn($grandTotal);
        $this->orderMock->method('addStatusHistoryComment')->willReturn($this->orderMock);
        $this->orderMock->method('setIsCustomerNotified')->willReturn($this->orderMock);
        if ($isSame) {
            $this->invoiceService->expects(static::once())->method('prepareInvoice')->willReturn($invoice);
        } else {
            $this->invoiceService->expects(static::once())->method('prepareInvoiceWithoutItems')->willReturn($invoice);
        }

        TestHelper::invokeMethod(
            $this->currentMock,
            'createOrderInvoice',
            [$this->orderMock, self::TRANSACTION_ID, $amount]
        );
    }

    /**
     * @return array
     */
    public function createOrderInvoice_amountWithDifferentDecimalsProvider()
    {
        return [
            ['amount' => 12.25, 'grandTotal' => 12.25, 'isSame' => true],
            ['amount' => 12.00, 'grandTotal' => 12.001, 'isSame' => true],
            ['amount' => 12.001, 'grandTotal' => 12.00, 'isSame' => true],
            ['amount' => 12.1225, 'grandTotal' => 12.1234, 'isSame' => true],
            ['amount' => 12.1234, 'grandTotal' => 12.1225, 'isSame' => true],
            ['amount' => 12.13, 'grandTotal' => 12.14, 'isSame' => false],
            ['amount' => 12.14, 'grandTotal' => 12.13, 'isSame' => false],
            ['amount' => 12.123, 'grandTotal' => 12.126, 'isSame' => false],
            ['amount' => 12.126, 'grandTotal' => 12.123, 'isSame' => false],
            ['amount' => 12.1264, 'grandTotal' => 12.1225, 'isSame' => false],
            ['amount' => 12.1225, 'grandTotal' => 12.1264, 'isSame' => false],
        ];
    }

    /**
     * @test
     * @covers ::createOrderInvoice
     * @throws ReflectionException
     */
    public function createOrderInvoice_prepareInvoiceThrowsException_logAndRethrowException()
    {
        $message = 'Expected exception message';
        $exception = new Exception($message);
        $this->orderMock->expects(static::once())->method('getTotalInvoiced')->willReturn(5);
        $this->orderMock->expects(static::once())->method('getGrandTotal')->willReturn(5);
        $this->invoiceService->expects(static::once())->method('prepareInvoice')->willThrowException($exception);
        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);
        TestHelper::invokeMethod(
            $this->currentMock,
            'createOrderInvoice',
            [$this->orderMock, self::TRANSACTION_ID, 0]
        );
    }

    /**
     * @test
     * @covers ::createOrderInvoice
     * @throws ReflectionException
     */
    public function createOrderInvoice_invoiceSenderThrowsException_logsException()
    {
        $invoice = $this->createPartialMock(
            Invoice::class,
            [
                'setRequestedCaptureCase',
                'setTransactionId',
                'setBaseGrandTotal',
                'setGrandTotal',
                'register',
                'save',
            ]
        );
        $message = 'Expected exception message';
        $exception = new Exception($message);
        $this->orderMock->expects(static::once())->method('getTotalInvoiced')->willReturn(5);
        $this->orderMock->expects(static::once())->method('getGrandTotal')->willReturn(5);
        $this->orderMock->method('addStatusHistoryComment')->willReturn($this->orderMock);
        $this->orderMock->method('setIsCustomerNotified')->willReturn($this->orderMock);
        $this->invoiceService->expects(static::once())->method('prepareInvoice')->willReturn($invoice);
        $this->invoiceSender->expects(static::once())->method('send')->willThrowException($exception);
        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        TestHelper::invokeMethod(
            $this->currentMock,
            'createOrderInvoice',
            [$this->orderMock, self::TRANSACTION_ID, 0]
        );
    }

    /**
     * @test
     *
     * @covers ::isZeroAmountHook
     *
     * @dataProvider isZeroAmountHook_withVariousTransactionStates_returnsExpectedResultProvider
     *
     * @param bool   $isHookFromBolt
     * @param string $transactionState
     * @param bool   $expectedResult
     */
    public function isZeroAmountHook_withVariousTransactionStates_returnsExpectedResult(
        $isHookFromBolt,
        $transactionState,
        $expectedResult
    ) {
        Hook::$fromBolt = $isHookFromBolt;
        static::assertEquals($expectedResult, $this->currentMock->isZeroAmountHook($transactionState));
    }

    /**
     * Data provider for {@see isZeroAmountHook_withVariousTransactionStates_returnsExpectedResult}
     */
    public function isZeroAmountHook_withVariousTransactionStates_returnsExpectedResultProvider()
    {
        return [
            ['isHookFromBolt' => true, 'transactionState' => OrderHelper::TS_ZERO_AMOUNT, 'expectedResult' => true],
            ['isHookFromBolt' => false, 'transactionState' => OrderHelper::TS_ZERO_AMOUNT, 'expectedResult' => false],
            ['isHookFromBolt' => true, 'transactionState' => OrderHelper::TS_CANCELED, 'expectedResult' => false],
            ['isHookFromBolt' => false, 'transactionState' => OrderHelper::TS_CANCELED, 'expectedResult' => false],
        ];
    }

    /**
     * @test
     *
     * @covers ::isCaptureHookRequest
     *
     * @dataProvider isCaptureHookRequestDataProvider
     *
     * @param bool $isHookFromBolt
     * @param bool $newCapture
     * @param bool $expectedResult
     *
     * @throws ReflectionException
     */
    public function isCaptureHookRequest($isHookFromBolt, $newCapture, $expectedResult)
    {
        Hook::$fromBolt = $isHookFromBolt;
        static::assertEquals(
            $expectedResult,
            TestHelper::invokeMethod($this->currentMock, 'isCaptureHookRequest', [$newCapture])
        );
    }

    /**
     * Data provider for {@see isCaptureHookRequest}
     */
    public function isCaptureHookRequestDataProvider()
    {
        return [
            ['isHookFromBolt' => true, 'newCapture' => true, 'expectedResult' => true],
            ['isHookFromBolt' => true, 'newCapture' => false, 'expectedResult' => false],
            ['isHookFromBolt' => false, 'newCapture' => true, 'expectedResult' => false],
            ['isHookFromBolt' => false, 'newCapture' => false, 'expectedResult' => false],
        ];
    }

    /**
     * @test
     *
     * @covers ::validateCaptureAmount
     *
     * @throws ReflectionException
     */
    public function validateCaptureAmount_invalidCaptureAmount_throwsException()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Capture amount is invalid');
        TestHelper::invokeMethod($this->currentMock, 'validateCaptureAmount', [$this->orderMock, null]);
    }

    /**
     * @test
     *
     * @covers ::validateCaptureAmount
     *
     * @throws ReflectionException
     */
    public function validateCaptureAmount_captureAmountAndGrandTotalMismatch_throwsException()
    {
        $this->orderMock->expects(self::once())->method('getOrderCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $this->orderMock->expects(self::once())->method('getTotalInvoiced')->willReturn(10);
        $this->orderMock->expects(self::once())->method('getGrandTotal')->willReturn(5);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(sprintf(
                'Capture amount is invalid: captured [%s], grand total [%s]', 2000, 500)
        );
        TestHelper::invokeMethod($this->currentMock, 'validateCaptureAmount', [$this->orderMock, 10]);
    }

    /**
     * @test
     *
     * @covers ::validateCaptureAmount
     *
     * @throws ReflectionException
     */
    public function validateCaptureAmount_captureAmountAndGrandTotalMatch_doesNothing()
    {
        $this->orderMock->expects(self::once())->method('getOrderCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $this->orderMock->expects(self::once())->method('getTotalInvoiced')->willReturn(10);
        $this->orderMock->expects(self::once())->method('getGrandTotal')->willReturn(20);

        TestHelper::invokeMethod($this->currentMock, 'validateCaptureAmount', [$this->orderMock, 10]);
    }

    /**
     * @test
     *
     * @covers ::isAnAllowedUpdateFromAdminPanel
     *
     * @dataProvider isAnAllowedUpdateFromAdminPanel_withVariousParameters_returnsExpectedResultProvider
     *
     * @param bool   $isHookFromBolt
     * @param string $txState
     * @param string $orderState
     * @param bool   $expectedResult
     *
     * @throws ReflectionException
     */
    public function isAnAllowedUpdateFromAdminPanel_withVariousParameters_returnsExpectedResult(
        $isHookFromBolt, $txState, $orderState, $expectedResult
    ) {
        Hook::$fromBolt = $isHookFromBolt;
        $this->orderMock->method('getState')->willReturn($orderState);
        static::assertSame(
            $expectedResult,
            TestHelper::invokeMethod(
                $this->currentMock,
                'isAnAllowedUpdateFromAdminPanel',
                [$this->orderMock, $txState]
            )
        );
    }

    /**
     * Data provider for {@see isAnAllowedUpdateFromAdminPanel_withVariousParameters_returnsExpectedResult}
     */
    public function isAnAllowedUpdateFromAdminPanel_withVariousParameters_returnsExpectedResultProvider()
    {
        return [
            [
                'isHookFromBolt' => false,
                'txState'        => OrderHelper::TS_AUTHORIZED,
                'orderState'     => Order::STATE_PAYMENT_REVIEW,
                'expectedResult' => true
            ],
            [
                'isHookFromBolt' => false,
                'txState'        => OrderHelper::TS_COMPLETED,
                'orderState'     => Order::STATE_PAYMENT_REVIEW,
                'expectedResult' => true
            ],
            [
                'isHookFromBolt' => true,
                'txState'        => OrderHelper::TS_COMPLETED,
                'orderState'     => Order::STATE_PAYMENT_REVIEW,
                'expectedResult' => false
            ],
            [
                'isHookFromBolt' => false,
                'txState'        => OrderHelper::TS_CANCELED,
                'orderState'     => Order::STATE_PAYMENT_REVIEW,
                'expectedResult' => false
            ],
            [
                'isHookFromBolt' => false,
                'txState'        => OrderHelper::TS_COMPLETED,
                'orderState'     => Order::STATE_PROCESSING,
                'expectedResult' => false
            ],
        ];
    }

    /**
     * @test
     *
     * @covers ::getVoidMessage
     *
     * @throws ReflectionException
     */
    public function getVoidMessage_withValidPayment_returnsExpectedMessage()
    {
        $this->initCurrentMock(['formatAmountForDisplay']);
        $amount = '$5';
        $transaction = 'aaaabbbbcccc';
        $this->currentMock->expects(self::once())->method('formatAmountForDisplay')->with($this->orderMock, 5)
            ->willReturn($amount);
        $paymentMock = $this->createMock(OrderPayment::class);
        $authorizationTransaction = $this->createMock(Transaction::class);
        $authorizationTransaction->expects(self::once())->method('getHtmlTxnId')->willReturn($transaction);
        $paymentMock->expects(self::once())->method('getAuthorizationTransaction')
            ->willReturn($authorizationTransaction);
        $paymentMock->expects(self::once())->method('getOrder')->willReturn($this->orderMock);
        $this->orderMock->expects(self::once())->method('getGrandTotal')->willReturn(10);
        $this->orderMock->expects(self::once())->method('getTotalPaid')->willReturn(5);
        static::assertSame(
            sprintf(
                "BOLT notification: Transaction authorization has been voided. Amount: %s. Transaction ID: %s.",
                $amount,
                $transaction
            ),
            TestHelper::invokeMethod($this->currentMock, 'getVoidMessage', [$paymentMock])
        );
    }

    /**
     * @test
     *
     * @covers ::formatAmountForDisplay
     */
    public function formatAmountForDisplay_withValidAmount_returnsFormattedAmount()
    {
        $currencyMock = $this->createMock(Currency::class);
        $currencyMock->expects(static::once())->method('formatTxt')->willReturn("$1.23");
        $this->orderMock->expects(static::once())->method('getOrderCurrency')->willReturn($currencyMock);

        static::assertEquals("$1.23", $this->currentMock->formatAmountForDisplay($this->orderMock, 1.23));
    }

    /**
     * @test
     *
     * @covers ::getStoreIdByQuoteId
     */
    public function getStoreIdByQuoteId_withValidQuote_returnsStoreIdFromQuote()
    {
        $this->quoteMock->expects(static::once())->method('getStoreId')->willReturn(self::STORE_ID);
        $this->cartHelper->expects(static::once())->method('getQuoteById')->with(self::PARENT_QUOTE_ID)
            ->willReturn($this->quoteMock);
        static::assertEquals(self::STORE_ID, $this->currentMock->getStoreIdByQuoteId(self::PARENT_QUOTE_ID));
    }

    /**
     * @test
     *
     * @covers ::getStoreIdByQuoteId
     */
    public function getStoreIdByQuoteId_withEmptyQuoteId_returnsNull()
    {
        static::assertNull($this->currentMock->getStoreIdByQuoteId(''));
    }

    /**
     * @test
     *
     * @covers ::getOrderStoreIdByDisplayId
     */
    public function getOrderStoreIdByDisplayId_withEmptyDisplayId_returnsNull()
    {
        static::assertNull($this->currentMock->getOrderStoreIdByDisplayId(''));
    }

    /**
     * @test
     *
     * @covers ::getOrderStoreIdByDisplayId
     */
    public function getOrderStoreIdByDisplayId_withInvalidDisplayId_returnsNull()
    {
        static::assertNull($this->currentMock->getOrderStoreIdByDisplayId(' / '));
    }

    /**
     * @test
     *
     * @covers ::getOrderStoreIdByDisplayId
     * @covers ::getDataFromDisplayID
     */
    public function getOrderStoreIdByDisplayId_withValidDisplayId_returnsStoreId()
    {
        $orderMock = $this->createPartialMock(Order::class, ['getStoreId']);
        $orderMock->expects(static::exactly(2))->method('getStoreId')->willReturn(self::STORE_ID);
        $this->cartHelper->expects(static::once())->method('getOrderByIncrementId')
            ->with(self::INCREMENT_ID)->willReturn($orderMock);
        static::assertEquals(self::STORE_ID, $this->currentMock->getOrderStoreIdByDisplayId(self::DISPLAY_ID));
    }

    /**
     * @return array
     *
     * @throws ReflectionException
     */
    private function setBillingAndShippingMethodSetUp()
    {
        $this->initCurrentMock(['setAddress']);
        $quote = $this->createPartialMock(Quote::class, ['getBillingAddress', 'getShippingAddress']);
        $addressMock = $this->createMock(Address::class);
        $transaction = json_decode(
            json_encode(
                [
                    'order' => [
                        'cart' => [
                            'billing_address' => (object)self::ADDRESS_DATA,
                            'shipments'       => [
                                ['shipping_address' => (object)self::ADDRESS_DATA,]
                            ]
                        ]
                    ]
                ]
            )
        );

        $quote->method('getBillingAddress')->willReturn($addressMock);
        $quote->method('getShippingAddress')->willReturn($addressMock);
        return [$quote, $addressMock, $transaction];
    }

    /**
     * @test
     *
     * @covers ::setBillingAddress
     */
    public function setBillingAddress()
    {
        list($quote, $addressMock, $transaction) = $this->setBillingAndShippingMethodSetUp();

        $this->currentMock->expects(self::once())->method('setAddress')->with($addressMock, (object)self::ADDRESS_DATA);

        TestHelper::invokeMethod($this->currentMock, 'setBillingAddress', [$quote, $transaction]);
    }

    /**
     * @test
     *
     * @covers ::setShippingAddress
     */
    public function setShippingAddress()
    {
        list($quote, $addressMock, $transaction) = $this->setBillingAndShippingMethodSetUp();

        $this->currentMock->expects(self::once())->method('setAddress')->with($addressMock, (object)self::ADDRESS_DATA);

        TestHelper::invokeMethod($this->currentMock, 'setShippingAddress', [$quote, $transaction]);
    }

    /**
     * @test
     * @covers ::verifyOrderCreationHookType
     * @dataProvider verifyOrderCreationHookTypeProvider
     */
    public function verifyOrderCreationHookType($hookFromBolt, $hookType, $expectException = false)
    {
        Hook::$fromBolt = $hookFromBolt;
        if ( $expectException ) {
            $this->expectException(BoltException::class);
            $this->expectExceptionCode(\Bolt\Boltpay\Model\Api\CreateOrder::E_BOLT_REJECTED_ORDER);
            $this->expectExceptionMessage(
                sprintf(
                    'Order creation is forbidden from hook of type: %s',
                    $hookType
                )
            );
        }
        $this->assertNull($this->currentMock->verifyOrderCreationHookType($hookType));
    }
    public function verifyOrderCreationHookTypeProvider()
    {
        return [
            [true, Hook::HT_PENDING],
            [true, Hook::HT_PAYMENT],
            [true, Hook::HT_VOID, true],
            [true, null, true],
            [true, '', true],
            [false, Hook::HT_PENDING],
            [false, Hook::HT_PAYMENT],
            [false, Hook::HT_VOID, true],
            [false, null],
            [false, '', true]
        ];
    }
}