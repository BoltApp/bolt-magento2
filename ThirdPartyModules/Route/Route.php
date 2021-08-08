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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\Route;

use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Helper\Session as BoltSession;
use Route\Route\Helper\Data as RouteDataHelper;
use Magento\Quote\Model\Quote;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;

/**
 * Class Route
 *
 * @package Bolt\Boltpay\ThirdPartyModules\Route
 */
class Route
{
    use \Bolt\Boltpay\Model\ThirdPartyEvents\FiltersCartItems;
    use \Bolt\Boltpay\Model\ThirdPartyEvents\FiltersCartBeforeLegacyShippingAndTax;
    use \Bolt\Boltpay\Model\ThirdPartyEvents\FiltersItemsForUpdateCartApi;
    
    const ROUTE_PRODUCT_ID = 'ROUTEINS';
    
    /**
     * @var CacheInterface
     */
    private $cache;
    
    /**
     * @var BoltSession
     */
    private $boltSessionHelper;
    
    /**
     * @var State
     */
    private $appState;

    /**
     * @param CacheInterface  $cache
     * @param BoltSession     $boltSessionHelper
     */
    public function __construct(
        CacheInterface  $cache,
        BoltSession     $boltSessionHelper,
        State           $appState
    ) {
        $this->cache = $cache;
        $this->boltSessionHelper = $boltSessionHelper;
        $this->appState = $appState;
    }

    /**
     * @param array|array[]|int[] $result containing collected cart items, total amount and diff
     * @param RouteDataHelper     $routeDataHelper
     * @param Quote               $quote
     * @param int                 $storeId
     *
     * @return array|array[]|int[] changed or unchanged $result
     * @throws \Exception
     */
    public function filterCartItems($result, $routeDataHelper, $quote, $storeId, $ifOnlyVisibleItems)
    {
        if (!$ifOnlyVisibleItems) {
            return $result;
        }
        list($products, $totalAmount, $diff) = $result;
        $totals = $quote->getTotals();
        $routeFeeEnabled = '0';
        if ($totals && key_exists(RouteDataHelper::ROUTE_FEE, $totals)
            && $fee = $quote->getData(RouteDataHelper::ROUTE_FEE)) {
            $currencyCode = $quote->getQuoteCurrencyCode();
            $unitPrice = $fee;
            $itemTotalAmount = $unitPrice * 1;
            $roundedTotalAmount = CurrencyUtils::toMinor($itemTotalAmount, $currencyCode);
            $diff += CurrencyUtils::toMinorWithoutRounding($itemTotalAmount, $currencyCode) - $roundedTotalAmount;
            $totalAmount += $roundedTotalAmount;
            $product = [
                'reference'    => self::ROUTE_PRODUCT_ID,
                'name'         => $routeDataHelper->getRouteLabel(),
                'total_amount' => $roundedTotalAmount,
                'unit_price'   => CurrencyUtils::toMinor($unitPrice, $currencyCode),
                'quantity'     => 1,
                'image_url'    => 'https://cdn.routeapp.io/route-widget/images/RouteLogoIcon.png',
            ];
            $products[] = $product;
            $routeFeeEnabled = '1';
        }
        
        if ($this->appState->getAreaCode() !== Area::AREA_WEBAPI_REST) {
            $cacheIdentifier = self::ROUTE_PRODUCT_ID . $quote->getBoltParentQuoteId();
            $this->cache->save($routeFeeEnabled, $cacheIdentifier, [], 86400);
        }
            
        return [$products, $totalAmount, $diff];
    }

