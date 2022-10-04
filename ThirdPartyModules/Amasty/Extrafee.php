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

namespace Bolt\Boltpay\ThirdPartyModules\Amasty;

use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\ArrayHelper;

class Extrafee
{
    const AMASTY_EXTRAFEE = 'amasty_extrafee';
    const AMASTY_EXTRAFEE_PREFIX = 'AmastyExtraFee';

    protected $_extrafeeCollectionFactory;
    private $bugsnagHelper;

    /**
     * Extrafee constructor.
     * @param Bugsnag $bugsnagHelper
     */
    public function __construct(
        Bugsnag $bugsnagHelper
    )
    {
        $this->bugsnagHelper = $bugsnagHelper;
    }

    /**
     * @param $cart
     * @param $amastyFeesInformationManagement
     * @param $quote
     * @return mixed
     * @throws \Exception
     */
    public function filterCart(
        $cart,
        $amastyFeesInformationManagement,
        $quote
    )
    {
        $fees = $amastyFeesInformationManagement->collectQuote($quote);
        $currencyCode = $quote->getQuoteCurrencyCode();
        $cart['add_ons'] = [];
        foreach ($fees as $fee) {
            $feeId = $fee['entity_id'];
            foreach ($fee['base_options'] as $option) {
                $optionId = $option['index'];
                $cart['add_ons'][] = [
                    "name" => $option['label'],
                    "description" => $option['label'],
                    "productId" => self::AMASTY_EXTRAFEE_PREFIX . '_' . $feeId . '_' . $optionId,
                    "imageUrl" => "",
                    "price" => CurrencyUtils::toMinor($option['price'], $currencyCode),
                ];
            }
        }
        return $cart;
    }

    /**
     * @param $result
     * @param $extrafeeCollectionFactory
     * @param $amastyTotalsInformationManagement
     * @param $quote
     * @param $storeId
     * @return array
     * @throws \Exception
     */
    public function filterCartItems($result, $extrafeeCollectionFactory, $amastyTotalsInformationManagement, $quote, $storeId)
    {

        list($products, $totalAmount, $diff) = $result;
        $totals = $quote->getTotals();
        if ($totals && key_exists(self::AMASTY_EXTRAFEE, $totals) && $totals[self::AMASTY_EXTRAFEE]['value'] > 0) {
            $this->_extrafeeCollectionFactory = $extrafeeCollectionFactory;
            $feeDatas = $this->getFeesDataFromQuote($quote)->addFieldToFilter('option_id', ['neq' => '0'])->getItems();
            foreach ($feeDatas as $feeOption) {
                $currencyCode = $quote->getQuoteCurrencyCode();
                $unitPrice = $feeOption->getFeeAmount();
                $itemTotalAmount = $unitPrice * 1;
                $roundedTotalAmount = CurrencyUtils::toMinor($itemTotalAmount, $currencyCode);
                $diff += CurrencyUtils::toMinorWithoutRounding($itemTotalAmount, $currencyCode) - $roundedTotalAmount;
                $totalAmount += $roundedTotalAmount;
                $product = [
                    'reference' => self::AMASTY_EXTRAFEE_PREFIX . '_' . $feeOption->getFeeId() . '_' . $feeOption->getOptionId(),
                    'image_url' => '',
                    'name' => $feeOption->getLabel(),
                    'sku' => self::AMASTY_EXTRAFEE_PREFIX . '_' . $feeOption->getFeeId() . '_' . $feeOption->getOptionId(),
                    'description' => '',
                    'total_amount' => $roundedTotalAmount,
                    'unit_price' => CurrencyUtils::toMinor($unitPrice, $currencyCode),
                    'quantity' => 1,
                    'type' => 'physical'
                ];
                $products[] = $product;
            }
        }


        return [$products, $totalAmount, $diff];
    }

    /**
     * @param $cart
     * @return mixed
     */
    public function filterCartBeforeLegacyShippingAndTax($cart)
    {
        $cart['items'] = $this->filterCartItemsInTransaction($cart['items'], $cart['order_reference']);

        return $cart;
    }

    /**
     * @param $cart
     * @return mixed
     */
    public function filterCartBeforeSplitShippingAndTax($cart)
    {
        $cart['items'] = $this->filterCartItemsInTransaction($cart['items'], $cart['order_reference']);

        return $cart;
    }

    /**
     * @param $transaction
     * @return mixed
     */
    public function filterCartBeforeCreateOrder($transaction)
    {
        $transaction->order->cart->items = $this->filterCartItemsInTransaction($transaction->order->cart->items, $transaction->order->cart->order_reference);

        return $transaction;
    }

