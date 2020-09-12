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
                                     $quote,
                                     $paymentOnly)
    {
        list ($discounts, $totalAmount, $diff) = $result;

        try {
            if ($quote->getData('amrewards_point')) {
                $rewardData = $amastyRewardsHelperData->getRewardsData();
                $pointsUsed = $rewardData['pointsUsed'];
                $pointsRate = $rewardData['rateForCurrency'];
                $amount = $pointsUsed / $pointsRate;
                $currencyCode = $quote->getQuoteCurrencyCode();
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                $newDiscounts = array();
                // Amasty Rewards plugin applies discount amount to the quote as same as coupon code,
                // so we need to collect the amount discounted with reward points separately. 
                foreach ($discounts as $discount) {
                    if ($discount['discount_category'] === Discount::BOLT_DISCOUNT_CATEGORY_COUPON) {
                        if (!empty($discount['reference'])) {
                            $couponAmount = $discount['amount'] - $roundedAmount;
                            $newDiscounts[] = [
                                'description'       => trim(current(explode(',',$discount['description']))),
                                'amount'            => $couponAmount,
                                'reference'         => $discount['reference'],
                                'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_COUPON,
                                'discount_type'     => $discount['discount_type'], // For v1/discounts.code.apply and v2/cart.update
                                'type'              => $discount['type'], // For v1/merchant/order
                            ];
                        }
                    } else {
                        $newDiscounts[] = $discount;
                    }
                }
                $newDiscounts[] = [
                    'description'       => 'Reward Points',
                    'amount'            => $roundedAmount,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    'discount_type'     => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/discounts.code.apply and v2/cart.update
                    'type'              => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/merchant/order
                ];
                $discounts = $newDiscounts;
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {        
            return [$discounts, $totalAmount, $diff];
        }
    }
}
