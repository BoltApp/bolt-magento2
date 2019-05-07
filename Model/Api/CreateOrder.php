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
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;

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
     * @param HookHelper             $hookHelper
     * @param OrderHelper            $orderHelper
     * @param CartHelper             $cartHelper
     * @param LogHelper              $logHelper
     * @param Request                $request
     * @param Bugsnag                $bugsnag
     * @param Response               $response
     * @param UrlInterface           $url
     * @param BackendUrl             $backendUrl
     * @param ConfigHelper           $configHelper
     * @param StockRegistryInterface $stockRegistry
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
        BackendUrl $backendUrl,
        ConfigHelper $configHelper,
        StockRegistryInterface $stockRegistry
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
        $this->backendUrl = $backendUrl;
        $this->stockRegistry = $stockRegistry;
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

            $this->validateHook();

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

            // Convert to stdClass
            $transaction = json_decode($payload);

            /** @var Quote $quote */
            $quote = $this->orderHelper->prepareQuote($immutableQuote, $transaction);
            $this->validateQuoteData($quote, $transaction);

            /** @var \Magento\Sales\Model\Order $createOrderData */
            $createOrderData = $this->orderHelper->preAuthCreateOrder($quote, $transaction);
            $orderReference = $this->getOrderReference($order);

            $this->sendResponse(200, [
                'status'    => 'success',
                'message'   => 'Order create was successful',
                'display_id' => $createOrderData->getIncrementId() . ' / ' . $quote->getId(),
                'total'      => $this->cartHelper->getRoundAmount($createOrderData->getGrandTotal()),
                'order_received_url' => $this->getReceivedUrl($quote),
            ]);
        } catch (\Magento\Framework\Webapi\Exception $e) {
            $this->bugsnag->notifyException($e);
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
     * Format redirect url taking into account the type of session
     * the order was created from, front-end vs. back-end
     *
     * @param Quote $quote
     * @return string
     */
    public function getReceivedUrl($quote)
    {
        $this->logHelper->addInfoLog('[-= getReceivedUrl =-]');
        $urlInterface = $this->isBackOfficeOrder($quote) ? $this->backendUrl : $this->url;
        $url = $urlInterface->getUrl('boltpay/order/receivedurl', ['_secure' => true]);
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
        $this->validateCartItems($quote, $transaction);

        $this->validateTax($quote, $transaction);
        $this->validateShippingCost($quote, $transaction);
        $this->validateTotalAmount($quote, $transaction);
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

        foreach ($quoteItems as $item) {
            /** @var QuoteItem $item */
            $sku = $item->getSku();
            $itemPrice = $this->cartHelper->getRoundAmount($item->getPrice());

            if (!in_array($sku, array_keys($transactionItemsSkuQty))) {
                throw new BoltException(
                    __('Cart data has changed. SKU: ' . $sku),
                    null,
                    self::E_BOLT_GENERAL_ERROR
                );
            }

            $this->validateItemInventory($sku, $transactionItemsSkuQty[$sku]);

            $this->validateItemPrice($sku, $itemPrice, $transactionItems);
        }
    }

    /**
     * @param string $itemSku
     * @param int $itemQty
     * @throws BoltException
     * @throws NoSuchEntityException
     */
    public function validateItemInventory($itemSku, $itemQty)
    {
        $stockItem = $this->stockRegistry->getStockItemBySku($itemSku);

        if (!$stockItem->getManageStock()) return;

        $inStock = $stockItem->getIsInStock();
        $stockQty = $stockItem->getQty();

        if (!$inStock || $stockQty < $itemQty) {
            $this->bugsnag->registerCallback(function ($report) use ($stockQty, $itemQty) {
                $report->setMetaData([
                    'Pre Auth' => [
                        'stock.quantity' => $stockQty,
                        'cart.quantity' => $itemQty,
                    ]
                ]);
            });
            throw new BoltException(
                __('Item is out of stock. Item sku: ' . $itemSku . ' Stock qty: ' . $stockQty . ' Cart qty: ' . $itemQty),
                null,
                self::E_BOLT_ITEM_OUT_OF_INVENTORY
            );
        }
    }

    /**
     * @param string    $itemSku
     * @param int       $itemPrice
     * @param array     $transactionItems
     * @throws BoltException
     */
    public function validateItemPrice($itemSku, $itemPrice, $transactionItems)
    {
        foreach ($transactionItems as $transactionItem) {
            $transactionItemSku = $this->getSkuFromTransaction($transactionItem);
            $transactionUnitPrice = $this->getUnitPriceFromTransaction($transactionItem);

            if ($transactionItemSku === $itemSku
                && abs($itemPrice - $transactionUnitPrice) > OrderHelper::MISMATCH_TOLERANCE
            ) {
                $this->bugsnag->registerCallback(function ($report) use ($itemPrice, $transactionUnitPrice) {
                    $report->setMetaData([
                        'Pre Auth' => [
                            'item.price' => $itemPrice,
                            'transaction.unit_price' => $transactionUnitPrice,
                        ]
                    ]);
                });
                throw new BoltException(
                    __('Price do not matched. Item sku: ' . $itemSku),
                    null,
                    self::E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED
                );
            }
        }
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
                __('Shipping costs do not match.'),
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
    public function validateTotalAmount($quote, $transaction)
    {
        $quoteTotal = $this->cartHelper->getRoundAmount($quote->getGrandTotal());
        $transactionTotal = $this->getTotalAmountFromTransaction($transaction);

        if (abs($quoteTotal - $transactionTotal) > OrderHelper::MISMATCH_TOLERANCE) {
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
        return (int)@$transaction->order->cart->shipping_amount->amount;
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
