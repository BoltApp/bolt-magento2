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
use Bolt\Boltpay\Helper\MetricsClient;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Framework\UrlInterface;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\CatalogInventory\Helper\Data as CatalogInventoryData;
use Bolt\Boltpay\Helper\Session as SessionHelper;

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
    const E_BOLT_ITEM_OUT_OF_INVENTORY = 2001005;
    const E_BOLT_DISCOUNT_CANNOT_APPLY = 2001006;
    const E_BOLT_DISCOUNT_CODE_DOES_NOT_EXIST = 2001007;
    const E_BOLT_SHIPPING_EXPIRED = 2001008;
    const E_BOLT_REJECTED_ORDER = 2001010;
    const E_BOLT_MINIMUM_PRICE_NOT_MET = 2001011;

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
     * @var UrlInterface
     */
    private $url;

    /**
     * @var BackendUrl
     */
    private $backendUrl;

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var SessionHelper
     */
    private $sessionHelper;

    /**
     * @param HookHelper             $hookHelper
     * @param OrderHelper            $orderHelper
     * @param CartHelper             $cartHelper
     * @param LogHelper              $logHelper
     * @param Request                $request
     * @param Bugsnag                $bugsnag
     * @param MetricsClient        $metricsClient
     * @param Response               $response
     * @param UrlInterface           $url
     * @param BackendUrl             $backendUrl
     * @param ConfigHelper           $configHelper
     * @param StockRegistryInterface $stockRegistry
     * @param SessionHelper          $sessionHelper
     */
    public function __construct(
        HookHelper $hookHelper,
        OrderHelper $orderHelper,
        CartHelper $cartHelper,
        LogHelper $logHelper,
        Request $request,
        Bugsnag $bugsnag,
        MetricsClient $metricsClient,
        Response $response,
        UrlInterface $url,
        BackendUrl $backendUrl,
        ConfigHelper $configHelper,
        StockRegistryInterface $stockRegistry,
        SessionHelper $sessionHelper
    ) {
        $this->hookHelper = $hookHelper;
        $this->orderHelper = $orderHelper;
        $this->logHelper = $logHelper;
        $this->request = $request;
        $this->bugsnag = $bugsnag;
        $this->metricsClient = $metricsClient;
        $this->response = $response;
        $this->configHelper = $configHelper;
        $this->cartHelper = $cartHelper;
        $this->url = $url;
        $this->backendUrl = $backendUrl;
        $this->stockRegistry = $stockRegistry;
        $this->sessionHelper = $sessionHelper;
    }

    /**
     * Pre-Auth hook: Create order.
     *
     * @api
     * @inheritDoc
     * @see CreateOrderInterface
     */
    public function execute(
        $type = null,
        $order = null,
        $currency = null
    ) {
        try {
            $startTime = $this->metricsClient->getCurrentTime();
            $payload = $this->request->getContent();
            $this->logHelper->addInfoLog('[-= Pre-Auth CreateOrder =-]');
            $this->logHelper->addInfoLog($payload);

            if ($type !== 'order.create') {
                throw new BoltException(
                    __('Invalid hook type!'),
                    null,
                    self::E_BOLT_GENERAL_ERROR
                );
            }

            if (empty($order)) {
                throw new BoltException(
                    __('Missing order data.'),
                    null,
                    self::E_BOLT_GENERAL_ERROR
                );
            }

            $quoteId = $this->getQuoteIdFromPayloadOrder($order);
            /** @var Quote $immutableQuote */
            $immutableQuote = $this->loadQuoteData($quoteId);

            $this->preProcessWebhook($immutableQuote->getStoreId());

            $transaction = json_decode($payload);

            /** @var Quote $quote */
            $quote = $this->orderHelper->prepareQuote($immutableQuote, $transaction);

            /** @var \Magento\Sales\Model\Order $createdOrder */
            $createdOrder = $this->orderHelper->processExistingOrder($quote, $transaction);

            if (! $createdOrder) {
                $this->validateQuoteData($quote, $transaction);
                $createdOrder = $this->orderHelper->processNewOrder($quote, $transaction);
            }

            $this->sendResponse(200, [
                'status'    => 'success',
                'message'   => 'Order create was successful',
                'display_id' => $createdOrder->getIncrementId() . ' / ' . $quote->getId(),
                'total'      => $this->cartHelper->getRoundAmount($createdOrder->getGrandTotal()),
                'order_received_url' => $this->getReceivedUrl($immutableQuote),
            ]);
            $this->metricsClient->processMetric("order_creation.success", 1, "order_creation.latency", $startTime);
        } catch (\Magento\Framework\Webapi\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->metricsClient->processMetric("order_creation.failure", 1, "order_creation.latency", $startTime);
            $this->sendResponse($e->getHttpCode(), [
                'status' => 'failure',
                'error'  => [[
                    'code' => self::E_BOLT_GENERAL_ERROR,
                    'data' => [[
                        'reason' => $e->getCode() . ': ' . $e->getMessage(),
                    ]]
                ]]
            ]);
        } catch (BoltException $e) {
            $this->bugsnag->notifyException($e);
            $this->metricsClient->processMetric("order_creation.failure", 1, "order_creation.latency", $startTime);
            $this->sendResponse(422, [
                'status' => 'failure',
                'error'  => [[
                    'code' => $e->getCode(),
                    'data' => [[
                        'reason' => $e->getMessage(),
                    ]]
                ]]
            ]);
        } catch (LocalizedException $e) {
            $this->bugsnag->notifyException($e);
            $this->metricsClient->processMetric("order_creation.failure", 1, "order_creation.latency", $startTime);
            $this->sendResponse(422, [
                'status' => 'failure',
                'error' => [[
                    'code' => 6009,
                    'data' => [[
                        'reason' => 'Unprocessable Entity: ' . $e->getMessage(),
                    ]]
                ]]
            ]);
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->metricsClient->processMetric("order_creation.failure", 1, "order_creation.latency", $startTime);
            $this->sendResponse(422, [
                'status' => 'failure',
                'error' => [[
                    'code' => self::E_BOLT_GENERAL_ERROR,
                    'data' => [[
                        'reason' => $e->getMessage(),
                    ]]
                ]]
            ]);
        } finally {
            $this->response->sendResponse();
        }
    }

    /**
     * @param null $storeId
     * @throws LocalizedException
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function preProcessWebhook($storeId = null)
    {
        HookHelper::$fromBolt = true;

        $this->hookHelper->preProcessWebhook($storeId);
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
     * Format redirect url taking into account the type of session
     * the order was created from, front-end vs. back-end
     *
     * @param Quote $quote
     * @return string
     */
    public function getReceivedUrl($quote)
    {
        $this->sessionHelper->setFormKey($quote);

        $this->logHelper->addInfoLog('[-= getReceivedUrl =-]');
        $storeId = $quote->getStoreId();
        $urlInterface = $this->isBackOfficeOrder($quote) ? $this->backendUrl : $this->url;
        $urlInterface->setScope($storeId);
        $params = [
            '_secure' => true,
            'store_id' => $storeId
        ];
        $url = $urlInterface->getUrl('boltpay/order/receivedurl', $params);
        $this->logHelper->addInfoLog('---> ' . $url);

        return $url;
    }

    /**
     * @param $quote
     * @return bool
     */
    public function isBackOfficeOrder($quote)
    {
        return (bool)$quote->getBoltIsBackendOrder();
    }

    /**
     * @param $quoteId
     * @return Quote|null
     * @throws LocalizedException
     */
    public function loadQuoteData($quoteId)
    {
        try {
            /** @var Quote $quote */
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
     * @param Quote $quote
     * @param \stdClass $transaction
     * @return void
     * @throws NoSuchEntityException
     * @throws BoltException
     */
    public function validateQuoteData($quote, $transaction)
    {
        $this->validateMinimumAmount($quote);
        $this->validateCartItems($quote, $transaction);
        $this->validateTax($quote, $transaction);
        $this->validateShippingCost($quote, $transaction);
        $this->validateTotalAmount($quote, $transaction);
    }

    /**
     * Validate minimum order amount
     *
     * @param Quote $quote
     * @throws BoltException
     */
    public function validateMinimumAmount($quote)
    {
        if (!$quote->validateMinimumAmount()) {
            $minAmount = $this->configHelper->getMinimumOrderAmount($quote->getStoreId());
            $this->bugsnag->registerCallback(function ($report) use ($quote, $minAmount) {
                $report->setMetaData([
                    'Pre Auth' => [
                        'Minimum order amount' => $minAmount,
                        'Subtotal' => $quote->getSubtotal(),
                        'Subtotal with discount' => $quote->getSubtotalWithDiscount(),
                        'Total' => $quote->getGrandTotal(),
                    ]
                ]);
            });
            throw new BoltException(
                __('The minimum order amount: %1 has not being met.', $minAmount),
                null,
                self::E_BOLT_MINIMUM_PRICE_NOT_MET
            );
        }
    }

    /**
     * Get values that are in either array, but not in both of them.
     * Used for comparing / validating item SKU arrays
     *
     * @param array $A
     * @param array $B
     * @return array
     */
    private function arrayDiff($A, $B)
    {
        $intersect = array_intersect($A, $B);
        return array_merge(array_diff($A, $intersect), array_diff($B, $intersect));
    }

    /**
     * @param Quote  $quote
     * @param               $transaction
     * @throws BoltException
     * @throws NoSuchEntityException
     */
    public function validateCartItems($quote, $transaction)
    {
        $quoteItems = $quote->getAllVisibleItems();
        $transactionItems = $this->getCartItemsFromTransaction($transaction);
        $transactionItemsSkuQty = [];
        foreach ($transactionItems as $transactionItem) {
            $transactionItemsSkuQty[$this->getSkuFromTransaction($transactionItem)] =
                $this->getQtyFromTransaction($transactionItem);
        }

        $quoteSkus = array_map(
            function ($item) {
                return trim($item->getSku());
            },
            $quoteItems
        );

        $transactionSkus = array_keys($transactionItemsSkuQty);

        if ($diff = $this->arrayDiff($quoteSkus, $transactionSkus)) {
            throw new BoltException(
                __('Cart data has changed. SKU: ' . json_encode($diff)),
                null,
                self::E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED
            );
        }

        foreach ($quoteItems as $item) {
            /** @var QuoteItem $item */
            $sku = trim($item->getSku());
            $productId = $item->getProductId();
            $itemPrice = $this->cartHelper->getRoundAmount($item->getPrice());

            $this->hasItemErrors($item);
            $this->validateItemPrice($sku, $itemPrice, $transactionItems);
        }
    }

    /**
     * Check if quote have errors.
     *
     * @param QuoteItem $quoteItem
     * @return bool
     * @throws BoltException
     */
    public function hasItemErrors($quoteItem)
    {
        if (empty($quoteItem->getErrorInfos())) {
            return true;
        }

        $errorInfos = $quoteItem->getErrorInfos();
        $message = '';
        foreach ($errorInfos as $errorInfo) {
            $message .= '(' . $errorInfo['origin'] . '): ' . $errorInfo['message']->render() . PHP_EOL;
        }
        $errorCode = isset($errorInfos[0]['code']) ? $errorInfos[0]['code'] : 0;

        $this->bugsnag->registerCallback(function ($report) use ($message, $errorCode) {
            $report->setMetaData([
                'Pre Auth' => [
                    'quoteItem errors' => $message,
                    'Error Code' => $errorCode
                ]
            ]);
        });

        $boltErrorCode = self::E_BOLT_GENERAL_ERROR;
        if ($errorCode && ($errorCode === CatalogInventoryData::ERROR_QTY
                || $errorCode === CatalogInventoryData::ERROR_QTY_INCREMENTS)
        ) {
            $boltErrorCode = self::E_BOLT_ITEM_OUT_OF_INVENTORY;
        }

        throw new BoltException(
            __($message),
            null,
            $boltErrorCode
        );

        return false;
    }

    /**
     * @param string    $itemSku
     * @param int       $itemPrice
     * @param array     $transactionItems
     * @throws BoltException
     */
    public function validateItemPrice($itemSku, $itemPrice, &$transactionItems)
    {
        foreach ($transactionItems as $index => $transactionItem) {
            $transactionItemSku = $this->getSkuFromTransaction($transactionItem);
            $transactionUnitPrice = $this->getUnitPriceFromTransaction($transactionItem);

            if ($transactionItemSku === $itemSku &&
                abs($itemPrice - $transactionUnitPrice) <= OrderHelper::MISMATCH_TOLERANCE
            ) {
                unset ($transactionItems[$index]);
                return true;
            }
        }
        $this->bugsnag->registerCallback(function ($report) use ($itemPrice, $transactionUnitPrice) {
            $report->setMetaData([
                'Pre Auth' => [
                    'item.price' => $itemPrice,
                    'transaction.unit_price' => $transactionUnitPrice,
                ]
            ]);
        });
        throw new BoltException(
            __('Price does not match. Item sku: ' . $itemSku),
            null,
            self::E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED
        );
    }

    /**
     * @param Quote $quote
     * @param              $transaction
     * @return void
     * @throws BoltException
     */
    public function validateTax($quote, $transaction)
    {
        $transactionTax = $this->getTaxAmountFromTransaction($transaction);
        /** @var Quote\Address $address */
        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $tax = $this->cartHelper->getRoundAmount($address->getTaxAmount());

        if (abs($transactionTax - $tax) > OrderHelper::MISMATCH_TOLERANCE) {
            $this->bugsnag->registerCallback(function ($report) use ($tax, $transactionTax) {
                $report->setMetaData([
                    'Pre Auth' => [
                        'shipping.tax_amount' => $tax,
                        'transaction.tax_amount' => $transactionTax,
                    ]
                ]);
            });
            throw new BoltException(
                __('Cart Tax mismatched.'),
                null,
                self::E_BOLT_GENERAL_ERROR
            );
        }
    }

    /**
     * @param Quote $quote
     * @param \stdClass $transaction
     * @return void
     * @throws BoltException
     */
    public function validateShippingCost($quote, $transaction)
    {
        if ($quote->getShippingAddress() && !$quote->isVirtual()) {
            $amount = $quote->getShippingAddress()->getShippingAmount();
            if (! $this->isBackOfficeOrder($quote)) {
                $amount -= $quote->getShippingAddress()->getShippingDiscountAmount();
            }
            $storeCost = $this->cartHelper->getRoundAmount($amount);
        } else {
            $storeCost = 0;
        }

        $boltCost = $this->getShippingAmountFromTransaction($transaction);

        if (abs($storeCost - $boltCost) > OrderHelper::MISMATCH_TOLERANCE) {
            $this->bugsnag->registerCallback(function ($report) use ($storeCost, $boltCost) {
                $report->setMetaData([
                    'Pre Auth' => [
                        'shipping.shipping_amount' => $storeCost,
                        'transaction.shipping_amount' => $boltCost,
                    ]
                ]);
            });
            throw new BoltException(
                __('Shipping total has changed. Old value: ' . $boltCost . ', new value: ' . $storeCost),
                null,
                self::E_BOLT_SHIPPING_EXPIRED
            );
        }
    }

    /**
     * @param Quote $quote
     * @param \stdClass $transaction
     * @return void
     * @throws BoltException
     */
    public function validateTotalAmount($quote, $transaction)
    {
        $quoteTotal = $this->cartHelper->getRoundAmount($quote->getGrandTotal());
        $transactionTotal = $this->getTotalAmountFromTransaction($transaction);

        if ($quoteTotal != $transactionTotal) {
            $this->bugsnag->registerCallback(function ($report) use ($quoteTotal, $transactionTotal) {
                $report->setMetaData([
                    'Pre Auth' => [
                        'quote.total_amount' => $quoteTotal,
                        'transaction.total_amount' => $transactionTotal,
                    ]
                ]);
            });
            throw new BoltException(
                __('Total amount does not match.'),
                null,
                self::E_BOLT_GENERAL_ERROR
            );
        }
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

    /**
     * @param \stdClass $transaction
     * @return array
     */
    protected function getCartItemsFromTransaction($transaction)
    {
        return $transaction->order->cart->items;
    }

    /**
     * @param \stdClass $transaction
     * @return int
     */
    protected function getTaxAmountFromTransaction($transaction)
    {
        return $transaction->order->cart->tax_amount->amount;
    }

    /**
     * @param \stdClass $transactionItem
     * @return int
     */
    protected function getUnitPriceFromTransaction($transactionItem)
    {
        return $transactionItem->unit_price->amount;
    }

    /**
     * @param \stdClass $transactionItem
     * @return string
     */
    protected function getSkuFromTransaction($transactionItem)
    {
        return $transactionItem->sku;
    }

    /**
     * @param \stdClass $transactionItem
     * @return string
     */
    protected function getQtyFromTransaction($transactionItem)
    {
        return $transactionItem->quantity;
    }

    /**
     * @param \stdClass $transaction
     * @return int
     */
    protected function getShippingAmountFromTransaction($transaction)
    {
        if (isset($transaction->order->cart->shipping_amount->amount)) {
            return (int) $transaction->order->cart->shipping_amount->amount;
        }
        return 0;
    }

    /**
     * @param \stdClass $transaction
     * @return int
     */
    protected function getTotalAmountFromTransaction($transaction)
    {
        return $transaction->order->cart->total_amount->amount;
    }
}
