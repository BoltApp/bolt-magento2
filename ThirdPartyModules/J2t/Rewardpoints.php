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
    const J2T_REWARD_POINTS_TOTAL_KEY = 'rewardpoints';

    /**
     * @var Bugsnag Bugsnag helper instance
     */
    private $bugsnagHelper;

    /**
     * @var Discount
     */
    private $discountHelper;
    
    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;
    
    /**
     * @var QuoteRepository
     */
    private $quoteRepository;
    
    /**
     * @var float
     */
    private $customerPoints;
    
    /**
     * @var \J2t\Rewardpoints\Helper\Data
     */
    private $rewardHelper;

    /**
     * @param Bugsnag                $bugsnagHelper
     * @param Discount               $discountHelper
     * @param PriceCurrencyInterface $priceCurrency
     * @param QuoteRepository        $quoteRepository
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        Discount $discountHelper,
        PriceCurrencyInterface $priceCurrency,
        QuoteRepository $quoteRepository
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->discountHelper = $discountHelper;
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
            $totals = $quote->getTotals();
            $totalDiscount = $totals[self::J2T_REWARD_POINTS_TOTAL_KEY] ?? null;
            if ($totalDiscount && $amount = $totalDiscount->getValue()) {
                list ($discounts, $totalAmount, $diff) = $result;
                
                // J2t reward points can not be applied to shipping.
                // And if its setting Include Tax On Discounts is enabled,
                // the discount can be applied to tax.
                // But since Bolt checkout does not support such a behavior,
                // we have to exclude tax from the discount calculation.
                if ($rewardHelper->getIncludeTax($quote->getStoreId())) {
                    $pointsUsed = $quote->getRewardpointsQuantity();
                    $maxPointUsage = $this->getMaxPointUsage($quote, $pointsUsed);
                    $pointsValue = $rewardHelper->getPointMoneyEquivalence($maxPointUsage, true, $quote, $quote->getStoreId());
                    $discountAmount = abs($this->priceCurrency->convert($pointsValue));
                } else {
                    $discountAmount = abs($amount);               
                }                
        
                $currencyCode = $quote->getQuoteCurrencyCode();                
                $roundedDiscountAmount = CurrencyUtils::toMinor($discountAmount, $currencyCode);
                $discount_type = $this->discountHelper->getBoltDiscountType('by_fixed');
                $discounts[] = [
                    'description'       => 'Reward Points',
                    'amount'            => $roundedDiscountAmount,
                    'reference'         => self::J2T_REWARD_POINTS,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    // For v1/discounts.code.apply and v2/cart.update
                    'discount_type'     => $discount_type,
                    // For v1/merchant/order
                    'type'              => $discount_type,
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
                if ($rewardHelper->getIncludeTax($quote->getStoreId())) {
                    $maxPointUsage = $this->getMaxPointUsage($quote, $pointsUsed);
                    $pointsValue = $rewardHelper->getPointMoneyEquivalence($maxPointUsage, true, $quote, $quote->getStoreId());
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
     * Return the reward points of customer.
     *
     * @param Quote $quote
     *
     */
    protected function getCustomerPoints($quote)
    {
        if ($this->customerPoints === null) {
            $this->customerPoints = $this->rewardHelper->getCurrentCustomerPoints($quote->getCustomerId(), $quote->getStoreId());
        }
        
        return $this->customerPoints;
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
            $customerPoints = $this->getCustomerPoints($quote);
            $points = min($points, $customerPoints);
        }

        $points = $this->getMaxOrderUsage($quote, $points, true, $quote->getStoreId());
        return $points;
    }
    
    /**
     * Calculate the max amount of reward points can be applied to quote.
     *
     * @param Quote $quote
     * @param float $points
     * @param bool  $collectTotals
     * @param int   $storeId
     *
     */
    protected function getMaxOrderUsage($quote, $points, $collectTotals = false, $storeId = null) {
        //check cart base subtotal
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
            
            $baseSubtotalInPoints = $this->rewardHelper->getPointsProductPriceEquivalence($subtotalPrice, $quote->getStoreId()) * $percent / 100;
            $points = min($points, $baseSubtotalInPoints);
        }
        if ($maxPointUsage = $this->rewardHelper->getMaxPointUsage()) {
            $points = min($points, $maxPointUsage);
        }
        return $points;
    }
}
