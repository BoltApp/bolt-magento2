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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Plugin\Magento\Integration\Model;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\Cache\TypeListInterface as Cache;
use Magento\Integration\Block\Adminhtml\Integration\Edit\Tab\Info;

/**
 * Plugin for {@see \Magento\Integration\Model\OauthService} used to reauthorize Integration Tokens
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
     * @var Bolt\Boltpay\Helper\Bugsnag
     */
    protected $bugsnag;
    
    /**
     * @var Bolt\Boltpay\Helper\Config
     */
    protected $configHelper;
    
    /**
     * @param Config $resourceConfig
     * @param Cache $cache
     * @param Bugsnag $bugsnag
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        Config $resourceConfig,
        Bugsnag $bugsnag,
        Cache $cache,
        ConfigHelper $configHelper
    )
    {
        $this->resourceConfig = $resourceConfig;
        $this->cache = $cache;
        $this->bugsnag = $bugsnag;
        $this->configHelper = $configHelper;
    }

    /**
     * Delete an Integration.
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
            if (isset($result[Info::DATA_ID]) && $result[Info::DATA_ID] && $integrationId === (int)($this->configHelper->getLinkIntegrationFlag())) {
                // Reset the flag after the integration is deleted.
                $this->resourceConfig->saveConfig(ConfigHelper::XML_PATH_LINK_INTEGRATION_FLAG, 0);
                $this->cache->cleanType('config');
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        } finally {
            return $result;
        }
    }
}
