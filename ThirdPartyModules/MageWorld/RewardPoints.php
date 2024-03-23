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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\MageWorld;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Framework\Session\SessionManagerInterface as SessionManager;
use Magento\Quote\Model\Quote;

class RewardPoints
{
    const MW_REWARDPOINTS = 'mw_reward_points';
    
    /**
     * @var Bugsnag
     */
    protected $bugsnagHelper;
    
    /**
     * @var SessionManager|mixed
     */
    protected $sessionManager;

    protected $mwRewardPointsHelperData;

    protected $mwRewardPointsModelCustomer;

    /**
     * @param Bugsnag        $bugsnagHelper
     */
    public function __construct(
        Bugsnag         $bugsnagHelper,
        SessionManager  $sessionManager
    ) {
        $this->bugsnagHelper  = $bugsnagHelper;
        $this->sessionManager  = $sessionManager;
    }

    /**
     * Collect MW reward points data when building Bolt cart.
     *
     * @param array $result
     * @param $mwRewardPointsHelperData
     * @param $mwRewardPointsModelCustomer
     * @param Quote|mixed $quote
     *
     * @return array
     */
    public function collectDiscounts(
        $result,
        $mwRewardPointsHelperData,
        $mwRewardPointsModelCustomer,
        $quote
    ) {
        list ($discounts, $totalAmount, $diff) = $result;
        $this->mwRewardPointsHelperData = $mwRewardPointsHelperData;
        $this->mwRewardPointsModelCustomer = $mwRewardPointsModelCustomer;

        try {
            if ($quote->getMwRewardpoint()) {
                $currencyCode = $quote->getQuoteCurrencyCode();
                $storeCode = $quote->getStore()->getCode();
                if (
                    CurrencyUtils::toMinor($quote->getMwRewardpointDiscount(), $currencyCode) >= CurrencyUtils::toMinor($quote->getSubtotal(), $currencyCode) - $this->getDiscountedAmount($quote, $currencyCode)
                    && ($this->mwRewardPointsHelperData->getRedeemedShippingConfig($storeCode) || $this->mwRewardPointsHelperData->getRedeemedTaxConfig($storeCode))
                ) {
                    $rewardPoints = $this->mwRewardPointsModelCustomer->create()
                        ->load($quote->getCustomerId())->getMwRewardPoint();
                    $amount = abs((float)$this->mwRewardPointsHelperData->exchangePointsToMoneys($rewardPoints, $storeCode));
                } else {
                    $amount = $quote->getMwRewardpointDiscount();
                }

                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                $discounts[] = [
                    'description'       => 'Reward Points',
                    'amount'            => $roundedAmount,
                    'reference'         => self::MW_REWARDPOINTS,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    // For v1/discounts.code.apply and v2/cart.update
                    'discount_type'     => Discount::BOLT_DISCOUNT_TYPE_FIXED,
                    // For v1/merchant/order
                    'type'              => Discount::BOLT_DISCOUNT_TYPE_FIXED,
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
     * @param $mwRewardPointsHelperData
     * @param $mwRewardPointsModelCustomer
     * @param Quote|mixed $quote
     *
     */
    public function beforePrepareQuote(
        $mwRewardPointsHelperData,
        $mwRewardPointsModelCustomer,
        $quote
    ) {
        $this->mwRewardPointsHelperData = $mwRewardPointsHelperData;
        $this->mwRewardPointsModelCustomer = $mwRewardPointsModelCustomer;

        try {
            if ($quote->getMwRewardpoint()) {
                $storeCode = $quote->getStore()->getCode();
                $currencyCode = $quote->getQuoteCurrencyCode();
                if (
                    CurrencyUtils::toMinor($quote->getMwRewardpointDiscount(), $currencyCode) >= CurrencyUtils::toMinor($quote->getSubtotal(), $currencyCode) - $this->getDiscountedAmount($quote, $currencyCode)
                    && ($this->mwRewardPointsHelperData->getRedeemedShippingConfig($storeCode)
                        || $this->mwRewardPointsHelperData->getRedeemedTaxConfig($storeCode))
                ) {
                    $rewardPoints = $this->mwRewardPointsModelCustomer->create()
                        ->load($quote->getCustomerId())->getMwRewardPoint();
                    $amount = abs((float)$this->mwRewardPointsHelperData->exchangePointsToMoneys($rewardPoints, $storeCode));
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
    
    /**
     * Return code if the quote has MW reward points
     *
     * @param $result
     * @param $couponCode
     * @param $quote
     *
     * @return array
     */
    public function filterVerifyAppliedStoreCredit(
        $result,
        $couponCode,
        $quote
    ) {
        if ($couponCode == self::MW_REWARDPOINTS && $quote->getMwRewardpoint()) {
            $result[] = $couponCode;
        }
        
        return $result;
    }

    /**
     * Remove MW reward points from the quote.
     * @param $couponCode
     * @param $quote
     * @param $websiteId
     * @param $storeId
     * @return void
     */
    public function removeAppliedStoreCredit(
        $couponCode,
        $quote,
        $websiteId,
        $storeId
    ) {
        try {
            if ($couponCode == self::MW_REWARDPOINTS && $quote->getMwRewardpoint()) {
                $this->sessionManager->setMwRewardpointDiscountShowTotal(0)
                                     ->setMwRewardpointDiscountTotal(0)
                                     ->setMwRewardpointAfterDrop(0);
    
                $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
                $address->setMwRewardpoint(0)
                        ->setMwRewardpointDiscount(0)
                        ->setMwRewardpointDiscountShow(0)
                        ->setMwRewardpointDiscount(0);

                $quote->setMwRewardpoint(0)
                      ->setMwRewardpointDiscount(0)
                      ->setMwRewardpointDiscountShow(0)
                      ->setSpendRewardpointCart(0)
                      ->collectTotals()
                      ->save();
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @param $result
     * @param $mwRewardPointsHelperData
     * @param $mwRewardPointsModelCustomerFactory
     * @param $quote
     * @return int|mixed
     */
    public function filterShippingAmount($result,
                                         $mwRewardPointsHelperData,
                                         $mwRewardPointsModelCustomerFactory,
                                         $quote)
    {
        $this->mwRewardPointsHelperData = $mwRewardPointsHelperData;
        $this->mwRewardPointsModelCustomer = $mwRewardPointsModelCustomerFactory;
        try {
            if ($quote->getMwRewardpoint()) {
                $storeCode = $quote->getStore()->getCode();
                $currencyCode = $quote->getQuoteCurrencyCode();
                $discountedAmount = $this->getDiscountedAmount($quote, $currencyCode);
                if (
                    CurrencyUtils::toMinor($quote->getMwRewardpointDiscount(), $currencyCode) >= CurrencyUtils::toMinor($quote->getSubtotal(), $currencyCode) - $discountedAmount
                    && $this->mwRewardPointsHelperData->getRedeemedShippingConfig($storeCode)
                    && !$this->mwRewardPointsHelperData->getRedeemedTaxConfig($storeCode)
                ) {
                    $rewardPoints = $this->mwRewardPointsModelCustomer->create()
                        ->load($quote->getCustomerId())->getMwRewardPoint();
                    $maximumAmount = CurrencyUtils::toMinor(abs((float)$this->mwRewardPointsHelperData->exchangePointsToMoneys($rewardPoints, $storeCode)), $currencyCode);
                    $quoteSubtotal = CurrencyUtils::toMinor($quote->getSubtotal(), $currencyCode);

                    $quoteSubtotalIncludeShippingAndDiscount = $quoteSubtotal - $discountedAmount + $result;
                    if ($maximumAmount > $quoteSubtotalIncludeShippingAndDiscount) {
                        $result = $maximumAmount - $quoteSubtotal + $discountedAmount;
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->bugsnagHelper->notifyException($exception);
        }

        return $result;
    }

    /**
     * @param $result
     * @param $mwRewardPointsHelperData
     * @param $mwRewardPointsModelCustomerFactory
     * @param $quote
     * @return int
     */
    public function adjustShippingAmountInTaxEndPoint($result,
                                         $mwRewardPointsHelperData,
                                         $mwRewardPointsModelCustomerFactory,
                                         $quote)
    {
        $this->mwRewardPointsHelperData = $mwRewardPointsHelperData;
        $this->mwRewardPointsModelCustomer = $mwRewardPointsModelCustomerFactory;
        try {
            if ($quote->getMwRewardpoint()) {
                $storeCode = $quote->getStore()->getCode();
                $currencyCode = $quote->getQuoteCurrencyCode();
                $discountedAmount = $this->getDiscountedAmount($quote, $currencyCode);
                if (
                    CurrencyUtils::toMinor($quote->getMwRewardpointDiscount(), $currencyCode) >= CurrencyUtils::toMinor($quote->getSubtotal(), $currencyCode) - $discountedAmount
                    && $this->mwRewardPointsHelperData->getRedeemedShippingConfig($storeCode)
                    && !$this->mwRewardPointsHelperData->getRedeemedTaxConfig($storeCode)
                ) {
                    $rewardPoints = $this->mwRewardPointsModelCustomer->create()
                        ->load($quote->getCustomerId())->getMwRewardPoint();
                    $maximumAmount = CurrencyUtils::toMinor(abs($this->mwRewardPointsHelperData->exchangePointsToMoneys($rewardPoints, $storeCode)), $currencyCode);
                    $quoteSubtotal = CurrencyUtils::toMinor($quote->getSubtotal(), $currencyCode);

                    $quoteSubtotalIncludeShippingAndDiscount = $quoteSubtotal - $discountedAmount + $result;
                    if ($maximumAmount > $quoteSubtotalIncludeShippingAndDiscount) {
                        $result = $maximumAmount - $quoteSubtotal + $discountedAmount;
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->bugsnagHelper->notifyException($exception);
        }

        return $result;
    }

    /**
     * @param $result
     * @param $mwRewardPointsHelperData
     * @param $quote
     * @param $transaction
     * @return mixed|true
     */
    public function filterSkipValidateShippingForProcessNewOrder(
        $result,
        $mwRewardPointsHelperData,
        $quote,
        $transaction
    ) {
        try {
            $this->mwRewardPointsHelperData = $mwRewardPointsHelperData;
            if ($quote->getMwRewardpoint()) {
                $storeCode = $quote->getStore()->getCode();
                $currencyCode = $quote->getQuoteCurrencyCode();
                $discountedAmount = $this->getDiscountedAmount($quote, $currencyCode);
                if (
                    CurrencyUtils::toMinor($quote->getMwRewardpointDiscount(), $currencyCode) >= CurrencyUtils::toMinor($quote->getSubtotal(), $currencyCode) - $discountedAmount
                    && $this->mwRewardPointsHelperData->getRedeemedShippingConfig($storeCode)
                    && !$this->mwRewardPointsHelperData->getRedeemedTaxConfig($storeCode)
                ) {
                    return true;
                }
            }
        }catch (\Exception $exception) {
            $this->bugsnagHelper->notifyException($exception);
        }

        return $result;
    }

    /**
     * @param $quote
     * @param $currencyCode
     * @return int
     */
    public function getDiscountedAmount($quote, $currencyCode) {
        $amastyGift = 0;
        if (isset($quote->getTotals()['amasty_giftcard'])) {
            $amastyGift = CurrencyUtils::toMinor(abs((float)$quote->getTotals()['amasty_giftcard']->getValue()), $currencyCode);
        }

        $discountedAmount = CurrencyUtils::toMinor($quote->getSubtotal(), $currencyCode) -  CurrencyUtils::toMinor($quote->getSubtotalWithDiscount(), $currencyCode);
        return $discountedAmount + $amastyGift;
    }
}
