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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Test\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Bolt\Boltpay\Plugin\OrderPlugin;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Bolt\Boltpay\Helper\Order as OrderHelper;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\OrderPlugin
 */
class OrderPluginTest extends TestCase
{
    /**
     * @var OrderPlugin
     */
    protected $plugin;

    /**
     * @var Order
     */
    protected $subject;

    /**
     * @var Payment
     */
    protected $payment;

    public function setUp()
    {
        $this->plugin = (new ObjectManager($this))->getObject(OrderPlugin::class);
        $this->subject = $this->createPartialMock(Order::class, ['getPayment', 'getState', 'getConfig', 'getStateDefaultStatus', 'getStatus']);
        $this->payment = $this->createPartialMock(Payment::class, ['getMethod']);
    }

    /**
     * @test
     * @covers ::beforeSetState
     */
    public function beforeSetState_withoutPayment()
    {
        $this->subject->expects(self::once())->method('getPayment')->willReturn(null);
        $result = $this->plugin->beforeSetState($this->subject, 'normal_state');
        $this->assertEquals(['normal_state'], $result);
    }

    /**
     * @test
     * @covers ::beforeSetState
     */
    public function beforeSetState_withPaymentMethodIsNotBoltPay()
    {
        $this->subject->expects(self::exactly(2))->method('getPayment')->willReturn($this->payment);
        $this->payment->expects(self::once())->method('getMethod')->willReturn('is_not_boltpay');
        $result = $this->plugin->beforeSetState($this->subject, 'normal_state');
        $this->assertEquals(['normal_state'], $result);
    }

    /**
     * @test
     * @covers ::beforeSetState
     * @dataProvider dataProvider_beforeSetState_withPaymentMethodIsBoltPay
     *
     * @param $orderState
     * @param $stateParameter
     * @param $expected
     */
    public function beforeSetState_withPaymentMethodIsBoltPay($orderState, $stateParameter, $expected)
    {
        $this->subject->expects(self::exactly(2))->method('getPayment')->willReturn($this->payment);
        $this->payment->expects(self::once())->method('getMethod')->willReturn('boltpay');
        $this->subject->expects(self::any())->method('getState')->willReturn($orderState);
        $result = $this->plugin->beforeSetState($this->subject, $stateParameter);
        $this->assertEquals($expected, $result);
    }

    public function dataProvider_beforeSetState_withPaymentMethodIsBoltPay()
    {
        return [
            ['', OrderHelper::BOLT_ORDER_STATE_NEW, [Order::STATE_NEW]],
            ['', OrderHelper::TS_REJECTED_IRREVERSIBLE, [Order::STATE_PENDING_PAYMENT]],
            [Order::STATE_NEW, Order::STATE_NEW, [Order::STATE_PENDING_PAYMENT]],
            [Order::STATE_COMPLETE, Order::STATE_COMPLETE, [Order::STATE_COMPLETE]],
        ];
    }

    /**
     * @test
     * @covers ::beforeSetStatus
     */
    public function beforeSetStatus_withoutPayment()
    {
        $this->subject->expects(self::once())->method('getPayment')->willReturn(null);
        $result = $this->plugin->beforeSetStatus($this->subject, 'normal_status');
        $this->assertEquals(['normal_status'], $result);
    }

    /**
     * @test
     * @covers ::beforeSetStatus
     */
    public function beforeSetStatus_withPaymentMethodIsNotBoltPay()
    {
        $this->subject->expects(self::exactly(2))->method('getPayment')->willReturn($this->payment);
        $this->payment->expects(self::once())->method('getMethod')->willReturn('is_not_boltpay');
        $result = $this->plugin->beforeSetStatus($this->subject, 'normal_status');
        $this->assertEquals(['normal_status'], $result);
    }

    /**
     * @test
     * @dataProvider dataProvider_beforeSetStatus_withPaymentMethodIsBoltPay
     * @covers ::beforeSetStatus
     *
     * @param $stateDefaultStatus
     * @param $statusParameter
     * @param $orderStatus
     * @param $expected
     */
    public function beforeSetStatus_withPaymentMethodIsBoltPay($stateDefaultStatus, $statusParameter, $orderStatus, $expected)
    {
        $this->subject->expects(self::exactly(2))->method('getPayment')->willReturn($this->payment);
        $this->payment->expects(self::once())->method('getMethod')->willReturn('boltpay');
        $this->subject->expects(self::any())->method('getConfig')->willReturnSelf();
        $this->subject->expects(self::any())->method('getStateDefaultStatus')->willReturn($stateDefaultStatus);
        $this->subject->expects(self::any())->method('getStatus')->willReturn($orderStatus);
        $result = $this->plugin->beforeSetStatus($this->subject, $statusParameter);
        $this->assertEquals($expected, $result);
    }

    public function dataProvider_beforeSetStatus_withPaymentMethodIsBoltPay()
    {
        return [
            [Order::STATE_NEW, OrderHelper::BOLT_ORDER_STATUS_PENDING, '', [Order::STATE_NEW]],
            [Order::STATE_COMPLETE, OrderHelper::MAGENTO_ORDER_STATUS_PENDING, Order::STATE_PENDING_PAYMENT, [Order::STATE_COMPLETE]],
            [Order::STATE_COMPLETE, OrderHelper::MAGENTO_ORDER_STATUS_PENDING, Order::STATE_NEW, [Order::STATE_COMPLETE]],
            [Order::STATE_COMPLETE, OrderHelper::MAGENTO_ORDER_STATUS_PENDING, '', [Order::STATE_COMPLETE]],
        ];
    }
}
