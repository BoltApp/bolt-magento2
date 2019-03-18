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
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Cart;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Framework\UrlInterface;
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
     * @var CartHelper
     */
    private $cartHelper;
    private $url;

    /**
     * @param HookHelper   $hookHelper
     * @param OrderHelper  $orderHelper
     * @param CartHelper   $cartHelper
     * @param LogHelper    $logHelper
     * @param Request      $request
     * @param Bugsnag      $bugsnag
     * @param Response     $response
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        HookHelper $hookHelper,
        OrderHelper $orderHelper,
        CartHelper $cartHelper,
        LogHelper $logHelper,
        Request $request,
        Bugsnag $bugsnag,
        Response $response,
        UrlInterface $url,
        ConfigHelper $configHelper
    ) {
        $this->hookHelper = $hookHelper;
        $this->orderHelper = $orderHelper;
        $this->logHelper = $logHelper;
        $this->request = $request;
        $this->bugsnag = $bugsnag;
        $this->response = $response;
        $this->configHelper = $configHelper;
        $this->cartHelper = $cartHelper;
        $this->url = $url;
    }

    /**
     * Pre-Auth hook: Create order.
     *
     * @api
     *
     * @param string $type - "order.create"
     * @param array $order
     * @param string $currency
     *
     * @return void
     * @throws \Exception
     */
    public function execute(
        $type = null,
        $order = null,
        $currency = null
    ) {
        try {
            if ($type !== 'order.create') {
                throw new BoltException(
                    __('Invalid hook type!'),
                    null,
                    self::E_BOLT_GENERAL_ERROR
                );
            }

            $payload = $this->request->getContent();
            $this->logHelper->addInfoLog('[ --- DEBUG --- ]');
//            $this->logHelper->addInfoLog($payload);

            $this->validateHook();

            if (empty($order)) {
                throw new BoltException(
                    __('Missing order data.'),
                    null,
                    self::E_BOLT_GENERAL_ERROR
                );
            }

            $quoteId = $this->getQuoteIdFromPayloadOrder($order);
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $this->loadQuoteData($quoteId);

            /** @var \Magento\Sales\Model\Order $createOrderData */
            $createOrderData = $this->orderHelper->preAuthCreateOrder($quote, $payload);
            $orderReference = $this->getOrderReference($order);

            $this->sendResponse(200, [
                'status'    => 'success',
                'message'   => 'Order create was successful',
                'display_id' => $createOrderData->getIncrementId() . ' / ' . $quote->getId(),
                'total'      => $createOrderData->getGrandTotal() * 100,
                'order_received_url' => $this->getReceivedUrl($orderReference),
            ]);
        } catch (\Magento\Framework\Webapi\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->sendResponse($e->getHttpCode(), [
                'status' => 'failure',
                'error'  => [
                    'code' => self::E_BOLT_GENERAL_ERROR,
                    'data' => [
                        'reason' => $e->getCode() . ': ' . $e->getMessage(),
                    ]
                ]
            ]);
        } catch (BoltException $e) {
            $this->bugsnag->notifyException($e);
            $this->sendResponse(422, [
                'status' => 'failure',
                'error'  => [
                    'code' => $e->getCode(),
                    'data' => [
                        'reason' => $e->getMessage(),
                    ]
                ]
            ]);
        } catch (LocalizedException $e) {
            $this->bugsnag->notifyException($e);
            $this->sendResponse(422, [
                'status' => 'failure',
                'error'  => [
                    'code' => 6009,
                    'data' => [
                        'reason' => 'Unprocessable Entity: ' . $e->getMessage(),
                    ]
                ]
            ]);
        } finally {
            $this->response->sendResponse();
        }
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
     * @param $order
     * @return int
     * @throws LocalizedException
     */
    public function getQuoteIdFromPayloadOrder($order)
    {
        $parentQuoteId = $this->getOrderReference($order);
        $displayId = $this->getDisplayId($order);
        list($incrementId, $quoteId) = array_pad(
            explode(' / ', $displayId),
            2,
            null
        );

        if (!$quoteId) {
            $quoteId = $parentQuoteId;
        }

        return (int)$quoteId;
    }

    /**
     * @param $order
     * @return string
     * @throws LocalizedException
     */
    public function getOrderReference($order)
    {
        if (isset($order['cart']) && isset($order['cart']['order_reference'])) {
            return $order['cart']['order_reference'];
        }

        $error = __('cart->order_reference does not exist');
        throw new LocalizedException($error);
    }

    /**
     * @param $order
     * @return string
     * @throws LocalizedException
     */
    public function getDisplayId($order)
    {
        if (isset($order['cart']) && isset($order['cart']['display_id'])) {
            return $order['cart']['display_id'];
        }

        $error = __('cart->display_id does not exist');
        throw new LocalizedException($error);
    }

    /**
     * @return string
     */
    public function getReceivedUrl($orderReference)
    {
        $this->logHelper->addInfoLog('[-= getReceivedUrl =-]');
        if (empty($orderReference)) {
            $this->logHelper->addInfoLog('---> empty');
            return '';
        }

//        $url = $this->url->getUrl('/rest/V1/bolt/boltpay/order/received_url');
        $url = $this->url->getUrl('boltpay/order/receivedurl');
        $this->logHelper->addInfoLog('---> ' . $url);

        return $url;
    }

    /**
     * @param $quoteId
     * @return \Magento\Quote\Model\Quote|null
     * @throws LocalizedException
     */
    public function loadQuoteData($quoteId)
    {
        try {
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $this->cartHelper->getQuoteById($quoteId);
        } catch (NoSuchEntityException $e) {
            $this->bugsnag->registerCallback(function ($report) use ($quoteId) {
                $report->setMetaData([
                    'ORDER' => [
                        'pre-auth' => true,
                        'quoteId' => $quoteId,
                    ]
                ]);
            });
            $quote = null;

            throw new BoltException(
                __('There is no quote with ID: %1', $quoteId),
                null,
                self::E_BOLT_GENERAL_ERROR
            );
        }

        return $quote;
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
