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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
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
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Model\Api\CreateOrder;
use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Model\Request;
use Bolt\Boltpay\Model\Response;
use Bolt\Boltpay\Model\Service\InvoiceService;
use Bugsnag\Report;
use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Event\Manager;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\SessionException;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface as TransactionInterface;
use Magento\Sales\Model\Order\Email\Container\InvoiceIdentity as InvoiceEmailIdentity;
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
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use Magento\Store\Model\ScopeInterface;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Exception\BoltException;
use ReflectionException;
use stdClass;
use Zend_Http_Client_Exception;
use Zend_Validate_Exception;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Model\Request as BoltRequest;
use Bolt\Boltpay\Model\ResponseFactory;
use Bolt\Boltpay\Model\ResourceModel\WebhookLog\CollectionFactory as WebhookLogCollectionFactory;
use Bolt\Boltpay\Model\WebhookLogFactory;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Helper\CheckboxesHandler;
use Bolt\Boltpay\Test\Unit\Model\Api\OrderManagementTest;
use Bolt\Boltpay\Model\CustomerCreditCardFactory;
use Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard\CollectionFactory as CustomerCreditCardCollectionFactory;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * @coversDefaultClass \Bolt\Boltpay\Helper\Order
 */
class OrderTest extends BoltTestCase
{
    const INCREMENT_ID = 1000001;
    const QUOTE_ID = 5678;
    const IMMUTABLE_QUOTE_ID = self::QUOTE_ID + 1;
    const DISPLAY_ID = self::INCREMENT_ID;
    const REFERENCE_ID = '1123123123';
    const PROCESSOR_VANTIV = 'vantiv';
    const PROCESSOR_PAYPAL = 'paypal';
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
    const USER_ID = 1;
    const HOOK_TYPE_PENDING = 'pending';
    const HOOK_TYPE_AUTH = 'auth';
    const HOOK_PAYLOAD = ['checkboxes' => ['text'=>'Subscribe for our newsletter','category'=>'NEWSLETTER','value'=>true] ];
    const CUSTOMER_ID = 1111;

    /** @var string test cart network */
    const CREDIT_CARD_NETWORK = 'visa';

    /** @var int test credit card last 4 digits */
    const CREDIT_CARD_LAST_FOUR = 1111;

    /** @var MockObject|ApiHelper mocked instance of the Bolt api helper */
    private $apiHelper;

    /** @var MockObject|ConfigHelper mocked instance of the Bolt configuration helper */
    private $configHelper;

    /** @var MockObject|RegionModel mocked instance of the region model */
    private $regionModel;

    /** @var MockObject|QuoteManagement mocked instance of the quote management model */
    private $quoteManagement;

    /** @var MockObject|OrderSender mocked instance of the email order sender */
    private $emailSender;

    /** @var MockObject|InvoiceService mocked instance of the Bolt model invoice service */
    private $invoiceService;

    /** @var MockObject|InvoiceSender mocked instance of the email invoice sender */
    private $invoiceSender;

    /** @var MockObject|TransactionBuilder mocked instance of the payment transaction builder */
    private $transactionBuilder;

    /** @var MockObject|TimezoneInterface mocked instance of the date time timezone */
    private $timezone;

    /** @var MockObject|SearchCriteriaBuilder mocked instance of the api search criteria builder */
    private $searchCriteriaBuilder;

    /** @var MockObject|OrderRepository mocked instance of the api order repository */
    private $orderRepository;

    /** @var MockObject|DataObjectFactory mocked instance of the data object factory */
    private $dataObjectFactory;

    /** @var MockObject|LogHelper mocked instance of the Bolt log helper */
    private $logHelper;

    /** @var MockObject|Bugsnag mocked instance of the Bolt Bugsnag helper */
    private $bugsnag;

    /** @var MockObject|CartHelper mocked instance of the Bolt cart helper */
    private $cartHelper;

    /** @var MockObject|ResourceConnection mocked instance of the resource connection app */
    private $resourceConnection;

    /** @var MockObject|SessionHelper mocked instance of the Bolt session helper */
    private $sessionHelper;

    /** @var MockObject|DiscountHelper mocked instance of the Bolt discount helper */
    private $discountHelper;

    /** @var MockObject|DateTime mocked instance of the date time library */
    protected $date;

    /** @var MockObject|OrderHelper mocked instance of the Bolt order helper */
    private $currentMock;

    /** @var MockObject|Order mocked instance of the order model */
    private $orderMock;

    /** @var MockObject|Config mocked instance of the order model config */
    private $orderConfigMock;

    /** @var MockObject|Context mocked instance of the context helper */
    private $context;

    /** @var MockObject|Quote mocked instance of the Quote model */
    private $quoteMock;

    /** @var MockObject|InfoInterface mocked instance of the info interface payment model */
    private $paymentMock;

    /** @var MockObject|Manager mocked instance of the event manager */
    private $eventManager;

    /** @var MockObject|Mysql mocked instance of the mysql db adapter */
    private $connection;

    /** @var MockObject|BoltRequest mocked instance of the Bolt request model */
    private $boltRequest;

    /** @var MockObject|ResponseFactory mocked instance of the Bolt response factory model */
    private $responseFactory;

    /** @var MockObject|WebhookLogCollectionFactory mocked instance of the Bolt resource webhook */
    private $webhookLogCollectionFactory;

    /** @var MockObject|WebhookLogFactory mocked instance of the Bolt webbook log factory model */
    private $webhookLogFactory;

    /** @var MockObject|Decider mocked instance of the Bolt feature switch helper */
    private $featureSwitches;

    /** @var MockObject|CheckboxesHandler mocked instance of the Bolt checkbox handler helper */
    private $checkboxesHandler;

    /** @var MockObject|CustomerCreditCardFactory mocked instance of the Bolt customer credit card factory helper */
    private $customerCreditCardFactory;

    /** @var MockObject|CustomerCreditCardCollectionFactory mocked instance of the Bolt resource model */
    private $customerCreditCardCollectionFactory;

    /** @var MockObject|CreditmemoFactory mocked instance of the credit memo order model */
    private $creditmemoFactory;

    /** @var MockObject|CreditmemoManagementInterface mocked instance of the credit memo management api interface */
    private $creditmemoManagement;

    /** @var MockObject|ScopeConfigInterface mocked instance of the configuration provider */
    private $scopeConfigMock;

    /** @var MockObject|EventsForThirdPartyModules */
    private $eventsForThirdPartyModules;

    /**
     * @var \Magento\Sales\Api\OrderManagementInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $orderManagementMock;

    /**
     * @var \Magento\Sales\Model\OrderIncrementIdChecker|\PHPUnit\Framework\MockObject\MockObject
     */
    private $orderIncrementIdChecker;

