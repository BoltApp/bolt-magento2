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
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Model\Api\CreateOrder;
use Bolt\Boltpay\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Invoice as Invoice;
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
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Model\CustomerCreditCardFactory;
use Bolt\Boltpay\Test\Unit\Model\Api\OrderManagementTest;

/**
 * Class OrderTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper
 * @coversDefaultClass \Bolt\Boltpay\Helper\Order
 */
class OrderTest extends TestCase
{
    const INCREMENT_ID = 1234;
    const QUOTE_ID = 5678;
    const DISPLAY_ID = self::INCREMENT_ID . " / " . self::QUOTE_ID;
    const CUSTOMER_ID = 1111;

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
     * @var MockObject|QuoteManagement
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
     * @var MockObject|Bugsnag
     */
    private $bugsnag;

    /**
     * @var MockObject|CartHelper
     */
    private $cartHelper;

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
     * @var MockObject|OrderHelper
     */
    private $currentMock;

    /**
     * @var MockObject|Order
     */
    private $orderMock;
    /**
     * @var MockObject|Config
     */
    private $orderConfigMock;
    /**
     * @var MockObject
     */
    private $context;
    /**
     * @var MockObject|Quote
     */
    private $quoteMock;

    /**
     * @var InfoInterface
     */
    private $paymentMock;

    /**
     * @var \stdClass
     */
    private $transactionMock;

    /**
     * @var MockObject|CustomerCreditCardFactory
     */
    private $customerCreditCardFactory;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    private function initCurrentMock()
    {
        $this->currentMock = $this->getMockBuilder(OrderHelper::class)
            ->setConstructorArgs([
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
                $this->customerCreditCardFactory
            ])
            ->setMethods([
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
                'fetchTransactionInfo'
            ])
            ->getMock();
    }

