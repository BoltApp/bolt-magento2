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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\Config as AutomatedTestingConfig;

class DebugInfo implements \JsonSerializable
{
    /**
     * @var string
     */
    private $phpVersion;

    /**
     * @var string
     */
    private $composerVersion;

    /**
     * @var string
     */
    private $platformVersion;

    /**
     * @var BoltConfigSetting[]
     */
    private $boltConfigSettings;

    /**
     * @var PluginVersion[]
     */
    private $otherPluginVersions;

    /**
     * @var array
     */
    private $logs;

    /**
     * @var AutomatedTestingConfig
     */
    private $automatedTestingConfig;

    /**
     * @var array
     */
    private $catalogIngestionData;

    /**
     * @var array
     */
    private $featureSwitcherData;

    /**
     * @return string
     */
    public function getPhpVersion()
    {
        return $this->phpVersion;
    }

    /**
     * @param string $phpVersion
     *
     * @return $this
     */
    public function setPhpVersion($phpVersion)
    {
        $this->phpVersion = $phpVersion;
        return $this;
    }

    /**
     * @return string
     */
    public function getComposerVersion()
    {
        return $this->composerVersion;
    }

    /**
     * @param string $composerVersion
     *
     * @return $this
     */
    public function setComposerVersion($composerVersion)
    {
        $this->composerVersion = $composerVersion;
        return $this;
    }

    /**
     * @return string
     */
    public function getPlatformVersion()
    {
        return $this->platformVersion;
    }

    /**
     * @param string $platformVersion
     *
     * @return $this
     */
    public function setPlatformVersion($platformVersion)
    {
        $this->platformVersion = $platformVersion;
        return $this;
    }

    /**
     * @return \Bolt\Boltpay\Model\Api\Data\BoltConfigSetting[]
     */
    public function getBoltConfigSettings()
    {
        return $this->boltConfigSettings;
    }

    /**
     * @param \Bolt\Boltpay\Model\Api\Data\BoltConfigSetting[]
     *
     * @return $this
     */
    public function setBoltConfigSettings($boltConfigSettings)
    {
        $this->boltConfigSettings = $boltConfigSettings;
        return $this;
    }

    /**
     * @return \Bolt\Boltpay\Model\Api\Data\PluginVersion[]
     */
    public function getOtherPluginVersions()
    {
        return $this->otherPluginVersions;
    }

    /**
     * @param \Bolt\Boltpay\Model\Api\Data\PluginVersion[]
     *
     * @return $this
     */
    public function setOtherPluginVersions($otherPluginVersions)
    {
        $this->otherPluginVersions = $otherPluginVersions;
        return $this;
    }

    /**
     * @return array
     */
    public function getLogs()
    {
        return $this->logs;
    }

    /**
     * @param array $logs
     *
     * @return $this
     */

    public function setLogs($logs)
    {
        $this->logs = $logs;
        return $this;
    }

    /**
     * @return AutomatedTestingConfig
     */
    public function getAutomatedTestingConfig()
    {
        return $this->automatedTestingConfig;
    }

    /**
     * @param AutomatedTestingConfig $automatedTestingConfig
     *
     * @return $this
     */
    public function setAutomatedTestingConfig($automatedTestingConfig)
    {
        $this->automatedTestingConfig = $automatedTestingConfig;
        return $this;
    }

    /**
     * @param array $catalogIngestionData
     *
     * @return $this
     */
    public function setCatalogIngestionData($catalogIngestionData)
    {
        $this->catalogIngestionData = $catalogIngestionData;
        return $this;
    }

    /**
     * @return array
     */
    public function getCatalogIngestionData()
    {
        return $this->catalogIngestionData;
    }

    /**
     * @param array $featureSwitcherData
     *
     * @return $this
     */
    public function setFeatureSwitcherData($featureSwitcherData)
    {
        $this->featureSwitcherData = $featureSwitcherData;
        return $this;
    }

    /**
     * @return array
     */
    public function getFeatureSwitcherData()
    {
        return $this->featureSwitcherData;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            'php_version'              => $this->phpVersion,
            'composer_version'         => $this->composerVersion,
            'platform_version'         => $this->platformVersion,
            'bolt_config_settings'     => $this->boltConfigSettings,
            'other_plugin_versions'    => $this->otherPluginVersions,
            'logs'                     => $this->logs,
            'automated_testing_config' => $this->automatedTestingConfig,
            'catalog_ingestion'        => $this->catalogIngestionData,
            'feature_switches'         => $this->featureSwitcherData
        ]);
    }
}
