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
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\OrderRepositoryInterface as OrderRepository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Exception\BoltException;

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
     * @var CartHelper
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
                $this->date
            ])
            ->setMethods([
                'getExistingOrder',
                'deleteOrder',
                'cancelOrder',
                'hasSamePrice',
                'orderPostprocess',
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
        $this->invoiceService = $this->createMock(InvoiceService::class);
        $this->invoiceSender = $this->createMock(InvoiceSender::class);
        $this->transactionBuilder = $this->createMock(TransactionBuilder::class);
        $this->timezone = $this->createMock(TimezoneInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->cartHelper = $this->createMock(CartHelper::class);
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
                'addStatusHistoryComment',
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
}
