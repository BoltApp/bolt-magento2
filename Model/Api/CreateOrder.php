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

use Bolt\Boltpay\Api\CreateOrderInterface;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\Webapi\Rest\Request;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Helper\Config as ConfigHelper;

/**
 * Class CreateOrder
 * Web hook endpoint. Create the order.
 *
 * @package Bolt\Boltpay\Model\Api
 */
class CreateOrder implements CreateOrderInterface
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
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @param HookHelper   $hookHelper
     * @param OrderHelper  $orderHelper
     * @param LogHelper    $logHelper
     * @param Request      $request
     * @param Bugsnag      $bugsnag
     * @param Response     $response
     * @param ConfigHelper $configHelper
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
        $this->hookHelper = $hookHelper;
        $this->orderHelper = $orderHelper;
        $this->logHelper = $logHelper;
        $this->request = $request;
        $this->bugsnag = $bugsnag;
        $this->response = $response;
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
     * @param mixed $displayId
     * @param mixed $sourceTransactionId
     * @param mixed $sourceTransactionReference
     *
     * @return void
     * @throws \Exception
     */
    public function execute(
        $id = null,
        $reference = null,
        $order = null,
        $type = null,
        $amount = null,
        $currency = null,
        $status = null,
        $displayId = null,
        $sourceTransactionId = null,
        $sourceTransactionReference = null
    ) {
        try {
            HookHelper::$fromBolt = true;

            $this->logHelper->addInfoLog($this->request->getContent());

            $this->hookHelper->setCommonMetaData();
            $this->hookHelper->setHeaders();

            $this->hookHelper->verifyWebhook();

            if (empty($reference)) {
                throw new LocalizedException(
                    __('Missing required parameters.')
                );
            }
            $this->orderHelper->saveUpdateOrder(
                $reference,
                $this->request->getHeader(ConfigHelper::BOLT_TRACE_ID_HEADER)
            );
            $this->response->setHttpResponseCode(200);
            $this->response->setBody(json_encode([
                'status' => 'success',
                'message' => 'Order create was successful',
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
