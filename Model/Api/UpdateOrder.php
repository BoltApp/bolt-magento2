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

use Bolt\Boltpay\Api\UpdateOrderInterface;
use Bolt\Boltpay\Model\MerchantDivisionUrls;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Model\MerchantDivisionUrlsFactory;
use Bolt\Boltpay\Model\ResourceModel\MerchantDivisionUrls as ResourceModelMerchantDivisionUrls;
use Magento\Framework\Webapi\Rest\Request;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Sales\Model\OrderRepository;
use Magento\Framework\Exception\AlreadyExistsException;


/**
 * Class UPdateOrder
 * Web hook endpoint. Update the order.
 *
 * @package Bolt\Boltpay\Model\Api
 */
class UpdateOrder implements UpdateOrderInterface
{
    const E_BOLT_GENERAL_ERROR = 2001001;
    const E_BOLT_ORDER_ALREADY_EXISTS = 2001002;
    const E_BOLT_CART_HAS_EXPIRED = 2001003;
    const E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED = 2001004;

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
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var MerchantDivisionUrlsFactory
     */
    private $merchantDivisionUrlsFactory;
    private $resourceModelMerchantDivisionUrls;

    /**
     * @param HookHelper   $hookHelper
     * @param OrderHelper  $orderHelper
     * @param LogHelper    $logHelper
     * @param Request      $request
     * @param Bugsnag      $bugsnag
     * @param Response     $response
     */
    public function __construct(
        HookHelper $hookHelper,
        OrderHelper $orderHelper,
        LogHelper $logHelper,
        OrderRepository $orderRepository,
        MerchantDivisionUrlsFactory $merchantDivisionUrlsFactory,
        ResourceModelMerchantDivisionUrls $resourceModelMerchantDivisionUrls,
        Request $request,
        Bugsnag $bugsnag,
        Response $response
    ) {
        $this->hookHelper = $hookHelper;
        $this->orderHelper = $orderHelper;
        $this->logHelper = $logHelper;
        $this->request = $request;
        $this->bugsnag = $bugsnag;
        $this->response = $response;
        $this->orderRepository = $orderRepository;
        $this->merchantDivisionUrlsFactory = $merchantDivisionUrlsFactory;
        $this->resourceModelMerchantDivisionUrls = $resourceModelMerchantDivisionUrls;
    }

    /**
     * Pre-Auth hook: Update order.
     *
     * @api
     *
     * @param null $type
     * @param null $transaction
     * @param null $order_reference
     * @param null $display_id
     *
     * return void
     */
    public function execute(
        $type = null,
        $transaction = null,
        $order_reference = null,
        $display_id = null
    ) {
        try {
            if ($type !== 'order.update') {
                throw new LocalizedException(__('Invalid hook type!'));
            }

            $this->validateHook();

            if (!$display_id) {
                throw new LocalizedException(__('Invalid parameter display_id!'));
            }

            $orderData = $this->getMagentoOrderById($display_id);

            $orderUpdate = $this->orderHelper->preAuthUpdateOrder($orderData, $transaction);

            if ($orderUpdate) {
                $this->saveMerchantDivisionData($type, $transaction);
            }

            $this->sendResponse(200, [
                'status'    => 'success',
                'message'   => 'Order update was successful',
                'display_id' => $display_id,
                'total'      => $orderData->getGrandTotal() * 100,
                'order_received_url' => '',
            ]);
        } catch (\Magento\Framework\Webapi\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->sendResponse($e->getHttpCode(), [
                'status' => 'error',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ]);
        } catch (LocalizedException $e) {
            $this->bugsnag->notifyException($e);
            $this->sendResponse(422, [
                'status' => 'error',
                'code' => '6009',
                'message' => 'Unprocessable Entity: ' . $e->getMessage(),
            ]);
        } finally {
            $this->response->sendResponse();
        }
    }

    /**
     * @param $type
     * @param $transaction
     * @return bool
     * @throws LocalizedException
     */
    public function saveMerchantDivisionData($type, $transaction)
    {
        try {
            /*
             * merchant_division object:
             * "merchant_division": {
             *      "id","merchant_id", "public_id", "description", "logo": {"domain","resource"},
             *      "platform": "woo_commerce", "hook_url", "hook_type",
             *      "shipping_and_tax_url", "create_order_url", "update_order_url"
             *  },
             * */

            $divisionID = $transaction->merchant_division->id;
            $divisionType = $type;
            $updateOrderUrl = $transaction->merchant_division->update_order_url;

            /** @var MerchantDivisionUrls $merchantDivision */
            $merchantDivision = $this->merchantDivisionUrlsFactory->create();
            // save merchant division data.
            $merchantDivision->addData([
                $divisionID,
                $divisionType,
                $updateOrderUrl
            ]);

            $this->resourceModelMerchantDivisionUrls->save($merchantDivision);
            return true;
        } catch (\Exception $e) {
            $message = __('MerchantDivision: %1', $e->getMessage());
            throw new LocalizedException($message);
        }
    }

    /**
     * @param $orderId
     * @return \Magento\Sales\Api\Data\OrderInterface|null
     * @throws LocalizedException
     */
    public function getMagentoOrderById($orderId)
    {
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (LocalizedException $e) {
            $order = null;

            $message = __('Order ID: %1. Error: %2', $orderId, $e->getMessage());
            throw new LocalizedException($message);
        }

        return $order;
    }

    /**
     * @throws LocalizedException
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function validateHook()
    {
        HookHelper::$fromBolt = true;

        $this->hookHelper->setCommonMetaData();
        $this->hookHelper->setHeaders();

        $this->hookHelper->verifyWebhook();
    }

    /**
     * @param int   $code
     * @param array $body
     */
    public function sendResponse($code, array $body)
    {
        $this->response->setHttpResponseCode($code);
        $this->response->setBody(json_encode($body));
    }
}
