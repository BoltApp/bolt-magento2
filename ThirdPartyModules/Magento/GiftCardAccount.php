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

namespace Bolt\Boltpay\ThirdPartyModules\Magento;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Quote\Model\Quote;

/**
 * Class GiftCardAccount
 * @package Bolt\Boltpay\ThirdPartyModules\Magento
 */
class GiftCardAccount
{

    /**
     * @var Bugsnag Bugsnag helper instance
     */
    private $bugsnagHelper;

    /**
     * @var Discount
     */
    private $discountHelper;
    
    /**
     * @var Cart
     */
    private $cartHelper;
    
    /**
     * @var Magento\GiftCardAccount\Helper\Data
     */
    private $magentoGiftCardAccountHelper;
    
    /**
     * @var Magento\GiftCardAccount\Model\ResourceModel\Giftcardaccount\Collection
     */
    private $magentoGiftCardAccount;

    /**
     * @param Bugsnag                                   $bugsnagHelper Bugsnag helper instance
     * @param Discount                                  $discountHelper
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     */
    public function __construct(
        Bugsnag  $bugsnagHelper,
        Discount $discountHelper,
        Cart     $cartHelper
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->discountHelper = $discountHelper;
        $this->cartHelper = $cartHelper;
    }

    /**
     * @param array                                $result
     * @param \Magento\GiftCardAccount\Helper\Data $magentoGiftCardAccountHelper
     * @param \Magento\Quote\Model\Quote           $quote
     * @param \Magento\Quote\Model\Quote           $parentQuote
     * @param bool                                 $paymentOnly
     * @return array
     */
    public function collectDiscounts(
        $result,
        $magentoGiftCardAccountHelper,
        $quote,
        $parentQuote,
        $paymentOnly
    ) {
        $this->magentoGiftCardAccountHelper = $magentoGiftCardAccountHelper;
        list ($discounts, $totalAmount, $diff) = $result;

        try {
            $roundedDiscountAmount = 0;
            $discountAmount = 0;
                
            $giftCardCodes = $this->getMagentoGiftCardAccountGiftCardData($quote);
            $currencyCode = $quote->getQuoteCurrencyCode();

            foreach($giftCardCodes as $giftCardCode => $giftCardAmount) {
                $amount = abs($giftCardAmount);
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                $boltDiscountType = $this->discountHelper->getBoltDiscountType('by_fixed');
                $discountItem = [
                    'description'       => 'Gift Card: ' . $giftCardCode,
                    'amount'            => $roundedAmount,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_GIFTCARD,
                    'reference'         => (string)$giftCardCode,
                    'discount_type'     => $boltDiscountType, // For v1/discounts.code.apply and v2/cart.update
                    'type'              => $boltDiscountType, // For v1/merchant/order
                ];
                $this->cartHelper->logEmptyDiscountCode($giftCardCode, 'Gift Card: ' . $giftCardCode);
                $discountAmount += $amount;
                $roundedDiscountAmount += $roundedAmount;
                $discounts[] = $discountItem;
            }
            
            $diff -= CurrencyUtils::toMinorWithoutRounding($discountAmount, $currencyCode) - $roundedDiscountAmount;
            $totalAmount -= $roundedDiscountAmount;
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return [$discounts, $totalAmount, $diff];
        }
    }

    /**
     * Get the Magento_GiftCardAccount Gift Card data from quote
     *
     * @param Quote $quote
     *
     * @return array
     */
    public function getMagentoGiftCardAccountGiftCardData($quote)
    {
        try {
            $cards = $this->magentoGiftCardAccountHelper->getCards($quote);
    
            if (!$cards) {
                $cards = [];
            } else {
                $cards = array_column($cards,
                    defined( '\Magento\GiftCardAccount\Model\Giftcardaccount::AMOUNT' ) ? \Magento\GiftCardAccount\Model\Giftcardaccount::AMOUNT : 'a',
                    defined( '\Magento\GiftCardAccount\Model\Giftcardaccount::CODE' ) ? \Magento\GiftCardAccount\Model\Giftcardaccount::CODE : 'c'
                );
            }
            
            return $cards;
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
            return [];
        }
    }
    
