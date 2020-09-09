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

namespace Bolt\Boltpay\ThirdPartyModules\Mirasvit;

use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Service\OrderService;
use Magento\Framework\App\State;
use Bolt\Boltpay\Helper\Session as SessionHelper;

class Credit
{
    /**
     * @var State
     */
    protected $appState;
    
    /**
     * @var DiscountHelper
     */
    protected $discountHelper;
    
    /**
     * @var SessionHelper
     */
    protected $sessionHelper;
    
    /**
     * @var \Mirasvit\Credit\Helper\Data
     */
    protected $mirasvitStoreCreditHelper;
    
    /**
     * @var \Mirasvit\Credit\Helper\Calculation
     */
    protected $mirasvitStoreCreditCalculationService;
    
    /**
     * @var \Mirasvit\Credit\Api\Config\CalculationConfigInterface
     */
    protected $mirasvitStoreCreditCalculationConfig;
    
    /**
     * @var \Mirasvit\Credit\Service\Config\CalculationConfig
     */
    protected $mirasvitStoreCreditCalculationConfigLegacy;

    /**
     * @param Discount                     $discountHelper
     * @param EventsForThirdPartyModules   $eventsForThirdPartyModules
     * @param State                        $appState
     * @param SessionHelper                $sessionHelper
     */
    public function __construct(
        Discount    $discountHelper,
        State       $appState,
        SessionHelper $sessionHelper
    ) {
        $this->discountHelper  = $discountHelper;
        $this->appState        = $appState;
        $this->sessionHelper = $sessionHelper;
    }

    public function collectDiscounts($result,
                                     $mirasvitStoreCreditHelper,
                                     $mirasvitStoreCreditCalculationService,
                                     $mirasvitStoreCreditCalculationConfig,
                                     $mirasvitStoreCreditCalculationConfigLegacy,
                                     $quote,
                                     $paymentOnly)
    {
        list ($discounts, $totalAmount, $diff) = $result;
        $this->mirasvitStoreCreditHelper = $mirasvitStoreCreditHelper;
        $this->mirasvitStoreCreditCalculationService = $mirasvitStoreCreditCalculationService;
        $this->mirasvitStoreCreditCalculationConfig = $mirasvitStoreCreditCalculationConfig;
        $this->mirasvitStoreCreditCalculationConfigLegacy = $mirasvitStoreCreditCalculationConfigLegacy;

        try {
            $amount = abs($this->getMirasvitStoreCreditAmount($quote, $paymentOnly));
            $currencyCode = $quote->getQuoteCurrencyCode();
            $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);

            $discounts[] = [
                'description'       => 'Store Credit',
                'amount'            => $roundedAmount,
                'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                'discount_type'     => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/discounts.code.apply and v2/cart.update
                'type'              => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/merchant/order
            ];

            $diff -= CurrencyUtils::toMinorWithoutRounding($amount, $currencyCode) - $roundedAmount;
            $totalAmount -= $roundedAmount;
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {        
            return [$discounts, $totalAmount, $diff];
        }
    }
    
    /**
     * @param      $quote
     *
     * @param bool $paymentOnly
     *
     * @return float
     */
    private function getMirasvitStoreCreditAmount($quote, $paymentOnly = false)
    {
        $miravitBalanceAmount = $this->getMirasvitStoreCreditUsedAmount($quote);
            
        if (!$paymentOnly) {       
            if ($this->ifMirasvitCreditIsShippingTaxIncluded($quote)) {
                return $miravitBalanceAmount;
            }
        }

        $unresolvedTotal = $quote->getGrandTotal() + $quote->getCreditAmountUsed();
        $totals = $quote->getTotals();

        $tax      = isset($totals['tax']) ? $totals['tax']->getValue() : 0;
        $shipping = isset($totals['shipping']) ? $totals['shipping']->getValue() : 0;

        $unresolvedTotal = $this->mirasvitStoreCreditCalculationService->calc($unresolvedTotal, $tax, $shipping);
        
        return min($unresolvedTotal, $miravitBalanceAmount);
    }
    