    /**
     * @param $cartItems
     * @param $parentQuoteId
     * @return array
     */
    private function filterCartItemsInTransaction($cartItems, $parentQuoteId)
    {
        try {
            $cartItems = array_filter(
                $cartItems,
                function ($item) use ($parentQuoteId) {
                    $itemReference = ArrayHelper::getValueFromArray($item, 'reference');
                    return strpos($itemReference, self::AMASTY_EXTRAFEE_PREFIX) === false;
                }
            );
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return $cartItems;
    }

    /**
     * @param $extrafeeCollectionFactory
     * @param $extrafeeTotalsInformationManagement
     * @param $sourceQuote
     * @param $destinationQuote
     */
    public function replicateQuoteData($extrafeeCollectionFactory, $extrafeeTotalsInformationManagement, $sourceQuote, $destinationQuote)
    {
        $this->_extrafeeCollectionFactory = $extrafeeCollectionFactory;
        $feeDatas = $this->getFeesDataFromQuote($sourceQuote)->getData();

        if ($feeDatas) {
            $feeIds = array_unique(array_column($feeDatas, 'fee_id'));
            foreach ($feeIds as $feeId) {
                $feeOptions = $this->_extrafeeCollectionFactory->create()
                    ->addFieldToFilter('option_id', ['neq' => '0'])
                    ->addFieldToFilter('quote_id', $sourceQuote->getId())
                    ->addFieldToFilter('fee_id', $feeId)->getData();
                $optionIds = array_column($feeOptions, 'option_id');
                $extrafeeTotalsInformationManagement->proceedQuoteOptions($destinationQuote, $feeId, $optionIds);
            }
        }
    }

    /**
     * @param $quote
     * @return mixed
     */
    private function getFeesDataFromQuote($quote)
    {
        $feesQuoteCollection = $this->_extrafeeCollectionFactory->create()
            ->addFieldToFilter('quote_id', $quote->getId());

        return $feesQuoteCollection;
    }


    /**
     * Filter the item before adding to cart
     *
     * @param $result
     * @param $amastyTotalsInformationManagement
     * @param $amastyExtrafeeCollectionFactory
     * @param $addItem
     * @param $checkoutSession
     * @return bool
     */
    public function filterAddItemBeforeUpdateCart($result, $amastyTotalsInformationManagement, $amastyExtrafeeCollectionFactory, $addItem, $checkoutSession)
    {
        if (strpos($addItem['product_id'], self::AMASTY_EXTRAFEE_PREFIX) !== false) {
            $feeData = explode('_', $addItem['product_id']);
            $feeId = $feeData[1];
            $optionId = $feeData[2];
            $quote = $checkoutSession->getQuote();
            $feesQuoteCollection = $amastyExtrafeeCollectionFactory->create()
                ->addFieldToFilter('option_id', ['neq' => '0'])
                ->addFieldToFilter('quote_id', $quote->getId())
                ->addFieldToFilter('fee_id', $feeId);
            $optionIds = [];
            if ($feesQuoteCollection->getData()) {
                $optionIds = array_column($feesQuoteCollection->getData(),'option_id');
            }
            $optionIds[] = $optionId;
            $amastyTotalsInformationManagement->proceedQuoteOptions($quote, $feeId, array_unique($optionIds));
            return true;
        }

        return $result;
    }

    /**
     * @param $result
     * @param $amastyTotalsInformationManagement
     * @param $amastyExtrafeeCollectionFactory
     * @param $removeItem
     * @param $checkoutSession
     * @return bool
     */
    public function filterRemoveItemBeforeUpdateCart($result, $amastyTotalsInformationManagement, $amastyExtrafeeCollectionFactory, $removeItem, $checkoutSession)
    {
        if (strpos($removeItem['product_id'], self::AMASTY_EXTRAFEE_PREFIX) !== false) {
            $feeData = explode('_', $removeItem['product_id']);
            $feeId = $feeData[1];
            $removeOptionId = $feeData[2];
            $quote = $checkoutSession->getQuote();
            $feesQuoteCollection = $amastyExtrafeeCollectionFactory->create()
                ->addFieldToFilter('option_id', ['neq' => '0'])
                ->addFieldToFilter('quote_id', $quote->getId())
                ->addFieldToFilter('fee_id', $feeId);
            $optionIds = [];
            if ($feesQuoteCollection->getData()) {
                $optionIds = array_column($feesQuoteCollection->getData(),'option_id');
                if (($key = array_search($removeOptionId, $optionIds)) !== false) {
                    unset($optionIds[$key]);
                }
            }

            $amastyTotalsInformationManagement->proceedQuoteOptions($quote, $feeId, array_unique($optionIds));
            return true;
        }

        return $result;
    }

}
