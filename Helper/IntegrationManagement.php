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
use Magento\Integration\Model\Config\Converter as IntegrationConverter;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider as DeciderHelper;

/**
 * Boltpay IntegrationManagement helper
 *
 */
class IntegrationManagement extends AbstractHelper
{
    const BOLT_INTEGRATION_NAME = 'boltIntegration';
    
    const BOLT_INTEGRATION_AUTHENTICATION_ENDPOINT_URL_SANDBOX = 'https://example.com/sandbox/endpoint.php';
    const BOLT_INTEGRATION_IDENTITY_LINKING_URL_SANDBOX = 'https://example.com/sandbox/login.php';
    const BOLT_INTEGRATION_TOKEN_EXCHANGE_URL_SANDBOX = 'https://example.com/sandbox/tokensexchange.php';
    
    const BOLT_INTEGRATION_AUTHENTICATION_ENDPOINT_URL_PRODUCTION = 'https://example.com/production/endpoint.php';
    const BOLT_INTEGRATION_IDENTITY_LINKING_URL_PRODUCTION = 'https://example.com/production/login.php';
    const BOLT_INTEGRATION_TOKEN_EXCHANGE_URL_PRODUCTION = 'https://example.com/production/tokensexchange.php';
    
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
     * @var IntegrationOauthHelper
     */
    protected $dataHelper;
    
    /**
     * @var DeciderHelper
     */
    protected $deciderHelper;
    
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
     * @param DeciderHelper $deciderHelper
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
        IntegrationOauthHelper $dataHelper,
        DeciderHelper $deciderHelper
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
        $this->deciderHelper = $deciderHelper;
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
            if ($boltIntegration = $this->processMagentoIntegraion()) {
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
    public function processMagentoIntegraion()
    {
        $boltIntegration = null;
        try {
            $integrationConfigs = $this->integrationConfig->getIntegrations();
            if (isset($integrationConfigs[self::BOLT_INTEGRATION_NAME])) {
                $integrationData = [
                    IntegrationModel::NAME => self::BOLT_INTEGRATION_NAME,
                    IntegrationModel::EMAIL => $integrationConfigs[self::BOLT_INTEGRATION_NAME][IntegrationConverter::KEY_EMAIL],
                    IntegrationModel::ENDPOINT => $this->getEndpointUrl(),
                    IntegrationModel::IDENTITY_LINK_URL => $this->getIdentityLinkUrl(),
                    IntegrationModel::SETUP_TYPE => IntegrationModel::TYPE_CONFIG,
                ];
                $tmpIntegration = $this->integrationService->findByName(self::BOLT_INTEGRATION_NAME);
                if ($tmpIntegration->getId()) {
                    //If Integration already exists, update it.
                    $integrationData[IntegrationModel::ID] = $tmpIntegration->getId();
                    $this->integrationService->update($integrationData);
                } else {
                    $this->integrationService->create($integrationData);
                }
                $boltIntegration = $this->integrationService->findByName(self::BOLT_INTEGRATION_NAME);
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
        $boltIntegration = $this->processMagentoIntegraion();
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
                $this->httpClient->setUri($this->getTokensExchangeUrl());
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
    
    public function getEndpointUrl()
    {
        //Check for sandbox mode
        if ($this->deciderHelper->isIntegrationSandboxEnv()) {
            return self::BOLT_INTEGRATION_AUTHENTICATION_ENDPOINT_URL_SANDBOX;
        }
        return self::BOLT_INTEGRATION_AUTHENTICATION_ENDPOINT_URL_PRODUCTION;
    }
    
    public function getIdentityLinkUrl()
    {
        //Check for sandbox mode
        if ($this->deciderHelper->isIntegrationSandboxEnv()) {
            return self::BOLT_INTEGRATION_IDENTITY_LINKING_URL_SANDBOX;
        }
        return self::BOLT_INTEGRATION_IDENTITY_LINKING_URL_PRODUCTION;
    }
    
    public function getTokensExchangeUrl()
    {
        //Check for sandbox mode
        if ($this->deciderHelper->isIntegrationSandboxEnv()) {
            return self::BOLT_INTEGRATION_TOKEN_EXCHANGE_URL_SANDBOX;
        }
        return self::BOLT_INTEGRATION_TOKEN_EXCHANGE_URL_PRODUCTION;
    }
}
