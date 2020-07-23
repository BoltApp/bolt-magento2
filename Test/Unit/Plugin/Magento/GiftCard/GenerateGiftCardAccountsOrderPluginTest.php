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
namespace Bolt\Boltpay\Test\Unit\Plugin\Magento\GiftCard;

use Magento\Framework\Event\ObserverInterface;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Model\Payment as BoltPayment;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Bolt\Boltpay\Plugin\Magento\GiftCard\GenerateGiftCardAccountsOrderPlugin;
use Magento\Framework\Event\Observer;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Event;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Class GenerateGiftCardAccountsOrderPluginTest
 * @package Bolt\Boltpay\Test\Unit\Plugin\Magento\GiftCard
 * @coversDefaultClass \Bolt\Boltpay\Plugin\Magento\GiftCard\GenerateGiftCardAccountsOrderPlugin
 */
class GenerateGiftCardAccountsOrderPluginTest extends TestCase
{
    const OTHER_METHOD = 'NON_BOLT';
    const EXPECTS_NEVER = 'never';
    const EXPECTS_ONCE = 'once';

    /** @var ObserverInterface|MockObject  */
    protected $subject;

    /** @var Observer|MockObject  */
    protected $observer;

    /** @var Order|MockObject  */
    protected $order;

    /** @var callable  */
    protected $proceed;

    /** @var Event|MockObject  */
    protected $event;

    /** @var OrderPaymentInterface|MockObject  */
    protected $payment;

    /** @var callable|MockObject  */
    protected $callback;

    /** @var GenerateGiftCardAccountsOrderPlugin  */
    protected $plugin;

    protected function setUp()
    {
        $objectManager = new ObjectManager($this);
        $this->plugin = $objectManager->getObject(
            GenerateGiftCardAccountsOrderPlugin::class
        );

        $this->subject = $this->createMock(
            ObserverInterface::class
        );

        $this->observer = $observer = $this->createMock(Observer::class);

        /** @var callable $callback */
        $this->callback = $callback = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])->getMock();
        $this->proceed = function (Observer $obsrvr) use ($observer, $callback) {
            $this->assertEquals($obsrvr, $observer);
            return $callback($obsrvr);
        };

        $this->payment = $this->createMock(OrderPaymentInterface::class);

        $this->order = $this->createMock(Order::class);
        $this->order->method('getPayment')->willReturn($this->payment);

        $this->event = $this->getMockBuilder(Event::class)->setMethods(['getOrder'])->getMock();
        $this->event->method('getOrder')->willReturn($this->order);

        $this->observer->method('getEvent')->willReturn($this->event);
    }

    /**
     * @test
     * @dataProvider methodStatusExpects
     * @covers ::aroundExecute
     */
    public function aroundExecute($paymentMethod, $orderStatus, $expects)
    {
        $this->payment->method('getMethod')->willReturn($paymentMethod);
        $this->order->method('getStatus')->willReturn($orderStatus);

        $this->callback->expects($this->$expects())->method('__invoke')->with($this->observer);

        $this->plugin->aroundExecute($this->subject, $this->proceed, $this->observer);
    }

    public function methodStatusExpects()
    {
        return [
            [self::OTHER_METHOD, Order::STATE_PENDING_PAYMENT, self::EXPECTS_ONCE],
            [self::OTHER_METHOD, Order::STATE_CANCELED, self::EXPECTS_ONCE],
            [self::OTHER_METHOD, Order::STATE_PROCESSING, self::EXPECTS_ONCE],
            [BoltPayment::METHOD_CODE, Order::STATE_PROCESSING, self::EXPECTS_ONCE],
            [BoltPayment::METHOD_CODE, Order::STATE_NEW, self::EXPECTS_ONCE],
            [BoltPayment::METHOD_CODE, Order::STATE_PENDING_PAYMENT, self::EXPECTS_NEVER],
            [BoltPayment::METHOD_CODE, Order::STATE_CANCELED, self::EXPECTS_NEVER]
        ];
    }
}
