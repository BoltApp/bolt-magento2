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

namespace Bolt\Boltpay\Helper;

use Exception;
use Magento\Config\Model\ResourceModel\Config as ResourceConfig;
use Magento\Framework\App\Cache\TypeListInterface as Cache;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObjectFactory;
use Bolt\Boltpay\Model\HttpClientAdapterFactory;
use Magento\Framework\UrlInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface as IntegrationOauthService;
use Magento\Integration\Block\Adminhtml\Integration\Edit\Tab\Info;
use Magento\Integration\Helper\Oauth\Data as IntegrationOauthHelper;
use Magento\Integration\Model\Config as IntegrationConfig;
use Magento\Integration\Model\Config\Converter as IntegrationConverter;
use Magento\Integration\Model\ConfigBasedIntegrationManager;
use Magento\Integration\Model\Integration as IntegrationModel;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider as DeciderHelper;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Boltpay IntegrationManagement helper
 *
 */
class IntegrationManagement extends AbstractHelper
{
    /**
     * Integration is not created yet
     */
    const BOLT_INTEGRATION_STATUS_NONEXISTENT = '0';

    /**
     * Integration is already created and in matched env
     */
    const BOLT_INTEGRATION_STATUS_CREATED = '1';

    /**
     * Integration is already created but in unmatched env
     */
    const BOLT_INTEGRATION_STATUS_EXPIRED = '2';


    /**
     * Integration is not created yet
     */
    const BOLT_INTEGRATION_MODE_NONEXISTENT = '0';

    /**
     * Integration is created for Bolt sandbox mode
     */
    const BOLT_INTEGRATION_MODE_SANDBOX = 'sandbox';

    /**
     * Integration is created for Bolt production mode
     */
    const BOLT_INTEGRATION_MODE_PRODUCTION = 'production';


    const BOLT_INTEGRATION_NAME = 'boltIntegration';

    const BOLT_INTEGRATION_AUTHENTICATION_ENDPOINT_URL = '/v1/magento2/bolt-checkout/authorize';
    const BOLT_INTEGRATION_IDENTITY_LINKING_URL = 'https://status.bolt.com/';
    const BOLT_INTEGRATION_TOKEN_EXCHANGE_URL = '/v1/magento2/bolt-checkout/exchange';

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
     * @var HttpClientAdapterFactory
     */
    protected $httpClientAdapterFactory;

    /**
     * @var \Magento\Integration\Helper\Oauth\Data
     */
    protected $dataHelper;