    /**
     * Setup test dependencies, called before each test
     *
     * @throws ReflectionException from initRequiredMocks and initCurrentMock methods
     */
    protected function setUpInternal()
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
                'getTotalRefunded'
            ]
        );
    }

    /**
     * Cleanup changes made by tests
     */
    protected function tearDownInternal()
    {
        Hook::$fromBolt = false;
    }

    /**
     * Sets mocked instance of the tested class
     *
     * @param array $methods to be mocked
     * @param bool  $disableOriginalConstructor flag
     *
     * @throws ReflectionException if cartHelper, discountHelper or scopeConfig properties don't exist
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
                    $this->date,
                    $this->webhookLogCollectionFactory,
                    $this->webhookLogFactory,
                    $this->featureSwitches,
                    $this->checkboxesHandler,
                    $this->customerCreditCardFactory,
                    $this->customerCreditCardCollectionFactory,
                    $this->creditmemoFactory,
                    $this->creditmemoManagement,
                    $this->eventsForThirdPartyModules,
                    $this->orderManagementMock,
                    $this->orderIncrementIdChecker,
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
        TestHelper::setProperty($this->currentMock, 'scopeConfig', $this->scopeConfigMock);
    }

    /**
     * Sets required mocked instance for the tested class
     *
     * @throws ReflectionException if unable to create one of the required mocks
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
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->cartHelper = $this->getMockBuilder(CartHelper::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getQuoteById',
                'handleSpecialAddressCases',
                'isVirtual',
                'getOrderByIncrementId',
                'quoteResourceSave',
                'replicateQuoteData',
                'getCartData',
                'getOrderByQuoteId',
                'validateEmail',
                'getOrderById'
            ])
            ->getMock();

        $this->connection = $this->createMock(Mysql::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->sessionHelper = $this->createMock(SessionHelper::class);
        $this->discountHelper = $this->createMock(DiscountHelper::class);
        $this->date = $this->createMock(DateTime::class);
        $this->checkboxesHandler = $this->createMock(CheckboxesHandler::class);

        $this->dataObjectFactory = $this->getMockBuilder(DataObjectFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create','setApiData', 'setDynamicApiUrl','setApiKey'])
            ->getMock();

        $this->responseFactory = $this->createPartialMock(ResponseFactory::class, ['getResponse']);
        $this->boltRequest = $this->createMock(BoltRequest::class);
        $this->webhookLogCollectionFactory = $this->createPartialMock(WebhookLogCollectionFactory::class, ['create','getWebhookLogByTransactionId']);
        $this->webhookLogFactory = $this->createPartialMock(WebhookLogFactory::class, ['getNumberOfMissingQuoteFailedHooks','incrementAttemptCount','recordAttempt','create','getId']);

        $this->customerCreditCardFactory = $this->getMockBuilder(CustomerCreditCardFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create','saveCreditCard'])
            ->getMock();

        $this->customerCreditCardCollectionFactory = $this->getMockBuilder(CustomerCreditCardCollectionFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create', 'doesCardExist'])
            ->getMock();

        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCustomerId','getReservedOrderId','getId','isVirtual','setUpdatedAt','getStoreId','getBillingAddress','setIsActive','getIsActive', 'getBoltCheckoutType', 'setBoltCheckoutType'])
            ->getMock();

        $this->scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)
            ->getMockForAbstractClass();

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
                'getOrderCurrency',
                'getQuoteId',
                'getAllStatusHistory',
                'getCustomerId',
                'getBillingAddress',
                'getStoreId',
                'getTotalRefunded',
                'setData',
                'addData',
                'getData',
                'getOriginalIncrementId',
                'getEditIncrement',
                'setRelationChildId',
                'setRelationChildRealId',
                'getEntityId',
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
        $this->featureSwitches = $this->createPartialMock(
            Decider::class,
            [
                'isLogMissingQuoteFailedHooksEnabled',
                'isCreatingCreditMemoFromWebHookEnabled',
                'isIgnoreHookForInvoiceCreationEnabled',
                'isCancelFailedPaymentOrderInsteadOfDeleting'
            ]
        );
        $this->creditmemoFactory = $this->createPartialMock(CreditmemoFactory::class, ['createByOrder']);
        $this->creditmemoManagement = $this->createMock(CreditmemoManagementInterface::class);
        $this->eventsForThirdPartyModules = $this->createMock(EventsForThirdPartyModules::class);
        $this->orderManagementMock = $this->createMock(\Magento\Sales\Api\OrderManagementInterface::class);
        $this->orderIncrementIdChecker = $this->getMockBuilder(\Magento\Sales\Model\OrderIncrementIdChecker::class)
            ->disableOriginalConstructor()
            ->setMethods(['isIncrementIdUsed'])
            ->getMock();
    }

    /**
     * @test
     * that constructor sets provided arguments to appropriate properties
     *
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperties()
    {
        $instance = new OrderHelper(
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
            $this->date,
            $this->webhookLogCollectionFactory,
            $this->webhookLogFactory,
            $this->featureSwitches,
            $this->checkboxesHandler,
            $this->customerCreditCardFactory,
            $this->customerCreditCardCollectionFactory,
            $this->creditmemoFactory,
            $this->creditmemoManagement,
            $this->eventsForThirdPartyModules
        );
        static::assertAttributeEquals($this->apiHelper, 'apiHelper', $instance);
        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
        static::assertAttributeEquals($this->regionModel, 'regionModel', $instance);
        static::assertAttributeEquals($this->quoteManagement, 'quoteManagement', $instance);
        static::assertAttributeEquals($this->emailSender, 'emailSender', $instance);
        static::assertAttributeEquals($this->invoiceService, 'invoiceService', $instance);
        static::assertAttributeEquals($this->invoiceSender, 'invoiceSender', $instance);
        static::assertAttributeEquals($this->searchCriteriaBuilder, 'searchCriteriaBuilder', $instance);
        static::assertAttributeEquals($this->orderRepository, 'orderRepository', $instance);
        static::assertAttributeEquals($this->transactionBuilder, 'transactionBuilder', $instance);
        static::assertAttributeEquals($this->timezone, 'timezone', $instance);
        static::assertAttributeEquals($this->dataObjectFactory, 'dataObjectFactory', $instance);
        static::assertAttributeEquals($this->logHelper, 'logHelper', $instance);
        static::assertAttributeEquals($this->bugsnag, 'bugsnag', $instance);
        static::assertAttributeEquals($this->cartHelper, 'cartHelper', $instance);
        static::assertAttributeEquals($this->resourceConnection, 'resourceConnection', $instance);
        static::assertAttributeEquals($this->sessionHelper, 'sessionHelper', $instance);
        static::assertAttributeEquals($this->discountHelper, 'discountHelper', $instance);
        static::assertAttributeEquals($this->date, 'date', $instance);
        static::assertAttributeEquals($this->webhookLogCollectionFactory, 'webhookLogCollectionFactory', $instance);
        static::assertAttributeEquals($this->webhookLogFactory, 'webhookLogFactory', $instance);
        static::assertAttributeEquals($this->featureSwitches, 'featureSwitches', $instance);
        static::assertAttributeEquals($this->checkboxesHandler, 'checkboxesHandler', $instance);
        static::assertAttributeEquals($this->customerCreditCardFactory, 'customerCreditCardFactory', $instance);
        static::assertAttributeEquals(
            $this->customerCreditCardCollectionFactory,
            'customerCreditCardCollectionFactory',
            $instance
        );
        static::assertAttributeEquals($this->creditmemoFactory, 'creditmemoFactory', $instance);
        static::assertAttributeEquals($this->creditmemoManagement, 'creditmemoManagement', $instance);
        static::assertAttributeEquals($this->eventsForThirdPartyModules, 'eventsForThirdPartyModules', $instance);
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

        $response = (object)['display_id' => static::DISPLAY_ID];

        $result->expects(static::once())->method('getResponse')->willReturn($response);

        static::assertEquals(
            $response,
            $this->currentMock->fetchTransactionInfo(static::REFERENCE_ID, static::STORE_ID)
        );

        // When we call method second time result should be returned from cache
        static::assertEquals(
            $response,
            $this->currentMock->fetchTransactionInfo(static::REFERENCE_ID, static::STORE_ID)
        );
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
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $product = TestUtils::createVirtualProduct();
        $quote->addProduct($product, 1);
        $quote->setIsVirtual(true);
        $quote->save();

        TestHelper::invokeMethod(
            $boltHelperOrder,
            'setShippingMethod',
            [$quote, new stdClass()]
        );

        static::assertNull($quote->getShippingMethod());
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
        $this->skipTestInUnitTestsFlow();
        $quote = TestUtils::createQuote();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);

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

        TestHelper::invokeMethod($boltHelperOrder, 'setShippingMethod', [$quote, $transaction]);
        static::assertEquals('flatrate_flatrate', $quote->getShippingAddress()->getShippingMethod());
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
        $this->skipTestInUnitTestsFlow();
        $addressObject = (object) self::ADDRESS_DATA;;
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $quote = TestUtils::createQuote();
        $quoteAddress = $quote->getShippingAddress();
        TestHelper::invokeMethod(
            $boltHelperOrder,
            'setAddress',
            [$quoteAddress, $addressObject]
        );
        self::assertEquals($quote->getShippingAddress()->getCity(),'Beverly Hills');
        self::assertEquals($quote->getShippingAddress()->getCountryId(),'US');
        self::assertEquals($quote->getShippingAddress()->getCompany(),'Bolt');
        self::assertEquals($quote->getShippingAddress()->getEmail(),'test@bolt.com');
        self::assertEquals($quote->getShippingAddress()->getTelephone(),'0123456789');
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
        $this->quoteMock->expects(self::once())->method('getId')->willReturn(self::QUOTE_ID);

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function ($callback) {
                $report = $this->createMock(Report::class);
                $report->expects(self::once())->method('setMetaData')->with(
                    [
                        'TAX MISMATCH' => [
                            'Store Applied Taxes' => null,
                            'Bolt Tax Amount'     => (float)10,
                            'Store Tax Amount'    => (float)50,
                            'Quote ID'            => self::QUOTE_ID,
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
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder(['quote_id' => self::QUOTE_ID]);
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);

        static::assertEquals(
            $order->getId(),
            TestHelper::invokeMethod(
                $boltHelperOrder,
                'checkExistingOrder',
                [self::QUOTE_ID]
            )->getId()
        );
        TestUtils::cleanupSharedFixtures([$order]);
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
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);

        static::assertFalse(
            TestHelper::invokeMethod(
                $boltHelperOrder,
                'checkExistingOrder',
                [self::QUOTE_ID]
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

        $quoteMock = $this->createPartialMock(Quote::class, ['getId']);
        $quoteMock->method('getId')->willReturn(self::QUOTE_ID);

        $this->createOrderSetUp($quoteMock);
        $this->currentMock->method('getExistingOrder')->willReturn(null);
        $this->quoteManagement->expects(self::once())->method('submit')->with($quoteMock)
            ->willReturn(null);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Quote Submit Error. Parent Quote ID: '.self::QUOTE_ID.
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
                $this->currentMock,
                'createOrder',
                [$this->quoteMock, $transaction]
            )
        );
    }

    /**
     * @test
     * that orderPostprocess saves additional order data
     *
     * @covers ::orderPostprocess
     * @covers ::adjustTaxMismatch
     *
     * @throws ReflectionException if orderPostprocess method doesn't exist
     */
    public function orderPostprocess_withVariousBoltCheckoutTypes_savesAdditionalOrderData()
    {
        $this->initCurrentMock(['setOrderUserNote']);
        $quoteMock = $this->createPartialMock(Quote::class, ['getBoltCheckoutType', 'setBoltCheckoutType']);
        $userNote = 'Test User Note';
        $transaction = json_decode(
            json_encode(
                [
                    'order'     => [
                        'cart'      => [
                            'tax_amount'   => [
                                'amount' => 500,
                            ],
                            'total_amount' => [
                                'amount' => 500,
                            ],
                        ],
                        'user_note' => $userNote,
                    ],
                    'reference' => self::REFERENCE_ID,
                ]
            )
        );

        $quoteMock->expects(static::once())->method('getBoltCheckoutType')
            ->willReturn(CartHelper::BOLT_CHECKOUT_TYPE_PPC);
        $quoteMock->expects(static::once())->method('setBoltCheckoutType')
            ->with(CartHelper::BOLT_CHECKOUT_TYPE_PPC_COMPLETE);
        $this->cartHelper->expects(static::once())->method('quoteResourceSave')->with($quoteMock);
        $this->configHelper->expects(self::once())->method('shouldAdjustTaxMismatch')->willReturn(true);
        //adjust tax mismatch start
        $this->orderMock->expects(self::exactly(2))->method('getOrderCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $this->orderMock->expects(self::once())->method('getTaxAmount')->willReturn(5);
        //adjust tax mismatch end
        $this->configHelper->expects(self::exactly(2))->method('getPriceFaultTolerance')
            ->willReturnOnConsecutiveCalls(1, 1);

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
            [$this->orderMock, $quoteMock, $transaction]
        );
    }

    /**
     * @test
     * that orderPostprocess sets bolt checkout type to ppc complete if current checkout type is ppc
     *
     * @covers ::orderPostprocess
     *
     * @throws ReflectionException if quoteResourceSave method is not defined
     */
    public function orderPostprocess_ifQuoteCheckoutTypeIsPPC_setsQuoteCheckoutTypeToPPCComplete()
    {
        $this->initCurrentMock([]);
        $transaction = new stdClass();
        $this->quoteMock->expects(static::once())->method('getBoltCheckoutType')
            ->willReturn(CartHelper::BOLT_CHECKOUT_TYPE_PPC);
        $this->quoteMock->expects(static::once())->method('setBoltCheckoutType')
            ->willReturn(CartHelper::BOLT_CHECKOUT_TYPE_PPC_COMPLETE);
        $this->cartHelper->expects(static::once())->method('quoteResourceSave')->with($this->quoteMock);

        TestHelper::invokeMethod(
            $this->currentMock,
            'orderPostprocess',
            [$this->orderMock, $this->quoteMock, $transaction]
        );
    }

    /**
     * @test
     * if the payment already has the card network type and the card's last four digits,
     * the card data is not updated
     *
     * @covers ::setOrderPaymentInfoData
     *
     * @throws ReflectionException if setOrderPaymentInfoData method is not defined
     */
    public function setOrderPaymentInfoData_ifPaymentHasExistingCardData_cardDataIsNotUpdatedAndPaymentIsSaved()
    {
        $payment = $this->getMockBuilder(Payment::class)
            ->setMethods(['getCcLast4', 'setCcLast4', 'getCcType', 'setCcType', 'save'])
            ->disableOriginalConstructor()
            ->getMock();

        $transaction = (object)[
            'from_credit_card' => (object)[
                'last4' => 4444,
                'network' => 'mastercard',
            ],
        ];

        $testLastFour = 4242;
        $payment->expects(self::once())->method('getCcLast4')->willReturn($testLastFour);
        $payment->expects(self::never())
            ->method('setCcLast4');

        $testCreditCardNetwork = 'visa';
        $payment->expects(self::once())->method('getCcType')->willReturn($testCreditCardNetwork);
        $payment->expects(self::never())
            ->method('setCcType');

        $payment->expects(self::once())->method('save')->willReturnSelf();

        TestHelper::invokeMethod($this->currentMock, 'setOrderPaymentInfoData', [$payment, $transaction]);
    }

    /**
     * @test
     * if the Magento payment is missing any credit card information, it is saved to the payment from
     * the Bolt transaction
     *
     * @covers ::setOrderPaymentInfoData
     *
     * @throws ReflectionException if setOrderPaymentInfoData method is not defined
     */
    public function setOrderPaymentInfoData_ifPaymentIsMissingLastFourOrType_theyAreUpdated()
    {
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $payment = $order->getPayment();


        $transaction = (object)[
            'from_credit_card' => (object)[
                'last4' => self::CREDIT_CARD_LAST_FOUR,
                'network' => self::CREDIT_CARD_NETWORK,
            ],
        ];
        TestHelper::invokeMethod($boltHelperOrder, 'setOrderPaymentInfoData', [$payment, $transaction]);
        TestUtils::cleanupSharedFixtures([$order]);
        self::assertEquals(self::CREDIT_CARD_LAST_FOUR, $order->getPayment()->getCcLast4());
        self::assertEquals(self::CREDIT_CARD_NETWORK, $order->getPayment()->getCcType());
    }

    /**
     * @test
     *
     * @covers ::deleteRedundantQuotes
     *
     * @throws ReflectionException if deleteRedundantQuotes method is not defined
     */
    public function deleteRedundantQuotes()
    {
        $quoteMock = $this->createPartialMock(Quote::class, ['getBoltParentQuoteId']);
        $quoteMock->method('getBoltParentQuoteId')->willReturn(self::QUOTE_ID);
        $this->resourceConnection->expects(self::once())->method('getTableName')->with('quote')
            ->willReturn('quote');
        $this->connection->expects(self::once())->method('delete')->with(
            'quote',
            [
                'bolt_parent_quote_id = ?' => self::QUOTE_ID,
                'entity_id != ?'           => self::QUOTE_ID
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
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $boltHelperOrder->resetOrderState($order);

        self::assertEquals(OrderHelper::MAGENTO_ORDER_STATUS_PENDING, $order->getStatus());
        self::assertEquals(OrderModel::STATE_PENDING_PAYMENT, $order->getState());
        self::assertEquals($order->getAllStatusHistory()[0]->getComment(),'BOLTPAY INFO :: This order was approved by Bolt');
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * that getOrderByQuoteId returns order based on provided quote id from
     * {@see \Bolt\Boltpay\Helper\Cart::getOrderByQuoteId}
     *
     * @covers ::getOrderByQuoteId
     */
    public function getOrderByQuoteId()
    {
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder(['quote_id' => self::QUOTE_ID]);
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        static::assertEquals($order->getId(), $boltHelperOrder->getOrderByQuoteId(self::QUOTE_ID)->getId());
        TestUtils::cleanupSharedFixtures([$order]);
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
                'updateOrderPayment',
                'getExistingOrder'
            ],
            false
        );

        $transaction = json_decode(
            json_encode(
                [
                    'order' => [
                        'cart' => [
                            'order_reference' => self::QUOTE_ID,
                            'display_id'      => self::DISPLAY_ID,
                            'total_amount'    => [
                                'amount' => 100,
                            ],
                            'metadata'        => [
                                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
                            ],
                        ]
                    ],
                    'status' => 'cancelled',
                    'id' => '111'
                ]
            )
        );

        $this->currentMock->expects(self::once())->method('fetchTransactionInfo')
            ->with(self::REFERENCE_ID, self::STORE_ID)->willReturn($transaction);
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

        $immutablequoteMock = $this->createMock(Quote::class);

        $this->cartHelper->expects(self::exactly(2))->method('getQuoteById')
            ->willReturnMap([
                [self::IMMUTABLE_QUOTE_ID, $immutablequoteMock],
                [self::QUOTE_ID, $this->quoteMock]
            ]);

        $this->bugsnag->expects(self::never())->method('registerCallback');

        $this->currentMock->expects(self::once())->method('getExistingOrder')
            ->with(self::INCREMENT_ID)->willReturn($this->orderMock);
        $this->orderMock->expects(self::once())->method('getId')->willReturn(self::ORDER_ID);
        $this->discountHelper->expects(self::once())->method('deleteRedundantAmastyGiftCards')->with($this->quoteMock);
        $this->discountHelper->expects(self::once())->method('deleteRedundantAmastyRewardPoints')->with(
            $this->quoteMock
        );

        static::assertEquals(
            [$this->quoteMock, $this->orderMock],
            $this->currentMock->saveUpdateOrder(
                self::REFERENCE_ID,
                self::STORE_ID,
                self::BOLT_TRACE_ID,
                self::HOOK_TYPE_PENDING,
                self::HOOK_PAYLOAD
            )
        );
    }

    /**
     * @test
     * that saveUpdateOrder doesn't update order when all the following are true:
     * 1. Order is not already created
     * 2. Immutable quote is not found
     * 3. Request is a hook
     * 4. Transaction status is Authorized
     * 5. M2_LOG_MISSING_QUOTE_FAILED_HOOKS feature switch is disabled
     * This all results in “Unknown quote id” exception only being notified to bugsnag instead of being thrown
     *
     * @covers ::saveUpdateOrder
     *
     * @throws LocalizedException from the tested method
     * @throws ReflectionException from the initCurrentMock method
     * @throws Zend_Http_Client_Exception from the tested method
     */
    public function saveUpdateOrder_ifHookDoesntFindOrderAndLogDisabled_willNotifyBugsnagWithoutWebhookLogging()
    {
        $this->initCurrentMock([
            'fetchTransactionInfo',
            'getDataFromDisplayID',
            'createOrder',
            'resetOrderState',
            'dispatchPostCheckoutEvents',
            'updateOrderPayment',
            'getExistingOrder',
            'voidTransactionOnBolt'
        ]);

        $transaction = json_decode(
            json_encode(
                [
                    'order' => [
                        'cart' => [
                            'order_reference' => self::QUOTE_ID,
                            'display_id'      => self::DISPLAY_ID,
                            'total_amount'    => [
                                'amount' => 100,
                            ],
                            'metadata'        => [
                                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
                            ],
                        ]
                    ],
                    'status' => Payment::TRANSACTION_AUTHORIZED,
                    'id' => '111'
                ]
            )
        );

        $this->currentMock->expects(self::once())->method('fetchTransactionInfo')
            ->with(self::REFERENCE_ID, self::STORE_ID)->willReturn($transaction);

        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)->willReturn(null);

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function ($callback) {
                $report = $this->createMock(Report::class);
                $report->expects(self::once())->method('setMetaData')->with(
                    [
                        'ORDER' => [
                            'incrementId'     => self::INCREMENT_ID,
                            'quoteId'         => self::IMMUTABLE_QUOTE_ID,
                            'Magento StoreId' => self::STORE_ID
                        ]
                    ]
                );
                $callback($report);
            }
        );

        $this->currentMock->expects(self::once())
            ->method('getExistingOrder')
            ->with(self::INCREMENT_ID)
            ->willReturn(null);

        $this->orderMock->expects(self::never())->method('getId');
        $this->orderMock->expects(self::never())->method('getState');

        Hook::$fromBolt = true;

        $this->currentMock->expects(self::once())
            ->method('voidTransactionOnBolt')
            ->with($transaction->id, self::STORE_ID)
            ->willReturnSelf();

        $this->featureSwitches->expects(self::once())->method('isLogMissingQuoteFailedHooksEnabled')->willReturn(false);

        $this->bugsnag->expects(self::once())->method('notifyException')
            ->with(new LocalizedException(__('Unknown quote id: %1', self::IMMUTABLE_QUOTE_ID)))
            ->willReturnSelf();

        $this->currentMock->saveUpdateOrder(
            self::REFERENCE_ID, self::STORE_ID, self::BOLT_TRACE_ID
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
            ->with(self::IMMUTABLE_QUOTE_ID)->willReturn(null);

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function ($callback) {
                $report = $this->createMock(Report::class);
                $report->expects(self::once())->method('setMetaData')->with(
                    [
                        'ORDER' => [
                            'incrementId'     => self::INCREMENT_ID,
                            'quoteId'         => self::IMMUTABLE_QUOTE_ID,
                            'Magento StoreId' => self::STORE_ID
                        ]
                    ]
                );
                $callback($report);
            }
        );

        $this->currentMock->expects(self::once())->method('getExistingOrder')
            ->with(self::INCREMENT_ID)->willReturn(null);
        $this->orderMock->expects(self::never())->method('getId')->willReturn(self::ORDER_ID);
        $this->orderMock->expects(self::never())->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown quote id: ' . self::IMMUTABLE_QUOTE_ID);

        $this->currentMock->saveUpdateOrder(
            self::REFERENCE_ID,
            self::STORE_ID,
            self::BOLT_TRACE_ID
        );
    }

    private function saveUpdateOrder_noOrder_noQuote_SetUp()
    {
        $this->saveUpdateOrderSetUp();

        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)->willReturn(null);

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function ($callback) {
                $report = $this->createMock(Report::class);
                $report->expects(self::once())->method('setMetaData')->with(
                    [
                        'ORDER' => [
                            'incrementId'     => self::INCREMENT_ID,
                            'quoteId'         => self::IMMUTABLE_QUOTE_ID,
                            'Magento StoreId' => self::STORE_ID
                        ]
                    ]
                );
                $callback($report);
            }
        );

        $this->currentMock->expects(self::once())->method('getExistingOrder')
            ->with(self::INCREMENT_ID)->willReturn(null);
        $this->orderMock->expects(self::never())->method('getId')->willReturn(self::ORDER_ID);
        $this->orderMock->expects(self::never())->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);
    }

    /**
     * @test
     *
     * @covers ::saveUpdateOrder
     */
    public function saveUpdateOrder_noOrderNoQuote_fromWebhook_recordAttempt_throwException()
    {
        Hook::$fromBolt = true;
        $this->saveUpdateOrder_noOrder_noQuote_SetUp();

        $this->webhookLogFactory->expects(self::once())->method('create')->willReturnSelf();
        $this->webhookLogCollectionFactory->expects(self::once())->method('create')->willReturnSelf();
        $this->webhookLogCollectionFactory->expects(self::once())->method('getWebhookLogByTransactionId')->willReturn(false);
        $this->webhookLogFactory->expects(self::once())->method('recordAttempt')->willReturnSelf();
        $this->featureSwitches->expects(self::once())->method('isLogMissingQuoteFailedHooksEnabled')->willReturn(true);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown quote id: ' . self::IMMUTABLE_QUOTE_ID);
        $this->currentMock->saveUpdateOrder(
            self::REFERENCE_ID,
            self::STORE_ID,
            self::BOLT_TRACE_ID
        );
    }

    /**
     * @test
     *
     * @covers ::saveUpdateOrder
     */
    public function saveUpdateOrder_noOrderNoQuote_fromWebhook_incrementAttemptCount_throwException()
    {
        Hook::$fromBolt = true;
        $this->saveUpdateOrder_noOrder_noQuote_SetUp();

        $this->webhookLogCollectionFactory->expects(self::once())->method('create')->willReturnSelf();
        $this->webhookLogCollectionFactory->expects(self::once())->method('getWebhookLogByTransactionId')->willReturn($this->webhookLogFactory);
        $this->webhookLogFactory->expects(self::once())->method('getNumberOfMissingQuoteFailedHooks')->willReturn(4);
        $this->webhookLogFactory->expects(self::once())->method('incrementAttemptCount')->willReturnSelf();
        $this->featureSwitches->expects(self::once())->method('isLogMissingQuoteFailedHooksEnabled')->willReturn(true);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown quote id: ' . self::IMMUTABLE_QUOTE_ID);
        $this->currentMock->saveUpdateOrder(
            self::REFERENCE_ID,
            self::STORE_ID,
            self::BOLT_TRACE_ID
        );
    }

    /**
     * @test
     *
     * @covers ::saveUpdateOrder
     */
    public function saveUpdateOrder_noOrderNoQuote_fromWebhook_returnThis()
    {
        Hook::$fromBolt = true;
        $this->saveUpdateOrder_noOrder_noQuote_SetUp();

        $this->webhookLogFactory->expects(self::never())->method('create')->willReturnSelf();
        $this->webhookLogCollectionFactory->expects(self::once())->method('create')->willReturnSelf();
        $this->webhookLogCollectionFactory->expects(self::once())->method('getWebhookLogByTransactionId')->willReturn($this->webhookLogFactory);
        $this->webhookLogFactory->expects(self::once())->method('getNumberOfMissingQuoteFailedHooks')->willReturn(11);
        $this->webhookLogFactory->expects(self::never())->method('incrementAttemptCount')->willReturnSelf();
        $this->featureSwitches->expects(self::once())->method('isLogMissingQuoteFailedHooksEnabled')->willReturn(true);

        $this->webhookLogFactory->expects(self::never())->method('recordAttempt')->willReturnSelf();

        $this->currentMock->saveUpdateOrder(
            self::REFERENCE_ID,
            self::STORE_ID,
            self::BOLT_TRACE_ID
        );
    }

    /**
     * @test
     *
     * @covers ::saveUpdateOrder
     */
    public function saveUpdateOrder_noOrderNoQuote_fromWebhook_isAllowingLogMissingQuoteFailedHooksDisabled_returnThis()
    {
        Hook::$fromBolt = true;
        $this->saveUpdateOrder_noOrder_noQuote_SetUp();
        $this->featureSwitches->expects(self::once())->method('isLogMissingQuoteFailedHooksEnabled')->willReturn(false);

        $this->webhookLogFactory->expects(self::never())->method('create')->willReturnSelf();

        $this->currentMock->saveUpdateOrder(
            self::REFERENCE_ID,
            self::STORE_ID,
            self::BOLT_TRACE_ID
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

        $immutablequoteMock = $this->createMock(Quote::class);

        $this->cartHelper->expects(self::exactly(2))->method('getQuoteById')
            ->willReturnMap([
                [self::IMMUTABLE_QUOTE_ID, $immutablequoteMock],
                [self::QUOTE_ID, $this->quoteMock]
            ]);

        $this->bugsnag->expects(self::never())->method('registerCallback');

        $this->currentMock->expects(self::once())->method('getExistingOrder')
            ->with(self::INCREMENT_ID)->willReturn(null);

        $this->currentMock->expects(self::once())->method('createOrder')
            ->with($immutablequoteMock, $transaction, self::BOLT_TRACE_ID)->willReturn($this->orderMock);

        $this->orderMock->expects(self::never())->method('getId')->willReturn(self::ORDER_ID);
        $this->orderMock->expects(self::atLeastOnce())->method('getGrandTotal')->willReturn(1);
        $this->currentMock->expects(self::once())->method('updateOrderPayment')
            ->with($this->orderMock, $transaction, null, $type = 'pending')->willReturn($this->orderMock);

        $this->currentMock->saveUpdateOrder(
            self::REFERENCE_ID,
            self::STORE_ID,
            self::BOLT_TRACE_ID,
            $type
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
                'getBoltDispatched',
                'setBoltDispatched',
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
        list($orderMock, $quoteMock) = $this->dispatchPostCheckoutEventsSetUp();
        $quoteMock->expects(self::once())->method('getBoltDispatched')->willReturn(false);
        $quoteMock->expects(self::once())->method('setInventoryProcessed')->with(true);

        $orderMock->expects(self::once())->method('getAppliedRuleIds')->willReturn(null);
        $orderMock->expects(self::once())->method('setAppliedRuleIds')->with('');

        $this->logHelper->expects(self::once())->method('addInfoLog')->with('[-= dispatchPostCheckoutEvents =-]');
        $this->eventManager->expects(self::once())->method('dispatch')
            ->with(
                'checkout_submit_all_after',
                [
                    'order' => $orderMock,
                    'quote' => $quoteMock
                ]
            );

        $quoteMock->expects(self::once())->method('setBoltDispatched')->with(true);
        $this->cartHelper->expects(self::once())->method('quoteResourceSave')->with($quoteMock);

        $this->currentMock->dispatchPostCheckoutEvents($orderMock, $quoteMock);
    }

    /**
     * @test
     * that dispatchPostCheckoutEvents returns null when events were already dispatched
     *
     * @covers ::dispatchPostCheckoutEvents
     */
    public function dispatchPostCheckoutEvents_whenAlreadyDispatched_returnsNull()
    {
        list($orderMock, $quoteMock) = $this->dispatchPostCheckoutEventsSetUp();
        $quoteMock->expects(self::once())->method('getBoltDispatched')->willReturn(true);

        $this->assertNull($this->currentMock->dispatchPostCheckoutEvents($orderMock, $quoteMock));
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
        $this->skipTestInUnitTestsFlow();
        $quote = TestUtils::createQuote();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        self::assertFalse($boltHelperOrder->processExistingOrder($quote, new stdClass()));
    }

    /**
     * @test
     *
     * @covers ::processExistingOrder
     *
     * @throws Exception
     */
    public function processExistingOrder_withCanceledOrder_throwsException() {
        $this->quoteMock->expects(self::exactly(2))->method('getId')
            ->willReturn(self::QUOTE_ID);
        $this->currentMock->expects(self::once())->method('getExistingOrder')
            ->with(null, self::QUOTE_ID)->willReturn($this->orderMock);
        $this->orderMock->expects(self::once())->method('isCanceled')->willReturn(true);
        $this->orderMock->method('getIncrementId')->willReturn(self::INCREMENT_ID);
        $this->featureSwitches->method('isCancelFailedPaymentOrderInsteadOfDeleting')->willReturn(false);

        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Order has been canceled due to the previously declined payment. Quote ID: %s Order Increment ID %s',
                self::QUOTE_ID,
                self::INCREMENT_ID
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
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);

        $quote = Bootstrap::getObjectManager()->create(Quote::class);

        $quote->setQuoteCurrencyCode("USD");
        $quote->save();

        $transaction = new stdClass();
        $transaction->order = new \stdClass();
        $transaction->order->cart = new \stdClass();
        $transaction->order->cart->metadata = new \stdClass();
        $transaction->order->cart->metadata->immutable_quote_id = $quote->getId();

        $order = TestUtils::createDumpyOrder(
            [
                'quote_id' => $quote->getId()
            ]
        );

        self::assertFalse($boltHelperOrder->processExistingOrder($quote, $transaction));
        TestUtils::cleanupSharedFixtures([$order]);
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
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);

        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $quote->setQuoteCurrencyCode("USD");
        $quote->save();

        $transaction = new stdClass();
        $transaction->order = new \stdClass();
        $transaction->order->cart = new \stdClass();
        $transaction->order->cart->total_amount = new \stdClass();
        $transaction->order->cart->total_amount->amount = 10000;
        $transaction->order->cart->tax_amount = new \stdClass();
        $transaction->order->cart->tax_amount->amount = 0;
        $transaction->order->cart->shipping_amount = new \stdClass();
        $transaction->order->cart->shipping_amount->amount = 0;

        $order = TestUtils::createDumpyOrder(
            [
                'quote_id' => $quote->getId(),
                'state' => OrderModel::STATE_PROCESSING
            ]
        );

        self::assertEquals(
            $boltHelperOrder->processExistingOrder($quote, $transaction)->getIncrementId(),
            $order->getIncrementId()
        );
        TestUtils::cleanupSharedFixtures([$order]);
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
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);

        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $quote->setQuoteCurrencyCode("USD");
        $quote->save();

        $transaction = new stdClass();
        $transaction->order = new \stdClass();
        $transaction->order->cart = new \stdClass();
        $transaction->order->cart->total_amount = new \stdClass();
        $transaction->order->cart->total_amount->amount = 10001;
        $transaction->order->cart->tax_amount = new \stdClass();
        $transaction->order->cart->tax_amount->amount = 0;
        $transaction->order->cart->shipping_amount = new \stdClass();
        $transaction->order->cart->shipping_amount->amount = 0;

        $order = TestUtils::createDumpyOrder(
            [
                'quote_id' => $quote->getId()
            ]
        );

        self::assertFalse($boltHelperOrder->processExistingOrder($quote, $transaction));
        self::assertFalse(
            $boltHelperOrder->processExistingOrder($this->quoteMock, $transaction)
        );
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * that submitQuote returns order created by {@see \Magento\Quote\Model\QuoteManagement::submit}
     *
     * @covers ::submitQuote
     */
    public function submitQuote_withSuccessfulSubmission_returnsCreatedOrder()
    {
        $this->quoteManagement->expects(static::once())->method('submit')->with($this->quoteMock)
            ->willReturn($this->orderMock);
        static::assertEquals($this->orderMock, $this->currentMock->submitQuote($this->quoteMock));
    }

    /**
     * @test
     * that submitQuote returns order if an exception occurs during {@see \Magento\Quote\Model\QuoteManagement::submit}
     * but an order was successfully created regardless
     *
     * @covers ::submitQuote
     */
    public function submitQuote_withExceptionDuringOrderCreationAndOrderCreated_returnsCreatedOrder()
    {
        $this->initCurrentMock(['getOrderByQuoteId']);
        $this->paymentMock = $this->getMockBuilder(InfoInterface::class)->setMethods(['getMethod'])
            ->getMockForAbstractClass();

        $exception = new Exception('Exception after creating the order');
        $this->quoteManagement->expects(static::once())->method('submit')->with($this->quoteMock)
            ->willThrowException($exception);
        $this->currentMock->expects(static::once())->method('getOrderByQuoteId')->willReturn($this->orderMock);
        $this->orderMock->expects(static::exactly(2))->method('getPayment')->willReturn($this->paymentMock);
        $this->paymentMock->expects(static::once())->method('getMethod')->willReturn(Payment::METHOD_CODE);
        $this->orderMock->expects(self::once())->method('getId')->willReturn(self::ORDER_ID);
        $this->orderMock->expects(self::once())->method('getIncrementId')->willReturn(self::INCREMENT_ID);
        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function ($callback) {
                $report = $this->createMock(Report::class);
                $report->expects(self::once())->method('setMetaData')->with(
                    [
                        'CREATE ORDER' => [
                            'order_id' => self::ORDER_ID,
                            'order_increment_id' => self::INCREMENT_ID,
                        ]
                    ]
                );
                $callback($report);
            }
        );
        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);

        static::assertEquals($this->orderMock, $this->currentMock->submitQuote($this->quoteMock));
    }

    /**
     * @test
     * that submitQuote rethrows an exception thrown when creating order if the order was not succesfully created
     *
     * @covers ::submitQuote
     */
    public function submitQuote_withExceptionDuringOrderCreationAndOrderNotCreated_reThrowsException()
    {
        $this->initCurrentMock(['getOrderByQuoteId']);
        $this->paymentMock = $this->getMockBuilder(InfoInterface::class)->setMethods(['getMethod'])
            ->getMockForAbstractClass();

        $exception = new Exception('Exception after creating the order');
        $this->quoteManagement->expects(static::once())->method('submit')->with($this->quoteMock)
            ->willThrowException($exception);
        $this->currentMock->expects(static::once())->method('getOrderByQuoteId')->willReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($exception->getMessage());

        $this->currentMock->submitQuote($this->quoteMock);
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
        $this->quoteMock->expects(self::atLeastOnce())->method('getId')
            ->willReturn(self::QUOTE_ID);

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function ($callback) {
                $report = $this->createMock(Report::class);
                $report->expects(self::once())->method('setMetaData')->with(
                    [
                        'CREATE ORDER' => [
                            'pre-auth order.create' => true,
                            'parent quote ID'       => self::QUOTE_ID,
                        ]
                    ]
                );
                $callback($report);
            }
        );

        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Quote Submit Error. Parent Quote ID: %s',
                self::QUOTE_ID
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
        $this->orderMock->method('getId')->willReturn(self::ORDER_ID);
        $paymentMock = $this->createMock(OrderPaymentInterface::class);
        $paymentMock->expects(self::any())->method('getMethod')->willReturn(Payment::METHOD_CODE);
        $this->orderMock->expects(self::once())->method('getPayment')->willReturn($paymentMock);
        $this->cartHelper->expects(self::once())->method('getOrderById')->with(self::ORDER_ID)->willReturn($this->orderMock);

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
     * @covers ::processNewOrder
     */
    public function processNewOrder_withNonBoltOrder()
    {
        $transaction = new stdClass();
        $this->quoteManagement->expects(self::once())->method('submit')
            ->with($this->quoteMock)->willReturn($this->orderMock);
        $this->orderMock->method('getId')->willReturn(self::ORDER_ID);
        $this->orderMock->method('getIncrementId')->willReturn(self::INCREMENT_ID);
        $paymentMock = $this->createMock(OrderPaymentInterface::class);
        $paymentMock->expects(self::any())->method('getMethod')->willReturn('paypal');
        $this->orderMock->expects(self::once())->method('getPayment')->willReturn($paymentMock);
        $this->cartHelper->expects(self::once())->method('getOrderById')->with(self::ORDER_ID)->willReturn($this->orderMock);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(sprintf("Payment method assigned to order %s is: paypal", self::INCREMENT_ID));
        $this->currentMock->processNewOrder($this->quoteMock, $transaction);
    }

    /**
     * Setup method for tests covering the cases when transaction has original order entity id (edit order flow)
     *
     * @param int $originalIncrementId
     * @param int $editIncrement
     *
     * @throws ReflectionException if unable to init current mock
     */
    protected function processNewOrder_withOriginalOrderId_SetUp($originalIncrementId, $editIncrement)
    {
        $this->initCurrentMock(['submitQuote', 'orderPostprocess']);
        $originalOrderMock = $this->createPartialMock(
            Order::class,
            [
                'getOriginalIncrementId',
                'getEditIncrement',
                'getId',
                'getIncrementId',
                'setRelationChildId',
                'setRelationChildRealId',
                'save',
                'getEntityId',
            ]
        );
        $transaction = new stdClass();
        @$transaction->order->cart->metadata->original_order_entity_id = CartTest::ORIGINAL_ORDER_ENTITY_ID;
        $this->orderRepository->expects(static::once())->method('get')->with(CartTest::ORIGINAL_ORDER_ENTITY_ID)
            ->willReturn($originalOrderMock);
        $originalOrderMock->expects(static::once())->method('getOriginalIncrementId')->willReturn($originalIncrementId);
        $originalOrderMock->method('getIncrementId')->willReturn(self::INCREMENT_ID);
        $originalOrderMock->method('getId')->willReturn(CartTest::ORIGINAL_ORDER_ENTITY_ID);

        $this->quoteMock = $this->createPartialMock(Quote::class, ['setReservedOrderId']);
        $this->quoteMock->expects(static::once())->method('setReservedOrderId')->with(
            self::INCREMENT_ID . '-' . $editIncrement
        );
        $this->currentMock->expects(static::once())->method('submitQuote')
            ->with(
                $this->quoteMock,
                [
                    'original_increment_id' => self::INCREMENT_ID,
                    'relation_parent_id' => CartTest::ORIGINAL_ORDER_ENTITY_ID,
                    'relation_parent_real_id' => self::INCREMENT_ID,
                    'edit_increment' => $editIncrement,
                    'increment_id' => self::INCREMENT_ID . '-' . $editIncrement,
                ]
            )
            ->willReturn($this->orderMock);

        $this->orderMock->method('getId')->willReturn(self::ORDER_ID);
        $paymentMock = $this->createMock(OrderPaymentInterface::class);
        $paymentMock->expects(self::any())->method('getMethod')->willReturn(Payment::METHOD_CODE);
        $this->orderMock->expects(self::once())->method('getPayment')->willReturn($paymentMock);
        $this->cartHelper->expects(self::once())->method('getOrderById')->with(self::ORDER_ID)->willReturn(
            $this->orderMock
        );

        $this->orderMock->expects(self::once())->method('getIncrementId')->willReturn(self::INCREMENT_ID);

        $this->orderMock->expects(self::once())->method('addStatusHistoryComment')
            ->with('BOLTPAY INFO :: This order was created via Bolt Pre-Auth Webhook');

        $originalOrderMock->expects(static::once())->method('setRelationChildId')->with(self::ORDER_ID);
        $originalOrderMock->expects(static::once())->method('setRelationChildRealId')->with(self::INCREMENT_ID);
        $originalOrderMock->expects(static::once())->method('save');
        $originalOrderMock->expects(static::once())->method('getEntityId')->willReturn(
            CartTest::ORIGINAL_ORDER_ENTITY_ID
        );

        $this->currentMock->expects(self::once())->method('orderPostprocess')
            ->with($this->orderMock, $this->quoteMock, $transaction);
    }

    /**
     * @test
     * that processNewOrder will supply the correct order data to {@see \Magento\Quote\Model\QuoteManagement::submit}
     * in order to support Magento edit order functionality. Original order id is read from Bolt transaction metadata.
     *
     * @covers ::processNewOrder
     */
    public function processNewOrder_withOriginalOrderEntityId_submitsQuoteWithOriginalOrderData()
    {
        $previousEditIncrement = 0;
        $editIncrement = $previousEditIncrement + 1;
        $originalIncrementId = null;
        $this->orderIncrementIdChecker->expects(static::once())->method('isIncrementIdUsed')
            ->with(self::INCREMENT_ID . '-' . $editIncrement)->willReturn(false);
        $transaction = new stdClass();
        @$transaction->order->cart->metadata->original_order_entity_id = CartTest::ORIGINAL_ORDER_ENTITY_ID;

        $this->processNewOrder_withOriginalOrderId_SetUp($originalIncrementId, $editIncrement);
        $this->orderMock->expects(static::once())->method('save');
        $this->orderManagementMock->expects(static::once())->method('cancel')->with(CartTest::ORIGINAL_ORDER_ENTITY_ID);
        self::assertEquals(
            $this->currentMock->processNewOrder($this->quoteMock, $transaction),
            $this->orderMock
        );
    }

    /**
     * @test
     * that processNewOrder will successfully proceed after canceling the previous(edited) order fails
     *
     * @covers ::processNewOrder
     */
    public function processNewOrder_ifUnableToCancelPreviousOrder_proceedsWithExceptionNotify()
    {
        $previousEditIncrement = 0;
        $editIncrement = $previousEditIncrement + 1;
        $originalIncrementId = null;
        $this->orderIncrementIdChecker->expects(static::once())->method('isIncrementIdUsed')
            ->with(self::INCREMENT_ID . '-' . $editIncrement)->willReturn(false);
        $transaction = new stdClass();
        @$transaction->order->cart->metadata->original_order_entity_id = CartTest::ORIGINAL_ORDER_ENTITY_ID;

        $this->processNewOrder_withOriginalOrderId_SetUp($originalIncrementId, $editIncrement);
        $this->orderMock->expects(static::never())->method('save');
        $exception = new LocalizedException(__('We cannot cancel this order.'));
        $this->orderManagementMock->expects(static::once())->method('cancel')
            ->with(CartTest::ORIGINAL_ORDER_ENTITY_ID)
            ->willThrowException($exception);
        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception);
        self::assertEquals(
            $this->currentMock->processNewOrder($this->quoteMock, $transaction),
            $this->orderMock
        );
    }

    /**
     * @test
     * that processNewOrder will recover from expected increment id being taken by incrementing edit number suffix until
     * a free one is found
     *
     * @covers ::processNewOrder
     */
    public function processNewOrder_withOriginalOrderIncrementIdTaken_submitsQuoteWithOriginalOrderData()
    {
        $previousEditIncrement = 0;
        $editIncrement = $previousEditIncrement + 2;
        $originalIncrementId = null;
        $this->orderIncrementIdChecker->expects(static::exactly(2))->method('isIncrementIdUsed')
            ->withConsecutive(
                [self::INCREMENT_ID . '-' . ($previousEditIncrement + 1)],
                [self::INCREMENT_ID . '-' . ($previousEditIncrement + 2)]
            )->willReturnOnConsecutiveCalls(true, false);
        $transaction = new stdClass();
        @$transaction->order->cart->metadata->original_order_entity_id = CartTest::ORIGINAL_ORDER_ENTITY_ID;

        $this->processNewOrder_withOriginalOrderId_SetUp($originalIncrementId, $editIncrement);
        $this->orderMock->expects(static::once())->method('save');
        $this->orderManagementMock->expects(static::once())->method('cancel')->with(CartTest::ORIGINAL_ORDER_ENTITY_ID);
        self::assertEquals(
            $this->currentMock->processNewOrder($this->quoteMock, $transaction),
            $this->orderMock
        );
    }

    /**
     * @test
     * that processNewOrder will only notify exception to Bugsnag if the original order doesn't exist for editing
     *
     * @covers ::processNewOrder
     */
    public function processNewOrder_withOriginalOrderNotFound_createsOrderWithoutEditOrderData()
    {
        $previousEditIncrement = 0;
        $editIncrement = $previousEditIncrement + 1;
        $originalIncrementId = null;
        $this->initCurrentMock(['submitQuote', 'orderPostprocess']);
        $originalOrderMock = $this->createPartialMock(
            Order::class,
            [
                'getOriginalIncrementId',
                'getEditIncrement',
                'getId',
                'getIncrementId',
                'setRelationChildId',
                'setRelationChildRealId',
                'save',
                'getEntityId',
            ]
        );
        $transaction = new stdClass();
        @$transaction->order->cart->metadata->original_order_entity_id = CartTest::ORIGINAL_ORDER_ENTITY_ID;
        $exception = new NoSuchEntityException(__("The entity that was requested doesn't exist. Verify the entity and try again."));
        $this->orderRepository->expects(static::once())->method('get')->with(CartTest::ORIGINAL_ORDER_ENTITY_ID)
            ->willThrowException($exception);
        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception);
        $originalOrderMock->expects(static::never())->method('getOriginalIncrementId')->willReturn($originalIncrementId);
        $originalOrderMock->method('getIncrementId')->willReturn(self::INCREMENT_ID);
        $originalOrderMock->method('getId')->willReturn(CartTest::ORIGINAL_ORDER_ENTITY_ID);
        $this->orderIncrementIdChecker->expects(static::never())->method('isIncrementIdUsed')
            ->with(self::INCREMENT_ID . '-' . $editIncrement)->willReturn(false);

        $this->quoteMock = $this->createPartialMock(Quote::class, ['setReservedOrderId']);
        $this->quoteMock->expects(static::never())->method('setReservedOrderId')->with(self::INCREMENT_ID . '-' . $editIncrement);
        $this->currentMock->expects(static::once())->method('submitQuote')
            ->with($this->quoteMock, [])
            ->willReturn($this->orderMock);

        $this->orderMock->method('getId')->willReturn(self::ORDER_ID);
        $paymentMock = $this->createMock(OrderPaymentInterface::class);
        $paymentMock->expects(self::any())->method('getMethod')->willReturn(Payment::METHOD_CODE);
        $this->orderMock->expects(self::once())->method('getPayment')->willReturn($paymentMock);
        $this->cartHelper->expects(self::once())->method('getOrderById')->with(self::ORDER_ID)->willReturn($this->orderMock);

        $this->orderMock->expects(self::never())->method('getIncrementId')->willReturn(self::INCREMENT_ID);

        $this->orderMock->expects(self::once())->method('addStatusHistoryComment')
            ->with('BOLTPAY INFO :: This order was created via Bolt Pre-Auth Webhook');

        $originalOrderMock->expects(static::never())->method('setRelationChildId')->with(self::ORDER_ID);
        $originalOrderMock->expects(static::never())->method('setRelationChildRealId')->with(self::INCREMENT_ID);
        $originalOrderMock->expects(static::never())->method('save');
        $this->orderManagementMock->expects(static::never())->method('cancel')->with(CartTest::ORIGINAL_ORDER_ENTITY_ID);
        $originalOrderMock->expects(static::never())->method('getEntityId')->willReturn(CartTest::ORIGINAL_ORDER_ENTITY_ID);
        $this->orderMock->expects(static::never())->method('save');

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
        $this->configHelper->expects(self::once())->method('getPriceFaultTolerance')->willReturn(1);
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
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder();
        $orderId = $order->getId();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        TestHelper::invokeMethod($boltHelperOrder, 'deleteOrder', [$order]);
        self::assertFalse(TestUtils::getOrderById($orderId));
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
        $this->skipTestInUnitTestsFlow();

        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Order Cancelation Error. Order does not exist. Order #: %s Immutable Quote ID: %s',
                self::INCREMENT_ID,
                self::IMMUTABLE_QUOTE_ID
            )
        );
        $this->expectExceptionCode(CreateOrder::E_BOLT_GENERAL_ERROR);

        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $boltHelperOrder->tryDeclinedPaymentCancelation(self::INCREMENT_ID, self::IMMUTABLE_QUOTE_ID);
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
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);

        $order = TestUtils::createDumpyOrder();
        $incrementId = $order->getIncrementId();

        self::assertTrue($boltHelperOrder->tryDeclinedPaymentCancelation($incrementId, self::IMMUTABLE_QUOTE_ID));
        TestUtils::cleanupSharedFixtures([$order]);
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
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);

        $order = TestUtils::createDumpyOrder(
            ['state' => Order::STATE_CANCELED]
        );
        $incrementId = $order->getIncrementId();
        self::assertTrue($boltHelperOrder->tryDeclinedPaymentCancelation($incrementId, self::IMMUTABLE_QUOTE_ID));
        TestUtils::cleanupSharedFixtures([$order]);
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
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);

        $order = TestUtils::createDumpyOrder(
            ['state' => Order::STATE_COMPLETE]
        );
        $incrementId = $order->getIncrementId();
        self::assertFalse($boltHelperOrder->tryDeclinedPaymentCancelation($incrementId, self::IMMUTABLE_QUOTE_ID));
        TestUtils::cleanupSharedFixtures([$order]);
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
        $this->skipTestInUnitTestsFlow();
        $this->bugsnag->expects(self::once())->method('notifyError');
        $this->currentMock->deleteOrderByIncrementId(self::INCREMENT_ID, self::IMMUTABLE_QUOTE_ID);
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
        $this->currentMock->deleteOrderByIncrementId(self::INCREMENT_ID, self::IMMUTABLE_QUOTE_ID);
    }

    /**
     * @test
     *
     * @covers ::deleteOrderByIncrementId
     *
     * @throws Exception
     */
    public function deleteOrderByIncrementId_ifParentQuoteIdIsNotEqualToImmutableQuoteId_reactivateSessionQuote()
    {
        $this->skipTestInUnitTestsFlow();

        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $quote->setQuoteCurrencyCode("USD");
        $quote->save();

        $order = TestUtils::createDumpyOrder(['quote_id' => $quote->getId()]);
        $incrementId = $order->getIncrementId();

        Bootstrap::getObjectManager()->create(\Bolt\Boltpay\Helper\Order::class)->deleteOrderByIncrementId($incrementId, self::IMMUTABLE_QUOTE_ID);
        $quote = TestUtils::getQuoteById($quote->getId());

        self::assertEquals('1', $quote->getData('is_active'));
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * @throws LocalizedException
     */
    public function deleteOrderByIncrementId_ifBoltCheckoutTypeIsComplete_changesCheckoutTypeToPPC()
    {
        $this->skipTestInUnitTestsFlow();

        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $quote->setBoltCheckoutType(CartHelper::BOLT_CHECKOUT_TYPE_PPC_COMPLETE);
        $quote->setQuoteCurrencyCode("USD");
        $quote->save();

        $order = TestUtils::createDumpyOrder(['quote_id' => $quote->getId()]);
        $incrementId = $order->getIncrementId();

        Bootstrap::getObjectManager()->create(\Bolt\Boltpay\Helper\Order::class)->deleteOrderByIncrementId($incrementId, $quote->getId());
        $quote = TestUtils::getQuoteById($quote->getId());

        self::assertEquals(CartHelper::BOLT_CHECKOUT_TYPE_PPC, $quote->getBoltCheckoutType());
        TestUtils::cleanupSharedFixtures([$order]);
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
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder();

        $incrementId = $order->getIncrementId();
        $orderHelper = Bootstrap::getObjectManager()->create(\Bolt\Boltpay\Helper\Order::class);
        static::assertEquals(
            $order->getId(),
            $orderHelper->getExistingOrder($incrementId)->getId()
        );
        TestUtils::cleanupSharedFixtures([$order]);
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
        $this->skipTestInUnitTestsFlow();
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        $orderHelper = Bootstrap::getObjectManager()->create(OrderHelper::class);
        TestHelper::invokeMethod($orderHelper, 'quoteAfterChange', [$quote]);
        self::assertNotNull(TestUtils::getQuoteById($quoteId)->getUpdatedAt());
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
                        'last4' => self::CREDIT_CARD_LAST_FOUR,
                        'network' => self::CREDIT_CARD_NETWORK,
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
        $quoteMockMethodsList = ['getBoltParentQuoteId', 'getPayment', 'setPaymentMethod', 'getId'];
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
            $immutableQuote->expects(self::once())->method('getBoltParentQuoteId')->willReturn(self::QUOTE_ID);
            $parentQuote = $this->createPartialMock(Quote::class, $quoteMockMethodsList);
            $parentQuote->expects(self::once())->method('getId')->willReturn(self::QUOTE_ID);
            $bugsnagParentQuoteId = self::QUOTE_ID;

            $this->cartHelper->expects(self::once())->method('getQuoteById')->with(self::QUOTE_ID)
                ->willReturn($parentQuote);
            $this->cartHelper->expects(static::once())->method('replicateQuoteData')
                ->willReturn($immutableQuote, $parentQuote);
        }

        $this->currentMock->expects(self::exactly(4))->method('quoteAfterChange')->with($parentQuote);

        $this->sessionHelper->expects(static::once())->method('loadSession')->with($parentQuote);
        $this->currentMock->expects(self::once())->method('setShippingAddress')->with($parentQuote, $transaction);
        $this->currentMock->expects(self::once())->method('setBillingAddress')->with($parentQuote, $transaction);
        $this->currentMock->expects(self::once())->method('setShippingMethod')->with($parentQuote);

        $parentQuote->expects(self::once())->method('setPaymentMethod')->with(Payment::METHOD_CODE);

        $quotePayment = $this->createMock(Quote\Payment::class);

        $parentQuote->expects(self::atLeastOnce())->method('getPayment')->willReturn($quotePayment);

        $quotePayment->expects(self::once())->method('importData')->with(['method' => Payment::METHOD_CODE])
            ->willReturnSelf();

        $quotePayment->expects(self::once())->method('save');

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function ($callback) use ($bugsnagParentQuoteId) {
                $report = $this->createMock(Report::class);
                $report->expects(self::once())->method('setMetaData')->with(
                    [
                        'CREATE ORDER' => [
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
        $this->skipTestInUnitTestsFlow();
        $userNote = 'Test note';
        $order = TestUtils::createDumpyOrder();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $boltHelperOrder->setOrderUserNote($order, $userNote);
        self::assertEquals($userNote, $order->getStatusHistories()[0]->getComment());
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     *
     * @covers ::formatReferenceUrl
     */
    public function formatReferenceUrl()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        static::assertEquals(
            sprintf(
                '<a href="https://merchant-sandbox.bolt.com/transaction/%1$s">%1$s</a>',
                self::REFERENCE_ID
            ),
            $boltHelperOrder->formatReferenceUrl(self::REFERENCE_ID)
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
        $itemType,
        $itemTypeAdditionalInformation,
        $expectedResult
    ) {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $payment = Bootstrap::getObjectManager()->create(Payment::class);
        $payment->setAdditionalInformation(
            [$itemType => $itemTypeAdditionalInformation]
        );

        static::assertEquals(
            $expectedResult,
            TestHelper::invokeMethod($boltHelperOrder, 'getProcessedItems', [$payment, $itemType])
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
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $payment = Bootstrap::getObjectManager()->create(Payment::class);
        $payment->setAdditionalInformation(
            ['refunds' => 'refund1,refund2']
        );
        static::assertEquals(
            [
                'refund1',
                'refund2',
            ],
            TestHelper::invokeMethod(
                $boltHelperOrder,
                'getProcessedRefunds',
                [$payment]
            )
        );
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     * @covers ::getProcessedCaptures
     */
    public function getTransactionState_CreditCardAuthorize()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $payment = Bootstrap::getObjectManager()->create(Payment::class);

        $payment->setAdditionalInformation(
            [
                'transaction_state' => OrderHelper::TS_PENDING,
                'transaction_reference' => '000123',
                'real_transaction_id' => self::TRANSACTION_ID,
                'captures' => null,
                'processor' => self::PROCESSOR_VANTIV,
            ]
        );


        $transactionMock = (object)([
            'type'     => OrderHelper::TT_PAYMENT,
            'status'   => "authorized",
            'captures' => [],
        ]);
        $state = $boltHelperOrder->getTransactionState($transactionMock, $payment, null);
        static::assertEquals(OrderHelper::TS_AUTHORIZED, $state);
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     */
    public function getTransactionState_PaypalCompleted()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $payment = Bootstrap::getObjectManager()->create(Payment::class);
        $payment->setAdditionalInformation(
            [
                'transaction_state' => OrderHelper::TS_PENDING,
                'transaction_reference' => '000123',
                'real_transaction_id' => self::TRANSACTION_ID,
                'captures' => null,
                'processor' => self::PROCESSOR_PAYPAL,
            ]
        );

        $transactionMock = (object)([
            'type'     => OrderHelper::TT_PAYPAL_PAYMENT,
            'status'   => "completed",
            'captures' => [],
        ]);
        $state = $boltHelperOrder->getTransactionState($transactionMock, $payment, null);
        static::assertEquals("cc_payment:completed", $state);
    }

    /**
     * @test
     * @covers ::getTransactionState
     */
    public function getTransactionState_APMInitialAuthorized()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        list($paymentMock, $transaction) = $this->getTransactionStateSetUp(
            null,
            OrderHelper::TT_APM_PAYMENT,
            'authorized',
            []
        );
        static::assertEquals(
            $boltHelperOrder->getTransactionState($transaction, $paymentMock, null),
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
        $payment = Bootstrap::getObjectManager()->create(Payment::class);
        $payment->setAdditionalInformation(
            [
                'transaction_state' => $prevTransactionState,
                'transaction_reference' => null,
                'real_transaction_id' => null,
                'captures' => null,
                'processor' => self::PROCESSOR_VANTIV,
            ]
        );

        $transaction = (object)([
            'type'     => $transactionType,
            'status'   => $transactionStatus,
            'captures' => $captures,
        ]);
        return [$payment, $transaction];
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     */
    public function getTransactionState_TSCreditCompleted()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        list($payment, $transaction) = $this->getTransactionStateSetUp(
            null,
            OrderHelper::TT_PAYPAL_REFUND,
            "completed"
        );
        static::assertEquals(
            OrderHelper::TS_CREDIT_COMPLETED,
            $boltHelperOrder->getTransactionState($transaction, $payment)
        );
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     */
    public function getTransactionState_TSAuthorized()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        list($payment, $transaction) = $this->getTransactionStateSetUp(
            OrderHelper::TS_PENDING,
            OrderHelper::TT_PAYMENT,
            'authorized',
            true
        );
        static::assertEquals(
            OrderHelper::TS_AUTHORIZED,
            $boltHelperOrder->getTransactionState($transaction, $payment)
        );
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     */
    public function getTransactionState_TSAuthorizedFromPending()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        list($payment, $transaction) = $this->getTransactionStateSetUp(
            OrderHelper::TS_PENDING,
            OrderHelper::TT_PAYMENT,
            'completed',
            [1, 2]
        );
        static::assertEquals(
            OrderHelper::TS_AUTHORIZED,
            $boltHelperOrder->getTransactionState($transaction, $payment)
        );
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     */
    public function getTransactionState_TSCaptured()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        list($payment, $transaction) = $this->getTransactionStateSetUp(
            OrderHelper::TS_AUTHORIZED,
            OrderHelper::TT_PAYMENT,
            'completed',
            [1, 2]
        );
        static::assertEquals(
            OrderHelper::TS_CAPTURED,
            $boltHelperOrder->getTransactionState($transaction, $payment)
        );
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     */
    public function getTransactionState_TSCapturedFromAuthorized()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        list($payment, $transaction) = $this->getTransactionStateSetUp(
            OrderHelper::TS_AUTHORIZED,
            OrderHelper::TT_PAYMENT,
            'authorized',
            [1]
        );
        static::assertEquals(
            OrderHelper::TS_CAPTURED,
            $boltHelperOrder->getTransactionState($transaction, $payment)
        );
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     */
    public function getTransactionState_TSCapturedFromACompleted()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        list($payment, $transaction) = $this->getTransactionStateSetUp(
            OrderHelper::TS_CREDIT_COMPLETED,
            OrderHelper::TT_PAYMENT,
            'authorized',
            []
        );
        static::assertEquals(
            OrderHelper::TS_CAPTURED,
            $boltHelperOrder->getTransactionState($transaction, $payment)
        );
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     */
    public function getTransactionState_TSPartialVoided()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        list($paymentMock, $transaction) = $this->getTransactionStateSetUp(
            OrderHelper::TS_CAPTURED,
            OrderHelper::TT_PAYMENT,
            'completed',
            [1]
        );
        static::assertEquals(
            OrderHelper::TS_PARTIAL_VOIDED,
            $boltHelperOrder->getTransactionState($transaction, $paymentMock, Transaction::TYPE_VOID)
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
                            'display_id'      => self::INCREMENT_ID,
                            'metadata'        => [
                                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
                            ],
                        ],
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
                                'amount' => 1000,
                            ],
                            'order_reference' => self::QUOTE_ID,
                            'display_id'      => self::INCREMENT_ID,
                            'metadata'        => [
                                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
                            ],
                        ],
                    ],
                    'reference' => self::REFERENCE_ID
                ]
            )
        );

        $cartData = [
            'total_amount' => 500
        ];

        $this->cartHelper->expects(self::once())->method('getQuoteById')->with(self::IMMUTABLE_QUOTE_ID)
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
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        Hook::$fromBolt = $isHookFromBolt;
        static::assertEquals(
            $orderState,
            TestHelper::invokeMethod($boltHelperOrder, 'getBoltTransactionStatus', [$transactionState])
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
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $order = TestUtils::createDumpyOrder();

        $timestamp = 1609365491.6782;
        $transaction = (object)[
            'date'      => $timestamp * 1000,
            'reference' => self::REFERENCE_ID,
            'id'        => self::TRANSACTION_ID
        ];
        static::assertEquals(
            [
                'Time'           => 'Dec 30, 2020, 1:58:11 PM',
                'Reference'      => '1123123123',
                'Amount'         => '$10.00',
                'Transaction ID' => 'ABCD-1234-XXXX',
            ],
            TestHelper::invokeMethod(
                $boltHelperOrder,
                'formatTransactionData',
                [$order, $transaction, 1000]
            )
        );

        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     *
     * @covers ::setOrderState
     */
    public function setOrderState_holdedOrder()
    {
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $boltHelperOrder->setOrderState($order, Order::STATE_HOLDED);
        self::assertEquals(Order::STATE_HOLDED,$order->getState());
        TestUtils::cleanupSharedFixtures([$order]);
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
        $this->skipTestInUnitTestsFlow();
        $state = Order::STATE_CANCELED;
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $boltHelperOrder->setOrderState($order, $state);
        self::assertEquals(Order::STATE_CANCELED,$order->getState());
        TestUtils::cleanupSharedFixtures([$order]);
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
        $this->skipTestInUnitTestsFlow();
        $state = Order::STATE_PAYMENT_REVIEW;
        $order = TestUtils::createDumpyOrder();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $boltHelperOrder->setOrderState($order, $state);
        self::assertEquals(Order::STATE_PAYMENT_REVIEW,$order->getState());
        TestUtils::cleanupSharedFixtures([$order]);
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
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        TestHelper::invokeMethod($boltHelperOrder, 'cancelOrder', [$order]);
        self::assertEquals(Order::STATE_CANCELED, $order->getState());
        TestUtils::cleanupSharedFixtures([$order]);
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
        $this->skipTestInUnitTestsFlow();
        $orderPayment = Bootstrap::getObjectManager()->create(OrderPayment::class);
        $orderPayment->setMethod(Payment::METHOD_CODE);
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        TestHelper::invokeMethod($boltHelperOrder, 'checkPaymentMethod', [$orderPayment]);
        self::assertEquals(Payment::METHOD_CODE, $orderPayment->getMethod());
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
        $this->skipTestInUnitTestsFlow();
        $orderPayment = Bootstrap::getObjectManager()->create(OrderPayment::class);
        $orderPayment->setMethod('checkmo');
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Payment method assigned to order is: checkmo');
        TestHelper::invokeMethod($boltHelperOrder, 'checkPaymentMethod', [$orderPayment]);
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
     * @param bool   $isCreatingCreditMemoFromWebHookEnabled
     * @param int $orderTotalRefunded
     * @param int $orderGrandTotal
     * @param int $orderTotalPaid
     */
    public function transactionToOrderState($transactionState, $orderState, $isHookFromBolt = false, $isCreatingCreditMemoFromWebHookEnabled = false, $orderTotalRefunded = 0, $orderGrandTotal = 10 , $orderTotalPaid = 10)
    {
        Hook::$fromBolt = $isHookFromBolt;
        $this->featureSwitches->method('isCreatingCreditMemoFromWebHookEnabled')->willReturn($isCreatingCreditMemoFromWebHookEnabled);
        $this->orderMock->method('getTotalRefunded')->willReturn($orderTotalRefunded);
        $this->orderMock->method('getGrandTotal')->willReturn($orderGrandTotal);
        $this->orderMock->method('getTotalPaid')->willReturn($orderTotalPaid);
        static::assertEquals($orderState, $this->currentMock->transactionToOrderState($transactionState, $this->orderMock));
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
            [OrderHelper::TS_CREDIT_COMPLETED, OrderModel::STATE_CLOSED, true, true, 10, 10 ,10],
            [OrderHelper::TS_CREDIT_COMPLETED, OrderModel::STATE_PROCESSING, true, true, 8, 10 ,10],
            [OrderHelper::TS_CREDIT_COMPLETED, OrderModel::STATE_PROCESSING, false, true],
            [OrderHelper::TS_CREDIT_COMPLETED, OrderModel::STATE_HOLDED, true, false],
            [OrderHelper::TS_CREDIT_COMPLETED, OrderModel::STATE_HOLDED, true, false],
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
                    'processor' => self::PROCESSOR_VANTIV,
                    'amount'    => [
                        'amount' => 10
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
                    ],
                    'order' => [
                        'cart' => [
                            'total_amount' => [
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
                ['captures'],
                ['processor']
            )
            ->willReturnOnConsecutiveCalls(
                $prevTransactionState,
                $prevTransactionReference,
                self::TRANSACTION_ID,
                true,
                '',
                self::PROCESSOR_VANTIV
            );

        $this->currentMock->updateOrderPayment($this->orderMock, $transaction);
    }

    /**
     * @test
     *
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_handleCheckboxes()
    {
        list($transaction, $paymentMock) = $this->updateOrderPaymentSetUp(OrderHelper::TS_AUTHORIZED);
        $paymentMock->expects(self::atLeastOnce())->method('getAdditionalInformation')
            ->withConsecutive(['transaction_state'])
            ->willReturnOnConsecutiveCalls('');

        $this->transactionBuilder->expects(self::once())->method('setPayment')->with($paymentMock)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setOrder')->with($this->orderMock)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setTransactionId')->with(self::TRANSACTION_ID . '-auth')
            ->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setAdditionalInformation')->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setFailSafe')->with(true)->willReturnSelf();

        $this->transactionBuilder->expects(self::once())->method('build')
            ->with(TransactionInterface::TYPE_AUTH)->willThrowException(new Exception(''));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('');

        $this->orderMock->expects(self::any())->method('getState')
            ->willReturn('pending_payment');
        $this->checkboxesHandler->expects(self::once())->method('handle')
            ->with($this->orderMock, self::HOOK_PAYLOAD['checkboxes']);

        $this->currentMock->updateOrderPayment($this->orderMock, $transaction, null, null, self::HOOK_PAYLOAD);
    }

    /**
     * @test
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_sameState_withAuthHookAndOrderStatusIsPaymentReview()
    {
        $transactionState = OrderHelper::TS_AUTHORIZED;
        $prevTransactionState = OrderHelper::TS_AUTHORIZED;
        list($transaction, $paymentMock) = $this->updateOrderPaymentSetUp($transactionState);
        $prevTransactionReference = self::REFERENCE_ID;
        $this->orderMock->expects(self::any())->method('getState')
            ->willReturn(OrderModel::STATE_PAYMENT_REVIEW);

        $this->currentMock->expects(self::never())->method('isAnAllowedUpdateFromAdminPanel')
            ->with($this->orderMock, $transactionState)->willReturn(true);
        $paymentMock->expects(self::never())->method('setIsTransactionApproved')->with(true);
        $paymentMock->expects(self::atLeastOnce())->method('getAdditionalInformation')
            ->withConsecutive(
                ['transaction_state'],
                ['transaction_reference'],
                ['real_transaction_id'],
                ['authorized'],
                ['captures'],
                ['refunds'],
                [null]
            )->willReturnOnConsecutiveCalls(
                $prevTransactionState,
                $prevTransactionReference,
                self::TRANSACTION_ID,
                true,
                '',
                0,
                ''
            );

        $this->transactionBuilder->expects(self::once())->method('setPayment')->with($paymentMock)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setOrder')->with($this->orderMock)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setTransactionId')->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setAdditionalInformation')->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setFailSafe')->with(true)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('build')->willReturn($paymentMock);

        $this->currentMock->updateOrderPayment($this->orderMock, $transaction, self::REFERENCE_ID, self::HOOK_TYPE_AUTH);
    }

    /**
     * @test
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_sameState_withPendingHookAndOrderStatusIsPendingPayment()
    {
        $transactionState = OrderHelper::TS_AUTHORIZED;
        $prevTransactionState = OrderHelper::TS_AUTHORIZED;
        list($transaction, $paymentMock) = $this->updateOrderPaymentSetUp($transactionState);
        $prevTransactionReference = self::REFERENCE_ID;
        $this->orderMock->expects(self::any())->method('getState')
            ->willReturn(OrderModel::STATE_PENDING_PAYMENT);

        $this->currentMock->expects(self::never())->method('isAnAllowedUpdateFromAdminPanel')
            ->with($this->orderMock, $transactionState)->willReturn(true);
        $paymentMock->expects(self::never())->method('setIsTransactionApproved')->with(true);
        $paymentMock->expects(self::atLeastOnce())->method('getAdditionalInformation')
            ->withConsecutive(
                ['transaction_state'],
                ['transaction_reference'],
                ['real_transaction_id'],
                ['authorized'],
                ['captures'],
                ['refunds'],
                [null]
            )->willReturnOnConsecutiveCalls(
                $prevTransactionState,
                $prevTransactionReference,
                self::TRANSACTION_ID,
                true,
                '',
                0,
                ''
            );

        $this->transactionBuilder->expects(self::once())->method('setPayment')->with($paymentMock)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setOrder')->with($this->orderMock)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setTransactionId')->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setAdditionalInformation')->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setFailSafe')->with(true)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('build')->willReturn($paymentMock);

        $this->currentMock->updateOrderPayment($this->orderMock, $transaction, self::REFERENCE_ID, self::HOOK_TYPE_PENDING);
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
        Hook::$fromBolt = true;
        /**
         * @var MockObject|OrderPayment $paymentMock
         */
        list($transaction, $paymentMock) = $this->updateOrderPaymentSetUp($transactionState);
        $paymentMock->expects(self::atLeastOnce())->method('getAdditionalInformation')
            ->withConsecutive(['transaction_state'])
            ->willReturnOnConsecutiveCalls('');
        if ($transactionState == OrderHelper::TS_CREDIT_COMPLETED) {
            $this->featureSwitches->method('isCreatingCreditMemoFromWebHookEnabled')->willReturn(true);
            $this->orderMock->method('getOrderCurrencyCode')->willReturn('USD');
            $this->orderMock->method('getTotalRefunded')->willReturn(0);
            $this->orderMock->method('getTotalPaid')->willReturn(1);

            $creditMemoMock = $this->createPartialMock(Creditmemo::class, ['setAutomaticallyCreated','addComment']);
            $this->creditmemoFactory->expects(self::once())->method('createByOrder')->with($this->orderMock, [])->willReturn($creditMemoMock);
            $creditMemoMock->expects(self::once())->method('setAutomaticallyCreated')->with(true)->willReturnSelf();
            $creditMemoMock->expects(self::once())->method('addComment')->with(__('The credit memo has been created automatically.'))->willReturnSelf();
            $this->creditmemoManagement->expects(self::once())->method('refund')->with($creditMemoMock, true)->willReturnSelf();
        }

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
     * Data provider for {@see updateOrderPayment_variousTxStates}
     *
     * @return array containing
     * 1. stubbed transaction state from {@see \Bolt\Boltpay\Helper\Order::getTransactionState}
     * 2. stubbed transaction type from current transaction
     * 3. stubbed transaction id from current transaction
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
     * that updateOrderPayment returns null if
     * 1. Transaction state is {@see \Bolt\Boltpay\Helper\Order::TS_CREDIT_COMPLETED}
     * 2. no state changed between current transaction and previous
     * 3. Order is not already canceled
     *
     * @covers ::updateOrderPayment
     *
     * @throws LocalizedException from the tested method
     * @throws Zend_Http_Client_Exception from the tested method
     * @throws ReflectionException if unable to create one of the required mocks
     */
    public function updateOrderPayment_whenTransactionStateIsCreditCompleted_()
    {
        list($transaction, $paymentMock) = $this->updateOrderPaymentSetUp(OrderHelper::TS_CREDIT_COMPLETED);
        $paymentMock->expects(static::exactly(6))->method('getAdditionalInformation')
            ->withConsecutive(
                ['transaction_state'],
                ['transaction_reference'],
                ['real_transaction_id'],
                ['authorized'],
                ['captures'],
                ['refunds']
            )
            ->willReturnOnConsecutiveCalls(
                "",
                null,
                $transaction->id,
                false,
                null,
                "$transaction->id,example-232-axs,example-dada-3232"
            );

        $this->assertNull(
            $this->currentMock->updateOrderPayment($this->orderMock, $transaction, null, null, self::HOOK_PAYLOAD)
        );
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
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_withIgnoreInvoiceCreationEnabled_DoesNotCreateInvoice()
    {
        /**
         * @var MockObject|OrderPayment $paymentMock
         */
        list($transaction, $paymentMock) = $this->updateOrderPaymentSetUp(OrderHelper::TS_ZERO_AMOUNT);
        $this->featureSwitches->method('isIgnoreHookForInvoiceCreationEnabled')->willReturn(true);
        $invoiceMock = $this->createMock(Invoice::class);
        $paymentMock->expects(self::atLeastOnce())->method('getAdditionalInformation')
            ->willReturnMap([['authorized', true]]);
        $this->currentMock->expects(self::once())->method('fetchTransactionInfo')->willReturn($transaction);

        $this->currentMock->expects(self::never())->method('createOrderInvoice')->willReturn($invoiceMock);

        $this->transactionBuilder->expects(self::once())->method('setPayment')->with($paymentMock)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setOrder')->with($this->orderMock)->willReturnSelf();
        $this->transactionBuilder->expects(self::once())->method('setTransactionId')->willReturnSelf();
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
     * that createOrderInvoice only notifies the exception to Bugsnag if one occurs during invoice email sending
     *
     * @covers ::createOrderInvoice
     *
     * @throws ReflectionException if createOrderInvoice method doesn't exist
     */
    public function createOrderInvoice_ifInvoiceSenderThrowsException_notifiesException()
    {
        $invoice = $this->createPartialMock(
            Invoice::class,
            [
                'setRequestedCaptureCase',
                'setTransactionId',
                'setBaseGrandTotal',
                'setGrandTotal',
                'register',
            ]
        );
        $message = 'Expected exception message';
        $exception = new Exception($message);
        $this->orderMock->expects(static::once())->method('getTotalInvoiced')->willReturn(5);
        $this->orderMock->expects(static::once())->method('getGrandTotal')->willReturn(5);
        $this->orderMock->expects(static::once())->method('getStoreId')->willReturn(self::STORE_ID);
        $this->orderMock->method('addStatusHistoryComment')->willReturn($this->orderMock);
        $this->orderMock->method('setIsCustomerNotified')->willReturn($this->orderMock);
        $this->invoiceService->expects(static::once())->method('prepareInvoice')->willReturn($invoice);
        $this->scopeConfigMock->expects(static::once())->method('isSetFlag')
            ->with(InvoiceEmailIdentity::XML_PATH_EMAIL_ENABLED, ScopeInterface::SCOPE_STORE, self::STORE_ID)
            ->willReturn(true);
        $this->invoiceSender->expects(static::once())->method('send')->willThrowException($exception);
        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        TestHelper::invokeMethod(
            $this->currentMock,
            'createOrderInvoice',
            [$this->orderMock, self::TRANSACTION_ID, 0]
        );
    }

    /**
     * Setup method for {@see \Bolt\Boltpay\Helper\Order::createCreditMemoForHookRequest}
     *
     * @param int $amount to be used as transaction total amount
     *
     * @throws ReflectionException from methods getOrderCurrencyCode, getAllItems
     *
     * @return array containing transaction and order model mock instance
     */
    private function createCreditMemoForHookRequestSetUp($amount)
    {
        $this->initCurrentMock(['validateRefundAmount']);

        $transaction = (object)[
            'order' => (object)[
                'cart' => (object)[
                    'total_amount' => (object)[
                        'amount' => 1000,
                    ],
                ]
            ],
            'amount' => (object)[
                'amount' => $amount,
            ],
        ];

        $orderMock = $this->createPartialMock(OrderModel::class, ['getOrderCurrencyCode', 'getAllItems']);

        return [$transaction, $orderMock];
    }

    /**
     * @test
     * that createCreditMemoForHookRequest creates the credit memo without adjustments when the refund is full (refund amount is equal or greater than order total)
     *
     * @covers ::createCreditMemoForHookRequest
     *
     * @throws ReflectionException from createCreditMemoForHookRequestSetUp method
     * @throws Exception from {@see CurrencyUtils::toMajor} method when unknown currency code is passed
     */
    public function createCreditMemoForHookRequest_ifFullRefund_createsCreditMemoWithAdjustmentPositive()
    {
        list($transaction, $orderMock) = $this->createCreditMemoForHookRequestSetUp(1200);

        $orderMock->expects(self::once())->method('getOrderCurrencyCode')->willReturn(self::CURRENCY_CODE);

        $refundAmount = CurrencyUtils::toMajor($transaction->amount->amount, self::CURRENCY_CODE);
        $this->currentMock->expects(self::once())
            ->method('validateRefundAmount')
            ->with($orderMock, $refundAmount)
            ->willReturnSelf();

        $creditMemoMock = $this->createPartialMock(Creditmemo::class, ['setAutomaticallyCreated','addComment']);

        $this->creditmemoFactory->expects(self::once())->method('createByOrder')
            ->with($orderMock, [])
            ->willReturn($creditMemoMock);
        $creditMemoMock->expects(self::once())->method('setAutomaticallyCreated')->with(true)->willReturnSelf();
        $creditMemoMock->expects(self::once())
            ->method('addComment')
            ->with(__('The credit memo has been created automatically.'))
            ->willReturnSelf();

        $this->creditmemoManagement->expects(self::once())
            ->method('refund')
            ->with($creditMemoMock, true)
            ->willReturnSelf();

        $this->currentMock->createCreditMemoForHookRequest($orderMock, $transaction);
    }

    /**
     * @test
     * that createCreditMemoForHookRequest creates credit memo using adjustments when the refund is partial (refund amount is lower than order total)
     *
     * @covers ::createCreditMemoForHookRequest
     *
     * @throws ReflectionException from createCreditMemoForHookRequestSetUp method
     * @throws Exception from {@see CurrencyUtils::toMajor} method when unknown currency code is passed
     */
    public function createCreditMemoForHookRequest_ifPartialRefund_createsCreditMemoWithAdjustmentPositive()
    {
        list($transaction, $orderMock) = $this->createCreditMemoForHookRequestSetUp(200);

        $orderMock->expects(self::once())->method('getOrderCurrencyCode')->willReturn(self::CURRENCY_CODE);

        $refundAmount = CurrencyUtils::toMajor($transaction->amount->amount, self::CURRENCY_CODE);
        $this->currentMock->expects(self::once())
            ->method('validateRefundAmount')
            ->with($orderMock, $refundAmount)
            ->willReturnSelf();

        $orderItem = $this->createMock(Item::class);

        $orderMock->expects(self::once())->method('getAllItems')->willReturn([$orderItem, $orderItem]);
        $orderItemId = 1;
        $orderItemIdTwo = 2;

        $orderItem->expects(self::exactly(2))
            ->method('getId')
            ->willReturnOnConsecutiveCalls($orderItemId, $orderItemIdTwo);

        $expectedAdjustment = [
            'adjustment_positive' => $refundAmount,
            'shipping_amount' => 0,
            'qtys' => [
                $orderItemId => 0,
                $orderItemIdTwo => 0,
            ]
        ];

        $creditMemoMock = $this->createPartialMock(
            Creditmemo::class,
            ['setAutomaticallyCreated','addComment']
        );

        $this->creditmemoFactory->expects(self::once())
            ->method('createByOrder')
            ->with($orderMock, $expectedAdjustment)
            ->willReturn($creditMemoMock);

        $creditMemoMock->expects(self::once())->method('setAutomaticallyCreated')->with(true)->willReturnSelf();
        $creditMemoMock->expects(self::once())
            ->method('addComment')
            ->with(__('The credit memo has been created automatically.'))
            ->willReturnSelf();

        $this->creditmemoManagement->expects(self::once())
            ->method('refund')
            ->with($creditMemoMock, true)
            ->willReturnSelf();

        $this->currentMock->createCreditMemoForHookRequest($orderMock, $transaction);
    }

    /**
     * @test
     * that createOrderInvoice doesn't send invoice email if it is disabled in configuration
     *
     * @covers ::createOrderInvoice
     *
     * @throws ReflectionException if createOrderInvoice method doesn't exist
     */
    public function createOrderInvoice_ifInvoiceEmailSendingIsDisabled_doesNotSendInvoiceEmail()
    {
        $invoice = $this->createPartialMock(
            Invoice::class,
            [
                'setRequestedCaptureCase',
                'setTransactionId',
                'setBaseGrandTotal',
                'setGrandTotal',
                'register',
            ]
        );
        $message = 'Expected exception message';
        $exception = new Exception($message);
        $this->orderMock->expects(static::once())->method('getTotalInvoiced')->willReturn(5);
        $this->orderMock->expects(static::once())->method('getGrandTotal')->willReturn(5);
        $this->orderMock->expects(static::once())->method('getStoreId')->willReturn(self::STORE_ID);
        $this->orderMock->method('addStatusHistoryComment')->willReturn($this->orderMock);
        $this->orderMock->method('setIsCustomerNotified')->willReturn($this->orderMock);
        $this->invoiceService->expects(static::once())->method('prepareInvoice')->willReturn($invoice);

        $this->scopeConfigMock->expects(static::once())->method('isSetFlag')
            ->with(InvoiceEmailIdentity::XML_PATH_EMAIL_ENABLED, ScopeInterface::SCOPE_STORE, self::STORE_ID)
            ->willReturn(false);
        $this->invoiceSender->expects(static::never())->method('send')->willThrowException($exception);

        $this->bugsnag->expects(self::never())->method('notifyException')->with($exception);
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
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        Hook::$fromBolt = $isHookFromBolt;
        static::assertEquals($expectedResult, $boltHelperOrder->isZeroAmountHook($transactionState));
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
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        Hook::$fromBolt = $isHookFromBolt;
        static::assertEquals(
            $expectedResult,
            TestHelper::invokeMethod($boltHelperOrder, 'isCaptureHookRequest', [$newCapture])
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
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder();
        TestUtils::cleanupSharedFixtures([$order]);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Capture amount is invalid');
        TestHelper::invokeMethod($this->currentMock, 'validateCaptureAmount', [$order, null]);
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
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder([
            'total_invoiced' => 200,
            'grand_total' => 100,
        ]);

        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(sprintf(
            'Capture amount is invalid: captured [%s], grand total [%s]',
            30000,
            10000
        ));
        TestUtils::cleanupSharedFixtures([$order]);
        TestHelper::invokeMethod($boltHelperOrder, 'validateCaptureAmount', [$order, 100]);
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
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder([
            'total_invoiced' => 50,
            'grand_total' => 100,
        ]);

        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        TestHelper::invokeMethod($boltHelperOrder, 'validateCaptureAmount', [$order, 50]);
        TestUtils::cleanupSharedFixtures([$order]);
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
        $isHookFromBolt,
        $txState,
        $orderState,
        $expectedResult
    ) {
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder(['state' => $orderState]);
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        Hook::$fromBolt = $isHookFromBolt;

        static::assertSame(
            $expectedResult,
            TestHelper::invokeMethod(
                $boltHelperOrder,
                'isAnAllowedUpdateFromAdminPanel',
                [$order, $txState]
            )
        );

        TestUtils::cleanupSharedFixtures([$order]);
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
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        static::assertEquals("$1.23", $boltHelperOrder->formatAmountForDisplay($order, 1.23));
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     *
     * @covers ::getStoreIdByQuoteId
     */
    public function getStoreIdByQuoteId_withValidQuote_returnsStoreIdFromQuote()
    {
        $this->skipTestInUnitTestsFlow();
        $quote = TestUtils::createQuote(['store_id' => self::STORE_ID]);
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        static::assertEquals(self::STORE_ID, $boltHelperOrder->getStoreIdByQuoteId($quote->getId()));
    }

    /**
     * @test
     *
     * @covers ::getStoreIdByQuoteId
     */
    public function getStoreIdByQuoteId_withEmptyQuoteId_returnsNull()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        static::assertNull($boltHelperOrder->getStoreIdByQuoteId(''));
    }

    /**
     * @test
     *
     * @covers ::getOrderStoreIdByDisplayId
     */
    public function getOrderStoreIdByDisplayId_withEmptyDisplayId_returnsNull()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        static::assertNull($boltHelperOrder->getOrderStoreIdByDisplayId(''));
    }

    /**
     * @test
     *
     * @covers ::getOrderStoreIdByDisplayId
     */
    public function getOrderStoreIdByDisplayId_withInvalidDisplayId_returnsNull()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        static::assertNull($boltHelperOrder->getOrderStoreIdByDisplayId(' / '));
    }

    /**
     * @test
     *
     * @covers ::getOrderStoreIdByDisplayId
     * @covers ::getDataFromDisplayID
     */
    public function getOrderStoreIdByDisplayId_withValidDisplayId_returnsStoreId()
    {
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder(['store_id'=>self::STORE_ID]);
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        static::assertEquals(self::STORE_ID, $boltHelperOrder->getOrderStoreIdByDisplayId($order->getIncrementId()));
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     *
     * @covers ::setBillingAddress
     */
    public function setBillingAddress()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
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

        $quote = TestUtils::createQuote();

        TestHelper::invokeMethod($boltHelperOrder, 'setBillingAddress', [$quote, $transaction]);
        self::assertEquals($quote->getBillingAddress()->getCity(),'Beverly Hills');
        self::assertEquals($quote->getBillingAddress()->getCountryId(),'US');
        self::assertEquals($quote->getBillingAddress()->getCompany(),'Bolt');
        self::assertEquals($quote->getBillingAddress()->getEmail(),'test@bolt.com');
        self::assertEquals($quote->getBillingAddress()->getTelephone(),'0123456789');
    }

    /**
     * @test
     *
     * @covers ::setShippingAddress
     */
    public function setShippingAddress()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
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

        $quote = TestUtils::createQuote();

        TestHelper::invokeMethod($boltHelperOrder, 'setShippingAddress', [$quote, $transaction]);
        self::assertEquals($quote->getShippingAddress()->getCity(),'Beverly Hills');
        self::assertEquals($quote->getShippingAddress()->getCountryId(),'US');
        self::assertEquals($quote->getShippingAddress()->getCompany(),'Bolt');
        self::assertEquals($quote->getShippingAddress()->getEmail(),'test@bolt.com');
        self::assertEquals($quote->getShippingAddress()->getTelephone(),'0123456789');
    }

    /**
     * @test
     * that setShippingAddress sets Quote shipping address data if:
     * 1. transaction cart has shipping address
     * 2. transaction cart has shipment reference
     * 3. pickup is in the store shipping code
     *
     * @covers ::setShippingAddress
     *
     * @throws ReflectionException if unable to create one of the required mocks
     */
    public function setShippingAddress_forInStorePickups_usesInStorePickupAddress()
    {
        $this->initCurrentMock(['setAddress']);
        $quote = $this->createPartialMock(Quote::class, [
            'getBillingAddress',
            'getShippingAddress',
            'addData',
        ]);

        $addressMock = $this->createMock(Address::class);

        $transaction = (object)[
            'order' => (object)[
                'cart' => (object)[
                    'billing_address' => (object)static::ADDRESS_DATA,
                    'shipments' => [
                        (object)[
                            'shipping_address' => (object)static::ADDRESS_DATA,
                            'reference' => (object)static::REFERENCE_ID,
                        ]
                    ]
                ]
            ]
        ];

        $quote->method('getBillingAddress')->willReturn($addressMock);
        $quote->expects(static::exactly(2))->method('getShippingAddress')
            ->willReturnOnConsecutiveCalls($addressMock,$quote);

        $this->currentMock->expects(static::once())
            ->method('setAddress')
            ->with($addressMock, (object)static::ADDRESS_DATA)
            ->willReturnSelf();

        $referenceShipmentMethod = ConfigHelper::XML_PATH_PICKUP_SHIPPING_METHOD_CODE;

        $this->configHelper->expects(static::once())
            ->method('isPickupInStoreShippingMethodCode')
            ->with($transaction->order->cart->shipments[0]->reference)
            ->willReturn($referenceShipmentMethod);
        $pickupAddressData = [
            'street' => '123 Candy Cane Way',
            'city' => 'Santaville',
            'postcode' => 'H0H 0H0',
            'country_id' => 2,
            'region_id' => 32,
            'region_code' => 'AR',
        ];

        $this->configHelper->expects(static::once())->method('getPickupAddressData')->willReturn($pickupAddressData);

        $quote->expects(static::once())->method('addData')->with($pickupAddressData)->willReturnSelf();
        TestHelper::invokeMethod($this->currentMock, 'setShippingAddress', [$quote, $transaction]);
    }

    /**
     * @test
     * that verifyOrderCreationHookType throws an exception only if the webhook is from Bolt
     * and the current webhook request type is not in {@see \Bolt\Boltpay\Helper\Order::VALID_HOOKS_FOR_ORDER_CREATION}
     *
     * @covers ::verifyOrderCreationHookType
     *
     * @dataProvider verifyOrderCreationHookType_withVariousHookTypesProvider
     *
     * @param bool $hookFromBolt flag whether the current request is coming from bolt
     * @param string $hookType current webhook request type
     * @param bool $expectException flag whether or not to expect exception to be thrown
     *
     * @throws BoltException from tested method
     */
    public function verifyOrderCreationHookType_withVariousHookTypes_throwsExceptionIfHookIsInvalidForCreatingMissingOrder(
        $hookFromBolt,
        $hookType,
        $expectException
    ) {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        Hook::$fromBolt = $hookFromBolt;
        if ($expectException) {
            $this->expectException(BoltException::class);
            $this->expectExceptionCode(\Bolt\Boltpay\Model\Api\CreateOrder::E_BOLT_REJECTED_ORDER);
            $this->expectExceptionMessage(
                sprintf(
                    'Order creation is forbidden from hook of type: %s',
                    $hookType
                )
            );
        }
        $this->assertNull($boltHelperOrder->verifyOrderCreationHookType($hookType));
    }

    /**
     * Data provider for {@see verifyOrderCreationHookType_withVariousHookTypes_throwsExceptionIfHookIsInvalidForCreatingMissingOrder}
     *
     * @return array[] containing hook from bolt flag, hook type and expect the exception flag
     */
    public function verifyOrderCreationHookType_withVariousHookTypesProvider()
    {
        return [
            ['hookFromBolt' => true, 'hookType' => Hook::HT_PENDING, 'expectException' => false],
            ['hookFromBolt' => true, 'hookType' => Hook::HT_PAYMENT, 'expectException' => false],
            ['hookFromBolt' => true, 'hookType' => Hook::HT_VOID, 'expectException' => true],
            ['hookFromBolt' => true, 'hookType' => null, 'expectException' => true],
            ['hookFromBolt' => true, 'hookType' => '', 'expectException' => true],
            ['hookFromBolt' => false, 'hookType' => Hook::HT_PENDING, 'expectException' => false],
            ['hookFromBolt' => false, 'hookType' => Hook::HT_PAYMENT, 'expectException' => false],
            ['hookFromBolt' => false, 'hookType' => Hook::HT_VOID, 'expectException' => true],
            ['hookFromBolt' => false, 'hookType' => null, 'expectException' => false],
            ['hookFromBolt' => false, 'hookType' => '', 'expectException' => true],
        ];
    }

    /**
     * @test
     * that getOrderByQuoteId returns result according to provided quote id
     *
     * @covers ::getOrderByQuoteId
     *
     * @param int $quoteId value
     * @param mixed $order value from {@see \Bolt\Boltpay\Helper\Cart::getOrderByQuoteId}
     * @param mixed $expectedResult of the tested method call
     */
    public function getOrderByQuoteId_withVariousQuoteIds_returnsOrder()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $order = TestUtils::createDumpyOrder(['quote_id' => self::QUOTE_ID]);
        $this->assertEquals($order->getId(), $boltHelperOrder->getOrderByQuoteId(self::QUOTE_ID)->getId());
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * @covers ::voidTransactionOnBolt
     * @param $data
     * @dataProvider voidTransactionOnBolt_dataProvider
     * @throws LocalizedException
     * @throws Zend_Http_Client_Exception
     */
    public function voidTransactionOnBolt($data)
    {
        $this->configHelper->expects(self::once())->method('getApiKey')->willReturnSelf();
        $this->responseFactory->expects(self::once())->method('getResponse')->willReturn(json_decode($data['response']));

        $this->dataObjectFactory->expects(self::once())->method('create')->willReturnSelf();
        $this->dataObjectFactory->expects(self::once())->method('setDynamicApiUrl')->with(ApiHelper::API_VOID_TRANSACTION)->willReturnSelf();
        $this->dataObjectFactory->expects(self::once())->method('setApiKey')->willReturnSelf();

        $this->apiHelper->expects(self::once())->method('buildRequest')
            ->with($this->dataObjectFactory)->willReturn($this->boltRequest);
        $this->apiHelper->expects(self::once())->method('sendRequest')
            ->withAnyParameters()->willReturn($this->responseFactory);

        if ($data['exception']) {
            $this->expectException(LocalizedException::class);
            $this->expectExceptionMessage($data['exception_message']);
        }

        $this->currentMock->voidTransactionOnBolt(self::TRANSACTION_ID, self::STORE_ID);
    }

    public function voidTransactionOnBolt_dataProvider()
    {
        return [
            ['data' => [
                'response' => '{"status": "completed", "reference": "ABCD-1234-XXXX"}',
                'exception_message' => 'Payment void error',
                'exception' => true,
                ]
            ],
            ['data' => [
                'response' => '',
                'exception_message' => 'Bad void response from boltpay',
                'exception' => true,
                ]
            ],
            ['data' => [
                'response' => '{"status": "cancelled", "reference": "ABCD-1234-XXXX"}',
                'exception_message' => 'Bad void response from boltpay',
                'exception' => false,
                ]
            ]
        ];
    }

    /**
     * @test
     * @covers ::saveCustomerCreditCard
     * @param $data
     * @dataProvider providerTestSaveCustomerCreditCard_invalidData
     *
     * @throws ReflectionException from initCurrentMock method
     */
    public function testSaveCustomerCreditCard_invalidData($data)
    {
        $this->initCurrentMock(['fetchTransactionInfo']);
        $this->currentMock->expects(static::once())->method('fetchTransactionInfo')->with(OrderManagementTest::REFERENCE, OrderManagementTest::STORE_ID)
            ->willReturn($data['transaction']);
        $this->quoteMock->expects(static::once())->method('getCustomerId')
            ->willReturn($data['customer_id']);
        $this->cartHelper->expects(static::once())->method('getQuoteById')->withAnyParameters()
            ->willReturn($this->quoteMock);

        $this->customerCreditCardFactory->expects(static::never())->method('create');
        $this->customerCreditCardFactory->expects(static::never())->method('saveCreditCard');

        $result = $this->currentMock->saveCustomerCreditCard(OrderManagementTest::REFERENCE, OrderManagementTest::STORE_ID);
        $this->assertFalse($result);
    }

    public function providerTestSaveCustomerCreditCard_invalidData()
    {
        return [
            ['data' => [
                'transaction' => '',
                'customer_id' => self::CUSTOMER_ID
            ]
            ],
            ['data' => [
                'transaction' => new \stdClass(),
                'customer_id' => ''
            ]
            ],
            ['data' => [
                'transaction' => '',
                'customer_id' => ''
            ]
            ],
        ];
    }

    private function mockTransactionData() {
        $transactionData = new \stdClass();
        $transactionData->from_consumer = new \stdClass();
        $transactionData->from_credit_card = new \stdClass();
        $transactionData->order = new \stdClass();
        $transactionData->order->cart = new \stdClass();

        $transactionData->from_consumer->id = 1;
        $transactionData->from_credit_card->id = 1;
        $transactionData->order->cart->order_reference = self::QUOTE_ID;
        $transactionData->order->cart->metadata = new \stdClass();
        $transactionData->order->cart->metadata->immutable_quote_id = self::IMMUTABLE_QUOTE_ID;

        return $transactionData;
    }

    /**
     * @test
     * @covers ::saveCustomerCreditCard
     */
    public function testSaveCustomerCreditCard_validData()
    {
        $this->initCurrentMock(['fetchTransactionInfo']);
        $transaction = $this->mockTransactionData();

        $this->currentMock->expects(static::once())->method('fetchTransactionInfo')->with(OrderManagementTest::REFERENCE, OrderManagementTest::STORE_ID)
            ->willReturn($transaction);
        $this->quoteMock->expects(self::once())->method('getCustomerId')
            ->willReturn(self::CUSTOMER_ID);
        $this->cartHelper->expects(static::once())->method('getQuoteById')
            ->willReturn($this->quoteMock);

        $this->customerCreditCardCollectionFactory->expects(self::once())->method('create')->willReturnSelf();
        $this->customerCreditCardCollectionFactory->expects(self::once())->method('doesCardExist')->willReturn(false);

        $this->customerCreditCardFactory->expects(static::once())->method('create')->willReturnSelf();
        $this->customerCreditCardFactory->expects(static::once())->method('saveCreditCard')->willReturnSelf();

        $result = $this->currentMock->saveCustomerCreditCard(OrderManagementTest::REFERENCE, OrderManagementTest::STORE_ID);
        $this->assertTrue($result);
    }

    /**
     * @test
     * @covers ::saveCustomerCreditCard
     */
    public function testSaveCustomerCreditCard_IfParentQuoteDoesNotExist()
    {
        $this->initCurrentMock(['fetchTransactionInfo']);
        $transaction = $this->mockTransactionData();

        $this->currentMock->expects(static::once())->method('fetchTransactionInfo')->with(OrderManagementTest::REFERENCE, OrderManagementTest::STORE_ID)
            ->willReturn($transaction);
        $this->quoteMock->expects(self::once())->method('getCustomerId')
            ->willReturn(self::CUSTOMER_ID);

        $this->cartHelper->expects(self::exactly(2))->method('getQuoteById')
            ->withConsecutive([self::QUOTE_ID], [self::IMMUTABLE_QUOTE_ID])
            ->willReturnOnConsecutiveCalls(null, $this->quoteMock);

        $this->customerCreditCardCollectionFactory->expects(self::once())->method('create')->willReturnSelf();
        $this->customerCreditCardCollectionFactory->expects(self::once())->method('doesCardExist')->willReturn(false);

        $this->customerCreditCardFactory->expects(static::once())->method('create')->willReturnSelf();
        $this->customerCreditCardFactory->expects(static::once())->method('saveCreditCard')->willReturnSelf();

        $result = $this->currentMock->saveCustomerCreditCard(OrderManagementTest::REFERENCE, OrderManagementTest::STORE_ID);
        $this->assertTrue($result);
    }

    /**
     * @test
     * @covers ::saveCustomerCreditCard
     */
    public function testSaveCustomerCreditCard_IfQuoteDoesNotExist()
    {
        $this->initCurrentMock(['fetchTransactionInfo']);
        $transaction = $this->mockTransactionData();

        $this->currentMock->expects(static::once())->method('fetchTransactionInfo')->with(OrderManagementTest::REFERENCE, OrderManagementTest::STORE_ID)
            ->willReturn($transaction);


        $this->cartHelper->expects(self::exactly(2))->method('getQuoteById')
            ->withConsecutive([self::QUOTE_ID], [self::IMMUTABLE_QUOTE_ID])
            ->willReturnOnConsecutiveCalls(null, null);


        $result = $this->currentMock->saveCustomerCreditCard(OrderManagementTest::REFERENCE, OrderManagementTest::STORE_ID);
        $this->assertFalse($result);
    }

    /**
     * @test
     * @covers ::saveCustomerCreditCard
     */
    public function testSaveCustomerCreditCard_withException()
    {
        $this->initCurrentMock(['fetchTransactionInfo']);
        $transaction = $this->mockTransactionData();

        $this->currentMock->expects(static::once())->method('fetchTransactionInfo')->with(OrderManagementTest::REFERENCE, OrderManagementTest::STORE_ID)
            ->willReturn($transaction);
        $this->quoteMock->expects(self::once())->method('getCustomerId')
            ->willReturn(self::CUSTOMER_ID);
        $this->cartHelper->expects(static::once())->method('getQuoteById')
            ->willReturn($this->quoteMock);

        $this->customerCreditCardCollectionFactory->expects(self::once())->method('create')->willReturnSelf();
        $this->customerCreditCardCollectionFactory->expects(self::once())->method('doesCardExist')->willReturn(false);

        $this->customerCreditCardFactory->expects(static::once())->method('create')->willReturnSelf();
        $this->customerCreditCardFactory->expects(static::once())->method('saveCreditCard')->willThrowException(new \Exception());

        $result = $this->currentMock->saveCustomerCreditCard(OrderManagementTest::REFERENCE, OrderManagementTest::STORE_ID);
        $this->assertFalse($result);
    }

    /**
     * @test
     * @covers ::saveCustomerCreditCard
     */
    public function testSaveCustomerCreditCard_ignoreCreditCardCreationLogicIfCardExists()
    {
        $this->initCurrentMock(['fetchTransactionInfo']);
        $transaction = $this->mockTransactionData();

        $this->currentMock->expects(static::once())->method('fetchTransactionInfo')->with(OrderManagementTest::REFERENCE, OrderManagementTest::STORE_ID)
            ->willReturn($transaction);
        $this->quoteMock->expects(self::once())->method('getCustomerId')
            ->willReturn(self::CUSTOMER_ID);
        $this->cartHelper->expects(static::once())->method('getQuoteById')
            ->willReturn($this->quoteMock);


        $this->customerCreditCardCollectionFactory->expects(self::once())->method('create')->willReturnSelf();
        $this->customerCreditCardCollectionFactory->expects(self::once())->method('doesCardExist')->willReturn(true);

        $this->customerCreditCardFactory->expects(static::never())->method('create');
        $this->customerCreditCardFactory->expects(static::never())->method('saveCreditCard');

        $result = $this->currentMock->saveCustomerCreditCard(OrderManagementTest::REFERENCE, OrderManagementTest::STORE_ID);
        $this->assertFalse($result);
    }

    /**
     * @test
     * @covers ::validateRefundAmount
     * @param $refundAmount
     * @dataProvider refundAmount_Provider
     * @throws ReflectionException
     */
    public function validateRefundAmount_withInvalidRefundAmount($refundAmount)
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $order = Bootstrap::getObjectManager()->create(OrderModel::class);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Refund amount is invalid');
        TestHelper::invokeMethod($boltHelperOrder, 'validateRefundAmount', [$order, $refundAmount]);
    }

    public function refundAmount_Provider()
    {
        return [
            ['refundAmount' => -1],
            ['refundAmount' => 'is_string'],
            ['refundAmount' => null]
        ];
    }

    /**
     * @test
     * @covers ::validateRefundAmount
     * @throws ReflectionException
     */
    public function validateRefundAmount_withRefundAmountIsMoreThanAvailableRefund()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $order = TestUtils::createDumpyOrder(
            [
                'total_refunded' => 10,
                'total_paid' => 15,
            ]
        );

        $refundAmount = 1000;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Refund amount is invalid: refund amount [1000], available refund [500]');

        TestUtils::cleanupSharedFixtures([$order]);
        TestHelper::invokeMethod($boltHelperOrder, 'validateRefundAmount', [$order, $refundAmount]);
    }

    /**
     * @test
     * that validateRefundAmount succeeds if refund amount is valid number and it is lower than or equal to
     * the available amount
     *
     * @covers ::validateRefundAmount
     *
     * @throws ReflectionException if validateRefundAmount method doesn't exist
     */
    public function validateRefundAmount_withRefundAmountValidAndLowerThanAvailable_succeeds()
    {
        $this->skipTestInUnitTestsFlow();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $order = TestUtils::createDumpyOrder(
            [
                'total_refunded' => 10,
                'total_paid' => 25,
            ]
        );
        $refundAmount = 1000;
        TestHelper::invokeMethod($boltHelperOrder, 'validateRefundAmount', [$order, $refundAmount]);
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * that adjustPriceMismatch records price mismatch with Bugsnag if
     * 1. total price mismatch is greater than zero
     * 2. total price mismatch is bellow or equal to price fault tolerance
     *
     * @covers ::adjustPriceMismatch
     *
     * @dataProvider adjustPriceMismatch_withVariousAmountsProvider
     *
     * @param int $cartTotalAmount from current order transaction
     * @param float $grandTotal amount from order
     * @param int $priceFaultTolerance configuration value
     *
     * @throws ReflectionException if adjustPriceMismatch method is not defined
     * @throws Exception from {@see \Bolt\Boltpay\Helper\Shared\CurrencyUtils::toMinor} when unknown currency code is passed
     */
    public function adjustPriceMismatch_withVariousAmounts_recordsMismatch(
        $cartTotalAmount,
        $grandTotal,
        $priceFaultTolerance
    ) {
        $this->skipTestInUnitTestsFlow();
        $orderHelper = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        $order = TestUtils::createDumpyOrder(
            [
                'quote_id'=> $quoteId,
                'grand_total' => $grandTotal,
                'base_grand_total' => $grandTotal,


            ]
        );

        $transaction = new \stdClass();
        $transaction->order = new \stdClass();
        $transaction->order->cart = new \stdClass();
        $transaction->order->cart->total_amount = new \stdClass();
        $transaction->order->cart->total_amount->amount = $cartTotalAmount;

        $priceFaultToleranceConfiguration = '{"priceFaultTolerance":'.$priceFaultTolerance.'}';
        $configWriter = Bootstrap::getObjectManager()->create(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        $configWriter->save(
            ConfigHelper::XML_PATH_ADDITIONAL_CONFIG,
            $priceFaultToleranceConfiguration
        );

        $magentoTotalAmount = CurrencyUtils::toMinor($grandTotal,'USD');
        $totalMismatch = $cartTotalAmount - $magentoTotalAmount;
        $recordMismatch = abs($totalMismatch) > 0 && abs($totalMismatch) <= $priceFaultTolerance;

        TestHelper::invokeMethod(
            $orderHelper, 'adjustPriceMismatch',
            [$transaction, $order, $quote]
        );

        if ($recordMismatch) {
            self::assertEquals(CurrencyUtils::toMajor($cartTotalAmount, 'USD'), $order->getGrandTotal());
            self::assertEquals(CurrencyUtils::toMajor($cartTotalAmount, 'USD'), $order->getBaseGrandTotal());
        }else {
            self::assertEquals($grandTotal, $order->getGrandTotal());
            self::assertEquals($grandTotal, $order->getBaseGrandTotal());
        }

        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * Data provider for {@see adjustPriceMismatch_withVariousAmounts_recordsMismatch}
     *
     * @return array[] containing
     * 1. cart total amount from current order transaction
     * 2. grand total amount from order
     * 3. price fault tolerance configuration value
     */
    public function adjustPriceMismatch_withVariousAmountsProvider()
    {
        return [
            ['cartTotalAmount' => 10000, 'grandTotal' => 99.99, 'priceFaultTolerance' => 2],
            ['cartTotalAmount' => 100, 'grandTotal' => 99.99, 'priceFaultTolerance' => 2],
        ];
    }

    /**
     * @test
     *
     * @covers ::getExistingOrder
     *
     * @throws ReflectionException
     */
    public function getExistingOrder_byParenQuoteId() {
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder(['quote_id' => self::QUOTE_ID]);

        $orderHelper = Bootstrap::getObjectManager()->create(\Bolt\Boltpay\Helper\Order::class);
        static::assertEquals(
            $order->getId(),
            $orderHelper->getExistingOrder(self::INCREMENT_ID, self::QUOTE_ID)->getId()
        );
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * that cancelFailedPaymentOrder does not attempt to cancel if an existing order to be canceled cannot be found
     *
     * @covers ::cancelFailedPaymentOrder
     */
    public function cancelFailedPaymentOrder_ifOrderDoesNotExist_doesNotCancel()
    {
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::DISPLAY_ID)
            ->willReturn(false);
        $this->orderManagementMock->expects(static::never())->method('cancel');
        $this->orderRepository->expects(static::never())->method('save');
        $this->currentMock->cancelFailedPaymentOrder(self::DISPLAY_ID, self::IMMUTABLE_QUOTE_ID);
    }

    /**
     * @test
     * that cancelFailedPaymentOrder does not attempt to cancel if an existing order to be canceled cannot be found
     *
     * @covers ::cancelFailedPaymentOrder
     *
     * @dataProvider cancelFailedPaymentOrderProvider
     *
     * @param bool $isPPC
     *
     * @throws AlreadyExistsException
     */
    public function cancelFailedPaymentOrder_withVariousOrderTypes_cancelsOrder($isPPC)
    {
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::DISPLAY_ID)
            ->willReturn($this->orderMock);
        $this->orderManagementMock->expects(static::once())->method('cancel')->with(self::ORDER_ID);
        $this->orderMock->method('getId')->willReturn(self::ORDER_ID);
        $this->orderMock->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);
        $this->orderMock->method('getQuoteId')->willReturn($isPPC ? self::IMMUTABLE_QUOTE_ID : self::QUOTE_ID);
        $this->orderRepository->expects(static::once())->method('get')->with(self::ORDER_ID)
            ->willReturn($this->orderMock);
        $this->cartHelper->expects(static::once())->method('getQuoteById')
            ->with($isPPC ? self::IMMUTABLE_QUOTE_ID : self::QUOTE_ID)
            ->willReturn($this->quoteMock);
        $this->eventManager->expects(static::once())->method('dispatch')->with(
            'sales_model_service_quote_submit_failure',
            [
                'order' => $this->orderMock,
                'quote' => $this->quoteMock,
            ]
        );
        if ($isPPC) {
            $this->quoteMock->expects(static::once())->method('getBoltCheckoutType')
                ->willReturn(CartHelper::BOLT_CHECKOUT_TYPE_PPC_COMPLETE);
            $this->quoteMock->expects(static::once())->method('setBoltCheckoutType')
                ->with(CartHelper::BOLT_CHECKOUT_TYPE_PPC);
            $this->quoteMock->expects(static::once())->method('setIsActive')->with(false)->willReturnSelf();
            $this->cartHelper->expects(static::once())->method('quoteResourceSave')->with($this->quoteMock);
        } else {
            $this->quoteMock->expects(static::once())->method('getBoltCheckoutType')
                ->willReturn(CartHelper::BOLT_CHECKOUT_TYPE_MULTISTEP);
            $this->quoteMock->expects(static::once())->method('setIsActive')->with(true)->willReturnSelf();
            $this->cartHelper->expects(static::once())->method('quoteResourceSave')->with($this->quoteMock);
        }
        $this->eventsForThirdPartyModules->expects(static::once())->method('dispatchEvent')
            ->with("beforeFailedPaymentOrderSave", $this->orderMock);
        $this->orderMock->method('addData')->with(['quote_id' => null])->willReturn(self::ORDER_ID);
        $this->orderMock->method('addCommentToStatusHistory')
            ->with(__('BOLTPAY INFO :: Order was canceled due to Processor rejection'));

        $this->orderRepository->expects(static::once())->method('save');
        $this->currentMock->cancelFailedPaymentOrder(self::DISPLAY_ID, self::IMMUTABLE_QUOTE_ID);
    }

    /**
     * Data provider for {@see cancelFailedPaymentOrder_withVariousOrderTypes_cancelsOrder}
     *
     * @return array
     */
    public function cancelFailedPaymentOrderProvider(){
        return [
            ['isPPC' => true],
            ['isPPC' => false],
        ];
    }

    /**
     * @test
     * @covers ::deleteOrCancelFailedPaymentOrder
     */
    public function deleteOrCancelFailedPaymentOrder_deleteOrderByIncrementId() {
        $this->skipTestInUnitTestsFlow();
        $quote = TestUtils::createQuote();
        $order = TestUtils::createDumpyOrder(
            [
                'state' => OrderModel::STATE_PENDING_PAYMENT,
                'quote_id' => $quote->getId()
            ]
        );
        $orderHelper = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $displayId = $order->getIncrementId();
        TestUtils::setSecureAreaIfNeeded();
        self::assertEquals(
            'Order was deleted: '.$displayId,
            $orderHelper->deleteOrCancelFailedPaymentOrder($displayId, $quote->getId())
        );
    }

    /**
     * @test
     * @covers ::deleteOrCancelFailedPaymentOrder
     */
    public function deleteOrCancelFailedPaymentOrder_cancelFailedPaymentOrder() {
        $this->skipTestInUnitTestsFlow();
        $this->featureSwitches->method('isCancelFailedPaymentOrderInsteadOfDeleting')
            ->willReturn(true);
        $quote = TestUtils::createQuote();
        $order = TestUtils::createDumpyOrder(
            [
                'state' => OrderModel::STATE_PENDING_PAYMENT,
                'quote_id' => $quote->getId()
            ]
        );
        $displayId = $order->getIncrementId();
        
        self::assertEquals(
            'Order was canceled: '.$displayId,
            $this->currentMock->deleteOrCancelFailedPaymentOrder($displayId, $quote->getId())
        );
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * that cancelFailedPaymentOrder throws an exception if order to be canceled is not in the pending payment state
     * @covers ::cancelFailedPaymentOrder
     *
     * @param string $orderState current order state
     *
     * @throws AlreadyExistsException from the tested method
     */
    public function cancelFailedPaymentOrder_ifOrderStateIsNotPendingPayment_throwsBoltException()
    {
        $this->skipTestInUnitTestsFlow();
        $order = TestUtils::createDumpyOrder(['state' => OrderModel::STATE_PROCESSING]);
        $incrementId = $order->getIncrementId();
        $orderHelper = Bootstrap::getObjectManager()->create(\Bolt\Boltpay\Helper\Order::class);
        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(
            sprintf(
                "Order Delete Error. Order is in invalid state. Order #: %d State: %s Immutable Quote ID: %d",
                $order->getIncrementId(),
                OrderModel::STATE_PROCESSING,
                self::QUOTE_ID
            )
        );
        $this->expectExceptionCode(CreateOrder::E_BOLT_GENERAL_ERROR);
        $orderHelper->cancelFailedPaymentOrder($incrementId, self::QUOTE_ID);
        TestUtils::cleanupSharedFixtures([$order]);
    }
}
