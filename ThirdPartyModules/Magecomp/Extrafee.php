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

namespace Bolt\Boltpay\ThirdPartyModules\Magecomp;

use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Quote\Model\Quote;

/**
 * Class Extrafee
 *
 * @package Bolt\Boltpay\ThirdPartyModules\Magecomp
 */
class Extrafee
{
    use \Bolt\Boltpay\Model\ThirdPartyEvents\FiltersCartItems;
    use \Bolt\Boltpay\Model\ThirdPartyEvents\FiltersTransactionBeforeValidation;
    use \Bolt\Boltpay\Model\ThirdPartyEvents\FiltersCartBeforeLegacyShippingAndTax;

    /**
     * @var string total code used by the Extrafee module
     */
    const EXTRA_FEE_TOTAL_CODE = 'fee';

    /**
     * @var string Bolt product reference used for dummy products that represents Extrafee total
     */
    const EXTRA_FEE_REFERENCE = 'third_party_' . self::EXTRA_FEE_TOTAL_CODE;

    /**
     * @param array|array[]|int[] $result containing collected cart items, total amount and diff
     * @param Quote               $quote
     * @param int                 $storeId
     * @param bool                $ifOnlyVisibleItems
     *
     * @return array|array[]|int[] changed or unchanged $result
     * @throws \Exception
     */
    public function filterCartItems($result, $quote, $storeId, $ifOnlyVisibleItems)
    {
        list($products, $totalAmount, $diff) = $result;

        $totals = $quote->getTotals();
        if ($totals && key_exists(self::EXTRA_FEE_TOTAL_CODE, $totals)
            && $fee = $quote->getData(self::EXTRA_FEE_TOTAL_CODE)) {
            $currencyCode = $quote->getQuoteCurrencyCode();

            $unitPrice = $fee;
            $itemTotalAmount = $unitPrice * 1;

            $roundedTotalAmount = CurrencyUtils::toMinor($itemTotalAmount, $currencyCode);

            $diff += CurrencyUtils::toMinorWithoutRounding($itemTotalAmount, $currencyCode) - $roundedTotalAmount;

            $totalAmount += $roundedTotalAmount;

            $product = [
                'reference'    => self::EXTRA_FEE_REFERENCE,
                'name'         => $totals[self::EXTRA_FEE_TOTAL_CODE]->getTitle(),
                'total_amount' => $roundedTotalAmount,
                'unit_price'   => CurrencyUtils::toMinor($unitPrice, $currencyCode),
                'quantity'     => 1
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
                return $item->reference !== self::EXTRA_FEE_REFERENCE;
            }
        );
        return $transaction;
    }

    /**
     * Filter the Cart portion of the transaction before Legacy Shipping and Tax functionality is executed
     * Remove a dummy product representing the Extrafee additional total
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
                return $item['reference'] !== self::EXTRA_FEE_REFERENCE;
            }
        );
        return $cart;
    }
}
