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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
        $order = null, // <parent quote ID>
        $type = null,
        $amount = null,
        $currency = null,
        $status = null,
        $display_id = null, // <order increment ID / immutable quote ID>
        $source_transaction_id = null,
        $source_transaction_reference = null
    ) {
        try {
            HookHelper::$fromBolt = true;

            $this->logHelper->addInfoLog($this->request->getContent());

            // get the store id. try fetching from quote first,
            // otherwise load it from order if there's one created
            $storeId = $this->orderHelper->getStoreIdByQuoteId($order)
                    ?: $this->orderHelper->getOrderStoreIdByDisplayId($display_id);

            $this->logHelper->addInfoLog('StoreId: ' . $storeId);

            $this->hookHelper->preProcessWebhook($storeId);

            if (empty($reference)) {
                throw new LocalizedException(
                    __('Missing required parameters.')
                );
            }
            $this->orderHelper->saveUpdateOrder(
                $reference,
                $storeId,
                $this->request->getHeader(ConfigHelper::BOLT_TRACE_ID_HEADER),
                $type
            );
            $this->response->setHttpResponseCode(200);
            $this->response->setBody(json_encode([
                'status' => 'success',
                'message' => 'Order creation / upadte was successful',
            ]));
        } catch (\Magento\Framework\Webapi\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->response->setHttpResponseCode($e->getHttpCode());
            $this->response->setBody(json_encode([
                'status' => 'error',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ]));
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->response->setHttpResponseCode(422);
            $this->response->setBody(json_encode([
                'status' => 'error',
                'code' => '6009',
                'message' => 'Unprocessable Entity: ' . $e->getMessage(),
            ]));
        } finally {
            $this->response->sendResponse();
        }
    }
}
