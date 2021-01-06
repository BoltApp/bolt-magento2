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

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Plugin\SalesRuleQuoteDiscountPlugin;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\SalesRule\Model\Quote\Discount;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;

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
    
    /** @var SessionHelper */
    protected $sessionHelper;
    
    /** @var Discount */
    protected $subject;
    
    /** @var ShippingAssignmentInterface */
    protected $shippingAssignment;

    public function setUpInternal()
    {
        $this->subject = $this->createMock(Discount::class);
        $this->sessionHelper = $this->createPartialMock(SessionHelper::class,
            ['getCheckoutSession']
        );
        $this->checkoutSession = $this->createPartialMock(CheckoutSession::class,
            ['setBoltCollectSaleRuleDiscounts']
        );
        $this->plugin = (new ObjectManager($this))->getObject(
            SalesRuleQuoteDiscountPlugin::class,
            [
                'sessionHelper' => $this->sessionHelper
            ]
        );
        $this->shippingAssignment = $this->createMock(ShippingAssignmentInterface::class);
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
        $this->sessionHelper->expects(self::once())
                            ->method('getCheckoutSession')
                            ->willReturn($this->checkoutSession);
        $this->shippingAssignment->expects(self::once())
                            ->method('getItems')
                            ->willReturn(['item']);
        $this->plugin->beforeCollect($this->subject, null, $this->shippingAssignment, null);
    }
    
    /**
     * @test
     * @covers ::beforeCollect
     */
    public function beforeCollecte_noItemInShippingAssignment_doNothing()
    {
        $this->checkoutSession->expects(self::never())
                            ->method('setBoltCollectSaleRuleDiscounts')
                            ->with([]);
        $this->sessionHelper->expects(self::never())
                            ->method('getCheckoutSession')
                            ->willReturn($this->checkoutSession);
        $this->shippingAssignment->expects(self::once())
                            ->method('getItems')
                            ->willReturn([]);
        $this->plugin->beforeCollect($this->subject, null, $this->shippingAssignment, null);
    }
}
