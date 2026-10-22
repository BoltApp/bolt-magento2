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
 *
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.boltapp.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Config as BoltConfig;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Composer\ComposerFactory;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class ConfigTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper
 * @coversDefaultClass \Bolt\Boltpay\Helper\Config
 */
class ConfigTest extends BoltTestCase
{
    const KEY_ENCRYPTED = 'KeyValue_Encripted';
    const KEY_DECRYPTED = 'KeyValue_Decrypted';
    const TEST_IP = [
        '111.111.111.111',
        '222.222.222.222',
        '123.123.123.123'
    ];
    const STORE_PICKUP_STREET = '4535 ANNALEE Way';
    const STORE_PICKUP_ZIP_CODE = '37921';
    const STORE_PICKUP_CITY = 'Knoxville';
    const STORE_PICKUP_COUNTRY_ID = 'US';
    const STORE_PICKUP_REGION_ID = '56';
    const STORE_PICKUP_APARTMENT = 'Room 4000';
    const STORE_PICKUP_SHIPPING_METHOD_CODE = 'instorepickup_instorepickup';

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
     "priceFaultTolerance": "10",
     "merchantDashboardURL": "https://test-sandbox.boltapp.com/",
     "apiURL": "https://test-sandbox.boltapp.com/",
     "accountURL": "https://test-sandbox.boltapp.com/",
     "cdnURL": "https://test-sandbox.boltapp.com/",
     "integrationBaseURL": "https://test-sandbox.boltapp.com/"
}
JSON;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;


    /**
     * @var Config
     */
    private $configHelper;

    private $objectManager;

    private $storeId;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->configHelper = $this->objectManager->create(Config::class);
        $store = $this->objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
        $this->storeId = $store->getStore()->getId();
    }
    
    protected function tearDownInternal()
    {
        // Reset additional configuration
        $configData = [
            [
                'path' => Config::XML_PATH_ADDITIONAL_CONFIG,
                'value' => '',
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
        ];
        TestUtils::setupBoltConfig($configData);
    }

    /**
     * @test
     * @covers ::getMerchantDashboardUrl
     */
    #[Test]
    public function getMerchantDashboardUrl_returnMerchantDashSandbox()
    {
        $configData = [
            [
                'path' => Config::XML_PATH_SANDBOX_MODE,
                'value' => true,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getMerchantDashboardUrl();
        self::assertEquals(BoltConfig::MERCHANT_DASH_SANDBOX, $result);
    }
    
    /**
     * @test
     * @covers ::getMerchantDashboardUrl
     */
    #[Test]
    public function getMerchantDashboardUrl_returnAdditionalConfigMerchantDashUrl()
    {
        $configData = [
            [
                'path' => Config::XML_PATH_SANDBOX_MODE,
                'value' => true,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => Config::XML_PATH_ADDITIONAL_CONFIG,
                'value' => self::ADDITIONAL_CONFIG,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getMerchantDashboardUrl();
        self::assertEquals("https://test-sandbox.boltapp.com/", $result);
    }

    /**
     * @test
     * @covers ::getMerchantDashboardUrl
     */
    #[Test]
    public function getMerchantDashboardUrl_returnMerchantDashProduction()
    {
        $configData = [
            [
                'path' => Config::XML_PATH_SANDBOX_MODE,
                'value' => false,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getMerchantDashboardUrl();
        self::assertEquals(BoltConfig::MERCHANT_DASH_PRODUCTION, $result);
    }

    /**
     * @test
     */
    #[Test]
    public function getStoreVersion()
    {
        $magentoVersion = '2.2.3';
        $this->productMetadata = $this->objectManager->create(\Magento\Framework\App\ProductMetadata::class);
        TestHelper::setInaccessibleProperty($this->productMetadata, 'version', '2.2.3');
        TestHelper::setInaccessibleProperty($this->configHelper, 'productMetadata', $this->productMetadata);
        $this->assertEquals($magentoVersion, $this->configHelper->getStoreVersion(), 'Cannot determine magento version');
    }

    /**
     * @test
     */
    #[Test]
    public function getTitle()
    {
        $expected = 'Bolt Pay';

        $configData = [
            [
                'path' => Config::XML_PATH_TITLE,
                'value' => $expected,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getTitle();
        self::assertEquals($expected, $result);
    }

    /**
     * @test
     * @covers ::getCdnUrl
     */
    #[Test]
    public function getCdnUrl_willReturnCdnUrlProduction()
    {
        $configData = [
            [
                'path' => Config::XML_PATH_SANDBOX_MODE,
                'value' => false,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getCdnUrl();
        self::assertEquals(Config::CDN_URL_PRODUCTION, $result);
    }

    /**
     * @test
     * @covers ::getCdnUrl
     */
    #[Test]
    public function getCdnUrl_willReturnCdnUrlSandbox()
    {
        $configData = [
            [
                'path' => Config::XML_PATH_SANDBOX_MODE,
                'value' => true,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getCdnUrl();
        self::assertEquals(Config::CDN_URL_SANDBOX, $result);
    }
    
    /**
     * @test
     * @covers ::getCdnUrl
     */
    #[Test]
    public function getCdnUrl_setupAdditionalConfig_willReturnCdnUrlSandbox()
    {
        $configData = [
            [
                'path' => Config::XML_PATH_SANDBOX_MODE,
                'value' => true,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => Config::XML_PATH_ADDITIONAL_CONFIG,
                'value' => self::ADDITIONAL_CONFIG,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getCdnUrl();
        self::assertEquals("https://test-sandbox.boltapp.com/", $result);
    }

    /**
     * @test
     * @covers ::getAccountUrl
     */
    #[Test]
    public function getAccountUrl_willReturnCdnUrlProduction()
    {
        $configData = [
            [
                'path' => Config::XML_PATH_SANDBOX_MODE,
                'value' => false,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getAccountUrl();
        self::assertEquals(Config::ACCOUNT_URL_PRODUCTION, $result);
    }

    /**
     * @test
     * @covers ::getAccountUrl
     */
    #[Test]
    public function getAccountUrl_willReturnAccountUrlSandbox()
    {
        $configData = [
            [
                'path' => Config::XML_PATH_SANDBOX_MODE,
                'value' => true,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getAccountUrl();
        self::assertEquals(Config::ACCOUNT_URL_SANDBOX, $result);
    }
    
    /**
     * @test
     * @covers ::getAccountUrl
     */
    #[Test]
    public function getAccountUrl_setupAdditionalConfig_willReturnAccountUrlSandbox()
    {
        $configData = [
            [
                'path' => Config::XML_PATH_SANDBOX_MODE,
                'value' => true,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => Config::XML_PATH_ADDITIONAL_CONFIG,
                'value' => self::ADDITIONAL_CONFIG,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getAccountUrl();
        self::assertEquals("https://test-sandbox.boltapp.com/", $result);
    }

    /**
     * @covers ::getApiUrl
     * @test
     */
    #[Test]
    public function getApiUrl_willReturnApiUrlProduction()
    {
        $configData = [
            [
                'path' => Config::XML_PATH_SANDBOX_MODE,
                'value' => false,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getApiUrl();
        self::assertEquals(Config::API_URL_PRODUCTION, $result);
    }

    /**
     * @test
     */
    #[Test]
    public function getApiUrl_willReturnApiUrlSandbox()
    {
        $configData = [
            [
                'path' => Config::XML_PATH_SANDBOX_MODE,
                'value' => true,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getApiUrl();
        self::assertEquals(Config::API_URL_SANDBOX, $result);
    }
    
    /**
     * @test
     */
    #[Test]
    public function getApiUrl_setupAdditionalConfig_willReturnApiUrlSandbox()
    {
        $configData = [
            [
                'path' => Config::XML_PATH_SANDBOX_MODE,
                'value' => true,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => Config::XML_PATH_ADDITIONAL_CONFIG,
                'value' => self::ADDITIONAL_CONFIG,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getApiUrl();
        self::assertEquals("https://test-sandbox.boltapp.com/", $result);
    }

    /**
     * @test
     * @dataProvider isSetFlagMethodsProvider
     *
     * @param mixed $methodName
     * @param mixed $path
     * @param mixed $result
     */
    #[Test]
    #[DataProvider('isSetFlagMethodsProvider')]
    public function isSetFlagMethods($methodName, $path, $result = true)
    {
        $configData = [
            [
                'path' => $path,
                'value' => $result,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        if ($result) {
            $this->assertTrue($this->configHelper->$methodName());
        } else {
            $this->assertFalse($this->configHelper->$methodName());
        }
    }

    public static function isSetFlagMethodsProvider()
    {
        return [
            ['isActive', BoltConfig::XML_PATH_ACTIVE],
            ['isSandboxModeSet', BoltConfig::XML_PATH_SANDBOX_MODE, false],
            ['getPrefetchShipping', BoltConfig::XML_PATH_PREFETCH_SHIPPING],
            ['getResetShippingCalculation', BoltConfig::XML_PATH_RESET_SHIPPING_CALCULATION, false],
            ['isDebugModeOn', BoltConfig::XML_PATH_DEBUG],
            ['shouldTrackCheckoutFunnel', BoltConfig::XML_PATH_TRACK_CHECKOUT_FUNNEL, false],
            ['getIsPreAuth', BoltConfig::XML_PATH_IS_PRE_AUTH],
            ['getMinicartSupport', BoltConfig::XML_PATH_MINICART_SUPPORT, false],
            ['getBoltOnCartPage', BoltConfig::XML_PATH_BOLT_ON_CART_PAGE, false],
            ['useStoreCreditConfig', BoltConfig::XML_PATH_STORE_CREDIT],
            ['useAmastyStoreCreditConfig', BoltConfig::XML_PATH_AMASTY_STORE_CREDIT],
            ['useRewardPointsConfig', BoltConfig::XML_PATH_REWARD_POINTS, false],
            ['displayRewardPointsInMinicartConfig', BoltConfig::XML_PATH_REWARD_POINTS_MINICART, false],
            ['isPaymentOnlyCheckoutEnabled', BoltConfig::XML_PATH_PAYMENT_ONLY_CHECKOUT],
            ['isBoltOrderCachingEnabled', BoltConfig::XML_PATH_BOLT_ORDER_CACHING, false],
            ['isSessionEmulationEnabled', BoltConfig::XML_PATH_API_EMULATE_SESSION],
            ['shouldMinifyJavascript', BoltConfig::XML_PATH_SHOULD_MINIFY_JAVASCRIPT, false],
            ['shouldCaptureMetrics', BoltConfig::XML_PATH_CAPTURE_MERCHANT_METRICS],
            ['isOrderManagementEnabled', BoltConfig::XML_PATH_PRODUCT_ORDER_MANAGEMENT],
            ['isAlwaysPresentCheckoutEnabled', BoltConfig::XML_PATH_ALWAYS_PRESENT_CHECKOUT, false],
            ['isBoltSSOEnabled', BoltConfig::XML_PATH_BOLT_SSO],
            ['isBoltDebugUniversalEnabled', BoltConfig::XML_PATH_DEBUG_UNIVERSAL],
            ['getUseAheadworksRewardPointsConfig', BoltConfig::XML_PATH_AHEADWORKS_REWARD_POINTS_ON_CART, false],
            ['getUseMageWorxRewardPointsConfig', BoltConfig::XML_PATH_MAGEWORX_REWARD_POINTS_ON_CART, false]
        ];
    }

    /**
     * @test
     * @dataProvider getValueMethodsProvider
     *
     * @param mixed $methodName
     * @param mixed $path
     * @param mixed $configValue
     */
    #[Test]
    #[DataProvider('getValueMethodsProvider')]
    public function getValueMethods($methodName, $path, $configValue = 'test config value')
    {
        $configData = [
            [
                'path' => $path,
                'value' => $configValue,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->$methodName();
        $this->assertEquals($configValue, $result, "$methodName() method: not working properly");
    }

    public static function getValueMethodsProvider()
    {
        return [
            ['getGlobalCSS', BoltConfig::XML_PATH_GLOBAL_CSS, '.replaceable-example-selector1 {color: black;}]'],
            ['getGlobalJS', BoltConfig::XML_PATH_GLOBAL_JS, 'require(["jquery"], function ($) {});'],
            ['getAdditionalCheckoutButtonClass', BoltConfig::XML_PATH_ADDITIONAL_CHECKOUT_BUTTON_CLASS, 'with-cards'],
            ['getPrefetchAddressFields', BoltConfig::XML_PATH_PREFETCH_ADDRESS_FIELDS, 'address_field1, address_field2'],
            ['getButtonColor', BoltConfig::XML_PATH_BUTTON_COLOR],
            ['getReplaceSelectors', BoltConfig::XML_PATH_REPLACE_SELECTORS],
            ['getTotalsChangeSelectors', BoltConfig::XML_PATH_TOTALS_CHANGE_SELECTORS, 'tr.grand.totals td.amount span.price'],
            ['getSuccessPageRedirect', BoltConfig::XML_PATH_SUCCESS_PAGE_REDIRECT, 'checkout/onepage/success'],
            ['getjavascriptSuccess', BoltConfig::XML_PATH_JAVASCRIPT_SUCCESS],
            ['getAdditionalJS', BoltConfig::XML_PATH_ADDITIONAL_JS],
            ['getOnCheckoutStart', BoltConfig::XML_PATH_TRACK_CHECKOUT_START],
            ['getOnEmailEnter', BoltConfig::XML_PATH_TRACK_EMAIL_ENTER],
            ['getOnShippingDetailsComplete', BoltConfig::XML_PATH_TRACK_SHIPPING_DETAILS_COMPLETE],
            ['getOnPaymentSubmit', BoltConfig::XML_PATH_TRACK_PAYMENT_SUBMIT],
            ['getOnSuccess', BoltConfig::XML_PATH_TRACK_SUCCESS],
            ['getOnClose', BoltConfig::XML_PATH_TRACK_CLOSE],
            ['getMinimumOrderAmount', BoltConfig::XML_PATH_MINIMUM_ORDER_AMOUNT],
            ['getOnShippingOptionsComplete', BoltConfig::XML_PATH_TRACK_SHIPPING_OPTIONS_COMPLETE],
            ['getOrderManagementSelector', BoltConfig::XML_PATH_PRODUCT_ORDER_MANAGEMENT_SELECTOR],
        ];
    }

    /**
     * @test
     */
    public function testGetProductPageCheckoutFlag()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_PRODUCT_PAGE_CHECKOUT,
                'value' => false,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $this->assertFalse(
            $this->configHelper->getProductPageCheckoutFlag(),
            'getProductPageCheckoutFlag() method: not working properly'
        );
    }

    /**
     * @test
     */
    public function testGetSelectProductPageCheckoutFlag()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_SELECT_PRODUCT_PAGE_CHECKOUT,
                'value' => false,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $this->assertFalse(
            $this->configHelper->getSelectProductPageCheckoutFlag(),
            'getSelectProductPageCheckoutFlag() method: not working properly'
        );
    }

    /**
     * @test
     */
    #[Test]
    public function getIgnoredShippingAddressCoupons()
    {
        $expected = ['ignored_shipping_address_coupon'];

        $configData = [
            [
                'path' => BoltConfig::XML_PATH_ADDITIONAL_CONFIG,
                'value' => self::ADDITIONAL_CONFIG,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $this->assertEquals(
            $expected,
            $this->configHelper->getIgnoredShippingAddressCoupons(null),
            'getIgnoredShippingAddressCoupons() method: not working properly'
        );
    }

    /**
     * @test
     */
    #[Test]
    public function getIPWhitelistConfig()
    {
        $expected = self::TEST_IP[0] . ',' . self::TEST_IP[1];

        $configData = [
            [
                'path' => BoltConfig::XML_PATH_IP_WHITELIST,
                'value' => $expected,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->invokeInaccessibleMethod($this->configHelper, 'getIPWhitelistConfig');
        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    #[Test]
    public function getIPWhitelistArray()
    {
        $getIPWhitelistConfig = ' , ' . self::TEST_IP[0] . ' , ' . self::TEST_IP[1] . ' , ';
        $expected = [self::TEST_IP[0], self::TEST_IP[1]];

        $configData = [
            [
                'path' => BoltConfig::XML_PATH_IP_WHITELIST,
                'value' => $getIPWhitelistConfig,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $result = array_values($this->configHelper->getIPWhitelistArray());
        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    #[Test]
    public function getAmastyGiftCardConfig()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_ADDITIONAL_CONFIG,
                'value' => self::ADDITIONAL_CONFIG,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $amastyGiftCardConfig = $this->configHelper->getAmastyGiftCardConfig();
        $this->assertTrue($amastyGiftCardConfig->payForEverything);
    }

    /**
     * @test
     */
    #[Test]
    public function shouldAdjustTaxMismatch()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_ADDITIONAL_CONFIG,
                'value' => self::ADDITIONAL_CONFIG,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $adjustTaxMismatch = $this->configHelper->shouldAdjustTaxMismatch();
        $this->assertFalse($adjustTaxMismatch);
    }

    /**
     * @test
     */
    #[Test]
    public function getPriceFaultTolerance()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_ADDITIONAL_CONFIG,
                'value' => self::ADDITIONAL_CONFIG,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $priceFaultTolerance = $this->configHelper->getPriceFaultTolerance();
        $this->assertEquals(10, $priceFaultTolerance);
    }

    /**
     * @test
     */
    #[Test]
    public function getToggleCheckout()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_ADDITIONAL_CONFIG,
                'value' => self::ADDITIONAL_CONFIG,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $toggleCheckout = $this->configHelper->getToggleCheckout();
        $this->assertTrue($toggleCheckout->active);
        $this->assertEquals(
            ["#top-cart-btn-checkout", "button[data-role=proceed-to-checkout]"],
            $toggleCheckout->magentoButtons
        );
    }

    private function getPageFilters($aditionalConfig = null)
    {
        $aditionalConfig = $aditionalConfig ?: self::ADDITIONAL_CONFIG;
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_ADDITIONAL_CONFIG,
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
    #[Test]
    public function getPageWhitelist()
    {
        $this->getPageFilters();
        $pageWhitelist = $this->configHelper->getPageWhitelist();
        $this->assertEquals(
            ["checkout_cart_index", "checkout_index_index", "checkout_onepage_success"],
            $pageWhitelist
        );
    }

    /**
     * @test
     */
    #[Test]
    public function getPageWhitelistEmpty()
    {
        $this->getPageFilters('{"pageFilters": {}}');
        $pageWhitelist = $this->configHelper->getPageWhitelist();
        $this->assertEquals(
            [],
            $pageWhitelist
        );
    }

    /**
     * @test
     */
    #[Test]
    public function getPageBlacklist()
    {
        $this->getPageFilters();
        $pageBlacklist = $this->configHelper->getPageBlacklist();
        $this->assertEquals(
            ["cms_index_index"],
            $pageBlacklist
        );
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object $object Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeInaccessibleMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * @test
     * @dataProvider providerTrueAndFalse
     *
     * @param mixed $boolean_value
     */
    #[Test]
    #[DataProvider('providerTrueAndFalse')]
    public function isGuestCheckoutAllowed($boolean_value)
    {
        $configData = [
            [
                'path' => 'checkout/options/guest_checkout',
                'value' => $boolean_value,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->isGuestCheckoutAllowed();
        $this->assertEquals($boolean_value, $result);
    }

    public static function providerTrueAndFalse()
    {
        return [
            [true],
            [false],
        ];
    }

    /**
     * @test
     * @dataProvider providerTrueAndFalse
     *
     * @param $booleanValue
     */
    #[Test]
    #[DataProvider('providerTrueAndFalse')]
    public function isGuestCheckoutForDownloadableProductDisabled($booleanValue)
    {
        $configData = [
            [
                'path' => 'catalog/downloadable/disable_guest_checkout',
                'value' => $booleanValue,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $result = $this->configHelper->isGuestCheckoutForDownloadableProductDisabled();
        $this->assertEquals($booleanValue, $result);
    }

    /**
     * @test
     * @covers ::getPickupStreetConfiguration
     */
    #[Test]
    public function getPickupStreetConfiguration()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_PICKUP_STREET,
                'value' => self::STORE_PICKUP_STREET,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $result = $this->configHelper->getPickupStreetConfiguration();
        $this->assertEquals(self::STORE_PICKUP_STREET, $result);
    }

    /**
     * @test
     * @covers ::getPickupCityConfiguration
     */
    #[Test]
    public function getPickupCityConfiguration()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_PICKUP_CITY,
                'value' => self::STORE_PICKUP_CITY,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getPickupCityConfiguration();
        $this->assertEquals(self::STORE_PICKUP_CITY, $result);
    }

    /**
     * @test
     * @covers ::getPickupZipCodeConfiguration
     */
    #[Test]
    public function getPickupZipCodeConfiguration()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_PICKUP_ZIP_CODE,
                'value' => self::STORE_PICKUP_ZIP_CODE,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];

        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getPickupZipCodeConfiguration();
        $this->assertEquals(self::STORE_PICKUP_ZIP_CODE, $result);
    }

    /**
     * @test
     * @covers ::getPickupCountryIdConfiguration
     */
    #[Test]
    public function getPickupCountryIdConfiguration()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_PICKUP_COUNTRY_ID,
                'value' => self::STORE_PICKUP_COUNTRY_ID,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];

        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getPickupCountryIdConfiguration();
        $this->assertEquals(self::STORE_PICKUP_COUNTRY_ID, $result);
    }

    /**
     * @test
     * @covers ::getPickupRegionIdConfiguration
     */
    #[Test]
    public function getPickupRegionIdConfiguration()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_PICKUP_REGION_ID,
                'value' => self::STORE_PICKUP_REGION_ID,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getPickupRegionIdConfiguration();
        $this->assertEquals(self::STORE_PICKUP_REGION_ID, $result);
    }

    /**
     * @test
     * @covers ::getPickupShippingMethodCodeConfiguration
     */
    #[Test]
    public function getPickupShippingMethodCodeConfiguration()
    {

        $configData = [
            [
                'path' => BoltConfig::XML_PATH_PICKUP_SHIPPING_METHOD_CODE,
                'value' => self::STORE_PICKUP_SHIPPING_METHOD_CODE,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $result = $this->configHelper->getPickupShippingMethodCodeConfiguration();
        $this->assertEquals(self::STORE_PICKUP_SHIPPING_METHOD_CODE, $result);
    }

    /**
     * @test
     * @covers ::getPickupApartmentConfiguration
     */
    #[Test]
    public function getPickupApartmentConfiguration()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_PICKUP_APARTMENT,
                'value' => self::STORE_PICKUP_APARTMENT,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $result = $this->configHelper->getPickupApartmentConfiguration();
        $this->assertEquals(self::STORE_PICKUP_APARTMENT, $result);
    }

    /**
     * @param $method
     * @param $expected
     * @param $isStorePickFeatureEnabled
     * @test
     * @covers ::isPickupInStoreShippingMethodCode
     * @dataProvider providerGetPickupShippingMethodCode
     */
    #[Test]
    #[DataProvider('providerGetPickupShippingMethodCode')]
    public function isPickupInStoreShippingMethodCode($isStorePickFeatureEnabled, $method, $expected)
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_ENABLE_STORE_PICKUP_FEATURE,
                'value' => $isStorePickFeatureEnabled,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => BoltConfig::XML_PATH_PICKUP_SHIPPING_METHOD_CODE,
                'value' => self::STORE_PICKUP_SHIPPING_METHOD_CODE,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $result = $this->configHelper->isPickupInStoreShippingMethodCode($method);
        $this->assertEquals($expected, $result);
    }

    public static function providerGetPickupShippingMethodCode()
    {
        return [
            [false, 'instorepickup_instorepickup', false],
            [true, 'instorepickup_instorepickup', true],
            [true, 'is_not_instorepickup_instorepickup', false],
            [true, null, false]
        ];
    }

    /**
     * @test
     * @covers ::isStorePickupFeatureEnabled
     */
    #[Test]
    public function isStorePickupFeatureEnabled()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_ENABLE_STORE_PICKUP_FEATURE,
                'value' => true,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->isStorePickupFeatureEnabled();
        $this->assertEquals(1, $result);
    }

    /**
     * @test
     */
    #[Test]
    public function getPickupAddressData_returnsNull_ifFieldIsEmpty()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_PICKUP_STREET,
                'value' => null,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => BoltConfig::XML_PATH_PICKUP_CITY,
                'value' => null,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => BoltConfig::XML_PATH_PICKUP_ZIP_CODE,
                'value' => null,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => BoltConfig::XML_PATH_PICKUP_COUNTRY_ID,
                'value' => null,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => BoltConfig::XML_PATH_PICKUP_REGION_ID,
                'value' => null,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => BoltConfig::XML_PATH_PICKUP_APARTMENT,
                'value' => null,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $result = $this->configHelper->getPickupAddressData();

        $this->assertEquals(null, $result);
    }


    /**
     * @test
     * @covers ::getPickupAddressData
     */
    #[Test]
    public function getPickupAddressData()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_PICKUP_STREET,
                'value' => self::STORE_PICKUP_STREET,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => BoltConfig::XML_PATH_PICKUP_CITY,
                'value' => self::STORE_PICKUP_CITY,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => BoltConfig::XML_PATH_PICKUP_ZIP_CODE,
                'value' => self::STORE_PICKUP_ZIP_CODE,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => BoltConfig::XML_PATH_PICKUP_COUNTRY_ID,
                'value' => self::STORE_PICKUP_COUNTRY_ID,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => BoltConfig::XML_PATH_PICKUP_REGION_ID,
                'value' => self::STORE_PICKUP_REGION_ID,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => BoltConfig::XML_PATH_PICKUP_APARTMENT,
                'value' => self::STORE_PICKUP_APARTMENT,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $result = $this->configHelper->getPickupAddressData();
        $this->assertEquals(
            [
                'city' => 'Knoxville',
                'country_id' => 'US',
                'postcode' => '37921',
                'region_code' => 'TN',
                'region_id' => '56',
                'street' => '4535 ANNALEE Way
Room 4000',
            ],
            $result
        );
    }

    /**
     * @test
     * @covers ::validateCustomUrl
     * @dataProvider providerValidateCustomUrl
     *
     * @param $url
     * @param $expected
     *
     * @throws \ReflectionException
     */
    #[Test]
    #[DataProvider('providerValidateCustomUrl')]
    public function validateCustomUrl($url, $expected)
    {
        $result = TestHelper::invokeMethod($this->configHelper, 'validateCustomUrl', [$url]);
        $this->assertEquals($expected, $result);
    }

    public static function providerValidateCustomUrl()
    {
        return [
            ['https://test.bolt.me', true],
            ['https://test.bolt.me/', true],
            ['https://api.test.bolt.me/', true],
            ['https://test.boltapp.com', true],
            ['https://connect-staging.boltapp.com', true],
            ['https://test .boltapp.com', false],
            ['https://testbolt.me', false],
            ['https://test.com', false],
            ['test.bolt.me', false],
            ['gopher://127.0.0.1:6379/_FLUSHALL%0D%0Abolt.me', false],
        ];
    }

    /**
     * @param $commaSeparatedList
     * @param $expected
     * @test
     * @covers ::getProductAttributesList
     * @dataProvider providerGetProductAttributesList
     */
    #[Test]
    #[DataProvider('providerGetProductAttributesList')]
    public function getProductAttributesList($commaSeparatedList, $expected)
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_PRODUCT_ATTRIBUTES_LIST,
                'value' => $commaSeparatedList,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getProductAttributesList();
        $this->assertEquals($expected, $result);
    }

    public static function providerGetProductAttributesList()
    {
        return [
            ['', []],
            ['value', ['value']],
            ['value1,value2', ['value1', 'value2']],
        ];
    }

    /**
     * @test
     * @covers ::getComposerVersion
     */
    #[Test]
    public function getComposerVersion()
    {
        $composerFactory = $this->createPartialMock(ComposerFactory::class, ['create', 'getLocker', 'getLockedRepository', 'findPackage', 'getVersion']);
        $composerFactory->expects(self::once())->method('create')->willReturnSelf();
        $composerFactory->expects(self::once())->method('getLocker')->willReturnSelf();
        $composerFactory->expects(self::once())->method('getLockedRepository')->willReturnSelf();
        $composerFactory->expects(self::once())->method('findPackage')->with(Config::BOLT_COMPOSER_NAME, '*')->willReturnSelf();
        $composerFactory->expects(self::once())->method('getVersion')->willReturn('dev-test-feature');
        TestHelper::setInaccessibleProperty($this->configHelper, 'composerFactory', $composerFactory);
        $this->assertEquals('dev-test-feature', $this->configHelper->getComposerVersion());
    }

    /**
     * @test
     * @covers ::getComposerVersion
     */
    #[Test]
    public function getComposerVersion_withException_returnNull()
    {
        $composerFactory = $this->createPartialMock(ComposerFactory::class, ['create', 'getLocker', 'getLockedRepository', 'findPackage', 'getVersion']);
        $composerFactory->expects(self::once())->method('create')->willReturnSelf();
        $composerFactory->expects(self::once())->method('getLocker')->willReturnSelf();
        $composerFactory->expects(self::once())->method('getLockedRepository')->willReturnSelf();
        $composerFactory->expects(self::once())->method('findPackage')->with(Config::BOLT_COMPOSER_NAME, '*')->willReturnSelf();
        $e = new \Exception(__('Test'));
        $composerFactory->expects(self::once())->method('getVersion')->willThrowException($e);
        TestHelper::setInaccessibleProperty($this->configHelper, 'composerFactory', $composerFactory);
        $this->assertEquals('', $this->configHelper->getComposerVersion());
    }

    /**
     * @test
     * that getAdditionalCheckoutButtonAttributes returns additional checkout button attributes
     * stored in additional config field under 'checkoutButtonAttributes' field
     *
     * @covers ::getAdditionalCheckoutButtonAttributes
     *
     * @dataProvider getAdditionalCheckoutButtonAttributes_withVariousAdditionalConfigsProvider
     *
     * @param string $additionalConfig string from config property
     * @param mixed $expectedResult from the tested method
     */
    #[Test]
    #[DataProvider('getAdditionalCheckoutButtonAttributes_withVariousAdditionalConfigsProvider')]
    public function getAdditionalCheckoutButtonAttributes_withVariousAdditionalConfigs_returnsButtonAttributes(
        $additionalConfig,
        $expectedResult
    )
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_ADDITIONAL_CONFIG,
                'value' => $additionalConfig,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getAdditionalCheckoutButtonAttributes($this->storeId);
        static::assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for
     *
     * @see getAdditionalCheckoutButtonAttributes_withVariousAdditionalConfigs_returnsButtonAttributes
     *
     * @return array[] containing additional config values and expected result of the tested method
     */
    public static function getAdditionalCheckoutButtonAttributes_withVariousAdditionalConfigsProvider()
    {
        return [
            'Only attributes in initial config' => [
                'additionalConfig' => '{
                    "checkoutButtonAttributes": {
                        "data-btn-txt": "Pay now"
                    }
                }',
                'expectedResult' => (object)['data-btn-txt' => 'Pay now'],
            ],
            'Multiple attributes' => [
                'additionalConfig' => '{
                    "checkoutButtonAttributes": {
                        "data-btn-txt": "Pay now",
                        "data-btn-text": "Data"
                    }
                }',
                'expectedResult' => (object)['data-btn-txt' => 'Pay now', 'data-btn-text' => 'Data'],
            ],
            'Empty checkout button attributes property' => [
                'additionalConfig' => '{
                    "checkoutButtonAttributes": {}
                }',
                'expectedResult' => (object)[],
            ],
            'Missing checkout button attributes property' => [
                'additionalConfig' => '{
                    "checkoutButtonAttributes": {}
                }',
                'expectedResult' => (object)[],
            ],
            'Invalid additional config JSON' => [
                'additionalConfig' => 'invalid JSON',
                'expectedResult' => (object)[],
            ],
        ];
    }

    /**
     * @test
     * @covers ::getComposerVersion
     */
    #[Test]
    public function getComposerVersion_withFindPackageWillReturnNull()
    {
        $composerFactory = $this->createPartialMock(ComposerFactory::class, ['create', 'getLocker', 'getLockedRepository', 'findPackage', 'getVersion']);
        $composerFactory->expects(self::once())->method('create')->willReturnSelf();
        $composerFactory->expects(self::once())->method('getLocker')->willReturnSelf();
        $composerFactory->expects(self::once())->method('getLockedRepository')->willReturnSelf();
        $composerFactory->expects(self::once())->method('findPackage')->with(Config::BOLT_COMPOSER_NAME, '*')->willReturn(null);
        $composerFactory->expects(self::never())->method('getVersion');
        TestHelper::setInaccessibleProperty($this->configHelper, 'composerFactory', $composerFactory);
        $this->assertEquals('', $this->configHelper->getComposerVersion());
    }

    /**
     * @test
     * that getOrderCommentField returns order comment field from the configuration if set
     *
     * @covers ::getOrderCommentField
     */
    #[Test]
    public function getOrderCommentField_ifSetInConfiguration_returnsCommentField()
    {
        $customCommentField = 'custom_comment_field';
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_ORDER_COMMENT_FIELD,
                'value' => $customCommentField,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $this->assertEquals($customCommentField, $this->configHelper->getOrderCommentField());
    }

    /**
     * @test
     * that getOrderCommentField returns 'customer_note' field if a field is not set in the configuration
     *
     * @covers ::getOrderCommentField
     */
    #[Test]
    public function getOrderCommentField_ifNotSetInConfiguration_returnsCustomerNote()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_ORDER_COMMENT_FIELD,
                'value' => null,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $this->assertEquals('customer_note', $this->configHelper->getOrderCommentField());
    }
    
    /**
     * @test
     * @covers ::getIntegrationBaseUrl
     */
    #[Test]
    public function getIntegrationBaseUrl_returnIntegrationBaseUrlSandbox()
    {
        $configData = [
            [
                'path' => Config::XML_PATH_SANDBOX_MODE,
                'value' => true,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getIntegrationBaseUrl();
        self::assertEquals(BoltConfig::API_URL_SANDBOX, $result);
    }
    
    /**
     * @test
     * @covers ::getIntegrationBaseUrl
     */
    #[Test]
    public function getIntegrationBaseUrl_returnAdditionalConfigIntegrationBaseUrl()
    {
        $configData = [
            [
                'path' => Config::XML_PATH_SANDBOX_MODE,
                'value' => true,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => Config::XML_PATH_ADDITIONAL_CONFIG,
                'value' => self::ADDITIONAL_CONFIG,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getIntegrationBaseUrl();
        self::assertEquals("https://test-sandbox.boltapp.com/", $result);
    }

    /**
     * @test
     * @covers ::getIntegrationBaseUrl
     */
    #[Test]
    public function getIntegrationBaseUrl_returnIntegrationBaseUrlProduction()
    {
        $configData = [
            [
                'path' => Config::XML_PATH_SANDBOX_MODE,
                'value' => false,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->configHelper->getIntegrationBaseUrl();
        self::assertEquals(BoltConfig::API_URL_PRODUCTION, $result);
    }
}
