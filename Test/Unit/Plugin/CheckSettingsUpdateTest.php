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

namespace Bolt\Boltpay\Test\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Magento\Config\Model\Config as MagentoConfig;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Manager as FeatureSwitchManager;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Plugin\CheckSettingsUpdate;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\CheckSettingsUpdate
 */
class CheckSettingsUpdateTest extends TestCase
{
    const OLD_KEY = 'old_key';
    const NEW_KEY = 'new_key';

    /**
     * @var CheckSettingsUpdate
     */
    private $currentMock;

    /**
     * @var MagentoConfig
     */

    private $magentoConfig;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /*
     * @var Manager
     */
    protected $fsManager;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    public function setUp()
    {
        /*
        $this->context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();*/

        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->fsManager = $this->createMock(FeatureSwitchManager::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->magentoConfig = $this->createMock(magentoConfig::class);

        $this->initCurrentMock([]);
    }

    /**
     * @param array $methods
     * @param bool $enableOriginalConstructor
     * @param bool $enableProxyingToOriginalMethods
     */
    private function initCurrentMock($methods = [])
    {
        $builder = $this->getMockBuilder(CheckSettingsUpdate::class)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->cartHelper,
                    $this->configHelper,
                    $this->fsManager,
                    $this->bugsnag,
                ]
            )
            ->setMethods($methods)
            ->enableProxyingToOriginalMethods();

        $this->currentMock = $builder->getMock();
    }

    private function callBeforeSaveAndAfterSave()
    {
        $beforeSaveResult = $this->currentMock->beforeSave($this->magentoConfig);
        $afterSaveResult = $this->currentMock->afterSave($this->magentoConfig, $this->magentoConfig);
        $this->assertNull($beforeSaveResult);
        $this->assertSame($this->magentoConfig, $afterSaveResult);
    }

    /**
     * @test
     */
    public function callBeforeSaveAndAfterSave_whenKeyNotChanged_doNothing()
    {
        $this->configHelper->expects($this->exactly(2))
            ->method('getApiKey')->willReturn(self::OLD_KEY);
        $this->fsManager->expects($this->never())
            ->method('updateSwitchesFromBolt');
        $this->callBeforeSaveAndAfterSave();
    }

    /**
     * @test
     */
    public function callBeforeSaveAndAfterSave_whenKeyChanged_updateSwitches()
    {
        $this->configHelper->expects($this->at(0))
            ->method('getApiKey')->willReturn(self::OLD_KEY);
        $this->configHelper->expects($this->at(1))
            ->method('getApiKey')->willReturn(self::NEW_KEY);
        $this->fsManager->expects($this->once())
            ->method('updateSwitchesFromBolt');
        $this->callBeforeSaveAndAfterSave();
    }

    /**
     * @test
     */
    public function callBeforeSaveAndAfterSave_whenKeyChangedToEmpty_doNothing()
    {
        $this->configHelper->expects($this->at(0))
            ->method('getApiKey')->willReturn(self::OLD_KEY);
        $this->configHelper->expects($this->at(1))
            ->method('getApiKey')->willReturn('');
        $this->fsManager->expects($this->never())
            ->method('updateSwitchesFromBolt');
        $this->callBeforeSaveAndAfterSave();
    }

    /**
     * @test
     */
    public function callBeforeSaveAndAfterSave_whenKeyChangedAnfUpdateSwitchesThrowException_callBugsnag()
    {
        $this->configHelper->expects($this->at(0))
            ->method('getApiKey')->willReturn(self::OLD_KEY);
        $this->configHelper->expects($this->at(1))
            ->method('getApiKey')->willReturn(self::NEW_KEY);
        $e = new \Exception('test exception');
        $this->fsManager->expects($this->once())
            ->method('updateSwitchesFromBolt')->willThrowException($e);
        $this->bugsnag->expects($this->once())
            ->method('notifyException')->with($e);
        $this->callBeforeSaveAndAfterSave();
    }
}
