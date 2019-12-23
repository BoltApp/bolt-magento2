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
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var BoltConfig
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

        $methods = ['getGlobalCSS', 'isDebugModeOn', 'getScopeConfig'];
        $this->currentMock = $this->getMockBuilder(BoltConfig::class)
            ->setMethods($methods)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->encryptor,
                    $this->moduleResource,
                    $this->productMetadata,
                    $this->request
                ]
            )
            ->getMock();

        $this->currentMock->method('getScopeConfig')
            ->will($this->returnValue($this->scopeConfig));
    }

    /**
     * @inheritdoc
     */
    public function testGetStoreVersion()
    {
        $magentoVersion = '2.2.3';

        $this->productMetadata->expects($this->once())
            ->method('getVersion')
            ->will($this->returnValue($magentoVersion));

        $result = $this->productMetadata->getVersion();

        $this->assertEquals($magentoVersion, $result, 'Cannot determine magento version');
    }

    /**
     * @inheritdoc
     */
    public function testGetGlobalCSS()
    {
        $value = '.replaceable-example-selector1 {
            color: black;
        }';

        $this->currentMock->expects($this->once())->method('getGlobalCSS')
            ->will($this->returnValue($value));

        $result = $this->currentMock->getGlobalCSS();

        $this->assertEquals($value, $result, 'getGlobalCSS() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testIsDebugModeOn()
    {
        $this->currentMock->expects($this->once())->method('isDebugModeOn')
            ->will($this->returnValue(true));

        $this->assertTrue($this->currentMock->isDebugModeOn(), 'isDebugModeOn() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetPublishableKeyCheckout()
    {
        $configValue = '0:2:m8z7GPChWNjJPrvLPgFSGE5j0bZ7XiK9:O0zw8cQQlmS/NFDVrjP/CebewQRKEpAqKF+QyCXIJuai8ep4ziQKs/mzBktyEmv5b2uXRcTr3l+Hv7N1ADx0oJfEC6L/R0pg04j1mi4jcjFs9RxZ1t4UVpwYl8cguYP1';
        $this->scopeConfig->method('getValue')
            ->with(BoltConfig::XML_PATH_PUBLISHABLE_KEY_CHECKOUT)
            ->will($this->returnValue($configValue));

        $decryptedKey = 'pKv_p0zR1E1I.Y0jBkEIjgggR.4e1911f1d15511cd7548c1953f2479b2689f2e5a20188c5d7f666c1149136300';
        $this->encryptor->method('decrypt')
            ->with($configValue)
            ->will($this->returnValue($decryptedKey));

        $this->assertEquals($decryptedKey, $this->currentMock->getPublishableKeyCheckout(), 'getPublishableKeyCheckout() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetAnyPublishableKey()
    {
        $decryptedKey = 'pKv_p0zR1E1I.Y0jBkEIjgggR.4e1911f1d15511cd7548c1953f2479b2689f2e5a20188c5d7f666c1149136300';

        $currentMock = $this->getMockBuilder(BoltConfig::class)
            ->setMethods(['getPublishableKeyCheckout'])
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->encryptor,
                    $this->moduleResource,
                    $this->productMetadata,
                    $this->request
                ]
            )
            ->getMock();

        $currentMock->method('getPublishableKeyCheckout')
            ->will($this->returnValue($decryptedKey));

        $result = $currentMock->getAnyPublishableKey();

        $this->assertEquals($decryptedKey, $result, 'getAnyPublishableKey() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetAnyPublishableKeyIfCheckoutKeyIsEmpty()
    {
        $decryptedCheckoutKey = '';
        $decryptedPaymentKey = 'pKv_pOzR4ElI.iCXN0f1DX7j6.43dd17e5a78fda11a854839561458f522b847094d19b621e08187426c335b201';

        $currentMock = $this->getMockBuilder(BoltConfig::class)
            ->setMethods(['getPublishableKeyCheckout', 'getPublishableKeyPayment'])
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->encryptor,
                    $this->moduleResource,
                    $this->productMetadata,
                    $this->request
                ]
            )
            ->getMock();

        $currentMock->method('getPublishableKeyCheckout')
            ->will($this->returnValue($decryptedCheckoutKey));

        $currentMock->method('getPublishableKeyPayment')
            ->will($this->returnValue($decryptedPaymentKey));

        $result = $currentMock->getAnyPublishableKey();

        $this->assertEquals($decryptedPaymentKey, $result, 'getAnyPublishableKey() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetCdnUrl()
    {
        $mock = $this->getMockBuilder(BoltConfig::class)
            ->setMethods(['isSandboxModeSet'])
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->encryptor,
                    $this->moduleResource,
                    $this->productMetadata,
                    $this->request
                ]
            )
            ->getMock();

        $mock->method('isSandboxModeSet')
            ->will($this->returnValue(true));


        $result = $mock->getCdnUrl();

        $this->assertEquals(BoltConfig::CDN_URL_SANDBOX, $result, 'getCdnUrl() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetCdnUrlInProductionMode()
    {
        $mock = $this->getMockBuilder(BoltConfig::class)
            ->setMethods(['isSandboxModeSet'])
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->encryptor,
                    $this->moduleResource,
                    $this->productMetadata,
                    $this->request
                ]
            )
            ->getMock();

        $mock->method('isSandboxModeSet')
            ->will($this->returnValue(false));


        $result = $mock->getCdnUrl();

        $this->assertEquals(BoltConfig::CDN_URL_PRODUCTION, $result, 'getCdnUrl() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetPublishableKeyPayment()
    {
        $configValue = '0:2:97N0zWAd4aTmvu2C0L8uGhlI6jvfTvnS:B6v46SD8Xyy3we00mLMwNqd7GxJGkuLGGbHv/3sWaIzB7PS5DcP8tIeQSlL9/tT+mHMk+VwsLZG+AjAxDum3LCaqjVojrdKNQUZA/5QRfa7bYxIT0hDy7tybpXXXX//Y';
        $this->scopeConfig->method('getValue')
            ->with(BoltConfig::XML_PATH_PUBLISHABLE_KEY_PAYMENT)
            ->will($this->returnValue($configValue));

        $decryptedKey = 'pKv_pOzR4ElI.iCXN0f1DX7j6.43dd17e5a78fda11a854839561458f522b847094d19b621e08187426c335b201';
        $this->encryptor->method('decrypt')
            ->with($configValue)
            ->will($this->returnValue($decryptedKey));

        $this->assertEquals($decryptedKey, $this->currentMock->getPublishableKeyPayment(), 'getPublishableKeyPayment() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testIsActive()
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(BoltConfig::XML_PATH_ACTIVE)
            ->will($this->returnValue(true));


        $result = $this->currentMock->isActive();

        $this->assertTrue($result, 'isActive() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetReplaceSelectors()
    {
        $value = 'button#top-cart-btn-checkout, button[data-role=proceed-to-checkout]|prepend';

        $this->scopeConfig->method('getValue')
            ->with(BoltConfig::XML_PATH_REPLACE_SELECTORS)
            ->will($this->returnValue($value));

        $this->assertEquals($value, $this->currentMock->getReplaceSelectors(), 'getReplaceSelectors() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetSuccessPageRedirect()
    {
        $value = 'checkout/onepage/success';
        $this->scopeConfig->method('getValue')
            ->with(BoltConfig::XML_PATH_SUCCESS_PAGE_REDIRECT)
            ->will($this->returnValue($value));

        $this->assertEquals($value, $this->currentMock->getSuccessPageRedirect(), 'getSuccessPageRedirect() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testIsSandboxModeSet()
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(BoltConfig::XML_PATH_SANDBOX_MODE)
            ->will($this->returnValue(true));

        $this->assertTrue($this->currentMock->isSandboxModeSet(), 'IsSandboxModeSet() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetPrefetchShipping()
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(BoltConfig::XML_PATH_PREFETCH_SHIPPING)
            ->will($this->returnValue(true));

        $this->assertTrue($this->currentMock->getPrefetchShipping(), 'getPrefetchShipping() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetModuleVersion()
    {
        $moduleVersion = '1.0.10';
        $this->moduleResource->method('getDataVersion')
            ->with('Bolt_Boltpay')
            ->will($this->returnValue($moduleVersion));

        $result = $this->currentMock->getModuleVersion();

        $this->assertEquals($moduleVersion, $result, 'getModuleVersion() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetSigningSecret()
    {
        $configValue = '0:2:97N0zWAd4aTmvu2C0L8uGhlI6jvfTvnS:B6v46SD8Xyy4157mLMwNqd7GxJGkuL\LKSmv3sWaIzB7PS5DcP8tIeQSlL9/tY+mHOk+VwsAAG+0jaxDum3LCAqjV0jrdKNQUZA/5QRfa7bYxIT0hDy7tybpXXXX//Y';
        $this->scopeConfig->method('getValue')
            ->with(BoltConfig::XML_PATH_SIGNING_SECRET)
            ->will($this->returnValue($configValue));

        $decryptedKey = '114faae6a9e0893697dd3fc1ad2afdaafa63a5689bd6a954343e8f4c6275da76';
        $this->encryptor->method('decrypt')
            ->with($configValue)
            ->will($this->returnValue($decryptedKey));

        $this->assertEquals($decryptedKey, $this->currentMock->getSigningSecret(), 'getSigningSecret() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetApiUrl()
    {
        $mock = $this->getMockBuilder(BoltConfig::class)
            ->setMethods(['isSandboxModeSet'])
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->encryptor,
                    $this->moduleResource,
                    $this->productMetadata,
                    $this->request
                ]
            )
            ->getMock();

        $mock->method('isSandboxModeSet')
            ->will($this->returnValue(true));


        $result = $mock->getApiUrl();

        $this->assertEquals(BoltConfig::API_URL_SANDBOX, $result, 'getApiUrl() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetApiUrlInProductionMode()
    {
        $mock = $this->getMockBuilder(BoltConfig::class)
            ->setMethods(['isSandboxModeSet'])
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->encryptor,
                    $this->moduleResource,
                    $this->productMetadata,
                    $this->request
                ]
            )
            ->getMock();

        $mock->method('isSandboxModeSet')
            ->will($this->returnValue(false));


        $result = $mock->getApiUrl();

        $this->assertEquals(BoltConfig::API_URL_PRODUCTION, $result, 'getApiUrl() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetApiKey()
    {
        $configValue = '0:2:zWKTWcrt1CUe1PzR1h73oa8PNgknv2dV:ZaCiGOAwUsUSt76s49kji8Je9ybOK0MFlS774xtr+xh4YrdMQaIW5s8yP/8M4/U0KBY/VbplggggSCojP8uGcg==';
        $this->scopeConfig->method('getValue')
            ->with(BoltConfig::XML_PATH_API_KEY)
            ->will($this->returnValue($configValue));

        $decryptedKey = '60c47bdb25b0b133840808ce5fd2879d6295c53d0265c70e311552fb2028b00b';
        $this->encryptor->method('decrypt')
            ->with($configValue)
            ->will($this->returnValue($decryptedKey));

        $this->assertEquals($decryptedKey, $this->currentMock->getApiKey(), 'getApiKey() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetjavascriptSuccess()
    {
        $configValue = "(function() { alert('Test: get js success'); })";
        $this->scopeConfig->method('getValue')
            ->with(BoltConfig::XML_PATH_JAVASCRIPT_SUCCESS)
            ->will($this->returnValue($configValue));

        $result = $this->currentMock->getJavascriptSuccess();

        $this->assertEquals($configValue, $result, 'getjavascriptSuccess() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetAdditionalJS()
    {
        $configValue = "(function() { alert('Test: get additional js'); })";
        $this->scopeConfig->method('getValue')
            ->with(BoltConfig::XML_PATH_ADDITIONAL_JS)
            ->will($this->returnValue($configValue));

        $result = $this->currentMock->getAdditionalJS();

        $this->assertEquals($configValue, $result, 'getAdditionalJS() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetScopeConfig()
    {
        $result = $this->currentMock->getScopeConfig();

        $this->assertInstanceOf(ScopeConfigInterface::class, $result, 'getScopeConfig() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetIgnoredShippingAddressCoupons()
    {
        $configCouponsJson = '{"ignoredShippingAddressCoupons": ["IGNORED_SHIPPING_ADDRESS_COUPON"]}';

        $this->scopeConfig->method('getValue')
                ->with(BoltConfig::XML_PATH_ADDITIONAL_CONFIG)
                ->will($this->returnValue($configCouponsJson));

        $result = $this->currentMock->getIgnoredShippingAddressCoupons(null);
        $expected = ['ignored_shipping_address_coupon'];

        $this->assertEquals($expected, $result, 'getIgnoredShippingAddressCoupons() method: not working properly');
    }
}
