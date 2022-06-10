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

namespace Bolt\Boltpay\ThirdPartyModules\J2t;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Quote\Model\Quote;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\QuoteRepository;

class Rewardpoints
{
    const J2T_REWARD_POINTS = 'j2t_reward_points';

    /**
     * @var Bugsnag Bugsnag helper instance
     */
    private $bugsnagHelper;

    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;
    
    /**
     * @var QuoteRepository
     */
    private $quoteRepository;
    
    /**
     * @var \J2t\Rewardpoints\Helper\Data
     */
    private $rewardHelper;

    /**
     * @param Bugsnag                $bugsnagHelper
     * @param PriceCurrencyInterface $priceCurrency
     * @param QuoteRepository        $quoteRepository
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        PriceCurrencyInterface $priceCurrency,
        QuoteRepository $quoteRepository
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->priceCurrency = $priceCurrency;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param array                         $result
     * @param \J2t\Rewardpoints\Helper\Data $rewardHelper
     * @param \Magento\Quote\Model\Quote    $quote
     * @param \Magento\Quote\Model\Quote    $parentQuote
     * @param bool                          $paymentOnly
     * @return array
     */
    public function collectDiscounts(
        $result,
        $rewardHelper,
        $quote,
        $parentQuote,
        $paymentOnly
    ) {
        list ($discounts, $totalAmount, $diff) = $result;

        try {
            $this->rewardHelper = $rewardHelper;
            $pointsUsed = $quote->getRewardpointsQuantity();
            if ($pointsUsed > 0) {
                list ($discounts, $totalAmount, $diff) = $result;
                
                // J2t reward points can not be applied to shipping.
                // And if its setting Include Tax On Discounts is enabled,
                // the discount can be applied to tax.
                // But since Bolt checkout does not support such a behavior,
                // we have to exclude tax from the discount calculation.
                $storeId = $quote->getStoreId();
                if ($rewardHelper->getIncludeTax($storeId)) {
                    $pointsUsed = $this->getMaxPointUsage($quote, $pointsUsed);    
                }
                $pointsValue = $rewardHelper->getPointMoneyEquivalence($pointsUsed, true, $quote, $storeId);
                $discountAmount = abs($this->priceCurrency->convert($pointsValue));        
                $currencyCode = $quote->getQuoteCurrencyCode();                
                $roundedDiscountAmount = CurrencyUtils::toMinor($discountAmount, $currencyCode);
                $discounts[] = [
                    'description'       => 'Reward Points',
                    'amount'            => $roundedDiscountAmount,
                    'reference'         => self::J2T_REWARD_POINTS,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    // For v1/discounts.code.apply and v2/cart.update
                    'discount_type'     => Discount::BOLT_DISCOUNT_TYPE_FIXED,
                    // For v1/merchant/order
                    'type'              => Discount::BOLT_DISCOUNT_TYPE_FIXED,
                ];                
                $diff -= CurrencyUtils::toMinorWithoutRounding($discountAmount, $currencyCode) - $roundedDiscountAmount;
                $totalAmount -= $roundedDiscountAmount;
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return [$discounts, $totalAmount, $diff];
        }
    }
    
    /**
     * Get Additional Javascript to invalidate BoltCart.
     *
     * @param $result
     * @return string
     */
    public function getAdditionalJS($result)
    {
        $result .= 'var selectorsForInvalidate = ["#discount-point-form .applyPointsBtn","#discount-point-form .cancelPoints"];
                    for (var i = 0; i < selectorsForInvalidate.length; i++) {
                        var button = document.querySelector(selectorsForInvalidate[i]);
                        if (button) {
                            button.addEventListener("click", function() {
                                if (localStorage) {
                                    localStorage.setItem("bolt_cart_is_invalid", "true");
                                }
                            }, false);
                        }
                    }';
        
        return $result;
    }
    
    /**
     * Return code if the quote has J2t reward points.
     * 
     * @param $result
     * @param $couponCode
     * @param $quote
     * 
     * @return array
     */
    public function filterVerifyAppliedStoreCredit (
        $result,
        $couponCode,
        $quote
    )
    {
        if ($couponCode == self::J2T_REWARD_POINTS && $quote->getRewardpointsQuantity() > 0) {     
            $result[] = $couponCode;
        }
        
        return $result;
    }
    
    /**
     * Remove J2t reward points from the quote.
     *
     * @param $couponCode
     * @param $quote
     * @param $websiteId
     * @param $storeId
     * 
     */
    public function removeAppliedStoreCredit (
        $couponCode,
        $quote,
        $websiteId,
        $storeId
    )
    {
        try {
            if ($couponCode == self::J2T_REWARD_POINTS) {
                $quote->getShippingAddress()->setCollectShippingRates(true);
                $quote->setRewardpointsQuantity(0)->collectTotals();
                $this->quoteRepository->save($quote);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Update reward points to the quote.
     *
     * @param \J2t\Rewardpoints\Helper\Data $rewardHelper
     * @param Quote $quote
     *
     */
    public function beforePrepareQuote(
        $rewardHelper,
        $quote
    ) {
        try {
            $this->rewardHelper = $rewardHelper;
            $pointsUsed = $quote->getRewardpointsQuantity();
            if ($pointsUsed > 0) {
                // J2t reward points can not be applied to shipping.
                // And if its setting Include Tax On Discounts is enabled,
                // the discount can be applied to tax.
                // But since Bolt checkout does not support such a behavior,
                // we have to exclude tax from the discount calculation.
                $storeId = $quote->getStoreId();
                if ($rewardHelper->getIncludeTax($storeId)) {
                    $maxPointUsage = $this->getMaxPointUsage($quote, $pointsUsed);
                    $pointsValue = $rewardHelper->getPointMoneyEquivalence($maxPointUsage, true, $quote, $storeId);
                    $quote->setRewardpointsQuantity($maxPointUsage);
                    $quote->setBaseRewardpoints($pointsValue);
                    $quote->setRewardpoints($this->priceCurrency->convert($pointsValue));
                    $quote->save();
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
    
    /**
     * Return the max amount of reward points can be applied to quote.
     *
     * @param Quote $quote
     * @param float $points
     *
     */
    protected function getMaxPointUsage($quote, $points)
    {
        if ($points > 0) {
            $customerPoints = $this->rewardHelper->getCurrentCustomerPoints($quote->getCustomerId(), $quote->getStoreId());
            $points = max($points, $customerPoints);
        }
        $points = $this->getMaxOrderUsage($quote, $points, true);
        return $points;
    }
    
    /**
     * Calculate the max amount of reward points can be applied to quote.
     *
     * @param Quote $quote
     * @param float $points
     * @param bool  $collectTotals
     *
     */
    protected function getMaxOrderUsage($quote, $points, $collectTotals = false) {
        //check cart base subtotal
        $storeId = $quote->getStoreId();
        if ($percent = $this->rewardHelper->getMaxPercentUsage($storeId)) {
            //do collect totals
            $subtotalPrice = $quote->getShippingAddress()->getBaseSubtotal();
            
            if ($collectTotals && $subtotalPrice <= 0) {
                $quote->setByPassRewards(true);
                $quote->setTotalsCollectedFlag(false)->collectTotals();
                $quote->setByPassRewards(false);
                $subtotalPrice = $quote->getShippingAddress()->getBaseSubtotal();
            }
            
            if ($subtotalPrice <= 0){
                foreach ($quote->getAllVisibleItems() as $item) {
                    $subtotalPrice += $item->getBasePrice()*$item->getQty();
                }
            }
            
            $baseSubtotalInPoints = $this->rewardHelper->getPointsProductPriceEquivalence($subtotalPrice, $storeId) * $percent / 100;
            $points = min($points, $baseSubtotalInPoints);
        }
        if ($maxPointUsage = $this->rewardHelper->getMaxPointUsage()) {
            $points = min($points, $maxPointUsage);
        }
        return $points;
    }
}
