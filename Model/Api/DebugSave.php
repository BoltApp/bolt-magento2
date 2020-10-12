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

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Helper\Config as ConfigHelper;

class DebugSave implements DebugSaveInterface
{
    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        ConfigHelper $configHelper
    ) {
        $this->configHelper = $configHelper;
    }
    
    /**
     * This request parse the debug info and map the configuration to the debug info's configuration.
     *
     * @return void
     * @param mixed $debug_info
     * @api
     */
    public function execute(
        $debug_info
    ) {
        # parse debug_info into array
        $debug_info_decoded = json_decode($debug_info, true);

        # extract bolt config data
        $config_data = $debug_info["pluginConfigSettings"];

        # TODO: Loop through config settings 
        # Don't set "api_key" or "signing_secret" since their values are not displayed in the debug info
        # $configHelper->setConfigSetting("configSettingName", configSettingValue)
    }
}