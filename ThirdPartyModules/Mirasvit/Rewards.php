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
     * Rewards constructor.
     * @param Bugsnag $bugsnagHelper
     * @param Discount $discountHelper
     * @param ScopeInterface $scopeConfigInterface
     * @param CustomerFactory $customerFactory
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        Discount $discountHelper,
        ScopeConfigInterface $scopeConfigInterface,
        CustomerFactory $customerFactory
    )
    {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->discountHelper = $discountHelper;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->customerFactory = $customerFactory;
    }

    /**
     * @param $mirasvitRewardsPurchaseHelper
     * @param $immutableQuote
     */
    public function applyExternalDiscountData($mirasvitRewardsPurchaseHelper, $immutableQuote)
    {
        $this->mirasvitRewardsPurchaseHelper = $mirasvitRewardsPurchaseHelper;
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
        /** @var \Mirasvit\Rewards\Helper\Purchase $mirasvitRewardsPurchaseHelper */
        $mirasvitRewardsPurchaseHelper = $this->mirasvitRewardsPurchaseHelper;
        if (!$mirasvitRewardsPurchaseHelper || !$parentQuoteId) {
            return;
        }

        try {
            $parentPurchase = $mirasvitRewardsPurchaseHelper->getByQuote($parentQuoteId);
            if (abs($parentPurchase->getSpendAmount()) > 0) {
                $mirasvitRewardsPurchaseHelper->getByQuote($immutableQuote)
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
                    $pointsMoney = [0];
                    /** @var \Mirasvit\Rewards\Model\Spending\Rule $rule */
                    foreach ($rules as $rule) {
                        $tier = $rule->getTier($customer);
                        $spendPoints = $tier->getSpendPoints(); 
                        if ($spendPoints <= \Mirasvit\Rewards\Helper\Calculation::ZERO_VALUE) {
                            continue;
                        }
                        $pointsMoney[] = ($balancePoints / $spendPoints) * $tier->getMonetaryStep($spendAmount);  
                    } 
                    $points = max($pointsMoney);
                }                
                $spendAmount = max($points, $spendAmount);
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
            if($this->mirasvitRewardsModelConfig->getGeneralIsSpendShipping() && $this->getMirasvitRewardsAmount($quote)) {
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
    
}
