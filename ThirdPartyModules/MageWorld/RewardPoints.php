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

namespace Bolt\Boltpay\ThirdPartyModules\MageWorld;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;

class RewardPoints
{    
    /**
     * @var Discount
     */
    protected $discountHelper;
    
    /**
     * @var Bugsnag
     */
    protected $bugsnagHelper;
    
    /**
     * @var \Mirasvit\Credit\Helper\Data
     */
    protected $mwRewardPointsHelperData;
    
    /**
     * @var \MW\RewardPoints\Model\CustomerFactory
     */
    protected $mwRewardPointsModelCustomer;

    /**
     * @param Discount       $discountHelper
     * @param Bugsnag        $bugsnagHelper
     */
    public function __construct(
        Discount               $discountHelper,
        Bugsnag                $bugsnagHelper
    ) {
        $this->discountHelper = $discountHelper;
        $this->bugsnagHelper  = $bugsnagHelper;
    }

    /**
     * Collect MW reward points data when building Bolt cart.
     *
     * @param array $result
     * @param MW\RewardPoints\Helper\Data $mwRewardPointsHelperData
     * @param MW\RewardPoints\Model\CustomerFactory $mwRewardPointsModelCustomer
     * @param Quote $quote
     *
     * @return array
     */
    public function collectDiscounts($result,
                                     $mwRewardPointsHelperData,
                                     $mwRewardPointsModelCustomer,
                                     $quote)
    {
        list ($discounts, $totalAmount, $diff) = $result;
        $this->mwRewardPointsHelperData = $mwRewardPointsHelperData;
        $this->mwRewardPointsModelCustomer = $mwRewardPointsModelCustomer;

        try {
            if ($quote->getMwRewardpoint()) {
                $currencyCode = $quote->getQuoteCurrencyCode();
                $storeCode = $quote->getStore()->getCode();
                if (
                    $quote->getMwRewardpointDiscount() >= $quote->getSubtotal()
                    && ($this->mwRewardPointsHelperData->getRedeemedShippingConfig($storeCode)
                    || $this->mwRewardPointsHelperData->getRedeemedTaxConfig($storeCode))
                ) {
                    $rewardPoints = $this->mwRewardPointsModelCustomer->create()->load($quote->getCustomerId())->getMwRewardPoint();
                    $amount = abs($this->mwRewardPointsHelperData->exchangePointsToMoneys($rewardPoints, $storeCode));
                } else {
                    $amount = $quote->getMwRewardpointDiscount();
                }

                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                $discounts[] = [
                    'description'       => 'Reward Points',
                    'amount'            => $roundedAmount,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    'discount_type'     => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/discounts.code.apply and v2/cart.update
                    'type'              => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/merchant/order
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
     * Add js to the cart page to refresh Bolt cart when the applied reward points changes.
     *
     * @param string $result
     *
     * @return string
     */
    public function getAdditionalJS($result)
    {
        try {
            $result .= 'var mwRewardPointsAjaxData = "";
            $(document).ajaxSuccess(function( event, xhr, settings ) {
                if (settings.url.indexOf("rewardpointspost") > -1 && mwRewardPointsAjaxData != settings.data) {
                    $(document).trigger("bolt:createOrder");
                    mwRewardPointsAjaxData = settings.data;
                }
            });';
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {        
            return $result;
        }
    }
    
    /**
     * Update reward points to the quote.
     *
     * @param MW\RewardPoints\Helper\Data $mwRewardPointsHelperData
     * @param MW\RewardPoints\Model\CustomerFactory $mwRewardPointsModelCustomer
     * @param Quote $quote
     *
     */
    public function beforePrepareQuote($mwRewardPointsHelperData,
                                     $mwRewardPointsModelCustomer,
                                     $quote)
    {
        $this->mwRewardPointsHelperData = $mwRewardPointsHelperData;
        $this->mwRewardPointsModelCustomer = $mwRewardPointsModelCustomer;
        
        try {
            if ($quote->getMwRewardpoint()) {
                $storeCode = $quote->getStore()->getCode();
                if (
                    $quote->getMwRewardpointDiscount() >= $quote->getSubtotal()
                    && ($this->mwRewardPointsHelperData->getRedeemedShippingConfig($storeCode)
                    || $this->mwRewardPointsHelperData->getRedeemedTaxConfig($storeCode))
                ) {
                    $rewardPoints = $this->mwRewardPointsModelCustomer->create()->load($quote->getCustomerId())->getMwRewardPoint();
                    $amount = abs($this->mwRewardPointsHelperData->exchangePointsToMoneys($rewardPoints, $storeCode));                        
                    $this->mwRewardPointsHelperData->setPointToCheckOut($rewardPoints);
                    $quote->setSpendRewardpointCart($rewardPoints);
                    
                    $quote->setMwRewardpoint($rewardPoints)
                          ->setMwRewardpointDiscount($amount)
                          ->setMwRewardpointDiscountShow($amount)
                          ->save();
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
    
}
