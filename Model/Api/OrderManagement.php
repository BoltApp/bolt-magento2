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

use Bolt\Boltpay\Api\OrderManagementInterface;
use Bolt\Boltpay\Exception\BoltException;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\Webapi\Rest\Request;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\MetricsClient;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;

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

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var Decider
     */
    private $decider;

    /**
     * @param HookHelper $hookHelper
     * @param OrderHelper $orderHelper
     * @param LogHelper $logHelper
     * @param Request $request
     * @param Bugsnag $bugsnag
     * @param MetricsClient $metricsClient
     * @param Response $response
     * @param ConfigHelper $configHelper
     * @param CartHelper $cartHelper
     * @param Decider $decider
     */
    public function __construct(
        HookHelper $hookHelper,
        OrderHelper $orderHelper,
        LogHelper $logHelper,
        Request $request,
        Bugsnag $bugsnag,
        MetricsClient $metricsClient,
        Response $response,
        ConfigHelper $configHelper,
        CartHelper $cartHelper,
        Decider $decider
    ) {
        $this->hookHelper   = $hookHelper;
        $this->orderHelper  = $orderHelper;
        $this->logHelper    = $logHelper;
        $this->request      = $request;
        $this->bugsnag      = $bugsnag;
        $this->metricsClient = $metricsClient;
        $this->response     = $response;
        $this->configHelper = $configHelper;
        $this->cartHelper   = $cartHelper;
        $this->decider = $decider;
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
        $reference = null,
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
            $startTime = $this->metricsClient->getCurrentTime();
            HookHelper::$fromBolt = true;

            $this->logHelper->addInfoLog($this->request->getContent());

            // get the store id. try fetching from quote first,
            // otherwise load it from order if there's one created
            $storeId = $this->orderHelper->getStoreIdByQuoteId($order)
                    ?: $this->orderHelper->getOrderStoreIdByDisplayId($display_id);

            $this->logHelper->addInfoLog('StoreId: ' . $storeId);

            $this->hookHelper->preProcessWebhook($storeId);

            if ($type === 'pending') {
                $this->orderHelper->saveCustomerCreditCard($display_id, $reference, $storeId);
            }

            if ($type == 'cart.create') {
                $this->handleCartCreateApiCall();
            } else {
                $this->saveUpdateOrder($reference, $type, $display_id, $storeId);
            }
            $this->metricsClient->processMetric("webhooks.success", 1, "webhooks.latency", $startTime);
        } catch (BoltException $e) {
            $this->bugsnag->notifyException($e);
            $this->metricsClient->processMetric("webhooks.failure", 1, "webhooks.latency", $startTime);
            $this->response->setHttpResponseCode(422);
            $this->response->setBody(json_encode([
                'status' => 'failure',
                'error' => ['code' => $e->getCode(), 'message' => $e->getMessage()],
            ]));
        } catch (\Magento\Framework\Webapi\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->metricsClient->processMetric("webhooks.failure", 1, "webhooks.latency", $startTime);
            $this->response->setHttpResponseCode($e->getHttpCode());
            $this->response->setBody(json_encode([
                'status' => 'error',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ]));
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->metricsClient->processMetric("webhooks.failure", 1, "webhooks.latency", $startTime);
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

    /**
     * Save or Update magento Order by data from hook
     *
     * @param $reference
     * @param $type
     * @param $display_id
     * @param $storeId
     * @throws \Bolt\Boltpay\Exception\BoltException
     */
    private function saveUpdateOrder($reference, $type, $display_id, $storeId)
    {
        if (empty($reference)) {
            throw new LocalizedException(
                __('Missing required parameters.')
            );
        }
        if ($type === 'failed_payment' || $type === 'failed') {
            $this->orderHelper->deleteOrderByIncrementId($display_id);
            $this->setSuccessResponse('Order was deleted: ' . $display_id);
            return;
        }

        if ($type === 'rejected_irreversible' && $this->orderHelper->tryDeclinedPaymentCancelation($display_id)) {
            $this->setSuccessResponse('Order was canceled due to declined payment: ' . $display_id);
            return;
        }

        if ($type === HookHelper::HT_CAPTURE && $this->decider->isIgnoreHookForInvoiceCreationEnabled()) {
            $this->setSuccessResponse('Ignore the capture hook for the invoice creation');
            return;
        }

        if ($type === HookHelper::HT_CREDIT && $this->decider->isIgnoreHookForCreditMemoCreationEnabled()) {
            $this->setSuccessResponse('Ignore the credit hook for the credit memo creation');
            return;
        }

        list(, $order) = $this->orderHelper->saveUpdateOrder(
            $reference,
            $storeId,
            $this->request->getHeader(ConfigHelper::BOLT_TRACE_ID_HEADER),
            $type,
            $this->request->getBodyParams()
        );

        $orderData = json_encode($order->getData());
        $this->response->setHttpResponseCode(200);
        $this->response->setBody(json_encode([
            'status' => 'success',
            'message' => "Order creation / update was successful. Order Data: $orderData",
        ]));
    }

    /**
     * @param $message
     */
    private function setSuccessResponse($message)
    {
        $this->response->setHttpResponseCode(200);
        $this->response->setBody(json_encode([
            'status' => 'success',
            'message' => $message,
        ]));
    }

    /**
     * Handle cart.create API call
     * - generate quote by item data
     * - create bolt order
     * - return order in Bolt format
     *
     * @throws \Exception
     */
    private function handleCartCreateApiCall()
    {
        $request = $this->request->getBodyParams();

        if (!isset($request['items'][0])) {
            throw new LocalizedException(
                __('Missing required parameters.')
            );
        }

        $cart = $this->cartHelper->createCartByRequest($request);
        $this->response->setHttpResponseCode(200);
        $this->response->setBody(json_encode([
            'status' => 'success',
            'cart' => $cart,
        ]));
    }
}
