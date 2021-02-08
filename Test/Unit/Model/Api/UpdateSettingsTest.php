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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Model\Api\UpdateSettings;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Bugsnag as Bugsnag;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Model\Api\Data\BoltConfigSetting;
use Bolt\Boltpay\Model\Api\Data\BoltConfigSettingFactory;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Bolt\Boltpay\Test\Unit\BoltTestCase;

/**
 * Class CreateOrderTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\Debug
 */
class UpdateTest extends BoltTestCase
{
    /**
     * @var UpdateSettings
     */
    private $updateSettings;

    /**
     * @var Response
     */
    private $responseMock;

    /**
     * @var BoltConfigSettingFactory
     */
    private $boltConfigSettingFactoryMock;

    /**
     * @var ModuleRetriever
     */
    private $moduleRetrieverMock;

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
    public function setUpInternal()
    {
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
        $localConfigHelperMock = $this->createMock(ConfigHelper::class);
        $localConfigHelperMock->setting = "initial_setting";
        // Mock setter
        $localConfigHelperMock->method('setConfigSetting')->willReturnCallback(
            function($settingName, $settingValue) use ($localConfigHelperMock) {
                $localConfigHelperMock->setting = $settingValue;
            }
        );
        // Mock getter
        $localConfigHelperMock->method('getPublishableKeyCheckout')->willReturnCallback(
            function($arg) use ($localConfigHelperMock) {
                return $localConfigHelperMock->setting;
            }
        );

        $this->configHelperMock = $localConfigHelperMock;

        $this->bugsnag = $this->getMockBuilder(Bugsnag::class)
            ->setMethods(['notifyException', 'notifyError', 'registerCallback'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->bugsnag->method('notifyException')
            ->willReturnSelf();

        // initialize test object
        $objectManager = new ObjectManager($this);
        $this->updateSettings = $objectManager->getObject(
            UpdateSettings::class,
            [
                'configHelper' => $this->configHelperMock,
                'bugsnag' => $this->bugsnag,
                'hookHelper' => $this->hookHelperMock,
                'storeManager' => $this->storeManagerInterfaceMock
            ]
        );
    }

    /**
     * @test
     * @covers ::debug
     */
    public function update_settings_successful()
    {
        $mockDebugInfo = json_encode([
            'division' => [
                'pluginIntegrationInfo' => [
                    'phpVersion' => PHP_VERSION,
                    'platformVersion' => '2.3.3',
                    'pluginConfigSettings' => [
                        [
                            'name' => 'publishable_key_checkout',
                            'value' => 'test_key'
                        ]
                    ]
                ]
            ]
        ]);

        $this->hookHelperMock->expects($this->once())->method('preProcessWebhook');
        $this->updateSettings->execute($mockDebugInfo);

        $result = $this->configHelperMock->getPublishableKeyCheckout(0);
        $this->assertEquals("test_key", $result);
    }
}
