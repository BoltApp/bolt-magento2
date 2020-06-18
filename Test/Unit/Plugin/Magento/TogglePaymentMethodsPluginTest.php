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

namespace Bolt\Boltpay\Test\Unit\Plugin\Magento;

use Bolt\Boltpay\Plugin\Magento\TogglePaymentMethodsPlugin;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Magento\Quote\Api\Data\PaymentMethodInterface;

/**
 * Class TogglePaymentMethodsPluginTest
 * @package Bolt\Boltpay\Test\Unit\Plugin\Magento
 * @coversDefaultClass \Bolt\Boltpay\Plugin\Magento\TogglePaymentMethodsPlugin
 */
class TogglePaymentMethodsPluginTest extends TestCase
{
    /** @var ObserverInterface|MockObject  */
    protected $subject;

    /** @var Observer|MockObject  */
    protected $observer;

    /** @var callable  */
    protected $proceed;

    /** @var Event|MockObject  */
    protected $event;

    /** @var PaymentMethodInterface|MockObject  */
    protected $payment;

    /** @var callable|MockObject  */
    protected $callback;

    /** @var TogglePaymentMethodsPlugin  */
    protected $plugin;

    /** @var DataObject|MockObject  */
    protected $result;

    protected function setUp()
    {
        $this->plugin = (new ObjectManager($this))->getObject(TogglePaymentMethodsPlugin::class);
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
        $this->payment = $this->createMock(PaymentMethodInterface::class);
        $this->result = $this->createMock(DataObject::class);
        $this->event = $this->getMockBuilder(Event::class)
            ->setMethods(['getMethodInstance', 'getResult', 'getQuote'])->getMock();
        $this->event->method('getMethodInstance')->willReturn($this->payment);
        $this->event->method('getResult')->willReturn($this->result);
        $this->observer->method('getEvent')->willReturn($this->event);
    }

    /**
     * @test
     * @covers ::aroundExecute
     */
    public function aroundExecute_ifNonBoltPayment_return()
    {
        $this->callback->expects(self::once())->method('__invoke')->with($this->observer);
        $this->payment->expects(self::once())->method('getCode')->willReturn('other_method');
        $this->event->expects(self::never())->method('getResult');
        $this->plugin->aroundExecute($this->subject, $this->proceed, $this->observer);
    }

    /**
     * @test
     * @covers ::aroundExecute
     */
    public function aroundExecute_ifBoltPaymentAndResultIsAvailable_return()
    {
        $this->callback->expects(self::once())->method('__invoke')->with($this->observer);
        $this->payment->expects(self::once())->method('getCode')
            ->willReturn(\Bolt\Boltpay\Model\Payment::METHOD_CODE);
        $this->event->expects(self::once())->method('getResult');
        $this->result->expects(self::once())->method('getData')
            ->with('is_available')->willReturn(true);
        $this->event->expects(self::never())->method('getQuote');
        $this->plugin->aroundExecute($this->subject, $this->proceed, $this->observer);
    }

    /**
     * @test
     * @covers ::aroundExecute
     */
    public function aroundExecute_ifBoltPaymentAndNotResultIsAvailableAndNoQuote_return()
    {
        $this->callback->expects(self::once())->method('__invoke')->with($this->observer);
        $this->payment->expects(self::once())->method('getCode')
            ->willReturn(\Bolt\Boltpay\Model\Payment::METHOD_CODE);
        $this->event->expects(self::once())->method('getResult');
        $this->result->expects(self::once())->method('getData')
            ->with('is_available')->willReturn(false);
        $this->event->expects(self::once())->method('getQuote')->willReturn(null);
        $this->result->expects(self::never())->method('setData');
        $this->plugin->aroundExecute($this->subject, $this->proceed, $this->observer);
    }

    /**
     * @test
     * @covers ::aroundExecute
     */
    public function aroundExecute_ifBoltPaymentAndNotResultIsAvailableAndQuoteZeroTotal_setIsAvailable()
    {
        $this->callback->expects(self::once())->method('__invoke')->with($this->observer);
        $this->payment->expects(self::once())->method('getCode')
            ->willReturn(\Bolt\Boltpay\Model\Payment::METHOD_CODE);
        $this->event->expects(self::once())->method('getResult');
        $this->result->expects(self::once())->method('getData')
            ->with('is_available')->willReturn(false);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()->setMethods(['getBaseGrandTotal'])->getMock();
        $quote->expects(self::once())->method('getBaseGrandTotal')->willReturn(0);
        $this->event->expects(self::once())->method('getQuote')->willReturn($quote);
        $this->result->expects(self::once())->method('setData')
            ->with('is_available', true);
        $this->plugin->aroundExecute($this->subject, $this->proceed, $this->observer);
    }

}