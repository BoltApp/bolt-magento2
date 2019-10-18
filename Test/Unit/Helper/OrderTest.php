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
use Bolt\Boltpay\Model\Service\InvoiceService;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\OrderRepositoryInterface as OrderRepository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Exception\BoltException;

/**
 * Class OrderTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper
 */
class OrderTest extends TestCase
{
    const INCREMENT_ID = 1234;
    const QUOTE_ID = 5678;

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
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $currentMock;

    /**
     * @var Order
     */
    private $orderMock;

    /**
     * @var InfoInterface
     */
    private $paymentMock;

    /**
     * @var \stdClass
     */
    private $transactionMock;

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
                'deleteOrder'
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

        $this->orderMock = $this->createPartialMock(
            Order::class,
            [
                'getState'
            ]);
    }

    /**
     * @test
     */
    public function deleteOrderByIncrementId_noOrder()
    {
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn(null);
        $this->orderMock->expects(static::never())->method('getState');
        $this->currentMock->expects(static::never())->method('deleteOrder');
        $this->currentMock->deleteOrderByIncrementId(self::INCREMENT_ID." / ".self::QUOTE_ID);
    }

    /**
     * @test
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
        $this->currentMock->deleteOrderByIncrementId(self::INCREMENT_ID." / ".self::QUOTE_ID);
    }

    /**
     * @test
     */
    public function deleteOrderByIncrementId_noError()
    {
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn($this->orderMock);
        $state = Order::STATE_PENDING_PAYMENT;
        $this->orderMock->expects(static::once())->method('getState')->willReturn($state);
        $this->currentMock->expects(static::once())->method('deleteOrder')->with($this->orderMock);
        $this->currentMock->deleteOrderByIncrementId(self::INCREMENT_ID." / ".self::QUOTE_ID);
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
            array('captures', array()),
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
            array('captures', array()),
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
            array('captures', array()),
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
}
