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

namespace Bolt\Boltpay\Test\Unit\Plugin\Webkul\Odoomagentoconnect\Model\ResourceModel;

use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Plugin\Webkul\Odoomagentoconnect\Model\ResourceModel\OrderPlugin;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class OrderPluginTest
 *
 * @coversDefaultClass \Bolt\Boltpay\Plugin\Webkul\Odoomagentoconnect\Model\ResourceModel\OrderPlugin
 */
class OrderPluginTest extends \Bolt\Boltpay\Test\Unit\BoltTestCase
{

    /**
     * @var OrderPlugin|MockObject
     */
    private $currentMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|\Webkul\Odoomagentoconnect\Model\ResourceModel\Order
     */
    private $subjectMock;

    /**
     * @var Order|\PHPUnit_Framework_MockObject_MockObject
     */
    private $orderMock;

    /**
     * @var \Magento\Sales\Model\Order\Payment|\PHPUnit_Framework_MockObject_MockObject
     */
    private $paymentMock;

    /**
     * @var Quote|MockObject
     */
    private $quoteMock;

    /**
     * Setup test dependencies, called before each test
     */
    public function setUpInternal()
    {
        $this->currentMock = $this->getMockBuilder(OrderPlugin::class)->setMethods(null)->getMock();
        $this->subjectMock = $this->getMockBuilder('\Webkul\Odoomagentoconnect\Model\ResourceModel\Order')
            ->disableAutoload()
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentMock = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $this->orderMock = $this->createMock(Order::class);
        $this->quoteMock = $this->createMock(Quote::class);
    }

    /**
     * @test
     * that aroundExportOrder plugin prevents the original method call if the order is Bolt and state is pending
     *
     * @covers ::aroundExportOrder
     */
    public function aroundExportOrder_withBoltOrderAndPendingPaymentState_doesNotProceed()
    {
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->paymentMock->expects(static::once())->method('getMethod')->willReturn(Payment::METHOD_CODE);
        $this->orderMock->expects(static::once())->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);
        $proceedMock = $this->getMockBuilder(\stdClass::class)->setMethods(['proceed'])->getMock();
        $proceedMock->expects(static::never())->method('proceed');
        static::assertEquals(
            0,
            $this->currentMock->aroundExportOrder(
                $this->subjectMock,
                [$proceedMock, 'proceed'],
                $this->orderMock
            )
        );
    }

    /**
     * @test
     * that aroundExportOrder plugin calls the original method call if the order is noy Bolt
     *
     * @covers ::aroundExportOrder
     */
    public function aroundExportOrder_withNonBoltOrder_proceedsToOriginal()
    {
        $odooId = mt_rand();
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->paymentMock->expects(static::once())->method('getMethod')->willReturn('checkmo');
        $this->orderMock->expects(static::never())->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);
        $proceedMock = $this->getMockBuilder(\stdClass::class)->setMethods(['proceed'])->getMock();
        $proceedMock->expects(static::once())->method('proceed')->with($this->orderMock, $this->quoteMock)
            ->willReturn($odooId);
        static::assertEquals(
            $odooId,
            $this->currentMock->aroundExportOrder(
                $this->subjectMock,
                [$proceedMock, 'proceed'],
                $this->orderMock,
                $this->quoteMock
            )
        );
    }

    /**
     * @test
     * that aroundExportOrder plugin calls the original method call if the order is Bolt and state is not pending
     *
     * @covers ::aroundExportOrder
     */
    public function aroundExportOrder_withNonPendingBoltOrder_proceedsToOriginal()
    {
        $odooId = mt_rand();
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->paymentMock->expects(static::once())->method('getMethod')->willReturn(Payment::METHOD_CODE);
        $this->orderMock->expects(static::once())->method('getState')->willReturn(Order::STATE_NEW);
        $proceedMock = $this->getMockBuilder(\stdClass::class)->setMethods(['proceed'])->getMock();
        $proceedMock->expects(static::once())->method('proceed')->with($this->orderMock, $this->quoteMock)
            ->willReturn($odooId);
        static::assertEquals(
            $odooId,
            $this->currentMock->aroundExportOrder(
                $this->subjectMock,
                [$proceedMock, 'proceed'],
                $this->orderMock,
                $this->quoteMock
            )
        );
    }
}
