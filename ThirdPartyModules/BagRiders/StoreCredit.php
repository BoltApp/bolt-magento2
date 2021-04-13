<?php

namespace Bolt\Boltpay\ThirdPartyModules\BagRiders;

use BagRiders\StoreCredit\Api\ApplyStoreCreditToQuoteInterface;
use BagRiders\StoreCredit\Api\Data\SalesFieldInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;

class StoreCredit
{
    const BAGRIDERS_STORECREDIT = 'brstorecredit';
    // Since the related Store Credit module is for the merchant Bag Riders only,
    // and they want to show readable text on the Bolt modal,
    // we use another string for reference.
    const BAGRIDERS_STORECREDIT_REFERENCE = 'Store Credit';

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
            if (array_key_exists(self::BAGRIDERS_STORECREDIT, $totals)) {
                $amount = abs($totals[self::BAGRIDERS_STORECREDIT]->getValue());
                $currencyCode = $quote->getQuoteCurrencyCode();
                $roundedDiscountAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                $discountType = $this->discountHelper->getBoltDiscountType('by_fixed');
                $discounts[] = [
                    'description' => 'Bag Riders Store Credit',
                    'amount' => $roundedDiscountAmount,
                    'reference' => self::BAGRIDERS_STORECREDIT_REFERENCE,
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
        // TODO: Add store config for Bag Riders store credit
        unset($result['components']['block-totals']['children']['brstorecredit_total']);
        unset($result['components']['block-totals']['children']['amstorecredit_form']);
            
        return $result;
    }

    /**
     * Return code if the quote has BagRiders store credits.
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
        if ($couponCode == self::BAGRIDERS_STORECREDIT_REFERENCE && $quote->getData(SalesFieldInterface::AMSC_USE)) {
            $result[] = $couponCode;
        }

        return $result;
    }

    /**
     * Remove BagRiders store credits from the quote.
     *
     * @param ApplyStoreCreditToQuoteInterface $bagRidersApplyStoreCreditQuote
     * @param $couponCode
     * @param $quote
     * @param $websiteId
     * @param $storeId
     *
     */
    public function removeAppliedStoreCredit (
        $bagRidersApplyStoreCreditQuote,
        $couponCode,
        $quote,
        $websiteId,
        $storeId
    )
    {
        try {
            if ($couponCode == self::BAGRIDERS_STORECREDIT_REFERENCE && $quote->getData(SalesFieldInterface::AMSC_USE)) {
                $bagRidersApplyStoreCreditQuote->cancel($quote->getId());
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
