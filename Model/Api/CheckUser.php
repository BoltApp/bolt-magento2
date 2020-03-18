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

use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\Framework\Webapi\Exception;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Bolt\Boltpay\Api\CheckUserInterface;

class CheckUser implements CheckUserInterface
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

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
     * @var ConfigHelper
     */
    private $configHelper;

    /* @var StoreManagerInterface */
    protected $storeManager;

    /**
     * @var BoltErrorResponse
     */
    private $errorResponse;

    /**
     * @var \Magento\Customer\Api\AccountManagementInterface
     */
    private $accountManagement;

    /**
     * @param Request $request
     * @param Response $response
     * @param HookHelper $hookHelper
     * @param OrderHelper $orderHelper
     * @param LogHelper $logHelper
     * @param MetricsClient $metricsClient
     * @param Config $configHelper
     * @param BoltErrorResponse $errorResponse
     * @param AccountManagementInterface $accountManagement
     */
    public function __construct(
        Request $request,
        Response $response,
        HookHelper $hookHelper,
        StoreManagerInterface $storeManager,
        LogHelper $logHelper,
        MetricsClient $metricsClient,
        BoltErrorResponse $errorResponse,
        AccountManagementInterface $accountManagement
    )
    {
        $this->request = $request;
        $this->request = $request;
        $this->response = $response;
        $this->hookHelper = $hookHelper;
        $this->logHelper = $logHelper;
        $this->metricsClient = $metricsClient;
        $this->storeManager = $storeManager;
        $this->errorResponse = $errorResponse;
        $this->accountManagement = $accountManagement;
    }

    /**
     * This webhook receive email and return true if M2 user with this email exists
     * and false otherwise
     *
     * @return void
     * @throws \Exception
     * @api
     */
    public function checkEmail()
    {
        $startTime = $this->metricsClient->getCurrentTime();
        /*$this->hookHelper
              ->preProcessWebhook($this->storeManager->getStore()->getId());*/
        try {
            $request = json_decode($this->request->getContent(), true);
            $email = $request['email'];
            $result = $this->checkIfUserExistsByEmail($email);
        } catch (\Exception $e) {
            $encodedError = $this->errorResponse->prepareErrorMessage(
                BoltErrorResponse::ERR_SERVICE, $e->getMessage()
            );
            $this->logHelper
                ->addInfoLog('CheckUser: failed checking email');
            $this->logHelper->addInfoLog($encodedError);
            $this->metricsClient
                ->processMetric(
                    "check_user.webhook.failure", 1,
                    "check_user.webhook.latency", $startTime
                );

            $this->response->setHttpResponseCode(Exception::HTTP_INTERNAL_ERROR);
            $this->response->setBody($encodedError);
            $this->response->sendResponse();
            return;
        }
        $this->metricsClient
            ->processMetric("check_user.webhook.success", 1,
                "check_user.webhook.latency", $startTime
            );
        $this->response->setBody(json_encode(array("result" => $result)));
        $this->response->sendResponse();
    }

    private function checkIfUserExistsByEmail($email)
    {
        return !$this->accountManagement->isEmailAvailable($email);
    }
}