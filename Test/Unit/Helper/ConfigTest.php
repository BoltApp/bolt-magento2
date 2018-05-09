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
     * @var ResourceInterface
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

        $this->scopeConfig = $this->createPartialMock(ScopeConfigInterface::class, ['getValue', 'isSetFlag']);

        $this->productMetadata = $this->createMock(ProductMetadataInterface::class);

        $this->currentMock = $this->getMockBuilder(BoltConfig::class)
            ->setConstructorArgs(
                [
                    $this->contextHelper,
                    $this->encryptor,
                    $this->moduleResource,
                    $this->productMetadata
                ]
            )
            ->getMock();
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

    }

    public function testGetPublishableKeyPayment()
    {

    }

    public function testIsActive()
    {
        $this->currentMock->expects($this->once())->method('isActive')
            ->will($this->returnValue(true));

        $this->assertTrue($this->currentMock->isActive(), 'isActive() method: not working properly');
    }

    public function testGetReplaceSelectors()
    {
        $text = '.replace-example-selector1';
        $this->currentMock->expects($this->once())->method('getReplaceSelectors')
            ->will($this->returnValue($text));

        $this->assertEquals($text, $this->currentMock->getReplaceSelectors(), 'getReplaceSelectors() method: not working properly');
    }

    public function testGetSuccessPageRedirect()
    {
        $text = 'checkout/onepage/success';
        $this->currentMock->expects($this->once())->method('getSuccessPageRedirect')
            ->will($this->returnValue($text));

        $this->assertEquals($text, $this->currentMock->getSuccessPageRedirect(), 'getSuccessPageRedirect() method: not working properly');
    }

    public function testIsSandboxModeSet()
    {
        $this->currentMock->expects($this->once())->method('isSandboxModeSet')
            ->will($this->returnValue(true));

        $this->assertTrue($this->currentMock->isSandboxModeSet(), 'IsSandboxModeSet() method: not working properly');
    }

    public function testGetAutomaticCaptureMode()
    {
        $this->currentMock->expects($this->once())->method('getAutomaticCaptureMode')
            ->will($this->returnValue(true));

        $this->assertTrue($this->currentMock->getAutomaticCaptureMode(), 'getAutomaticCaptureMode() method: not working properly');
    }

    public function testGetPrefetchShipping()
    {
        $this->currentMock->expects($this->once())->method('getPrefetchShipping')
            ->will($this->returnValue(true));

        $this->assertTrue($this->currentMock->getPrefetchShipping(), 'getPrefetchShipping() method: not working properly');
    }

    public function testGetModuleVersion()
    {

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
