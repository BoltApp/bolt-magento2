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

use Bolt\Boltpay\Plugin\ClearQuote;
use PHPUnit\Framework\TestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Checkout\Model\Session as CheckoutSession;
use Bolt\Boltpay\Helper\Cart as CartHelper;

/**
 * Class QuotePluginTest
 * @package Bolt\Boltpay\Test\Unit\Plugin
 * @coversDefaultClass \Bolt\Boltpay\Plugin\ClearQuote
 */
class ClearQuoteTest extends TestCase
{
    /**
     * @var CartHelper
     */
    protected $cartHelper;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var ClearQuote
     */
    protected $plugin;

    public function setUp()
    {
        $this->cartHelper = $this->getMockBuilder(CartHelper::class)
            ->setMethods(['getIsActive', 'getQuoteById'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutSession = $this->getMockBuilder(CheckoutSession::class)
            ->setMethods(['replaceQuote', 'setLoadInactive', 'getQuote', 'save', 'getId', 'getLastSuccessQuoteId'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->plugin = (new ObjectManager($this))->getObject(
            ClearQuote::class,
            [
                'cartHelper' => $this->cartHelper,
            ]
        );
    }

    /**
     * @test
     * @covers ::beforeClearQuote
     * @throws \Exception
     */
    public function afterClearQuote()
    {
        $this->checkoutSession->expects(self::once())->method('setLoadInactive')->with(false)->willReturnSelf();
        $this->checkoutSession->expects(self::once())->method('getQuote')->willReturnSelf();
        $this->checkoutSession->expects(self::once())->method('save')->willReturnSelf();
        $this->checkoutSession->expects(self::once())->method('replaceQuote')->willReturnSelf();
        $this->plugin->afterClearQuote($this->checkoutSession);
    }

    /**
     * @test
     * @dataProvider provider_beforeClearQuote_returnNull
     * @covers ::beforeClearQuote
     * @param $currentQuoteId
     * @param $orderQuoteId
     * @throws \Exception
     */
    public function beforeClearQuote_withGetQuoteByIdIsNotCalled_returnNull($currentQuoteId, $orderQuoteId)
    {
        $this->checkoutSession->expects(self::once())->method('getQuote')->willReturnSelf();
        $this->checkoutSession->expects(self::once())->method('getId')->willReturn($currentQuoteId);
        $this->checkoutSession->expects(self::once())->method('getLastSuccessQuoteId')->willReturn($orderQuoteId);
        $this->assertNull($this->plugin->beforeClearQuote($this->checkoutSession));
    }

    public function provider_beforeClearQuote_returnNull()
    {
        return [
            ['1', null],
            [null, '1'],
            ['1', '1']
        ];
    }

    /**
     * @test
     * @covers ::beforeClearQuote
     * @throws \Exception
     */
    public function beforeClearQuote_withGetQuoteByIdIsCalled_returnNull()
    {
        $this->checkoutSession->expects(self::once())->method('getQuote')->willReturnSelf();
        $this->checkoutSession->expects(self::once())->method('getId')->willReturn(1);
        $this->checkoutSession->expects(self::once())->method('getLastSuccessQuoteId')->willReturn(2);
        $this->cartHelper->expects(self::once())->method('getQuoteById')->with(2)->willReturn(null);
        $this->assertNull($this->plugin->beforeClearQuote($this->checkoutSession));
    }
}
