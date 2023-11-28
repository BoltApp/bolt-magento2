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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
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
use Magento\Framework\Webapi\Exception as WebApiException;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Sales\Model\Order as OrderModel;
use \Magento\Sales\Model\Service\OrderService;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\PaymentFailuresInterface;

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
     * @var OrderService
     */
    private $orderService;

    /**
     * @var SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var PaymentFailuresInterface
     */
    private $paymentFailures;

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
     * @param OrderService $orderService
     * @param SubscriberFactory $subscriberFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentFailuresInterface $paymentFailures
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
        Decider $decider,
        OrderService $orderService,
        SubscriberFactory $subscriberFactory,
        OrderRepositoryInterface $orderRepository,
        PaymentFailuresInterface $paymentFailures
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
        $this->orderService = $orderService;
        $this->subscriberFactory = $subscriberFactory;
        $this->orderRepository = $orderRepository;
        $this->paymentFailures = $paymentFailures;
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
     * @param mixed $immutable_quote_id
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
        $display_id = null, // <order increment ID>
        $source_transaction_id = null,
        $source_transaction_reference = null
    ) {
        try {
            $startTime = $this->metricsClient->getCurrentTime();
            HookHelper::$fromBolt = true;

            $this->logHelper->addInfoLog($this->request->getContent());

            if ($type == 'cart.create') {
                $this->handleCartCreateApiCall();
                $this->metricsClient->processMetric("webhooks.success", 1, "webhooks.latency", $startTime);
                return;
            }

            $this->cartHelper->setCurrentCurrencyCode($currency);

            // get the store id. try fetching from quote first,
            // otherwise load it from order if there's one created
            $storeId = $this->orderHelper->getStoreIdByQuoteId($order)
                    ?: $this->orderHelper->getOrderStoreIdByDisplayId($display_id);

            $this->logHelper->addInfoLog('StoreId: ' . $storeId);

            $this->hookHelper->preProcessWebhook($storeId);

            if ($type === 'pending') {
                $this->orderHelper->saveCustomerCreditCard($reference, $storeId);
            }

            $this->saveUpdateOrder($reference, $type, $display_id, $storeId);

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
     * public function for universal API use of cart.create
     *
     * @param mixed $items
     * @param mixed $currency
     *
     */
    public function createCart($items = null, $currency = null)
    {
        if ($items == null) {
            throw new BoltException(
                __('An unknown error occured when fetching the cart'),
                null,
                6300 //const not yet made for this error code
            );
        }

        $this->handleCartCreateApiCall(true);

        $this->response->sendResponse();
    }

    /**
     * Save or Update magento Order by data from hook
     *
     * @param $reference
     * @param $type
     * @param $display_id
     * @param $immutable_quote_id
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

        if ($type === 'failed_payment' || $type === 'failed' || $type === 'rejected_irreversible') {
            $transaction = $this->orderHelper->fetchTransactionInfo($reference, $storeId);
            $immutableQuoteId = $this->cartHelper->getImmutableQuoteIdFromBoltOrder($transaction->order);

            if ($type === 'failed_payment' || $type === 'failed') {
                $responseMessage = $this->orderHelper->deleteOrCancelFailedPaymentOrder($display_id, $immutableQuoteId);
                $this->setSuccessResponse($responseMessage);
                return;
            }

            if ($type === 'rejected_irreversible' && $this->orderHelper->tryDeclinedPaymentCancelation($display_id, $immutableQuoteId)) {
                $this->setSuccessResponse('Order was canceled due to declined payment: ' . $display_id);
                return;
            }
        }

        if ($type === HookHelper::HT_CAPTURE && $this->decider->isIgnoreHookForInvoiceCreationEnabled()) {
            $this->setSuccessResponse('Ignore the capture hook for the invoice creation');
            return;
        }

        if ($type === HookHelper::HT_CREDIT && $this->decider->isIgnoreHookForCreditMemoCreationEnabled()) {
            $this->setSuccessResponse('Ignore the credit hook for the credit memo creation');
            return;
        }

        $request = $this->request->getBodyParams();
        if (isset($request['data'])) {
            $request = $request['data'];
        }

        list(, $order) = $this->orderHelper->saveUpdateOrder(
            $reference,
            $storeId,
            $this->request->getHeader(ConfigHelper::BOLT_TRACE_ID_HEADER),
            $type,
            $request
        );

        $displayId = ($order !== null) ? $order->getIncrementId() : '';
        $orderData = ($order !== null) ? json_encode($order->getData()) : '';

        $this->response->setHttpResponseCode(200);
        $this->response->setBody(json_encode([
            'status' => 'success',
            'display_id' => $displayId,
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
     * @param boolean $isUniversal
     *
     * @throws \Exception
     */
    private function handleCartCreateApiCall($isUniversal = false)
    {
        $request = $this->request->getBodyParams();

        if ($isUniversal) {
            $request = $request['data'];
        }

        if (!isset($request['items'][0])) {
            throw new LocalizedException(
                __('Missing required parameters.')
            );
        }

        $storeId = isset($request['items'][0]['options']) ? (json_decode((string)$request['items'][0]['options'], true))['storeId'] : null;
        $this->logHelper->addInfoLog('StoreId: ' . $storeId);
        $this->hookHelper->preProcessWebhook($storeId);

        $cart = $this->cartHelper->createCartByRequest($request);
        $this->response->setHttpResponseCode(200);
        if ($isUniversal) {
            $this->response->setBody(json_encode([
                'event' => 'cart.create',
                'status' => 'success',
                'data' => [
                    'cart' => $cart,
                ]
            ]));
        } else {
            $this->response->setBody(json_encode([
                'status' => 'success',
                'cart' => $cart,
            ]));
        }
    }

    /**
     * Deletes a specified order by ID.
     *
     * @param int $id The order ID.
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     * @throws NoSuchEntityException
     * @throws WebapiException
     */
    public function deleteById($id)
    {
        try {
            $order = $this->orderHelper->getOrderById($id);
            if ($order->getState() != OrderModel::STATE_PENDING_PAYMENT) {
                throw new WebapiException(__('Unexpected order state'), 0, 422);
            }
            $payment = $order->getPayment();
            if ($payment && $payment->getCcTransId() != "") {
                throw new WebapiException(__('Order is already associated with transaction'), 0, 422);
            }
            $this->orderHelper->deleteOrder($order);
        } catch (WebapiException $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }
    }

    /**
     * Creates invoice by order ID.
     * We need this endpoing because magento API is not able to create
     * partial invoice without settings specific items
     *
     * @param int $id The order ID.
     * @param float $amount
     * @return bool $notify
     * @throws NoSuchEntityException
     * @throws WebapiException
     */
    public function createInvoice($id, $amount, $notify = false) {
        $order = $this->orderHelper->getOrderById($id);
        $invoice = $this->orderHelper->createOrderInvoice($order, $amount, $notify);
        $order->save();
        return $invoice->getEntityId();
    }

    /**
     *  Places an order by order ID.
     *  We need this endpoint because we skip order place during order creation
     *  as transaction might be failed on bolt side
     *  & we don't want to trigger some after events if transaction failed.
     *
     * @param $id
     * @param $transactionData
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws CommandException
     */
    public function placeOrder($id, $transactionData)
    {
        $order = $this->orderHelper->getOrderById($id);
        if ($this->decider->isOrderPlaceCallNotPostponed()) {
            return $order;
        }
        try {
            $order->place();
        } catch (CommandException $e) {
            $this->paymentFailures->handle((int)$order->getQuoteId(), __($e->getMessage()));
            throw $e;
        }

        try {
            $order = $this->orderRepository->save($order);
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }
        return $order;
    }

    /**
     * Subscribe an user / email to newsletter
     *
     * @param int $id The order ID.
     * @return bool
     * @throws NoSuchEntityException
     * @throws WebapiException
     */
    public function subscribeToNewsletter($id) {
        try {
            $order = $this->orderHelper->getOrderById($id);
            $customerId = $order->getCustomerId();
            if ($customerId) {
                $this->subscriberFactory->create()->subscribeCustomerById($customerId);
            } else {
                $email = $order->getBillingAddress()->getEmail();
                $this->subscriberFactory->create()->subscribe($email);
            }
            return true;
        } catch (WebapiException $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        } catch (\Exception $e) {
            // // We are here if we are unable to send confirmation email, for example don't have transport
            $this->bugsnag->notifyException($e);
            throw $e;
        }
    }
}
