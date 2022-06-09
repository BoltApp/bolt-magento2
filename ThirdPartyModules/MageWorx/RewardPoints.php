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

namespace Bolt\Boltpay\ThirdPartyModules\MageWorx;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Model\Quote;

class RewardPoints
{
    const MAGEWORX_REWARD = 'mageworx_rewards_point';    
    const MAGEWORX_REWARDS_APPLY_MODE_NONE = 'none';    
    const MAGEWORX_REWARDS_APPLY_MODE_PART = 'part';    
    const MAGEWORX_REWARDS_APPLY_MODE_ALL  = 'all';
    
    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;
    
    /**
     * @var Config
     */
    private $configHelper;
    
    /**
     * @var CustomerSession
     */
    private $customerSession;
    
    /**
     * @var \MageWorx\RewardPoints\Helper\Data
     */
    private $mageWorxRewardPointsHelper;
    
    /**
     * @var \MageWorx\RewardPoints\Api\CustomerBalanceRepositoryInterface
     */
    private $customerBalanceRepository;
    
    private $appliedRewardsMode;

    /**
     * RewardPoints constructor.
     *
     * @param Bugsnag          $bugsnagHelper
     * @param CustomerSession  $customerSession
     * @param Config           $configHelper
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        CustomerSession $customerSession,
        Config $configHelper
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->customerSession = $customerSession;
        $this->configHelper = $configHelper;
    }
    
    /**
     * @param array $result
     * @param \MageWorx\RewardPoints\Api\CustomerBalanceRepositoryInterface $customerBalanceRepository
     * @param \MageWorx\RewardPoints\Model\PointCurrencyConverter $pointCurrencyConverter
     * @param \MageWorx\RewardPoints\Helper\Data $mageWorxRewardPointsHelper
     * @param Quote $quote
     * @param Quote $parentQuote
     * @param bool $paymentOnly
     *
     * @return array
     */
    public function collectDiscounts(
        $result,
        $customerBalanceRepository,
        $pointCurrencyConverter,
        $mageWorxRewardPointsHelper,
        $quote,
        $parentQuote,
        $paymentOnly
    ) {
        list ($discounts, $totalAmount, $diff) = $result;
        
        try {
            if ($quote->getUseMwRewardPoints()
                && $quote->getCustomer()->getId()) {
                $this->mageWorxRewardPointsHelper = $mageWorxRewardPointsHelper;
                $this->customerBalanceRepository = $customerBalanceRepository;
                $requestedPoints = $this->getMageWorxRewardsPoints($quote);  
                if ($requestedPoints < 0.01) {
                    // Reward Points amount is too small
                    return [$discounts, $totalAmount, $diff];
                }
                $currencyAmount = $pointCurrencyConverter->getCurrencyByPoints(
                    $requestedPoints
                );
                if (!$currencyAmount) {
                    // Reward Points currency amount is zero
                    return [$discounts, $totalAmount, $diff];
                }
                $currencyCode = $quote->getQuoteCurrencyCode();
                $roundedAmount = CurrencyUtils::toMinor($currencyAmount, $currencyCode);
                $discounts[] = [
                    'description'       => 'Reward Points',
                    'amount'            => $roundedAmount,
                    'reference'         => self::MAGEWORX_REWARD,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    // For v1/discounts.code.apply and v2/cart.update
                    'discount_type'     => Discount::BOLT_DISCOUNT_TYPE_FIXED,
                    // For v1/merchant/order
                    'type'              => Discount::BOLT_DISCOUNT_TYPE_FIXED,
                ];
                $diff -= CurrencyUtils::toMinorWithoutRounding($currencyAmount, $currencyCode) - $roundedAmount;
                $totalAmount -= $roundedAmount;
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return [$discounts, $totalAmount, $diff];
        }
    }

    /**
     * Add MageWorx Reward Points to layout to be rendered below the cart
     *
     * @param array $jsLayout
     * @param \MageWorx\RewardPoints\Helper\Data $mageWorxRewardPointsHelper
     *
     * @return array
     */
    public function collectCartDiscountJsLayout(
        $jsLayout,
        $mageWorxRewardPointsHelper
    ) {
        if ($this->configHelper->getUseMageWorxRewardPointsConfig()
            && $mageWorxRewardPointsHelper->isEnableForCustomer()) {
            $jsLayout["mageworx-reward-points"] = [
                "sortOrder" => 0,
                "component" => "MageWorx_RewardPoints/js/view/payment/rewardpoints",
                "children"  => [
                    "errors" => [
                        "sortOrder"   => 0,
                        "component"   => "MageWorx_RewardPoints/js/view/payment/rewardpoints-messages",
                        "displayArea" => "messages",
                    ]
                ],
                "inputPlaceholder" => $mageWorxRewardPointsHelper->getCustomPointsInputPlaceholder()
            ];
            $jsLayout["mageworx-reward-points"]["config"] = [
                "template" => $mageWorxRewardPointsHelper->isAllowedCustomPointsAmount()
                            ? 'Bolt_Boltpay/third-party-modules/mageworx/reward-points/cart/rewardpoints_custom_amount'
                            : 'Bolt_Boltpay/third-party-modules/mageworx/reward-points/cart/rewardpoints'
            ];
        }

        return $jsLayout;
    }
    
    /**
     * Get additional conditions to compare the quote totals.
     *
     * @param string $result
     * @param \MageWorx\RewardPoints\Helper\Data $mageWorxRewardPointsHelper
     * 
     * @return string
     */
    public function getAdditionalQuoteTotalsConditions($result, $mageWorxRewardPointsHelper)
    {
        if ($this->configHelper->getUseMageWorxRewardPointsConfig()
            && $mageWorxRewardPointsHelper->isEnableForCustomer()) {
            $result .= '&& (totals.base_subtotal-totals.base_rwrdpoints_cur_amnt) * 100 == BoltState.boltCart.total_amount.amount';
        }

        return $result;
    }
    
    /**
     * Check whether the customer choose to use maximum
     */
    private function getAppliedRewardsMode()
    {
        if (!$this->mageWorxRewardPointsHelper->isEnableForCustomer()) {
            $this->appliedRewardsMode = self::MAGEWORX_REWARDS_APPLY_MODE_NONE;
        }
        $boltCustomerMageWorxRewardsMode = $this->customerSession->getBoltMageWorxRewardsMode();
        if (!empty($boltCustomerMageWorxRewardsMode)
            && $boltCustomerMageWorxRewardsMode == self::MAGEWORX_REWARDS_APPLY_MODE_PART
        ) {
            $this->appliedRewardsMode = self::MAGEWORX_REWARDS_APPLY_MODE_PART;
        } else {
            $this->appliedRewardsMode = self::MAGEWORX_REWARDS_APPLY_MODE_ALL;
        }
    }
    
    /**
     * If enabled, gets the MageWorx rewards points used
     *
     * @param Quote $quote
     *
     * @return int
     */
    private function getMageWorxRewardsPoints($quote)
    {
        try {
            $this->getAppliedRewardsMode();     
            if ($this->appliedRewardsMode == self::MAGEWORX_REWARDS_APPLY_MODE_NONE) {
                return 0;
            }
            if ($this->appliedRewardsMode == self::MAGEWORX_REWARDS_APPLY_MODE_PART) {
                return $quote->getMwRwrdpointsAmnt();
            }
            $customerBalance = $this->customerBalanceRepository->getByCustomer(
                $quote->getCustomer()->getId(),
                $quote->getStore()->getWebsiteId()
            );
            if (!$customerBalance->getId()) {
                return 0;
            }

            return $customerBalance->getPoints();
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
            return 0;
        }
    }
}
