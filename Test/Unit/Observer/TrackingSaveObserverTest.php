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
use Magento\Framework\DataObject;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\DataObjectFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Bolt\Boltpay\Observer\TrackingSaveObserver as Observer;
use \PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;

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
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->dataObjectFactory->method('create')->willReturn(new DataObject());
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
        $shipmentItem = $this->getMockBuilder(\Magento\Sales\Model\Order\Shipment\Item::class)->disableOriginalConstructor()->getMock();
        $orderItem = $this->getMockBuilder(\Magento\Sales\Model\Order\Item::class)->disableOriginalConstructor()->getMock();
        $payment = $this->getMockBuilder(\Magento\Payment\Model\InfoInterface::class)->getMockForAbstractClass();
        $order = $this->getMockBuilder(\Magento\Sales\Model\Order::class)->disableOriginalConstructor()->getMock();
        $track = $this->getMockBuilder(\Magento\Sales\Model\Order\Shipment\Track::class)->disableOriginalConstructor()->getMock();

        $map = array(
            array('transaction_reference', '000123'),
        );
        $payment->expects($this->once())
            ->method('getAdditionalInformation')
            ->will($this->returnValueMap($map));
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

        $shipmentItem->expects($this->once())
            ->method('getOrderItem')
            ->willReturn($orderItem);
        $orderItem->expects($this->once())
            ->method('getProductId')
            ->willReturn(12345);
        $shipment->expects($this->once())
            ->method('getItemsCollection')
            ->willReturn([$shipmentItem]);

        $this->apiHelper->expects($this->once())->method('sendRequest');

        $this->observer->execute($eventObserver);
    }
}
