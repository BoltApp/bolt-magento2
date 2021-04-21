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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Test\Unit\Plugin;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Plugin\OrderPlugin;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Model\Payment as BoltPayment;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\OrderPlugin
 */
class OrderPluginTest extends BoltTestCase
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

    protected $objectManager;

    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->plugin = $this->objectManager->create(OrderPlugin::class);

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
        $order = $this->objectManager->create(Order::class);
        $result = $this->plugin->beforeSetState($order, 'normal_state');
        $this->assertEquals(['normal_state'], $result);
    }

    /**
     * @test
     * @covers ::beforeSetState
     */
    public function beforeSetState_withPaymentMethodIsNotBoltPay()
    {
        $order = $this->objectManager->create(Order::class);
        $payment = $this->objectManager->create(Payment::class);
        $payment->setMethod('is_not_boltpay');
        $order->setPayment($payment);

        $result = $this->plugin->beforeSetState($order, 'normal_state');
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
        $order = $this->objectManager->create(Order::class);
        $order->setState($orderState);
        $payment = $this->objectManager->create(Payment::class);
        $payment->setMethod('boltpay');
        $order->setPayment($payment);
        $result = $this->plugin->beforeSetState($order, $stateParameter);
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
        $order = $this->objectManager->create(Order::class);
        $payment = $this->objectManager->create(Payment::class);
        $payment->setMethod('boltpay');
        $order->setPayment($payment);
        $order->setIsRechargedOrder(true);

        $result = $this->plugin->beforeSetState($order, 'new');
        $this->assertEquals([Order::STATE_PROCESSING], $result);
    }

    /**
     * @test
     * @covers ::beforeSetStatus
     */
    public function beforeSetStatus_withoutPayment()
    {
        $order = $this->objectManager->create(Order::class);
        $result = $this->plugin->beforeSetStatus($order, 'normal_status');
        $this->assertEquals(['normal_status'], $result);
    }

    /**
     * @test
     * @covers ::beforeSetStatus
     */
    public function beforeSetStatus_withPaymentMethodIsNotBoltPay()
    {
        $order = $this->objectManager->create(Order::class);
        $payment = $this->objectManager->create(Payment::class);
        $payment->setMethod('is_not_boltpay');
        $order->setPayment($payment);
        $result = $this->plugin->beforeSetStatus($order, 'normal_status');
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
        $order = $this->createPartialMock(Order::class, [
            'getPayment', 'getConfig', 'getStateDefaultStatus', 'getStatus',
        ]);
        $payment = $this->createPartialMock(Payment::class, ['getMethod']);
        $order->expects(self::exactly(2))->method('getPayment')->willReturn($payment);
        $payment->expects(self::once())->method('getMethod')->willReturn('boltpay');
        $order->expects(self::any())->method('getConfig')->willReturnSelf();
        $order->expects(self::any())->method('getStateDefaultStatus')->willReturn($stateDefaultStatus);
        $order->expects(self::any())->method('getStatus')->willReturn($orderStatus);
        $result = $this->plugin->beforeSetStatus($order, $statusParameter);
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
        $order = $this->objectManager->create(Order::class);
        $payment = $this->objectManager->create(Payment::class);
        $payment->setMethod(BoltPayment::METHOD_CODE);
        $order->setPayment($payment);
        $order->setState($orderState);
        $result = $this->plugin->aroundPlace($order, $this->proceed);
        $this->assertEquals($result, $order);


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
        $order = $this->objectManager->create(Order::class);
        $payment = $this->objectManager->create(Payment::class);
        $payment->setMethod($paymentMethod);
        $order->setPayment($payment);

        $this->callback->expects(self::once())->method('__invoke');
        $this->plugin->aroundPlace($order, $this->proceed);
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
        $order = $this->createPartialMock(Order::class, ['getPayment', 'getState']);
        $payment = $this->createPartialMock(Payment::class, ['getMethod']);
        $payment->method('getMethod')->willReturn('other_method');
        $order->method('getPayment')->willReturn($payment);
        $order->method('getState')->willReturn(Order::STATE_NEW);
        $this->callback->expects(self::once())->method('__invoke');
        $this->plugin->aroundPlace($order, $this->proceed);
    }

    /**
     * @test
     * @covers ::afterPlace
     */
    public function afterPlace_ifNotBoltPayment_setStateNotCalled()
    {
        $subjectParameter = $this->objectManager->create(Order::class);
        $resultParameter = $this->objectManager->create(Order::class);
        $payment = $this->objectManager->create(Payment::class);
        $payment->setMethod('other_method');
        $subjectParameter->setPayment($payment);
        $subjectParameter->setState(Order::STATE_PROCESSING);
        $result = $this->plugin->afterPlace($subjectParameter, $resultParameter);
        $this->assertEquals($resultParameter, $result);
        $this->assertEquals(Order::STATE_PROCESSING, $subjectParameter->getState());
    }

    /**
     * @test
     * @covers ::afterPlace
     */
    public function afterPlace_ifBoltPaymentAndNoStateSet_setStateIsCalled()
    {
        $subjectParameter = $this->objectManager->create(Order::class);
        $resultParameter = $this->objectManager->create(Order::class);
        $payment = $this->objectManager->create(Payment::class);
        $payment->setMethod(BoltPayment::METHOD_CODE);
        $subjectParameter->setPayment($payment);
        $subjectParameter->setStatus(Order::STATE_PROCESSING);
        $result = $this->plugin->afterPlace($subjectParameter, $resultParameter);
        $this->assertEquals($resultParameter, $result);
        $this->assertEquals(Order::STATE_PENDING_PAYMENT, $subjectParameter->getState());
    }

    /**
     * @test
     * @covers ::afterSetState
     */
    public function afterSetState_ifNotBoltPayment_orderPlaceNotCalled()
    {
        $order = $this->createPartialMock(Order::class, ['getPayment', 'getState', 'place',]);
        $payment = $this->createPartialMock(Payment::class, ['getMethod']);
        $payment->method('getMethod')->willReturn('other_method');
        $order->method('getPayment')->willReturn($payment);
        $order->method('getState');
        $order->expects(self::never())->method('place');
        $resultParameter = $this->objectManager->create(Order::class);
        $result = $this->plugin->afterSetState($order, $resultParameter);
        $this->assertEquals($result, $resultParameter);
    }

    /**
     * @test
     * @covers ::afterSetState
     */
    public function afterSetState_ifBoltPaymentAndOrderStateNotNew_orderPlaceNotCalled()
    {
        $order = $this->createPartialMock(Order::class, ['getPayment', 'getState', 'place',]);
        $payment = $this->createPartialMock(Payment::class, ['getMethod']);
        $payment->method('getMethod')->willReturn(BoltPayment::METHOD_CODE);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getState')->willReturn('other_state');
        $order->expects(self::never())->method('place');
        $resultParameter = $this->objectManager->create(Order::class);
        $result = $this->plugin->afterSetState($order, $resultParameter);
        $this->assertEquals($result, $resultParameter);
    }

    /**
     * @test
     * @covers ::afterSetState
     */
    public function afterSetState_ifBoltPaymentAndOrderStateNewAndOldOrderStateNotPendingPayment_orderPlaceNotCalled()
    {
        $order = $this->createPartialMock(Order::class, ['getPayment', 'getState', 'place',]);
        $payment = $this->createPartialMock(Payment::class, ['getMethod']);
        $payment->method('getMethod')->willReturn(BoltPayment::METHOD_CODE);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getState')->willReturn(Order::STATE_NEW);
        TestHelper::setProperty($this->plugin, 'oldState', 'other_state');
        $order->expects(self::never())->method('place');
        $resultParameter = $this->objectManager->create(Order::class);
        $result = $this->plugin->afterSetState($order, $resultParameter);
        $this->assertEquals($result, $resultParameter);
    }

    /**
     * @test
     * @covers ::afterSetState
     */
    public function afterSetState_ifBoltPaymentAndOrderStateNewAndOldOrderStatePendingPayment_orderPlaceCalled()
    {
        $order = $this->createPartialMock(Order::class, ['getPayment', 'getState', 'place',]);
        $payment = $this->createPartialMock(Payment::class, ['getMethod']);
        $payment->expects(self::once())->method('getMethod')->willReturn(BoltPayment::METHOD_CODE);
        $order->expects(self::exactly(2))->method('getPayment')->willReturn($payment);
        $order->expects(self::once())->method('getState')->willReturn(Order::STATE_NEW);
        TestHelper::setProperty($this->plugin, 'oldState', Order::STATE_PENDING_PAYMENT);
        $order->expects(self::once())->method('place');
        $result = $this->plugin->afterSetState($order, $order);
        $this->assertEquals($result, $order);
    }

    /**
     * @test
     * @covers ::beforeSetStatus
     */
    public function beforeSetStatus_withRechargedOrder()
    {
        $order = $this->objectManager->create(Order::class);
        $payment = $this->objectManager->create(Payment::class);
        $payment->setMethod(BoltPayment::METHOD_CODE);
        $order->setPayment($payment);
        $order->setIsRechargedOrder(true);
        $result = $this->plugin->beforeSetStatus($order, \Bolt\Boltpay\Helper\Order::MAGENTO_ORDER_STATUS_PENDING);
        $this->assertEquals([Order::STATE_PROCESSING], $result);
    }
}
