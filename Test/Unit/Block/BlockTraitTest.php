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

namespace Bolt\Boltpay\Test\Unit\Block;

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Block\BlockTrait;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;

/**
 * Class BlockTraitTest
 * @coversDefaultClass \Bolt\Boltpay\Block\BlockTrait
 */
class BlockTraitTest extends TestCase
{
    const CDN_URL = 'https://bolt-cdn.com';
    const STORE_ID = '1';
    const PAGE_BLACK_LIST = 'catalog_product_view';
    const PAGE_WHITE_LIST = 'catalog_product_view';

    /**
     * @var BlockTrait
     */
    private $currentMock;

    /**
     * @var Config
     */
    private $configHelper;

    /** @var Decider */
    private $featureSwitches;

    public function setUp()
    {
        $this->currentMock = $this->getMockBuilder(BlockTrait::class)
            ->enableOriginalConstructor()
            ->setMethods([
                'getCheckoutKey',
                'getStoreId'
            ])
            ->getMockForTrait();

        $this->configHelper = $this->createPartialMock(Config::class, [
            'shouldTrackCheckoutFunnel',
            'isPaymentOnlyCheckoutEnabled',
            'getPublishableKeyCheckout',
            'getCdnUrl',
            'getApiKey',
            'getSigningSecret',
            'isActive',
            'getPageBlacklist',
            'getPageWhitelist',
            'isIPRestricted'
        ]);
        $this->featureSwitches = $this->createPartialMock(Decider::class, ['isInstantCheckoutButton', 'isBoltEnabled']);
        TestHelper::setProperty($this->currentMock, 'configHelper', $this->configHelper);
        TestHelper::setProperty($this->currentMock, 'featureSwitches', $this->featureSwitches);

    }

    /**
     * @test
     * @dataProvider dataProvider_isInstantCheckoutButton
     * @param $isInstantCheckoutButton
     * @param $expected
     */
    public function isInstantCheckoutButton($isInstantCheckoutButton, $expected)
    {
        $this->featureSwitches->expects(self::once())->method('isInstantCheckoutButton')->willReturn($isInstantCheckoutButton);
        $this->assertEquals($expected, $this->currentMock->isInstantCheckoutButton());
    }

    public function dataProvider_isInstantCheckoutButton()
    {
        return [
            [true, true],
            [false, false]
        ];
    }

    /**
     * @test
     */
    public function getCheckoutCdnUrl()
    {
        $this->configHelper->expects(self::once())->method('getCdnUrl')->willReturn(self::CDN_URL);
        $this->assertEquals(self::CDN_URL, $this->currentMock->getCheckoutCdnUrl());
    }

    /**
     * @test
     * @dataProvider dataProvider_shouldTrackCheckoutFunnel
     *
     * @param $shouldTrackCheckoutFunnel
     * @param $expected
     */
    public function shouldTrackCheckoutFunnel($shouldTrackCheckoutFunnel, $expected)
    {
        $this->configHelper->expects(self::once())->method('shouldTrackCheckoutFunnel')->willReturn($shouldTrackCheckoutFunnel);
        $this->assertEquals($expected, $this->currentMock->shouldTrackCheckoutFunnel());
    }

    public function dataProvider_shouldTrackCheckoutFunnel()
    {
        return [
            [true, true],
            [false, false]
        ];
    }

    /**
     * @test
     * @dataProvider dataProvider_isKeyMissing
     *
     * @param $checkoutKey
     * @param $apiKey
     * @param $signingSecretKey
     * @param $expected
     */
    public function isKeyMissing($checkoutKey, $apiKey, $signingSecretKey, $expected)
    {
        $this->currentMock->expects(self::any())->method('getCheckoutKey')->willReturn($checkoutKey);
        $this->configHelper->expects(self::any())->method('getApiKey')->willReturn($apiKey);
        $this->configHelper->expects(self::any())->method('getSigningSecret')->willReturn($signingSecretKey);
        $this->assertEquals($expected, $this->currentMock->isKeyMissing());
    }

    public function dataProvider_isKeyMissing()
    {
        return [
            ['checkout_key', 'api_key', 'signing_secret_key', false],
            ['checkout_key', 'api_key', '', true],
            ['checkout_key', '', '', true],
            ['', '', '', true],
        ];
    }


    /**
     * @test
     * @dataProvider dataProvider_isEnabled
     * @param $apiKey
     * @param $expected
     */
    public function isEnabled($apiKey, $expected)
    {
        $this->currentMock->expects(self::any())->method('getStoreId')->willReturn(self::STORE_ID);
        $this->configHelper->expects(self::any())->method('isActive')->with(self::STORE_ID)->willReturn($apiKey);
        $this->assertEquals($expected, $this->currentMock->isEnabled());
    }

    public function dataProvider_isEnabled()
    {
        return [
            [true, true],
            [false, false]
        ];
    }

    /**
     * @test
     */
    public function getPageBlacklist()
    {
        $this->configHelper->expects(self::once())->method('getPageBlacklist')->willReturn(self::PAGE_BLACK_LIST);
        $this->assertEquals(self::PAGE_BLACK_LIST, TestHelper::invokeMethod($this->currentMock, 'getPageBlacklist'));
    }

    /**
     * @test
     */
    public function getPageWhitelist()
    {
        $this->configHelper->expects(self::once())->method('getPageWhitelist')->willReturn([self::PAGE_WHITE_LIST]);
        $this->assertEquals([
            Config::SHOPPING_CART_PAGE_ACTION,
            Config::CHECKOUT_PAGE_ACTION,
            Config::SUCCESS_PAGE_ACTION,
            self::PAGE_WHITE_LIST
        ], TestHelper::invokeMethod($this->currentMock, 'getPageWhitelist'));
    }

    /**
     * @test
     * @dataProvider dataProvider_isIPRestricted
     * @param $expected
     * @param $isIPRestricted
     * @throws \ReflectionException
     */
    public function isIPRestricted($expected, $isIPRestricted)
    {
        $this->configHelper->expects(self::once())->method('isIPRestricted')->willReturn($isIPRestricted);
        $this->assertEquals($expected, TestHelper::invokeMethod($this->currentMock, 'isIPRestricted'));
    }

    public function dataProvider_isIPRestricted()
    {
        return [
            [true, true],
            [false, false]
        ];
    }
}
