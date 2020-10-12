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
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;

class StoreCredit
{
    const AMASTY_STORECREDIT = 'amstorecredit';

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var Discount
     */
    protected $discountHelper;

    /**
     * @var Config
     */
    protected $configHelper;

    /**
     * StoreCredit constructor.
     * @param Discount $discountHelper
     * @param Bugsnag $bugsnagHelper
     * @param Config $configHelper
     */
    public function __construct(
        Discount $discountHelper,
        Bugsnag $bugsnagHelper,
        Config $configHelper
    )
    {
        $this->discountHelper = $discountHelper;
        $this->bugsnagHelper = $bugsnagHelper;
        $this->configHelper = $configHelper;
    }

    /**
     * @param $result
     * @param $quote
     * @param $parentQuote
     * @param $paymentOnly
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
        $totals = $quote->getTotals();

        try {
            if (array_key_exists(self::AMASTY_STORECREDIT, $totals)) {
                $amount = abs($totals[self::AMASTY_STORECREDIT]->getValue());
                $currencyCode = $quote->getQuoteCurrencyCode();
                $roundedDiscountAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                $discountType = $this->discountHelper->getBoltDiscountType('by_fixed');
                $discounts[] = [
                    'description' => $totals[self::AMASTY_STORECREDIT]->getTitle(),
                    'amount' => $roundedDiscountAmount,
                    'reference' => self::AMASTY_STORECREDIT,
                    'discount_type' => $discountType, // For v1/discounts.code.apply and v2/cart.update
                    'type' => $discountType, // For v1/discounts.code.apply and v2/cart.update
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT
                ];

                $diff -= CurrencyUtils::toMinorWithoutRounding($amount, $currencyCode) - $roundedDiscountAmount;
                $totalAmount -= $roundedDiscountAmount;
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return [$discounts, $totalAmount, $diff];
        }
    }

    /**
     * @param $result
     * @return mixed
     */
    public function filterProcessLayout($result)
    {
        if (!$this->configHelper->useAmastyStoreCreditConfig()) {
            unset($result['components']['block-totals']['children']['amstorecredit_total']);
            unset($result['components']['block-totals']['children']['amstorecredit_form']);
        }
        return $result;
    }
    
    /**
     * Return code if the quote has Amasty store credits.
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
        if ($couponCode == self::AMASTY_STORECREDIT && $quote->getData(\Amasty\StoreCredit\Api\Data\SalesFieldInterface::AMSC_USE)) {
            $result[] = $couponCode;
        }
        
        return $result;
    }
    
    /**
     * Remove Amasty store credits from the quote.
     *
     * @param $amastyApplyStoreCreditToQuote
     * @param $couponCode
     * @param $quote
     * @param $websiteId
     * @param $storeId
     * 
     */
    public function removeAppliedStoreCredit (
        $amastyApplyStoreCreditToQuote,
        $couponCode,
        $quote,
        $websiteId,
        $storeId
    )
    {
        try {
            if ($couponCode == self::AMASTY_STORECREDIT && $quote->getData(\Amasty\StoreCredit\Api\Data\SalesFieldInterface::AMSC_USE)) {
                $amastyApplyStoreCreditToQuote->cancel($quote->getId());
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
