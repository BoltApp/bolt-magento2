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

use Bolt\Boltpay\Api\UpdateSettingsInterface;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Magento\Store\Model\StoreManagerInterface;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Helper\Log as LogHelper;

class UpdateSettings implements UpdateSettingsInterface
{
    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var HookHelper
     */
    private $hookHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var LogHelper
     */
    protected $logHelper;

    /**
     * @param ConfigHelper $configHelper
     * @param Bugsnag $bugsnag
     * @param HookHelper $hookHelper
     * @param StoreManagerInterface $storeManager
     * @param Response $response
     * @param HookHelper $hookHelper
     * @param BoltErrorResponse $errorResponse
     * @param LogHelper $logHelper
     */
    public function __construct(
        ConfigHelper $configHelper,
        Bugsnag $bugsnag,
        HookHelper $hookHelper,
        StoreManagerInterface $storeManager,
        Response $response,
        BoltErrorResponse $errorResponse,
        LogHelper $logHelper
    ) {
        $this->configHelper = $configHelper;
        $this->bugsnag = $bugsnag;
        $this->hookHelper = $hookHelper;
        $this->storeManager = $storeManager;
        $this->response = $response;
        $this->errorResponse = $errorResponse;
        $this->logHelper = $logHelper;   
    }
    
    /**
     * This request parse the debug info and map the configuration to the debug info's configuration.
     *
     * @return void
     * @param mixed $debug_info
     * @api
     */
    public function execute(
        $debug_info
    ) {
        # verify request
        $this->hookHelper->preProcessWebhook($this->storeManager->getStore()->getId());

        try {
            # parse debug_info into array
            $debug_info_decoded = json_decode($debug_info, true);

            # extract bolt config data
            $config_data = $debug_info_decoded['division']['pluginIntegrationInfo']['pluginConfigSettings'];

            # Don't set "api_key" or "signing_secret" since their values are not displayed in the debug info
            foreach ($config_data as $i => $setting) {
                $settingName = $setting['name'];
                $settingValue = $setting['value'];
                if ($settingName == 'api_key' || $settingName == 'signing_secret') {
                    continue;
                }
                else {
                    $this->configHelper->setConfigSetting($settingName, $settingValue);
                }
            }
            
            $this->response->setHttpResponseCode(200);
            $this->response->sendResponse();
            
        } catch (\Exception $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                500
            );
        }
        
    }

    /**
     * @param int        $errCode
     * @param string     $message
     * @param int        $httpStatusCode
     * @param null|Quote $quote
     *
     * @return void
     * @throws \Exception
     */
    protected function sendErrorResponse($errCode, $message, $httpStatusCode)
    {
        $errResponse = [
            'status' => 'failure',
            'errors' => [
                [
                    'code' => $errCode,
                    'message' => $message,
                ]
            ],
        ];

        $encodeErrorResult = json_encode($errResponse);

        $this->logHelper->addInfoLog('### sendErrorResponse');
        $this->logHelper->addInfoLog($encodeErrorResult);
        
        $this->bugsnag->notifyException(new \Exception($message));

        $this->response->setHttpResponseCode($httpStatusCode);
        $this->response->setBody($encodeErrorResult);
        $this->response->sendResponse();
    }
}