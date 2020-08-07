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
use Bolt\Boltpay\Plugin\LoginPostPlugin;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\ResultFactory;
use Magento\Customer\Controller\Account\LoginPost;

/**
 * Class LoginPostPluginTest
 * @package Bolt\Boltpay\Test\Unit\Plugin\Magento\GiftCard
 * @coversDefaultClass \Bolt\Boltpay\Plugin\LoginPostPlugin
 */
class LoginPostPluginTest extends TestCase
{
    /**
     * @var LoginPostPlugin
     */
    protected $plugin;

    /** @var CustomerSession */
    protected $customerSession;

    /** @var CheckoutSession */
    protected $checkoutSession;

    /** @var ResultFactory */
    protected $resultFactory;

    /** @var LoginPost */
    protected $loginPost;

    /** @var Bugsnag */
    protected $bugsnag;

    public function setUp()
    {
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->resultFactory = $this->createPartialMock(
            ResultFactory::class,
            [
                'create',
                'setPath'
            ]
        );
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->loginPost = $this->createMock(LoginPost::class);
        $this->plugin = $this->getMockBuilder(LoginPostPlugin::class)
            ->setMethods([
                'shouldRedirectToCartPage',
                'setBoltInitiateCheckout',
                'notifyException'
            ])->setConstructorArgs([
                $this->customerSession,
                $this->checkoutSession,
                $this->resultFactory,
                $this->bugsnag
            ])
            ->getMock();
    }

    /**
     * @test
     * @covers ::afterExecute
     */
    public function afterExecute_ifShouldRedirectToCartPageIsFalse()
    {
        $this->plugin->expects(self::once())->method('shouldRedirectToCartPage')->willReturn(false);
        $this->plugin->expects(self::never())->method('setBoltInitiateCheckout');
        $this->plugin->afterExecute($this->loginPost, null);
    }

    /**
     * @test
     * @covers ::afterExecute
     */
    public function afterExecute_ifShouldRedirectToCartPageIsTrue()
    {
        $this->plugin->expects(self::once())->method('shouldRedirectToCartPage')->willReturn(true);
        $this->plugin->expects(self::once())->method('setBoltInitiateCheckout');
        $this->resultFactory->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_REDIRECT)
            ->willReturnSelf();
        $this->resultFactory->expects(self::once())
            ->method('setPath')
            ->with(\Bolt\Boltpay\Plugin\AbstractLoginPlugin::SHOPPING_CART_PATH)
            ->willReturnSelf();

        $this->plugin->afterExecute($this->loginPost, null);
    }

    /**
     * @test
     * @covers ::afterExecute
     */
    public function afterExecute_throwException()
    {
        $expected = new \Exception('General Exception');
        $this->plugin->expects(self::once())->method('shouldRedirectToCartPage')->willThrowException($expected);

        $this->plugin->expects(self::once())->method('notifyException')
            ->with($expected)->willReturnSelf();

        $this->plugin->afterExecute($this->loginPost, null);
    }
}