    /**
     * Filter the Cart portion of the transaction before Legacy Shipping and Tax functionality is executed
     * Remove a dummy product representing the Route Fee additional total
     *
     * @see \Bolt\Boltpay\Model\Api\ShippingMethods::getShippingAndTax
     *
     * @param array $cart portion of the transaction object from Bolt
     *
     * @return array either changed or unchanged cart array
     */
    public function filterCartBeforeLegacyShippingAndTax($cart)
    {
        $parentQuoteId = $cart['order_reference'];
        $cart['items'] = array_filter(
            $cart['items'],
            function ($item) {
                $this->saveCacheInsured($item['reference'], $parentQuoteId);
                return $item['reference'] !== self::ROUTE_PRODUCT_ID;
            }
        );
        
        return $cart;
    }
    
    public function filterCartBeforeSplitShippingAndTax($cart)
    {
        $parentQuoteId = $cart['order_reference'];
        $cart['items'] = array_filter(
            $cart['items'],
            function ($item) use($parentQuoteId) {
                $this->saveCacheInsured($item['reference'], $parentQuoteId);
                return $item['reference'] !== self::ROUTE_PRODUCT_ID;
            }
        );
        
        return $cart;
    }
    
    public function filterCartBeforeCreateOrder($transaction)
    {
        $parentQuoteId = $transaction->order->cart->order_reference;
        $transaction->order->cart->items = array_filter(
            $transaction->order->cart->items,
            function ($item) use($parentQuoteId) {
                $this->saveCacheInsured($item->reference, $parentQuoteId);
                return $item->reference !== self::ROUTE_PRODUCT_ID;
            }
        );
        
        return $transaction;
    }
    
    /**
     * Filter the item before adding to cart.
     *
     * @see \Bolt\Boltpay\Model\Api\UpdateCart::execute
     * 
     * @param bool $flag
     * @param array $addItem
     * @param \Magento\Checkout\Model\Session $checkoutSession
     *
     * @return bool if return true, then move to next item, if return false, just process the normal execution.
     */
    public function filterAddItemBeforeUpdateCart($result, $addItem, $checkoutSession)
    {
        if ($addItem['product_id'] == self::ROUTE_PRODUCT_ID) {
            $checkoutSession->setInsured(true);
            $cacheIdentifier = self::ROUTE_PRODUCT_ID . $checkoutSession->getQuote()->getBoltParentQuoteId();
            $this->cache->save('1', $cacheIdentifier, [], 86400);
            return true;
        }
        
        return $result;
    }
    
    /**
     * Filter the item before removing from cart.
     *
     * @see \Bolt\Boltpay\Model\Api\UpdateCart::execute
     * 
     * @param bool $flag
     * @param array $removeItem
     * @param \Magento\Checkout\Model\Session $checkoutSession
     *
     * @return bool if return true, then move to next item, if return false, just process the normal execution.
     */
    public function filterRemoveItemBeforeUpdateCart($result, $removeItem, $checkoutSession)
    {
        if ($removeItem['product_id'] == self::ROUTE_PRODUCT_ID) {
            $checkoutSession->setInsured(false);
            $cacheIdentifier = self::ROUTE_PRODUCT_ID . $checkoutSession->getQuote()->getBoltParentQuoteId();
            $this->cache->save('0', $cacheIdentifier, [], 86400);
            return true;
        }
        
        return $result;
    }
    
    /**
     * .
     *
     * @param Quote $quote
     */
    public function afterLoadSession($quote)
    {
        $cacheIdentifier = self::ROUTE_PRODUCT_ID . $quote->getBoltParentQuoteId();
        $routeFeeEnabled = $this->cache->load($cacheIdentifier);
        $checkoutSession = $this->boltSessionHelper->getCheckoutSession();
        if ($routeFeeEnabled !== null) {
            $checkoutSession->setInsured(filter_var($routeFeeEnabled, FILTER_VALIDATE_BOOLEAN));
        }
    }
    
    private function saveCacheInsured($itemReference, $parentQuoteId)
    {
        if($itemReference == self::ROUTE_PRODUCT_ID){
            $this->cache->save('1', self::ROUTE_PRODUCT_ID . $parentQuoteId, [], 86400);
        }
    }
}
