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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api;


use Bolt\Boltpay\Api\FeatureSwitchesHookInterface;
use Bolt\Boltpay\Helper\FeatureSwitch\Manager;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\Framework\Webapi\Exception;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Model\StoreManagerInterface;

class FeatureSwitchesHook implements FeatureSwitchesHookInterface {

    /**
     * @var HookHelper
     */
    private $hookHelper;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var MetricsClient
     */
    private $metricsClient;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /* @var StoreManagerInterface */
    protected $storeManager;

    /* @var Manager */
    protected $fsManager;

    /**
     * @var BoltErrorResponse
     */
    private $errorResponse;

    /**
     * @param HookHelper $hookHelper
     * @param OrderHelper $orderHelper
     * @param LogHelper $logHelper
     * @param MetricsClient $metricsClient
     * @param Response $response
     * @param Config $configHelper
     * @param Manager $fsManager,
     * @param BoltErrorResponse $errorResponse
     */
    public function __construct(
        HookHelper $hookHelper,
        StoreManagerInterface $storeManager,
        LogHelper $logHelper,
        MetricsClient $metricsClient,
        Response $response,
        ConfigHelper $configHelper,
        Manager $fsManager,
        BoltErrorResponse $errorResponse
    ) {
        $this->hookHelper   = $hookHelper;
        $this->logHelper    = $logHelper;
        $this->metricsClient = $metricsClient;
        $this->response     = $response;
        $this->configHelper = $configHelper;
        $this->storeManager = $storeManager;
        $this->fsManager = $fsManager;
        $this->errorResponse = $errorResponse;
    }

    /**
     * This webhook handler will reach out to bolt and then store switches on
     * the plugin table for feature switches. It returns only when the entire
     * call is done, i.e. it is a blocking call.
     * 
     * @api
     * @return void
     * @throws \Exception
     */
    public function notifyChanged() {
        $startTime = $this->metricsClient->getCurrentTime();
        // TODO(roopakv): verify webhook after adding support on bolt side
        // $this->hookHelper
        //      ->preProcessWebhook($this->storeManager->getStore()->getId());

        try {
            $this->fsManager->updateSwitchesFromBolt();
        } catch (\Exception $e) {
            $encodedError =  $this->errorResponse->prepareErrorMessage(
                BoltErrorResponse::ERR_SERVICE, $e->getMessage()
            );
            $this->logHelper
                ->addInfoLog('FeatureSwitchHook: failed updating switches');
            $this->logHelper->addInfoLog($encodedError);
            $this->metricsClient
                ->processMetric(
                    "feature_switch.webhook.failure", 1,
                    "feature_switch.webhook.latency", $startTime
                );

            $this->response->setHttpResponseCode(Exception::HTTP_INTERNAL_ERROR);
            $this->response->setBody($encodedError);
            $this->response->sendResponse();
            return;
        }
        $this->metricsClient
            ->processMetric("feature_switch.webhook.success", 1,
                "feature_switch.webhook.latency", $startTime
            );
        $this->response->setBody(json_encode(array("status"=>"success")));
        $this->response->sendResponse();
    }
}