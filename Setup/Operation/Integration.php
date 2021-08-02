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

namespace Bolt\Boltpay\Setup\Operation;

use Magento\Integration\Model\ConfigBasedIntegrationManager;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Model\Config as IntegrationConfig;
use Magento\Integration\Api\OauthServiceInterface;
use Magento\Integration\Model\Integration as IntegrationModel;

class Integration
{
     /**
     * @var ConfigBasedIntegrationManager
     */
    private $integrationManager;
    
    /**
     * @var IntegrationServiceInterface
     */
    protected $integrationService;
    
    /**
     * @var IntegrationConfig
     */
    protected $integrationConfig;
    
    /**
     * @var OauthServiceInterface
     */
    private $oauthService;
    
    /**
     * @param ConfigBasedIntegrationManager $integrationManager
     */
    public function __construct(
        ConfigBasedIntegrationManager $integrationManager,
        IntegrationServiceInterface $integrationService,
        IntegrationConfig $integrationConfig,
        OauthServiceInterface $oauthService
    ) {
        $this->integrationManager = $integrationManager;
        $this->integrationService = $integrationService;
        $this->integrationConfig = $integrationConfig;
        $this->oauthService = $oauthService;
    }
    
    public function createMagentoIntegraion()
    {
        $integrations = $this->integrationConfig->getIntegrations();
        if (isset($integrations['boltIntegration'])) {
            $this->integrationManager->processIntegrationConfig(['boltIntegration']);
            $integration = $this->integrationService->findByName('boltIntegration');
            if ($integration) {
                $consumerId = $integration->getConsumerId();
                $accessToken = $this->oauthService->getAccessToken($consumerId);
                if (!$accessToken && $this->oauthService->createAccessToken($consumerId, true)) {
                    $accessToken = $this->oauthService->getAccessToken($consumerId);
                }
                $this->integrationService->get($integration->getId());
                $integration->setStatus(IntegrationModel::STATUS_ACTIVE);
                $integration->save();    
            }
        }
    }
}
