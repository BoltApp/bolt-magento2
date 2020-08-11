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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Plugin\ThirdPartySupport;

use Bolt\Boltpay\Plugin\ThirdPartySupport\CommonModuleContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\ThirdPartySupport\CommonModuleContext
 */
class CommonModuleContextTest extends TestCase
{
    /** @var string Test supported module */
    const TEST_SUPPORTED_MODULE = 'Vendor_SupportedModule';

    /** @var string Test minimum version of the supported module that the plugin supports */
    const TEST_VERSION_FROM = '1.0.0';

    /** @var string Test maximum version of the supported module that the plugin supports */
    const TEST_VERSION_TO = '9.9.9';

    /**
     * @var CommonModuleContext|MockObject mocked instance of the context class
     */
    private $contextMock;

    /**
     * @var \Bolt\Boltpay\Helper\Bugsnag|MockObject mocked instance of the Bolt Bugsnag helper
     */
    private $bugsnagHelperMock;

    /**
     * @var \Bolt\Boltpay\Helper\Log|MockObject mocked instance of the Bolt logging helper
     */
    private $logHelperMock;

    /**
     * @var \Magento\Framework\Module\Manager|MockObject mocked instance of the Magento module manager instance
     */
    private $moduleManagerMock;

    /**
     * @var \Magento\Framework\Module\ResourceInterface|MockObject mocked instance of the Magento module resouce model
     */
    private $moduleResourceMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->bugsnagHelperMock = $this->createMock(\Bolt\Boltpay\Helper\Bugsnag::class);
        $this->logHelperMock = $this->createMock(\Bolt\Boltpay\Helper\Log::class);
        $this->moduleManagerMock = $this->createMock(\Magento\Framework\Module\Manager::class);
        $this->moduleResourceMock = $this->createMock(\Magento\Framework\Module\ResourceInterface::class);
        $this->contextMock = $this->getMockBuilder(CommonModuleContext::class)
            ->setConstructorArgs(
                [
                    $this->bugsnagHelperMock,
                    $this->logHelperMock,
                    $this->moduleManagerMock,
                    $this->moduleResourceMock,
                    self::TEST_SUPPORTED_MODULE,
                    self::TEST_VERSION_FROM,
                    self::TEST_VERSION_TO
                ]
            )
            ->enableOriginalConstructor()
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     * @test
     * that constructor will populate properties with provided arguments
     *
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperties()
    {
        $instance = new CommonModuleContext(
            $this->bugsnagHelperMock,
            $this->logHelperMock,
            $this->moduleManagerMock,
            $this->moduleResourceMock,
            self::TEST_SUPPORTED_MODULE,
            self::TEST_VERSION_FROM,
            self::TEST_VERSION_TO
        );
        static::assertAttributeEquals($this->bugsnagHelperMock, 'bugsnagHelper', $instance);
        static::assertAttributeEquals($this->logHelperMock, 'logHelper', $instance);
        static::assertAttributeEquals($this->moduleManagerMock, 'moduleManager', $instance);
        static::assertAttributeEquals($this->moduleResourceMock, 'moduleResource', $instance);
        static::assertAttributeEquals(self::TEST_SUPPORTED_MODULE, 'supportedModule', $instance);
        static::assertAttributeEquals(self::TEST_VERSION_FROM, 'versionFrom', $instance);
        static::assertAttributeEquals(self::TEST_VERSION_TO, 'versionTo', $instance);
    }

    /**
     * @test
     * that getLogHelper returns log helper from internal property
     *
     * @covers ::getLogHelper
     */
    public function getLogHelper()
    {
        static::assertEquals($this->logHelperMock, $this->contextMock->getLogHelper());
    }

    /**
     * @test
     * that getModuleResource returns module resource from internal property
     *
     * @covers ::getModuleResource
     */
    public function getModuleResource()
    {
        static::assertEquals($this->moduleResourceMock, $this->contextMock->getModuleResource());
    }

    /**
     * @test
     * that getBugsnagHelper returns bugsnag helper from internal property
     *
     * @covers ::getBugsnagHelper
     */
    public function getBugsnagHelper()
    {
        static::assertEquals($this->bugsnagHelperMock, $this->contextMock->getBugsnagHelper());
    }

    /**
     * @test
     * that getModuleManager returns module manager from internal property
     *
     * @covers ::getModuleManager
     */
    public function getModuleManager()
    {
        static::assertEquals($this->moduleManagerMock, $this->contextMock->getModuleManager());
    }

    /**
     * @test
     * that getSupportedModule returns supported module from internal property
     *
     * @covers ::getSupportedModule
     */
    public function getSupportedModule()
    {
        static::assertEquals(self::TEST_SUPPORTED_MODULE, $this->contextMock->getSupportedModule());
    }

    /**
     * @test
     * that getVersionFrom returns minimum version of the supported module that the plugin supports
     *
     * @covers ::getVersionFrom
     */
    public function getVersionFrom()
    {
        static::assertEquals(self::TEST_VERSION_FROM, $this->contextMock->getVersionFrom());
    }

    /**
     * @test
     * that getVersionTo returns maximum version of the supported module that the plugin supports
     *
     * @covers ::getVersionTo
     */
    public function getVersionTo()
    {
        static::assertEquals(self::TEST_VERSION_TO, $this->contextMock->getVersionTo());
    }
}
