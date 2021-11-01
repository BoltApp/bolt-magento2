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

use Bolt\Boltpay\Helper\IntegrationManagement;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\Cache\TypeListInterface as Cache;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Plugin for {@see \Magento\Integration\Model\OauthService} used to reauthorize Integration Tokens
 */
class OauthServicePlugin
{
    /**
     * @var Bolt\Boltpay\Helper\IntegrationManagement
     */
    protected $integrationManagementHelper;
    
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
     * @var  \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * @var Bolt\Boltpay\Helper\Config
     */
    protected $configHelper;
    
    /**
     * @param BIntegrationManagement $integrationManagementHelper
     * @param Config $resourceConfig
     * @param Cache $cache
     * @param Bugsnag $bugsnag
     * @param StoreManagerInterface $storeManager
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        IntegrationManagement $integrationManagementHelper,
        Config $resourceConfig,
        Bugsnag $bugsnag,
        Cache $cache,
        StoreManagerInterface $storeManager,
        ConfigHelper $configHelper
    )
    {
        $this->integrationManagementHelper = $integrationManagementHelper;
        $this->resourceConfig = $resourceConfig;
        $this->cache = $cache;
        $this->bugsnag = $bugsnag;
        $this->storeManager = $storeManager;
        $this->configHelper = $configHelper;
    }

    /**
     * Save new access token when the integration is reauthorized.
     * 
     * @see \Magento\Integration\Model\OauthService::createAccessToken
     *
     * @param \Magento\Integration\Model\OauthService $subject the frontend controller wrapped by this plugin
     * @param bool $result If token was created
     * @param int $consumerId
     * @param bool $clearExistingToken
     *
     * @return bool original result of the wrapped method
     */
    public function afterCreateAccessToken(\Magento\Integration\Model\OauthService $subject, $result, $consumerId, $clearExistingToken)
    {       
        try {
            if ($clearExistingToken && $consumerId === $this->integrationManagementHelper->getMagentoIntegraionConsumerId()) {
                $accessToken = $this->integrationManagementHelper->getMagentoIntegraionToken();
                $this->resourceConfig->saveConfig(ConfigHelper::XML_PATH_INTEGRATION_TOKEN, $accessToken);
                $this->cache->cleanType('config');
                // If the access token is already linked to Bolt merchant account,
                // we need to resend it to Bolt after reauthorization.
                if ($this->configHelper->getLinkIntegrationFlag()) {
                    $storeId = $this->storeManager->getStore()->getId();
                    $this->integrationManagementHelper->linkAccessTokenBoltMerchantAccount($storeId);
                }
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        } finally {
            return $result;
        }
    }
}
