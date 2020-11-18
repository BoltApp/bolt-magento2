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

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Store\Model\ScopeInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Rewards
{
    /**
     * @var \Mirasvit\Rewards\Helper\Purchase
     */
    private $mirasvitRewardsPurchaseHelper;
    
    /**
     * @var ThirdPartyModuleFactory
     */
    private $mirasvitRewardsSpendRulesListHelper;
    
    /**
     * @var ThirdPartyModuleFactory
     */
    private $mirasvitRewardsModelConfig;
    
    /**
     * @var ThirdPartyModuleFactory
     */
    private $mirasvitRewardsBalanceHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var Discount
     */
    private $discountHelper;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfigInterface;
    
    /**
     * @var CustomerFactory
     */
    private $customerFactory;
    
    /**
     * @var SessionHelper
     */
    private $sessionHelper;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * Rewards constructor.
     * @param Bugsnag $bugsnagHelper
     * @param Discount $discountHelper
     * @param ScopeInterface $scopeConfigInterface
     * @param CustomerFactory $customerFactory
     * @param SessionHelper $sessionHelper
     * @param CartHelper $cartHelper
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        Discount $discountHelper,
        ScopeConfigInterface $scopeConfigInterface,
        CustomerFactory $customerFactory,
        SessionHelper $sessionHelper,
        CartHelper $cartHelper
    )
    {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->discountHelper = $discountHelper;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->customerFactory = $customerFactory;
        $this->sessionHelper   = $sessionHelper;
        $this->cartHelper = $cartHelper;
    }

    /**
     * @param $mirasvitRewardsPurchaseHelper
     * @param $immutableQuote
     */
    public function applyExternalDiscountData(
        $mirasvitRewardsPurchaseHelper,
        $mirasvitRewardsBalanceHelper,
        $mirasvitRewardsSpendRulesListHelper,
        $mirasvitRewardsModelConfig,
        $immutableQuote
    )
    {
        $this->mirasvitRewardsPurchaseHelper = $mirasvitRewardsPurchaseHelper;
        $this->mirasvitRewardsBalanceHelper = $mirasvitRewardsBalanceHelper;
        $this->mirasvitRewardsSpendRulesListHelper = $mirasvitRewardsSpendRulesListHelper;
        $this->mirasvitRewardsModelConfig = $mirasvitRewardsModelConfig;

        $this->applyMiravistRewardPoint($immutableQuote);
    }

    /**
     * @param $result
     * @param $mirasvitRewardsPurchaseHelper
     * @param $mirasvitRewardsBalanceHelper
     * @param $mirasvitRewardsSpendRulesListHelper
     * @param $mirasvitRewardsModelConfig
     * @param $quote
     * @param $parentQuote
     * @param $paymentOnly
     * @return array
     */
    public function collectDiscounts(
        $result,
        $mirasvitRewardsPurchaseHelper,
        $mirasvitRewardsBalanceHelper,
        $mirasvitRewardsSpendRulesListHelper,
        $mirasvitRewardsModelConfig,
        $quote,
        $parentQuote,
        $paymentOnly
    )
    {
        $this->mirasvitRewardsPurchaseHelper = $mirasvitRewardsPurchaseHelper;
        $this->mirasvitRewardsBalanceHelper = $mirasvitRewardsBalanceHelper;
        $this->mirasvitRewardsSpendRulesListHelper = $mirasvitRewardsSpendRulesListHelper;
        $this->mirasvitRewardsModelConfig = $mirasvitRewardsModelConfig;
        list ($discounts, $totalAmount, $diff) = $result;
        $discountType = $this->discountHelper->getBoltDiscountType('by_fixed');
        try {
            if ($amount = abs($this->getMirasvitRewardsAmount($parentQuote))) {
                $currencyCode = $parentQuote->getQuoteCurrencyCode();
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                $discounts[] = [
                    'description' => $this->scopeConfigInterface->getValue(
                        'rewards/general/point_unit_name',
                        ScopeInterface::SCOPE_STORE,
                        $quote->getStoreId()
                    ),
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

    /**
     * @param $result
     * @param $mirasvitRewardsPurchaseHelper
     * @param $quote
     * @return string
     */
    public function filterApplyExternalQuoteData(
        $result,
        $mirasvitRewardsPurchaseHelper,
        $mirasvitRewardsBalanceHelper,
        $mirasvitRewardsSpendRulesListHelper,
        $mirasvitRewardsModelConfig,
        $quote
    )
    {
        $this->mirasvitRewardsPurchaseHelper = $mirasvitRewardsPurchaseHelper;
        $this->mirasvitRewardsBalanceHelper = $mirasvitRewardsBalanceHelper;
        $this->mirasvitRewardsSpendRulesListHelper = $mirasvitRewardsSpendRulesListHelper;
        $this->mirasvitRewardsModelConfig = $mirasvitRewardsModelConfig;

        if ($rewardsAmount = $this->getMirasvitRewardsAmount($quote)) {
            $result .= $rewardsAmount;
        }

        return $result;
    }

    /**
     * Copy Miravist Reward Point data from parent quote to immutable quote.
     * The reward points are fetched from the 3rd party module DB table (mst_rewards_purchase)
     * and assigned to the parent quote temporarily (they are not persisted in the quote table).
     * The data needs to be set on the immutable quote before the quote totals are calculated
     * in the Shipping and Tax call in order to get correct tax
     *
     * @param $immutableQuote
     */
    private function applyMiravistRewardPoint($immutableQuote)
    {
        $parentQuoteId = $immutableQuote->getBoltParentQuoteId();
        if (!$parentQuoteId) {
            return;
        }
        
        $immutableQuoteId = $immutableQuote->getId();
        
        if ($immutableQuoteId == $parentQuoteId) {
            // Product Page Checkout - quotes are created as inactive
            $parentQuote = $this->cartHelper->getQuoteById($parentQuoteId);
        } else {
            $parentQuote = $this->cartHelper->getActiveQuoteById($parentQuoteId);
        }
        
        try {
            if (abs($this->getMirasvitRewardsAmount($parentQuote)) > 0) {
                $parentPurchase = $this->mirasvitRewardsPurchaseHelper->getByQuote($parentQuoteId);
               
                $this->mirasvitRewardsPurchaseHelper->getByQuote($immutableQuoteId)
                    ->setSpendPoints($parentPurchase->getSpendPoints())
                    ->setSpendMinAmount($parentPurchase->getSpendMinAmount())
                    ->setSpendMaxAmount($parentPurchase->getSpendMaxAmount())
                    ->setSpendAmount($parentPurchase->getSpendAmount())
                    ->setBaseSpendAmount($parentPurchase->getBaseSpendAmount())
                    ->save();
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }

    /**
     * If enabled, gets the Mirasvit Rewards amount used
     *
     * @param \Magento\Quote\Model\Quote $quote The parent quote of this order which contains Rewards points references
     *
     * @return float  If enabled, the currency amount used in the order, otherwise 0
     */
    private function getMirasvitRewardsAmount($quote)
    {        
        try{
            $miravitRewardsPurchase = $this->mirasvitRewardsPurchaseHelper->getByQuote($quote);
            $spendAmount = $miravitRewardsPurchase->getSpendAmount();
            // If the setting "Allow to spend points for shipping charges" is set to Yes,
            // we need to send full balance to the Bolt server.
            if(($spendAmount > \Mirasvit\Rewards\Helper\Calculation::ZERO_VALUE) && $this->mirasvitRewardsModelConfig->getGeneralIsSpendShipping()) {
                $balancePoints = $this->mirasvitRewardsBalanceHelper->getBalancePoints($quote->getCustomerId());
                $customer = $this->customerFactory->create()->load($quote->getCustomer()->getId());
                $points = 0;
                $websiteId = $quote->getStore()->getWebsiteId();
                $rules = $this->mirasvitRewardsSpendRulesListHelper->getRuleCollection($websiteId, $customer->getGroupId());
                if ($rules->count()) {
                    $totalAmount = 0;
                    $totalPoints = 0;
        
                    $mockSubtotal = 999999999;
                    $pointsMoney = [0];
                    /** @var \Mirasvit\Rewards\Model\Spending\Rule $rule */
                    foreach ($rules as $rule) {
                        $rule->afterLoad();
                        $tier = $rule->getTier($customer);
                      
                        $rulePointsNumber = $balancePoints;
        
                        if ($tier->getSpendingStyle() == \Mirasvit\Rewards\Api\Config\Rule\SpendingStyleInterface::STYLE_PARTIAL) {
                            $stepsSecond = round($rulePointsNumber / $tier->getSpendPoints(), 2, PHP_ROUND_HALF_DOWN);
                        } else {
                            $spendPoints = $tier->getSpendPoints();
                            $rulePointsNumber = floor($rulePointsNumber / $spendPoints) * $spendPoints;
                            $stepsSecond = floor($rulePointsNumber / $spendPoints);
                        }
        
                        if ($rulePointsNumber < $tier->getSpendMinAmount($mockSubtotal)) {
                            continue;
                        }
        
                        $stepsFirst = round($mockSubtotal / $tier->getMonetaryStep($mockSubtotal), 2, PHP_ROUND_HALF_DOWN);
                        if ($stepsFirst != $mockSubtotal / $tier->getMonetaryStep($mockSubtotal)) {
                            ++$stepsFirst;
                        }
        
                        $steps = min($stepsFirst, $stepsSecond);
        
                        $amount = $steps * $tier->getMonetaryStep($mockSubtotal);
                        $amount = min($amount, $mockSubtotal);
        
                        $totalAmount += $amount;
        
                        $balancePoints = $balancePoints - $rulePointsNumber;
                        $totalPoints += $rulePointsNumber;

                        if ($rule->getIsStopProcessing()) {
                            break;
                        }
                    }
                }

                $spendAmount = max($spendAmount, $totalAmount);
            }

            return $spendAmount;
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
            return 0;
        }        
    }
    
    /**
     * @param $mirasvitRewardsPurchaseHelper
     * @param $mirasvitRewardsBalanceHelper
     * @param $mirasvitRewardsSpendRulesListHelper
     * @param $mirasvitRewardsModelConfig
     * @param $quote
     */
    public function beforeValidateQuoteDataForProcessNewOrder(
        $mirasvitRewardsPurchaseHelper,
        $mirasvitRewardsBalanceHelper,
        $mirasvitRewardsSpendRulesListHelper,
        $mirasvitRewardsModelConfig,
        $mirasvitRewardsCheckoutHelper,
        $quote
    )
    {
        $this->mirasvitRewardsPurchaseHelper = $mirasvitRewardsPurchaseHelper;
        $this->mirasvitRewardsBalanceHelper = $mirasvitRewardsBalanceHelper;
        $this->mirasvitRewardsSpendRulesListHelper = $mirasvitRewardsSpendRulesListHelper;
        $this->mirasvitRewardsModelConfig = $mirasvitRewardsModelConfig;
        
        try{           
            if($this->mirasvitRewardsModelConfig->getGeneralIsSpendShipping() && abs($this->getMirasvitRewardsAmount($quote)) > 0) {
                $balancePoints = $this->mirasvitRewardsBalanceHelper->getBalancePoints($quote->getCustomerId());
                $miravitRewardsPurchase = $this->mirasvitRewardsPurchaseHelper->getByQuote($quote);
                $mirasvitRewardsCheckoutHelper->updatePurchase($miravitRewardsPurchase, $balancePoints);
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
    
    /**
     * @param $result
     * @param $mirasvitRewardsModelConfig
     * @param $quote
     * @param $transaction
     * @return boolean
     */
    public function filterSkipValidateShippingForProcessNewOrder($result, $mirasvitRewardsModelConfig, $quote, $transaction)
    {
        return $result || $mirasvitRewardsModelConfig->getGeneralIsSpendShipping();
    }
    
    /**
     * To run filter to check if the Mirasvit rewards can be applied to shipping.
     *
     * @param boolean $result
     * @param Mirasvit\Rewards\Model\Config $mirasvitRewardsModelConfig
     * 
     * @return boolean
     */
    public function checkMirasvitRewardsIsShippingIncluded($result, $mirasvitRewardsModelConfig)
    {
        return $mirasvitRewardsModelConfig->getGeneralIsSpendShipping();
    }
    
    /**
     * Exclude the Mirasvit rewards points from shipping discount, so the Bolt can apply Mirasvit rewards points to shipping properly.
     *
     * @param float $result
     * @param Quote|object $quote
     * @param Address|object $shippingAddress
     * 
     * @return float
     */
    public function collectShippingDiscounts($result,
                                     $quote,
                                     $shippingAddress)
    {
        $mirasvitRewardsShippingDiscountAmount = $this->sessionHelper->getCheckoutSession()->getMirasvitRewardsShippingDiscountAmount(0);
        $result -= $mirasvitRewardsShippingDiscountAmount;
        return $result;
    }
    
}
