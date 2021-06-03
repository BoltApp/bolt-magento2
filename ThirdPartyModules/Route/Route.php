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
use Magento\Quote\Model\Quote;
use Route\Route\Helper\Data as RouteDataHelper;

/**
 * Class Route
 *
 * @package Bolt\Boltpay\ThirdPartyModules\Route
 */
class Route
{
    use \Bolt\Boltpay\Model\ThirdPartyEvents\FiltersCartItems;
    use \Bolt\Boltpay\Model\ThirdPartyEvents\FiltersTransactionBeforeValidation;
    use \Bolt\Boltpay\Model\ThirdPartyEvents\FiltersCartBeforeLegacyShippingAndTax;

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
        if ($totals && key_exists(RouteDataHelper::ROUTE_FEE, $totals)
            && $fee = $quote->getData(RouteDataHelper::ROUTE_FEE)) {
            $currencyCode = $quote->getQuoteCurrencyCode();

            $unitPrice = $fee;
            $itemTotalAmount = $unitPrice * 1;

            $roundedTotalAmount = CurrencyUtils::toMinor($itemTotalAmount, $currencyCode);

            $diff += CurrencyUtils::toMinorWithoutRounding($itemTotalAmount, $currencyCode) - $roundedTotalAmount;

            $totalAmount += $roundedTotalAmount;

            $product = [
                'reference'    => RouteDataHelper::ROUTE_FEE,
                'name'         => $routeDataHelper->getRouteLabel(),
                'total_amount' => $roundedTotalAmount,
                'unit_price'   => CurrencyUtils::toMinor($unitPrice, $currencyCode),
                'quantity'     => 1,
                'image_url'    => 'https://cdn.routeapp.io/route-widget/images/RouteLogoIcon.png',
            ];

            $products[] = $product;
        }
        return [$products, $totalAmount, $diff];
    }
    
    /**
     * Filters Bolt transaction received during the order creation hook
     *
     * @see \Bolt\Boltpay\Model\Api\CreateOrder::validateQuoteData
     *
     * @param \stdClass $transaction Bolt transaction object
     *
     * @return \stdClass either changed or unchanged transaction object
     */
    public function filterTransactionBeforeOrderCreateValidation($transaction)
    {
        $transaction->order->cart->items = array_filter(
            $transaction->order->cart->items,
            function ($item) {
                return $item->reference !== RouteDataHelper::ROUTE_FEE;
            }
        );
        return $transaction;
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
        $cart['items'] = array_filter(
            $cart['items'],
            function ($item) {
                return $item['reference'] !== RouteDataHelper::ROUTE_FEE;
            }
        );
        return $cart;
    }
}
