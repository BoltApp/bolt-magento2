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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Helper;

use Magento\Framework\Module\FullModuleList;
use Bolt\Boltpay\Model\Api\Data\PluginVersionFactory;

class ModuleRetriever
{
    private const UNDEFINED_MODULE_VERSION = 'undefined';

    /**
     * @var PluginVersionFactory
     */
    private $pluginVersionFactory;

    /**
     * @var FullModuleList
     */
    private $fullModuleList;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param PluginVersionFactory $pluginVersionFactory
     * @param FullModuleList $fullModuleList
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        PluginVersionFactory $pluginVersionFactory,
        FullModuleList $fullModuleList,
        Bugsnag $bugsnag
    ) {
        $this->pluginVersionFactory = $pluginVersionFactory;
        $this->fullModuleList = $fullModuleList;
        $this->bugsnag = $bugsnag;
    }

    public function getInstalledModules()
    {
        $installedModules = [];
        try {
            foreach ($this->fullModuleList->getAll() as $module) {
                $pluginName = $module['name'];
                $pluginVersion = ($module['setup_version']) ?: self::UNDEFINED_MODULE_VERSION;
                $plugin = $this->pluginVersionFactory->create()
                    ->setName($pluginName)
                    ->setVersion($pluginVersion);
                $installedModules[] = $plugin;
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        } finally {
            return $installedModules;
        }
    }
}
