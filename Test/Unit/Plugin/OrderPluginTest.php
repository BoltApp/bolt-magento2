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
use Bolt\Boltpay\Plugin\OrderPlugin;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Model\Payment as BoltPayment;
use Bolt\Boltpay\Test\Unit\TestHelper;

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

    /**
     * @var callable
     */
    protected $proceed;

    /** @var callable|MockObject */
    protected $callback;

    public function setUp()
    {
        $this->plugin = (new ObjectManager($this))->getObject(OrderPlugin::class);
        $this->subject = $this->createPartialMock(Order::class, [
            'getPayment', 'getState', 'getConfig', 'getStateDefaultStatus', 'getStatus', 'setState', 'setStatus', 'place','getIsRechargedOrder'
        ]);
        $this->payment = $this->createPartialMock(Payment::class, ['getMethod']);
        /** @var callable $callback */
        $this->callback = $callback = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])->getMock();
        $this->proceed = function () use ($callback) {
            return $callback();
        };
    }

    /**
     * @test
     * @covers ::beforeSetState
     */
    public function beforeSetState_withoutPayment()
    {
        $this->subject->expects(self::once())->method('getState');
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
        $this->subject->expects(self::once())->method('getState');
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
     * @covers ::beforeSetState
     */
    public function beforeSetState_withRechargedOrder()
    {
        $this->subject->expects(self::exactly(2))->method('getPayment')->willReturn($this->payment);
        $this->payment->expects(self::once())->method('getMethod')->willReturn('boltpay');
        $this->subject->expects(self::once())->method('getIsRechargedOrder')->willReturn(true);
        $result = $this->plugin->beforeSetState($this->subject, 'new');
        $this->assertEquals([Order::STATE_PROCESSING], $result);
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

    /**
     * @test
     * @dataProvider dataProvider_aroundPlace_noProceedCallbackCalled
     * @covers ::aroundPlace
     */
    public function aroundPlace_ifPaymentMethodIsBoltPayAndNoAppropriateOrderState_noProceedCallbackCalled($orderState)
    {
        $this->payment->expects(self::once())->method('getMethod')->willReturn(BoltPayment::METHOD_CODE);
        $this->subject->expects(self::atMost(2))->method('getPayment')->willReturn($this->payment);
        $this->subject->expects(self::atMost(2))->method('getState')->willReturn($orderState);
        $this->callback->expects(self::never())->method('__invoke');
        $result = $this->plugin->aroundPlace($this->subject, $this->proceed);
        $this->assertEquals($result, $this->subject);
    }

    public function dataProvider_aroundPlace_noProceedCallbackCalled()
    {
        return [
            [null],
            [Order::STATE_PENDING_PAYMENT]
        ];
    }

    /**
     * @test
     * @dataProvider dataProvider_aroundPlace_noBoltPayment
     * @covers ::aroundPlace
     */
    public function aroundPlace_ifNotBoltPayment_proceedCallbackCalled($paymentMethod)
    {
        $this->payment->expects(self::atMost(1))->method('getMethod')->willReturn($paymentMethod);
        $this->subject->expects(self::atLeast(1))->method('getPayment')->willReturn($this->payment);
        $this->subject->expects(self::never())->method('getState');
        $this->callback->expects(self::once())->method('__invoke');
        $this->plugin->aroundPlace($this->subject, $this->proceed);
    }

    public function dataProvider_aroundPlace_noBoltPayment()
    {
        return [
            [null],
            ['other_method']
        ];
    }

    /**
     * @test
     * @covers ::aroundPlace
     */
    public function aroundPlace_ifBoltPaymentAndStateNew_proceedCallbackCalled()
    {
        $this->payment->expects(self::once())->method('getMethod')->willReturn(BoltPayment::METHOD_CODE);
        $this->subject->expects(self::exactly(2))->method('getPayment')->willReturn($this->payment);
        $this->subject->method('getState')->willReturn(Order::STATE_NEW);
        $this->callback->expects(self::once())->method('__invoke');
        $this->plugin->aroundPlace($this->subject, $this->proceed);
    }

    /**
     * @test
     * @covers ::afterPlace
     */
    public function afterPlace_ifNotBoltPayment_setStateNotCalled()
    {
        $this->payment->expects(self::once())->method('getMethod')->willReturn('other_method');
        $this->subject->expects(self::exactly(2))->method('getPayment')->willReturn($this->payment);
        $this->subject->expects(self::never())->method('getState');
        $this->subject->expects(self::never())->method('setState');
        $this->subject->expects(self::never())->method('setStatus');
        $result = $this->plugin->afterPlace($this->subject, $this->subject);
        $this->assertEquals($result, $this->subject);
    }

    /**
     * @test
     * @covers ::afterPlace
     */
    public function afterPlace_ifBoltPaymentAndNoStateSet_setStateIsCalled()
    {
        $this->payment->expects(self::once())->method('getMethod')->willReturn(BoltPayment::METHOD_CODE);
        $this->subject->expects(self::exactly(2))->method('getPayment')->willReturn($this->payment);
        $this->subject->expects(self::once())->method('getState')->willReturn(null);
        $this->subject->expects(self::once())->method('setState')->with(Order::STATE_NEW);
        $orderConfig = $this->createMock('\Magento\Sales\Model\Order\Config');
        $orderConfig->expects(self::once())->method('getStateDefaultStatus')->with(Order::STATE_NEW)
            ->willReturn('pending');
        $this->subject->expects(self::once())->method('getConfig')->willReturn($orderConfig);
        $this->subject->expects(self::once())->method('setStatus')->with('pending');
        $result = $this->plugin->afterPlace($this->subject, $this->subject);
        $this->assertEquals($result, $this->subject);
    }

    /**
     * @test
     * @covers ::afterSetState
     */
    public function afterSetState_ifNotBoltPayment_orderPlaceNotCalled()
    {
        $this->payment->expects(self::once())->method('getMethod')->willReturn('other_method');
        $this->subject->expects(self::exactly(2))->method('getPayment')->willReturn($this->payment);
        $this->subject->expects(self::never())->method('getState');
        $this->subject->expects(self::never())->method('place');
        $result = $this->plugin->afterSetState($this->subject, $this->subject);
        $this->assertEquals($result, $this->subject);
    }

    /**
     * @test
     * @covers ::afterSetState
     */
    public function afterSetState_ifBoltPaymentAndOrderStateNotNew_orderPlaceNotCalled()
    {
        $this->payment->expects(self::once())->method('getMethod')->willReturn(BoltPayment::METHOD_CODE);
        $this->subject->expects(self::exactly(2))->method('getPayment')->willReturn($this->payment);
        $this->subject->expects(self::once())->method('getState')->willReturn('other_state');
        $this->subject->expects(self::never())->method('place');
        $result = $this->plugin->afterSetState($this->subject, $this->subject);
        $this->assertEquals($result, $this->subject);
    }

    /**
     * @test
     * @covers ::afterSetState
     */
    public function afterSetState_ifBoltPaymentAndOrderStateNewAndOldOrderStateNotPendingPayment_orderPlaceNotCalled()
    {
        $this->payment->expects(self::once())->method('getMethod')->willReturn(BoltPayment::METHOD_CODE);
        $this->subject->expects(self::exactly(2))->method('getPayment')->willReturn($this->payment);
        $this->subject->expects(self::once())->method('getState')->willReturn(Order::STATE_NEW);
        TestHelper::setProperty($this->plugin, 'oldState', 'other_state');
        $this->subject->expects(self::never())->method('place');
        $result = $this->plugin->afterSetState($this->subject, $this->subject);
        $this->assertEquals($result, $this->subject);
    }

    /**
     * @test
     * @covers ::afterSetState
     */
    public function afterSetState_ifBoltPaymentAndOrderStateNewAndOldOrderStatePendingPayment_orderPlaceCalled()
    {
        $this->payment->expects(self::once())->method('getMethod')->willReturn(BoltPayment::METHOD_CODE);
        $this->subject->expects(self::exactly(2))->method('getPayment')->willReturn($this->payment);
        $this->subject->expects(self::once())->method('getState')->willReturn(Order::STATE_NEW);
        TestHelper::setProperty($this->plugin, 'oldState', Order::STATE_PENDING_PAYMENT);
        $this->subject->expects(self::once())->method('place');
        $result = $this->plugin->afterSetState($this->subject, $this->subject);
        $this->assertEquals($result, $this->subject);
    }

    /**
     * @test
     * @covers ::beforeSetStatus
     */
    public function beforeSetStatus_withRechargedOrder()
    {
        $this->subject->expects(self::exactly(2))->method('getPayment')->willReturn($this->payment);
        $this->payment->expects(self::once())->method('getMethod')->willReturn('boltpay');
        $this->subject->expects(self::once())->method('getIsRechargedOrder')->willReturn(true);
        $result = $this->plugin->beforeSetStatus($this->subject, \Bolt\Boltpay\Helper\Order::MAGENTO_ORDER_STATUS_PENDING);
        $this->assertEquals([Order::STATE_PROCESSING], $result);
    }
}
