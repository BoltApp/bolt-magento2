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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Definitions;
use Bolt\Boltpay\Helper\Hook;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Model\Api\CreateOrder;
use Bolt\Boltpay\Model\Api\OrderManagement;
use Bolt\Boltpay\Model\FeatureSwitch;
use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Model\Request;
use Bolt\Boltpay\Model\Service\InvoiceService;
use Bugsnag\Report;
use Exception;
use Magento\Customer\Model\Customer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Event\Manager;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\SessionException;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface as TransactionInterface;
use Magento\Sales\Model\AdminOrder\Create;
use Magento\Sales\Model\Order\Email\Container\InvoiceIdentity as InvoiceEmailIdentity;
use Magento\Sales\Model\Order\Invoice as Invoice;
use Magento\Sales\Model\Order as OrderModel;
use Magento\Directory\Model\Region as RegionModel;
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
use Magento\Sales\Model\Order\Item as OrderItem;
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
use Bolt\Boltpay\Helper\CustomFieldsHandler;
use Bolt\Boltpay\Model\CustomerCreditCardFactory;
use Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard\CollectionFactory as CustomerCreditCardCollectionFactory;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard\Collection as CustomerCreditCardCollection;

/**
 * @coversDefaultClass \Bolt\Boltpay\Helper\Order
 */
class OrderTest extends BoltTestCase
{
    const INCREMENT_ID = 1000001;
    const QUOTE_ID = 5678;
    const IMMUTABLE_QUOTE_ID = self::QUOTE_ID + 1;
    const DISPLAY_ID = self::INCREMENT_ID;
    const REFERENCE = 'AAAA-BBBB-PYQ9';
    const REFERENCE_ID = '1123123123';
    const PROCESSOR_VANTIV = 'vantiv';
    const PROCESSOR_PAYPAL = 'paypal';
    const TOKEN_BOLT = 'bolt';
    const STORE_ID = 1;
    const API_KEY = 'aaaabbbbcccc';
    const BOLT_TRACE_ID = 'aaaabbbbcccc';
    const ORDER_ID = 1233;
    const CURRENCY_CODE = 'USD';
    const TRANSACTION_ID = 'ABCD-1234-XXXX';
    const ADDRESS_DATA = [
        'region'                     => 'CA',
        'country_code'               => 'US',
        'email_address'              => self::CUSTOMER_EMAIL,
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
    const HOOK_PAYLOAD = ['checkboxes' => ['text'=>'Subscribe for our newsletter','category'=>'NEWSLETTER','value'=>true, 'is_custom_field'=>false],
                          'custom_fields' =>  ['label'=>'Gift', 'type'=>'CHECKBOX', 'is_custom_field'=>true,'value'=>true]];
    const CUSTOMER_ID = 1111;

    /** @var string test cart network */
    const CREDIT_CARD_NETWORK = 'visa';

    /** @var int test credit card last 4 digits */
    const CREDIT_CARD_LAST_FOUR = 1111;
    const CUSTOMER_EMAIL = 'test@bolt.com';

    private $objectManager;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    private $storeId;

    private $websiteId;

    /**
     * Setup test dependencies, called before each test
     *
     * @throws ReflectionException from initRequiredMocks and initCurrentMock methods
     */
    protected function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->orderHelper = $this->objectManager->create(OrderHelper::class);

        $store = $this->objectManager->get(StoreManagerInterface::class);
        $this->storeId = $store->getStore()->getId();

        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $this->websiteId = $websiteRepository->get('base')->getId();
    }

    /**
     * Cleanup changes made by tests
     */
    protected function tearDownInternal()
    {
        Hook::$fromBolt = false;
    }