    /**
     * @param null                                                                    $result
     * @param \Magento\GiftCardAccount\Model\ResourceModel\Giftcardaccount\Collection $magentoGiftCardAccount
     * @param string                                                                  $couponCode
     * @param Quote                                                                   $quote
     * @return GiftCardAccountInterface|null
     */
    public function loadGiftcard($result, $magentoGiftCardAccount, $couponCode, $quote)
    {
        if (!empty($result)) {
            return $result;
        }
        
        try {
            $websiteId = $quote->getStore()->getWebsiteId();
            
            $giftCardsCollection = $magentoGiftCardAccount
                ->addFieldToFilter('code', ['eq' => $couponCode])
                ->addWebsiteFilter([0, $websiteId]);
    
            /** @var \Magento\GiftCardAccount\Model\Giftcardaccount $giftCard */
            $giftCard = $giftCardsCollection->getFirstItem();
   
            return (!$giftCard->isEmpty() && $giftCard->isValid()) ? $giftCard : null;
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
            return null;
        }
    }
    
    public function getAdditionalJS($result)
    {
        $result .= '$(document).on("submit","form#giftcard-form",function(){
            boltMagentoGiftInvalidateCart();
        });         
        $(document).on("click",".totals.giftcard .action.delete",function(){
            boltMagentoGiftInvalidateCart();
        });
        function boltMagentoGiftInvalidateCart() {
            if (localStorage) {
                localStorage.setItem("bolt_cart_is_invalid", "true");
                return;
            }
        }';
        return $result;
    }
    
    /**
     * @see \Bolt\Boltpay\Model\Api\DiscountCodeValidation::applyingGiftCardCode
     *
     *
     * @param mixed                                            $result
     * @param string                                           $code
     * @param \Magento\GiftCardAccount\Model\Giftcardaccount   $giftCard
     * @param Quote                                            $immutableQuote
     * @param Quote                                            $parentQuote
     * @return array
     */
    public function applyGiftcard(
        $result,
        $code,
        $giftCard,
        $immutableQuote,
        $parentQuote
    ) {
        if (!$giftCard instanceof \Magento\GiftCardAccount\Model\Giftcardaccount) {
            return $result;
        }
        try {
            if ($immutableQuote->getGiftCardsAmountUsed() == 0) {
                try {
                    // on subsequest validation calls from Bolt checkout
                    // try removing the gift card before adding it
                    $giftCard->removeFromCart(true, $immutableQuote);
                } catch (\Exception $e) {
                    // gift card not added yet
                } finally {
                    $giftCard->addToCart(true, $immutableQuote);
                }
            }

            if ($parentQuote->getGiftCardsAmountUsed() == 0) {
                try {
                    // on subsequest validation calls from Bolt checkout
                    // try removing the gift card before adding it
                    $giftCard->removeFromCart(true, $parentQuote);
                } catch (\Exception $e) {
                    // gift card not added yet
                } finally {
                    $giftCard->addToCart(true, $parentQuote);
                }
            }

            // Send the whole GiftCard Amount.
            $giftAmount = $parentQuote->getGiftCardsAmount();
            $currencyCode = $parentQuote->getQuoteCurrencyCode();

            return [
                'status'          => 'success',
                'discount_code'   => $code,
                'discount_amount' => abs(
                    CurrencyUtils::toMinor($giftAmount, $currencyCode)
                ),
                'description'     => __('Gift Card (%1)', $code),
                'discount_type'   => 'fixed_amount',
            ];
        } catch (\Exception $e) {
            return [
                'status'        => 'failure',
                'error_message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * @param bool                                           $result
     * @param string                                         $couponCode
     * @param \Magento\GiftCardAccount\Model\Giftcardaccount $giftCard
     * @param Quote                                          $quote
     * @return bool
     */
    public function filterApplyingGiftCardCode(
        $result,
        $couponCode,
        $giftCard,
        $quote
    ) {
        if ($result || !$giftCard instanceof \Magento\GiftCardAccount\Model\Giftcardaccount) {
            return $result;
        }
        
        try {
            // on subsequest validation calls from Bolt checkout
            // try removing the gift card before adding it
            $giftCard->removeFromCart(true, $quote);
        } catch (\Exception $e) {
            // gift card not added yet
        } finally {
            $giftCard->addToCart(true, $quote);
            return true;
        }
    }
    
    /**
     * @param bool                                          $result
     * @param Magento\GiftCardAccount\Model\Giftcardaccount $giftCard
     * @param Quote                                         $quote
     *
     * @return bool
     */
    public function filterRemovingGiftCardCode(
        $result,
        $giftCard,
        $quote
    ) {
        if ($result || !($giftCard instanceof \Magento\GiftCardAccount\Model\Giftcardaccount)) {
            return $result;
        }
        
        try {
            $giftCard->removeFromCart(true, $quote);
            return true;
        } catch (\Exception $e) {
            return $e;
        }
    }
}
