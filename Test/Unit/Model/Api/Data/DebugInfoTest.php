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

namespace Bolt\Boltpay\Test\Unit\Model\Api\Data;

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Model\Api\Data\DebugInfo;

/**
 * Class DebugInfoTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 */
class DebugInfoTest extends TestCase
{
    /**
     * @var \Bolt\Boltpay\Model\Api\Data\DebugInfo
     */
    protected $debugInfo;

    protected function setUp()
    {
        $this->debugInfo = new DebugInfo();
        $this->debugInfo->setPhpVersion('7.1');
        $this->debugInfo->setComposerVersion('composer_version');
        $this->debugInfo->setPlatformVersion('magento231');
        $this->debugInfo->setOtherPluginVersions('other');
        $this->debugInfo->setBoltConfigSettings('boltConfigSetting');
        $this->debugInfo->setLogs([]);
    }

    /**
     * @test
     */
    public function setAndGetPhpVersion()
    {
        $this->assertEquals('7.1', $this->debugInfo->getPhpVersion());
    }

    /**
     * @test
     */
    public function setAndGetComposerVersion()
    {
        $this->assertEquals('composer_version', $this->debugInfo->getComposerVersion());
    }

    /**
     * @test
     */
    public function setAndGetPlatformVersion()
    {
        $this->assertEquals('magento231', $this->debugInfo->getPlatformVersion());
    }

    /**
     * @test
     */
    public function setAndGetOtherPluginVersions()
    {
        $this->assertEquals('other', $this->debugInfo->getOtherPluginVersions());
    }

    /**
     * @test
     */
    public function setAndGetBoltConfigSettings()
    {
        $this->assertEquals('boltConfigSetting', $this->debugInfo->getBoltConfigSettings());
    }

    /**
     * @test
     */
    public function setAndGetLogs()
    {
        $this->assertEquals([], $this->debugInfo->getLogs());
    }

    /**
     * @test
     */
    public function jsonSerialize()
    {
        $this->assertEquals(
            [
                'bolt_config_settings' => 'boltConfigSetting',
                'logs' => [],
                'other_plugin_versions' => 'other',
                'php_version' => '7.1',
                'composer_version' => 'composer_version',
                'platform_version' => 'magento231',
            ],
            $this->debugInfo->jsonSerialize()
        );
    }
}
