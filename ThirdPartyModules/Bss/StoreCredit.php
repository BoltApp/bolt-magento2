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

namespace Bolt\Boltpay\ThirdPartyModules\Bss;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;

class StoreCredit
{
    const BSS_STORE_CREDIT = 'bss_storecredit';

    /**
     * @var $bssStoreCreditHelper
     */
    private $bssStoreCreditHelper;

    /**
     * @var $bssStoreCreditCollection
     */
    private $bssStoreCreditCollection;

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
     * @param $bssStoreCreditHelper
     * @param $bssStoreCreditCollection
     * @param $quote
     * @param $parentQuote
     * @param $paymentOnly
     * @return array
     */
    public function collectDiscounts(
        $result,
        $bssStoreCreditHelper,
        $bssStoreCreditCollection,
        $quote,
        $parentQuote,
        $paymentOnly
    )
    {
        $this->bssStoreCreditHelper = $bssStoreCreditHelper;
        $this->bssStoreCreditCollection = $bssStoreCreditCollection;
        list ($discounts, $totalAmount, $diff) = $result;
        $totals = $quote->getTotals();
        try {
            if (array_key_exists(self::BSS_STORE_CREDIT, $totals)) {
                $amount = $this->getBssStoreCreditAmount($quote, $parentQuote);
                $currencyCode = $quote->getQuoteCurrencyCode();
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                $discounts[] = [
                    'description' => 'Store Credit',
                    'amount' => $roundedAmount,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    'discount_type' => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/discounts.code.apply and v2/cart.update
                    'type' => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/merchant/order
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
     * @param $immutableQuote
     * @param $parentQuote
     * @return float|int
     */
    private function getBssStoreCreditAmount($immutableQuote, $parentQuote)
    {
        try {
            $isAppliedToShippingAndTax = $this->bssStoreCreditHelper->getGeneralConfig('used_shipping') || $this->bssStoreCreditHelper->getGeneralConfig('used_tax');

            $storeCreditAmount = $immutableQuote->getBaseBssStorecreditAmountInput();
            if ($isAppliedToShippingAndTax && abs($storeCreditAmount) >= $immutableQuote->getSubtotal()) {
                $storeCreditAmount = $this->getBssStoreCreditBalanceAmount($parentQuote);
                $parentQuote->setBaseBssStorecreditAmountInput($storeCreditAmount)->save();
                $immutableQuote->setBaseBssStorecreditAmountInput($storeCreditAmount)->save();
            }

            return $storeCreditAmount;
        } catch (\Exception $exception) {
            $this->bugsnagHelper->notifyException($exception);
            return 0;
        }
    }

    /**
     * @param $quote
     * @return float|int
     */
    private function getBssStoreCreditBalanceAmount($quote)
    {
        $data = $this->bssStoreCreditCollection
            ->addFieldToFilter('customer_id', ['in' => $quote->getCustomerId()])
            ->getData();

        return array_sum(array_column($data, 'balance_amount'));
    }
}
