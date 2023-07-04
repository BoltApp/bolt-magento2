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

namespace Bolt\Boltpay\Controller\Adminhtml\System;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\UrlInterface;
use Magento\Config\Model\ResourceModel\Config as ResourceConfig;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Cache\TypeListInterface as Cache;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config as BoltConfigHelper;
use Bolt\Boltpay\Helper\IntegrationManagement;

class ProcessIntegrationToken extends Action
{
    /**
     * XML Path for Enable Integration as Bearer
     */
    const CONFIG_PATH_INTEGRATION_BEARER = 'oauth/consumer/enable_integration_as_bearer';
    
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;
    
    /**
     * @var Bolt\Boltpay\Helper\IntegrationManagement
     */
    protected $integrationManagement;
    
    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    protected $resourceConfig;
    
    /**
     * @var Bolt\Boltpay\Helper\Bugsnag
     */
    protected $bugsnag;
    
    /**
     * @var \Magento\Framework\App\Cache\TypeListInterface
     */
    protected $cache;
    
    /**
     * @var \Bolt\Boltpay\Helper\Config
     */
    protected $boltConfigHelper;
    
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param IntegrationManagement $integrationManagement
     * @param ResourceConfig $resourceConfig
     * @param Bugsnag $bugsnag
     * @param Cache $cache
     * @param BoltConfigHelper $boltConfigHelper
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        IntegrationManagement $integrationManagement,        
        ResourceConfig $resourceConfig,
        Bugsnag $bugsnag,
        Cache $cache,
        BoltConfigHelper $boltConfigHelper,
        UrlInterface $urlBuilder
    )
    {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->integrationManagement = $integrationManagement;
        $this->resourceConfig = $resourceConfig;
        $this->bugsnag = $bugsnag;
        $this->cache = $cache;
        $this->boltConfigHelper = $boltConfigHelper;
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context);
    }

    /**
     * Processing integration via Ajax.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        try {
            $storeId = $this->getRequest()->getParam('store_id');
            $integrationStatus = $this->integrationManagement->getIntegrationStatus($storeId);
            if ($integrationStatus != IntegrationManagement::BOLT_INTEGRATION_STATUS_EXPIRED) {
                // Create integration and initiate OAUTH handshake.
                if ($this->integrationManagement->linkAccessTokenBoltMerchantAccountByOAuth($storeId)) {
                    $boltMode = $this->boltConfigHelper->isSandboxModeSet($storeId) ? IntegrationManagement::BOLT_INTEGRATION_MODE_SANDBOX : IntegrationManagement::BOLT_INTEGRATION_MODE_PRODUCTION;
                    // Update related config.
                    $this->resourceConfig->saveConfig(BoltConfigHelper::XML_PATH_CONNECT_INTEGRATION_MODE, $boltMode);
                    if (version_compare($this->boltConfigHelper->getStoreVersion(), '2.4.4', '>=')) {
                        $this->resourceConfig->saveConfig(self::CONFIG_PATH_INTEGRATION_BEARER, true);
                    }
                    $this->cache->cleanType('config');
                    return $result->setData(['status' => 'success',
                                             'integration_mode' => $boltMode,
                                             'integration_status' => IntegrationManagement::BOLT_INTEGRATION_STATUS_CREATED]);
                } else {
                    return $result->setData(['status' => 'failure']);
                }
            } else {
                // If the integration is associated with improper Bolt mode,
                // (eg. the integration was created for Bolt sandbox mode, but now store has been switched to Bolt production mode.)
                // then delete the integration and reload page.
                if ($this->integrationManagement->deleteMagentoIntegraion()) {
                    return $result->setData(['status' => 'success',
                                             'integration_mode' => IntegrationManagement::BOLT_INTEGRATION_MODE_NONEXISTENT,
                                             'integration_status' => IntegrationManagement::BOLT_INTEGRATION_MODE_NONEXISTENT,
                                             'reload' => $this->urlBuilder->getUrl('adminhtml/system_config/edit', ['section' => 'payment'])]);
                } else {
                    return $result->setData(['status' => 'failure']);
                }
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->bugsnag->notifyException($e);
            return $result->setData(['status' => 'failure', 'errorMessage' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }      
    }
}