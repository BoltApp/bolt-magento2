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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\ThirdPartySupport\AbstractPlugin
 */
class AbstractPluginTest extends TestCase
{
    /** @var string Test supported module */
    const TEST_SUPPORTED_MODULE = 'Vendor_SupportedModule';

    /** @var string Test minimum version of the supported module that the plugin supports */
    const TEST_VERSION_FROM = '1.0.0';

    /** @var string Test maximum version of the supported module that the plugin supports */
    const TEST_VERSION_TO = '9.9.9';

    /**
     * @var \Bolt\Boltpay\Plugin\ThirdPartySupport\CommonModuleContext|MockObject mocked instance of the context class
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
     * @var \Magento\Framework\Module\Manager|MockObject mocked instance of the Magento module manager
     */
    private $moduleManagerMock;

    /**
     * @var \Magento\Framework\Module\ResourceInterface|MockObject mocked instance of the Magento Module resource model
     */
    private $moduleResourceMock;

    /**
     * @var \Bolt\Boltpay\Plugin\ThirdPartySupport\AbstractPlugin|MockObject mocked instance of the tested class
     */
    private $currentMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->bugsnagHelperMock = $this->createMock(\Bolt\Boltpay\Helper\Bugsnag::class);
        $this->logHelperMock = $this->createMock(\Bolt\Boltpay\Helper\Log::class);
        $this->moduleManagerMock = $this->createMock(\Magento\Framework\Module\Manager::class);
        $this->moduleResourceMock = $this->createMock(\Magento\Framework\Module\ResourceInterface::class);
        $this->contextMock = $this->getMockBuilder(\Bolt\Boltpay\Plugin\ThirdPartySupport\CommonModuleContext::class)
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
            ->getMock();
        $this->currentMock = $this->getMockBuilder(\Bolt\Boltpay\Plugin\ThirdPartySupport\AbstractPlugin::class)
            ->setMethods()
            ->setConstructorArgs([$this->contextMock])
            ->getMockForAbstractClass();
    }

    /**
     * @test
     * that constuctor will populate properties using provided context's getter methods
     *
     * @covers ::__construct
     */
    public function __construct_always_configuresPropertyWithProvidedContext()
    {
        static::assertAttributeEquals($this->contextMock, 'context', $this->currentMock);
    }

    /**
     * @test
     * that shouldRun returns true only if the supported module is enabled and its version
     * is inside the configured threshold
     *
     * @dataProvider shouldRunDataProvider
     *
     * @covers ::shouldRun
     *
     * @param string      $moduleVersion supported module version
     * @param bool        $isModuleEnabled whether the supported module is enabled
     * @param string|null $versionFrom minimum version of the supported module that the plugin supports
     * @param string|null $versionTo maximum version of the supported module that the plugin supports
     * @param bool        $expectedResult of the method call
     *
     * @throws \ReflectionException if unable to set versionFrom or versionTo property
     */
    public function shouldRun_withVariousContextStates_determinesIfThePluginShouldRun(
        $moduleVersion,
        $isModuleEnabled,
        $versionFrom,
        $versionTo,
        $expectedResult
    ) {
        $this->contextMock->method('getVersionFrom')->willReturn($versionFrom);
        $this->contextMock->method('getVersionTo')->willReturn($versionTo);
        $this->contextMock->method('getSupportedModule')->willReturn(self::TEST_SUPPORTED_MODULE);
        $this->contextMock->method('getModuleResource')->willReturn($this->moduleResourceMock);
        $this->contextMock->method('getModuleManager')->willReturn($this->moduleManagerMock);
        $this->moduleResourceMock->expects(static::once())->method('getDataVersion')
            ->with(self::TEST_SUPPORTED_MODULE)->willReturn($moduleVersion);
        $this->moduleManagerMock->expects(static::once())->method('isEnabled')
            ->with(self::TEST_SUPPORTED_MODULE)->willReturn($isModuleEnabled);
        static::assertEquals($expectedResult, $this->currentMock->shouldRun());
    }

    /**
     * Data provider for {@see shouldRun_withVariousContextStates_determinesIfThePluginShouldRun}
     *
     * @return array containing supported module version,
     * whether the supported module is enabled,
     * minimum version of the supported module that the plugin supports,
     * maximum version of the supported module that the plugin supports,
     * and expected result of the method call
     */
    public function shouldRunDataProvider()
    {
        return [
            'Module enabled and no version limit - should return true'             => [
                'moduleVersion'   => '1.0.0',
                'isModuleEnabled' => true,
                'versionFrom'     => null,
                'versionTo'       => null,
                'expectedResult'  => true,
            ],
            'Module disabled and no version limit - should return false'           => [
                'moduleVersion'   => '1.0.0',
                'isModuleEnabled' => false,
                'versionFrom'     => null,
                'versionTo'       => null,
                'expectedResult'  => false,
            ],
            'Module enabled below version limit - should return false'             => [
                'moduleVersion'   => '1.0.0',
                'isModuleEnabled' => true,
                'versionFrom'     => '2.0.0',
                'versionTo'       => null,
                'expectedResult'  => false,
            ],
            'Module enabled above version limit - should return false'             => [
                'moduleVersion'   => '3.0.1',
                'isModuleEnabled' => true,
                'versionFrom'     => '2.0.0',
                'versionTo'       => '3.0.0',
                'expectedResult'  => false,
            ],
            'Module enabled and version equal to upper limit - should return true' => [
                'moduleVersion'   => '1.0.0',
                'isModuleEnabled' => true,
                'versionFrom'     => '0.0.1',
                'versionTo'       => '1.0.0',
                'expectedResult'  => true,
            ],
            'Module enabled and version equal to lower limit - should return true' => [
                'moduleVersion'   => '0.0.1',
                'isModuleEnabled' => true,
                'versionFrom'     => '0.0.1',
                'versionTo'       => '1.0.0',
                'expectedResult'  => true,
            ],
            'Module enabled and version between limit - should return true'        => [
                'moduleVersion'   => '0.5.3',
                'isModuleEnabled' => true,
                'versionFrom'     => '0.0.1',
                'versionTo'       => '1.0.0',
                'expectedResult'  => true,
            ],
        ];
    }

    /**
     * @test
     * that __get will provide properties from context if they are available, otherwise returns null
     *
     * @covers ::__get
     *
     * @dataProvider __get_withVariousPropertiesProvider
     *
     * @param string $propertyName of the property to be retrieved
     * @param mixed $expectedResult of the method call
     */
    public function __get_withVariousProperties_returnsPropertyFromContextIfAvailable($propertyName, $expectedResult)
    {
        $methodName = 'get' . ucfirst($propertyName);
        if (method_exists($this->contextMock, $methodName)) {
            $this->contextMock->method($methodName)->willReturn($expectedResult);
        }
        static::assertEquals($expectedResult, $this->currentMock->$propertyName);
    }

    /**
     * Data provider for {@see __get_withVariousProperties_returnsPropertyFromContextIfAvailable}
     *
     * @return array containing property name and expected result of the method call
     */
    public function __get_withVariousPropertiesProvider()
    {
        return [
            ['propertyName' => 'logHelper', 'expectedResult' => $this->logHelperMock],
            ['propertyName' => 'bugsnagHelper', 'expectedResult' => $this->bugsnagHelperMock],
            ['propertyName' => 'moduleManager', 'expectedResult' => $this->moduleManagerMock],
            ['propertyName' => 'moduleResource', 'expectedResult' => $this->moduleResourceMock],
            ['propertyName' => 'supportedModule', 'expectedResult' => self::TEST_SUPPORTED_MODULE],
            ['propertyName' => 'versionFrom', 'expectedResult' => self::TEST_VERSION_FROM],
            ['propertyName' => 'versionTo', 'expectedResult' => self::TEST_VERSION_TO],
            ['propertyName' => 'nonexistingproperty', 'expectedResult' => null],
        ];
    }
}
