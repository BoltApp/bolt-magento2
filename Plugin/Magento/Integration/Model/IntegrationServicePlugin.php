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

namespace Bolt\Boltpay\Plugin\Magento\Integration\Model;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config as BoltConfigHelper;
use Bolt\Boltpay\Helper\IntegrationManagement;
use Magento\Config\Model\ResourceModel\Config as ResourceConfig;
use Magento\Framework\App\Cache\TypeListInterface as Cache;
use Magento\Integration\Block\Adminhtml\Integration\Edit\Tab\Info;

/**
 * Plugin for {@see \Magento\Integration\Model\OauthService} used to delete Integration
 */
class IntegrationServicePlugin
{    
    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    protected $resourceConfig;

    /**
     * @var \Magento\Framework\App\Cache\TypeListInterface
     */
    protected $cache;
    
    /**
     * @var \Bolt\Boltpay\Helper\Bugsnag
     */
    protected $bugsnag;
    
    /**
     * @param ResourceConfig $resourceConfig
     * @param Cache $cache
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        ResourceConfig $resourceConfig,
        Bugsnag $bugsnag,
        Cache $cache
    )
    {
        $this->resourceConfig = $resourceConfig;
        $this->cache = $cache;
        $this->bugsnag = $bugsnag;
    }

    /**
     * Mark Bolt associated integration as "not created" when deleting an Integration.
     *
     * @see \Magento\Integration\Model\IntegrationService::delete
     *
     * @param \Magento\Integration\Model\IntegrationService $subject the frontend controller wrapped by this plugin
     * @param array $result Integration data
     * @param int $integrationId
     * @return array Integration data
     */
    public function afterDelete(\Magento\Integration\Model\IntegrationService $subject, $result, $integrationId)
    {
        try {
            if (isset($result[Info::DATA_ID])
                && $result[Info::DATA_ID]
                && isset($result[Info::DATA_NAME])
                && $result[Info::DATA_NAME] == IntegrationManagement::BOLT_INTEGRATION_NAME) {
                $this->resourceConfig->saveConfig(BoltConfigHelper::XML_PATH_CONNECT_INTEGRATION_MODE, IntegrationManagement::BOLT_INTEGRATION_MODE_NONEXISTENT);
                $this->cache->cleanType('config');
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        } finally {
            return $result;
        }
    }
}
