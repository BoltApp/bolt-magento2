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

namespace Bolt\Boltpay\Test\Unit\Observer;

use Bolt\Boltpay\Helper\Api as ApiHelper;
use Magento\Framework\DataObject;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\DataObjectFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Bolt\Boltpay\Observer\TrackingSaveObserver as Observer;
use \PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Model\Request;

/**
 * Class TrackingSaveObserverTest
 * @coversDefaultClass \Bolt\Boltpay\Observer\TrackingSaveObserver
 */
class TrackingSaveObserverTest extends TestCase
{
    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var DataObject
     */
    private $dataObject;

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var MockObject|Bugsnag
     */
    private $bugsnag;

    /**
     * @var MetricsClient
     */
    private $metricsClient;

    /**
     * @var Decider
     */
    private $decider;

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
        $this->configHelper = $this->createMock(ConfigHelper::class);
        //$this->dataObject = $this->createMock(DataObject::class);
        $this->dataObject = $this->getMockBuilder(DataObject::class)
            ->setMethods(['setApiData'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->dataObjectFactory->method('create')->willReturn($this->dataObject);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->apiHelper = $this->createMock(ApiHelper::class);
        $this->metricsClient = $this->createMock(MetricsClient::class);
        $this->decider = $this->createMock(Decider::class);
        $this->observer = new Observer(
            $this->configHelper,
            $this->dataObjectFactory,
            $this->apiHelper,
            $this->bugsnag,
            $this->metricsClient,
            $this->decider
        );
    }

    /**
     * @test
     */
    public function testExecute()
    {
        $shipment = $this->getMockBuilder(\Magento\Sales\Model\Order\Shipment::class)->disableOriginalConstructor()->getMock();
        $shipmentItem1 = $this->getMockBuilder(\Magento\Sales\Model\Order\Shipment\Item::class)->disableOriginalConstructor()->getMock();
        $shipmentItem2 = $this->getMockBuilder(\Magento\Sales\Model\Order\Shipment\Item::class)->disableOriginalConstructor()->getMock();
        $orderItem1 = $this->getMockBuilder(\Magento\Sales\Model\Order\Item::class)->disableOriginalConstructor()->getMock();
        $orderItem2 = $this->getMockBuilder(\Magento\Sales\Model\Order\Item::class)->disableOriginalConstructor()->getMock();
        $payment = $this->getMockBuilder(\Magento\Sales\Api\Data\OrderPaymentInterface::class)->getMockForAbstractClass();
        $order = $this->getMockBuilder(\Magento\Sales\Model\Order::class)->disableOriginalConstructor()->getMock();
        $track = $this->getMockBuilder(\Magento\Sales\Model\Order\Shipment\Track::class)->disableOriginalConstructor()->getMock();

        $map = [
            ['transaction_reference', '000123'],
        ];
        $payment->expects($this->once())
            ->method('getAdditionalInformation')
            ->will($this->returnValueMap($map));
        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn(\Bolt\Boltpay\Model\Payment::METHOD_CODE);
        $order->expects($this->once())
            ->method('getPayment')
            ->willReturn($payment);
        $shipment->expects($this->once())
            ->method('getOrder')
            ->willReturn($order);
        $track->expects($this->once())
            ->method('getShipment')
            ->willReturn($shipment);
        $track->expects($this->once())
              ->method('getTrackNumber')
              ->willReturn("EZ4000000004");
        $track->expects($this->once())
              ->method("getCarrierCode")
              ->willReturn("United States Postal Service");

        $eventObserver = $this->getMockBuilder(\Magento\Framework\Event\Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event = $this->getMockBuilder(\Magento\Framework\Event::class)
            ->setMethods(['getTrack'])
            ->disableOriginalConstructor()
            ->getMock();
        $eventObserver->expects($this->once())
            ->method('getEvent')
            ->willReturn($event);
        $event->expects($this->once())
            ->method('getTrack')
            ->willReturn($track);

        $shipmentItem1->expects($this->once())
            ->method('getOrderItem')
            ->willReturn($orderItem1);
        $orderItem1->expects($this->once())
            ->method('getProductId')
            ->willReturn(12345);
        $orderItem1->expects($this->once())
            ->method('getParentItem')
            ->willReturn(false);
        $orderItem1->expects($this->once())
            ->method('getProductOptions')
            ->willReturn([
                'attributes_info' => [[
                    "label"  => "Size",
                    "value" => "XS" ,
                ]]
            ]);

        $shipmentItem2->expects($this->once())
            ->method('getOrderItem')
            ->willReturn($orderItem2);
        $orderItem2->expects($this->never())
            ->method('getProductId');
        $orderItem2->expects($this->once())
            ->method('getParentItem')
            ->willReturn($orderItem1);

        $shipment->expects($this->once())
            ->method('getItemsCollection')
            ->willReturn([$shipmentItem1, $shipmentItem2]);

        $expectedData = [
            "transaction_reference" => "000123",
            "tracking_number"       => "EZ4000000004",
            "carrier"               => "United States Postal Service",
            "items"                 => [
                (object)[
                    'reference'=>'12345',
                    'options'=>[(object)[
                        "name"  => "Size",
                        "value" => "XS",
                    ]],
                ],
            ],
            'is_non_bolt_order' => false,
        ];

        $this->dataObject->expects($this->once())
            ->method("setApiData")
            ->with($expectedData);

        $this->apiHelper->expects($this->once())->method('buildRequest')->willReturn(new Request());
        $this->apiHelper->expects($this->once())->method('sendRequest')->willReturn(200);
        $this->decider->expects($this->once())->method('isTrackShipmentEnabled')->willReturn(true);
        $this->observer->execute($eventObserver);
    }

    /**
     * @test
     */
    public function testExecuteNonBoltOrder()
    {
        $shipment = $this->getMockBuilder(\Magento\Sales\Model\Order\Shipment::class)->disableOriginalConstructor()->getMock();
        $shipmentItem = $this->getMockBuilder(\Magento\Sales\Model\Order\Shipment\Item::class)->disableOriginalConstructor()->getMock();
        $orderItem = $this->getMockBuilder(\Magento\Sales\Model\Order\Item::class)->disableOriginalConstructor()->getMock();
        $payment = $this->getMockBuilder(\Magento\Sales\Api\Data\OrderPaymentInterface::class)->getMockForAbstractClass();
        $order = $this->getMockBuilder("BoltOrder")
            ->setMethods(['getPayment', 'getStoreId', 'getBoltTransactionReference'])
            ->getMock();
        $track = $this->getMockBuilder(\Magento\Sales\Model\Order\Shipment\Track::class)->disableOriginalConstructor()->getMock();

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn('otherpay');
        $order->expects($this->once())
            ->method('getBoltTransactionReference')
            ->will($this->returnValue('ABCD-EFGH-1234'));
        $order->expects($this->once())
            ->method('getPayment')
            ->willReturn($payment);
        $shipment->expects($this->once())
            ->method('getOrder')
            ->willReturn($order);
        $track->expects($this->once())
            ->method('getShipment')
            ->willReturn($shipment);

        $track->expects($this->once())
            ->method('getTrackNumber')
            ->willReturn("EZ4000000004");
        $track->expects($this->once())
            ->method("getCarrierCode")
            ->willReturn("United States Postal Service");

        $eventObserver = $this->getMockBuilder(\Magento\Framework\Event\Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event = $this->getMockBuilder(\Magento\Framework\Event::class)
            ->setMethods(['getTrack'])
            ->disableOriginalConstructor()
            ->getMock();
        $eventObserver->expects($this->once())
            ->method('getEvent')
            ->willReturn($event);
        $event->expects($this->once())
            ->method('getTrack')
            ->willReturn($track);

        $shipmentItem->expects($this->once())
            ->method('getOrderItem')
            ->willReturn($orderItem);
        $orderItem->expects($this->once())
            ->method('getProductId')
            ->willReturn(12345);
        $orderItem->expects($this->once())
            ->method('getParentItem')
            ->willReturn(false);
        $orderItem->expects($this->once())
            ->method('getProductOptions')
            ->willReturn([
                'attributes_info' => [[
                    "label"  => "Size",
                    "value" => "XS" ,
                ]]
            ]);

        $shipment->expects($this->once())
            ->method('getItemsCollection')
            ->willReturn([$shipmentItem]);

        $expectedData = [
            "transaction_reference" => "ABCD-EFGH-1234",
            "tracking_number"       => "EZ4000000004",
            "carrier"               => "United States Postal Service",
            "items"                 => [
                (object)[
                    'reference'=>'12345',
                    'options'=>[(object)[
                        "name"  => "Size",
                        "value" => "XS",
                    ]],
                ],
            ],
            'is_non_bolt_order' => true,
        ];

        $this->dataObject->expects($this->once())
            ->method("setApiData")
            ->with($expectedData);

        $this->apiHelper->expects($this->once())->method('buildRequest')->willReturn(new Request());
        $this->apiHelper->expects($this->once())->method('sendRequest')->willReturn(200);
        $this->decider->expects($this->once())->method('isTrackShipmentEnabled')->willReturn(true);
        $this->observer->execute($eventObserver);
    }

    /**
     * @test
     */
    public function testExecuteFalseDecider()
    {
        $shipment = $this->getMockBuilder(\Magento\Sales\Model\Order\Shipment::class)->disableOriginalConstructor()->getMock();
        $shipmentItem = $this->getMockBuilder(\Magento\Sales\Model\Order\Shipment\Item::class)->disableOriginalConstructor()->getMock();
        $orderItem = $this->getMockBuilder(\Magento\Sales\Model\Order\Item::class)->disableOriginalConstructor()->getMock();
        $payment = $this->getMockBuilder(\Magento\Sales\Api\Data\OrderPaymentInterface::class)->getMockForAbstractClass();
        $order = $this->getMockBuilder("BoltOrder")
            ->setMethods(['getPayment', 'getBoltTransactionReference'])
            ->getMock();
        $track = $this->getMockBuilder(\Magento\Sales\Model\Order\Shipment\Track::class)->disableOriginalConstructor()->getMock();

        $payment->expects($this->never())
            ->method('getMethod')
            ->willReturn(\Bolt\Boltpay\Model\Payment::METHOD_CODE);
        $order->expects($this->never())
            ->method('getPayment')
            ->willReturn($payment);
        $shipment->expects($this->never())
            ->method('getOrder')
            ->willReturn($order);
        $track->expects($this->never())
            ->method('getShipment')
            ->willReturn($shipment);

        $eventObserver = $this->getMockBuilder(\Magento\Framework\Event\Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event = $this->getMockBuilder(\Magento\Framework\Event::class)
            ->setMethods(['getTrack'])
            ->disableOriginalConstructor()
            ->getMock();
        $eventObserver->expects($this->never())
            ->method('getEvent')
            ->willReturn($event);
        $event->expects($this->never())
            ->method('getTrack')
            ->willReturn($track);

        $shipmentItem->expects($this->never())
            ->method('getOrderItem')
            ->willReturn($orderItem);
        $orderItem->expects($this->never())
            ->method('getProductId')
            ->willReturn(12345);
        $shipment->expects($this->never())
            ->method('getItemsCollection')
            ->willReturn([$shipmentItem]);

        $this->decider->expects($this->once())->method('isTrackShipmentEnabled')->willReturn(false);
        $this->apiHelper->expects($this->never())->method('sendRequest');
        $this->observer->execute($eventObserver);
    }

    /**
     * @test
     */
    public function testExecuteMissingTransactionReference()
    {
        $shipment = $this->getMockBuilder(\Magento\Sales\Model\Order\Shipment::class)->disableOriginalConstructor()->getMock();
        $shipmentItem = $this->getMockBuilder(\Magento\Sales\Model\Order\Shipment\Item::class)->disableOriginalConstructor()->getMock();
        $orderItem = $this->getMockBuilder(\Magento\Sales\Model\Order\Item::class)->disableOriginalConstructor()->getMock();
        $payment = $this->getMockBuilder(\Magento\Sales\Api\Data\OrderPaymentInterface::class)->getMockForAbstractClass();
        $order = $this->getMockBuilder("BoltOrder")
            ->setMethods(['getPayment', 'getQuoteId', 'getBoltTransactionReference'])
            ->getMock();
        $track = $this->getMockBuilder(\Magento\Sales\Model\Order\Shipment\Track::class)->disableOriginalConstructor()->getMock();

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn('otherpay');
        $order->expects($this->once())
            ->method('getBoltTransactionReference')
            ->will($this->returnValue(null));
        $order->expects($this->once())
            ->method('getPayment')
            ->willReturn($payment);
        $shipment->expects($this->once())
            ->method('getOrder')
            ->willReturn($order);
        $track->expects($this->once())
            ->method('getShipment')
            ->willReturn($shipment);

        $eventObserver = $this->getMockBuilder(\Magento\Framework\Event\Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event = $this->getMockBuilder(\Magento\Framework\Event::class)
            ->setMethods(['getTrack'])
            ->disableOriginalConstructor()
            ->getMock();
        $eventObserver->expects($this->once())
            ->method('getEvent')
            ->willReturn($event);
        $event->expects($this->once())
            ->method('getTrack')
            ->willReturn($track);

        $shipmentItem->expects($this->never())
            ->method('getOrderItem')
            ->willReturn($orderItem);
        $orderItem->expects($this->never())
            ->method('getProductId')
            ->willReturn(12345);
        $shipment->expects($this->never())
            ->method('getItemsCollection')
            ->willReturn([$shipmentItem]);

        $this->decider->expects($this->once())->method('isTrackShipmentEnabled')->willReturn(true);
        $this->apiHelper->expects($this->never())->method('sendRequest');
        $this->observer->execute($eventObserver);
    }
}
