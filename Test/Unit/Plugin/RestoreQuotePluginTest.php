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

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Plugin\RestoreQuotePlugin;
use Magento\Checkout\Model\Session as CheckoutSession;
use Bolt\Boltpay\Model\Payment as BoltPayment;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\RestoreQuotePlugin
 */
class RestoreQuotePluginTest extends TestCase
{
    /**
     * @var RestoreQuotePlugin
     */
    private $plugin;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var Order
     */
    private $order;

    /**
     * @var Payment
     */
    private $payment;

    /**
     * @var callable
     */
    protected $proceed;

    /**
     * @var Bugsnag
     */
    protected $bugsnag;

    /**
     * @var CheckoutSession
     */
    protected $subject;

    /** @var callable|MockObject */
    protected $callback;

    public function setUp()
    {
        $this->checkoutSession = $this->createPartialMock(
            CheckoutSession::class,
            ['getLastRealOrder']
        );

        $this->order = $this->createPartialMock(
            Order::class,
            ['getPayment','getQuoteId']
        );

        $this->payment = $this->createPartialMock(
            Order::class,
            ['getMethod']
        );

        /** @var callable $callback */
        $this->callback = $callback = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])->getMock();
        $this->proceed = function () use ($callback) {
            return $callback();
        };

        $this->subject = $this->createMock(CheckoutSession::class);
        $this->bugsnag = $this->createPartialMock(Bugsnag::class, ['notifyError']);

        $objectManager = new ObjectManager($this);
        $this->plugin = $objectManager->getObject(
            RestoreQuotePlugin::class,
            [
                'checkoutSession' => $this->checkoutSession,
                'bugsnag' => $this->bugsnag
            ]
        );
    }

    /**
     * @test
     * @covers ::aroundRestoreQuote
     */
    public function aroundRestoreQuote_ifOrderWithPaymentMethodIsBoltPay()
    {
        $this->checkoutSession->method('getLastRealOrder')->willReturn($this->order);
        $this->payment->method('getMethod')->willReturn(BoltPayment::METHOD_CODE);
        $this->order->method('getPayment')->willReturn($this->payment);
        $this->order->method('getQuoteId')->willReturn(111);

        $this->bugsnag->method('notifyError')
            ->with('Ignore restoring quote if payment method is Boltpay', 'Quote Id: 111')
            ->willReturnSelf();

        $this->callback->expects(self::never())->method('__invoke');
        $result = $this->plugin->aroundRestoreQuote($this->subject, $this->proceed);
        $this->assertFalse($result);
    }

    /**
     * @test
     * @covers ::aroundRestoreQuote
     */
    public function aroundRestoreQuote_ifOrderWithoutPayment()
    {
        $this->checkoutSession->method('getLastRealOrder')->willReturn($this->order);
        $this->order->method('getPayment')->willReturn(null);
        $this->callback->expects(self::once())->method('__invoke');
        $this->plugin->aroundRestoreQuote($this->subject, $this->proceed);
    }

    /**
     * @test
     * @covers ::aroundRestoreQuote
     */
    public function aroundRestoreQuote_ifOrderWithPaymentMethodIsNotBoltPay()
    {
        $this->checkoutSession->method('getLastRealOrder')->willReturn($this->order);
        $this->payment->method('getMethod')->willReturn('is_not_BoltPay');
        $this->order->method('getPayment')->willReturn($this->payment);
        $this->callback->expects(self::once())->method('__invoke');
        $this->plugin->aroundRestoreQuote($this->subject, $this->proceed);
    }
}
