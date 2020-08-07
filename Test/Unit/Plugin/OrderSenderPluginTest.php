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

namespace Bolt\Boltpay\Test\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Bolt\Boltpay\Plugin\OrderSenderPlugin;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Bolt\Boltpay\Model\Payment;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\OrderSenderPlugin
 */
class OrderSenderPluginTest extends TestCase
{
    /**
     * @var OrderSender
     */
    private $subject;

    /**
     * @var Order
     */
    private $order;

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

    public function setUp()
    {
        $this->order = $this->createPartialMock(Order::class, ['getPayment', 'getMethod', 'getState']);
        $this->subject = $this->createPartialMock(OrderSender::class, ['getPayment', 'getMethod']);
        $objectManager = new ObjectManager($this);
        $this->plugin = $objectManager->getObject(
            OrderSenderPlugin::class
        );

        /** @var callable $callback */
        $this->callback = $callback = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])->getMock();
        $this->proceed = function ($order, $forceSyncMode) use ($callback) {
            return $callback($order, $forceSyncMode);
        };
    }

    /**
     * @test
     * @covers ::aroundSend
     */
    public function aroundSend_withPaymentMethodIsNotBoltPay()
    {
        $this->order->expects(self::once())->method('getPayment')->willReturnSelf();
        $this->order->expects(self::once())->method('getMethod')->willReturn(false);
        $this->callback->expects(self::once())->method('__invoke')->with($this->order, false)->willReturnSelf();
        $this->plugin->aroundSend($this->subject, $this->proceed, $this->order, false);
    }

    /**
     * @test
     * @covers ::aroundSend
     * @dataProvider dataProvider_aroundSend_withPaymentMethodIsBoltPay
     * @param $orderState
     */
    public function aroundSend_withPaymentMethodIsBoltPay($orderState)
    {
        $this->order->expects(self::once())->method('getPayment')->willReturnSelf();
        $this->order->expects(self::once())->method('getMethod')->willReturn(Payment::METHOD_CODE);
        $this->order->expects(self::once())->method('getState')->willReturn($orderState);
        $result = $this->plugin->aroundSend($this->subject, $this->proceed, $this->order, false);
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
