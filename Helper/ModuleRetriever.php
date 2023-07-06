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

namespace Bolt\Boltpay\Helper;

use Magento\Framework\Module\FullModuleList;
use Magento\Framework\App\ResourceConnection;
use Bolt\Boltpay\Model\Api\Data\PluginVersionFactory;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Magento\Framework\Module\PackageInfo;

class ModuleRetriever
{
    private const UNDEFINED_MODULE_VERSION = 'undefined';

    /**
     * @var ResourceConnection $resource
     */
    private $resource;

    /**
     * @var Decider
     */
    private $featureSwitches;

    /**
     * @var PluginVersionFactory
     */
    private $pluginVersionFactory;

    /**
     * @var FullModuleList
     */
    private $fullModuleList;

    /**
     * @var PackageInfo
     */
    private $packageInfo;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param Decider $featureSwitches
     * @param ResourceConnection $resource
     * @param PluginVersionFactory $pluginVersionFactory
     * @param FullModuleList $fullModuleList
     * @param PackageInfo $packageInfo
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        Decider $featureSwitches,
        ResourceConnection $resource,
        PluginVersionFactory $pluginVersionFactory,
        FullModuleList $fullModuleList,
        PackageInfo $packageInfo,
        Bugsnag $bugsnag
    ) {
        $this->featureSwitches = $featureSwitches;
        $this->resource = $resource;
        $this->pluginVersionFactory = $pluginVersionFactory;
        $this->fullModuleList = $fullModuleList;
        $this->packageInfo = $packageInfo;
        $this->bugsnag = $bugsnag;
    }

    public function getInstalledModules()
    {
        $installedModules = [];
        try {
            $isUseModulesListFromDatabase = $this->featureSwitches->isEnabledModuleRetrieverFromSetupModuleTable();
            $modulesList = ($isUseModulesListFromDatabase)
                ? $this->getModulesListFromSetupModuleTable()
                : $this->getModulesList();
            foreach ($modulesList as $module) {
                $moduleName = $this->getModuleName($module, $isUseModulesListFromDatabase);
                $moduleVersion = ($isUseModulesListFromDatabase)
                    ? $this->getModuleVersion($module, $isUseModulesListFromDatabase)
                    : $this->packageInfo->getVersion($moduleName);
                $plugin = $this->pluginVersionFactory->create()
                    ->setName($moduleName)
                    ->setVersion($moduleVersion);
                $installedModules[] = $plugin;
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        } finally {
            return $installedModules;
        }
    }

    /**
     * Returns module version
     *
     * @param array $module
     * @param bool $isUseModulesListFromDatabase
     * @return string
     */
    private function getModuleVersion(array $module, bool $isUseModulesListFromDatabase): string
    {
        if ($isUseModulesListFromDatabase) {
            return $module['schema_version'];
        }
        return ($module['setup_version']) ?: self::UNDEFINED_MODULE_VERSION;
    }

    /**
     * Returns module name
     *
     * @param array $module
     * @param bool $isUseModulesListFromDatabase
     * @return string
     */
    private function getModuleName(array $module, bool $isUseModulesListFromDatabase): string
    {
        return ($isUseModulesListFromDatabase) ? $module['module'] : $module['name'];
    }

    /**
     * Returns modules list by CORE methods
     *
     * @return array|string[]
     */
    private function getModulesList(): array
    {
        return $this->fullModuleList->getAll();
    }

    /**
     * Returns modules list from `setup_module` table
     *
     * @return array
     */
    private function getModulesListFromSetupModuleTable(): array
    {
        $connection = $this->resource->getConnection();
        return $connection->fetchAll('SELECT module, schema_version FROM setup_module');
    }
}
