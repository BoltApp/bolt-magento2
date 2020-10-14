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
use Bolt\Boltpay\Helper\Bugsnag;

class UpdateSettings implements UpdateSettingsInterface
{
    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param ConfigHelper $configHelper
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        ConfigHelper $configHelper,
        Bugsnag $bugsnag
    ) {
        $this->configHelper = $configHelper;
        $this->bugsnag = $bugsnag;
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
        # verify request
        $this->hookHelper->preProcessWebhook($this->storeManager->getStore()->getId());

        try {
            # parse debug_info into array
            $debug_info_decoded = json_decode($debug_info, true);

            # extract bolt config data
            $config_data = $debug_info["pluginConfigSettings"];

            # Don't set "api_key" or "signing_secret" since their values are not displayed in the debug info
            foreach ($config_data as $settingName => $settingValue) {
                if ($settingName == "api_key" || $settingName == "signing_secret") {
                    continue;
                }
                else {
                    $configHelper->setConfigSetting($settingName, $settingValue);
                }
            }
            
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
        
    }
}