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

namespace Bolt\Boltpay\Helper;

use Exception;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObjectFactory;
use Magento\Integration\Model\ConfigBasedIntegrationManager;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Model\Config as IntegrationConfig;
use Magento\Integration\Api\OauthServiceInterface as IntegrationOauthService;
use Magento\Integration\Model\Integration as IntegrationModel;
use Magento\Config\Model\ResourceModel\Config as ResourceConfig;
use Magento\Framework\App\Cache\TypeListInterface as Cache;
use Magento\Backend\Block\Template;
use Magento\Framework\HTTP\ZendClient;
use Magento\Integration\Helper\Oauth\Data as IntegrationOauthHelper;

/**
 * Boltpay IntegrationManagement helper
 *
 */
class IntegrationManagement extends AbstractHelper
{
    const BOLT_TOKEN_EXCHANGE_URL = 'https://xxx.xxx/tokensexchange.php';
    
    /**
     * @var \Magento\Integration\Model\ConfigBasedIntegrationManager
     */
    private $integrationManager;
    
    /**
     * @var \Magento\Integration\Api\IntegrationServiceInterface
     */
    protected $integrationService;
    
    /**
     * @var \Magento\Integration\Model\Config
     */
    protected $integrationConfig;
    
    /**
     * @var \Magento\Integration\Api\OauthServiceInterface
     */
    protected $oauthService;
    
    /**
     * @var Bolt\Boltpay\Helper\Bugsnag
     */
    protected $bugsnag;
    
    /**
     * @var Bolt\Boltpay\Helper\Config
     */
    protected $configHelper;
    
    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;
    
    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    protected $resourceConfig;
    
    /**
     * @var \Magento\Framework\App\Cache\TypeListInterface
     */
    protected $cache;
    
    /**
     * @var \Magento\Backend\Block\Template
     */
    protected $block;
    
    /**
     * @var \Magento\Framework\HTTP\ZendClient
     */
    protected $httpClient;
    
    /**
     * @var  IntegrationOauthHelper
     */
    protected $dataHelper;
    
    /**
     * @param Context $context
     * @param ConfigBasedIntegrationManager $integrationManager
     * @param IntegrationServiceInterface $integrationService
     * @param IntegrationConfig $integrationConfig
     * @param IntegrationOauthService $oauthService
     * @param Bugsnag $bugsnag
     * @param Config $configHelper
     * @param DataObjectFactory $dataObjectFactory
     * @param ResourceConfig $resourceConfig
     * @param Cache $cache
     * @param Template $block
     * @param ZendClient $httpClient
     */
    public function __construct(
        Context $context,
        ConfigBasedIntegrationManager $integrationManager,
        IntegrationServiceInterface $integrationService,
        IntegrationConfig $integrationConfig,
        IntegrationOauthService $oauthService,
        Bugsnag $bugsnag,
        Config $configHelper,
        DataObjectFactory $dataObjectFactory,
        ResourceConfig $resourceConfig,
        Cache $cache,
        Template $block,
        ZendClient $httpClient,
        IntegrationOauthHelper $dataHelper
    ) {
        parent::__construct($context);
        $this->integrationManager = $integrationManager;
        $this->integrationService = $integrationService;
        $this->integrationConfig = $integrationConfig;
        $this->oauthService = $oauthService;
        $this->bugsnag = $bugsnag;
        $this->configHelper = $configHelper;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->resourceConfig = $resourceConfig;
        $this->cache = $cache;
        $this->block = $block;
        $this->httpClient = $httpClient;
        $this->dataHelper = $dataHelper;
    }
    
    /**
     * Create an access token, tied to integration which has permissions to all API resources in the system.
     * Return the access token if already exists.
     *
     * @return string
     */
    public function createMagentoIntegraionToken()
    {
        $token = '';
        try {
            if ($boltIntegration = $this->createMagentoIntegraion()) {
                $consumerId = $boltIntegration->getConsumerId();
                $accessToken = $this->oauthService->getAccessToken($consumerId);
                if (empty($accessToken)) {
                    $this->integrationService->get($boltIntegration->getId());
                    $boltIntegration->setStatus(IntegrationModel::STATUS_ACTIVE);
                    $boltIntegration->save(); 
                    $this->oauthService->createAccessToken($consumerId, false);
                    $accessToken = $this->oauthService->getAccessToken($consumerId);
                }
                $token = $accessToken->getToken();
            }  
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
        } finally {
            return $token;
        }
    }
    
    /**
     * Get an access token, tied to integration which has permissions to all API resources in the system.
     *
     * @return string
     */
    public function getMagentoIntegraionToken()
    {
        $token = '';
        try {
            if ($consumerId = $this->getMagentoIntegraionConsumerId()) {
                $accessToken = $this->oauthService->getAccessToken($consumerId);
                if ($accessToken) {
                    $token = $accessToken->getToken();
                }
            }   
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
        } finally {
            return $token;
        }
    }
    
