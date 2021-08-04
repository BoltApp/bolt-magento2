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
use Bolt\Boltpay\Plugin\SalesRuleModelUtilityPlugin;
use Magento\Framework\DataObject;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\SalesRule\Model\Utility;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Magento\SalesRule\Model\Rule\Action\Discount\Data as DiscountData;
use Magento\Quote\Model\Quote\Item;

/**
 * Class SalesRuleModelUtilityPluginTest
 * @package Bolt\Boltpay\Test\Unit\Plugin
 * @coversDefaultClass \Bolt\Boltpay\Plugin\SalesRuleModelUtilityPlugin
 */
class SalesRuleModelUtilityPluginTest extends BoltTestCase
{
    /**
     * @var SalesRuleModelUtilityPlugin
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
        $this->subject = $this->createMock(Utility::class);
        $this->sessionHelper = $this->createPartialMock(
            SessionHelper::class,
            ['getCheckoutSession']
        );
        $this->checkoutSession = $this->createPartialMock(
            CheckoutSession::class,
            ['getBoltNeedCollectSaleRuleDiscounts',
             'setBoltNeedCollectSaleRuleDiscounts',
             'getBoltCollectSaleRuleDiscounts',
             'setBoltCollectSaleRuleDiscounts',
             'setBoltDiscountBreakdown',
             'getBoltDiscountBreakdown']
        );
        $this->plugin = (new ObjectManager($this))->getObject(
            SalesRuleModelUtilityPlugin::class,
            [
                'sessionHelper' => $this->sessionHelper
            ]
        );
    }
    
    /**
     * @test
     * @covers ::beforeMinFix
     */
    public function beforeMinFix_validSaleRule_saveDiscountBreakdownToCheckoutSession()
    {
        $this->checkoutSession->expects(self::once())
                            ->method('getBoltNeedCollectSaleRuleDiscounts')
                            ->willReturn(2);
        $item = $this->getMockBuilder(Item::class)
            ->setMethods(['getDiscountAmount'])
            ->disableOriginalConstructor()
            ->getMock();
        $item->expects(self::once())
            ->method('getDiscountAmount')
            ->willReturn(20.0);
        $this->plugin->beforeMinFix($this->subject, null, null, $item, null);
    }

    /**
     * @test
     * @covers ::afterMinFix
     */
    public function afterMinFix_saveSaleRuleDiscountsToCheckoutSession_sessionExists()
    {
        $discountData = $this->getMockBuilder(DiscountData::class)
            ->setMethods(['getAmount'])
            ->disableOriginalConstructor()
            ->getMock();
        $discountData->expects(self::once())
            ->method('getAmount')
            ->willReturn(20.0);
        $this->checkoutSession->expects(self::once())
                            ->method('getBoltNeedCollectSaleRuleDiscounts')
                            ->willReturn(2);
        $boltCollectSaleRuleDiscounts = [2 => 106.0,];
        $this->checkoutSession->expects(self::once())
                            ->method('getBoltCollectSaleRuleDiscounts')
                            ->willReturn($boltCollectSaleRuleDiscounts);
        $boltDiscountBreakdown = ['item_discount' => 0.0,'rule_id' => 2,];
        $this->checkoutSession->expects(self::once())
                            ->method('getBoltDiscountBreakdown')
                            ->willReturn($boltDiscountBreakdown);
        $this->checkoutSession->expects(self::once())
                            ->method('setBoltCollectSaleRuleDiscounts')
                            ->with([2 => 126.0,]);
        $this->checkoutSession->expects(self::once())
                            ->method('setBoltNeedCollectSaleRuleDiscounts')
                            ->with('');
        $this->sessionHelper->expects(self::once())
                            ->method('getCheckoutSession')
                            ->willReturn($this->checkoutSession);
        $this->plugin->afterMinFix($this->subject, null, $discountData, null, null);
    }
    
    /**
     * @test
     * @covers ::afterMinFix
     */
    public function afterMinFix_doNotSaveUselessSaleRuleDiscountsToCheckoutSession_sessionExists()
    {
        $discountData = $this->getMockBuilder(DiscountData::class)
            ->setMethods(['getAmount'])
            ->disableOriginalConstructor()
            ->getMock();
        $discountData->expects(self::once())
            ->method('getAmount')
            ->willReturn(20.0);
        $this->checkoutSession->expects(self::once())
                            ->method('getBoltNeedCollectSaleRuleDiscounts')
                            ->willReturn(2);
        $boltCollectSaleRuleDiscounts = [2 => 106.0,];
        $this->checkoutSession->expects(self::once())
                            ->method('getBoltCollectSaleRuleDiscounts')
                            ->willReturn($boltCollectSaleRuleDiscounts);
        $boltDiscountBreakdown = ['item_discount' => 20.0,'rule_id' => 2,];
        $this->checkoutSession->expects(self::once())
                            ->method('getBoltDiscountBreakdown')
                            ->willReturn($boltDiscountBreakdown);
        $this->checkoutSession->expects(self::once())
                            ->method('setBoltCollectSaleRuleDiscounts')
                            ->with([2 => 106.0,]);
        $this->checkoutSession->expects(self::once())
                            ->method('setBoltNeedCollectSaleRuleDiscounts')
                            ->with('');
        $this->sessionHelper->expects(self::once())
                            ->method('getCheckoutSession')
                            ->willReturn($this->checkoutSession);
        $this->plugin->afterMinFix($this->subject, null, $discountData, null, null);
    }

    /**
     * @test
     * @covers ::afterMinFix
     */
    public function afterMinFix_saveSaleRuleDiscountsToCheckoutSession_sessionNotExists()
    {
        $this->checkoutSession->expects(self::once())
                            ->method('getBoltNeedCollectSaleRuleDiscounts')
                            ->willReturn('');
        $this->checkoutSession->expects(self::never())
                            ->method('getBoltCollectSaleRuleDiscounts');
        $this->checkoutSession->expects(self::never())
                            ->method('getBoltDiscountBreakdown');
        $this->checkoutSession->expects(self::never())
                            ->method('setBoltCollectSaleRuleDiscounts');
        $this->checkoutSession->expects(self::never())
                            ->method('setBoltNeedCollectSaleRuleDiscounts');
        $this->sessionHelper->expects(self::once())
                            ->method('getCheckoutSession')
                            ->willReturn($this->checkoutSession);
        $this->plugin->afterMinFix($this->subject, null, null, null, null);
    }
}
