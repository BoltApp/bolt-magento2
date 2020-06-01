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

namespace Bolt\Boltpay\Test\Unit\Observer;

use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Model\Payment;
use Exception;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\DataObject;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\DataObjectFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Event;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Bolt\Boltpay\Observer\NonBoltOrderObserver as Observer;
use \PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Model\Request;

/**
 * Class TrackingSaveObserverTest
 * @coversDefaultClass \Bolt\Boltpay\Observer\TrackingSaveObserver
 */
class NonBoltOrderObserverTest extends TestCase
{
    const IMMUTABLE_QUOTE_ID = 1001;
    const RESERVED_ORDER_ID = '100010001';
    const FIRST_NAME = 'IntegrationBolt';
    const LAST_NAME = 'BoltTest';
    const EMAIL = 'integration@bolt.com';
    const PHONE = '132 231 1234';
    const EXPECTED_SHIPMENT = [
        'cost'             => 0,
        'tax_amount'       => 0,
        'shipping_address' => [
            'first_name'      => self::FIRST_NAME,
            'last_name'       => self::LAST_NAME,
            'phone'           => self::PHONE,
            'email'           => self::EMAIL,
        ],
        'service'          => null,
        'reference'        => null,
    ];
    const CART = [
        'order_reference' => self::IMMUTABLE_QUOTE_ID,
        'display_id'      => self::RESERVED_ORDER_ID . ' / ' . self::IMMUTABLE_QUOTE_ID,
        'shipments'       => [self::EXPECTED_SHIPMENT],
        'total_amount'    => 100,
        'tax_amount'      => 0,
    ];

    /**
     * @var ApiHelper|MockObject
     */
    private $apiHelper;

    /**
     * @var Bugsnag|MockObject
     */
    private $bugsnag;

    /**
     * @var CartHelper|MockObject
     */
    private $cartHelper;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var DataObject|MockObject
     */
    private $dataObject;

    /**
     * @var LogHelper|MockObject
     */
    private $logHelper;

    /**
     * @var MetricsClient
     */
    private $metricsClient;

    /**
     * @var Observer
     */
    protected $observer;

    protected function setUp()
    {
        $this->initRequiredMocks();
    }