    /**
     * Get comsumer id of Magento integration.
     *
     * @return string
     */
    public function getMagentoIntegraionConsumerId()
    {
        $consumerId = '';
        try {
            if ($boltIntegration = $this->getMagentoIntegration()) {
                $consumerId = $boltIntegration->getConsumerId();
            }   
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
        } finally {
            return $consumerId;
        }
    }
    
    /**
     * Get id of Magento integration.
     *
     * @return int|null
     */
    public function getMagentoIntegraionId()
    {
        $integrationId = null;
        try {
            if ($boltIntegration = $this->getMagentoIntegration()) {
                $integrationId = (int)($boltIntegration->getId());
            }   
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
        } finally {
            return $integrationId;
        }
    }
    
    /**
     * Find Magento integration related to Bolt api.
     *
     * @return IntegrationModel|null
     */
    public function getMagentoIntegration()
    {
        $boltIntegration = null;
        try {
            $integrations = $this->integrationConfig->getIntegrations();
            if (isset($integrations['boltIntegration'])) {
                $boltIntegration = $this->integrationService->findByName('boltIntegration');
            }    
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
        } finally {
            return $boltIntegration;
        }
    }
    
    /**
     * Create Magento integration related to Bolt api.
     *
     * @return IntegrationModel|null
     */
    public function createMagentoIntegraion()
    {
        $boltIntegration = null;
        try {
            $integrations = $this->integrationConfig->getIntegrations();
            if (isset($integrations['boltIntegration'])) {
                // Process integrations from config files for the given array of integration names.
                // If Integration already exists, update it.
                $this->integrationManager->processIntegrationConfig(['boltIntegration']);
                $boltIntegration = $this->integrationService->findByName('boltIntegration');
            }    
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
        } finally {
            return $boltIntegration;
        }
    }
    
    /**
     * Link access token of Magento integration to Bolt merchant account via OAuth handshake.
     *
     * @param int|string $storeId
     */
    public function linkAccessTokenBoltMerchantAccountByOAuth()
    {
        $boltIntegration = $this->createMagentoIntegraion();
        if ($boltIntegration && $boltIntegration->getId()) {
            try {
                // Integration chooses to use Oauth for token exchange
                // Execute post to integration (consumer) HTTP Post URL (endpoint_url).
                $this->oauthService->postToConsumer($boltIntegration->getConsumerId(), $boltIntegration->getEndpoint());
                // Ignore calling the page defined in the Identity Link,
                // and send oauth_consumer_key and callback_url directly for token exchange.
                $consumer = $this->oauthService->loadConsumer($boltIntegration->getConsumerId());
                $loginSuccessCallback = $this->block->escapeJs(
                    $this->block->getUrl(
                        '*/*/loginSuccessCallback'
                    )
                );
                $this->httpClient->setUri(self::BOLT_TOKEN_EXCHANGE_URL);
                $this->httpClient->setParameterPost(
                    [
                        'oauth_consumer_key' => $consumer->getKey(),
                        'callback_url' => $loginSuccessCallback,
                    ]
                );
                $maxredirects = $this->dataHelper->getConsumerPostMaxRedirects();
                $timeout = $this->dataHelper->getConsumerPostTimeout();
                $this->httpClient->setConfig(['maxredirects' => $maxredirects, 'timeout' => $timeout]);
                $this->httpClient->request(\Magento\Framework\HTTP\ZendClient::POST);
            } catch (Exception $e) {
                $this->bugsnag->notifyException($e);
            }      
        }
    }
    
    /**
     * Link access token of Magento integration to Bolt merchant account.
     *
     * @param int|string $storeId
     */
    public function linkAccessTokenBoltMerchantAccount($storeId)
    {
        $apiKey = $this->configHelper->getApiKey($storeId);
        //Request Data
        $accessToken = $this->getMagentoIntegraionToken();
        if ($accessToken) {
            $requestData = $this->dataObjectFactory->create();
            $requestData->setApiData(['access_token' => $accessToken]);
            $requestData->setDynamicApiUrl(Api::API_CONFIG);
            $requestData->setApiKey($apiKey);
            //$request = $this->apiHelper->buildRequest($requestData);
            //$result = $this->apiHelper->sendRequest($request);

            // Save the integration id (non-zero value) into XML_PATH_LINK_INTEGRATION_FLAG for two purposes:
            // 1. Mark the access token has been linked to Bolt merchant account;
            // 2. When the associated Magento integration is deleted, if the flag value and the integration id are identical, then reset the flag.
            $this->resourceConfig->saveConfig(Config::XML_PATH_LINK_INTEGRATION_FLAG, $this->getMagentoIntegraionId());
            $this->cache->cleanType('config');
            return true;
        }
        
        return false;
    }
}
