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
 * Class ConfigTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper
 */
class ConfigTest extends TestCase
{
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
        $this->context = $this->createMock(Context::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->moduleResource = $this->createMock(ModuleResource::class);

        $this->scopeConfig = $this->getMockBuilder(ScopeConfigInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

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
        $this->initCurrentMock(['isSandboxModeSet'],true, false);
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
     */
    public function getPublishableKeyCheckout()
    {
        $configValue = '0:2:m8z7GPChWNjJPrvLPgFSGE5j0bZ7XiK9:O0zw8cQQlmS/NFDVrjP/CebewQRKEpAqKF+QyCXIJuai8ep4ziQKs/mzBktyEmv5b2uXRcTr3l+Hv7N1ADx0oJfEC6L/R0pg04j1mi4jcjFs9RxZ1t4UVpwYl8cguYP1';
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_PUBLISHABLE_KEY_CHECKOUT)
            ->willReturn($configValue);

        $decryptedKey = 'pKv_p0zR1E1I.Y0jBkEIjgggR.4e1911f1d15511cd7548c1953f2479b2689f2e5a20188c5d7f666c1149136300';
        $this->encryptor
            ->expects(self::once())
            ->method('decrypt')
            ->with($configValue)
            ->willReturn($decryptedKey);

        $this->assertEquals($decryptedKey, $this->currentMock->getPublishableKeyCheckout(), 'getPublishableKeyCheckout() method: not working properly');
    }

    /**
     * @test
     */
    public function getAnyPublishableKey()
    {
        $decryptedKey = 'pKv_p0zR1E1I.Y0jBkEIjgggR.4e1911f1d15511cd7548c1953f2479b2689f2e5a20188c5d7f666c1149136300';

        $this->initCurrentMock(['getPublishableKeyCheckout'], true);

        $this->currentMock
            ->expects(self::once())
            ->method('getPublishableKeyCheckout')
            ->willReturn($decryptedKey);

        $result = $this->currentMock->getAnyPublishableKey();

        $this->assertEquals($decryptedKey, $result, 'getAnyPublishableKey() method: not working properly');
    }

    /**
     * @test
     */
    public function getAnyPublishableKeyIfCheckoutKeyIsEmpty()
    {
        $decryptedCheckoutKey = '';
        $decryptedPaymentKey = 'pKv_pOzR4ElI.iCXN0f1DX7j6.43dd17e5a78fda11a854839561458f522b847094d19b621e08187426c335b201';

        $this->initCurrentMock(['getPublishableKeyCheckout', 'getPublishableKeyPayment'], true);

        $this->currentMock->method('getPublishableKeyCheckout')
            ->willReturn($decryptedCheckoutKey);

        $this->currentMock->method('getPublishableKeyPayment')
            ->willReturn($decryptedPaymentKey);

        $result = $this->currentMock->getAnyPublishableKey();

        $this->assertEquals($decryptedPaymentKey, $result, 'getAnyPublishableKey() method: not working properly');
    }

    /**
     * @test
     */
    public function getCdnUrl()
    {
        $this->initCurrentMock(['isSandboxModeSet'], true);
        $this->currentMock
            ->expects(self::once())
            ->method('isSandboxModeSet')
            ->willReturn(true);

        $result = $this->currentMock->getCdnUrl();

        $this->assertEquals(BoltConfig::CDN_URL_SANDBOX, $result, 'getCdnUrl() method: not working properly');
    }

    /**
     * @test
     */
    public function getCdnUrlInProductionMode()
    {
        $this->initCurrentMock(['isSandboxModeSet'], true);
        $this->currentMock
            ->expects(self::once())
            ->method('isSandboxModeSet')
            ->willReturn(false);

        $result = $this->currentMock->getCdnUrl();

        $this->assertEquals(BoltConfig::CDN_URL_PRODUCTION, $result, 'getCdnUrl() method: not working properly');
    }

    /**
     * @test
     */
    public function getPublishableKeyPayment()
    {
        $configValue = '0:2:97N0zWAd4aTmvu2C0L8uGhlI6jvfTvnS:B6v46SD8Xyy3we00mLMwNqd7GxJGkuLGGbHv/3sWaIzB7PS5DcP8tIeQSlL9/tT+mHMk+VwsLZG+AjAxDum3LCaqjVojrdKNQUZA/5QRfa7bYxIT0hDy7tybpXXXX//Y';
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_PUBLISHABLE_KEY_PAYMENT)
            ->willReturn($configValue);

        $decryptedKey = 'pKv_pOzR4ElI.iCXN0f1DX7j6.43dd17e5a78fda11a854839561458f522b847094d19b621e08187426c335b201';
        $this->encryptor
            ->expects(self::once())
            ->method('decrypt')
            ->with($configValue)
            ->willReturn($decryptedKey);

        $this->assertEquals($decryptedKey, $this->currentMock->getPublishableKeyPayment(), 'getPublishableKeyPayment() method: not working properly');
    }