    /**
     * Get Mirasvit Store credit balance used amount.
     * This method is only called when the Mirasvit_Credit module is installed and available on the quote.
     *
     * @param $quote
     *
     * @return float
     */
    private function getMirasvitStoreCreditUsedAmount($quote)
    {
        $balance = $this->mirasvitStoreCreditHelper
                        ->getBalance($quote->getCustomerId(), $quote->getQuoteCurrencyCode());

        $amount = ((float)$quote->getManualUsedCredit() > 0) ? $quote->getManualUsedCredit() : $balance->getAmount();
        if ($quote->getQuoteCurrencyCode() !== $balance->getCurrencyCode()) {
            $amount = $this->mirasvitStoreCreditCalculationService->convertToCurrency(
                $amount,
                $balance->getCurrencyCode(),
                $quote->getQuoteCurrencyCode(),
                $quote->getStore()
            );
        }

        return $amount;
    }
    
    /**
     * @param Observer $observer
     *
     * @return bool
     */
    public function checkMirasvitCreditAdminQuoteUsed($result, Observer $observer)
    {
        try {
            $payment = $observer->getEvent()->getPayment();

            if ($payment->getMethod() == Payment::METHOD_CODE &&
                $this->appState->getAreaCode() == FrontNameResolver::AREA_CODE &&
                $payment->getQuote()->getUseCredit() == Mirasvit\Credit\Model\Config::USE_CREDIT_YES
            ) {
                return true;
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return false;
    }
    
    /**
     * To run filter to check if the Mirasvit credit amount can be applied to shipping/tax.
     *
     * @param boolean $result
     * @param Mirasvit\Credit\Api\Config\CalculationConfigInterface|object $mirasvitStoreCreditCalculationConfig
     * @param Mirasvit\Credit\Service\Config\CalculationConfig|object $mirasvitStoreCreditCalculationConfigLegacy
     * @param Quote|object $quote
     * 
     * @return boolean
     */
    public function checkMirasvitCreditIsShippingTaxIncluded($result,
                                     $mirasvitStoreCreditCalculationConfig,
                                     $mirasvitStoreCreditCalculationConfigLegacy,
                                     $quote)
    {
        $this->mirasvitStoreCreditCalculationConfig = $mirasvitStoreCreditCalculationConfig;
        $this->mirasvitStoreCreditCalculationConfigLegacy = $mirasvitStoreCreditCalculationConfigLegacy;
        
        return $this->ifMirasvitCreditIsShippingTaxIncluded($quote);
    }
    
    /**
     * If the Mirasvit credit amount can be applied to shipping/tax.
     *
     * @param Quote|object $quote
     * 
     * @return boolean
     */
    private function ifMirasvitCreditIsShippingTaxIncluded($quote)
    {
        // For old version of Mirasvit Store Credit plugin,
        // $miravitCalculationConfig could be empty,
        // so we use the instance of \Mirasvit\Credit\Service\Config\CalculationConfig instead.
        $miravitCalculationConfig = !empty($this->mirasvitStoreCreditCalculationConfig)
                                    ? $this->mirasvitStoreCreditCalculationConfig
                                    : $this->mirasvitStoreCreditCalculationConfigLegacy;
   
        if ($miravitCalculationConfig->isTaxIncluded($quote->getStore()) || $miravitCalculationConfig->IsShippingIncluded($quote->getStore())) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Exclude the Mirasvit credit amount from shipping discount, so the Bolt can apply Mirasvit credit to shipping properly.
     *
     * @param float $result
     * @param Mirasvit\Credit\Helper\Data|object $mirasvitStoreCreditHelper 
     * @param Quote|object $quote
     * @param Address|object $shippingAddress
     * 
     * @return float
     */
    public function collectShippingDiscounts($result,
                                     $mirasvitStoreCreditHelper,
                                     $quote,
                                     $shippingAddress)
    {
        $mirasvitStoreCreditShippingDiscountAmount = $this->sessionHelper->getCheckoutSession()->getMirasvitStoreCreditShippingDiscountAmount(0);
        $result -= $mirasvitStoreCreditShippingDiscountAmount;
        return $result;
    }
}