    private function initRequiredMocks()
    {
        $this->apiHelper = $this->createMock(ApiHelper::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->cartHelper = $this->getMockBuilder(CartHelper::class)
            ->setMethods(['buildCartFromQuote'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->dataObject = $this->getMockBuilder(DataObject::class)
            ->setMethods(['setApiData'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->dataObjectFactory->method('create')->willReturn($this->dataObject);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->metricsClient = $this->createMock(MetricsClient::class);
        $this->observer = new Observer(
            $this->apiHelper,
            $this->bugsnag,
            $this->cartHelper,
            $this->configHelper,
            $this->dataObjectFactory,
            $this->logHelper,
            $this->metricsClient
        );
    }

    /**
     * @test
     */
    public function testExecute()
    {
        $order = $this->createMock(Order::class);
        $item = $this->createMock(Item::class);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn(self::EMAIL);
        $customer->method('getFirstname')->willReturn(self::FIRST_NAME);
        $customer->method('getLastname')->willReturn(self::LAST_NAME);
        $quote = $this->createMock(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([$item]);
        $quote->method('getCustomer')->willReturn($customer);

        $event = $this->getMockBuilder(Event::class)
            ->setMethods(['getOrder', 'getQuote'])
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getOrder')
            ->willReturn($order);
        $event->expects($this->once())
            ->method('getQuote')
            ->willReturn($quote);
        $eventObserver = $this->getMockBuilder(Event\Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $eventObserver->expects($this->exactly(2))
            ->method('getEvent')
            ->willReturn($event);

        $this->cartHelper->method('buildCartFromQuote')->willReturn(self::CART);
        $expectedOrderData = [
            'cart' => self::CART,
            'user_identifier' => [
                'email' => self::EMAIL,
                'phone' => self::PHONE,
            ],
            'user_identity' => [
                'first_name' => self::FIRST_NAME,
                'last_name' => self::LAST_NAME,
            ],
        ];

        $this->dataObject->expects($this->once())
            ->method("setApiData")
            ->with($expectedOrderData);

        $this->apiHelper->expects($this->once())->method('buildRequest')->willReturn(new Request());
        $this->apiHelper->expects($this->once())->method('sendRequest')->willReturn(200);
        $this->observer->execute($eventObserver);
    }

    /**
     * @test
     */
    public function testExecuteNoOrder()
    {
        $event = $this->getMockBuilder(Event::class)
            ->setMethods(['getOrder', 'getOrders'])
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getOrder')
            ->willReturn(null);
        $event->expects($this->once())
            ->method('getOrders')
            ->willReturn(null);
        $eventObserver = $this->getMockBuilder(Event\Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $eventObserver->expects($this->exactly(2))
            ->method('getEvent')
            ->willReturn($event);
        $this->apiHelper->expects($this->never())->method('sendRequest');
        $this->observer->execute($eventObserver);
    }

    /**
     * @test
     */
    public function testExecuteNoQuote()
    {
        $order = $this->createMock(Order::class);
        $event = $this->getMockBuilder(Event::class)
            ->setMethods(['getOrder', 'getQuote'])
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getOrder')
            ->willReturn($order);
        $event->expects($this->once())
            ->method('getQuote')
            ->willReturn(null);
        $eventObserver = $this->getMockBuilder(Event\Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $eventObserver->expects($this->exactly(2))
            ->method('getEvent')
            ->willReturn($event);
        $this->apiHelper->expects($this->never())->method('sendRequest');
        $this->observer->execute($eventObserver);
    }

    /**
     * @test
     */
    public function testExecuteBoltOrder()
    {
        $payment = $this->getMockForAbstractClass(OrderPaymentInterface::class);
        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn(Payment::METHOD_CODE);
        $order = $this->getMockBuilder(Order::class)
            ->setMethods(['getPayment'])
            ->disableOriginalConstructor()
            ->getMock();
        $order->expects($this->once())
            ->method('getPayment')
            ->willReturn($payment);
        $event = $this->getMockBuilder(Event::class)
            ->setMethods(['getOrder'])
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getOrder')
            ->willReturn($order);
        $eventObserver = $this->getMockBuilder(Event\Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $eventObserver->expects($this->exactly(1))
            ->method('getEvent')
            ->willReturn($event);
        $this->apiHelper->expects($this->never())->method('sendRequest');
        $this->observer->execute($eventObserver);
    }

    /**
     * @test
     */
    public function testExecuteBuildCartError()
    {
        $order = $this->createMock(Order::class);
        $item = $this->createMock(Item::class);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn(self::EMAIL);
        $customer->method('getFirstname')->willReturn(self::FIRST_NAME);
        $customer->method('getLastname')->willReturn(self::LAST_NAME);
        $quote = $this->createMock(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([$item]);
        $quote->method('getCustomer')->willReturn($customer);

        $event = $this->getMockBuilder(Event::class)
            ->setMethods(['getOrder', 'getQuote'])
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getOrder')
            ->willReturn($order);
        $event->expects($this->once())
            ->method('getQuote')
            ->willReturn($quote);
        $eventObserver = $this->getMockBuilder(Event\Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $eventObserver->expects($this->exactly(2))
            ->method('getEvent')
            ->willReturn($event);

        $this->cartHelper->method('buildCartFromQuote')->willReturnCallback(function() {
            trigger_error("Error", E_ERROR);
        });
        $this->dataObject->expects($this->never())->method("setApiData");
        $this->apiHelper->expects($this->never())->method('buildRequest')->willReturn(new Request());
        $this->apiHelper->expects($this->never())->method('sendRequest')->willReturn(200);
        $this->observer->execute($eventObserver);

        // assert that an error was not thrown by the observer
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function testExecuteBuildCartException()
    {
        $order = $this->createMock(Order::class);
        $item = $this->createMock(Item::class);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn(self::EMAIL);
        $customer->method('getFirstname')->willReturn(self::FIRST_NAME);
        $customer->method('getLastname')->willReturn(self::LAST_NAME);
        $quote = $this->createMock(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([$item]);
        $quote->method('getCustomer')->willReturn($customer);

        $event = $this->getMockBuilder(Event::class)
            ->setMethods(['getOrder', 'getQuote'])
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getOrder')
            ->willReturn($order);
        $event->expects($this->once())
            ->method('getQuote')
            ->willReturn($quote);
        $eventObserver = $this->getMockBuilder(Event\Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $eventObserver->expects($this->exactly(2))
            ->method('getEvent')
            ->willReturn($event);

        $this->cartHelper->method('buildCartFromQuote')->willThrowException(new Exception());
        $this->dataObject->expects($this->never())->method("setApiData");
        $this->apiHelper->expects($this->never())->method('buildRequest')->willReturn(new Request());
        $this->apiHelper->expects($this->never())->method('sendRequest')->willReturn(200);
        $this->observer->execute($eventObserver);

        // assert that an error was not thrown by the observer
        $this->assertTrue(true);
    }
}
