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
use Bolt\Boltpay\Model\Api\Data\AccountInfo;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\Framework\Webapi\Exception;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Bolt\Boltpay\Api\CheckAccountInterface;
use Bolt\Boltpay\Model\Api\Data\AccountInfoFactory;

class CheckAccount implements CheckAccountInterface
{
    /**
     * @var AccountInfoFactory
     */
    private $accountInfoFactory;

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

    /*
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var BoltErrorResponse
     */
    private $errorResponse;

    /**
     * @var \Magento\Customer\Api\AccountManagementInterface
     */
    private $accountManagement;

    /**
     * @param AccountInfoFactory $accountInfoFactory
     * @param Response $response
     * @param HookHelper $hookHelper
     * @param StoreManagerInterface $storeManager,
     * @param LogHelper $logHelper
     * @param MetricsClient $metricsClient
     * @param BoltErrorResponse $errorResponse
     * @param AccountManagementInterface $accountManagement
     */
    public function __construct(
        AccountInfoFactory $accountInfoFactory,
        Response $response,
        HookHelper $hookHelper,
        StoreManagerInterface $storeManager,
        LogHelper $logHelper,
        MetricsClient $metricsClient,
        BoltErrorResponse $errorResponse,
        AccountManagementInterface $accountManagement
    )
    {
        $this->accountInfoFactory = $accountInfoFactory;
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
     * @param mixed $email
     *
     * @return \Bolt\Boltpay\Model\Api\Data\AccountInfo
     * @throws \Exception
     * @api
     */
    public function checkEmail($email = null)
    {
        $startTime = $this->metricsClient->getCurrentTime();
        try {
            $this->hookHelper
                ->preProcessWebhook($this->storeManager->getStore()->getId());
            $result = $this->accountInfoFactory->create();
            $result->setStatus("success");
            $result->setAccountExists($this->checkIfUserExistsByEmail($email));
        } catch (\Exception $e) {
            $encodedError = $this->errorResponse->prepareErrorMessage(
                BoltErrorResponse::ERR_SERVICE, $e->getMessage()
            );
            $this->logHelper
                ->addInfoLog('CheckAccount: failed checking email');
            $this->logHelper->addInfoLog($encodedError);
            $this->metricsClient
                ->processMetric(
                    "check_account.webhook.failure", 1,
                    "check_account.webhook.latency", $startTime
                );

            $this->response->setHttpResponseCode(Exception::HTTP_INTERNAL_ERROR);
            $this->response->setBody($encodedError);
            $this->response->sendResponse();
            return;
        }
        $this->metricsClient
            ->processMetric("check_account.webhook.success", 1,
                "check_account.webhook.latency", $startTime
            );
        return $result;
    }

    private function checkIfUserExistsByEmail($email)
    {
        return !$this->accountManagement->isEmailAvailable($email);
    }
}