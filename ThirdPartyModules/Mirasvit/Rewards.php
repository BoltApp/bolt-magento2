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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\Mirasvit;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Helper\Discount;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Rewards
{
    /**
     * @var \Mirasvit\Rewards\Helper\Purchase
     */
    private $mirasvitRewardsPurchaseHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var Discount
     */
    private $discountHelper;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfigInterface;

    /**
     * Rewards constructor.
     * @param Bugsnag $bugsnagHelper
     * @param Discount $discountHelper
     * @param ScopeInterface $scopeConfigInterface
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        Discount $discountHelper,
        ScopeConfigInterface $scopeConfigInterface
    )
    {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->discountHelper = $discountHelper;
        $this->scopeConfigInterface = $scopeConfigInterface;
    }

    /**
     * @param $immutableQuote
     * @param $mirasvitRewardsPurchaseHelper
     */
    public function applyExternalDiscountData($immutableQuote, $mirasvitRewardsPurchaseHelper)
    {
        $this->mirasvitRewardsPurchaseHelper = $mirasvitRewardsPurchaseHelper;
        $this->applyMiravistRewardPoint($immutableQuote);
    }

    /**
     * @param $result
     * @param $mirasvitRewardsPurchaseHelper
     * @param $quote
     * @param $parentQuote
     * @param $paymentOnly
     * @return array
     */
    public function collectDiscounts(
        $result,
        $mirasvitRewardsPurchaseHelper,
        $quote,
        $parentQuote,
        $paymentOnly
    )
    {
        $this->mirasvitRewardsPurchaseHelper = $mirasvitRewardsPurchaseHelper;
        list ($discounts, $totalAmount, $diff) = $result;
        $discountType = $this->discountHelper->getBoltDiscountType('by_fixed');
        try {
            if ($amount = abs($this->getMirasvitRewardsAmount($parentQuote))) {
                $currencyCode = $parentQuote->getQuoteCurrencyCode();
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                $discounts[] = [
                    'description' =>
                        $this->scopeConfigInterface->getValue('rewards/general/point_unit_name', ScopeInterface::SCOPE_STORE, $quote->getStoreId()),
                    'amount' => $roundedAmount,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    'discount_type' => $discountType, // For v1/discounts.code.apply and v2/cart.update
                    'type' => $discountType, // For v1/merchant/order
                ];

                $diff -= CurrencyUtils::toMinorWithoutRounding($amount, $currencyCode) - $roundedAmount;
                $totalAmount -= $roundedAmount;
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return [$discounts, $totalAmount, $diff];
        }
    }

    /**
     * @param $result
     * @param $mirasvitRewardsPurchaseHelper
     * @param $quote
     * @return string
     */
    public function filterApplyExternalQuoteData($result, $mirasvitRewardsPurchaseHelper, $quote)
    {
        $this->mirasvitRewardsPurchaseHelper = $mirasvitRewardsPurchaseHelper;
        if ($rewardsAmount = $this->getMirasvitRewardsAmount($quote)) {
            $result .= $rewardsAmount;
        }
        return $result;
    }

    /**
     * Copy Miravist Reward Point data from parent quote to immutable quote.
     * The reward points are fetched from the 3rd party module DB table (mst_rewards_purchase)
     * and assigned to the parent quote temporarily (they are not persisted in the quote table).
     * The data needs to be set on the immutable quote before the quote totals are calculated
     * in the Shipping and Tax call in order to get correct tax
     *
     * @param $immutableQuote
     */
    private function applyMiravistRewardPoint($immutableQuote)
    {
        $parentQuoteId = $immutableQuote->getBoltParentQuoteId();
        /** @var \Mirasvit\Rewards\Helper\Purchase $mirasvitRewardsPurchaseHelper */
        $mirasvitRewardsPurchaseHelper = $this->mirasvitRewardsPurchaseHelper;
        if (!$mirasvitRewardsPurchaseHelper || !$parentQuoteId) {
            return;
        }

        try {
            $parentPurchase = $mirasvitRewardsPurchaseHelper->getByQuote($parentQuoteId);
            if (abs($parentPurchase->getSpendAmount()) > 0) {
                $mirasvitRewardsPurchaseHelper->getByQuote($immutableQuote)
                    ->setSpendPoints($parentPurchase->getSpendPoints())
                    ->setSpendMinAmount($parentPurchase->getSpendMinAmount())
                    ->setSpendMaxAmount($parentPurchase->getSpendMaxAmount())
                    ->setSpendAmount($parentPurchase->getSpendAmount())
                    ->setBaseSpendAmount($parentPurchase->getBaseSpendAmount())
                    ->save();
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }

    /**
     * If enabled, gets the Mirasvit Rewards amount used
     *
     * @param \Magento\Quote\Model\Quote $quote The parent quote of this order which contains Rewards points references
     *
     * @return float  If enabled, the currency amount used in the order, otherwise 0
     */
    private function getMirasvitRewardsAmount($quote)
    {
        /** @var \Mirasvit\Rewards\Helper\Purchase $mirasvitRewardsPurchaseHelper */
        $mirasvitRewardsPurchaseHelper = $this->mirasvitRewardsPurchaseHelper;

        if (!$mirasvitRewardsPurchaseHelper) {
            return 0;
        }

        $miravitRewardsPurchase = $mirasvitRewardsPurchaseHelper->getByQuote($quote);
        return $miravitRewardsPurchase->getSpendAmount();
    }
}
