<?php

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\Config as BoltConfig;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleResource;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context as ContextHelper;

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
     * @var ContextHelper
     */
    private $contextHelper;

    public function setUp()
    {
        $this->contextHelper = $this->createMock(ContextHelper::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->moduleResource = $this->createMock(ModuleResource::class);

        $this->scopeConfig = $this->getMockBuilder(ScopeConfigInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->productMetadata = $this->createMock(ProductMetadataInterface::class);

        $methods = ['getGlobalCSS', 'isDebugModeOn', 'getScopeConfig'];
        $this->currentMock = $this->getMockBuilder(BoltConfig::class)
            ->setMethods($methods)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->contextHelper,
                    $this->encryptor,
                    $this->moduleResource,
                    $this->productMetadata
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

    public function testIsDebugModeOn()
    {
        $this->currentMock->expects($this->once())->method('isDebugModeOn')
            ->will($this->returnValue(true));

        $this->assertTrue($this->currentMock->isDebugModeOn(), 'isDebugModeOn() method: not working properly');
    }

    public function testGetPublishableKeyCheckout()
    {

    }

    public function testGetAnyPublishableKey()
    {

    }

    public function testGetCdnUrl()
    {
        $mock = $this->getMockBuilder(BoltConfig::class)
            ->setMethods(['isSandboxModeSet'])
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->contextHelper,
                    $this->encryptor,
                    $this->moduleResource,
                    $this->productMetadata
                ]
            )
            ->getMock();

        $mock->method('isSandboxModeSet')
            ->will($this->returnValue(false));


        $result = $mock->getCdnUrl();

        $this->assertEquals(BoltConfig::CDN_URL_PRODUCTION, $result, 'getCdnUrl() method: not working properly');
    }

    public function testGetPublishableKeyPayment()
    {

    }

    public function testIsActive()
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(BoltConfig::XML_PATH_ACTIVE)
            ->will($this->returnValue(true));


        $result = $this->currentMock->isActive();

        $this->assertTrue($result, 'isActive() method: not working properly');
    }

    public function testGetReplaceSelectors()
    {
        $value = 'button#top-cart-btn-checkout, button[data-role=proceed-to-checkout]|prepend';

        $this->scopeConfig->method('getValue')
            ->with(BoltConfig::XML_PATH_REPLACE_SELECTORS)
            ->will($this->returnValue($value));

        $this->assertEquals($value, $this->currentMock->getReplaceSelectors(), 'getReplaceSelectors() method: not working properly');
    }

    public function testGetSuccessPageRedirect()
    {
        $value = 'checkout/onepage/success';
        $this->scopeConfig->method('getValue')
            ->with(BoltConfig::XML_PATH_SUCCESS_PAGE_REDIRECT)
            ->will($this->returnValue($value));

        $this->assertEquals($value, $this->currentMock->getSuccessPageRedirect(), 'getSuccessPageRedirect() method: not working properly');
    }

    public function testIsSandboxModeSet()
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(BoltConfig::XML_PATH_SANDBOX_MODE)
            ->will($this->returnValue(true));

        $this->assertTrue($this->currentMock->isSandboxModeSet(), 'IsSandboxModeSet() method: not working properly');
    }

    public function testGetAutomaticCaptureMode()
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(BoltConfig::XML_PATH_AUTOMATIC_CAPTURE_MODE)
            ->will($this->returnValue(false));

        $this->assertFalse($this->currentMock->getAutomaticCaptureMode(), 'getAutomaticCaptureMode() method: not working properly');
    }

    public function testGetPrefetchShipping()
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(BoltConfig::XML_PATH_PREFETCH_SHIPPING)
            ->will($this->returnValue(TRUE));

        $this->assertTrue($this->currentMock->getPrefetchShipping(), 'getPrefetchShipping() method: not working properly');
    }

    public function testGetModuleVersion()
    {
        $moduleVersion = '1.0.10';
        $this->moduleResource->method('getDataVersion')
            ->with('Bolt_Boltpay')
            ->will($this->returnValue($moduleVersion));

        $result = $this->currentMock->getModuleVersion();

        $this->assertEquals($moduleVersion, $result, 'getModuleVersion() method: not working properly');
    }

    public function testGetSigningSecret()
    {

    }

    public function testGetApiUrl()
    {

    }

    public function testGetApiKey()
    {

    }
}
