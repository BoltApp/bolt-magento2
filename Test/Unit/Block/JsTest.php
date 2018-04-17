<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bolt\Boltpay\Test\Unit\Block;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as UnitObjectManager;
use Bolt\Boltpay\Block\Js as BlockJs;
use Bolt\Boltpay\Helper\Config as HelperConfig;

/**
 * Class JsTest
 *
 * @package Bolt\Boltpay\Test\Unit\Block
 */
class JsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var HelperConfig
     */
    protected $configHelper;
    /**
     * @var \Magento\Framework\App\Helper\Context
     */
    protected $helperContextMock;
    /**
     * @var \Magento\Framework\View\Element\Template\Context
     */
    protected $contextMock;

    /**
     * @var BlockJs
     */
    protected $block;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
//        $objectManager = new UnitObjectManager($this);

        $this->helperContextMock = $this->createMock(\Magento\Framework\App\Helper\Context::class);
        $this->contextMock = $this->createMock(\Magento\Framework\View\Element\Template\Context::class);

        $methods = [
            'isSandboxModeSet', 'isActive', 'getAnyPublishableKey',
            'getReplaceSelectors', 'getGlobalCSS'
        ];

        $this->configHelper = $this->getMockBuilder(HelperConfig::class)
            ->setMethods($methods)
            ->setConstructorArgs(
                [
                    $this->helperContextMock,
                    $this->createMock(\Magento\Framework\Encryption\EncryptorInterface::class),
                    $this->createMock(\Magento\Framework\Module\ResourceInterface::class),
                    $this->createMock(\Magento\Framework\App\ProductMetadataInterface::class)
                ]
            )
            ->getMock();

        $this->block = $this->getMockBuilder(BlockJs::class)
            ->setMethods(['configHelper'])
            ->setConstructorArgs(
                [
                    $this->contextMock,
                    $this->configHelper,
                ]
            )
            ->getMock();
    }

    /**
     * @inheritdoc
     */
    public function testGetTrackJsUrl()
    {
        // For CDN URL in sandbox mode
        $this->setSandboxMode();
        $result = $this->block->getTrackJsUrl();
        $this->checkEquals($result, true, 'track.js');

        // For CDN URL in production mode.
        $this->setSandboxMode(false);
        $result = $this->block->getTrackJsUrl();
        $this->checkEquals($result, true, 'track.js');
    }

    /**
     * @inheritdoc
     */
    public function testGetConnectJsUrl()
    {
        // For CDN URL in sandbox mode
        $this->setSandboxMode();
        $result = $this->block->getConnectJsUrl();
        $this->checkEquals($result, true, 'connect.js');

        // For CDN URL in production mode.
        $this->setSandboxMode(false);
        $result = $this->block->getConnectJsUrl();
        $this->checkEquals($result, true, 'connect.js');
    }

    /**
     * @inheritdoc
     */
    public function testGetCheckoutKey()
    {
        $storeId = 0;
        $key = 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TEST';
        $this->configHelper->expects($this->any())
            ->method('getAnyPublishableKey')
            ->will($this->returnValue($key));

        $result = $this->block->getCheckoutKey();

        $this->assertStringStartsWith('pKv_', $result, '"Any Publishable Key" not working properly');
        $this->assertEquals(strlen($key), strlen($result), '"Any Publishable Key" have invalid length');
    }

    /**
     * @inheritdoc
     */
    public function testGetReplaceSelectors()
    {
        $value = '.replaceable-example-selector1|append
.replaceable-example-selector2|prepend,.replaceable-example-selector3';

        $correctResult = [
            '.replaceable-example-selector1|append .replaceable-example-selector2|prepend',
            '.replaceable-example-selector3'
        ];

        $this->configHelper->expects($this->once())
            ->method('getReplaceSelectors')
            ->will($this->returnValue($value));

        $result = $this->block->getReplaceSelectors();

        $this->assertEquals($correctResult, $result, 'getReplaceSelectors() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetGlobalCSS()
    {
        $value = '.replaceable-example-selector1 {
            color: red;
        }';

        $this->configHelper->expects($this->once())
            ->method('getGlobalCSS')
            ->will($this->returnValue($value));

        $result = $this->block->getGlobalCSS();

        $this->assertEquals($value, $result, 'getGlobalCSS() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testIsEnabled()
    {
        $storeId = 0;
        $this->configHelper->expects($this->any())
            ->method('isActive')
            ->with($storeId)
            ->will($this->returnValue(true));

        $result = $this->block->isEnabled();

        $this->assertTrue($result, 'IsEnabled() method: not working properly');
    }
    /**
     * Check if CDN Url equal or not.
     *
     * @param bool   $sandbox
     * @param string $file
     */
    public function checkEquals($result, $sandbox = true, $file = '')
    {
        $mode = ($sandbox) ? HelperConfig::CDN_URL_SANDBOX : HelperConfig::CDN_URL_PRODUCTION;
        $expectedUrl = $mode . DIRECTORY_SEPARATOR . $file;
        $modeMessage = ($sandbox) ? 'Sandbox' : 'Production';

        $this->assertEquals($expectedUrl, $result, 'Not equal CDN Url in '.$modeMessage.' mode');
    }

    /**
     * Get CDN url mode.
     *
     * @param bool $value
     */
    public function setSandboxMode($value = true)
    {
        $this->configHelper->expects($this->any())
            ->method('isSandboxModeSet')
            ->will($this->returnValue($value));
    }
}
