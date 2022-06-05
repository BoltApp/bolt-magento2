<?php

namespace Bolt\Boltpay\ThirdPartyModules\BagRiders;

use BagRiders\StoreCredit\Api\ApplyStoreCreditToQuoteInterface;
use BagRiders\StoreCredit\Api\Data\SalesFieldInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Framework\Pricing\PriceCurrencyInterface;

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
     * @var Config
     */
    private $configHelper;
    
    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * StoreCredit constructor.
     * @param Bugsnag $bugsnagHelper
     * @param Config $configHelper
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        Config $configHelper,
        PriceCurrencyInterface $priceCurrency
    )
    {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->configHelper = $configHelper;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * @param $result
     * @param \BagRiders\StoreCredit\Api\StoreCreditRepositoryInterface $storeCreditRepository
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Quote\Model\Quote $parentQuote
     * @param bool $paymentOnly
     * @return array
     */
    public function collectDiscounts(
        $result,
        $storeCreditRepository,
        $quote,
        $parentQuote,
        $paymentOnly
    )
    {
        list ($discounts, $totalAmount, $diff) = $result;
        $totals = $quote->getTotals();

        try {
            if (array_key_exists(self::BAGRIDERS_STORECREDIT, $totals)) {
                $availableBaseStoreCredit = $storeCreditRepository->getByCustomerId(
                    $quote->getCustomerId()
                )->getStoreCredit();
                
                if ($availableBaseStoreCredit) {
                    $currencyCode = $quote->getQuoteCurrencyCode();
                    $amount = $this->priceCurrency->convertAndRound(
                        $availableBaseStoreCredit,
                        null,
                        $currencyCode,
                        2
                    );                   
                    $roundedDiscountAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                    $discounts[] = [
                        'description' => 'Bag Riders Store Credit',
                        'amount' => $roundedDiscountAmount,
                        'reference' => self::BAGRIDERS_STORECREDIT_REFERENCE,
                        'discount_type' => Discount::BOLT_DISCOUNT_TYPE_FIXED, // For v1/discounts.code.apply and v2/cart.update
                        'type' => Discount::BOLT_DISCOUNT_TYPE_FIXED, // For v1/discounts.code.apply and v2/cart.update
                        'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT
                    ];
    
                    $diff -= CurrencyUtils::toMinorWithoutRounding($amount, $currencyCode) - $roundedDiscountAmount;
                    $totalAmount -= $roundedDiscountAmount;
                }
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
     * @param string $couponCode
     * @param \Magento\Quote\Model\Quote $quote
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
     * @param \BagRiders\StoreCredit\Model\StoreCredit\ApplyStoreCreditToQuote $bagRidersApplyStoreCreditQuote
     * @param string $couponCode
     * @param \Magento\Quote\Model\Quote $quote
     * @param int $websiteId
     * @param int $storeId
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
    
    /**
     * @param \BagRiders\StoreCredit\Api\StoreCreditRepositoryInterface $storeCreditRepository
     * @param \Magento\Quote\Model\Quote $quote
     */
    public function beforeValidateQuoteDataForProcessNewOrder(
        $storeCreditRepository,
        $quote
    )
    {
        try{
            if (!$quote->getData('am_store_credit_set') && !$quote->getData(SalesFieldInterface::AMSC_USE)) {
                return false;
            }
            $availableBaseStoreCredit = $storeCreditRepository->getByCustomerId(
                $quote->getCustomerId()
            )->getStoreCredit();
            $currencyCode = $quote->getQuoteCurrencyCode();
            $amount = $this->priceCurrency->convertAndRound(
                $availableBaseStoreCredit,
                null,
                $currencyCode,
                2
            );
            $quote->setData(SalesFieldInterface::AMSC_USE, 1);
            $quote->setData('am_store_credit_set', 1);
            $quote->setBrstorecreditAmount($amount);
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            $quote->save();
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
}