    /**
     * @test
     *
     * @covers ::fetchTransactionInfo
     */
    public function fetchTransactionInfo()
    {
        $response = (object)['display_id' => static::DISPLAY_ID];
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $this->mockFetchTransactionInfo($orderHelper, $response);

        static::assertEquals(
            $response,
            $orderHelper->fetchTransactionInfo(static::REFERENCE_ID, static::STORE_ID)
        );

        // When we call method second time result should be returned from cache
        static::assertEquals(
            $response,
            $orderHelper->fetchTransactionInfo(static::REFERENCE_ID, static::STORE_ID)
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
        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $product = TestUtils::createVirtualProduct();
        $quote->addProduct($product, 1);
        $quote->setIsVirtual(true);
        $quote->save();

        TestHelper::invokeMethod(
            $this->orderHelper,
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
        $quote = TestUtils::createQuote();

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

        TestHelper::invokeMethod($this->orderHelper, 'setShippingMethod', [$quote, $transaction]);
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
        $addressObject = (object) self::ADDRESS_DATA;

        $quote = TestUtils::createQuote();
        $quoteAddress = $quote->getShippingAddress();
        TestHelper::invokeMethod(
            $this->orderHelper,
            'setAddress',
            [$quoteAddress, $addressObject]
        );
        self::assertEquals($quote->getShippingAddress()->getCity(), 'Beverly Hills');
        self::assertEquals($quote->getShippingAddress()->getCountryId(), 'US');
        self::assertEquals($quote->getShippingAddress()->getCompany(), 'Bolt');
        self::assertEquals($quote->getShippingAddress()->getEmail(), self::CUSTOMER_EMAIL);
        self::assertEquals($quote->getShippingAddress()->getTelephone(), '0123456789');
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
        $orderModel = $this->objectManager->create(Order::class);
        $quote = $this->objectManager->create(Quote::class);
        $orderModel->setOrderCurrencyCode(self::CURRENCY_CODE);
        $orderModel->setTaxAmount(50);

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

        TestHelper::invokeMethod(
            $this->orderHelper,
            'adjustTaxMismatch',
            [$transaction, $orderModel, $quote]
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
        $order = TestUtils::createDumpyOrder(['quote_id' => self::QUOTE_ID]);

        static::assertEquals(
            $order->getId(),
            TestHelper::invokeMethod(
                $this->orderHelper,
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
     * @test
     *
     * @covers ::createOrder
     *
     * @throws ReflectionException
     */
    public function createOrder_existingOrder()
    {
        $orderHelper = $this->getMockBuilder(OrderHelper::class)
            ->setMethods(['prepareQuote'])
            ->disableOriginalConstructor()
            ->getMock();

        $cartHelper = $this->objectManager->create(CartHelper::class);
        $bugsnag = $this->objectManager->create(Bugsnag::class);
        TestHelper::setProperty($orderHelper, 'cartHelper', $cartHelper);
        TestHelper::setProperty($orderHelper, 'bugsnag', $bugsnag);

        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        $orderHelper->method('prepareQuote')->willReturn($quote);
        $order = TestUtils::createDumpyOrder(['quote_id'=>$quoteId]);

        static::assertSame(
            $order->getId(),
            TestHelper::invokeMethod($orderHelper, 'createOrder', [$quote, []])->getId()
        );
        TestUtils::cleanupSharedFixtures([$order]);
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
        $orderHelper = $this->getMockBuilder(OrderHelper::class)
            ->setMethods(['prepareQuote','getExistingOrder'])
            ->disableOriginalConstructor()
            ->getMock();
        $quote = TestUtils::createQuote();
        $order = TestUtils::createDumpyOrder(['quote_id'=> $quote->getId()]);
        $bugsnag = $this->objectManager->create(Bugsnag::class);
        TestHelper::setProperty($orderHelper, 'bugsnag', $bugsnag);

        $orderHelper->method('prepareQuote')->willReturn($quote);

        $orderHelper->method('getExistingOrder')->willReturnOnConsecutiveCalls(null, $order);

        $quoteManagement = $this->createPartialMock(QuoteManagement::class, ['submit']);
        $quoteManagement->expects(self::once())->method('submit')
            ->willThrowException(new Exception(''));
        TestHelper::setProperty($orderHelper, 'quoteManagement', $quoteManagement);

        static::assertSame(
            $order,
            TestHelper::invokeMethod($orderHelper, 'createOrder', [$quote, []])
        );

        TestUtils::cleanupSharedFixtures([$order]);
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
        $orderHelper = $this->getMockBuilder(OrderHelper::class)
            ->setMethods(['prepareQuote'])
            ->disableOriginalConstructor()
            ->getMock();
        $cartHelper = $this->objectManager->create(CartHelper::class);
        $bugsnag = $this->objectManager->create(Bugsnag::class);
        $quoteManagement = $this->createPartialMock(QuoteManagement::class, ['submit']);
        TestHelper::setProperty($orderHelper, 'cartHelper', $cartHelper);
        TestHelper::setProperty($orderHelper, 'bugsnag', $bugsnag);
        TestHelper::setProperty($orderHelper, 'quoteManagement', $quoteManagement);
        $quote = TestUtils::createQuote();
        $orderHelper->method('prepareQuote')->willReturn($quote);
        $quoteManagement->method('submit')->willThrowException(new Exception('Quote Submit Exception'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Quote Submit Exception');

        TestHelper::invokeMethod($orderHelper, 'createOrder', [$quote, []]);
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
        $orderHelper = $this->getMockBuilder(OrderHelper::class)
            ->setMethods(['prepareQuote'])
            ->disableOriginalConstructor()
            ->getMock();
        $cartHelper = $this->objectManager->create(CartHelper::class);
        $bugsnag = $this->objectManager->create(Bugsnag::class);
        $quoteManagement = $this->createPartialMock(QuoteManagement::class, ['submit']);
        TestHelper::setProperty($orderHelper, 'cartHelper', $cartHelper);
        TestHelper::setProperty($orderHelper, 'bugsnag', $bugsnag);
        TestHelper::setProperty($orderHelper, 'quoteManagement', $quoteManagement);
        $quote = TestUtils::createQuote();
        $orderHelper->method('prepareQuote')->willReturn($quote);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Quote Submit Error. Parent Quote ID: '.$quote->getId().' Immutable Quote ID: ');

        TestHelper::invokeMethod($orderHelper, 'createOrder', [$quote, []]);
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
        $transaction = [];
        $orderHelper = $this->getMockBuilder(OrderHelper::class)
            ->setMethods(['prepareQuote','orderPostprocess'])
            ->disableOriginalConstructor()
            ->getMock();
        $cartHelper = $this->objectManager->create(CartHelper::class);
        $quoteManagement = $this->createPartialMock(QuoteManagement::class, ['submit']);

        $quote = TestUtils::createQuote();
        $order = TestUtils::createDumpyOrder();

        $orderHelper->method('prepareQuote')->willReturn($quote);
        $quoteManagement->method('submit')->with($quote)->willReturn($order);
        $orderHelper->method('orderPostprocess')->willReturnSelf();

        TestHelper::setProperty($orderHelper, 'cartHelper', $cartHelper);
        TestHelper::setProperty($orderHelper, 'quoteManagement', $quoteManagement);


        TestHelper::invokeMethod($orderHelper, 'createOrder', [$quote, []]);
        static::assertSame(
            $order->getId(),
            TestHelper::invokeMethod(
                $orderHelper,
                'createOrder',
                [$quote, $transaction]
            )->getId()
        );
        TestUtils::cleanupSharedFixtures([$order]);
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
        $order = TestUtils::createDumpyOrder();
        $quote = TestUtils::createQuote();
        $userNote = 'Test User Note';
        $transaction = json_decode(
            json_encode(
                [
                    'order'     => [
                        'cart'      => [
                            'tax_amount'   => [
                                'amount' => 10000,
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

        TestHelper::invokeMethod(
            $this->orderHelper,
            'orderPostprocess',
            [$order, $quote, $transaction]
        );

        self::assertEquals($userNote, $order->getData('customer_note'));
        TestUtils::cleanupSharedFixtures([$order]);
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
        $order = TestUtils::createDumpyOrder();
        $quote = TestUtils::createQuote();
        $quote->setBoltCheckoutType(CartHelper::BOLT_CHECKOUT_TYPE_PPC);

        $transaction = json_decode(
            json_encode(
                [
                    'order' => [
                        'cart' => [
                            'total_amount' => [
                                'amount' => 10000
                            ]
                        ]
                    ]
                ]
            )
        );
        TestHelper::invokeMethod(
            $this->orderHelper,
            'orderPostprocess',
            [$order, $quote, $transaction]
        );

        self::assertEquals(CartHelper::BOLT_CHECKOUT_TYPE_PPC_COMPLETE, $quote->getBoltCheckoutType());
        TestUtils::cleanupSharedFixtures([$order]);
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
        $order = TestUtils::createDumpyOrder();
        $payment = $order->getPayment();
        $payment->setCcType('visa');
        $payment->setCcLast4(5555);

        $transaction = (object)[
            'from_credit_card' => (object)[
                'last4' => 4444,
                'network' => 'mastercard',
            ],
        ];

        TestHelper::invokeMethod($this->orderHelper, 'setOrderPaymentInfoData', [$payment, $transaction]);

        self::assertEquals(5555, $payment->getCcLast4());
        self::assertEquals('visa', $payment->getCcType());
        TestUtils::cleanupSharedFixtures([$order]);

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

        $order = TestUtils::createDumpyOrder();
        $payment = $order->getPayment();


        $transaction = (object)[
            'from_credit_card' => (object)[
                'last4' => self::CREDIT_CARD_LAST_FOUR,
                'network' => self::CREDIT_CARD_NETWORK,
            ],
        ];
        TestHelper::invokeMethod($this->orderHelper, 'setOrderPaymentInfoData', [$payment, $transaction]);

        self::assertEquals(self::CREDIT_CARD_LAST_FOUR, $order->getPayment()->getCcLast4());
        self::assertEquals(self::CREDIT_CARD_NETWORK, $order->getPayment()->getCcType());
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     *
     * @covers ::resetOrderState
     */
    public function resetOrderState()
    {
        $order = TestUtils::createDumpyOrder();
        $this->orderHelper->resetOrderState($order);

        self::assertEquals(OrderHelper::MAGENTO_ORDER_STATUS_PENDING, $order->getStatus());
        self::assertEquals(OrderModel::STATE_PENDING_PAYMENT, $order->getState());
        self::assertEquals($order->getAllStatusHistory()[0]->getComment(), 'BOLTPAY INFO :: This order was approved by Bolt');
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
        $order = TestUtils::createDumpyOrder(['quote_id' => self::QUOTE_ID]);
        static::assertEquals($order->getId(), $this->orderHelper->getOrderByQuoteId(self::QUOTE_ID)->getId());
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     *
     * @covers ::saveUpdateOrder
     */
    public function saveUpdateOrder()
    {
        $quote = TestUtils::createQuote();
        $order = TestUtils::createDumpyOrder(['quote_id'=> $quote->getId()]);

        $transaction = json_decode(
            json_encode(
                [
                    'order' => [
                        'cart' => [
                            'order_reference' => $quote->getId(),
                            'display_id'      => $order->getIncrementId(),
                            'total_amount'    => [
                                'amount' => 100,
                            ],
                            'metadata'        => [
                                'immutable_quote_id' => $quote->getId(),
                            ],
                        ]
                    ],
                    'status' => 'cancelled',
                    'id' => '111'
                ]
            )
        );

        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $this->mockFetchTransactionInfo($orderHelper, $transaction);

        list($result, $result2) = $orderHelper->saveUpdateOrder(
            self::REFERENCE_ID,
            self::STORE_ID,
            self::BOLT_TRACE_ID,
            self::HOOK_TYPE_PENDING,
            self::HOOK_PAYLOAD
        );
        static::assertEquals(
            [$quote->getId(), $order->getId()],
            [$result->getId(), $result2->getId()]
        );
        TestUtils::cleanupSharedFixtures([$order]);
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
        Hook::$fromBolt = true;
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
                                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID
                            ],
                        ]
                    ],
                    'status' => Payment::TRANSACTION_CANCELLED,
                    'id' => '111'
                ]

            )
        );

        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $this->mockFetchTransactionInfo($orderHelper, $transaction);
        TestUtils::saveFeatureSwitch(
            \Bolt\Boltpay\Helper\FeatureSwitch\Definitions::M2_LOG_MISSING_QUOTE_FAILED_HOOKS, false
        );

        $bugsnag = $this->createPartialMock(Bugsnag::class,['notifyException','registerCallback']);
        $bugsnag->expects(self::once())->method('registerCallback')->willReturnSelf();
        $bugsnag->expects(self::once())->method('notifyException')->with(new LocalizedException(__('Unknown quote id: %1', self::IMMUTABLE_QUOTE_ID)))->willReturnSelf();
        TestHelper::setInaccessibleProperty($orderHelper,'bugsnag', $bugsnag);
        $orderHelper->saveUpdateOrder(
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
    public function saveUpdateOrder_noOrderNoQuote()
    {
        Hook::$fromBolt = false;
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
                                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID
                            ],
                        ]
                    ],
                    'status' => Payment::TRANSACTION_CANCELLED,
                    'id' => '111'
                ]

            )
        );

        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $this->mockFetchTransactionInfo($orderHelper, $transaction);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown quote id: ' . self::IMMUTABLE_QUOTE_ID);

        $orderHelper->saveUpdateOrder(
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
    public function saveUpdateOrder_noOrderNoQuote_fromWebhook_recordAttempt_throwException()
    {
        Hook::$fromBolt = true;
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
                                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID
                            ],
                        ]
                    ],
                    'status' => Payment::TRANSACTION_CANCELLED,
                    'id' => '1112'
                ]

            )
        );

        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $this->mockFetchTransactionInfo($orderHelper, $transaction);

        TestUtils::saveFeatureSwitch(
            \Bolt\Boltpay\Helper\FeatureSwitch\Definitions::M2_LOG_MISSING_QUOTE_FAILED_HOOKS, true
        );
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown quote id: ' . self::IMMUTABLE_QUOTE_ID);
        $orderHelper->saveUpdateOrder(
            self::REFERENCE_ID,
            self::STORE_ID,
            self::BOLT_TRACE_ID,
            self::HOOK_TYPE_AUTH
        );
        $webhookLogCollection = $this->objectManager->create(WebhookLogCollectionFactory::class);
        self::assertNotNull($webhookLogCollection->create()->getWebhookLogByTransactionId($transaction->id, self::HOOK_TYPE_AUTH)->getId());
    }

    /**
     * @test
     *
     * @covers ::saveUpdateOrder
     */
    public function saveUpdateOrder_noOrderNoQuote_fromWebhook_incrementAttemptCount_throwException()
    {
        Hook::$fromBolt = true;
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
                                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID
                            ],
                        ]
                    ],
                    'status' => Payment::TRANSACTION_CANCELLED,
                    'id' => '1113'
                ]

            )
        );

        /** @var \Bolt\Boltpay\Model\WebhookLog $webhookLog */
        $webhookLog = $this->objectManager->create(WebhookLogFactory::class);

        $webhookLog->create()->recordAttempt($transaction->id, self::HOOK_TYPE_AUTH);

        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $this->mockFetchTransactionInfo($orderHelper, $transaction);

        TestUtils::saveFeatureSwitch(
            \Bolt\Boltpay\Helper\FeatureSwitch\Definitions::M2_LOG_MISSING_QUOTE_FAILED_HOOKS, true
        );
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown quote id: ' . self::IMMUTABLE_QUOTE_ID);
        $orderHelper->saveUpdateOrder(
            self::REFERENCE_ID,
            self::STORE_ID,
            self::BOLT_TRACE_ID,
            self::HOOK_TYPE_AUTH
        );
        $webhookLogCollection = $this->objectManager->create(WebhookLogCollectionFactory::class);
        self::assertEquals(2, $webhookLogCollection->create()->getWebhookLogByTransactionId($transaction->id, self::HOOK_TYPE_AUTH)->getNumberOfMissingQuoteFailedHooks());
    }

    /**
     * @test
     *
     * @covers ::saveUpdateOrder
     */
    public function saveUpdateOrder_noOrderNoQuote_fromWebhook_returnThis()
    {
        Hook::$fromBolt = true;
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
                                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID
                            ],
                        ]
                    ],
                    'status' => Payment::TRANSACTION_CANCELLED,
                    'id' => '1114'
                ]

            )
        );

        /** @var \Bolt\Boltpay\Model\WebhookLog $webhookLog */
        $webhookLog = $this->objectManager->create(WebhookLogFactory::class);

        $webhookLog->create()->setTransactionId('1114')
            ->setHookType(self::HOOK_TYPE_AUTH)
            ->setNumberOfMissingQuoteFailedHooks(11)
            ->save();

        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $this->mockFetchTransactionInfo($orderHelper, $transaction);

        TestUtils::saveFeatureSwitch(
            \Bolt\Boltpay\Helper\FeatureSwitch\Definitions::M2_LOG_MISSING_QUOTE_FAILED_HOOKS, true
        );

        $orderHelper->saveUpdateOrder(
            self::REFERENCE_ID,
            self::STORE_ID,
            self::BOLT_TRACE_ID,
            self::HOOK_TYPE_AUTH
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
        $orderHelper = $this->createPartialMock(OrderHelper::class,
            [
                'fetchTransactionInfo', 'getDataFromDisplayID', 'createOrder',
                'resetOrderState', 'dispatchPostCheckoutEvents', 'getExistingOrder', 'updateOrderPayment',
            ]
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

        $orderHelper->expects(self::once())->method('fetchTransactionInfo')
            ->with(self::REFERENCE_ID, self::STORE_ID)->willReturn($transaction);

        Hook::$fromBolt = true;
        $immutablequoteMock = $this->createMock(Quote::class);
        $quoteMock = $this->createMock(Quote::class);

        $cartHelper = $this->createPartialMock(CartHelper::class, ['getQuoteById']);
        $cartHelper->expects(self::exactly(2))->method('getQuoteById')
            ->willReturnMap([
                [self::IMMUTABLE_QUOTE_ID, $immutablequoteMock],
                [self::QUOTE_ID, $quoteMock]
            ]);

        $orderMock = $this->createPartialMock(Order::class, ['getId', 'getGrandTotal', 'getOrderCurrencyCode']);
        $bugsnag = $this->createPartialMock(Bugsnag::class, ['registerCallback']);

        $bugsnag->expects(self::never())->method('registerCallback');

        $orderHelper->expects(self::once())->method('getExistingOrder')
            ->with(self::INCREMENT_ID)->willReturn(null);

        $orderHelper->expects(self::once())->method('createOrder')
            ->with($immutablequoteMock, $transaction, self::BOLT_TRACE_ID)->willReturn($orderMock);

        $orderMock->expects(self::never())->method('getId')->willReturn(self::ORDER_ID);
        $orderMock->expects(self::atLeastOnce())->method('getGrandTotal')->willReturn(1);
        $orderMock->expects(self::atLeastOnce())->method('getOrderCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $orderHelper->expects(self::once())->method('updateOrderPayment')
            ->with($orderMock, $transaction, null, $type = 'pending')->willReturn($orderMock);
        $featureSwitches = $this->createPartialMock(FeatureSwitch::class,['isIgnoreTotalValidationWhenCreditHookIsSentToMagentoEnabled']);

        $featureSwitches->expects(self::once())->method('isIgnoreTotalValidationWhenCreditHookIsSentToMagentoEnabled')->willReturn(false);
        $eventsForThirdPartyModules = $this->createPartialMock(EventsForThirdPartyModules::class, ['dispatchEvent']);
        $eventsForThirdPartyModules->method('dispatchEvent')->willReturnSelf();
        $resourceConnection = $this->createPartialMock(ResourceConnection::class, ['getConnection', 'getTableName','delete']);
        $resourceConnection->method('getConnection')->willReturnSelf();
        $resourceConnection->method('getTableName')->willReturnSelf();
        $resourceConnection->method('delete')->willReturnSelf();

        TestHelper::setInaccessibleProperty($orderHelper,'featureSwitches', $featureSwitches);
        TestHelper::setInaccessibleProperty($orderHelper,'resourceConnection', $resourceConnection);
        TestHelper::setInaccessibleProperty($orderHelper,'eventsForThirdPartyModules', $eventsForThirdPartyModules);
        TestHelper::setInaccessibleProperty($orderHelper,'cartHelper', $cartHelper);
        TestHelper::setInaccessibleProperty($orderHelper,'bugsnag', $bugsnag);

        $orderHelper->saveUpdateOrder(
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
        /** @var Quote $quote */
        $quote = $this->objectManager->create(Quote::class);
        $discountHelper = $this->createPartialMock(DiscountHelper::class,['applyExternalDiscountData']);
        $discountHelper->expects(static::once())->method('applyExternalDiscountData')->with($quote);
        TestHelper::setProperty($this->orderHelper, 'discountHelper', $discountHelper);
        $this->orderHelper->applyExternalQuoteData($quote);
    }

    /**
     * @test
     *
     * @covers ::dispatchPostCheckoutEvents
     */
    public function dispatchPostCheckoutEvents()
    {
        $orderModel = $this->objectManager->create(Order::class);
        $quote = $this->objectManager->create(Quote::class)->setBoltDispatched(false);
        $this->orderHelper->dispatchPostCheckoutEvents($orderModel, $quote);
        self::assertTrue($quote->getBoltDispatched());
        self::assertTrue($quote->getInventoryProcessed());
    }

    /**
     * @test
     * that dispatchPostCheckoutEvents returns null when events were already dispatched
     *
     * @covers ::dispatchPostCheckoutEvents
     */
    public function dispatchPostCheckoutEvents_whenAlreadyDispatched_returnsNull()
    {
        $orderModel = $this->objectManager->create(Order::class);
        $quote = $this->objectManager->create(Quote::class)->setBoltDispatched(true);
        $this->assertNull($this->orderHelper->dispatchPostCheckoutEvents($orderModel, $quote));
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
        $quote = TestUtils::createQuote();
        self::assertFalse($this->orderHelper->processExistingOrder($quote, new stdClass()));
    }

    /**
     * @test
     *
     * @covers ::processExistingOrder
     *
     * @throws Exception
     */
    public function processExistingOrder_withCanceledOrder_throwsException()
    {
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();

        $order = TestUtils::createDumpyOrder([
            'quote_id' => $quoteId,
            'increment_id' => '100000003'
        ]);
        $order->setState(Order::STATE_CANCELED);
        $order->save();

        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Order has been canceled due to the previously declined payment. Quote ID: %s Order Increment ID %s',
                $quote->getId(),
                $order->getIncrementId()
            )
        );
        $this->expectExceptionCode(CreateOrder::E_BOLT_REJECTED_ORDER);
        self::assertFalse($this->orderHelper->processExistingOrder($quote, new stdClass()));
        TestUtils::cleanupSharedFixtures([$order]);
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

        self::assertFalse($this->orderHelper->processExistingOrder($quote, $transaction));
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
            $this->orderHelper->processExistingOrder($quote, $transaction)->getIncrementId(),
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

        self::assertFalse($this->orderHelper->processExistingOrder($quote, $transaction));
        self::assertFalse(
            $this->orderHelper->processExistingOrder($quote, $transaction)
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
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $quoteManagement = $this->createPartialMock(QuoteManagement::class, ['submit']);
        $quote = $this->objectManager->create(Quote::class);
        $order = $this->objectManager->create(Order::class);
        $quoteManagement->expects(static::once())->method('submit')->with($quote, [])
            ->willReturn($order);
        TestHelper::setInaccessibleProperty($orderHelper,'quoteManagement', $quoteManagement);
        static::assertEquals($order, $orderHelper->submitQuote($quote));
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
        $quote = TestUtils::createQuote();
        $product = TestUtils::getSimpleProduct();
        $quote->addProduct($product, 3);
        $quote->save();

        $payment = $this->objectManager->create(OrderPayment::class);
        $payment->setMethod(Payment::METHOD_CODE);
        $order = TestUtils::createDumpyOrder(
            [
                'quote_id'=> $quote->getId(),
            ], [], [], Order::STATE_PENDING_PAYMENT, Order::STATE_PENDING_PAYMENT,
            $payment
        );

        static::assertEquals($order->getId(), $this->orderHelper->submitQuote($quote)->getId());
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * that submitQuote rethrows an exception thrown when creating order if the order was not succesfully created
     *
     * @covers ::submitQuote
     */
    public function submitQuote_withExceptionDuringOrderCreationAndOrderNotCreated_reThrowsException()
    {
        $quote = TestUtils::createQuote();
        $product = TestUtils::getSimpleProduct();
        $quote->addProduct($product, 3);
        $quote->save();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Some addresses can\'t be used due to the configurations for specific countries.');

        $this->orderHelper->submitQuote($quote);
        TestUtils::cleanupSharedFixtures([$product]);
    }

    /**
     * @test
     *
     * @covers ::processNewOrder
     */
    public function processNewOrder_fail()
    {
        $transaction = new stdClass();
        $quote = TestUtils::createQuote();
        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Quote Submit Error. Parent Quote ID: %s',
                $quote->getId()
            )
        );
        $this->expectExceptionCode(CreateOrder::E_BOLT_GENERAL_ERROR);
        $this->orderHelper->processNewOrder($quote, $transaction);
    }

    /**
     * @test
     *
     * @covers ::processNewOrder
     */
    public function processNewOrder_success()
    {
        $transaction = json_decode(
            json_encode(
                [
                    'order' => [
                        'cart' => [
                            'total_amount' => [
                                'amount' => 10000
                            ]
                        ]
                    ]
                ]
            )
        );
        $quote = TestUtils::createQuote();
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $quoteManagement = $this->createPartialMock(QuoteManagement::class, ['submit']);

        $payment = $this->objectManager->create(OrderPayment::class);
        $payment->setMethod(Payment::METHOD_CODE);
        $order = TestUtils::createDumpyOrder(
            [
                'quote_id'=> $quote->getId(),
            ], [], [], Order::STATE_PENDING_PAYMENT, Order::STATE_PENDING_PAYMENT,
            $payment
        );

        $quoteManagement->expects(static::once())->method('submit')->with($quote, [])
            ->willReturn($order);
        TestHelper::setInaccessibleProperty($orderHelper, 'quoteManagement', $quoteManagement);
        $orderHelper->processNewOrder($quote, $transaction);
        self::assertEquals(
            $order->getStatusHistoryCollection()->getFirstItem()->getComment(),
            'BOLTPAY INFO :: This order was created via Bolt Pre-Auth Webhook'
        );
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     *
     * @covers ::processNewOrder
     */
    public function processNewOrder_withNonBoltOrder()
    {
        $transaction = new stdClass();
        $quote = TestUtils::createQuote();
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $quoteManagement = $this->createPartialMock(QuoteManagement::class, ['submit']);

        $payment = $this->objectManager->create(OrderPayment::class);
        $payment->setMethod('paypal');
        $order = TestUtils::createDumpyOrder(
            [
                'quote_id'=> $quote->getId(),
                'increment_id' => '100000100'
            ], [], [], Order::STATE_PENDING_PAYMENT, Order::STATE_PENDING_PAYMENT,
            $payment
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(sprintf("Payment method assigned to order %s is: paypal", '100000100'));
        $quoteManagement->expects(static::once())->method('submit')->with($quote, [])
            ->willReturn($order);
        TestHelper::setInaccessibleProperty($orderHelper, 'quoteManagement', $quoteManagement);
        $orderHelper->processNewOrder($quote, $transaction);
        TestUtils::cleanupSharedFixtures([$order]);
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
        $order = $this->objectManager->create(Order::class);
        $order->setOrderCurrencyCode(self::CURRENCY_CODE);
        $order->setTaxAmount($orderTax);
        $order->setShippingAmount($orderShipping);
        $order->setGrandTotal($orderTotal);

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
            TestHelper::invokeMethod($this->orderHelper, 'hasSamePrice', [$order, $transaction])
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

        $order = TestUtils::createDumpyOrder();
        $orderId = $order->getId();
        TestHelper::invokeMethod($this->orderHelper, 'deleteOrder', [$order]);
        self::assertFalse(TestUtils::getOrderById($orderId));
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
        $this->orderHelper->tryDeclinedPaymentCancelation(self::INCREMENT_ID, self::IMMUTABLE_QUOTE_ID);
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
        $order = TestUtils::createDumpyOrder();
        $incrementId = $order->getIncrementId();

        self::assertTrue($this->orderHelper->tryDeclinedPaymentCancelation($incrementId, self::IMMUTABLE_QUOTE_ID));
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
        $order = TestUtils::createDumpyOrder(
            ['state' => Order::STATE_CANCELED]
        );
        $incrementId = $order->getIncrementId();
        self::assertTrue($this->orderHelper->tryDeclinedPaymentCancelation($incrementId, self::IMMUTABLE_QUOTE_ID));
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
        $order = TestUtils::createDumpyOrder(
            ['state' => Order::STATE_COMPLETE]
        );
        $incrementId = $order->getIncrementId();
        self::assertFalse($this->orderHelper->tryDeclinedPaymentCancelation($incrementId, self::IMMUTABLE_QUOTE_ID));
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
        $bugsnag = $this->createPartialMock(Bugsnag::class, ['notifyError']);
        $bugsnag->expects(self::once())->method('notifyError');
        TestHelper::setInaccessibleProperty($this->orderHelper,'bugsnag', $bugsnag);
        $this->orderHelper->deleteOrderByIncrementId(self::INCREMENT_ID, self::IMMUTABLE_QUOTE_ID);
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
        $state = Order::STATE_CANCELED;
        $order = TestUtils::createDumpyOrder(
            [
                'state' => $state,
                'increment_id' => '100000004'
            ]
        );

        $incrementId = $order->getIncrementId();
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(CreateOrder::E_BOLT_GENERAL_ERROR);
        $this->expectExceptionMessage(
            sprintf(
                'Order Delete Error. Order is in invalid state. Order #: %s State: %s Immutable Quote ID: %s',
                $incrementId,
                $state,
                self::IMMUTABLE_QUOTE_ID
            )
        );

        $this->orderHelper->deleteOrderByIncrementId($incrementId, self::IMMUTABLE_QUOTE_ID);
        TestUtils::cleanupSharedFixtures([$order]);
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
        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $quote->setBoltCheckoutType(CartHelper::BOLT_CHECKOUT_TYPE_PPC_COMPLETE);
        $quote->setQuoteCurrencyCode("USD");
        $quote->save();

        $order = TestUtils::createDumpyOrder(['quote_id' => $quote->getId()]);
        $incrementId = $order->getIncrementId();

        $this->orderHelper->deleteOrderByIncrementId($incrementId, $quote->getId());
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

        $order = TestUtils::createDumpyOrder();

        $incrementId = $order->getIncrementId();
        static::assertEquals(
            $order->getId(),
            $this->orderHelper->getExistingOrder($incrementId)->getId()
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
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();

        TestHelper::invokeMethod($this->orderHelper, 'quoteAfterChange', [$quote]);
        self::assertNotNull(TestUtils::getQuoteById($quoteId)->getUpdatedAt());
    }

    public function trueAndFalseDataProvider()
    {
        return [[true],[false]];
    }

    /**
     * @test
     * that setOrderUserNote will both add a new status history containing the user note, and set the configured order
     * order field
     *
     * @covers ::setOrderUserNote
     *
     * @dataProvider setOrderUserNote_withVariousCommentFieldsConfiguredProvider
     */
    public function setOrderUserNote_withVariousCommentFieldsConfigured_setsOrderFieldAndAddsStatusHistory(
        $configuredField,
        $expectedField
    ) {
        TestUtils::setupBoltConfig(
            [
                [
                    'path'    => \Bolt\Boltpay\Helper\Config::XML_PATH_ORDER_COMMENT_FIELD,
                    'value'   => $configuredField,
                    'scope'   => ScopeInterface::SCOPE_STORE,
                    'scopeId' => 0,
                ]
            ]
        );
        $userNote = 'Test note';
        $order = TestUtils::createDumpyOrder();
        $this->orderHelper->setOrderUserNote($order, $userNote);
        self::assertEquals($userNote, $order->getStatusHistories()[0]->getComment());
        self::assertEquals($userNote, $order->getData($expectedField));
        TestUtils::cleanupSharedFixtures([$order]);
    }

    public function setOrderUserNote_withVariousCommentFieldsConfiguredProvider()
    {
        return [
            'Not configured - defaults to `customer_note`' => [
                'configuredField' => null,
                'expectedField'   => 'customer_note'
            ],
            'Custom field configured' => [
                'configuredField' => 'custom_comment_field',
                'expectedField'   => 'custom_comment_field'
            ],
        ];
    }

    /**
     * @test
     *
     * @covers ::formatReferenceUrl
     */
    public function formatReferenceUrl()
    {
        static::assertEquals(
            sprintf(
                '<a href="https://merchant-sandbox.bolt.com/transaction/%1$s">%1$s</a>',
                self::REFERENCE_ID
            ),
            $this->orderHelper->formatReferenceUrl(self::REFERENCE_ID)
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
                $this->orderHelper,
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
        $payment = Bootstrap::getObjectManager()->create(Payment::class);

        $payment->setAdditionalInformation(
            [
                'transaction_state' => OrderHelper::TS_PENDING,
                'transaction_reference' => '000123',
                'real_transaction_id' => self::TRANSACTION_ID,
                'captures' => null,
                'processor' => self::PROCESSOR_VANTIV,
                'token_type' => self::TOKEN_BOLT,
            ]
        );


        $transactionMock = (object)([
            'type'     => OrderHelper::TT_PAYMENT,
            'status'   => "authorized",
            'captures' => [],
        ]);
        $state = $this->orderHelper->getTransactionState($transactionMock, $payment, null);
        static::assertEquals(OrderHelper::TS_AUTHORIZED, $state);
    }

    /**
     * @test
     *
     * @covers ::getTransactionState
     */
    public function getTransactionState_PaypalCompleted()
    {
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
        $state = $this->orderHelper->getTransactionState($transactionMock, $payment, null);
        static::assertEquals("cc_payment:completed", $state);
    }

    /**
     * @test
     * @covers ::getTransactionState
     */
    public function getTransactionState_APMInitialAuthorized()
    {

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
                'token_type' => self::TOKEN_BOLT,
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
        $order = TestUtils::createDumpyOrder(
            ['increment_id' => '100000005']
        );
        $transaction = json_decode(
            json_encode(
                [
                    'order'     => [
                        'cart' => [
                            'total_amount'    => [
                                'amount' => 5000
                            ],
                            'order_reference' => $order->getId(),
                            'display_id'      => $order->getIncrementId(),
                            'metadata'        => [
                                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
                            ],
                        ],
                    ],
                    'reference' => self::REFERENCE_ID
                ]
            )
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Order Totals Mismatch Reference: %s Order: %s Bolt Total: %s Store Total: %s',
                self::REFERENCE_ID,
                $order->getIncrementId(),
                5000,
                10000
            )
        );

        TestHelper::invokeMethod(
            $this->orderHelper,
            'holdOnTotalsMismatch',
            [$order, $transaction]
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
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $order = TestUtils::createDumpyOrder(['increment_id' => '100000006']);
        $transaction = json_decode(
            json_encode(
                [
                    'order'     => [
                        'cart' => [
                            'total_amount'    => [
                                'amount' => 1000,
                            ],
                            'order_reference' => self::QUOTE_ID,
                            'display_id'      => $order->getIncrementId(),
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

        $cartHelper = $this->getMockBuilder(CartHelper::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getQuoteById',
                'getCartData',
            ])
            ->getMock();

        $quoteMock = $this->createMock(Quote::class);

        $cartHelper->expects(self::once())->method('getQuoteById')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn($quoteMock);
        $cartHelper->expects(self::once())->method('getCartData')->with(
            true,
            false,
            $quoteMock
        )->willReturn($cartData);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Order Totals Mismatch Reference: %s Order: %s Bolt Total: %s Store Total: %s',
                self::REFERENCE_ID,
                $order->getIncrementId(),
                1000,
                10000
            )
        );

        $bugsnag = $this->createPartialMock(Bugsnag::class, ['registerCallback']);
        $bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function ($callback) use ($cartData, $transaction, $order) {
                $report = $this->createMock(Report::class);
                $report->expects(self::once())->method('setMetaData')->with(
                    [
                        'TOTALS_MISMATCH' => [
                            'Reference'   => self::REFERENCE_ID,
                            'Order ID'    =>  $order->getIncrementId(),
                            'Bolt Total'  => 1000,
                            'Store Total' => 10000,
                            'Bolt Cart'   => $transaction->order->cart,
                            'Store Cart'  => $cartData
                        ]
                    ]
                );
                $callback($report);
            }
        );
        TestHelper::setProperty($orderHelper, 'bugsnag', $bugsnag);
        TestHelper::setProperty($orderHelper, 'cartHelper', $cartHelper);
        TestHelper::invokeMethod(
            $orderHelper,
            'holdOnTotalsMismatch',
            [$order, $transaction]
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
            TestHelper::invokeMethod($this->orderHelper, 'getBoltTransactionStatus', [$transactionState])
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
                $this->orderHelper,
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
        $order = TestUtils::createDumpyOrder();
        $this->orderHelper->setOrderState($order, Order::STATE_HOLDED);
        self::assertEquals(Order::STATE_HOLDED, $order->getState());
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

        $orderMock = $this->createPartialMock(Order::class, [
            'getState','hold','setState','setStatus','save','getConfig'
        ]);
        $orderMock->expects(static::once())->method('getState')->willReturn($prevState);
        $orderMock->expects(static::once())->method('hold')->willThrowException(new Exception());
        $orderMock->expects(static::exactly(2))->method('setState')
            ->withConsecutive([Order::STATE_PROCESSING], [Order::STATE_HOLDED]);
        $orderConfigMock = $this->createPartialMock(Config::class,
            [
                'getStateDefaultStatus',
            ]);
        $orderConfigMock->expects(static::exactly(2))->method('getStateDefaultStatus')
            ->withConsecutive([Order::STATE_PROCESSING], [Order::STATE_HOLDED])
            ->willReturnArgument(0);
        $orderMock->method('getConfig')->willReturn($orderConfigMock);
        $orderMock->expects(static::exactly(2))->method('setStatus')
            ->withConsecutive([Order::STATE_PROCESSING], [Order::STATE_HOLDED]);
        $orderMock->expects(static::once())->method('save');
        $this->orderHelper->setOrderState($orderMock, Order::STATE_HOLDED);
    }

    /**
     * @test
     *
     * @covers ::setOrderState
     */
    public function setOrderState_canceledOrder()
    {

        $state = Order::STATE_CANCELED;

        $order = TestUtils::createDumpyOrder();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $boltHelperOrder->setOrderState($order, $state);
        self::assertEquals(Order::STATE_CANCELED, $order->getState());
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
        $order = $this->createPartialMock(
            Order::class,
            [
                'getState',
                'registerCancellation',
                'setState',
                'setStatus',
                'save',
            ]
        );
        $order->expects(static::once())->method('getState')->willReturn($prevState);
        $order->expects(static::once())->method('registerCancellation')->willReturnSelf();
        $order->expects(static::never())->method('setState');
        $order->expects(static::never())->method('setStatus');
        $order->expects(static::once())->method('save');
        $this->orderHelper->setOrderState($order, Order::STATE_CANCELED);
    }

    /**
     * @test
     *
     * @covers ::setOrderState
     */
    public function setOrderState_canceledOrderForRejectedIrreversibleHookWithException()
    {
        $prevState = Order::STATE_PAYMENT_REVIEW;
        $order = $this->createPartialMock(
            Order::class,
            [
                'getState',
                'registerCancellation',
                'setState',
                'setStatus',
                'save',
                'getConfig',
                'getStateDefaultStatus',
            ]
        );
        $orderConfigMock = $this->createPartialMock(
            Config::class,
            [
                'getStateDefaultStatus'
            ]
        );

        $orderConfigMock->expects(static::once())->method('getStateDefaultStatus')->willReturn(OrderModel::STATE_CANCELED);

        $order->expects(static::once())->method('getState')->willReturn($prevState);
        $order->expects(static::once())->method('registerCancellation')->willThrowException(new Exception());
        $order->expects(static::once())->method('setState');
        $order->expects(static::once())->method('setStatus');
        $order->method('getConfig')->willReturn($orderConfigMock);

        $order->expects(static::once())->method('save');
        $this->orderHelper->setOrderState($order, Order::STATE_CANCELED);
    }

    /**
     * @test
     *
     * @covers ::setOrderState
     */
    public function setOrderState_nonSpecialStateOrder()
    {

        $state = Order::STATE_PAYMENT_REVIEW;
        $order = TestUtils::createDumpyOrder();
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $boltHelperOrder->setOrderState($order, $state);
        self::assertEquals(Order::STATE_PAYMENT_REVIEW, $order->getState());
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
        $order = $this->createPartialMock(
            Order::class,
            [
                'cancel',
                'setState',
                'save',
                'getConfig'
            ]
        );
        $orderConfigMock = $this->createPartialMock(
            Config::class,
            [
                'getStateDefaultStatus'
            ]
        );


        $orderConfigMock->expects(static::once())->method('getStateDefaultStatus')
            ->willReturn(OrderModel::STATE_CANCELED);

        $order->expects(self::once())->method('cancel')->willThrowException($exception);

        $bugsnag = $this->createPartialMock(Bugsnag::class, ['notifyException']);
        $bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $order->expects(self::once())->method('setState')->with(Order::STATE_CANCELED);
        $order->expects(self::once())->method('save');
        $order->method('getConfig')->willReturn($orderConfigMock);

        TestHelper::setProperty($this->orderHelper, 'bugsnag', $bugsnag);

        TestHelper::invokeMethod($this->orderHelper, 'cancelOrder', [$order]);
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
     * @param bool $orderCanShip
     */
    public function transactionToOrderState($transactionState, $orderState, $isHookFromBolt = false, $isCreatingCreditMemoFromWebHookEnabled = false, $orderTotalRefunded = 0, $orderGrandTotal = 10, $orderTotalPaid = 10, $orderCanShip = false)
    {
        Hook::$fromBolt = $isHookFromBolt;
        $featureSwitch = TestUtils::saveFeatureSwitch(
            \Bolt\Boltpay\Helper\FeatureSwitch\Definitions::M2_CREATING_CREDITMEMO_FROM_WEB_HOOK_ENABLED,
            $isCreatingCreditMemoFromWebHookEnabled
        );

        $order = $this->objectManager->create(Order::class);
        $order->setTotalRefunded($orderTotalRefunded);
        $order->setGrandTotal($orderGrandTotal);
        $order->setTotalPaid($orderTotalPaid);
        
        $order->setActionFlag(Order::ACTION_FLAG_UNHOLD, $orderCanShip);
        $order->setIsVirtual($orderCanShip);
        $order->setActionFlag(Order::ACTION_FLAG_SHIP, !$orderCanShip);
        $orderItem = $this->objectManager->create(OrderItem::class);
        $orderItem->setQtyOrdered(1)
                ->setQtyShipped($orderCanShip ? 0 : 1)
                ->setQtyRefunded(0)
                ->setQtyCanceled(0)
                ->setIsVirtual(false)
                ->setLockedDoShip(false);
        $order->addItem($orderItem);
        $orderRepository = $this->objectManager->create(OrderRepositoryInterface::class);
        $orderRepository->save($order);

        static::assertEquals($orderState, $this->orderHelper->transactionToOrderState($transactionState, $order));
        TestUtils::cleanupFeatureSwitch($featureSwitch);
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
            [OrderHelper::TS_CREDIT_COMPLETED, OrderModel::STATE_PROCESSING, true, true, 10, 10 ,10, true],
            [OrderHelper::TS_CREDIT_COMPLETED, OrderModel::STATE_PROCESSING, true, true, 8, 10 ,10],
            [OrderHelper::TS_CREDIT_COMPLETED, OrderModel::STATE_PROCESSING, false, true],
            [OrderHelper::TS_CREDIT_COMPLETED, OrderModel::STATE_HOLDED, true, false],
            [OrderHelper::TS_CREDIT_COMPLETED, OrderModel::STATE_HOLDED, true, false],
        ];
    }

    /**
     * @test
     *
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_sameState()
    {
        Hook::$fromBolt = false;
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
                    'captures'  => [],
                    'order' => [
                        'cart' => [
                            'total_amount' => [
                                'amount' => 10
                            ]
                        ]
                    ],
                    'from_credit_card' => [
                        'token_type' => self::TOKEN_BOLT
                    ],
                    'type'     => OrderHelper::TT_PAYMENT,
                    'status'   => "authorized",
                ]
            )
        );

        $prevTransactionState = OrderHelper::TS_AUTHORIZED;
        $prevTransactionReference = self::REFERENCE_ID;
        $orderHelper = $this->objectManager->create(OrderHelper::class);

        $payment = $this->objectManager->create(Order\Payment::class);
        $payment->setMethod(\Bolt\Boltpay\Model\Payment::METHOD_CODE);

        $paymentData = [
            'transaction_state' => $prevTransactionState,
            'transaction_reference' => $prevTransactionReference,
            'real_transaction_id' => self::TRANSACTION_ID,
            'authorized' => true,
            'captures' => '',
            'processor' => self::PROCESSOR_VANTIV,
            'token_type' => self::TOKEN_BOLT
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        $order = TestUtils::createDumpyOrder([], [], [], Order::STATE_PAYMENT_REVIEW, Order::STATE_PAYMENT_REVIEW, $payment);
        $orderHelper->updateOrderPayment($order, $transaction);
        self::assertTrue($payment->getIsTransactionApproved());
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     *
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_handleCheckboxes()
    {

        $transaction = json_decode(
            json_encode(
                [
                    'id'        => self::TRANSACTION_ID,
                    'reference' => '11112',
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
                    ],
                    'from_credit_card' => [
                        'token_type' => self::TOKEN_BOLT
                    ],
                    'type'     => OrderHelper::TT_PAYMENT,
                    'status'   => "captured",
                ]
            )
        );

        $payment = $this->objectManager->create(Order\Payment::class);
        $payment->setMethod(\Bolt\Boltpay\Model\Payment::METHOD_CODE);

        $paymentData = [
            'transaction_state' => "",
            'transaction_reference' => null,
            'real_transaction_id' => $transaction->id,
            'authorized' => false,
            'captures' => '',
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));

        $order = TestUtils::createDumpyOrder([], [], [], Order::STATE_PENDING_PAYMENT, Order::STATE_PENDING_PAYMENT, $payment);
        $hookLoad = [
            'checkboxes' => [
                [
                    'text' => 'Subscribe for our newsletter',
                    'category' => 'NEWSLETTER',
                    'value' => true,
                    'is_custom_field' => false,
                    'features' => false
                ]
            ]
        ];

        $this->orderHelper->updateOrderPayment($order, $transaction, null, Hook::HT_CAPTURE, $hookLoad);
        self::assertEquals('BOLTPAY INFO :: checkboxes<br>Subscribe for our newsletter: Yes',$order->getStatusHistories()[0]->getComment());
        TestUtils::cleanupSharedFixtures([$order]);
    }

        /**
         * @test
         *
         * @covers ::updateOrderPayment
         */
    public function updateOrderPayment_handleCustomFields()
    {

        $transaction = json_decode(
            json_encode(
                [
                    'id'        => self::TRANSACTION_ID,
                    'reference' => '11112',
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
                    ],
                    'from_credit_card' => [
                        'token_type' => self::TOKEN_BOLT
                    ],
                    'type'     => OrderHelper::TT_PAYMENT,
                    'status'   => "captured",
                ]
            )
        );

        $payment = $this->objectManager->create(Order\Payment::class);
        $payment->setMethod(\Bolt\Boltpay\Model\Payment::METHOD_CODE);

        $paymentData = [
            'transaction_state' => "",
            'transaction_reference' => null,
            'real_transaction_id' => $transaction->id,
            'authorized' => false,
            'captures' => '',
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));

        $order = TestUtils::createDumpyOrder([], [], [], Order::STATE_PENDING_PAYMENT, Order::STATE_PENDING_PAYMENT, $payment);
        $hookLoad = [
            'custom_fields' => [
                [
                    'features' => [],
                    'type' => CustomFieldsHandler::TYPE_DROPDOWN,
                    'label' => 'label',
                    'value' => 'value'
                ]
            ]
        ];

        $this->orderHelper->updateOrderPayment($order, $transaction, null, Hook::HT_CAPTURE, $hookLoad);
        self::assertEquals('BOLTPAY INFO :: customfields<br>label: value', $order->getStatusHistories()[0]->getComment());
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_sameState_withAuthHookAndOrderStatusIsPaymentReview()
    {
        Hook::$fromBolt = true;
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
                    'captures'  => [],
                    'order' => [
                        'cart' => [
                            'total_amount' => [
                                'amount' => 10
                            ]
                        ]
                    ],
                    'from_credit_card' => [
                        'token_type' => self::TOKEN_BOLT
                    ],
                    'type'     => OrderHelper::TT_PAYMENT,
                    'status'   => "authorized",
                ]
            )
        );

        $prevTransactionState = OrderHelper::TS_AUTHORIZED;
        $prevTransactionReference = self::REFERENCE_ID;
        $orderHelper = $this->objectManager->create(OrderHelper::class);

        $payment = $this->objectManager->create(Order\Payment::class);
        $payment->setMethod(\Bolt\Boltpay\Model\Payment::METHOD_CODE);

        $paymentData = [
            'transaction_state' => $prevTransactionState,
            'transaction_reference' => $prevTransactionReference,
            'real_transaction_id' => self::TRANSACTION_ID,
            'authorized' => true,
            'captures' => '',
            'processor' => self::PROCESSOR_VANTIV,
            'token_type' => self::TOKEN_BOLT
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        $order = TestUtils::createDumpyOrder([], [], [], Order::STATE_PAYMENT_REVIEW, Order::STATE_PAYMENT_REVIEW, $payment);
        $orderHelper->updateOrderPayment($order, $transaction, self::REFERENCE_ID, self::HOOK_TYPE_AUTH);
        self::assertEquals(
            'BOLTPAY INFO :: PAYMENT Status: AUTHORIZED Amount: $0.10<br>Bolt transaction: <a href="https://merchant-sandbox.bolt.com/transaction/1123123123">1123123123</a> Transaction ID: "ABCD-1234-XXXX-auth"',
            $order->getStatusHistories()[0]->getComment());
        self::assertNull($payment->getIsTransactionApproved());
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_sameState_withPendingHookAndOrderStatusIsPendingPayment()
    {
        Hook::$fromBolt = true;
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
                    'captures'  => [],
                    'order' => [
                        'cart' => [
                            'total_amount' => [
                                'amount' => 10
                            ]
                        ]
                    ],
                    'from_credit_card' => [
                        'token_type' => self::TOKEN_BOLT
                    ],
                    'type'     => OrderHelper::TT_PAYMENT,
                    'status'   => "authorized",
                ]
            )
        );

        $prevTransactionState = OrderHelper::TS_AUTHORIZED;
        $prevTransactionReference = self::REFERENCE_ID;
        $orderHelper = $this->objectManager->create(OrderHelper::class);

        $payment = $this->objectManager->create(Order\Payment::class);
        $payment->setMethod(\Bolt\Boltpay\Model\Payment::METHOD_CODE);

        $paymentData = [
            'transaction_state' => $prevTransactionState,
            'transaction_reference' => $prevTransactionReference,
            'real_transaction_id' => self::TRANSACTION_ID,
            'authorized' => true,
            'captures' => '',
            'processor' => self::PROCESSOR_VANTIV,
            'token_type' => self::TOKEN_BOLT
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        $order = TestUtils::createDumpyOrder([], [], [], Order::STATE_PENDING_PAYMENT, Order::STATE_PENDING_PAYMENT, $payment);
        $orderHelper->updateOrderPayment($order, $transaction, self::REFERENCE_ID, self::HOOK_TYPE_PENDING);
        self::assertEquals(
            'BOLTPAY INFO :: PAYMENT Status: AUTHORIZED Amount: $0.10<br>Bolt transaction: <a href="https://merchant-sandbox.bolt.com/transaction/1123123123">1123123123</a> Transaction ID: "ABCD-1234-XXXX-auth"',
            $order->getStatusHistories()[2]->getComment());
        self::assertNull($payment->getIsTransactionApproved());
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     *
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_rejectedIrreversible()
    {
        Hook::$fromBolt = false;
        $transaction = json_decode(
            json_encode(
                [
                    'id'        => self::TRANSACTION_ID,
                    'reference' => '11112',
                    'processor' => self::PROCESSOR_VANTIV,
                    'amount'    => [
                        'amount' => 10
                    ],
                    'date'      => microtime(true) * 1000,
                    'captures'  => [],
                    'order' => [
                        'cart' => [
                            'total_amount' => [
                                'amount' => 10
                            ]
                        ]
                    ],
                    'from_credit_card' => [
                        'token_type' => self::TOKEN_BOLT
                    ],
                    'type'     => OrderHelper::TT_PAYMENT,
                    'status'   => "rejected_irreversible",
                ]
            )
        );

        $prevTransactionState = OrderHelper::TS_REJECTED_IRREVERSIBLE;
        $prevTransactionReference = self::REFERENCE_ID;
        $orderHelper = $this->objectManager->create(OrderHelper::class);

        $payment = $this->objectManager->create(Order\Payment::class);
        $payment->setMethod(\Bolt\Boltpay\Model\Payment::METHOD_CODE);

        $paymentData = [
            'transaction_state' => $prevTransactionState,
            'transaction_reference' => $prevTransactionReference,
            'real_transaction_id' => self::TRANSACTION_ID,
            'authorized' => true,
            'captures' => '',
            'processor' => self::PROCESSOR_VANTIV,
            'token_type' => self::TOKEN_BOLT
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        $order = TestUtils::createDumpyOrder([], [], [], Order::STATE_PAYMENT_REVIEW, Order::STATE_PAYMENT_REVIEW, $payment);
        $orderHelper->updateOrderPayment($order, $transaction);

        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     *
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_withUnhandledState_throwsException()
    {
        Hook::$fromBolt = false;
        $transaction = json_decode(
            json_encode(
                [
                    'id'        => self::TRANSACTION_ID,
                    'reference' => '11112',
                    'processor' => self::PROCESSOR_VANTIV,
                    'amount'    => [
                        'amount' => 10
                    ],
                    'date'      => microtime(true) * 1000,
                    'captures'  => [],
                    'order' => [
                        'cart' => [
                            'total_amount' => [
                                'amount' => 10
                            ]
                        ]
                    ],
                    'from_credit_card' => [
                        'token_type' => self::TOKEN_BOLT
                    ],
                    'type'     => OrderHelper::TT_PAYMENT,
                    'status'   => "Unhandled_State",
                ]
            )
        );

        $prevTransactionState = OrderHelper::TS_REJECTED_IRREVERSIBLE;
        $prevTransactionReference = self::REFERENCE_ID;
        $orderHelper = $this->objectManager->create(OrderHelper::class);

        $payment = $this->objectManager->create(Order\Payment::class);
        $payment->setMethod(\Bolt\Boltpay\Model\Payment::METHOD_CODE);

        $paymentData = [
            'transaction_state' => $prevTransactionState,
            'transaction_reference' => $prevTransactionReference,
            'real_transaction_id' => self::TRANSACTION_ID,
            'authorized' => true,
            'captures' => '',
            'processor' => self::PROCESSOR_VANTIV,
            'token_type' => self::TOKEN_BOLT
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        $order = TestUtils::createDumpyOrder(['increment_id' => '100000101'], [], [], Order::STATE_PAYMENT_REVIEW, Order::STATE_PAYMENT_REVIEW, $payment);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unhandled transaction state : cc_payment:Unhandled_State');
        $orderHelper->updateOrderPayment($order, $transaction);

        TestUtils::cleanupSharedFixtures([$order]);
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
        $transaction = json_decode(
            json_encode(
                [
                    'id'        => self::TRANSACTION_ID,
                    'reference' => '11112',
                    'processor' => self::PROCESSOR_VANTIV,
                    'amount'    => [
                        'amount' => 10
                    ],
                    'date'      => microtime(true) * 1000,
                    'captures'  => [],
                    'order' => [
                        'cart' => [
                            'total_amount' => [
                                'amount' => 10
                            ]
                        ]
                    ],
                    'from_credit_card' => [
                        'token_type' => self::TOKEN_BOLT
                    ],
                    'type'     => OrderHelper::TT_CREDIT,
                    'status'   => "completed",
                ]
            )
        );

        $payment = $this->objectManager->create(Order\Payment::class);
        $payment->setMethod(\Bolt\Boltpay\Model\Payment::METHOD_CODE);

        $paymentData = [
            'transaction_state' => "",
            'transaction_reference' => null,
            'real_transaction_id' => $transaction->id,
            'authorized' => false,
            'captures' => '',
            'refunds' => "$transaction->id,example-232-axs,example-dada-3232"
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));

        $order = TestUtils::createDumpyOrder([], [], [], Order::STATE_PAYMENT_REVIEW, Order::STATE_PAYMENT_REVIEW, $payment);

        $this->assertNull(
            $this->orderHelper->updateOrderPayment($order, $transaction, null, null, self::HOOK_PAYLOAD)
        );

        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     *
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_createInvoice()
    {
        Hook::$fromBolt = true;
        $transaction = json_decode(
            json_encode(
                [
                    'id'        => self::TRANSACTION_ID,
                    'reference' => '11112',
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
                    ],
                    'from_credit_card' => [
                        'token_type' => self::TOKEN_BOLT
                    ],
                    'type'     => OrderHelper::TT_PAYMENT,
                    'status'   => "captured",
                ]
            )
        );

        $payment = $this->objectManager->create(Order\Payment::class);
        $payment->setMethod(\Bolt\Boltpay\Model\Payment::METHOD_CODE);

        $paymentData = [
            'transaction_state' => "",
            'transaction_reference' => null,
            'real_transaction_id' => $transaction->id,
            'authorized' => false,
            'captures' => '',
        ];
        $featureSwitch = TestUtils::saveFeatureSwitch(Definitions::M2_IGNORE_HOOK_FOR_INVOICE_CREATION,false);
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));

        $order = TestUtils::createDumpyOrder([], [], [], Order::STATE_PENDING_PAYMENT, Order::STATE_PENDING_PAYMENT, $payment);
        $this->orderHelper->updateOrderPayment($order, $transaction, null, Hook::HT_CAPTURE,null);

        self::assertEquals(1, $order->getInvoiceCollection()->getSize());
        TestUtils::cleanupSharedFixtures([$order]);
        TestUtils::cleanupFeatureSwitch($featureSwitch);
    }

    /**
     * @test
     *
     * @covers ::updateOrderPayment
     */
    public function updateOrderPayment_withIgnoreInvoiceCreationEnabled_DoesNotCreateInvoice()
    {
        Hook::$fromBolt = true;
        $transaction = json_decode(
            json_encode(
                [
                    'id'        => self::TRANSACTION_ID,
                    'reference' => '11112',
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
                    ],
                    'from_credit_card' => [
                        'token_type' => self::TOKEN_BOLT
                    ],
                    'type'     => OrderHelper::TT_PAYMENT,
                    'status'   => "captured",
                ]
            )
        );

        $payment = $this->objectManager->create(Order\Payment::class);
        $payment->setMethod(\Bolt\Boltpay\Model\Payment::METHOD_CODE);

        $paymentData = [
            'transaction_state' => "",
            'transaction_reference' => null,
            'real_transaction_id' => $transaction->id,
            'authorized' => false,
            'captures' => '',
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));
        $featureSwitch = TestUtils::saveFeatureSwitch(Definitions::M2_IGNORE_HOOK_FOR_INVOICE_CREATION,true);
        $order = TestUtils::createDumpyOrder([], [], [], Order::STATE_PENDING_PAYMENT, Order::STATE_PENDING_PAYMENT, $payment);
        $this->orderHelper->updateOrderPayment($order, $transaction, null, Hook::HT_CAPTURE,null);

        self::assertEquals(0, $order->getInvoiceCollection()->getSize());
        TestUtils::cleanupSharedFixtures([$order]);
        TestUtils::cleanupFeatureSwitch($featureSwitch);
    }

    /**
     * @covers ::createOrderInvoice
     * @throws LocalizedException
     * @throws ReflectionException
     */
    public function createOrderInvoice()
    {
        $order = TestUtils::createDumpyOrder();

        TestHelper::invokeMethod(
            $this->orderHelper,
            'createOrderInvoice',
            [$order, self::TRANSACTION_ID, 50]
        );

        self::assertTrue(count($order->getInvoiceCollection()->getItems()) > 0);
        TestUtils::cleanupSharedFixtures([$order]);
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
        $orderHelper = $this->objectManager->create(OrderHelper::class);

        $totalInvoiced = 0;
        $invoice = $this->createPartialMock(
            Invoice::class,
            [
                'setRequestedCaptureCase', 'setTransactionId',
                'setBaseGrandTotal', 'setGrandTotal', 'register','getEmailSent'
            ]
        );

        $order = $this->createPartialMock(
            Order::class,
            [
                'getOrderCurrencyCode','getTotalInvoiced', 'getGrandTotal',
                'addStatusHistoryComment', 'setIsCustomerNotified', 'register',
            ]
        );
        $order->method('getOrderCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $order->expects(static::once())->method('getTotalInvoiced')->willReturn($totalInvoiced);
        $order->expects(static::once())->method('getGrandTotal')->willReturn($grandTotal);
        $order->method('addStatusHistoryComment')->willReturn($order);
        $order->method('setIsCustomerNotified')->willReturn($order);
        $invoice->method('getEmailSent')->willReturn(true);
        $invoiceService = $this->createPartialMock(
            InvoiceService::class,
            ['prepareInvoice', 'prepareInvoiceWithoutItems']
        );

        if ($isSame) {
            $invoiceService->method('prepareInvoice')->with($order)->willReturn($invoice);
        } else {
            $invoiceService->method('prepareInvoiceWithoutItems')->with($order, $amount)->willReturn($invoice);
        }

        TestHelper::setInaccessibleProperty($orderHelper, 'invoiceService', $invoiceService);
        TestHelper::invokeMethod(
            $orderHelper ,
            'createOrderInvoice',
            [$order, self::TRANSACTION_ID, $amount]
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
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $message = 'Expected exception message';
        $exception = new Exception($message);
        $order = $this->createPartialMock(
            Order::class,
            [
                'getOrderCurrencyCode','getTotalInvoiced', 'getGrandTotal',
                'addStatusHistoryComment', 'setIsCustomerNotified', 'register',
            ]
        );
        $order->method('getOrderCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $order->expects(static::once())->method('getTotalInvoiced')->willReturn(5);
        $order->expects(static::once())->method('getGrandTotal')->willReturn(5);

        $invoiceService = $this->createPartialMock(
            InvoiceService::class,
            ['prepareInvoice', 'prepareInvoiceWithoutItems']
        );

        $invoiceService->expects(static::once())->method('prepareInvoice')->willThrowException($exception);

        $bugsnag = $this->createPartialMock(Bugsnag::class, ['notifyException']);
        $bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);

        TestHelper::setInaccessibleProperty($orderHelper, 'invoiceService', $invoiceService);
        TestHelper::setInaccessibleProperty($orderHelper, 'bugsnag', $bugsnag);

        TestHelper::invokeMethod(
            $orderHelper,
            'createOrderInvoice',
            [$order, self::TRANSACTION_ID, 0]
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
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $invoice = $this->createPartialMock(
            Invoice::class,
            [
                'setRequestedCaptureCase', 'setTransactionId', 'setBaseGrandTotal',
                'setGrandTotal', 'register',
            ]
        );

        $order = $this->createPartialMock(
            Order::class,
            [
                'getOrderCurrencyCode','getTotalInvoiced', 'getGrandTotal',
                'addStatusHistoryComment', 'setIsCustomerNotified', 'register','getStoreId'
            ]
        );
        $order->method('getOrderCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $order->expects(static::once())->method('getTotalInvoiced')->willReturn(5);
        $order->expects(static::once())->method('getGrandTotal')->willReturn(5);
        $order->expects(static::once())->method('getStoreId')->willReturn(self::STORE_ID);
        $order->method('addStatusHistoryComment')->willReturn($order);
        $order->method('setIsCustomerNotified')->willReturn($order);
        $invoiceService = $this->createPartialMock(
            InvoiceService::class,
            ['prepareInvoice', 'prepareInvoiceWithoutItems']
        );
        $message = 'Expected exception message';
        $exception = new Exception($message);
        $invoiceService->expects(static::once())->method('prepareInvoice')->willReturn($invoice);
        $scopeConfig = $this->createPartialMock(ScopeConfigInterface::class, ['isSetFlag','getValue']);
        $scopeConfig->expects(static::once())->method('isSetFlag')
            ->with(InvoiceEmailIdentity::XML_PATH_EMAIL_ENABLED, ScopeInterface::SCOPE_STORE, self::STORE_ID)
            ->willReturn(true);
        $invoiceSender= $this->createPartialMock(
            InvoiceSender::class,
            ['send']
        );
        $invoiceSender->expects(static::once())->method('send')->willThrowException($exception);
        $bugsnag = $this->createPartialMock(Bugsnag::class, ['notifyException']);
        $bugsnag->expects(self::once())->method('notifyException')->with($exception);

        TestHelper::setInaccessibleProperty($orderHelper, 'invoiceService', $invoiceService);
        TestHelper::setInaccessibleProperty($orderHelper, 'bugsnag', $bugsnag);
        TestHelper::setInaccessibleProperty($orderHelper, 'invoiceSender', $invoiceSender);
        TestHelper::setInaccessibleProperty($orderHelper, 'scopeConfig', $scopeConfig);
        TestHelper::invokeMethod(
            $orderHelper,
            'createOrderInvoice',
            [$order, self::TRANSACTION_ID, 0]
        );
    }

    /**
     * @test
     * that createCreditMemoForHookRequest creates the credit memo without adjustments when the refund is full (refund amount is equal or greater than order total)
     *
     * @covers ::createCreditMemoForHookRequest
     *
     * @throws Exception from {@see CurrencyUtils::toMajor} method when unknown currency code is passed
     */
    public function createCreditMemoForHookRequest_ifFullRefund_createsCreditMemoWithAdjustmentPositive()
    {
        $transaction = (object)[
            'order' => (object)[
                'cart' => (object)[
                    'total_amount' => (object)[
                        'amount' => 10000,
                    ],
                ]
            ],
            'amount' => (object)[
                'amount' => 10000,
            ],
        ];

        $order = TestUtils::createDumpyOrder();
        $invoiceService = $this->objectManager->create(InvoiceService::class);
        $invoice = $invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        $invoice->setTransactionId('xxxx');
        $invoice->setBaseGrandTotal(100);
        $invoice->setGrandTotal(100);
        $invoice->register();

        $this->orderHelper->createCreditMemoForHookRequest($order, $transaction);
        self::assertTrue($order->getCreditmemosCollection()->getSize() > 0);
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * that createCreditMemoForHookRequest creates credit memo using adjustments when the refund is partial (refund amount is lower than order total)
     *
     * @covers ::createCreditMemoForHookRequest
     *
     * @throws Exception from {@see CurrencyUtils::toMajor} method when unknown currency code is passed
     */
    public function createCreditMemoForHookRequest_ifPartialRefund_createsCreditMemoWithAdjustmentPositive()
    {
        $transaction = (object)[
            'order' => (object)[
                'cart' => (object)[
                    'total_amount' => (object)[
                        'amount' => 10000,
                    ],
                ]
            ],
            'amount' => (object)[
                'amount' => 2000,
            ],
        ];

        $order = TestUtils::createDumpyOrder();
        $invoiceService = $this->objectManager->create(InvoiceService::class);
        $invoice = $invoiceService->prepareInvoiceWithoutItems($order, 100);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        $invoice->setTransactionId('xxxx');
        $invoice->setBaseGrandTotal(100);
        $invoice->setGrandTotal(100);
        $invoice->register();

        $this->orderHelper->createCreditMemoForHookRequest($order, $transaction);
        self::assertTrue($order->getCreditmemosCollection()->getSize() > 0);
        TestUtils::cleanupSharedFixtures([$order]);
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
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $invoice = $this->createPartialMock(
            Invoice::class,
            [
                'setRequestedCaptureCase', 'setTransactionId', 'setBaseGrandTotal',
                'setGrandTotal', 'register',
            ]
        );

        $order = $this->createPartialMock(
            Order::class,
            [
                'getOrderCurrencyCode','getTotalInvoiced', 'getGrandTotal',
                'addStatusHistoryComment', 'setIsCustomerNotified', 'register','getStoreId'
            ]
        );
        $order->method('getOrderCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $order->expects(static::once())->method('getTotalInvoiced')->willReturn(5);
        $order->expects(static::once())->method('getGrandTotal')->willReturn(5);
        $order->expects(static::once())->method('getStoreId')->willReturn(self::STORE_ID);
        $order->method('addStatusHistoryComment')->willReturn($order);
        $order->method('setIsCustomerNotified')->willReturn($order);

        $invoiceService = $this->createPartialMock(
            InvoiceService::class,
            ['prepareInvoice','prepareInvoiceWithoutItems']
        );
        $invoiceService->expects(static::once())->method('prepareInvoice')->willReturn($invoice);

        $scopeConfig = $this->createPartialMock(ScopeConfigInterface::class, ['isSetFlag','getValue']);
        $scopeConfig->expects(static::once())->method('isSetFlag')
            ->with(InvoiceEmailIdentity::XML_PATH_EMAIL_ENABLED, ScopeInterface::SCOPE_STORE, self::STORE_ID)
            ->willReturn(false);

        $invoiceSender= $this->createPartialMock(
            InvoiceSender::class,
            ['send']
        );
        $invoiceSender->expects(static::never())->method('send');
        $bugsnag = $this->createPartialMock(Bugsnag::class, ['notifyException']);
        $bugsnag->expects(self::never())->method('notifyException');

        TestHelper::setInaccessibleProperty($orderHelper, 'invoiceService', $invoiceService);
        TestHelper::setInaccessibleProperty($orderHelper, 'bugsnag', $bugsnag);
        TestHelper::setInaccessibleProperty($orderHelper, 'invoiceSender', $invoiceSender);
        TestHelper::setInaccessibleProperty($orderHelper, 'scopeConfig', $scopeConfig);
        TestHelper::invokeMethod(
            $orderHelper,
            'createOrderInvoice',
            [$order, self::TRANSACTION_ID, 0]
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
        static::assertEquals($expectedResult, $this->orderHelper->isZeroAmountHook($transactionState));
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
            TestHelper::invokeMethod($this->orderHelper, 'isCaptureHookRequest', [$newCapture])
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
        $order = TestUtils::createDumpyOrder(
            ['increment_id' => '100000007']
        );
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Capture amount is invalid');
        TestHelper::invokeMethod($this->orderHelper, 'validateCaptureAmount', [$order, null]);
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
        $order = TestUtils::createDumpyOrder([
            'total_invoiced' => 200,
            'grand_total' => 100,
            'increment_id' => '100000008'
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(sprintf(
            'Capture amount is invalid: capture amount [%s], previously captured [%s], grand total [%s]',
            10000,
            20000,
            10000
        ));
        TestHelper::invokeMethod($this->orderHelper, 'validateCaptureAmount', [$order, 100]);
        TestUtils::cleanupSharedFixtures([$order]);
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

        $order = TestUtils::createDumpyOrder([
            'total_invoiced' => 50,
            'grand_total' => 100,
        ]);

        TestHelper::invokeMethod($this->orderHelper, 'validateCaptureAmount', [$order, 50]);
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
     * @covers ::formatAmountForDisplay
     */
    public function formatAmountForDisplay_withValidAmount_returnsFormattedAmount()
    {

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
        static::assertNull($this->orderHelper->getStoreIdByQuoteId(''));
    }

    /**
     * @test
     *
     * @covers ::getOrderStoreIdByDisplayId
     */
    public function getOrderStoreIdByDisplayId_withEmptyDisplayId_returnsNull()
    {
        static::assertNull($this->orderHelper->getOrderStoreIdByDisplayId(''));
    }

    /**
     * @test
     *
     * @covers ::getOrderStoreIdByDisplayId
     */
    public function getOrderStoreIdByDisplayId_withInvalidDisplayId_returnsNull()
    {

        static::assertNull($this->orderHelper->getOrderStoreIdByDisplayId(' / '));
    }

    /**
     * @test
     *
     * @covers ::getOrderStoreIdByDisplayId
     * @covers ::getDataFromDisplayID
     */
    public function getOrderStoreIdByDisplayId_withValidDisplayId_returnsStoreId()
    {

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
        self::assertEquals($quote->getBillingAddress()->getCity(), 'Beverly Hills');
        self::assertEquals($quote->getBillingAddress()->getCountryId(), 'US');
        self::assertEquals($quote->getBillingAddress()->getCompany(), 'Bolt');
        self::assertEquals($quote->getBillingAddress()->getEmail(), self::CUSTOMER_EMAIL);
        self::assertEquals($quote->getBillingAddress()->getTelephone(), '0123456789');
    }

    /**
     * @test
     *
     * @covers ::setShippingAddress
     */
    public function setShippingAddress()
    {

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
        self::assertEquals($quote->getShippingAddress()->getCity(), 'Beverly Hills');
        self::assertEquals($quote->getShippingAddress()->getCountryId(), 'US');
        self::assertEquals($quote->getShippingAddress()->getCompany(), 'Bolt');
        self::assertEquals($quote->getShippingAddress()->getEmail(), self::CUSTOMER_EMAIL);
        self::assertEquals($quote->getShippingAddress()->getTelephone(), '0123456789');
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
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $configData = [
            [
                'path'    => ConfigHelper::XML_PATH_ENABLE_STORE_PICKUP_FEATURE,
                'value'   => true,
                'scope'   => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path'    => ConfigHelper::XML_PATH_PICKUP_SHIPPING_METHOD_CODE,
                'value'   => static::REFERENCE_ID,
                'scope'   => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => ConfigHelper::XML_PATH_PICKUP_STREET,
                'value' => ConfigTest::STORE_PICKUP_STREET,
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => ConfigHelper::XML_PATH_PICKUP_CITY,
                'value' => ConfigTest::STORE_PICKUP_CITY,
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => ConfigHelper::XML_PATH_PICKUP_ZIP_CODE,
                'value' => ConfigTest::STORE_PICKUP_ZIP_CODE,
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => ConfigHelper::XML_PATH_PICKUP_COUNTRY_ID,
                'value' => ConfigTest::STORE_PICKUP_COUNTRY_ID,
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => ConfigHelper::XML_PATH_PICKUP_REGION_ID,
                'value' => ConfigTest::STORE_PICKUP_REGION_ID,
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => ConfigHelper::XML_PATH_PICKUP_APARTMENT,
                'value' => ConfigTest::STORE_PICKUP_APARTMENT,
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];

        TestUtils::setupBoltConfig($configData);

        $transaction = (object)[
            'order' => (object)[
                'cart' => (object)[
                    'billing_address' => (object)static::ADDRESS_DATA,
                    'shipments' => [
                        (object)[
                            'shipping_address' => (object)static::ADDRESS_DATA,
                            'reference' => static::REFERENCE_ID,
                        ]
                    ]
                ]
            ]
        ];
        $quote = TestUtils::createQuote();
        TestHelper::invokeMethod($orderHelper, 'setShippingAddress', [$quote, $transaction]);

        self::assertEquals($quote->getShippingAddress()->getCity(), ConfigTest::STORE_PICKUP_CITY);
        self::assertEquals($quote->getShippingAddress()->getCountryId(), ConfigTest::STORE_PICKUP_COUNTRY_ID);
        self::assertEquals($quote->getShippingAddress()->getStreet()[0], ConfigTest::STORE_PICKUP_STREET);
        self::assertEquals($quote->getShippingAddress()->getPostCode(), ConfigTest::STORE_PICKUP_ZIP_CODE);
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

        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $order = TestUtils::createDumpyOrder(['quote_id' => self::QUOTE_ID]);
        $this->assertEquals($order->getId(), $boltHelperOrder->getOrderByQuoteId(self::QUOTE_ID)->getId());
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * @covers ::voidTransactionOnBolt
     *
     * @param $data
     * @dataProvider voidTransactionOnBolt_dataProvider
     * @throws LocalizedException
     * @throws Zend_Http_Client_Exception
     */
    public function voidTransactionOnBolt($data)
    {
        $boltHelperOrder = Bootstrap::getObjectManager()->create(OrderHelper::class);
        $configHelper = $this->createMock(ConfigHelper::class);
        $responseFactory = $this->createPartialMock(ResponseFactory::class, ['getResponse']);
        $dataObjectFactory = $this->getMockBuilder(DataObjectFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create','setApiData', 'setDynamicApiUrl','setApiKey'])
            ->getMock();
        $apiHelper = $this->createMock(ApiHelper::class);
        $boltRequest = $this->createMock(ApiHelper::class);

        $configHelper->expects(self::once())->method('getApiKey')->willReturnSelf();
        $responseFactory->expects(self::once())->method('getResponse')->willReturn(json_decode($data['response']));

        $dataObjectFactory->expects(self::once())->method('create')->willReturnSelf();
        $dataObjectFactory->expects(self::once())->method('setDynamicApiUrl')->with(ApiHelper::API_VOID_TRANSACTION)->willReturnSelf();
        $dataObjectFactory->expects(self::once())->method('setApiKey')->willReturnSelf();

        $apiHelper->expects(self::once())->method('buildRequest')
            ->with($dataObjectFactory, self::STORE_ID)->willReturn($boltRequest);
        $apiHelper->expects(self::once())->method('sendRequest')
            ->withAnyParameters()->willReturn($responseFactory);

        if ($data['exception']) {
            $this->expectException(LocalizedException::class);
            $this->expectExceptionMessage($data['exception_message']);
        }
        TestHelper::setInaccessibleProperty($boltHelperOrder, 'configHelper', $configHelper);
        TestHelper::setInaccessibleProperty($boltHelperOrder, 'dataObjectFactory', $dataObjectFactory);
        TestHelper::setInaccessibleProperty($boltHelperOrder, 'apiHelper', $apiHelper);
        $boltHelperOrder->voidTransactionOnBolt(self::TRANSACTION_ID, self::STORE_ID);
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
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $this->mockFetchTransactionInfo($orderHelper, $data['transaction']);
        $quote = $this->objectManager->create(Quote::class);
        $quote->setCustomerId($data['customer_id']);
        $cartHelper = $this->createPartialMock(CartHelper::class, ['getQuoteById']);
        $cartHelper->expects(static::once())->method('getQuoteById')->withAnyParameters()
            ->willReturn($quote);

        TestHelper::setProperty($orderHelper,'cartHelper', $cartHelper);
        $result = $orderHelper->saveCustomerCreditCard(self::REFERENCE, self::STORE_ID);
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

    private function mockTransactionData()
    {
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

    private function mockFetchTransactionInfo($orderHelper, $transaction)
    {
        $responseFactory = $this->createPartialMock(ResponseFactory::class, ['getResponse']);
        $dataObjectFactory = $this->getMockBuilder(DataObjectFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create','setRequestMethod', 'setDynamicApiUrl','setApiKey'])
            ->getMock();
        $apiHelper = $this->createMock(ApiHelper::class);
        $boltRequest = $this->createMock(Request::class);


        $responseFactory->expects(self::once())->method('getResponse')->willReturn($transaction);

        $dataObjectFactory->expects(self::once())->method('create')->willReturnSelf();
        $dataObjectFactory->expects(self::once())->method('setDynamicApiUrl')->willReturnSelf();
        $dataObjectFactory->expects(self::once())->method('setApiKey')->willReturnSelf();
        $dataObjectFactory->expects(self::once())->method('setRequestMethod')->willReturnSelf();

        $apiHelper->expects(self::once())->method('buildRequest')
            ->with($dataObjectFactory, self::STORE_ID)->willReturn($boltRequest);
        $apiHelper->expects(self::once())->method('sendRequest')
            ->withAnyParameters()->willReturn($responseFactory);


        TestHelper::setInaccessibleProperty($orderHelper,'dataObjectFactory', $dataObjectFactory);
        TestHelper::setInaccessibleProperty($orderHelper,'apiHelper', $apiHelper);
    }

    /**
     * @test
     * @covers ::saveCustomerCreditCard
     */
    public function testSaveCustomerCreditCard_validData()
    {
        $orderHelper = $this->objectManager->create(OrderHelper::class);

        $quote = TestUtils::createQuote();

        $transactionData = new \stdClass();
        $transactionData->from_consumer = new \stdClass();
        $transactionData->from_credit_card = new \stdClass();
        $transactionData->order = new \stdClass();
        $transactionData->order->cart = new \stdClass();

        $transactionData->from_consumer->id = 2;
        $transactionData->from_credit_card->id = 2;
        $transactionData->order->cart->order_reference = $quote->getId();
        $transactionData->order->cart->metadata = new \stdClass();
        $transactionData->order->cart->metadata->immutable_quote_id = self::IMMUTABLE_QUOTE_ID;

        $addressInfo = TestUtils::createSampleAddress();
        TestUtils::createCustomer($this->storeId, $this->websiteId, $addressInfo);

        $customerRepository = $this->objectManager->get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
        $customer = $customerRepository->get($addressInfo['email_address']);

        $quote->setCustomer($customer);
        $quoteRepository = $this->objectManager->get(\Magento\Quote\Model\QuoteRepository::class);
        $quoteRepository->save($quote);

        $this->mockFetchTransactionInfo($orderHelper, $transactionData);

        $result = $orderHelper->saveCustomerCreditCard(self::REFERENCE, self::STORE_ID);

        $creditCardCollection = $this->objectManager->create(CustomerCreditCardCollection::class);
        $creditCards = $creditCardCollection->getCreditCards($customer->getId(), $transactionData->from_consumer->id, $transactionData->from_credit_card->id);
        $this->assertTrue($creditCards->getSize() > 0);
        $this->assertTrue($result);
    }

    /**
     * @test
     * @covers ::saveCustomerCreditCard
     */
    public function testSaveCustomerCreditCard_IfParentQuoteDoesNotExist()
    {
        $orderHelper = $this->objectManager->create(OrderHelper::class);

        $quote = TestUtils::createQuote();

        $transactionData = new \stdClass();
        $transactionData->from_consumer = new \stdClass();
        $transactionData->from_credit_card = new \stdClass();
        $transactionData->order = new \stdClass();
        $transactionData->order->cart = new \stdClass();

        $transactionData->from_consumer->id = 1;
        $transactionData->from_credit_card->id = 1;
        $transactionData->order->cart->order_reference = self::QUOTE_ID;
        $transactionData->order->cart->metadata = new \stdClass();
        $transactionData->order->cart->metadata->immutable_quote_id = $quote->getId();
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();

        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $websiteId = $websiteRepository->get('base')->getId();

        $addressInfo = TestUtils::createSampleAddress();
        TestUtils::createCustomer($storeId, $websiteId, $addressInfo);

        $customerRepository = $this->objectManager->get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
        $customer = $customerRepository->get($addressInfo['email_address']);

        $quote->setCustomer($customer);
        $quoteRepository = $this->objectManager->get(\Magento\Quote\Model\QuoteRepository::class);
        $quoteRepository->save($quote);

        $this->mockFetchTransactionInfo($orderHelper, $transactionData);

        $result = $orderHelper->saveCustomerCreditCard(self::REFERENCE, self::STORE_ID);

        $creditCardCollection = $this->objectManager->create(CustomerCreditCardCollection::class);
        $creditCards = $creditCardCollection->getCreditCards($customer->getId(), $transactionData->from_consumer->id, $transactionData->from_credit_card->id);
        $this->assertTrue(count($creditCards) > 0);
        $this->assertTrue($result);
    }

    /**
     * @test
     * @covers ::saveCustomerCreditCard
     */
    public function testSaveCustomerCreditCard_IfQuoteDoesNotExist()
    {
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $transaction = $this->mockTransactionData();
        $this->mockFetchTransactionInfo($orderHelper, $transaction);

        $result = $orderHelper->saveCustomerCreditCard(self::REFERENCE, self::STORE_ID);
        $this->assertFalse($result);
    }

    /**
     * @test
     * @covers ::saveCustomerCreditCard
     */
    public function testSaveCustomerCreditCard_withException()
    {
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $result = $orderHelper->saveCustomerCreditCard(self::REFERENCE, self::STORE_ID);
        $this->assertFalse($result);
    }

    /**
     * @test
     * @covers ::saveCustomerCreditCard
     */
    public function testSaveCustomerCreditCard_ignoreCreditCardCreationLogicIfCardExists()
    {
        $orderHelper = $this->objectManager->create(OrderHelper::class);
        $customerCreditCardFactory = $this->objectManager->create(CustomerCreditCardFactory::class);
        $transactionData = $this->mockTransactionData();
        $quote = TestUtils::createQuote();

        $transactionData->from_consumer->id = 666;
        $transactionData->from_credit_card->id = 666;
        $transactionData->order->cart->order_reference = $quote->getId();

        $store = $this->objectManager->get(StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();

        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $websiteId = $websiteRepository->get('base')->getId();

        $addressInfo = TestUtils::createSampleAddress();
        TestUtils::createCustomer($storeId, $websiteId, $addressInfo);

        $customerRepository = $this->objectManager->get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
        $customer = $customerRepository->get($addressInfo['email_address']);

        $quote->setCustomer($customer);
        $quoteRepository = $this->objectManager->get(\Magento\Quote\Model\QuoteRepository::class);
        $quoteRepository->save($quote);

        $customerCreditCardFactory->create()->saveCreditCard(
            $customer->getId(),
            666,
            666,
            []
        );

        $this->mockFetchTransactionInfo($orderHelper, $transactionData);

        $result = $orderHelper->saveCustomerCreditCard(self::REFERENCE, self::STORE_ID);
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
        $order = Bootstrap::getObjectManager()->create(OrderModel::class);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Refund amount is invalid');
        TestHelper::invokeMethod($this->orderHelper, 'validateRefundAmount', [$order, $refundAmount]);
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

        $magentoTotalAmount = CurrencyUtils::toMinor($grandTotal, 'USD');
        $totalMismatch = $cartTotalAmount - $magentoTotalAmount;
        $recordMismatch = abs($totalMismatch) > 0 && abs($totalMismatch) <= $priceFaultTolerance;
        $taxAmountBeforeAdjustingPriceMismatch = $order->getTaxAmount();
        $baseTaxAmountBeforeAdjustingPriceMismatch = $order->getBaseTaxAmount();

        TestHelper::invokeMethod(
            $orderHelper,
            'adjustPriceMismatch',
            [$transaction, $order, $quote]
        );

        if ($recordMismatch) {
            self::assertEquals(CurrencyUtils::toMajor($cartTotalAmount, 'USD'), $order->getGrandTotal());
            self::assertEquals(CurrencyUtils::toMajor($cartTotalAmount, 'USD'), $order->getBaseGrandTotal());
            self::assertEquals($taxAmountBeforeAdjustingPriceMismatch + CurrencyUtils::toMajor($totalMismatch, 'USD'), $order->getTaxAmount());
            self::assertEquals($baseTaxAmountBeforeAdjustingPriceMismatch + CurrencyUtils::toMajor($totalMismatch, 'USD'), $order->getBaseTaxAmount());
        } else {
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
    public function getExistingOrder_byParenQuoteId()
    {
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
        $orderHelper = Bootstrap::getObjectManager()->create(\Bolt\Boltpay\Helper\Order::class);
        $orderManagementMock = $this->createPartialMock(OrderManagement::class, [
            'cancel'
        ]);
        $orderManagementMock->expects(static::never())->method('cancel');

        TestHelper::setInaccessibleProperty($orderHelper,'orderManagement', $orderManagementMock);
        $this->assertNull($orderHelper->cancelFailedPaymentOrder(self::DISPLAY_ID, self::IMMUTABLE_QUOTE_ID));
    }

    /**
     * @test
     * @covers ::deleteOrCancelFailedPaymentOrder
     */
    public function deleteOrCancelFailedPaymentOrder_deleteOrderByIncrementId()
    {

        $quote = TestUtils::createQuote();
        $order = TestUtils::createDumpyOrder(
            [
                'state' => OrderModel::STATE_PENDING_PAYMENT,
                'quote_id' => $quote->getId()
            ]
        );
        $displayId = $order->getIncrementId();
        TestUtils::setSecureAreaIfNeeded();
        self::assertEquals(
            'Order was deleted: '.$displayId,
            $this->orderHelper->deleteOrCancelFailedPaymentOrder($displayId, $quote->getId())
        );
    }

    /**
     * @test
     * @covers ::deleteOrCancelFailedPaymentOrder
     */
    public function deleteOrCancelFailedPaymentOrder_cancelFailedPaymentOrder()
    {
        $featureSwitch = TestUtils::saveFeatureSwitch(
            \Bolt\Boltpay\Helper\FeatureSwitch\Definitions::M2_CANCEL_FAILED_PAYMENT_ORDERS_INSTEAD_OF_DELETING,
            true
        );
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
            $this->orderHelper->deleteOrCancelFailedPaymentOrder($displayId, $quote->getId())
        );
        TestUtils::cleanupSharedFixtures([$order]);
        TestUtils::cleanupFeatureSwitch($featureSwitch);
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
        $order = TestUtils::createDumpyOrder(['state' => OrderModel::STATE_PROCESSING]);
        $incrementId = $order->getIncrementId();
        $orderHelper = Bootstrap::getObjectManager()->create(\Bolt\Boltpay\Helper\Order::class);
        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(
            sprintf(
                "Order Delete Error. Order is in invalid state. Order #: %s State: %s Immutable Quote ID: %d",
                $incrementId,
                OrderModel::STATE_PROCESSING,
                self::QUOTE_ID
            )
        );
        $this->expectExceptionCode(CreateOrder::E_BOLT_GENERAL_ERROR);
        $orderHelper->cancelFailedPaymentOrder($incrementId, self::QUOTE_ID);
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * that addCustomerDetails will assign customer name from transaction billing address if quote is guest
     *
     * @covers ::addCustomerDetails
     */
    public function addCustomerDetails_ifNoCustomerId_assignsFirstAndLastNameFromTransaction()
    {
        $quote = TestUtils::createQuote();

        $transaction = new stdClass();
        @$transaction->order->cart->billing_address->first_name = 'Bolt';
        @$transaction->order->cart->billing_address->last_name = 'Team';
        $featureSwitch = TestUtils::saveFeatureSwitch(
            \Bolt\Boltpay\Helper\FeatureSwitch\Definitions::M2_SET_CUSTOMER_NAME_TO_ORDER_FOR_GUESTS,
            true
        );

        TestHelper::invokeMethod($this->orderHelper, 'addCustomerDetails', [$quote, self::CUSTOMER_EMAIL, $transaction]);
        self::assertEquals('Bolt', $quote->getCustomerFirstname());
        self::assertEquals('Team', $quote->getCustomerLastname());
        TestUtils::cleanupFeatureSwitch($featureSwitch);
    }

    /**
     * @test
     * that addCustomerDetails will assign checkout method, customer group, is guest
     *
     * @covers ::addCustomerDetails
     */
    public function addCustomerDetails_ifNoCustomerIdAndCheckoutTypeIsNotBackOffice_assignAdditionalInfo()
    {
        $quote = TestUtils::createQuote();
        $quote->setData('bolt_checkout_type', CartHelper::BOLT_CHECKOUT_TYPE_MULTISTEP);
        $transaction = new stdClass();
        @$transaction->order->cart->billing_address->first_name = 'Bolt';
        @$transaction->order->cart->billing_address->last_name = 'Team';
        $featureSwitch = TestUtils::saveFeatureSwitch(
            \Bolt\Boltpay\Helper\FeatureSwitch\Definitions::M2_SET_CUSTOMER_NAME_TO_ORDER_FOR_GUESTS,
            true
        );

        TestHelper::invokeMethod($this->orderHelper, 'addCustomerDetails', [$quote, self::CUSTOMER_EMAIL, $transaction]);
        self::assertEquals('Bolt', $quote->getCustomerFirstname());
        self::assertEquals('Team', $quote->getCustomerLastname());

        self::assertEquals(null, $quote->getCustomerId());
        self::assertEquals('guest', $quote->getCheckoutMethod());
        self::assertEquals(true, $quote->getCustomerIsGuest());
        self::assertEquals(\Magento\Customer\Api\Data\GroupInterface::NOT_LOGGED_IN_ID, $quote->getCustomerGroupId());
        TestUtils::cleanupFeatureSwitch($featureSwitch);
    }
}
