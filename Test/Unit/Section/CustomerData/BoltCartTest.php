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

namespace Bolt\Boltpay\Test\Unit\Section\CustomerData;

use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Section\CustomerData\BoltCart;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Magento\Framework\App\Response\RedirectInterface;
use Bolt\Boltpay\Helper\Config;
use Magento\Framework\UrlInterface;

/**
 * Class BoltCartTest
 * @package Bolt\Boltpay\Test\Unit\Section\CustomerData
 * @coversDefaultClass \Bolt\Boltpay\Section\CustomerData\BoltCart
 */
class BoltCartTest extends TestCase
{
    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var BoltCart
     */
    private $boltCart;

    /**
     * @var RedirectInterface
     */
    private $redirect;

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->cartHelper = $this->createPartialMock(CartHelper::class, ['calculateCartAndHints']);
        $this->configHelper = $this->createPartialMock(ConfigHelper::class, ['isPaymentOnlyCheckoutEnabled']);
        $this->redirect = $this->createPartialMock(RedirectInterface::class, [
            'getRefererUrl', 'getRedirectUrl', 'error',
            'success', 'updatePathParams', 'redirect'
        ]);
        $this->url = $this->createPartialMock(UrlInterface::class,
            [
                'getUrl', 'getUseSession', 'getBaseUrl',
                'getCurrentUrl', 'getRouteUrl', 'addSessionParam', 'addQueryParams',
                'setQueryParam', 'escape', 'getDirectUrl', 'sessionUrlVar',
                'isOwnOriginUrl', 'getRedirectUrl', 'setScope'
            ]
        );

        $this->boltCart = (new ObjectManager($this))->getObject(
            BoltCart::class,
            [
                'cartHelper' => $this->cartHelper,
                'redirect' => $this->redirect,
                'configHelper' => $this->configHelper,
                'url' => $this->url,
            ]
        );
    }


    /**
     * @test
     * @covers ::getSectionData
     * @covers ::isBoltUsedInCheckoutPage
     */
    public function getSectionData_withBoltIsUsedInCheckoutPage()
    {
        $this->url->expects(self::once())->method('getUrl')->willReturn('https://test/checkout');
        $this->redirect->expects(self::once())->method('getRefererUrl')->willReturn('https://test/checkout');
        $this->configHelper->expects(self::once())->method('isPaymentOnlyCheckoutEnabled')->willReturn(true);
        $this->cartHelper->expects(self::once())->method('calculateCartAndHints')->with(true);
        $this->boltCart->getSectionData();
    }

    /**
     * @test
     * @covers ::getSectionData
     * @covers ::isBoltUsedInCheckoutPage
     */
    public function getSectionData_withBoltIsNotUsedInCheckoutPage()
    {
        $this->url->expects(self::once())->method('getUrl')->willReturn('https://test/checkout');
        $this->redirect->expects(self::once())->method('getRefererUrl')->willReturn('https://test/checkout22');
        $this->cartHelper->expects(self::once())->method('calculateCartAndHints')->with(false);
        $this->boltCart->getSectionData();
    }
}