    /**
     * @var Bolt\Boltpay\Helper\FeatureSwitch\Decider
     */
    protected $deciderHelper;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

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
     * @param HttpClientAdapterFactory $httpClient
     * @param DeciderHelper $deciderHelper
     * @param UrlInterface $urlBuilder
     * @param StoreManagerInterface $storeManager
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
        HttpClientAdapterFactory $httpClientAdapterFactory,
        IntegrationOauthHelper $dataHelper,
        DeciderHelper $deciderHelper,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager
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
        $this->httpClientAdapterFactory = $httpClientAdapterFactory;
        $this->dataHelper = $dataHelper;
        $this->deciderHelper = $deciderHelper;
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
    }

    /**
     * Get an access token, tied to integration which has permissions to specific API resources in the system.
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
     * @return int|null
     */
    public function getMagentoIntegraionConsumerId()
    {
        $consumerId = null;
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
     * Find Magento integration related to Bolt API.
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
     * Delete Magento integration related to Bolt API.
     *
     * @return bool
     */
    public function deleteMagentoIntegraion()
    {
        try {
            if($integrationId = $this->getMagentoIntegraionId()) {
                $integrationData = $this->integrationService->delete($integrationId);
                if (!$integrationData[Info::DATA_ID]) {
                    throw new Exception(
                        __('The Bolt integration no longer exists.')
                    );
                } else {
                    //Integration deleted successfully, now safe to delete the associated consumer data
                    if (isset($integrationData[Info::DATA_CONSUMER_ID])) {
                        $this->oauthService->deleteConsumer($integrationData[Info::DATA_CONSUMER_ID]);
                    }
                    return true;
                }
            } else {
                throw new Exception(
                    __('The Bolt integration no longer exists.')
                );
            }
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            return false;
        }
    }

    /**
     * Create Magento integration for Bolt API.
     *
     * @return IntegrationModel|null
     */
    public function processMagentoIntegraion($storeId = null)
    {
        $boltIntegration = null;
        try {
            $integrationConfigs = $this->integrationConfig->getIntegrations();
            if (isset($integrationConfigs[self::BOLT_INTEGRATION_NAME])) {
                $integrationData = [
                    IntegrationModel::NAME => self::BOLT_INTEGRATION_NAME,
                    IntegrationModel::EMAIL => $integrationConfigs[self::BOLT_INTEGRATION_NAME][IntegrationConverter::KEY_EMAIL],
                    IntegrationModel::ENDPOINT => $this->getEndpointUrl($storeId),
                    IntegrationModel::IDENTITY_LINK_URL => $this->getIdentityLinkUrl($storeId),
                    IntegrationModel::SETUP_TYPE => IntegrationModel::TYPE_CONFIG,
                ];
                $boltIntegration = $this->integrationService->findByName(self::BOLT_INTEGRATION_NAME);
                if (!$boltIntegration->getId()) {
                    $boltIntegration = $this->integrationService->create($integrationData);
                } else {
                    $integrationData[IntegrationModel::ID] = $boltIntegration->getId();
                    $boltIntegration = $this->integrationService->update($integrationData);
                }
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
     *
     * @return bool
     */
    public function linkAccessTokenBoltMerchantAccountByOAuth($storeId = null)
    {
        $boltIntegration = $this->processMagentoIntegraion();
        if (!$boltIntegration || !$boltIntegration->getId()) {
            return false;
        }
        try {
            if ($this->getIntegrationStatus($storeId) == self::BOLT_INTEGRATION_STATUS_CREATED) {
                // Re-send API keys to Bolt
                // For security reason, we have to remove existing token associated with consumer and issue a new one
                $this->oauthService->deleteIntegrationToken($boltIntegration->getConsumerId());
                $boltIntegration->setStatus(IntegrationModel::STATUS_INACTIVE)->save();
            }
            // Integration chooses to use Oauth for token exchange
            // Execute post to integration (consumer) HTTP Post URL (endpoint_url).
            $this->oauthService->postToConsumer($boltIntegration->getConsumerId(), $boltIntegration->getEndpoint());
            // Ignore calling the page defined in the Identity Link,
            // and send oauth_consumer_key and callback_url directly for token exchange.
            $consumer = $this->oauthService->loadConsumer($boltIntegration->getConsumerId());
            if (!$consumer->getId()) {
                throw new Exception(
                    __(
                        'A consumer with "%1" ID doesn\'t exist. Verify the ID and try again.',
                        $boltIntegration->getConsumerId()
                    )
                );
            }

            return $this->prepareAndSendExchangeRequest($storeId, $consumer);
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            return false;
        }
    }

    /**
     * Prepare and send exchange request
     *
     * @throws Exception
     */
    private function prepareAndSendExchangeRequest($storeId, $consumer): bool
    {
        $errorLog = [];
        $callbackUrlArray = $this->configureCallbackUrl();
        foreach ($callbackUrlArray as $key => $callbackUrl) {
            $client = $this->httpClientAdapterFactory->create();
            $client->setUri($this->getTokensExchangeUrl($storeId));
            $client->setParameterPost(
                [
                    'oauth_consumer_key' => $consumer->getKey(),
                    'callback_url' => $callbackUrl,
                ]
            );
            $maxredirects = $this->dataHelper->getConsumerPostMaxRedirects();
            $timeout = $this->dataHelper->getConsumerPostTimeout();
            $client->setConfig(['maxredirects' => $maxredirects, 'timeout' => $timeout]);
            $response = $client->request('POST');
            if ( (method_exists($response, 'getStatus') && $response->getStatus() != 200) ||
                (method_exists($response, 'getStatusCode') && $response->getStatusCode() != 200)
            ) {
                $errorLog[$key . ' attempt'] = $response->getMessage();
            } else {
                return true;
            }
        }

        if (!empty($errorLog)) {
            throw new Exception(
                $this->prepareErrorMessage($errorLog)
            );
        }
        return false;
    }

    /**
     * Check if adminhtml base_url is different from FE base_url
     * and returning of FE urls in case of a difference, to avoid request errors
     *
     * @return array
     */
    public function configureCallbackUrl(): array
    {
        $loginSuccessCallbackArray = [];
        $adminBaseUrl = $this->urlBuilder->getUrl();
        foreach ($this->storeManager->getStores(true) as $store) {
            if (strpos($adminBaseUrl, $store->getBaseUrl()) === false) {
                $loginSuccessCallbackArray[] = $this->urlBuilder->getUrl(
                    '*/*/loginSuccessCallback',
                    ['_scope' => $store->getId()]
                );
            }
        }

        if (empty($loginSuccessCallbackArray)) {
            return [$this->urlBuilder->getUrl('*/*/loginSuccessCallback')];
        }

        return $loginSuccessCallbackArray;
    }

    /**
     * Prepare exchange error message
     *
     * @param $errorLog
     * @return \Magento\Framework\Phrase
     */
    private function prepareErrorMessage($errorLog): \Magento\Framework\Phrase
    {
        $errorDetails = "";
        foreach ($errorLog as $attempt => $errorMessage) {
            $errorDetails .= sprintf('%s: %s%s', $attempt, $errorMessage, PHP_EOL);
        }

        return __(
            'An error occurred while processing token exchange: "%1".',
            $errorDetails
        );
    }

    /**
     * get callback URL.
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getEndpointUrl($storeId = null)
    {
        return rtrim($this->configHelper->getIntegrationBaseUrl($storeId), '/') . self::BOLT_INTEGRATION_AUTHENTICATION_ENDPOINT_URL;
    }

    /**
     * get identity link URL.
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getIdentityLinkUrl($storeId = null)
    {
        return self::BOLT_INTEGRATION_IDENTITY_LINKING_URL;
    }

    /**
     * get token exchange URL.
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getTokensExchangeUrl($storeId = null)
    {
        return rtrim($this->configHelper->getIntegrationBaseUrl($storeId), '/') . self::BOLT_INTEGRATION_TOKEN_EXCHANGE_URL;
    }

    /**
     * get current status of Magento integration associated with Bolt API.
     * '0' - Integration is not created yet
     * '1' - Integration is already created and in matched env
     * '2' - Integration is already created but in unmatched env
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getIntegrationStatus($storeId = null)
    {
        $integrationMode = $this->configHelper->getConnectIntegrationMode();
        $boltMode = $this->configHelper->isSandboxModeSet($storeId) ? self::BOLT_INTEGRATION_MODE_SANDBOX : self::BOLT_INTEGRATION_MODE_PRODUCTION;
        return empty($integrationMode) ? self::BOLT_INTEGRATION_STATUS_NONEXISTENT : ($integrationMode == $boltMode ? self::BOLT_INTEGRATION_STATUS_CREATED : self::BOLT_INTEGRATION_STATUS_EXPIRED);
    }
}
