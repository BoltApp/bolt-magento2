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

namespace Bolt\Boltpay\Test\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Plugin\MirasvitCreditQuotePaymentImportDataBeforePlugin;
use Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\MirasvitCreditQuotePaymentImportDataBeforePlugin
 */
class MirasvitCreditQuotePaymentImportDataBeforePluginTest extends TestCase
{
    /**
     * @var MirasvitCreditQuotePaymentImportDataBeforePlugin
     */
    protected $plugin;

    /**
     * @var DiscountHelper
     */
    protected $discountHelper;

    /**
     * @var Observer
     */
    protected $observer;
    /**
     * @var ObserverInterface
     */
    protected $observerInterface;

    public function setUp()
    {
        $this->discountHelper = $this->createPartialMock(DiscountHelper::class, ['isMirasvitAdminQuoteUsingCreditObserver']);

        $this->observer = $this->createPartialMock(Observer::class, ['getEvent', 'getInput', 'setUseCredit']);
        $this->observerInterface = $this->createMock(ObserverInterface::class);

        $this->plugin = (new ObjectManager($this))->getObject(
            MirasvitCreditQuotePaymentImportDataBeforePlugin::class,
            [
                'discountHelper' => $this->discountHelper
            ]
        );
    }

    /**
     * @test
     * @covers ::beforeExecute
     */
    public function beforeExecute_withIsMirasvitAdminQuoteUsingCreditObserverIsTrue()
    {
        $this->discountHelper->expects(self::once())->method('isMirasvitAdminQuoteUsingCreditObserver')->with($this->observer)->willReturn(true);
        $this->observer->expects(self::once())->method('getEvent')->willReturnSelf();
        $this->observer->expects(self::once())->method('getInput')->willReturnSelf();
        $this->observer->expects(self::once())->method('setUseCredit')->with(true)->willReturnSelf();
        $this->plugin->beforeExecute($this->observerInterface, $this->observer);
    }

    /**
     * @test
     * @covers ::beforeExecute
     */
    public function beforeExecute_withIsMirasvitAdminQuoteUsingCreditObserverIsFalse()
    {
        $this->discountHelper->expects(self::once())->method('isMirasvitAdminQuoteUsingCreditObserver')->with($this->observer)->willReturn(false);
        $this->observer->expects(self::never())->method('getEvent')->willReturnSelf();
        $this->plugin->beforeExecute($this->observerInterface, $this->observer);
    }
}
