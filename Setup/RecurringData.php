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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Setup;

use Bolt\Boltpay\Helper\FeatureSwitch\Manager;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Bolt\Boltpay\Helper\IntegrationManagement;

class RecurringData implements InstallDataInterface
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

    /**
     * @var IntegrationManagement
     */
    protected $_integrationManagement;

    /**
     * @param Manager $fsManager
     * @param LogHelper $logHelper
     * @param MetricsClient $metricsClient
     * @param BoltErrorResponse $errorResponse
     * @param IntegrationManagement $integrationManagement
     */
    public function __construct(
        Manager $fsManager,
        LogHelper $logHelper,
        MetricsClient $metricsClient,
        BoltErrorResponse $errorResponse,
        IntegrationManagement $integrationManagement
    ) {
        $this->_fsManager = $fsManager;
        $this->_logHelper = $logHelper;
        $this->_metricsClient = $metricsClient;
        $this->_errorResponse = $errorResponse;
        $this->_integrationManagement = $integrationManagement;
    }

    /**
     * Called by magento on module upgrades. We simply get new values for feature
     * switches from Bolt.
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface   $context
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $startTime = $this->_metricsClient->getCurrentTime();
        try {
            $this->_integrationManagement->syncExistingIntegrationAclResources();
            $this->_fsManager->updateSwitchesFromBolt();
        } catch (\Exception $e) {
            $encodedError = $this->_errorResponse->prepareErrorMessage(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage()
            );
            $this->_logHelper->addInfoLog('RecurringData: failed updating');
            $this->_logHelper->addInfoLog($encodedError);
            $this->_metricsClient->processMetric(
                'bolt.recurring.failure',
                1,
                'bolt.recurring.latency',
                $startTime
            );
            return;
        }
    }
}
