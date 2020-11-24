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
    const MIRASVIT_REWARDS = 'mirasvitrewards';
    
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
     * @var ThirdPartyModuleFactory
     */
    private $mirasvitRewardsRuleQuoteSubtotalCalc;

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
     * @param \Mirasvit\Rewards\Helper\Purchase $mirasvitRewardsPurchaseHelper
     * @param \Mirasvit\Rewards\Helper\Balance  $mirasvitRewardsBalanceHelper
     * @param \Mirasvit\Rewards\Helper\Balance\SpendRulesList $mirasvitRewardsSpendRulesListHelper
     * @param \Mirasvit\Rewards\Model\Config $mirasvitRewardsModelConfig
     * @param \Mirasvit\Rewards\Helper\Balance\Spend\RuleQuoteSubtotalCalc $mirasvitRewardsRuleQuoteSubtotalCalc
     * @param $immutableQuote
     */
    public function applyExternalDiscountData(
        $mirasvitRewardsPurchaseHelper,
        $mirasvitRewardsBalanceHelper,
        $mirasvitRewardsSpendRulesListHelper,
        $mirasvitRewardsModelConfig,
        $mirasvitRewardsRuleQuoteSubtotalCalc,
        $immutableQuote
    )
    {
        $this->mirasvitRewardsPurchaseHelper = $mirasvitRewardsPurchaseHelper;
        $this->mirasvitRewardsBalanceHelper = $mirasvitRewardsBalanceHelper;
        $this->mirasvitRewardsSpendRulesListHelper = $mirasvitRewardsSpendRulesListHelper;
        $this->mirasvitRewardsModelConfig = $mirasvitRewardsModelConfig;
        $this->mirasvitRewardsRuleQuoteSubtotalCalc = $mirasvitRewardsRuleQuoteSubtotalCalc;

        $this->applyMiravistRewardPoint($immutableQuote);
    }

    /**
     * @param $result
     * @param \Mirasvit\Rewards\Helper\Purchase $mirasvitRewardsPurchaseHelper
     * @param \Mirasvit\Rewards\Helper\Balance  $mirasvitRewardsBalanceHelper
     * @param \Mirasvit\Rewards\Helper\Balance\SpendRulesList $mirasvitRewardsSpendRulesListHelper
     * @param \Mirasvit\Rewards\Model\Config $mirasvitRewardsModelConfig
     * @param \Mirasvit\Rewards\Helper\Balance\Spend\RuleQuoteSubtotalCalc $mirasvitRewardsRuleQuoteSubtotalCalc
     * @param $quote
     * @param $parentQuote
     * @param $paymentOnly
     * 
     * @return array
     */
    public function collectDiscounts(
        $result,
        $mirasvitRewardsPurchaseHelper,
        $mirasvitRewardsBalanceHelper,
        $mirasvitRewardsSpendRulesListHelper,
        $mirasvitRewardsModelConfig,
        $mirasvitRewardsRuleQuoteSubtotalCalc,
        $quote,
        $parentQuote,
        $paymentOnly
    )
    {
        $this->mirasvitRewardsPurchaseHelper = $mirasvitRewardsPurchaseHelper;
        $this->mirasvitRewardsBalanceHelper = $mirasvitRewardsBalanceHelper;
        $this->mirasvitRewardsSpendRulesListHelper = $mirasvitRewardsSpendRulesListHelper;
        $this->mirasvitRewardsModelConfig = $mirasvitRewardsModelConfig;
        $this->mirasvitRewardsRuleQuoteSubtotalCalc = $mirasvitRewardsRuleQuoteSubtotalCalc;
        
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
                    'reference' => self::MIRASVIT_REWARDS,
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
     * @param \Mirasvit\Rewards\Helper\Purchase $mirasvitRewardsPurchaseHelper
     * @param \Mirasvit\Rewards\Helper\Balance  $mirasvitRewardsBalanceHelper
     * @param \Mirasvit\Rewards\Helper\Balance\SpendRulesList $mirasvitRewardsSpendRulesListHelper
     * @param \Mirasvit\Rewards\Model\Config $mirasvitRewardsModelConfig
     * @param \Mirasvit\Rewards\Helper\Balance\Spend\RuleQuoteSubtotalCalc $mirasvitRewardsRuleQuoteSubtotalCalc
     * @param $quote
     * 
     * @return string
     */
    public function filterApplyExternalQuoteData(
        $result,
        $mirasvitRewardsPurchaseHelper,
        $mirasvitRewardsBalanceHelper,
        $mirasvitRewardsSpendRulesListHelper,
        $mirasvitRewardsModelConfig,
        $mirasvitRewardsRuleQuoteSubtotalCalc,
        $quote
    )
    {
        $this->mirasvitRewardsPurchaseHelper = $mirasvitRewardsPurchaseHelper;
        $this->mirasvitRewardsBalanceHelper = $mirasvitRewardsBalanceHelper;
        $this->mirasvitRewardsSpendRulesListHelper = $mirasvitRewardsSpendRulesListHelper;
        $this->mirasvitRewardsModelConfig = $mirasvitRewardsModelConfig;
        $this->mirasvitRewardsRuleQuoteSubtotalCalc = $mirasvitRewardsRuleQuoteSubtotalCalc;

        if ($rewardsAmount = abs($this->getMirasvitRewardsAmount($quote))) {
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
                $websiteId = $quote->getStore()->getWebsiteId();
                $rules = $this->mirasvitRewardsSpendRulesListHelper->getRuleCollection($websiteId, $customer->getGroupId());
                
                if ($rules->count()) {
                    // To collect all of available rewards points for Bolt cart,
                    // we create a mock for the subtotal of quote which is large enough.
                    $mockSubtotal = 999999999;
                    $cartRange = $this->getCartPointsRange($quote, $customer, $rules, $balancePoints, $mockSubtotal);                    
                    $cartMaxPointsNumber = min($cartRange->getMaxPoints(), $balancePoints);
                    $spendAmount = $this->calMaxSpendAmount($rules, $customer, $cartMaxPointsNumber, $mockSubtotal);
                } else {
                    $spendAmount = 0;
                }
            }

            return $spendAmount;
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
            return 0;
        }        
    }
    
    /**
     * @param \Mirasvit\Rewards\Model\Spending\Rule $rule
     * @param \Magento\Customer\Model\Customer      $customer
     * @param float                                 $pointsNumber
     * @param float                                 $quoteSubTotal
     *
     * @return float
     */
    private function calMaxSpendAmount($rules, $customer, $pointsNumber, $quoteSubTotal)
    {
        $totalAmount = 0;
        
        foreach ($rules as $rule) {
            $rule->afterLoad();
            if ($pointsNumber > 0) {
                $rulePointsNumber = $pointsNumber;
                
                $tier = $rule->getTier($customer);

                if ($tier->getSpendingStyle() == \Mirasvit\Rewards\Api\Config\Rule\SpendingStyleInterface::STYLE_PARTIAL) {
                    $stepsSecond = round($rulePointsNumber / $tier->getSpendPoints(), 2, PHP_ROUND_HALF_DOWN);
                } else {
                    $spendPoints = $tier->getSpendPoints();
                    $rulePointsNumber = floor($rulePointsNumber / $spendPoints) * $spendPoints;
                    $stepsSecond = floor($rulePointsNumber / $spendPoints);
                }

                if ($rulePointsNumber < $tier->getSpendMinAmount($quoteSubTotal)) {
                    continue;
                }

                $stepsFirst = round($quoteSubTotal / $tier->getMonetaryStep($quoteSubTotal), 2, PHP_ROUND_HALF_DOWN);
                if ($stepsFirst != $quoteSubTotal / $tier->getMonetaryStep($quoteSubTotal)) {
                    ++$stepsFirst;
                }

                $steps = min($stepsFirst, $stepsSecond);

                $amount = $steps * $tier->getMonetaryStep($quoteSubTotal);
                $amount = min($amount, $quoteSubTotal);

                $totalAmount += $amount;
          
                $pointsNumber -= $rulePointsNumber;

                if ($rule->getIsStopProcessing()) {
                    break;
                }
            }
        }
        
        return $totalAmount;
    }
    
    /**
     * Calcs min and max amount of spend points for quote
     *
     * @param \Magento\Quote\Model\Quote            $quote
     * @param \Magento\Customer\Model\Customer      $customer
     * @param \Mirasvit\Rewards\Model\Spending\Rule $rule
     * @param float                                 $balancePoints
     * @param float                                 $quoteSubTotal
     *
     * @return \Magento\Framework\DataObject
     */
    private function getCartPointsRange($quote, $customer, $rules, $balancePoints, $quoteSubTotal)
    {
        $minPoints     = 0;
        $totalPoints   = 0;

        $data = new \Mirasvit\Rewards\Helper\Balance\SpendCartRangeData($quoteSubTotal, $balancePoints, $minPoints, $totalPoints);

        foreach ($rules as $rule) {
            $rule->afterLoad();
            
            $ruleSubTotal = $this->mirasvitRewardsRuleQuoteSubtotalCalc->getLimitedSubtotal($quote, $rule);
            if ($ruleSubTotal <= \Mirasvit\Rewards\Helper\Calculation::ZERO_VALUE) {
                continue;
            }
            
            $tier = $rule->getTier($customer);
            $data = $this->calcPointsPerRule($tier, $data);

            if ($rule->getIsStopProcessing()) {
                break;
            }
        }

        if ($data->minPoints > $data->maxPoints) {
            $data->minPoints = $data->maxPoints = 0;
        }

        return new \Magento\Framework\DataObject([
            'min_points'  => $data->minPoints,
            'max_points'  => $data->maxPoints,
        ]);
    }
    
    /**
     * @param \Mirasvit\Rewards\Model\Spending\Tier $tier
     * @param SpendCartRangeData                    $data
     *
     * @return SpendCartRangeData
     */
    private function calcPointsPerRule($tier, $data)
    {        
        $ruleSubTotal = $data->subtotal;       

        $monetaryStep    = $tier->getMonetaryStep($ruleSubTotal);
        $ruleMinPoints   = $tier->getSpendMinAmount($ruleSubTotal);
        $ruleMaxPoints   = $tier->getSpendMaxAmount($ruleSubTotal);
        $ruleSpendPoints = $tier->getSpendPoints();

        if (!$this->isRuleValid($ruleMinPoints, $ruleMaxPoints, $monetaryStep, $ruleSpendPoints, $data)) {
            return $data;
        }

        $ruleMinPoints = $ruleMinPoints ? max($ruleMinPoints, $ruleSpendPoints) : $ruleSpendPoints;

        $data->minPoints = $data->minPoints ? min($data->minPoints, $ruleMinPoints) : $ruleMinPoints;

        if ($tier->getSpendingStyle() == \Mirasvit\Rewards\Api\Config\Rule\SpendingStyleInterface::STYLE_FULL) {
            $roundedTotalPoints = floor($ruleMaxPoints / $ruleSpendPoints) * $ruleSpendPoints;
            if ($roundedTotalPoints < $ruleMaxPoints) {
                $ruleMaxPoints = $roundedTotalPoints + $ruleSpendPoints;
            } else {
                $ruleMaxPoints = $roundedTotalPoints;
            }
            if ($ruleMinPoints <= $ruleMaxPoints) {
                $data->subtotal  -= $ruleMaxPoints / $ruleSpendPoints * $monetaryStep;
                $data->maxPoints += $ruleMaxPoints;
            }
            if ($data->maxPoints > $data->balancePoints) {
                $data->maxPoints = floor($data->balancePoints / $ruleSpendPoints) * $ruleSpendPoints;
            }
        } elseif ($ruleMinPoints <= $ruleMaxPoints) {
            $data->subtotal  -= $ruleMaxPoints / $ruleSpendPoints * $monetaryStep;
            $data->maxPoints += $ruleMaxPoints;
        }

        return $data;
    }
    
    /**
     * @param float              $ruleMinPoints
     * @param float              $ruleMaxPoints
     * @param float              $monetaryStep
     * @param float              $ruleSpendPoints
     * @param SpendCartRangeData $data
     *
     * @return bool
     */
    private function isRuleValid($ruleMinPoints, $ruleMaxPoints, $monetaryStep, $ruleSpendPoints, $data)
    {
        if ($ruleMinPoints > $ruleMaxPoints) {
            return false;
        }
        if ($ruleMinPoints && ($data->subtotal / $monetaryStep) < 1) {
            return false;
        }
        if ($ruleMinPoints > $data->balancePoints || $ruleSpendPoints <= \Mirasvit\Rewards\Helper\Calculation::ZERO_VALUE) {
            return false;
        }
        if ($monetaryStep <= \Mirasvit\Rewards\Helper\Calculation::ZERO_VALUE) {
            return false;
        }

        return true;
    }
    
    /**
     * @param \Mirasvit\Rewards\Helper\Purchase $mirasvitRewardsPurchaseHelper
     * @param \Mirasvit\Rewards\Helper\Balance  $mirasvitRewardsBalanceHelper
     * @param \Mirasvit\Rewards\Helper\Balance\SpendRulesList $mirasvitRewardsSpendRulesListHelper
     * @param \Mirasvit\Rewards\Model\Config $mirasvitRewardsModelConfig
     * @param \Mirasvit\Rewards\Helper\Checkout $mirasvitRewardsCheckoutHelper
     * @param \Mirasvit\Rewards\Helper\Balance\Spend\RuleQuoteSubtotalCalc $mirasvitRewardsRuleQuoteSubtotalCalc
     * @param $quote
     */
    public function beforeValidateQuoteDataForProcessNewOrder(
        $mirasvitRewardsPurchaseHelper,
        $mirasvitRewardsBalanceHelper,
        $mirasvitRewardsSpendRulesListHelper,
        $mirasvitRewardsModelConfig,
        $mirasvitRewardsCheckoutHelper,
        $mirasvitRewardsRuleQuoteSubtotalCalc,
        $quote
    )
    {
        $this->mirasvitRewardsPurchaseHelper = $mirasvitRewardsPurchaseHelper;
        $this->mirasvitRewardsBalanceHelper = $mirasvitRewardsBalanceHelper;
        $this->mirasvitRewardsSpendRulesListHelper = $mirasvitRewardsSpendRulesListHelper;
        $this->mirasvitRewardsModelConfig = $mirasvitRewardsModelConfig;
        $this->mirasvitRewardsRuleQuoteSubtotalCalc = $mirasvitRewardsRuleQuoteSubtotalCalc;
        
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
     * @param \Mirasvit\Rewards\Model\Config $mirasvitRewardsModelConfig
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
    public function collectShippingDiscounts($result, $quote, $shippingAddress)
    {
        $mirasvitRewardsShippingDiscountAmount = $this->sessionHelper->getCheckoutSession()->getMirasvitRewardsShippingDiscountAmount(0);
        $result -= $mirasvitRewardsShippingDiscountAmount;
        return $result;
    }
    
    /**
     * Return code if the quote has Mirasvit rewards.
     * 
     * @param $result
     * @param \Mirasvit\Rewards\Helper\Purchase $mirasvitRewardsPurchaseHelper
     * @param \Mirasvit\Rewards\Helper\Balance  $mirasvitRewardsBalanceHelper
     * @param \Mirasvit\Rewards\Helper\Balance\SpendRulesList $mirasvitRewardsSpendRulesListHelper
     * @param \Mirasvit\Rewards\Model\Config $mirasvitRewardsModelConfig
     * @param \Mirasvit\Rewards\Helper\Balance\Spend\RuleQuoteSubtotalCalc $mirasvitRewardsRuleQuoteSubtotalCalc
     * @param $couponCode
     * @param $quote
     * 
     * @return array
     */
    public function filterVerifyAppliedStoreCredit (
        $result,
        $mirasvitRewardsPurchaseHelper,
        $mirasvitRewardsBalanceHelper,
        $mirasvitRewardsSpendRulesListHelper,
        $mirasvitRewardsModelConfig,
        $mirasvitRewardsRuleQuoteSubtotalCalc,
        $couponCode,
        $quote
    )
    {
        if ($couponCode == self::MIRASVIT_REWARDS) {
            $this->mirasvitRewardsPurchaseHelper = $mirasvitRewardsPurchaseHelper;
            $this->mirasvitRewardsBalanceHelper = $mirasvitRewardsBalanceHelper;
            $this->mirasvitRewardsSpendRulesListHelper = $mirasvitRewardsSpendRulesListHelper;
            $this->mirasvitRewardsModelConfig = $mirasvitRewardsModelConfig;
            $this->mirasvitRewardsRuleQuoteSubtotalCalc = $mirasvitRewardsRuleQuoteSubtotalCalc;
            
            try {
                if (abs($this->getMirasvitRewardsAmount($quote)) > 0) {
                    $result[] = $couponCode;
                }
            } catch (\Exception $e) {
                $this->bugsnagHelper->notifyException($e);
            }
        }
        
        return $result;
    }
    
    /**
     * Remove Mirasvit rewards from the quote.
     *
     * @param \Mirasvit\Rewards\Helper\Purchase $mirasvitRewardsPurchaseHelper
     * @param \Mirasvit\Rewards\Helper\Checkout $mirasvitRewardsCheckoutHelper
     * @param $couponCode
     * @param $quote
     * @param $websiteId
     * @param $storeId
     * 
     */
    public function removeAppliedStoreCredit (
        $mirasvitRewardsPurchaseHelper,
        $mirasvitRewardsCheckoutHelper,
        $couponCode,
        $quote,
        $websiteId,
        $storeId
    )
    {
        try {
            if ($couponCode == self::MIRASVIT_REWARDS) {
                $miravitRewardsPurchase = $mirasvitRewardsPurchaseHelper->getByQuote($quote);
                $mirasvitRewardsCheckoutHelper->updatePurchase($miravitRewardsPurchase, 0);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
    
}
