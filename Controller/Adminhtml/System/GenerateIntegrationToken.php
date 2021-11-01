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
use Bolt\Boltpay\Helper\IntegrationManagement;
use Bolt\Boltpay\Helper\Config as BoltConfig;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\Cache\TypeListInterface as Cache;

class GenerateIntegrationToken extends Action
{
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
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Data $helper
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        IntegrationManagement $integrationManagement,        
        Config $resourceConfig,
        Bugsnag $bugsnag,
        Cache $cache
    )
    {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->integrationManagement = $integrationManagement;
        $this->resourceConfig = $resourceConfig;
        $this->bugsnag = $bugsnag;
        $this->cache = $cache;
        parent::__construct($context);
    }

    /**
     * Collect relations data
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        try {
            $accessToken = $this->integrationManagement->createMagentoIntegraionToken();
            if ($accessToken) {
                $this->resourceConfig->saveConfig(BoltConfig::XML_PATH_INTEGRATION_TOKEN, $accessToken);
                $this->cache->cleanType('config');
                return $result->setData(['status' => 'success', 'accesstoken' => $accessToken]);
            } else {
                return $result->setData(['status' => 'failure', 'accesstoken' => '']);
            }
        } catch (LocalizedException $e) {
            $this->bugsnag->notifyException($e);
            return $result->setData(['status' => 'failure', 'accesstoken' => '', 'errorMessage' => $e->getMessage()]);
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }      
    }
}
?>