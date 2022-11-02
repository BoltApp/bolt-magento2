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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\Route;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\ArrayHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider as DeciderHelper;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Helper\Session as BoltSession;
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
    const ROUTE_PRODUCT_ID = 'ROUTEINS';
    const ROUTE_FEE = 'route_fee';
    
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
     * @var Bugsnag Bugsnag helper instance
     */
    private $bugsnagHelper;

    /**
     * @var DeciderHelper
     */
    private $deciderHelper;

    /**
     * @param CacheInterface  $cache
     * @param BoltSession     $boltSessionHelper
     * @param State           $appState
     * @param Bugsnag         $bugsnagHelper
     * @param DeciderHelper   $deciderHelper
     */
    public function __construct(
        CacheInterface  $cache,
        BoltSession     $boltSessionHelper,
        State           $appState,
        Bugsnag         $bugsnagHelper,
        DeciderHelper   $deciderHelper
    ) {
        $this->cache = $cache;
        $this->boltSessionHelper = $boltSessionHelper;
        $this->appState = $appState;
        $this->bugsnagHelper = $bugsnagHelper;
        $this->deciderHelper = $deciderHelper;
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
    public function filterCartItems($result, $routeDataHelper, $quote, $storeId)
    {
        list($products, $totalAmount, $diff) = $result;
        $totals = $quote->getTotals();
        $routeFeeEnabled = '0';
        if ($totals && key_exists(self::ROUTE_FEE, $totals)
            && $fee = $quote->getData(self::ROUTE_FEE)) {
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
        // Save status of Route fee (enable/disable) if the request comes from admin or frontend
        if ($this->appState->getAreaCode() !== Area::AREA_WEBAPI_REST) {
            $this->saveRouteFeeEnabledToCache(self::ROUTE_PRODUCT_ID, $quote->getBoltParentQuoteId(), $routeFeeEnabled);
        }
            
        return [$products, $totalAmount, $diff];
    }

    /**
     * Filter the Cart portion of the transaction before Legacy Shipping and Tax functionality is executed
     * Remove a dummy product representing the Route Fee additional total, also update cache if Route fee is enabled
     *
     * @see \Bolt\Boltpay\Model\Api\ShippingMethods::getShippingAndTax
     *
     * @param array $cart portion of the transaction object from Bolt
     *
     * @return array either changed or unchanged cart array
     */
    public function filterCartBeforeLegacyShippingAndTax($cart)
    {
        $cart['items'] = $this->filterCartItemsInTransaction($cart['items'], $cart['order_reference']);
        
        return $cart;
    }
    
    /**
     * Filter the Cart portion of the transaction before Split Shipping and Tax functionality is executed
     * Remove a dummy product representing the Route Fee additional total, also update cache if Route fee is enabled
     *
     * @see \Bolt\Boltpay\Model\Api\ShippingTax::handleRequest
     *
     * @param array $cart portion of the transaction object from Bolt
     *
     * @return array either changed or unchanged cart array
     */
    public function filterCartBeforeSplitShippingAndTax($cart)
    {
        $cart['items'] = $this->filterCartItemsInTransaction($cart['items'], $cart['order_reference']);
        
        return $cart;
    }
    
    /**
     * Filter the transaction before Create Order functionality is executed
     * Remove a dummy product representing the Route Fee additional total, also update cache if Route fee is enabled
     *
     * @see \Bolt\Boltpay\Model\Api\CreateOrder::createOrder
     *
     * @param array $cart the transaction object from Bolt
     *
     * @return array either changed or unchanged $transaction object
     */
    public function filterCartBeforeCreateOrder($transaction)
    {
        $transaction->order->cart->items = $this->filterCartItemsInTransaction($transaction->order->cart->items, $transaction->order->cart->order_reference);
        
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
        return $this->saveRouteFeeEnabledBeforeUpdateCart($result, $addItem, $checkoutSession, '1');
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
        return $this->saveRouteFeeEnabledBeforeUpdateCart($result, $removeItem, $checkoutSession, '0');
    }
    
    /**
     * Get insured value of Route from cache and set to checkout session.
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
    
    /**
     * If Route fee is enabled, save '1' into cache; if disabled, then save '0'.
     *
     * @param string $itemReference
     * @param int|string $parentQuoteId
     * @param string $routeFeeEnabled
     */
    private function saveRouteFeeEnabledToCache($itemReference, $parentQuoteId, $routeFeeEnabled)
    {
        if($itemReference == self::ROUTE_PRODUCT_ID){
            $this->cache->save($routeFeeEnabled, self::ROUTE_PRODUCT_ID . $parentQuoteId, [], 86400);
        }
    }
    
    /**
     * Remove a dummy product representing the Route Fee additional total, also update cache if Route fee is enabled
     *
     * @param array|object $cartItems
     * @param string $parentQuoteId
     *
     * @return array|object
     */
    private function filterCartItemsInTransaction($cartItems, $parentQuoteId)
    {
        try {
            $cartItems = array_filter(
                $cartItems,
                function ($item) use($parentQuoteId) {
                    $itemReference = ArrayHelper::getValueFromArray($item, 'reference');
                    $this->saveRouteFeeEnabledToCache($itemReference, $parentQuoteId, '1');
                    return $itemReference !== self::ROUTE_PRODUCT_ID;
                }
            );
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return $cartItems;
    }
    
    /**
     * Add Route fee for Bolt PPC
     *
     * @see \Bolt\Boltpay\Model\Api\UpdateCart::execute
     *
     * @param \Route\Route\Model\Route\Merchant $merchantClient
     * @param \Route\Route\Helper\Data $routeDataHelper
     * @param Quote $quote
     * @param \Magento\Checkout\Model\Session $checkoutSession
     *
     */
    public function beforeGetCartDataForCreateCart($merchantClient, $routeDataHelper, $quote, $checkoutSession)
    {
        if (!$routeDataHelper->isRouteModuleEnable()) {
            return false;
        }

        if ($routeDataHelper->isFullCoverage() || $merchantClient->isOptOut()) {
            $checkoutSession->setInsured(true);
        }

        if ($this->deciderHelper->isAPIDrivenIntegrationEnabled()) {
            $quote->collectTotals();
        }
    }
    
    private function saveRouteFeeEnabledBeforeUpdateCart($flag, $item, $checkoutSession, $routeFeeEnabled)
    {
        if ($item['product_id'] == self::ROUTE_PRODUCT_ID) {
            $checkoutSession->setInsured(filter_var($routeFeeEnabled, FILTER_VALIDATE_BOOLEAN));
            $this->saveRouteFeeEnabledToCache(self::ROUTE_PRODUCT_ID, $checkoutSession->getQuote()->getBoltParentQuoteId(), $routeFeeEnabled);
            return true;
        }
        
        return $flag;
    }
}
