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
use Bolt\Boltpay\Plugin\SalesRuleActionDiscountPlugin;
use Magento\Framework\DataObject;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\SalesRule\Model\Rule\Action\Discount\AbstractDiscount;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Bolt\Boltpay\Helper\Session as SessionHelper;

/**
 * Class SalesRuleActionDiscountPluginTest
 * @package Bolt\Boltpay\Test\Unit\Plugin
 * @coversDefaultClass \Bolt\Boltpay\Plugin\SalesRuleActionDiscountPlugin
 */
class SalesRuleActionDiscountPluginTest extends BoltTestCase
{
    /**
     * @var SalesRuleActionDiscountPlugin
     */
    protected $plugin;
    
    /** @var CheckoutSession */
    protected $checkoutSession;

    /** @var SessionHelper */
    protected $sessionHelper;
    
    /** @var AbstractDiscount */
    protected $subject;

    public function setUpInternal()
    {
        $this->subject = $this->createMock(AbstractDiscount::class);
        $this->sessionHelper = $this->createPartialMock(
            SessionHelper::class,
            ['getCheckoutSession']
        );
        $this->checkoutSession = $this->createPartialMock(
            CheckoutSession::class,
            ['getBoltCollectSaleRuleDiscounts', 'setBoltCollectSaleRuleDiscounts']
        );
        $this->plugin = (new ObjectManager($this))->getObject(
            SalesRuleActionDiscountPlugin::class,
            [
                'sessionHelper' => $this->sessionHelper
            ]
        );
    }

    /**
     * @test
     * @covers ::afterCalculate
     */
    public function afterCalculate_saveSaleRuleDiscountsToCheckoutSession_sessionExists()
    {
        $rule = $this->getMockBuilder(DataObject::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()
            ->getMock();
        $rule->expects(self::once())
            ->method('getId')
            ->willReturn(2);
        $result = $this->getMockBuilder(DataObject::class)
            ->setMethods(['getAmount'])
            ->disableOriginalConstructor()
            ->getMock();
        $result->expects(self::once())
            ->method('getAmount')
            ->willReturn(20.0);
        $boltCollectSaleRuleDiscounts = [2 => 106.0,];
        $this->checkoutSession->expects(self::once())
                            ->method('getBoltCollectSaleRuleDiscounts')
                            ->willReturn($boltCollectSaleRuleDiscounts);
        $this->checkoutSession->expects(self::once())
                            ->method('setBoltCollectSaleRuleDiscounts')
                            ->with([2 => 126.0,]);
        $this->sessionHelper->expects(self::once())
                            ->method('getCheckoutSession')
                            ->willReturn($this->checkoutSession);
        $this->plugin->afterCalculate($this->subject, $result, $rule, null, null);
    }

    /**
     * @test
     * @covers ::afterCalculate
     */
    public function afterCalculate_saveSaleRuleDiscountsToCheckoutSession_sessionNotExists()
    {
        $rule = $this->getMockBuilder(DataObject::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()
            ->getMock();
        $rule->expects(self::once())
            ->method('getId')
            ->willReturn(2);
        $result = $this->getMockBuilder(DataObject::class)
            ->setMethods(['getAmount'])
            ->disableOriginalConstructor()
            ->getMock();
        $result->expects(self::once())
            ->method('getAmount')
            ->willReturn(20.0);
        $boltCollectSaleRuleDiscounts = [];
        $this->checkoutSession->expects(self::once())
                            ->method('getBoltCollectSaleRuleDiscounts')
                            ->willReturn($boltCollectSaleRuleDiscounts);
        $this->checkoutSession->expects(self::once())
                            ->method('setBoltCollectSaleRuleDiscounts')
                            ->with([2 => 20.0,]);
        $this->sessionHelper->expects(self::once())
                            ->method('getCheckoutSession')
                            ->willReturn($this->checkoutSession);
        $this->plugin->afterCalculate($this->subject, $result, $rule, null, null);
    }
}
