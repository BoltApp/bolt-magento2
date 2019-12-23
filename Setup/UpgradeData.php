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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Setup;

use Bolt\Boltpay\Helper\FeatureSwitch\Manager;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\MetricsClient;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var Manager
     */
    protected $_fsManager;
    /**
     * @var LogHelper
     */
    protected $_logHelper;
    /**
     * @var MetricsClient
     */
    protected $_metricsClient;
    /**
     * @var BoltErrorResponse
     */
    protected $_errorResponse;

    public function __construct(
        Manager $fsManager,
        LogHelper $logHelper,
        MetricsClient $metricsClient,
        BoltErrorResponse $errorResponse
    ) {
        $this->_fsManager = $fsManager;
        $this->_logHelper = $logHelper;
        $this->_metricsClient = $metricsClient;
        $this->_errorResponse = $errorResponse;
    }

    /**
     * Called by magenot on module upgrades. We simply get new values for feature
     * switches from Bolt.
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $startTime = $this->_metricsClient->getCurrentTime();
        try {
            $this->_fsManager->updateSwitchesFromBolt();
        } catch (\Exception $e) {
            $encodedError =  $this->_errorResponse->prepareErrorMessage(
                BoltErrorResponse::ERR_SERVICE, $e->getMessage()
            );
            $this->_logHelper
                ->addInfoLog('UpgradeData: failed updating feature switches');
            $this->_logHelper->addInfoLog($encodedError);
            $this->_metricsClient
                ->processMetric(
                    "feature_switch.upgradedata.failure", 1,
                    "feature_switch.upgradedata.latency", $startTime
                );
            return;
        }
    }
}