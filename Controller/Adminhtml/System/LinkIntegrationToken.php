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

namespace Bolt\Boltpay\Controller\Adminhtml\System;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\IntegrationManagement;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Config\Model\ResourceModel\Config as ResourceConfig;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Cache\TypeListInterface as Cache;

class LinkIntegrationToken extends Action
{
    /**
     * @var Bolt\Boltpay\Helper\Api
     */
    protected $apiHelper;
    
    /**
     * @var Bolt\Boltpay\Helper\Config
     */
    protected $configHelper;
    
    /**
     * @var Bolt\Boltpay\Helper\Bugsnag
     */
    protected $bugsnag;
    
    /**
     * @var Bolt\Boltpay\Helper\IntegrationManagement
     */
    protected $integrationManagement;
    
    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    protected $resourceConfig;
    
    /**
     * @var DataObjectFactory
     */
    protected $dataObjectFactory;
    
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;
    
    /**
     * @var \Magento\Framework\App\Cache\TypeListInterface
     */
    protected $cache;

    /**
     * @param ApiHelper $apiHelper
     * @param Context $context
     * @param ConfigHelper $configHelper
     * @param Bugsnag $bugsnag
     * @param IntegrationManagement $integrationManagement
     * @param ResourceConfig $resourceConfig
     * @param DataObjectFactory $dataObjectFactory
     * @param JsonFactory $resultJsonFactory
     * @param Cache $cache
     */
    public function __construct(
        Context $context,
        ApiHelper $apiHelper,
        ConfigHelper $configHelper,
        Bugsnag $bugsnag,
        IntegrationManagement $integrationManagement, 
        ResourceConfig $resourceConfig,
        DataObjectFactory $dataObjectFactory,
        JsonFactory $resultJsonFactory,
        Cache $cache
    )
    {
        $this->apiHelper = $apiHelper;
        $this->configHelper = $configHelper;
        $this->bugsnag = $bugsnag;
        $this->integrationManagement = $integrationManagement;
        $this->resourceConfig = $resourceConfig;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cache = $cache;
        parent::__construct($context);
    }

    /**
     * Link access token of Magento integration to Bolt merchant account.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        try {
            $storeId = $this->getRequest()->getParam('store_id');
            if ($this->integrationManagement->linkAccessTokenBoltMerchantAccount($storeId)) {
                return $result->setData(['status' => 'success']);
            } else {
                return $result->setData(['status' => 'failure']);
            }
        } catch (LocalizedException $e) {
            $this->bugsnag->notifyException($e);
            return $result->setData(['status' => 'failure', 'errorMessage' => $e->getMessage()]);
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }      
    }
}
?>