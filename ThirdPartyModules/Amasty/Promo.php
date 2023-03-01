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

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\SalesRule\Model\RuleRepository;


class Promo
{
    const PROMO_RULES = [
        'ampromo_product', //Auto add the same product
        'ampromo_items', //Auto add promo items with products
        'ampromo_cart', //Auto add promo items for the whole cart
    ];

    /**
     * @var RuleRepository
     */
    private $ruleRepository;

    /**
     * @var Bugsnag Bugsnag helper instance
     */
    private $bugsnagHelper;

    /**
     * @var Discount
     */
    private $discountHelper;

    /**
     * @param Bugsnag $bugsnagHelper Bugsnag helper instance
     * @param Discount $discountHelper
     * @param RuleRepository $ruleRepository
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        Discount $discountHelper,
        RuleRepository $ruleRepository

    )
    {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->discountHelper = $discountHelper;
        $this->ruleRepository = $ruleRepository;

    }

    /**
     * @param array $result
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Quote\Model\Quote $parentQuote
     * @param bool $paymentOnly
     * @return array
     */
    public function collectDiscounts(
        $result,
        $quote,
        $parentQuote,
        $paymentOnly
    )
    {
        list ($discounts, $totalAmount, $diff) = $result;

        try {
            $currencyCode = $quote->getQuoteCurrencyCode();
            $roundedDiscountAmount = 0;
            $discountAmount = 0;
            if ($couponCode = $quote->getCouponCode()) {
                $salesruleIds = explode(',', ($quote->getAppliedRuleIds() ?: ''));
                foreach ($salesruleIds as $salesruleId) {
                    $rule = $this->ruleRepository->getById($salesruleId);

                    if (
                        in_array($rule->getSimpleAction(), self::PROMO_RULES)
                        && !$rule->getExtensionAttributes()->getAmpromoRule()->getItemsDiscount()
                        && !$this->isAmastyRuleAlreadyApplied($rule, $discounts)
                    ) {
                        $amount = 0;
                        $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                        $discountItem = [
                            'description' => $rule->getDescription(),
                            'amount' => $discountAmount,
                            'reference' => $couponCode,
                            'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_COUPON,
                            'discount_type' => $this->discountHelper->convertToBoltDiscountType($couponCode), // For v1/discounts.code.apply and v2/cart.update
                            'type' => $this->discountHelper->convertToBoltDiscountType($couponCode), // For v1/merchant/order
                        ];
                        $discountAmount += $amount;
                        $roundedDiscountAmount += $roundedAmount;
                        $discounts[] = $discountItem;
                    }
                }
            }
            $diff -= CurrencyUtils::toMinorWithoutRounding($discountAmount, $currencyCode) - $roundedDiscountAmount;
            $totalAmount -= $roundedDiscountAmount;
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return [$discounts, $totalAmount, $diff];
        }
    }

    /**
     * Check if rule has been already applied
     *
     * @param \Magento\SalesRule\Api\Data\RuleInterface $rule
     * @param array $appliedDiscounts
     * @return bool
     */
    private function isAmastyRuleAlreadyApplied($rule, $appliedDiscounts)
    {
        foreach ($appliedDiscounts as $discount) {
            if (isset($discount['rule_id']) && $discount['rule_id'] == $rule->getRuleId()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $result
     * @param $rule
     * @return mixed
     */
    public function filterGetBoltCollectSaleRuleDiscounts($result, $rule)
    {
        if (
            in_array($rule->getSimpleAction(), self::PROMO_RULES)
            && !$rule->getExtensionAttributes()->getAmpromoRule()->getItemsDiscount()
        ) {
            $ruleId = $rule->getRuleId();
            $result[$ruleId] = 0;
        }
        return $result;
    }
}
