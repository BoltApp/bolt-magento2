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

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Plugin\SalesRuleQuoteDiscountPlugin;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\SalesRule\Model\Quote\Discount;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

/**
 * Class SalesRuleQuoteDiscountPluginTest
 * @package Bolt\Boltpay\Test\Unit\Plugin
 * @coversDefaultClass \Bolt\Boltpay\Plugin\SalesRuleQuoteDiscountPlugin
 */
class SalesRuleQuoteDiscountPluginTest extends TestCase
{
    /**
     * @var SalesRuleQuoteDiscountPlugin
     */
    protected $plugin;

    /** @var CheckoutSession */
    protected $checkoutSession;
    
    /** @var Discount */
    protected $subject;

    public function setUp()
    {
        $this->subject = $this->createMock(Discount::class);
        $this->checkoutSession = $this->createPartialMock(CheckoutSession::class,
            ['setBoltCollectSaleRuleDiscounts']
        );
        $this->plugin = (new ObjectManager($this))->getObject(
            SalesRuleQuoteDiscountPlugin::class,
            [
                'checkoutSession' => $this->checkoutSession
            ]
        );
    }

    /**
     * @test
     * @covers ::beforeCollect
     */
    public function beforeCollecte_resetSaleRuleDiscountsToCheckoutSession()
    {
        $this->checkoutSession->expects(self::once())
                            ->method('setBoltCollectSaleRuleDiscounts')
                            ->with([]);                    
        $this->plugin->beforeCollect($this->subject, null, null, null);
    }
}
