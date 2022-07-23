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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Plugin\Webkul\Odoomagentoconnect\Model\ResourceModel;

use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Plugin\Webkul\Odoomagentoconnect\Model\ResourceModel\OrderPlugin;
use Magento\Sales\Model\Order;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class OrderPluginTest
 *
 * @coversDefaultClass \Bolt\Boltpay\Plugin\Webkul\Odoomagentoconnect\Model\ResourceModel\OrderPlugin
 */
class OrderPluginTest extends \Bolt\Boltpay\Test\Unit\BoltTestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|\Webkul\Odoomagentoconnect\Model\ResourceModel\Order
     */
    private $subjectMock;

    /**
     * @var Order
     */
    private $orderModel;

    /**
     * @var \Magento\Sales\Model\Order\Payment
     */
    private $payment;

    /**
     * @var OrderPlugin
     */
    private $orderPlugin;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    private $proceedMock;

    /**
     * Setup test dependencies, called before each test
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->orderPlugin = $this->objectManager->create(OrderPlugin::class);
        $this->orderModel = $this->objectManager->create(Order::class);
        $this->payment = $this->objectManager->create(\Magento\Sales\Model\Order\Payment::class);
        $this->proceedMock = $this->getMockBuilder(\stdClass::class)->setMethods(['proceed'])->getMock();

        $this->subjectMock = $this->getMockBuilder('\Webkul\Odoomagentoconnect\Model\ResourceModel\Order')
            ->disableAutoload()
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @test
     * that aroundExportOrder plugin prevents the original method call if the order is Bolt and state is pending
     *
     * @covers ::aroundExportOrder
     */
    public function aroundExportOrder_withBoltOrderAndPendingPaymentState_doesNotProceed()
    {
        $this->payment->setMethod('checkmo');
        $this->orderModel->setPayment($this->payment);
        $this->orderModel->setState(Order::STATE_PENDING_PAYMENT);
        static::assertEquals(
            0,
            $this->orderPlugin->aroundExportOrder(
                $this->subjectMock,
                [$this->proceedMock, 'proceed'],
                $this->orderModel
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
        $odooId = random_int();
        $this->payment->setMethod('checkmo');
        $this->orderModel->setPayment($this->payment);
        $this->proceedMock->expects(static::once())->method('proceed')->withAnyParameters()->willReturn($odooId);
        static::assertEquals(
            $odooId,
            $this->orderPlugin->aroundExportOrder(
                $this->subjectMock,
                [$this->proceedMock, 'proceed'],
                $this->orderModel
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
        $odooId = random_int();
        $this->payment->setMethod(Payment::METHOD_CODE);
        $this->orderModel->setPayment($this->payment);
        $this->orderModel->setState(\Bolt\Boltpay\Helper\Order::BOLT_ORDER_STATE_NEW);

        $proceedMock = $this->getMockBuilder(\stdClass::class)->setMethods(['proceed'])->getMock();
        $proceedMock->expects(static::once())->method('proceed')
            ->withAnyParameters()
            ->willReturn($odooId);
        static::assertEquals(
            $odooId,
            $this->orderPlugin->aroundExportOrder(
                $this->subjectMock,
                [$proceedMock, 'proceed'],
                $this->orderModel
            )
        );
    }
}