    /**
     * @test
     */
    public function getPublishableKeyBackOffice()
    {
        $configValue = '0:2:97N0zWAd4aTmvu2C0L8uGhlI6jvfTvnS:B6v46SD8Xyy3we00mLMwNqd7GxJGkuLGGbHv/3sWaIzB7PS5DcP8tIeQSlL9/tT+mHMk+VwsLZG+AjAxDum3LCaqjVojrdKNQUZA/5QRfa7bYxIT0hDy7tybpXXXX//Y';
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_PUBLISHABLE_KEY_BACK_OFFICE)
            ->willReturn($configValue);

        $decryptedKey = 'pKv_pOzR4ElI.iCXN0f1DX7j6.43dd17e5a78fda11a854839561458f522b847094d19b621e08187426c335b201';
        $this->encryptor
            ->expects(self::once())
            ->method('decrypt')
            ->with($configValue)
            ->willReturn($decryptedKey);

        $this->assertEquals($decryptedKey, $this->currentMock->getPublishableKeyBackOffice(), 'getPublishableKeyPayment() method: not working properly');
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
    public function getSigningSecret()
    {
        $configValue = '0:2:97N0zWAd4aTmvu2C0L8uGhlI6jvfTvnS:B6v46SD8Xyy4157mLMwNqd7GxJGkuL\LKSmv3sWaIzB7PS5DcP8tIeQSlL9/tY+mHOk+VwsAAG+0jaxDum3LCAqjV0jrdKNQUZA/5QRfa7bYxIT0hDy7tybpXXXX//Y';
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_SIGNING_SECRET)
            ->willReturn($configValue);

        $decryptedKey = '114faae6a9e0893697dd3fc1ad2afdaafa63a5689bd6a954343e8f4c6275da76';
        $this->encryptor
            ->expects(self::once())
            ->method('decrypt')
            ->with($configValue)
            ->willReturn($decryptedKey);

