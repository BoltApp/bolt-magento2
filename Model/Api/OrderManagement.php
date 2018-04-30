<?php
/**
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\OrderManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\Webapi\Rest\Request;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Helper\Config as ConfigHelper;

/**
 * Class OrderManagement
 * Web hook endpoint. Save the order / Update order and payment status.
 *
 * @package Bolt\Boltpay\Model\Api
 */
class OrderManagement implements OrderManagementInterface
{
    /**
     * @var HookHelper
     */
    private $hookHelper;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @param HookHelper $hookHelper
     * @param OrderHelper $orderHelper
     * @param LogHelper $logHelper
     * @param Request $request
     * @param Bugsnag $bugsnag
     * @param Response $response
     * @param Config $configHelper
     */
    public function __construct(
        HookHelper $hookHelper,
        OrderHelper $orderHelper,
        LogHelper $logHelper,
        Request $request,
        Bugsnag $bugsnag,
        Response $response,
        ConfigHelper $configHelper
    ) {
        $this->hookHelper   = $hookHelper;
        $this->orderHelper  = $orderHelper;
        $this->logHelper    = $logHelper;
        $this->request      = $request;
        $this->bugsnag      = $bugsnag;
        $this->response     = $response;
        $this->configHelper = $configHelper;
    }

    /**
     * Manage order.
     *
     * @api
     *
     * @param mixed $id
     * @param mixed $reference
     * @param mixed $order
     * @param mixed $type
     * @param mixed $amount
     * @param mixed $currency
     * @param mixed $status
     * @param mixed $display_id
     * @param mixed $source_transaction_id
     * @param mixed $source_transaction_reference
     *
     * @return void
     * @throws \Exception
     */
    public function manage(
        $id = null,
        $reference,
        $order = null,
        $type = null,
        $amount = null,
        $currency = null,
        $status = null,
        $display_id = null,
        $source_transaction_id = null,
        $source_transaction_reference = null
    ) {
        try {

            $this->logHelper->addInfoLog($this->request->getContent());

            if ($bolt_trace_id = $this->request->getHeader(ConfigHelper::BOLT_TRACE_ID_HEADER)) {
                $this->bugsnag->registerCallback(function ($report) use ($bolt_trace_id) {
                    $report->setMetaData([
                        'BREADCRUMBS_' => [
                            'bolt_trace_id' => $bolt_trace_id,
                        ]
                    ]);
                });
            }

            $this->response->setHeader('User-Agent', 'BoltPay/Magento-'.$this->configHelper->getStoreVersion());
            $this->response->setHeader('X-Bolt-Plugin-Version', $this->configHelper->getModuleVersion());

            $this->hookHelper->verifyWebhook();

            if (empty($reference)) {
                throw new LocalizedException(
                    __('Missing required parameters.')
                );
            }
            $this->orderHelper->saveUpdateOrder($reference, false);
            $this->response->setHttpResponseCode(200);
            $this->response->setBody(json_encode(array('status' => 'success','message' => 'Order creation was successful')));
            $this->response->sendResponse();
          
       } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->response->setHttpResponseCode(422);
            $this->response->setBody(json_encode(array('status' => 'error', 'code' => '1000', 'message' => $e->getMessage())));  
            $this->response->sendResponse();
          
        }
    }
}
