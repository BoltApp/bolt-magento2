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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\MageWorx;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Helper\ArrayHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Bolt\Boltpay\Helper\Session as BoltSession;

class Donations
{
    const MAGEWORX_DONATION = 'MageWorxDonation';

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var BoltSession
     */
    private $boltSessionHelper;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    protected $donationHelper;

    /**
     * Donations constructor.
     * @param Bugsnag $bugsnagHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param BoltSession $boltSessionHelper
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        ScopeConfigInterface $scopeConfig,
        BoltSession $boltSessionHelper
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->bugsnagHelper = $bugsnagHelper;
        $this->boltSessionHelper = $boltSessionHelper;
    }

    /**
     * @param $result
     * @param $donationHelper
     * @param $quote
     * @param $storeId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function filterCartItems($result, $donationHelper, $quote, $storeId)
    {
        $this->donationHelper = $donationHelper;
        list($products, $totalAmount, $diff) = $result;

        $checkoutSession = $this->boltSessionHelper->getCheckoutSession();
        $shippingAddress = $checkoutSession->getQuote()->getShippingAddress();

        $mageworxDonationDetail = json_decode($shippingAddress->getMageworxDonationDetails(), true);
        if (isset($mageworxDonationDetail['global_donation']) && $mageworxDonationDetail['global_donation'] > 0) {
            $currencyCode = $quote->getQuoteCurrencyCode();
            $itemTotalAmount = $mageworxDonationDetail['global_donation'];
            $roundedTotalAmount = CurrencyUtils::toMinor($itemTotalAmount, $currencyCode);
            $diff += CurrencyUtils::toMinorWithoutRounding($itemTotalAmount, $currencyCode) - $roundedTotalAmount;
            $totalAmount += $roundedTotalAmount;
            $product = [
                'reference' => self::MAGEWORX_DONATION . '_' . 0,
                'name' => 'Donation for charity: ' . $mageworxDonationDetail['charity_title'],
                'sku' => self::MAGEWORX_DONATION . '_' . 0,
                'description' => '',
                'total_amount' => $roundedTotalAmount,
                'unit_price' => CurrencyUtils::toMinor($itemTotalAmount, $currencyCode),
                'quantity' => 1,
                'type' => 'digital'
            ];
            $products[] = $product;

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
                function ($item) use ($parentQuoteId) { // @phpstan-ignore-line
                    $itemReference = ArrayHelper::getValueFromArray($item, 'reference');
                    return strpos($itemReference, self::MAGEWORX_DONATION) === false;
                }
            );
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return $cartItems;
    }

    /**
     * @param $result
     * @param $donationHelper
     * @param $charityCollectionFactory
     * @param $addItem
     * @param $checkoutSession
     * @return bool
     * @throws \Exception
     */
    public function filterAddItemBeforeUpdateCart(
        $result,
        $donationHelper,
        $charityCollectionFactory,
        $addItem,
        $checkoutSession
    )
    {
        if (strpos($addItem['product_id'], self::MAGEWORX_DONATION) !== false) {
            $charityData = $charityCollectionFactory->create()->addFilter('is_active', 1)->addLocales()->getFirstItem();
            $this->donationHelper = $donationHelper;
            $amount = CurrencyUtils::toMajor($addItem['price'], $checkoutSession->getQuote()->getQuoteCurrencyCode());
            $shippingAddress = $checkoutSession->getQuote()->getShippingAddress();

            $mageworxDonationDetailsData = [
                'global_donation' => $amount,
                'donation' => $amount,
                'donation_roundup' => 0,
                'isUseDonationRoundUp' => false,
                'charity_id' => $charityData->getId(),
                'charity_title' => $charityData->getName(),
            ];

            $shippingAddress->setMageworxDonationDetails(json_encode($mageworxDonationDetailsData));
            $checkoutSession->setTotalsCollectedFlag(false)->getQuote()->collectTotals();
            return true;
        }

        return $result;
    }

    /**
     * @param $result
     * @param $donationHelper
     * @param $removeItem
     * @param $checkoutSession
     * @return bool
     */
    public function filterRemoveItemBeforeUpdateCart(
        $result,
        $donationHelper,
        $removeItem,
        $checkoutSession
    )
    {
        $this->donationHelper = $donationHelper;
        if (strpos($removeItem['product_id'], self::MAGEWORX_DONATION) !== false) {
            $mageworxDonationDetails = [];
            $shippingAddress = $checkoutSession->getQuote()->getShippingAddress();
            $mageworxDonationDetails['donation_roundup'] = 0;
            $mageworxDonationDetails['isUseDonationRoundUp'] = false;
            $mageworxDonationDetails['donation'] = 0;
            $mageworxDonationDetails['global_donation'] = 0;
            $shippingAddress->setMageworxDonationDetails(json_encode($mageworxDonationDetails));
            $checkoutSession->setTotalsCollectedFlag(false)->getQuote()->collectTotals();

            return true;
        }

        return $result;
    }

    /**
     * @param $quote
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function afterLoadSession($quote)
    {
        $checkoutSession = $this->boltSessionHelper->getCheckoutSession();
        $checkoutSession->getQuote()->getShippingAddress()->setMageworxDonationDetails($quote->getShippingAddress()->getMageworxDonationDetails());
        $checkoutSession->setTotalsCollectedFlag(false);
    }
}
