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

namespace Bolt\Boltpay\ThirdPartyModules\MageWorx;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Helper\ArrayHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Bolt\Boltpay\Helper\Session as BoltSession;

class Donations
{
    const MAGEWORX_DONATION = 'MageWorxDonation';

    protected $_extrafeeCollectionFactory;

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var BoltSession
     */
    private $boltSessionHelper;

    /**
     * Core store config
     *
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /** @var \MageWorx\Donations\Helper\Donation */
    protected $donationHelper;

    /**
     * Donations constructor.
     * @param Bugsnag $bugsnagHelper
     * @param ScopeConfigInterface $scopeConfig
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
     * @param $cart
     * @param \MageWorx\Donations\Helper\Donation $donationHelper
     * @param $quote
     * @return mixed
     * @throws \Exception
     */
    public function filterCart(
        $cart,
        \MageWorx\Donations\Helper\Donation $donationHelper,
        \MageWorx\Donations\Model\ResourceModel\Charity\CollectionFactory $charityCollectionFactory,
        $quote
    )
    {
        $this->donationHelper = $donationHelper;
        $predefinedValuesDonations = $this->getPredefinedValuesDonation();
        $shippingAddress = $quote->getShippingAddress();
        $currencyCode = $quote->getQuoteCurrencyCode();

        $charityData = $charityCollectionFactory->create()->addFilter('is_active', 1)->getFirstItem();

        foreach ($predefinedValuesDonations as $key => $donation) {
            $roundUpName = '';
            
            if ($key == 0) {
                $roundUpName = '[Round Up To ' . $donation . ']';
            }

            $cart['add_ons'][] = [
                "name" => 'Donation for charity ' . $roundUpName,
                "description" => $charityData->getName(),
                "productId" => self::MAGEWORX_DONATION . '_' . $key,
                "price" => CurrencyUtils::toMinor($donation, $currencyCode),
            ];
        }
        return $cart;
    }

    public function getPredefinedValuesDonation()
    {
        $predefinedDonation = json_decode($this->scopeConfig->getValue('mageworx_donations/main/predefined_values_donation', 'store'), true);
        $roundUpValue = $this->donationHelper->getRoundUpValue();
        return array_merge([$roundUpValue], $predefinedDonation);
    }