        $this->assertEquals($decryptedKey, $this->currentMock->getSigningSecret(), 'getSigningSecret() method: not working properly');
    }

    /**
     * @test
     */
    public function getApiUrl()
    {
        $this->initCurrentMock(['isSandboxModeSet'],true, false);
        $this->currentMock->method('isSandboxModeSet')
            ->willReturn(true);

        $result = $this->currentMock->getApiUrl();

        $this->assertEquals(BoltConfig::API_URL_SANDBOX, $result, 'getApiUrl() method: not working properly');
    }

    /**
     * @test
     */
    public function getApiUrlInProductionMode()
    {
        $this->initCurrentMock(['isSandboxModeSet'],true, false);
        $this->currentMock->method('isSandboxModeSet')
            ->willReturn(false);

        $result = $this->currentMock->getApiUrl();
        $this->assertEquals(BoltConfig::API_URL_PRODUCTION, $result, 'getApiUrl() method: not working properly');
    }

    /**
     * @test
     */
    public function getApiKey()
    {
        $configValue = '0:2:zWKTWcrt1CUe1PzR1h73oa8PNgknv2dV:ZaCiGOAwUsUSt76s49kji8Je9ybOK0MFlS774xtr+xh4YrdMQaIW5s8yP/8M4/U0KBY/VbplggggSCojP8uGcg==';
        $this->scopeConfig->method('getValue')
            ->with(BoltConfig::XML_PATH_API_KEY)
            ->willReturn($configValue);

        $decryptedKey = '60c47bdb25b0b133840808ce5fd2879d6295c53d0265c70e311552fb2028b00b';
        $this->encryptor->method('decrypt')
            ->with($configValue)
            ->willReturn($decryptedKey);

        $this->assertEquals($decryptedKey, $this->currentMock->getApiKey(), 'getApiKey() method: not working properly');
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
     */
    public function getScopeConfig()
    {
        $this->initCurrentMock([], true, true);
        $this->assertNull($this->currentMock->getScopeConfig());
    }

    /**
     * @test
     */
    public function getGeolocationApiKey() {
        $configValue = '0:2:zWKTWcrt1CUe1PzR1h73oa8PNgknv2dV:ZaCiGOAwUsUSt76s49kji8Je9ybOK0MFlS774xtr+xh4YrdMQaIW5s8yP/8M4/U0KBY/VbplggggSCojP8uGcg==';
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_GEOLOCATION_API_KEY)
            ->willReturn($configValue);
        $decryptedKey = '60c47bdb25b0b133840808ce5fd2879d6295c53d0265c70e311552fb2028b00b';
        $this->encryptor
            ->expects(self::once())
            ->method('decrypt')
            ->with($configValue)
            ->willReturn($decryptedKey);
        $this->assertEquals($decryptedKey, $this->currentMock->getGeolocationApiKey(), 'getGeolocationApiKey() method: not working properly');
    }

    /**
     * @test
     */
    public function getIgnoredShippingAddressCoupons()
    {
        $configCouponsJson = '{"ignoredShippingAddressCoupons": ["IGNORED_SHIPPING_ADDRESS_COUPON"]}';

        $this->scopeConfig->method('getValue')
                ->with(BoltConfig::XML_PATH_ADDITIONAL_CONFIG)
                ->willReturn($configCouponsJson);

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
        $ip = '123.123.123.123';
        $request->method('getServer')->with('HTTP_CLIENT_IP', false)->willReturn($ip);
        $this->assertEquals($ip, $this->currentMock->getClientIp());
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
        $expected = '111.111.111.111,222.222.222.222';
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
        $getIPWhitelistConfig = ' , 111.111.111.111 , 222.222.222.222 , ';
        $expected = ['111.111.111.111', '222.222.222.222'];
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_IP_WHITELIST, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null)
            ->willReturn($getIPWhitelistConfig);
        $result = array_values($this->currentMock->getIPWhitelistArray());
        $this->assertArraySimilar($expected, $result);
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
            ['123.123.123.123', ['123.123.123.123'], false],
            ['111.111.111.111', ['222.222.222.222', '123.123.123.123'], true],
        ];
    }

    /**
     * @test
     */
    public function getAmastyGiftCardConfig()
    {
        $aditionalConfig = '{"amastyGiftCard": {"payForEverything": true}}';
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_ADDITIONAL_CONFIG, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null)
            ->willReturn($aditionalConfig);
        $amastyGiftCardConfig = $this->currentMock->getAmastyGiftCardConfig();
        $this->assertTrue($amastyGiftCardConfig->payForEverything);
    }

    /**
     * @test
     */
    public function shouldAdjustTaxMismatch()
    {
        $aditionalConfig = '{"adjustTaxMismatch": false}';
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_ADDITIONAL_CONFIG, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null)
            ->willReturn($aditionalConfig);
        $adjustTaxMismatch = $this->currentMock->shouldAdjustTaxMismatch();
        $this->assertFalse($adjustTaxMismatch);
    }

    /**
     * @test
     */
    public function getToggleCheckout()
    {
        $aditionalConfig = '
        {
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
            }
        }';
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(BoltConfig::XML_PATH_ADDITIONAL_CONFIG, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null)
            ->willReturn($aditionalConfig);
        $toggleCheckout = $this->currentMock->getToggleCheckout();
        $this->assertTrue($toggleCheckout->active);
        $this->assertArraySimilar(
            ["#top-cart-btn-checkout", "button[data-role=proceed-to-checkout]"],
            $toggleCheckout->magentoButtons
        );
    }

    private function getPageFilters($aditionalConfig = null)
    {
        $aditionalConfig = $aditionalConfig ?: '
        {
            "pageFilters": {
                "whitelist": ["checkout_cart_index", "checkout_index_index", "checkout_onepage_success"],
                "blacklist": ["cms_index_index"]
            }
        }';
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
        $this->assertArraySimilar(
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
        $this->assertArraySimilar(
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
        $this->assertArraySimilar(
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
     * Asserts that two associative arrays are similar.
     *
     * Both arrays must have the same indexes with identical values
     * without respect to key ordering
     *
     * @param array $expected
     * @param array $array
     */
    protected function assertArraySimilar(array $expected, array $array)
    {
        $this->assertEquals([], array_diff_key($array, $expected));
        foreach ($expected as $key => $value) {
            if (is_array($value)) {
                $this->assertArraySimilar($value, $array[$key]);
            } else {
                $this->assertContains($value, $array);
            }
        }
    }
}
