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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Plugin;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Plugin\OrderSenderPlugin;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\OrderSenderPlugin
 */
class OrderSenderPluginTest extends BoltTestCase
{
    /**
     * @var OrderSenderPlugin
     */
    private $plugin;

    /**
     * @var callable
     */
    private $proceed;

    /** @var callable */
    private $callback;

    private $objectManager;

    private $orderSender;

    public function setUpInternal()
    {
        /** @var callable $callback */
        $this->callback = $callback = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])->getMock();
        $this->proceed = function ($order, $forceSyncMode) use ($callback) {
            return $callback($order, $forceSyncMode);
        };
        $this->objectManager = Bootstrap::getObjectManager();
        $this->plugin = $this->objectManager->create(OrderSenderPlugin::class);
        $this->orderSender = $this->objectManager->create(OrderSender::class);
    }

    /**
     * @test
     * @covers ::aroundSend
     */
    public function aroundSend_withPaymentMethodIsNotBoltPay()
    {
        $order = $this->objectManager->create(Order::class);
        $payment = $this->objectManager->create(Order\Payment::class);
        $payment->setMethod('boltpay');
        $order->setPayment($payment);
        $this->callback->expects(self::once())->method('__invoke')->with($order, false)->willReturnSelf();
        $this->plugin->aroundSend($this->orderSender, $this->proceed, $order, false);
    }

    /**
     * @test
     * @covers ::aroundSend
     * @dataProvider dataProvider_aroundSend_withPaymentMethodIsBoltPay
     * @param $orderState
     */
    public function aroundSend_withPaymentMethodIsBoltPay($orderState)
    {
        $order = $this->objectManager->create(Order::class);
        $payment = $this->objectManager->create(Order\Payment::class);
        $payment->setMethod('boltpay');
        $order->setPayment($payment);
        $order->setState($orderState);
        $result = $this->plugin->aroundSend($this->orderSender, $this->proceed, $order, false);
        $this->assertFalse($result);
    }

    public function dataProvider_aroundSend_withPaymentMethodIsBoltPay()
    {
        return [
            [Order::STATE_PENDING_PAYMENT],
            [Order::STATE_NEW],
            [Order::STATE_CANCELED],
            [Order::STATE_PAYMENT_REVIEW],
            [Order::STATE_HOLDED],
        ];
    }
}
