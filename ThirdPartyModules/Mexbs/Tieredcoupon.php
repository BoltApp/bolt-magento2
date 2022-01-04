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

namespace Bolt\Boltpay\ThirdPartyModules\Mexbs;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Magento\SalesRule\Model\CouponFactory;
use Magento\SalesRule\Model\RuleRepository;
use Magento\Quote\Api\CartRepositoryInterface as QuoteRepository;
use Magento\Framework\Exception\NoSuchEntityException;

class Tieredcoupon
{
    /**
     * @var Bugsnag Bugsnag helper instance
     */
    private $bugsnagHelper;
    
    /**
     * @var QuoteRepository
     */
    private $quoteRepository;
    
    /**
     * @var SessionHelper
     */
    private $sessionHelper;
    
    /**
     * @var Discount
     */
    private $discountHelper;
    
    /**
     * @var RuleRepository
     */
    private $ruleRepository;
    
    /**
     * @var CouponFactory
     */
    private $couponFactory;
    
    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @param Bugsnag         $bugsnagHelper
     * @param QuoteRepository $quoteRepository
     * @param SessionHelper   $sessionHelper
     * @param RuleRepository  $ruleRepository
     * @param Discount        $discountHelper
     * @param CouponFactory   $couponFactory
     * @param CartHelper      $cartHelper
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        QuoteRepository $quoteRepository,
        SessionHelper $sessionHelper,
        RuleRepository $ruleRepository,
        Discount $discountHelper,
        CouponFactory $couponFactory,
        CartHelper $cartHelper
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->quoteRepository = $quoteRepository;
        $this->sessionHelper = $sessionHelper;
        $this->ruleRepository = $ruleRepository;
        $this->discountHelper = $discountHelper;
        $this->couponFactory = $couponFactory;
        $this->cartHelper = $cartHelper;
    }
    
    /**
     * Get tiered coupon if exists
     *
     * @param mixed|null                        $result
     * @param \Mexbs\Tieredcoupon\Helper\Data   $mexbsTieredcouponHelperData
     * @param string                            $couponCode
     * @return mixed|null     
     */
    public function loadCouponCodeData($result, $mexbsTieredcouponHelperData, $couponCode)
    {
        try {
            if (!$result) {
                $tieredCoupon = $mexbsTieredcouponHelperData->getTieredCouponByCouponCode($couponCode);
                if($tieredCoupon && $tieredCoupon->getId() && $tieredCoupon->getIsActive()){
                    return $tieredCoupon;
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return $result;
    }
    
    /**
     * Check if the coupon is a valid tiered coupon
     *
     * @param bool                                            $result
     * @param \Mexbs\Tieredcoupon\Model\TieredcouponFactory   $mexbsTieredcouponCouponFactory
     * @param mixed                                           $coupon
     * @param string                                          $couponCode
     * @return bool
     */
    public function isValidCouponObj($result, $mexbsTieredcouponCouponFactory, $coupon, $couponCode)
    {
        try {
            if (!$result) {
                $tieredCoupon = $mexbsTieredcouponCouponFactory->create()->load($couponCode, 'code');
                if ($tieredCoupon && $tieredCoupon->getId() && $tieredCoupon->getId() === $coupon->getId()) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return $result;
    }
    
    /**
     * Return the sale rule of applied subcoupon
     *
     * @param mixed|null                                      $result
     * @param \Mexbs\Tieredcoupon\Model\TieredcouponFactory   $mexbsTieredcouponCouponFactory
     * @param mixed                                           $coupon
     * @return mixed|null
     */
    public function getCouponRelatedRule($result, $mexbsTieredcouponCouponFactory, $coupon)
    {
        try {
            if (!$result && $coupon) {
                $tieredCoupon = $mexbsTieredcouponCouponFactory->create()->load($coupon->getCode(), 'code');
                if ($tieredCoupon && $tieredCoupon->getId() && $tieredCoupon->getId() === $coupon->getId()) {
                    $subCouponCodes = $tieredCoupon->getSubCouponCodes();
                    $quote = $this->sessionHelper->getCheckoutSession()->getQuote();
                    $saleRuleDiscounts = $this->cartHelper->getSaleRuleDiscounts($quote);
                    foreach ($subCouponCodes as $subCouponCode) {
                        try {
                            $coupon = $this->couponFactory->create()->loadByCode($subCouponCode);
                            $rule = $this->ruleRepository->getById($coupon->getRuleId());
                            if (isset($saleRuleDiscounts[$rule->getRuleId()])) {
                                return $rule;
                            }
                        } catch (NoSuchEntityException $e) {
                            // the subcoupon or its rule does not exist,
                            // but we can ignore such an exception and continue to search subcoupon for a match.
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return $result;
    }
    
    /**
     * Apply tiered coupon to quote
     *
     * @param bool                                            $result
     * @param \Mexbs\Tieredcoupon\Model\TieredcouponFactory   $mexbsTieredcouponCouponFactory
     * @param string                                          $couponCode
     * @param mixed                                           $coupon
     * @param \Magento\Quote\Model\Quote                      $quote
     * @param \Magento\Quote\Model\Quote                      $addQuote
     * @return bool
     */
    public function filterApplyingCouponCode(
        $result,
        $mexbsTieredcouponCouponFactory,
        $couponCode,
        $coupon,
        $quote,
        $addQuote
    ) {
        if (!$result) {
            $tieredCoupon = $mexbsTieredcouponCouponFactory->create()->load($couponCode, 'code');
            if ($tieredCoupon->getId() === $coupon->getId()) {
                if (!is_null($addQuote)) {
                    $addQuote->getShippingAddress()->setCollectShippingRates(true);
                    $addQuote->setCouponCode($couponCode)->collectTotals();
                    $this->quoteRepository->save($addQuote);
                }
                $quote->getShippingAddress()->setCollectShippingRates(true);
                $quote->setCouponCode($couponCode)->collectTotals();
                $this->quoteRepository->save($quote);
                if ($couponCode !== $quote->getCouponCode()) {
                    throw new BoltException(
                        __('Coupon code %1 does not equal with the quote code %2.', $couponCode, $quote->getCouponCode()),
                        null,
                        BoltErrorResponse::ERR_SERVICE,
                        $quote
                    );
                }
                $subCouponCodes = $tieredCoupon->getSubCouponCodes();
                $quote = $this->sessionHelper->getCheckoutSession()->getQuote();
                $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
                $saleRuleDiscounts = $this->cartHelper->getSaleRuleDiscounts($address);
                $appliedSubCoupon = null;
                foreach ($subCouponCodes as $subCouponCode) {
                    try {
                        $coupon = $this->couponFactory->create()->loadByCode($subCouponCode);
                        if (isset($saleRuleDiscounts[$coupon->getRuleId()])) {
                            $appliedSubCoupon = $coupon;
                        }
                    } catch (NoSuchEntityException $e) {
                        // the subcoupon does not exist,
                        // but we can ignore such an exception and continue to search subcoupon for a match.
                    }
                }
                if (!$appliedSubCoupon) {
                    throw new BoltException(
                        __('Failed to apply the coupon code %1', $couponCode),
                        null,
                        BoltErrorResponse::ERR_SERVICE,
                        $quote
                    );
                }
                $description = $tieredCoupon->getDescription();
                $display = $description != '' ? $description : 'Discount (' . $couponCode . ')';
                $result = [
                    'status'          => 'success',
                    'discount_code'   => $couponCode,
                    'discount_amount' => abs(CurrencyUtils::toMinor($boltCollectSaleRuleDiscounts[$appliedSubCoupon->getRuleId()], $quote->getQuoteCurrencyCode())),
                    'description'     => $display,
                    'discount_type'   => $this->discountHelper->convertToBoltDiscountType($appliedSubCoupon->getCode()),
                ];
            }
        }

        return $result;
    }
}
