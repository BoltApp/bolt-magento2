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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Plugin;

use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Plugin\SalesRuleQuoteDiscountPlugin;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\SalesRule\Model\Quote\Discount;
use Magento\Store\Model\Store;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ShippingAssignment;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

/**
 * Class SalesRuleQuoteDiscountPluginTest
 * @package Bolt\Boltpay\Test\Unit\Plugin
 * @coversDefaultClass \Bolt\Boltpay\Plugin\SalesRuleQuoteDiscountPlugin
 */
class SalesRuleQuoteDiscountPluginTest extends BoltTestCase
{
    /**
     * @var SalesRuleQuoteDiscountPlugin
     */
    protected $plugin;

    /** @var CheckoutSession */
    protected $checkoutSession;

    /**
     * @var Discount
     */
    protected $subject;

    /** @var ShippingAssignmentInterface */
    protected $shippingAssignment;
    
    /** @var CartHelper */
    protected $cartHelper;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    public function setUpInternal()
    {
        $this->subject = $this->createMock(Discount::class);
        $this->cartHelper = $this->createPartialMock(
            CartHelper::class,
            ['isCollectDiscountsByPlugin']
        );
        $this->sessionHelper = $this->createPartialMock(
            SessionHelper::class,
            ['getCheckoutSession']
        );
        $this->checkoutSession = $this->createPartialMock(
            CheckoutSession::class,
            ['setBoltCollectSaleRuleDiscounts']
        );
        $this->plugin = (new ObjectManager($this))->getObject(
            SalesRuleQuoteDiscountPlugin::class,
            [
                'sessionHelper' => $this->sessionHelper,
                'cartHelper' => $this->cartHelper
            ]
        );
    }

    /**
     * @test
     * @covers ::beforeCollect
     */
    public function beforeCollect_resetSaleRuleDiscountsToCheckoutSession()
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->cartHelper->expects(self::once())
                        ->method('isCollectDiscountsByPlugin')
                        ->with($quote)
                        ->willReturn(true);
        $shippingAssignment = $this->createPartialMock(
            ShippingAssignment::class,
            ['getItems']
        );
        $item = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->getMock();
        $shippingAssignment->expects(self::once())
                        ->method('getItems')
                        ->willReturn([$item]);
        $this->checkoutSession->expects(self::once())
                            ->method('setBoltCollectSaleRuleDiscounts')
                            ->with([]);
        $this->sessionHelper->expects(self::once())
                            ->method('getCheckoutSession')
                            ->willReturn($this->checkoutSession);
        $this->plugin->beforeCollect($this->subject, $quote, $shippingAssignment, null);
    }

    /**
     * @test
     * @covers ::beforeCollect
     */
    public function beforeCollect_noItemInShippingAssignment_doNothing()
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->cartHelper->expects(self::once())
                        ->method('isCollectDiscountsByPlugin')
                        ->with($quote)
                        ->willReturn(true);
        $shippingAssignment = $this->createPartialMock(
            ShippingAssignment::class,
            ['getItems']
        );
        $shippingAssignment->expects(self::once())
                        ->method('getItems')
                        ->willReturn([]);
        $this->checkoutSession->expects(self::never())
                            ->method('setBoltCollectSaleRuleDiscounts')
                            ->with([]);
        $this->sessionHelper->expects(self::never())
                            ->method('getCheckoutSession')
                            ->willReturn($this->checkoutSession);
        $this->plugin->beforeCollect($this->subject, $quote, $shippingAssignment, null);
    }
}
