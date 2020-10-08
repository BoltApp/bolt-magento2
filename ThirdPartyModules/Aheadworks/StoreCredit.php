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

namespace Bolt\Boltpay\ThirdPartyModules\Aheadworks;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;

class StoreCredit
{
    const AHEADWORKS_STORE_CREDIT = 'aw_store_credit';

    /**
     * @var $aheadworksCustomerStoreCreditManagement
     */
    private $aheadworksCustomerStoreCreditManagement;

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var Discount
     */
    protected $discountHelper;

    /**
     * StoreCredit constructor.
     * @param Discount $discountHelper
     * @param Bugsnag $bugsnagHelper
     */
    public function __construct(
        Discount $discountHelper,
        Bugsnag $bugsnagHelper
    )
    {
        $this->discountHelper = $discountHelper;
        $this->bugsnagHelper = $bugsnagHelper;
    }

    /**
     * @param $result
     * @param $aheadworksCustomerStoreCreditManagement
     * @param $quote
     * @param $parentQuote
     * @param $paymentOnly
     * @return array
     */
    public function collectDiscounts(
        $result,
        $aheadworksCustomerStoreCreditManagement,
        $quote,
        $parentQuote,
        $paymentOnly
    )
    {
        $this->aheadworksCustomerStoreCreditManagement = $aheadworksCustomerStoreCreditManagement;

        list ($discounts, $totalAmount, $diff) = $result;
        $totals = $quote->getTotals();
        try {
            if (array_key_exists(Discount::AHEADWORKS_STORE_CREDIT, $totals)) {
                $amount = abs($this->aheadworksCustomerStoreCreditManagement->getCustomerStoreCreditBalance($quote->getCustomerId()));
                $currencyCode = $quote->getQuoteCurrencyCode();
                $discountType = $this->discountHelper->getBoltDiscountType('by_fixed');
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                $discounts[] = [
                    'description' => 'Store Credit',
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
}