    /**
     * @param $result
     * @param \MageWorx\Donations\Helper\Donation $donationHelper
     * @param $quote
     * @param $storeId
     * @return array
     * @throws \Exception
     */
    public function filterCartItems($result, \MageWorx\Donations\Helper\Donation $donationHelper, $quote, $storeId)
    {
        $this->donationHelper = $donationHelper;
        list($products, $totalAmount, $diff) = $result;

        $checkoutSession = $this->boltSessionHelper->getCheckoutSession();
        $shippingAddress = $checkoutSession->getQuote()->getShippingAddress();

        $mageworxDonationDetail = json_decode($shippingAddress->getMageworxDonationDetails(), true);
        if (isset($mageworxDonationDetail['option']) && $mageworxDonationDetail['option'] && $mageworxDonationDetail['global_donation'] > 0) {
            foreach (array_unique($mageworxDonationDetail['option']) as $option) {
                $currencyCode = $quote->getQuoteCurrencyCode();
                $unitPrice = ($option == 0) ? @$mageworxDonationDetail['bolt_round_up'] : $this->getPredefinedValuesDonation()[$option];

                $itemTotalAmount = $unitPrice * 1;
                $roundedTotalAmount = CurrencyUtils::toMinor($itemTotalAmount, $currencyCode);
                $diff += CurrencyUtils::toMinorWithoutRounding($itemTotalAmount, $currencyCode) - $roundedTotalAmount;
                $totalAmount += $roundedTotalAmount;
                $product = [
                    'reference' => self::MAGEWORX_DONATION . '_' . $option,
                    'name' => 'Donation for charity: ' . $mageworxDonationDetail['charity_title'],
                    'sku' => self::MAGEWORX_DONATION . '_' . $option,
                    'description' => '',
                    'total_amount' => $roundedTotalAmount,
                    'unit_price' => CurrencyUtils::toMinor($unitPrice, $currencyCode),
                    'quantity' => 1,
                    'type' => 'digital'
                ];
                $products[] = $product;
            }
        }

        if ($checkoutSession instanceof \Magento\Backend\Model\Session\Quote) {
            $mageworxDonationDetail = $checkoutSession->getMageworxDonationDetails();
            if (isset($mageworxDonationDetail['global_donation']) && $mageworxDonationDetail['global_donation'] > 0) {
                $currencyCode = $quote->getQuoteCurrencyCode();
                $unitPrice = @$mageworxDonationDetail['global_donation'];
                $itemTotalAmount = $unitPrice * 1;
                $roundedTotalAmount = CurrencyUtils::toMinor($itemTotalAmount, $currencyCode);
                $diff += CurrencyUtils::toMinorWithoutRounding($itemTotalAmount, $currencyCode) - $roundedTotalAmount;
                $totalAmount += $roundedTotalAmount;

                $product = [
                    'reference' => self::MAGEWORX_DONATION . '_' . $unitPrice,
                    'name' => 'Donation for charity: ' . $mageworxDonationDetail['charity_title'],
                    'sku' => self::MAGEWORX_DONATION . '_' . $unitPrice,
                    'description' => '',
                    'total_amount' => $roundedTotalAmount,
                    'unit_price' => CurrencyUtils::toMinor($unitPrice, $currencyCode),
                    'quantity' => 1,
                    'type' => 'digital'
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
                    return strpos($itemReference, self::MAGEWORX_DONATION) === false;
                }
            );
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return $cartItems;
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
    public function filterAddItemBeforeUpdateCart(
        $result,
        \MageWorx\Donations\Helper\Donation $donationHelper,
        \MageWorx\Donations\Model\ResourceModel\Charity\CollectionFactory $charityCollectionFactory,
        $addItem,
        $checkoutSession
    )
    {
        if (strpos($addItem['product_id'], self::MAGEWORX_DONATION) !== false) {
            $charityData = $charityCollectionFactory->create()->addFilter('is_active', 1)->getFirstItem();

            $this->donationHelper = $donationHelper;
            $donationData = explode('_', $addItem['product_id']);
            $donationId = $donationData[1];
            $amount = CurrencyUtils::toMajor($addItem['price'], $checkoutSession->getQuote()->getQuoteCurrencyCode());
            $shippingAddress = $checkoutSession->getQuote()->getShippingAddress();
            $mageworxDonationDetails = json_decode($shippingAddress->getMageworxDonationDetails(), true);
            $existMageworxDonationAmount = $shippingAddress->getBaseMageworxDonationAmount();
            $mageworxDonationDetailsData = [];
            if ($existMageworxDonationAmount > 0) {
                $mageworxDonationDetails['option'][] = $donationId;

                $mageworxDonationDetailsData =
                    [
                        'global_donation' => $amount + $existMageworxDonationAmount,
                        'donation' => $amount + $existMageworxDonationAmount,
                        'donation_roundup' => 0,
                        'isUseDonationRoundUp' => false,
                        'charity_id' => $charityData->getId(),
                        'charity_title' => $charityData->getName(),
                        'option' => array_unique($mageworxDonationDetails['option']),
                        'bolt_round_up' => @$mageworxDonationDetails['bolt_round_up'],
                    ];

            } else {
                $mageworxDonationDetailsData = [
                    'global_donation' => $amount,
                    'donation' => $amount,
                    'donation_roundup' => 0,
                    'isUseDonationRoundUp' => false,
                    'charity_id' => $charityData->getId(),
                    'charity_title' => $charityData->getName(),
                    'option' => [$donationId],
                    'bolt_round_up' => 0
                ];

            }
            if ($donationId == 0 && $amount > 0) {
                $mageworxDonationDetailsData['bolt_round_up'] = $amount;
            }
            $checkoutSession->setMageworxDonationDetails($mageworxDonationDetailsData);
            $checkoutSession->setTotalsCollectedFlag(false)->getQuote()->collectTotals();
            return true;
        }

        return $result;
    }

    /**
     * @param $result
     * @param \MageWorx\Donations\Helper\Donation $donationHelper
     * @param $removeItem
     * @param $checkoutSession
     * @return bool
     */
    public function filterRemoveItemBeforeUpdateCart(
        $result,
        \MageWorx\Donations\Helper\Donation $donationHelper,
        $removeItem,
        $checkoutSession
    )
    {
        $this->donationHelper = $donationHelper;
        if (strpos($removeItem['product_id'], self::MAGEWORX_DONATION) !== false) {
            $removeItemInfo = explode('_', $removeItem['product_id']);
            $donationId = $removeItemInfo[1];
            $shippingAddress = $checkoutSession->getQuote()->getShippingAddress();
            $mageworxDonationDetails = json_decode($shippingAddress->getMageworxDonationDetails(), true);
            if ($donationId == 0 && isset($mageworxDonationDetails['bolt_round_up'])) {
                $removeAmount = $mageworxDonationDetails['bolt_round_up'];
                $mageworxDonationDetails['bolt_round_up'] = 0;
            } else {
                $removeAmount = $this->getPredefinedValuesDonation()[$donationId];
            }

            $mageworxDonationDetails['global_donation'] -= $removeAmount;
            $mageworxDonationDetails['donation'] -= $removeAmount;

            if (($key = array_search($donationId, $mageworxDonationDetails['option'])) !== false) {
                unset($mageworxDonationDetails['option'][$key]);
            }
            $checkoutSession->setMageworxDonationDetails($mageworxDonationDetails);
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
        $checkoutSession->setMageworxDonationDetails(json_decode($quote->getShippingAddress()->getMageworxDonationDetails(), true));
        $checkoutSession->setTotalsCollectedFlag(false);
    }
}
