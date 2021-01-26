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

use Bolt\Boltpay\Api\UniversalWebhookInterface;
use Bolt\Boltpay\Api\OrderManagementInterface;
use Bolt\Boltpay\Api\Data\UniversalWebhookResultInterface as Result;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\Framework\Webapi\Rest\Response;

class UniversalWebhook implements UniversalWebhookInterface
{

    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var Result
     */
    private $result;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var BoltErrorResponse
     */
    protected $errorResponse;

    /**
     * @var Response
     */
    private $response;

    public function __construct(
        OrderManagementInterface $orderManagement,
        Result $result,
        Bugsnag $bugsnag,
        LogHelper $logHelper,
        BoltErrorResponse $errorResponse,
        Response $response
    )
    {
        $this->orderManagement = $orderManagement;
        $this->result = $result;
        $this->bugsnag = $bugsnag;
        $this->logHelper = $logHelper;
        $this->errorResponse = $errorResponse;
        $this->response = $response;
    }

    /**
     * @api
     * 
     * @param string $type
     * @param string $object
     * @param mixed $data
     * 
     * @return boolean
     */
    public function execute(
        $type = null,
        $object = null,
        $data = null
    )
    {
        try {
            // This just reuses the existing manage function by pulling the necessary data from the request.
            $this->orderManagement->manage(
                isset($data['id']) ? $data['id'] : null, //transaction id
                isset($data['reference']) ? $data['reference'] : null, //bolt transaction id
                isset($data['order']['cart']['order_reference']) ? $data['order']['cart']['order_reference'] : null, //parent quote id
                isset($type) ? $type : null,
                isset($data['amount']['amount']) ? $data['amount']['amount'] : null, 
                isset($data['amount']['currency']) ? $data['amount']['currency'] : null,
                isset($data['status']) ? $data['status'] : null,
                isset($data['order']['cart']['display_id']) ? $data['order']['cart']['display_id'] : null,
                isset($data['source_transaction']['id']) ? $data['source_transaction']['id'] : null,
                isset($data['source_transaction']['reference']) ? $data['source_transaction']['reference'] : null
            );
        }
        catch (BoltException $e) {
            $this->sendErrorResponse(
                $e->getCode(),
                $e->getMessage(),
                422
            );
            return false;
        }
        $this->result->setStatus('success');

        $this->sendSuccessResponse($this->result);

        return true;
    }

    /**
     * @param UniversalWebhookResultInterface $result
     */
    private function formatResponse($result)
    {
        //formats the response from the api result interface.
        $response = [
            'status' => $this->result->getStatus()
        ];

        //currently this is never used as error response is handled separately
        //leaving as failsafe
        if ($response['status'] == "failed")
        {
            $response['error'] = $this->result->getError();
        }

        return json_encode($response);
    }

    //TODO: extract this out, duplicated with UniversalApi::sendErrorResponse
    /**
     * @param int        $errCode
     * @param string     $message
     * @param int        $httpStatusCode
     *
     * @return void
     * @throws \Exception
     */
    protected function sendErrorResponse($errCode, $message, $httpStatusCode)
    {
        $encodeErrorResult = $this->errorResponse
            ->prepareErrorMessage($errCode, $message);

        $this->logHelper->addInfoLog('### sendErrorResponse');
        $this->logHelper->addInfoLog($encodeErrorResult);
        
        $this->bugsnag->notifyException(new \Exception($message));

        $this->response->setHttpResponseCode($httpStatusCode);
        $this->response->setBody($encodeErrorResult);
        $this->response->sendResponse();
    }

    protected function sendSuccessResponse($result)
    {
        //creates and sends error response. Uses formatResponse
        $this->response->setHttpResponseCode(200);
        $this->response->setBody($this->formatResponse($result));
        $this->response->sendResponse();
    }

}