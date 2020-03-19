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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\Config as BoltConfig;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleResource;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Request\Http as Request;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass \Bolt\Boltpay\Helper\Config
 */
class ConfigTest extends TestCase
{
    const KEY_ENCRYPTED = 'KeyValue_Encripted';
    const KEY_DECRYPTED = 'KeyValue_Decrypted';
    const TEST_IP = [
        '111.111.111.111',
        '222.222.222.222',
        '123.123.123.123'
    ];
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
    ]
}
JSON;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var ModuleResource
     */
    private $moduleResource;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ProductMetadataInterface|MockObject
     */
    private $productMetadata;

    /**
     * @var BoltConfig|MockObject
     */
    private $currentMock;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Request
     */
    private $request;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->moduleResource = $this->createMock(ModuleResource::class);

        $this->scopeConfig = $this->getMockBuilder(ScopeConfigInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->setMethods(['getScopeConfig'])
            ->getMock();
        $this->context->method('getScopeConfig')->willReturn($this->scopeConfig);

        $this->productMetadata = $this->createMock(ProductMetadataInterface::class);
        $this->request = $this->createMock(Request::class);

        $methods = ['getScopeConfig'];
        $this->initCurrentMock($methods);

        $this->currentMock->method('getScopeConfig')
            ->willReturn($this->scopeConfig);
    }
    /**
     * @param array $methods
     * @param bool  $enableOriginalConstructor
     * @param bool  $enableProxyingToOriginalMethods
     */
    private function initCurrentMock(
        $methods = [],
        $enableOriginalConstructor = true,
        $enableProxyingToOriginalMethods = false
    ) {
        $builder = $this->getMockBuilder(BoltConfig::class)
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->encryptor,
                    $this->moduleResource,
                    $this->productMetadata,
                    $this->request
                ]
            )
            ->setMethods($methods);

        if($enableOriginalConstructor) {
            $builder->enableOriginalConstructor();
        } else {
            $builder->disableOriginalConstructor();
        }

        if($enableProxyingToOriginalMethods) {
            $builder->enableProxyingToOriginalMethods();
        } else {
            $builder->disableProxyingToOriginalMethods();
        }

        $this->currentMock = $builder->getMock();
    }

    /**
     * @test
     * @dataProvider getMerchantDashboardUrlProvider
     */
    public function getMerchantDashboardUrl($sandboxFlag, $expected)
    {
        $this->initCurrentMock(['isSandboxModeSet']);
        $this->currentMock->expects(self::once())->method('isSandboxModeSet')->willReturn($sandboxFlag);
        $this->assertEquals($expected, $this->currentMock->getMerchantDashboardUrl());
    }

    public function getMerchantDashboardUrlProvider ()
    {
        return [
            [true, BoltConfig::MERCHANT_DASH_SANDBOX],
            [false, BoltConfig::MERCHANT_DASH_PRODUCTION]
        ];
    }

    /**
     * @test
     */
    public function getStoreVersion()
    {
        $this->initCurrentMock([],true, true);

        $magentoVersion = '2.2.3';

        $this->productMetadata->expects($this->once())
            ->method('getVersion')
            ->willReturn($magentoVersion);

        $this->assertEquals($magentoVersion, $this->currentMock->getStoreVersion(), 'Cannot determine magento version');
    }

    /**
     * @test
     * @dataProvider getEncryptedKeyProvider
     */
    public function getEncryptedKey($path, $method)
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with($path)
            ->willReturn(self::KEY_ENCRYPTED);

        $this->encryptor
            ->expects(self::once())
            ->method('decrypt')
            ->with(self::KEY_ENCRYPTED)
            ->willReturn(self::KEY_DECRYPTED);

        $this->assertEquals(
            self::KEY_DECRYPTED,
            $this->currentMock->$method(),
            "$method() method: not working properly"
        );
    }

    public function getEncryptedKeyProvider()
    {
        return [
            [BoltConfig::XML_PATH_PUBLISHABLE_KEY_CHECKOUT, 'getPublishableKeyCheckout'],
            [BoltConfig::XML_PATH_PUBLISHABLE_KEY_PAYMENT, 'getPublishableKeyPayment'],
            [BoltConfig::XML_PATH_PUBLISHABLE_KEY_BACK_OFFICE, 'getPublishableKeyBackOffice'],
            [BoltConfig::XML_PATH_SIGNING_SECRET, 'getSigningSecret'],
            [BoltConfig::XML_PATH_API_KEY, 'getApiKey'],
            [BoltConfig::XML_PATH_GEOLOCATION_API_KEY, 'getGeolocationApiKey']
        ];
    }

    /**
     * @test
     */
    public function getAnyPublishableKey()
    {
        $this->initCurrentMock(['getPublishableKeyCheckout']);

        $this->currentMock
            ->expects(self::once())
            ->method('getPublishableKeyCheckout')
            ->willReturn(self::KEY_DECRYPTED);

        $this->assertEquals(
            self::KEY_DECRYPTED,
            $this->currentMock->getAnyPublishableKey(),
            'getAnyPublishableKey() method: not working properly'
        );
    }

    /**
     * @test
     */
    public function getAnyPublishableKeyIfCheckoutKeyIsEmpty()
    {
        $this->initCurrentMock(['getPublishableKeyCheckout', 'getPublishableKeyPayment']);

        $this->currentMock
            ->expects(self::once())
            ->method('getPublishableKeyCheckout')
            ->willReturn('');

        $this->currentMock
            ->expects(self::once())
            ->method('getPublishableKeyPayment')
            ->willReturn(self::KEY_DECRYPTED);

        $this->assertEquals(
            self::KEY_DECRYPTED,
            $this->currentMock->getAnyPublishableKey(),
            'getAnyPublishableKey() method: not working properly'
        );
    }

    /**
     * @test
     */
    public function getCdnUrl()
    {
        $this->initCurrentMock(['isSandboxModeSet', 'getScopeConfig']);

        $this->currentMock
            ->expects(self::once())
            ->method('isSandboxModeSet')
            ->willReturn(true);
        $this->currentMock
            ->expects(self::once())
            ->method('getScopeConfig')
            ->willReturn($this->scopeConfig);
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_CUSTOM_CDN)
            ->willReturn("");

        $this->assertEquals(
            BoltConfig::CDN_URL_SANDBOX,
            $this->currentMock->getCdnUrl(),
            'getCdnUrl() method: not working properly'
        );
    }

    /**
     * @test
     */
    public function getCdnUrl_devModeSet()
    {
        $this->initCurrentMock(['isSandboxModeSet', 'getScopeConfig']);

        $this->currentMock
            ->expects(self::once())
            ->method('isSandboxModeSet')
            ->willReturn(true);
        $this->currentMock
            ->expects(self::once())
            ->method('getScopeConfig')
            ->willReturn($this->scopeConfig);
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_CUSTOM_CDN)
            ->willReturn("https://cdn.something");

        $this->assertEquals(
            "https://cdn.something",
            $this->currentMock->getCdnUrl(),
            'getCdnUrl() method: not working properly'
        );
    }

    /**
     * @test
     */
    public function getCdnUrlInProductionMode()
    {
        $this->initCurrentMock(['isSandboxModeSet']);
        $this->currentMock
            ->expects(self::once())
            ->method('isSandboxModeSet')
            ->willReturn(false);

        $this->assertEquals(
            BoltConfig::CDN_URL_PRODUCTION,
            $this->currentMock->getCdnUrl(),
            'getCdnUrl() method: not working properly'
        );
    }

    /**
     * @test
     */
    public function getModuleVersion()
    {
        $moduleVersion = '1.0.10';
        $this->moduleResource
            ->expects(self::once())
            ->method('getDataVersion')
            ->with('Bolt_Boltpay')
            ->willReturn($moduleVersion);

        $result = $this->currentMock->getModuleVersion();

        $this->assertEquals($moduleVersion, $result, 'getModuleVersion() method: not working properly');
    }


    /**
     * @test
     */
    public function getApiUrl()
    {
        $this->initCurrentMock(['isSandboxModeSet', 'getScopeConfig']);

        $this->currentMock
            ->expects(self::once())
            ->method('isSandboxModeSet')
            ->willReturn(true);

        $this->currentMock
            ->expects(self::once())
            ->method('getScopeConfig')
            ->willReturn($this->scopeConfig);
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_CUSTOM_API)
            ->willReturn("");

        $this->assertEquals(
            BoltConfig::API_URL_SANDBOX,
            $this->currentMock->getApiUrl(),
            'getApiUrl() method: not working properly'
        );
    }

    /**
     * @test
     */
    public function getApiUrlInProductionMode()
    {
        $this->initCurrentMock(['isSandboxModeSet']);
        $this->currentMock
            ->expects(self::once())
            ->method('isSandboxModeSet')
            ->willReturn(false);

        $this->assertEquals(
            BoltConfig::API_URL_PRODUCTION,
            $this->currentMock->getApiUrl(),
            'getApiUrl() method: not working properly'
        );
    }

    /**
     * @test
     */
    public function getApiUrl_devMode()
    {
        $this->initCurrentMock(['isSandboxModeSet', 'getScopeConfig']);

        $this->currentMock
            ->expects(self::once())
            ->method('isSandboxModeSet')
            ->willReturn(true);

        $this->currentMock
            ->expects(self::once())
            ->method('getScopeConfig')
            ->willReturn($this->scopeConfig);
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_CUSTOM_API)
            ->willReturn("https://api.something");

        $this->assertEquals(
            "https://api.something",
            $this->currentMock->getApiUrl(),
            'getApiUrl() method: not working properly'
        );
    }

    /**
     * @test
     * @dataProvider isSetFlagMethodsProvider
     */
    public function isSetFlagMethods($methodName, $path, $result = true)
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('isSetFlag')
            ->with($path)
            ->willReturn($result);
        if ($result) {
            $this->assertTrue($this->currentMock->$methodName());
        } else {
            $this->assertFalse($this->currentMock->$methodName());
        }
    }

    public function isSetFlagMethodsProvider()
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
            ['useStoreCreditConfig', BoltConfig::XML_PATH_STORE_CREDIT],
            ['useRewardPointsConfig', BoltConfig::XML_PATH_REWARD_POINTS, false],
            ['isPaymentOnlyCheckoutEnabled', BoltConfig::XML_PATH_PAYMENT_ONLY_CHECKOUT],
            ['isBoltOrderCachingEnabled', BoltConfig::XML_PATH_BOLT_ORDER_CACHING, false],
            ['isSessionEmulationEnabled', BoltConfig::XML_PATH_API_EMULATE_SESSION],
            ['shouldMinifyJavascript', BoltConfig::XML_PATH_SHOULD_MINIFY_JAVASCRIPT, false],
            ['shouldCaptureMetrics', BoltConfig::XML_PATH_CAPTURE_MERCHANT_METRICS],
        ];
    }

    /**
     * @test
     * @dataProvider getValueMethodsProvider
     */
    public function getValueMethods($methodName, $path, $configValue = "test config value")
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with($path)
            ->willReturn($configValue);
        $result = $this->currentMock->$methodName();
        $this->assertEquals($configValue, $result, "$methodName() method: not working properly");
    }

    public function getValueMethodsProvider()
    {
        return [
            ['getGlobalCSS', BoltConfig::XML_PATH_GLOBAL_CSS, '.replaceable-example-selector1 {color: black;}]'],
            ['getAdditionalCheckoutButtonClass', BoltConfig::XML_PATH_ADDITIONAL_CHECKOUT_BUTTON_CLASS, 'with-cards'],
            ['getPrefetchAddressFields', BoltConfig::XML_PATH_PREFETCH_ADDRESS_FIELDS, 'address_field1, address_field2'],
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
        ];
    }

    /**
     * @test
     *
     */
    public function testGetProductPageCheckoutFlag()
    {
        $this->scopeConfig->method('isSetFlag')
                          ->with(BoltConfig::XML_PATH_PRODUCT_PAGE_CHECKOUT)
                          ->will($this->returnValue(false));

        $this->assertFalse(
            $this->currentMock->getProductPageCheckoutFlag(),
            'getProductPageCheckoutFlag() method: not working properly'
        );
    }

    /**
     * @test
     * @covers ::getScopeConfig
     */
    public function getScopeConfig()
    {
        $this->initCurrentMock([], true, true);
        $this->assertSame($this->scopeConfig, $this->currentMock->getScopeConfig());
    }

    /**
     * @test
     */
    public function getIgnoredShippingAddressCoupons()
    {
        $this->scopeConfig->method('getValue')
                ->with(BoltConfig::XML_PATH_ADDITIONAL_CONFIG)
                ->willReturn(self::ADDITIONAL_CONFIG);

        $result = $this->currentMock->getIgnoredShippingAddressCoupons(null);
        $expected = ['ignored_shipping_address_coupon'];

        $this->assertEquals($expected, $result, 'getIgnoredShippingAddressCoupons() method: not working properly');
    }

    /**
     * @test
     */
    public function getClientIp()
    {
        $request = $this->createMock(\Magento\Framework\App\Request\Http::class);
        $this->setInaccessibleProperty($this->currentMock, '_request', $request);
        $request->method('getServer')->with('HTTP_CLIENT_IP', false)->willReturn(self::TEST_IP[2]);
        $this->assertEquals(self::TEST_IP[2], $this->currentMock->getClientIp());
    }

    /**
     * @test
     */
    public function getClientIp_false()
    {
        $request = $this->createMock(\Magento\Framework\App\Request\Http::class);
        $this->setInaccessibleProperty($this->currentMock, '_request', $request);
        $request->method('getServer')->withAnyParameters()->willReturn(false);
        $this->assertEquals('', $this->currentMock->getClientIp());
    }

    /**
     * @test
     */
    public function getIPWhitelistConfig()
    {
        $expected = self::TEST_IP[0].','.self::TEST_IP[1];
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_IP_WHITELIST, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null)
            ->willReturn($expected);
        $result = $this->invokeInaccessibleMethod($this->currentMock, 'getIPWhitelistConfig');
        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function getIPWhitelistArray()
    {
        $getIPWhitelistConfig = ' , '.self::TEST_IP[0].' , '.self::TEST_IP[1].' , ';
        $expected = [self::TEST_IP[0], self::TEST_IP[1]];
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_IP_WHITELIST, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null)
            ->willReturn($getIPWhitelistConfig);
        $result = array_values($this->currentMock->getIPWhitelistArray());
        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     * @dataProvider isIPRestrictedProvider
     */
    public function isIPRestricted($ip, $whitelist, $result)
    {
        $this->initCurrentMock(
            ['getClientIp', 'getIPWhitelistArray'],
            true,
            false
        );
        $this->currentMock
            ->expects(self::once())
            ->method('getClientIp')
            ->willReturn($ip);
        $this->currentMock
            ->expects(self::once())
            ->method('getIPWhitelistArray')
            ->willReturn($whitelist);
        if ($result) {
            $this->assertTrue($this->currentMock->isIPRestricted());
        } else {
            $this->assertFalse($this->currentMock->isIPRestricted());
        }
    }

    public function isIPRestrictedProvider()
    {
        return [
            [self::TEST_IP[2], [], false],
            [self::TEST_IP[2], [self::TEST_IP[2]], false],
            [self::TEST_IP[0], [self::TEST_IP[1], self::TEST_IP[2]], true],
        ];
    }

    /**
     * @test
     */
    public function getAmastyGiftCardConfig()
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_ADDITIONAL_CONFIG, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null)
            ->willReturn(self::ADDITIONAL_CONFIG);
        $amastyGiftCardConfig = $this->currentMock->getAmastyGiftCardConfig();
        $this->assertTrue($amastyGiftCardConfig->payForEverything);
    }

    /**
     * @test
     */
    public function shouldAdjustTaxMismatch()
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_ADDITIONAL_CONFIG, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null)
            ->willReturn(self::ADDITIONAL_CONFIG);
        $adjustTaxMismatch = $this->currentMock->shouldAdjustTaxMismatch();
        $this->assertFalse($adjustTaxMismatch);
    }

    /**
     * @test
     */
    public function getToggleCheckout()
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_ADDITIONAL_CONFIG, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null)
            ->willReturn(self::ADDITIONAL_CONFIG);
        $toggleCheckout = $this->currentMock->getToggleCheckout();
        $this->assertTrue($toggleCheckout->active);
        $this->assertEquals(
            ["#top-cart-btn-checkout", "button[data-role=proceed-to-checkout]"],
            $toggleCheckout->magentoButtons
        );
    }

    private function getPageFilters($aditionalConfig = null)
    {
        $aditionalConfig = $aditionalConfig ?: self::ADDITIONAL_CONFIG;
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_ADDITIONAL_CONFIG, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null)
            ->willReturn($aditionalConfig);
    }

    /**
     * @test
     */
    public function getPageWhitelist()
    {
        $this->getPageFilters();
        $pageWhitelist = $this->currentMock->getPageWhitelist();
        $this->assertEquals(
            ["checkout_cart_index", "checkout_index_index", "checkout_onepage_success"],
            $pageWhitelist
        );
    }

    /**
     * @test
     */
    public function getPageWhitelistEmpty()
    {
        $this->getPageFilters('{"pageFilters": {}}');
        $pageWhitelist = $this->currentMock->getPageWhitelist();
        $this->assertEquals(
            [],
            $pageWhitelist
        );
    }

    /**
     * @test
     */
    public function getPageBlacklist()
    {
        $this->getPageFilters();
        $pageBlacklist = $this->currentMock->getPageBlacklist();
        $this->assertEquals(
            ["cms_index_index"],
            $pageBlacklist
        );
    }

    /**
     * @param  $object
     * @param  $property
     * @param  $value
     * @throws \ReflectionException
     */
    private static function setInaccessibleProperty($object, $property, $value)
    {
        $reflection = new \ReflectionClass(
            ($object instanceof MockObject) ? get_parent_class($object) : $object
        );
        $reflectionProperty = $reflection->getProperty($property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $value);
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object $object     Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeInaccessibleMethod($object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * @test
     * @dataProvider providerTrueAndFalse
     */
    public function isGuestCheckoutAllowed($boolean_value)
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('checkout/options/guest_checkout','store')
            ->willReturn($boolean_value);
        $result = $this->currentMock->isGuestCheckoutAllowed();
        $this->assertEquals($boolean_value, $result);
    }

    public function providerTrueAndFalse() {
        return [
            [true],
            [false],
        ];
    }
}
