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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\DebugInterface;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\ModuleRetriever;
use Bolt\Boltpay\Model\Api\Data\DebugInfoFactory;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\FullModuleList;
use Bolt\Boltpay\Helper\LogRetriever;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Model\StoreManagerInterface;

class Debug implements DebugInterface
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var DebugInfoFactory
     */
    private $debugInfoFactory;

    /**
     * @var HookHelper
     */
    private $hookHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var ModuleRetriever
     */
    private $moduleRetriever;

    /**
     * @var LogRetriever
     */
    private $logRetriever;

    /**
     * @param Response $response
     * @param DebugInfoFactory $debugInfoFactory
     * @param StoreManagerInterface $storeManager
     * @param HookHelper $hookHelper
     * @param ProductMetadataInterface $productMetadata
     * @param ConfigHelper $configHelper
     * @param ModuleRetriever $moduleRetriever
     * @param LogRetriever $logRetriever
     */
    public function __construct(
        Response $response,
        DebugInfoFactory $debugInfoFactory,
        StoreManagerInterface $storeManager,
        HookHelper $hookHelper,
        ProductMetadataInterface $productMetadata,
        ConfigHelper $configHelper,
        ModuleRetriever $moduleRetriever,
        LogRetriever $logRetriever
    ) {
        $this->response = $response;
        $this->debugInfoFactory = $debugInfoFactory;
        $this->storeManager = $storeManager;
        $this->hookHelper = $hookHelper;
        $this->productMetadata = $productMetadata;
        $this->configHelper = $configHelper;
        $this->moduleRetriever = $moduleRetriever;
        $this->logRetriever = $logRetriever;
    }

    /**
     * This request handler will return relevant information for Bolt for debugging purpose.
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Webapi\Exception
     * @api
     */
    public function debug()
    {
        # verify request
        $this->hookHelper->preProcessWebhook($this->storeManager->getStore()->getId());

        $result = $this->debugInfoFactory->create();

        # populate php version
        $result->setPhpVersion(PHP_VERSION);

        # populate bolt config settings
        $result->setComposerVersion($this->configHelper->getComposerVersion());

        # populate platform version
        $result->setPlatformVersion($this->productMetadata->getVersion());

        # populate bolt config settings
        $result->setBoltConfigSettings($this->configHelper->getAllConfigSettings());

        # populate other plugin info
        $result->setOtherPluginVersions($this->moduleRetriever->getInstalledModules());

        # populate log
        # parameters exist for getLog, default to exception.php last 100 lines
        $result->setLogs($this->logRetriever->getLogs());

        // prepare response
        $this->response->setHeader('Content-Type', 'json');
        $this->response->setHttpResponseCode(200);
        $this->response->setBody(
            json_encode([
                'status' => 'success',
                'event' => 'integration.debug',
                'data' => $result
            ])
        );
        $this->response->sendResponse();
    }
}
