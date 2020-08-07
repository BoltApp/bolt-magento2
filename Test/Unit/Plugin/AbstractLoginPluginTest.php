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

use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Plugin\AbstractLoginPlugin;
use Magento\Framework\Controller\ResultFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\AbstractLoginPlugin
 */
class AbstractLoginPluginTest extends TestCase
{
    /**
     * @var AbstractLoginPlugin
     */
    private $abstractLoginPlugin;

    /**
     * @var CustomerSession
     */
    private $customerSession;
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var Quote
     */
    private $quote;

    /**
     * @var Quote\Item
     */
    private $quoteItem;

    public function setUp()
    {
        $this->customerSession = $this->createPartialMock(
            CustomerSession::class,
            ['isLoggedIn']
        );

        $this->checkoutSession = $this->createPartialMock(
            CheckoutSession::class,
            ['hasQuote', 'getQuote', 'setBoltInitiateCheckout']
        );

        $this->quote = $this->createPartialMock(
            Quote::class,
            ['getAllVisibleItems']
        );

        $this->quoteItem = $this->createMock(Quote\Item::class);

        $this->resultFactory = $this->createMock(ResultFactory::class);

        $this->bugsnag = $this->createPartialMock(
            Bugsnag::class,
            ['notifyException']
        );

        $this->abstractLoginPlugin = $this->getMockBuilder(AbstractLoginPlugin::class)
            ->setConstructorArgs([
                    $this->customerSession,
                    $this->checkoutSession,
                    $this->resultFactory,
                    $this->bugsnag
                ]
            )
            ->setMethods([])
            ->getMockForAbstractClass();
    }

    /**
     * @test
     * @dataProvider dataProvider_isCustomerLoggedIn
     *
     * @param $expected
     * @throws \ReflectionException
     */
    public function isCustomerLoggedIn($expected)
    {
        $this->customerSession->method('isLoggedIn')->willReturn($expected);
        $this->assertEquals($expected, TestHelper::invokeMethod($this->abstractLoginPlugin, 'isCustomerLoggedIn'));
    }

    public function dataProvider_isCustomerLoggedIn()
    {
        return [
            [true], [false]
        ];
    }

    /**
     * @test
     * @dataProvider dataProvider_hasCart_withoutQuoteItems
     * @param $hasQuote
     * @throws \ReflectionException
     */
    public function hasCart_withoutQuoteItems($hasQuote)
    {
        $this->checkoutSession->method('hasQuote')->willReturn($hasQuote);
        $this->checkoutSession->method('getQuote')->willReturn($this->quote);
        $this->quote->method('getAllVisibleItems')->willReturn([]);

        $this->assertFalse(TestHelper::invokeMethod($this->abstractLoginPlugin, 'hasCart'));
    }

    public function dataProvider_hasCart_withoutQuoteItems()
    {
        return [
            [true], [false]
        ];
    }

    /**
     * @test
     * @dataProvider dataProvider_hasCart_withQuoteItems
     * @param $hasQuote
     * @param $expected
     * @throws \ReflectionException
     */
    public function hasCart_withQuoteItems($hasQuote, $expected)
    {
        $this->checkoutSession->method('hasQuote')->willReturn($hasQuote);
        $this->checkoutSession->method('getQuote')->willReturn($this->quote);
        $this->quote->method('getAllVisibleItems')->willReturn([$this->quoteItem]);

        $this->assertEquals($expected, TestHelper::invokeMethod($this->abstractLoginPlugin, 'hasCart'));
    }

    public function dataProvider_hasCart_withQuoteItems()
    {
        return [
            [true, true], [false, false]
        ];
    }

    /**
     * @test
     */
    public function setBoltInitiateCheckout()
    {
        $this->checkoutSession->expects(self::once())->method('setBoltInitiateCheckout')->with(true)->willReturnSelf();
        TestHelper::invokeMethod($this->abstractLoginPlugin, 'setBoltInitiateCheckout');
    }

    /**
     * @test
     *
     */
    public function notifyException()
    {
        $this->bugsnag->expects(self::once())->method('notifyException')->with(new \Exception('test'))->willReturnSelf();
        TestHelper::invokeMethod($this->abstractLoginPlugin, 'notifyException', [new \Exception('test')]);
    }
}
