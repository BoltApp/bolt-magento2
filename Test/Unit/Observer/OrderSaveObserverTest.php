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
 *
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Observer;

use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Model\Request;
use Bolt\Boltpay\Observer\OrderSaveObserver as Observer;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Exception;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Class OrderSaveObserverTest
 *
 * @coversDefaultClass \Bolt\Boltpay\Observer\OrderSaveObserver
 */
class OrderSaveObserverTest extends BoltTestCase
{
    const ORDER_CURRENCY_CODE = 'USD';
    const ORDER_INCREMENT_ID = '1235';
    const ORDER_STORE_ID = '4321';
    const ORDER_QUOTE_ID = '1357';
    const ORDER_TOTAL_AMOUNT = '1000';
    const ORDER_TAX_AMOUNT = '100';

    /**
     * @var Observer
     */
    protected $observer;

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
     * @test
     * that OrderSaveObserver::execute works as intended
     *
     * @covers ::execute
    **/
    public function testExecute()
    {
        $this->decider->expects($this->once())->method('isOrderUpdateEnabled')->willReturn(true);
        
        $order = $this->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $order->expects($this->once())
            ->method('getStoreId')
            ->willReturn(self::ORDER_STORE_ID);
        $order->expects($this->once())
            ->method('getOrderCurrencyCode')
            ->willReturn(self::ORDER_CURRENCY_CODE);
        $order->expects($this->once())
            ->method('getQuoteId')
            ->willReturn(self::ORDER_QUOTE_ID);
        $order->expects($this->once())
            ->method('getIncrementId')
            ->willReturn(self::ORDER_INCREMENT_ID);
        $order->expects($this->once())
            ->method('getGrandTotal')
            ->willReturn(self::ORDER_TOTAL_AMOUNT);
        $order->expects($this->once())
            ->method('getTaxAmount')
            ->willReturn(self::ORDER_TAX_AMOUNT);

        $eventObserver = $this->getMockBuilder(\Magento\Framework\Event\Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event = $this->getMockBuilder(\Magento\Framework\Event::class)
            ->setMethods(['getOrder'])
            ->disableOriginalConstructor()
            ->getMock();
        $eventObserver->expects($this->once())
            ->method('getEvent')
            ->willReturn($event);
        $event->expects($this->once())
            ->method('getOrder')
            ->willReturn($order);

        $this->cartHelper->expects($this->once())
            ->method('getCartItemsFromItems')
            ->with(null, self::ORDER_CURRENCY_CODE, self::ORDER_STORE_ID, 0, 0)
            ->willReturn(['item_data', 0, 0]);

        $expectedData = [
            'order_reference' => self::ORDER_QUOTE_ID,
            'cart' => [
                'display_id' => self::ORDER_INCREMENT_ID,
                'total_amount' => CurrencyUtils::toMinor(self::ORDER_TOTAL_AMOUNT, self::ORDER_CURRENCY_CODE),
                'tax_amount' => CurrencyUtils::toMinor(self::ORDER_TAX_AMOUNT, self::ORDER_CURRENCY_CODE),
                'items' => 'item_data',
            ],
        ];
        $this->dataObject->expects($this->once())
            ->method('setApiData')
            ->with($expectedData);

        $this->apiHelper->expects($this->once())->method('buildRequest')->willReturn(new Request());
        $this->apiHelper->expects($this->once())->method('sendRequest')->willReturn(200);

        $this->metricsClient->expects($this->once())->method('getCurrentTime')->willReturn(5000);
        $this->metricsClient->expects($this->once())->method('processMetric')->with(
            'order_update.success',
            1,
            'order_update.latency',
            5000
        );
                
        $this->observer->execute($eventObserver);
    }

    /**
     * @test
     * that OrderSaveObserver::execute properly caches carts and avoids extraneous network calls
     *
     * @covers ::execute
    **/
    public function testExecute_withCache()
    {
        $this->decider->method('isOrderUpdateEnabled')->willReturn(true);
        
        $order = $this->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $order->method('getStoreId')->willReturn(self::ORDER_STORE_ID);
        $order->method('getOrderCurrencyCode')->willReturn(self::ORDER_CURRENCY_CODE);
        $order->method('getQuoteId')->willReturn(self::ORDER_QUOTE_ID);
        $order->method('getIncrementId')->willReturn(self::ORDER_INCREMENT_ID);
        $order->method('getGrandTotal')->willReturn(self::ORDER_TOTAL_AMOUNT);
        $order->method('getTaxAmount')->willReturn(self::ORDER_TAX_AMOUNT);

        $eventObserver = $this->getMockBuilder(\Magento\Framework\Event\Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event = $this->getMockBuilder(\Magento\Framework\Event::class)
            ->setMethods(['getOrder'])
            ->disableOriginalConstructor()
            ->getMock();
        $eventObserver->method('getEvent')->willReturn($event);
        $event->method('getOrder')->willReturn($order);

        $this->cartHelper
            ->method('getCartItemsFromItems')
            ->with(null, self::ORDER_CURRENCY_CODE, self::ORDER_STORE_ID, 0, 0)
            ->willReturn(['item_data', 0, 0]);
        
        $this->cache->expects($this->exactly(2))->method('load')->willReturnOnConsecutiveCalls(false, true);
        $this->cache->expects($this->once())->method('save');

        $this->apiHelper->expects($this->once())->method('buildRequest')->willReturn(new Request());
        $this->apiHelper->expects($this->once())->method('sendRequest')->willReturn(200);

        $this->metricsClient->expects($this->exactly(2))->method('getCurrentTime')->willReturn(5000);
        $this->metricsClient->expects($this->exactly(2))->method('processMetric')->withConsecutive(
            ['order_update.success', 1, 'order_update.latency', 5000],
            ['order_update.cached', 1, 'order_update.latency', 5000]
        );
                
        $this->observer->execute($eventObserver);
        $this->observer->execute($eventObserver);
    }

    protected function setUpInternal()
    {
        $this->initRequiredMocks();
    }

    private function initRequiredMocks()
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        
        $this->dataObject = $this->getMockBuilder(DataObject::class)
            ->setMethods(['setApiData'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->dataObjectFactory->method('create')->willReturn($this->dataObject);

        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->apiHelper = $this->createMock(ApiHelper::class);
        $this->cartHelper = $this->createMock(Cart::class);
        $this->metricsClient = $this->createMock(MetricsClient::class);
        $this->decider = $this->createMock(Decider::class);

        $this->observer = new Observer(
            $this->cache,
            $this->configHelper,
            $this->dataObjectFactory,
            $this->apiHelper,
            $this->cartHelper,
            $this->bugsnag,
            $this->metricsClient,
            $this->decider
        );
    }
}