    private function initRequiredMocks()
    {
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
        $this->cartHelper = $this->getMockBuilder(CartHelper::class)
            ->disableOriginalConstructor()
            ->setMethods(['getRoundAmount', 'getQuoteById'])
            ->getMock();
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->sessionHelper = $this->createMock(SessionHelper::class);
        $this->discountHelper = $this->createMock(DiscountHelper::class);
        $this->date = $this->createMock(DateTime::class);
        $this->customerCreditCardFactory = $this->getMockBuilder(CustomerCreditCardFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create','setCustomerId','setConsumerId', 'setCreditCardId','setCardInfo','save'])
            ->getMock();

        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCustomerId','getReservedOrderId','getId'])
            ->getMock();

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
            ]
        );
        $this->orderConfigMock = $this->createPartialMock(
            Config::class,
            [
                'getStateDefaultStatus'
            ]
        );
        $this->orderMock->method('getConfig')->willReturn($this->orderConfigMock);
    }
    
    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
    
        return $method->invokeArgs($object, $parameters);
    }

    /**
     * @test
     * @covers ::deleteOrderByIncrementId
     */
    public function deleteOrderByIncrementId_noOrder()
    {
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn(null);
        $this->orderMock->expects(static::never())->method('getState');
        $this->currentMock->expects(static::never())->method('deleteOrder');
        $this->currentMock->deleteOrderByIncrementId(self::DISPLAY_ID);
    }

    /**
     * @test
     * @covers ::deleteOrderByIncrementId
     */
    public function deleteOrderByIncrementId_invalidState()
    {
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn($this->orderMock);
        $state = Order::STATE_NEW;
        $this->orderMock->expects(static::once())->method('getState')->willReturn($state);
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(\Bolt\Boltpay\Model\Api\CreateOrder::E_BOLT_GENERAL_ERROR);
        $this->expectExceptionMessage(
            sprintf(
                'Order Delete Error. Order is in invalid state. Order #: %s State: %s Immutable Quote ID: %s',
                self::INCREMENT_ID,
                $state,
                self::QUOTE_ID
            )
        );
        $this->currentMock->expects(static::never())->method('deleteOrder');
        $this->currentMock->deleteOrderByIncrementId(self::DISPLAY_ID);
    }

    /**
     * @test
     * @covers ::deleteOrderByIncrementId
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
     * @covers ::tryDeclinedPaymentCancelation
     */
    public function tryDeclinedPaymentCancelation_noOrder()
    {
        $this->expectException(\Bolt\Boltpay\Exception\BoltException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Order Cancelation Error. Order does not exist. Order #: %s Immutable Quote ID: %s',
                self::INCREMENT_ID,
                self::QUOTE_ID
            )
        );
        $this->expectExceptionCode(CreateOrder::E_BOLT_GENERAL_ERROR);
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn(false);

        $this->currentMock->tryDeclinedPaymentCancelation(self::DISPLAY_ID);
    }

    /**
     * @test
     * @covers ::tryDeclinedPaymentCancelation
     */
    public function tryDeclinedPaymentCancelation_pendingOrder()
    {
        $state = Order::STATE_PENDING_PAYMENT;
        $this->orderMock->expects(static::exactly(2))->method('getState')
            ->willReturnOnConsecutiveCalls($state, Order::STATE_CANCELED);
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn($this->orderMock);
        $this->currentMock->expects(static::once())->method('cancelOrder')->with($this->orderMock);

        self::assertTrue($this->currentMock->tryDeclinedPaymentCancelation(self::DISPLAY_ID));
    }

    /**
     * @test
     * @covers ::tryDeclinedPaymentCancelation
     */
    public function tryDeclinedPaymentCancelation_canceledOrder()
    {
        $state = Order::STATE_CANCELED;
        $this->orderMock->expects(static::exactly(2))->method('getState')->willReturn($state);
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn($this->orderMock);
        $this->currentMock->expects(static::never())->method('cancelOrder')->with($this->orderMock);
        $this->orderMock->expects(static::never())->method('save');

        self::assertTrue($this->currentMock->tryDeclinedPaymentCancelation(self::DISPLAY_ID));
    }

    /**
     * @test
     * @covers ::tryDeclinedPaymentCancelation
     */
    public function tryDeclinedPaymentCancelation_completeOrder()
    {
        $state = Order::STATE_COMPLETE;
        $this->orderMock->expects(static::exactly(2))->method('getState')->willReturn($state);
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn($this->orderMock);
        $this->currentMock->expects(static::never())->method('cancelOrder')->with($this->orderMock);
        $this->orderMock->expects(static::never())->method('save');

        self::assertFalse($this->currentMock->tryDeclinedPaymentCancelation(self::DISPLAY_ID));
    }

    /**
     * @test
     * @covers ::setOrderState
     */
    public function setOrderState_holdedOrder()
    {
        $state = Order::STATE_HOLDED;
        $prevState = Order::STATE_PENDING_PAYMENT;
        $this->orderMock->expects(static::once())->method('getState')->willReturn($prevState);
        $this->orderMock->expects(static::once())->method('hold');
        $this->orderMock->expects(static::once())->method('setState')->with(Order::STATE_PROCESSING);
        $this->orderConfigMock->expects(static::once())->method('getStateDefaultStatus')
            ->with(Order::STATE_PROCESSING)->willReturn(Order::STATE_PROCESSING);
        $this->orderMock->expects(static::once())->method('setStatus')->with(Order::STATE_PROCESSING);
        $this->orderMock->expects(static::once())->method('save');
        $this->currentMock->setOrderState($this->orderMock, $state);
    }

    /**
     * @test
     * @covers ::setOrderState
     */
    public function setOrderState_holdedOrderWithException()
    {
        $state = Order::STATE_HOLDED;
        $prevState = Order::STATE_PENDING_PAYMENT;
        $this->orderMock->expects(static::once())->method('getState')->willReturn($prevState);
        $this->orderMock->expects(static::once())->method('hold')->willThrowException(new \Exception());
        $this->orderMock->expects(static::exactly(2))->method('setState')
            ->withConsecutive([Order::STATE_PROCESSING], [Order::STATE_HOLDED]);
        $this->orderConfigMock->expects(static::exactly(2))->method('getStateDefaultStatus')
            ->withConsecutive([Order::STATE_PROCESSING], [Order::STATE_HOLDED])
            ->willReturnArgument(0);
        $this->orderMock->expects(static::exactly(2))->method('setStatus')
            ->withConsecutive([Order::STATE_PROCESSING], [Order::STATE_HOLDED]);
        $this->orderMock->expects(static::once())->method('save');
        $this->currentMock->setOrderState($this->orderMock, $state);
    }

    /**
     * @test
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
     * @covers ::setOrderState
     */
    public function setOrderState_canceledOrderForRejectedIrreversibleHook()
    {
        $state = Order::STATE_CANCELED;
        $prevState = Order::STATE_PAYMENT_REVIEW;
        $this->orderMock->expects(static::once())->method('getState')->willReturn($prevState);
        $this->orderMock->expects(static::once())->method('canCancel')->willReturn(false);
        $this->currentMock->expects(static::never())->method('cancelOrder')->with($this->orderMock);
        $this->orderMock->expects(static::once())->method('registerCancellation')->willReturn($this->orderMock);
        $this->orderMock->expects(static::never())->method('setState');
        $this->orderMock->expects(static::never())->method('setStatus');
        $this->orderMock->expects(static::once())->method('save');
        $this->currentMock->setOrderState($this->orderMock, $state);
    }

    /**
     * @test
     * @covers ::setOrderState
     */
    public function setOrderState_canceledOrderForRejectedIrreversibleHookWithException()
    {
        $state = Order::STATE_CANCELED;
        $prevState = Order::STATE_PAYMENT_REVIEW;
        $this->orderMock->expects(static::once())->method('getState')->willReturn($prevState);
        $this->orderMock->expects(static::once())->method('canCancel')->willReturn(false);
        $this->currentMock->expects(static::never())->method('cancelOrder')->with($this->orderMock);
        $this->orderMock->expects(static::once())->method('registerCancellation')->willThrowException(new \Exception());
        $this->orderMock->expects(static::once())->method('setState');
        $this->orderMock->expects(static::once())->method('setStatus');
        $this->orderMock->expects(static::once())->method('save');
        $this->currentMock->setOrderState($this->orderMock, $state);
    }

    /**
     * @test
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
     * @covers ::processExistingOrder
     */
    public function processExistingOrder_noOrder()
    {
        $this->quoteMock->expects(self::once())->method('getReservedOrderId')
            ->willReturn(self::INCREMENT_ID);
        $this->currentMock->expects(self::once())->method('getExistingOrder')
            ->with(self::INCREMENT_ID)->willReturn(false);
        self::assertFalse($this->currentMock->processExistingOrder($this->quoteMock, new \stdClass()));
    }

    /**
     * @test
     * @covers ::processExistingOrder
     */
    public function processExistingOrder_canceledOrder()
    {
        $this->quoteMock->expects(self::exactly(2))->method('getReservedOrderId')
            ->willReturn(self::INCREMENT_ID);
        $this->quoteMock->expects(self::once())->method('getId')
            ->willReturn(self::QUOTE_ID);
        $this->currentMock->expects(self::once())->method('getExistingOrder')
            ->with(self::INCREMENT_ID)->willReturn($this->orderMock);
        $this->orderMock->expects(self::once())->method('isCanceled')->willReturn(true);
        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Order has been canceled due to the previously declined payment. Order #: %s Quote ID: %s',
                self::INCREMENT_ID,
                self::QUOTE_ID
            )
        );
        $this->expectExceptionCode(CreateOrder::E_BOLT_REJECTED_ORDER);
        self::assertFalse($this->currentMock->processExistingOrder($this->quoteMock, new \stdClass()));
    }

    /**
     * @test
     * @covers ::processExistingOrder
     */
    public function processExistingOrder_pendingOrder()
    {
        $this->quoteMock->expects(self::exactly(2))->method('getReservedOrderId')
            ->willReturn(self::INCREMENT_ID);
        $this->quoteMock->expects(self::once())->method('getId')
            ->willReturn(self::QUOTE_ID);
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
                self::QUOTE_ID
            )
        );
        $this->expectExceptionCode(CreateOrder::E_BOLT_GENERAL_ERROR);
        self::assertFalse($this->currentMock->processExistingOrder($this->quoteMock, new \stdClass()));
    }

    /**
     * @test
     * @covers ::processExistingOrder
     */
    public function processExistingOrder_samePriceOrder()
    {
        $transaction = new \stdClass();
        $this->quoteMock->expects(self::once())->method('getReservedOrderId')
            ->willReturn(self::INCREMENT_ID);
        $this->currentMock->expects(self::once())->method('getExistingOrder')
            ->with(self::INCREMENT_ID)->willReturn($this->orderMock);
        $this->orderMock->expects(self::once())->method('isCanceled')
            ->willReturn(false);
        $this->orderMock->expects(self::once())->method('getState')
            ->willReturn(Order::STATE_CANCELED);
        $this->currentMock->expects(self::once())->method('hasSamePrice')
            ->with($this->orderMock,$transaction)->willReturn(true);

        self::assertEquals(
            $this->currentMock->processExistingOrder($this->quoteMock, $transaction),
            $this->orderMock
        );
    }

    /**
     * @test
     * @covers ::processExistingOrder
     */
    public function processExistingOrder_deleteOrder()
    {
        $transaction = new \stdClass();
        $this->quoteMock->expects(self::once())->method('getReservedOrderId')
            ->willReturn(self::INCREMENT_ID);
        $this->currentMock->expects(self::once())->method('getExistingOrder')
            ->with(self::INCREMENT_ID)->willReturn($this->orderMock);
        $this->orderMock->expects(self::once())->method('isCanceled')
            ->willReturn(false);
        $this->orderMock->expects(self::once())->method('getState')
            ->willReturn(Order::STATE_CANCELED);
        $this->currentMock->expects(self::once())->method('hasSamePrice')
            ->with($this->orderMock,$transaction)->willReturn(false);
        $this->currentMock->expects(self::once())->method('deleteOrder')
            ->with($this->orderMock);
        self::assertFalse(
            $this->currentMock->processExistingOrder($this->quoteMock, $transaction)
        );
    }


    /**
     * @test
     * @covers ::processNewOrder
     */
    public function processNewOrder_fail()
    {
        $transaction = new \stdClass();
        $this->quoteManagement->expects(self::once())->method('submit')
            ->with($this->quoteMock)->willReturn(null);
        $this->bugsnag->expects(self::once())->method('registerCallback');
        $this->quoteMock->expects(self::once())->method('getReservedOrderId')
            ->willReturn(self::INCREMENT_ID);
        $this->quoteMock->expects(self::once())->method('getId')
            ->willReturn(self::QUOTE_ID);
        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Quote Submit Error. Order #: %s Parent Quote ID: %s',
                self::INCREMENT_ID,
                self::QUOTE_ID
            )
        );
        $this->expectExceptionCode(CreateOrder::E_BOLT_GENERAL_ERROR);
        $this->currentMock->processNewOrder($this->quoteMock, $transaction);
    }

    /**
     * @test
     * @covers ::processNewOrder
     */
    public function processNewOrder_success()
    {
        $transaction = new \stdClass();
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
     */
    public function getTransactionState_CreditCardAuthorize()
    {
        $this->paymentMock = $this->getMockBuilder(InfoInterface::class)->setMethods(['getId', 'getOrder'])->getMockForAbstractClass();
        $map = array(
            array('transaction_state', 'cc_payment:pending'),
            array('transaction_reference', '000123'),
            array('real_transaction_id', 'ABCD-1234-XXXX'),
            array('captures', ""),
        );
        $this->paymentMock->expects($this->exactly(4))
            ->method('getAdditionalInformation')
            ->will($this->returnValueMap($map));

        $this->transactionMock = (object) ( array(
            'type' => "cc_payment",
            'status' => "authorized",
            'captures' => array(),
        ) );
        $state = $this->currentMock->getTransactionState($this->transactionMock, $this->paymentMock, NULL);
        $this->assertEquals($state, "cc_payment:authorized");
    }

    /**
     * @test
     */
    public function getTransactionState_PaypalCompleted()
    {
        $this->paymentMock = $this->getMockBuilder(InfoInterface::class)->setMethods(['getId', 'getOrder'])->getMockForAbstractClass();
        $map = array(
            array('transaction_state', 'cc_payment:pending'),
            array('transaction_reference', '000123'),
            array('real_transaction_id', 'ABCD-1234-XXXX'),
            array('captures', ""),
        );
        $this->paymentMock->expects($this->exactly(4))
            ->method('getAdditionalInformation')
            ->will($this->returnValueMap($map));

        $this->transactionMock = (object) ( array(
            'type' => "paypal_payment",
            'status' => "completed",
            'captures' => array(),
        ) );
        $state = $this->currentMock->getTransactionState($this->transactionMock, $this->paymentMock, NULL);
        $this->assertEquals($state, "cc_payment:completed");
    }

    /**
     * @test
     */
    public function getTransactionState_APMInitialAuthorized()
    {
        $this->paymentMock = $this->getMockBuilder(InfoInterface::class)->setMethods(['getId', 'getOrder'])->getMockForAbstractClass();
        $map = array(
            array('transaction_state', NULL),
            array('transaction_reference', NULL),
            array('real_transaction_id', NULL),
            array('captures', ""),
        );
        $this->paymentMock->expects($this->exactly(4))
            ->method('getAdditionalInformation')
            ->will($this->returnValueMap($map));

        $this->transactionMock = (object) ( array(
            'type' => "apm_payment",
            'status' => "authorized",
            'captures' => array(),
        ) );
        $state = $this->currentMock->getTransactionState($this->transactionMock, $this->paymentMock, NULL);
        $this->assertEquals($state, "cc_payment:pending");
    }
    
    /**
     * @test
     * @covers ::createOrderInvoice
     * @dataProvider additionAmountTotalProvider
     */
    public function createOrderInvoice_amountWithDifferentDecimals($amount, $grandTotal, $isSame) {
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
        $this->cartHelper->method('getRoundAmount')
                         ->will($this->returnCallback(function($amount) { return (int)round($amount * 100); }));
        $this->orderMock->expects(static::once())->method('getTotalInvoiced')->willReturn($totalInvoiced);
        $this->orderMock->expects(static::once())->method('getGrandTotal')->willReturn($grandTotal);
        $this->orderMock->method('addStatusHistoryComment')->willReturn($this->orderMock);
        $this->orderMock->method('setIsCustomerNotified')->willReturn($this->orderMock);
        if($isSame){
            $this->invoiceService->expects(static::once())->method('prepareInvoice')->willReturn($invoice);
        }
        else{
            $this->invoiceService->expects(static::once())->method('prepareInvoiceWithoutItems')->willReturn($invoice);
        }        
            
        $this->invokeMethod($this->currentMock, 'createOrderInvoice', array($this->orderMock, 'ABCD-1234-XXXX', $amount));
    }
    
    public function additionAmountTotalProvider() {
		return [
            [ 12.25, 12.25, true ],
            [ 12.00, 12.001, true ],
            [ 12.001, 12.00, true ],
            [ 12.1225, 12.1234, true ],
            [ 12.1234, 12.1225, true ],
            [ 12.13, 12.14, false ],
            [ 12.14, 12.13, false ],
            [ 12.123, 12.126, false ],
            [ 12.126, 12.123, false ],
            [ 12.1264, 12.1225, false ],
            [ 12.1225, 12.1264, false ],
		];
	}

    /**
     * @test
     * @param $data
     * @dataProvider providerTestSaveCustomerCreditCard_invalidData
     *
     */
    public function testSaveCustomerCreditCard_invalidData($data){
        $this->currentMock->expects(static::once())->method('fetchTransactionInfo')->with(OrderManagementTest::REFERENCE, OrderManagementTest::STORE_ID)
            ->willReturn($data['transaction']);
        $this->quoteMock->expects(static::once())->method('getCustomerId')
            ->willReturn($data['customer_id']);
        $this->cartHelper->expects(static::once())->method('getQuoteById')->withAnyParameters()
            ->willReturn($this->quoteMock);

        $this->customerCreditCardFactory->expects(static::never())->method('create');
        $this->customerCreditCardFactory->expects(static::never())->method('setCustomerId');
        $this->customerCreditCardFactory->expects(static::never())->method('setConsumerId');
        $this->customerCreditCardFactory->expects(static::never())->method('setCreditCardId');
        $this->customerCreditCardFactory->expects(static::never())->method('setCardInfo');
        $this->customerCreditCardFactory->expects(static::never())->method('save');

        $result = $this->currentMock->saveCustomerCreditCard(OrderManagementTest::REFERENCE,OrderManagementTest::STORE_ID);
        $this->assertFalse($result);

    }

    public function providerTestSaveCustomerCreditCard_invalidData(){
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

    /**
     * @test
     */
    public function testSaveCustomerCreditCard_validData(){
        $transaction = new \stdClass();
        @$transaction->from_consumer->id = 1;
        @$transaction->from_credit_card->id = 1;
        @$transaction->order->cart->order_reference = self::QUOTE_ID;

        $this->currentMock->expects(static::once())->method('fetchTransactionInfo')->with(OrderManagementTest::REFERENCE, OrderManagementTest::STORE_ID)
            ->willReturn($transaction);
        $this->quoteMock->expects(self::once())->method('getCustomerId')
            ->willReturn(self::CUSTOMER_ID);
        $this->cartHelper->expects(static::once())->method('getQuoteById')
            ->willReturn($this->quoteMock);

        $this->customerCreditCardFactory->expects(static::once())->method('create')->willReturnSelf();
        $this->customerCreditCardFactory->expects(static::once())->method('setCustomerId')->willReturnSelf();
        $this->customerCreditCardFactory->expects(static::once())->method('setConsumerId')->willReturnSelf();
        $this->customerCreditCardFactory->expects(static::once())->method('setCreditCardId')->willReturnSelf();
        $this->customerCreditCardFactory->expects(static::once())->method('setCardInfo')->willReturnSelf();
        $this->customerCreditCardFactory->expects(static::once())->method('save')->willReturnSelf();

        $result = $this->currentMock->saveCustomerCreditCard(OrderManagementTest::REFERENCE,OrderManagementTest::STORE_ID);
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function testSaveCustomerCreditCard_withException(){
        $transaction = new \stdClass();
        @$transaction->from_consumer->id = 1;
        @$transaction->from_credit_card->id = 1;
        @$transaction->order->cart->order_reference = self::QUOTE_ID;

        $this->currentMock->expects(static::once())->method('fetchTransactionInfo')->with(OrderManagementTest::REFERENCE, OrderManagementTest::STORE_ID)
            ->willReturn($transaction);
        $this->quoteMock->expects(self::once())->method('getCustomerId')
            ->willReturn(self::CUSTOMER_ID);
        $this->cartHelper->expects(static::once())->method('getQuoteById')
            ->willReturn($this->quoteMock);

        $this->customerCreditCardFactory->expects(static::once())->method('create')->willReturnSelf();
        $this->customerCreditCardFactory->expects(static::once())->method('setCustomerId')->willReturnSelf();
        $this->customerCreditCardFactory->expects(static::once())->method('setConsumerId')->willReturnSelf();
        $this->customerCreditCardFactory->expects(static::once())->method('setCreditCardId')->willReturnSelf();
        $this->customerCreditCardFactory->expects(static::once())->method('setCardInfo')->willReturnSelf();
        $this->customerCreditCardFactory->expects(static::once())->method('save')->willThrowException(new \Exception());

        $result = $this->currentMock->saveCustomerCreditCard(OrderManagementTest::REFERENCE,OrderManagementTest::STORE_ID);
        $this->assertFalse($result);
    }
}
