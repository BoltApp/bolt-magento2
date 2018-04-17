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


        $this->configHelper = $this->getMockBuilder(HelperConfig::class)
            ->setMethods(['isSandboxModeSet'])
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
