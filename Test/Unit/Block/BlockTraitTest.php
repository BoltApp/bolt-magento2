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

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Block\BlockTrait;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Framework\App\ObjectManager;
use Magento\TestFramework\Helper\Bootstrap;
use Bolt\Boltpay\Helper\FeatureSwitch\Definitions;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class BlockTraitTest
 * @coversDefaultClass \Bolt\Boltpay\Block\BlockTrait
 */
class BlockTraitTest extends BoltTestCase
{
    const CDN_URL = 'https://connect-sandbox.bolt.com';
    const STORE_ID = '1';
    const PAGE_BLACK_LIST = 'catalog_product_view';
    const PAGE_WHITE_LIST = 'catalog_product_view';
    const ADDITIONAL_CONFIG = <<<JSON
{
    "amastyGiftCard": {
        "payForEverything": true
    },
    "adjustTaxMismatch": false,
    "toggleCheckout": {
        "active": true,
        "magentoButtons": [
            "#top-cart-btn-checkout",
            "button[data-role=proceed-to-checkout]"
        ],
        "showElementsOnLoad": [
            ".checkout-methods-items",
            ".block-minicart .block-content > .actions > .primary"
        ],
        "productRestrictionMethods": [
            "getSubscriptionActive"
        ],
        "itemRestrictionMethods": [
            "getIsSubscription"
        ]
    },
    "pageFilters": {
        "whitelist": ["checkout_cart_index", "checkout_index_index", "checkout_onepage_success"],
        "blacklist": ["cms_index_index"]
    },
    "ignoredShippingAddressCoupons": [
        "IGNORED_SHIPPING_ADDRESS_COUPON"
    ],
     "priceFaultTolerance": "10"
}
JSON;

    /** @var ObjectManager */
    private $objectManager;

    /**
     * @var BlockTrait
     */
    private $block;

    private $storeId;

    private $websiteId;

    public function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->block = $this->objectManager->create(\Bolt\Boltpay\Block\Js::class);
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $this->storeId = $store->getStore()->getId();

        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $this->websiteId = $websiteRepository->get('base')->getId();
    }

    /**
     * @test
     * @dataProvider dataProvider_isInstantCheckoutButton
     * @param $isInstantCheckoutButton
     * @param $expected
     */
    public function isInstantCheckoutButton($isInstantCheckoutButton, $expected)
    {
        $featureSwitch = TestUtils::saveFeatureSwitch(Definitions::M2_INSTANT_BOLT_CHECKOUT_BUTTON, $isInstantCheckoutButton);
        $this->assertEquals($expected, $this->block->isInstantCheckoutButton());
        TestUtils::cleanupSharedFixtures($featureSwitch);

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
        $this->assertEquals(self::CDN_URL, $this->block->getCheckoutCdnUrl());
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
        $configData = [
            [
                'path'    => Config::XML_PATH_TRACK_CHECKOUT_FUNNEL,
                'value'   => $shouldTrackCheckoutFunnel,
                'scope'   => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];

        TestUtils::setupBoltConfig($configData);
        $this->assertEquals($expected, $this->block->shouldTrackCheckoutFunnel());
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
        $configData = [
            [
                'path'    => Config::XML_PATH_PUBLISHABLE_KEY_CHECKOUT,
                'value'   => $checkoutKey,
                'scope'   => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path'    => Config::XML_PATH_API_KEY,
                'value'   => $apiKey,
                'scope'   => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
            ,[
                'path'    => Config::XML_PATH_SIGNING_SECRET,
                'value'   => $signingSecretKey,
                'scope'   => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];

        TestUtils::setupBoltConfig($configData);
        $this->assertEquals($expected, $this->block->isKeyMissing());
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
     */
    public function getPageBlacklist()
    {
        $this->getPageFilters();
        $this->assertEquals(["cms_index_index"], TestHelper::invokeMethod($this->block, 'getPageBlacklist'));
    }

    private function getPageFilters($aditionalConfig = null)
    {
        $aditionalConfig = $aditionalConfig ?: self::ADDITIONAL_CONFIG;
        $configData = [
            [
                'path' => Config::XML_PATH_ADDITIONAL_CONFIG,
                'value' => $aditionalConfig,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
    }

    /**
     * @test
     */
    public function getPageWhitelist()
    {
        $this->getPageFilters();
        $this->assertEquals(
            ["checkout_cart_index", "checkout_index_index", "checkout_onepage_success"],
            TestHelper::invokeMethod($this->block, 'getPageWhitelist')
        );
    }
}
