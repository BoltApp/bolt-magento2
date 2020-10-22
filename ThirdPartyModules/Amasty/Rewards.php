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

namespace Bolt\Boltpay\ThirdPartyModules\Amasty;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;

class Rewards
{
    const AMASTY_REWARD = 'amasty_rewards_point';
    
    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;
    
    /**
     * @var Discount
     */
    protected $discountHelper;

    /**
     * @param Bugsnag $bugsnagHelper Bugsnag helper instance
     * @param Discount $discountHelper
     */
    public function __construct(
        Bugsnag   $bugsnagHelper,
        Discount  $discountHelper
    ) {
        $this->bugsnagHelper   = $bugsnagHelper;
        $this->discountHelper  = $discountHelper;
    }

    /**
     * @param array $result
     * @param Amasty\Rewards\Helper\Data $amastyRewardsHelperData
     * @param Quote $quote
     * 
     * @return array
     */
    public function collectDiscounts($result,
                                     $amastyRewardsHelperData,
                                     $amastyRewardsResourceModelQuote,
                                     $quote,
                                     $parentQuote,
                                     $paymentOnly)
    {
        list ($discounts, $totalAmount, $diff) = $result;

        try {
            if ($quote->getData('amrewards_point')) {
                $rewardData = $amastyRewardsHelperData->getRewardsData();
                $pointsUsed = $amastyRewardsResourceModelQuote->getUsedRewards($quote->getId());
                $pointsRate = $rewardData['rateForCurrency'];
                $amount = $pointsUsed / $pointsRate;
                $currencyCode = $quote->getQuoteCurrencyCode();
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                
                $discounts[] = [
                    'description'       => 'Reward Points',
                    'amount'            => $roundedAmount,
                    'reference'         => self::AMASTY_REWARD,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    'discount_type'     => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/discounts.code.apply and v2/cart.update
                    'type'              => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/merchant/order
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

    public function getAdditionalJS($result)
    {
        $result .= 'var selectorsForInvalidate = ["apply-amreward","cancel-amreward"];
        for (var i = 0; i < selectorsForInvalidate.length; i++) {
            var button = document.getElementById(selectorsForInvalidate[i]);
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
     * Return code if the quote has Amasty reward points.
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
        if ($couponCode == self::AMASTY_REWARD && $quote->getData('amrewards_point')) {
            $result[] = $couponCode;
        }
        
        return $result;
    }
    
    /**
     * Remove Amasty reward points from the quote.
     *
     * @param $amastyRewardsManagement
     * @param $amastyRewardsQuote
     * @param $couponCode
     * @param $quote
     * @param $websiteId
     * @param $storeId
     * 
     */
    public function removeAppliedStoreCredit (
        $amastyRewardsManagement,
        $amastyRewardsQuote,
        $couponCode,
        $quote,
        $websiteId,
        $storeId
    )
    {
        try {
            if ($couponCode == self::AMASTY_REWARD && $quote->getData('amrewards_point')) {
                $amastyRewardsManagement->collectCurrentTotals($quote, 0);

                $amastyRewardsQuote->addReward(
                    $quote->getId(),
                    0
                );
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
