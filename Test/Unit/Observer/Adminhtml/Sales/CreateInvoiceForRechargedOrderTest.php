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
 * @copyright  Copyright (c) 2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Observer\Adminhtml\Sales;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Observer\Adminhtml\Sales\CreateInvoiceForRechargedOrder;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Exception;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class CreateInvoiceForRechargedOrderTest
 *
 * @coversDefaultClass \Bolt\Boltpay\Observer\Adminhtml\Sales\CreateInvoiceForRechargedOrder
 */
class CreateInvoiceForRechargedOrderTest extends BoltTestCase
{
    /**
     * @var CreateInvoiceForRechargedOrder
     */
    protected $createInvoiceForRechargedOrderTest;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->createInvoiceForRechargedOrderTest = $this->objectManager->create(CreateInvoiceForRechargedOrder::class);
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_withoutRechargeOrder()
    {
        $order = TestUtils::createDumpyOrder();
        $observer = $this->objectManager->create(Observer::class);
        $event = $this->objectManager->create(\Magento\Framework\DataObject::class);
        $event->setOrder($order);
        $observer->setEvent($event);
        $this->createInvoiceForRechargedOrderTest->execute($observer);
        self::assertEquals(0, $order->getStatusHistoryCollection()->getSize());
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_withRechargeOrder()
    {
        $order = TestUtils::createDumpyOrder();
        $order->setIsRechargedOrder(true);
        $observer = $this->objectManager->create(Observer::class);
        $event = $this->objectManager->create(\Magento\Framework\DataObject::class);
        $event->setOrder($order);
        $observer->setEvent($event);
        $invoiceSender = $this->createPartialMock(InvoiceSender::class, ['send']);
        $invoiceSender->method('send')->withAnyParameters()->willReturnSelf();
        TestHelper::setProperty($this->createInvoiceForRechargedOrderTest, 'invoiceSender', $invoiceSender);
        $this->createInvoiceForRechargedOrderTest->execute($observer);
        self::assertEquals('Invoice #%1 is created. Notification email is sent to customer.', $order->getStatusHistories()[0]->getComment()->getText());
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_throwsException()
    {
        $e = new Exception('test exception');
        $observer = $this->createPartialMock(Observer::class, ['getEvent']);
        $observer->expects(self::once())->method('getEvent')->willThrowException($e);

        $bugsnag = $this->createPartialMock(Bugsnag::class, ['notifyException']);
        $bugsnag->expects(self::once())->method('notifyException')->with($e);

        TestHelper::setProperty($this->createInvoiceForRechargedOrderTest, 'bugsnag', $bugsnag);
        $this->createInvoiceForRechargedOrderTest->execute($observer);
    }
}
