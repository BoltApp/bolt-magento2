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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Plugin;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Plugin\SalesRuleQuoteDiscountPlugin;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Checkout\Model\Session as CheckoutSession;

use Magento\SalesRule\Model\Quote\Discount;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\TestFramework\Helper\Bootstrap;
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

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    public function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->plugin = $this->objectManager->create(SalesRuleQuoteDiscountPlugin::class);
        $this->subject = $this->objectManager->create(Discount::class);
        $this->checkoutSession = $this->objectManager->create(CheckoutSession::class);
        $this->shippingAssignment = $this->objectManager->create(ShippingAssignmentInterface::class);
    }

    /**
     * @test
     * @covers ::beforeCollect
     */
    public function beforeCollect_resetSaleRuleDiscountsToCheckoutSession()
    {
        $quote = TestUtils::createQuote();
        $product = TestUtils::getSimpleProduct();
        $quote->addProduct($product, 1)->save();
        $this->checkoutSession->setBoltCollectSaleRuleDiscounts([1]);
        $this->shippingAssignment->setItems($quote->getAllItems());
        $result = $this->plugin->beforeCollect($this->subject, $quote, $this->shippingAssignment, null);
        self::assertEquals($this->checkoutSession->getBoltCollectSaleRuleDiscounts(), []);
        self::assertEquals([$quote, $this->shippingAssignment, null], $result);
    }

    /**
     * @test
     * @covers ::beforeCollect
     */
    public function beforeCollect_noItemInShippingAssignment_doNothing()
    {
        $this->shippingAssignment->setItems([]);
        $this->checkoutSession->setBoltCollectSaleRuleDiscounts([1]);
        $result = $this->plugin->beforeCollect($this->subject, null, $this->shippingAssignment, null);
        self::assertEquals($this->checkoutSession->getBoltCollectSaleRuleDiscounts(), [1]);
        self::assertEquals([null, $this->shippingAssignment, null], $result);
    }
}
