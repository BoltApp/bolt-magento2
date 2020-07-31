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

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\LogRetriever;
use Bolt\Boltpay\Helper\ModuleRetriever;
use Bolt\Boltpay\Model\Api\Data\BoltConfigSetting;
use Bolt\Boltpay\Model\Api\Data\BoltConfigSettingFactory;
use Bolt\Boltpay\Model\Api\Data\DebugInfo;
use Bolt\Boltpay\Model\Api\Data\DebugInfoFactory;
use Bolt\Boltpay\Model\Api\Data\PluginVersion;
use Bolt\Boltpay\Model\Api\Debug;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManager;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class CreateOrderTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\Debug
 */
class DebugTest extends TestCase
{
    /**
     * @var Debug
     */
    private $debug;

    /**
     * @var Response
     */
    private $responseMock;

    /**
     * @var DebugInfoFactory
     */
    private $debugInfoFactoryMock;

    /**
     * @var BoltConfigSettingFactory
     */
    private $boltConfigSettingFactoryMock;

    /**
     * @var ModuleRetriever
     */
    private $moduleRetrieverMock;

    /**
     * @var LogRetriever
     */
    private $logRetrieverMock;

    /**
     * @var StoreManagerInterface
     */
    private $storeManagerInterfaceMock;

    /**
     * @var HookHelper
     */
    private $hookHelperMock;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadataInterfaceMock;

    /**
     * @var ConfigHelper
     */
    private $configHelperMock;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        // prepare response
        $this->responseMock = $this->createMock(Response::class);

        // prepare debug info factory
        $this->debugInfoFactoryMock = $this->createMock(DebugInfoFactory::class);
        $this->debugInfoFactoryMock->method('create')->willReturn(new DebugInfo());

        // prepare bolt config setting factory
        $this->boltConfigSettingFactoryMock = $this->createMock(BoltConfigSettingFactory::class);
        $this->boltConfigSettingFactoryMock->method('create')->willReturnCallback(function () {
            return new BoltConfigSetting();
        });

        // prepare store manager
        $storeInterfaceMock = $this->createMock(StoreInterface::class);
        $storeInterfaceMock->method('getId')->willReturn(0);
        $this->storeManagerInterfaceMock = $this->createMock(StoreManagerInterface::class);
        $this->storeManagerInterfaceMock->method('getStore')->willReturn($storeInterfaceMock);

        // prepare product meta data
        $this->productMetadataInterfaceMock = $this->createMock(ProductMetadataInterface::class);
        $this->productMetadataInterfaceMock->method('getVersion')->willReturn('2.3.0');

        // prepare hook helper
        $this->hookHelperMock = $this->createMock(HookHelper::class);
        $this->hookHelperMock->method('preProcessWebhook');

        // prepare config helper
        $this->configHelperMock = $this->createMock(ConfigHelper::class);
        $this->prepareConfigHelperMock();

        // prepare module retriever
        $this->moduleRetrieverMock = $this->createMock(ModuleRetriever::class);
        $this->moduleRetrieverMock->method('getInstalledModules')->willReturn(
            [
                (new PluginVersion())->setName('plugin1')->setVersion('1.0.0'),
                (new PluginVersion())->setName('plugin2')->setVersion('2.0.0'),
                (new PluginVersion())->setName('plugin3')->setVersion('3.0.0')
            ]
        );

        // prepare log retriever
        $this->logRetrieverMock = $this->createMock(LogRetriever::class);
        $this->logRetrieverMock->method('getLogs')->willReturn(
            [['Line 1 of log'], ['Line 2 of log']]
        );

        // initialize test object
        $objectManager = new ObjectManager($this);
        $this->debug = $objectManager->getObject(
            Debug::class,
            [
                'response' => $this->responseMock,
                'debugInfoFactory' => $this->debugInfoFactoryMock,
                'boltConfigSettingFactory' => $this->boltConfigSettingFactoryMock,
                'storeManager' => $this->storeManagerInterfaceMock,
                'hookHelper' => $this->hookHelperMock,
                'productMetadata' => $this->productMetadataInterfaceMock,
                'configHelper' => $this->configHelperMock,
                'moduleRetriever' => $this->moduleRetrieverMock,
                'logRetriever' => $this->logRetrieverMock
            ]
        );
    }

    private function prepareConfigHelperMock()
    {
        $boltSettings = [];
        $boltSettings[] = $this->boltConfigSettingFactoryMock->create()
                                                             ->setName('config_name1')
                                                             ->setValue('config_value1');
        $boltSettings[] = $this->boltConfigSettingFactoryMock->create()
                                                             ->setName('config_name2')
                                                             ->setValue('config_value2');
        $this->configHelperMock->method('getAllConfigSettings')->willReturn($boltSettings);
        $this->configHelperMock->method('getComposerVersion')->willReturn('composer_version');
    }

    /**
     * @test
     * @covers ::debug
     */
    public function debug_successful()
    {
        $this->hookHelperMock->expects($this->once())->method('preProcessWebhook');
        $this->responseMock->expects($this->once())->method('sendResponse');

        $expectedJson = json_encode([
            'status' => 'success',
            'event' => 'integration.debug',
            'data' => [
                'php_version' => PHP_VERSION,
                'composer_version' => 'composer_version',
                'platform_version' => '2.3.0',
                'bolt_config_settings' => [
                    [
                        'name' => 'config_name1',
                        'value' => 'config_value1'
                    ],
                    [
                        'name' => 'config_name2',
                        'value' => 'config_value2'
                    ]
                ],
                'other_plugin_versions' => [
                    [
                        'name' => 'plugin1',
                        'version' => '1.0.0'
                    ],
                    [
                        'name' => 'plugin2',
                        'version' => '2.0.0'
                    ],
                    [
                        'name' => 'plugin3',
                        'version' => '3.0.0'
                    ]
                ],
                'logs' => [
                    ['Line 1 of log'],
                    ['Line 2 of log']
                ]
            ]

        ]);

        $this->responseMock->expects($this->once())->method('setBody')->with($this->equalTo($expectedJson));
        $this->debug->debug();
    }
}
