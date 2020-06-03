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

namespace Bolt\Boltpay\Test\Unit\Observer\Adminhtml\Sales;

use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Event\Observer;
use Bolt\Boltpay\Observer\Adminhtml\Sales\CreateInvoiceForRechargedOrder;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * Class OrderCreateProcessDataObserverTest
 * @coversDefaultClass \Bolt\Boltpay\Observer\Adminhtml\Sales\CreateInvoiceForRechargedOrder
 */
class CreateInvoiceForRechargedOrderTest extends TestCase
{
    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var InvoiceSender
     */
    private $invoiceSender;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var CreateInvoiceForRechargedOrder
     */
    protected $creatingInvoiceForRechargeOrderObserverTest;

    /**
     * @var Observer
     */
    protected $observer;

    /**
     * @var Order
     */
    protected $order;

    /**
     * @var Order\Invoice
     */
    protected $invoice;

    protected function setUp()
    {
        $this->invoiceService = $this->createPartialMock(InvoiceService::class, ['prepareInvoice', 'setRequestedCaptureCase', 'register', 'save']);
        $this->invoiceSender = $this->createPartialMock(InvoiceSender::class, ['send']);
        $this->bugsnag = $this->createPartialMock(Bugsnag::class, ['notifyException']);
        $this->observer = $this->createPartialMock(
            Observer::class,
            ['getEvent', 'getOrder']
        );
        $this->order = $this->createPartialMock(
            Order::class,
            ['getIsRechargedOrder', 'canInvoice', 'addRelatedObject', 'addStatusHistoryComment', 'setIsCustomerNotified']
        );


        $this->invoice = $this->createPartialMock(
            Order\Invoice::class,
            ['getEmailSent']
        );
        $this->creatingInvoiceForRechargeOrderObserverTest = $this->getMockBuilder(CreateInvoiceForRechargedOrder::class)
            ->setConstructorArgs([
                $this->invoiceService,
                $this->invoiceSender,
                $this->bugsnag
            ])
            ->setMethods(['_init'])
            ->getMock();
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_withRechargeOrder()
    {
        $this->order->expects(self::once())->method('getIsRechargedOrder')->willReturn(true);
        $this->order->expects(self::once())->method('canInvoice')->willReturn(true);
        $this->order->expects(self::once())->method('addStatusHistoryComment')->willReturnSelf();
        $this->order->expects(self::once())->method('setIsCustomerNotified')->willReturnSelf();
        $this->observer->expects(self::once())->method('getEvent')->willReturnSelf();
        $this->observer->expects(self::once())->method('getOrder')->willReturn($this->order);
        $this->invoiceService->expects(self::once())->method('prepareInvoice')->with($this->order)->willReturnSelf();
        $this->invoiceService->expects(self::once())->method('setRequestedCaptureCase')->with(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE)->willReturnSelf();
        $this->invoiceService->expects(self::once())->method('register')->willReturnSelf();

        $this->order->expects(self::once())->method('addRelatedObject')->willReturnSelf();
        $this->invoice->expects(self::once())->method('getEmailSent')->willReturn(false);
        $this->invoiceSender->expects(self::once())->method('send')->with($this->invoice)->willReturnSelf();

        $this->invoiceService->expects(self::once())->method('save')->willReturn($this->invoice);
        $this->creatingInvoiceForRechargeOrderObserverTest->execute($this->observer);
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_withoutRechargeOrder()
    {
        $this->order->expects(self::once())->method('getIsRechargedOrder')->willReturn(false);
        $this->observer->expects(self::once())->method('getEvent')->willReturnSelf();
        $this->observer->expects(self::once())->method('getOrder')->willReturn($this->order);
        $this->invoiceService->expects(self::never())->method('prepareInvoice')->with($this->order)->willReturnSelf();

        $this->creatingInvoiceForRechargeOrderObserverTest->execute($this->observer);
    }
}

