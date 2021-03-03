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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\Mageplaza;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Helper\Session;
use Magento\Framework\Exception\LocalizedException;

class GiftCard
{
    const MAGEPLAZA_GIFTCARD = 'gift_card';
    const MAGEPLAZA_GIFTCARD_TITLE = 'Gift Card ';
    const MAGEPLAZA_GIFTCARD_QUOTE_KEY = 'mp_gift_cards';

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var Discount
     */
    protected $discountHelper;

    /**
     * @var Session
     */
    private $sessionHelper;

    protected $mageplazaGiftCardCollection;

    protected $mageplazaGiftCardCheckoutHelper;

    protected $mageplazaGiftCardFactory;

    public function __construct(
        Discount $discountHelper,
        Bugsnag $bugsnagHelper,
        Session $sessionHelper
    )
    {
        $this->discountHelper = $discountHelper;
        $this->bugsnagHelper = $bugsnagHelper;
        $this->sessionHelper = $sessionHelper;
    }

    /**
     * @param $result
     * @param $quote
     * @param $mageplazaGiftCardCollection
     * @param $parentQuote
     * @param $paymentOnly
     * @return array
     */
    public function collectDiscounts(
        $result,
        $mageplazaGiftCardCollection,
        $quote,
        $parentQuote,
        $paymentOnly
    )
    {
        $this->mageplazaGiftCardCollection = $mageplazaGiftCardCollection;
        list ($discounts, $totalAmount, $diff) = $result;
        $totals = $quote->getTotals();

        $totalDiscount = $totals[self::MAGEPLAZA_GIFTCARD] ?? null;

        try {
            if ($totalDiscount && $amount = $totalDiscount->getValue()) {
                ///////////////////////////////////////////////////////////////////////////
                // Change giftcards balance as discount amount to giftcard balances to the discount amount
                ///////////////////////////////////////////////////////////////////////////
                $roundedDiscountAmount = 0;
                $discountAmount = 0;
                $giftCardCodes = $this->getMageplazaGiftCardCodes($quote);
                $currencyCode = $quote->getQuoteCurrencyCode();
                foreach ($giftCardCodes as $giftCardCode) {
                    $amount = abs($this->getMageplazaGiftCardCodesCurrentValue(array($giftCardCode)));
                    $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                    $discountItem = [
                        'description' => self::MAGEPLAZA_GIFTCARD_TITLE . $giftCardCode,
                        'amount' => $roundedAmount,
                        'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_GIFTCARD,
                        'reference' => $giftCardCode,
                        'discount_type' => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/discounts.code.apply and v2/cart.update
                        'type' => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/merchant/order
                    ];
                    $discountAmount += $amount;
                    $roundedDiscountAmount += $roundedAmount;
                    $discounts[] = $discountItem;
                }
                $diff -= CurrencyUtils::toMinorWithoutRounding($discountAmount, $currencyCode) - $roundedDiscountAmount;
                $totalAmount -= $roundedDiscountAmount;
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return [$discounts, $totalAmount, $diff];
        }

    }

    /**
     * Get Mageplaza GiftCard Codes
     *
     * @param $quote
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getMageplazaGiftCardCodes($quote)
    {
        $giftCardsData = $this->sessionHelper->getCheckoutSession()->getGiftCardsData();
        $giftCardCodes = isset($giftCardsData[self::MAGEPLAZA_GIFTCARD_QUOTE_KEY]) ? array_keys($giftCardsData[self::MAGEPLAZA_GIFTCARD_QUOTE_KEY]) : [];

        $giftCardsQuote = $quote->getMpGiftCards();
        if (!$giftCardCodes && $giftCardsQuote) {
            $giftCardCodes = array_keys(json_decode($giftCardsQuote, true));
        }

        return $giftCardCodes;
    }

    /**
     * @param $giftCardCodes
     * @return float|int
     */
    private function getMageplazaGiftCardCodesCurrentValue($giftCardCodes)
    {
        $data = $this->mageplazaGiftCardCollection->create()
            ->addFieldToFilter('code', ['in' => $giftCardCodes])
            ->getData();

        return array_sum(array_column($data, 'balance'));
    }

    /**
     * @param $result
     * @param $mageplazaGiftCardCheckoutHelper
     * @param $code
     * @param $giftCard
     * @param $immutableQuote
     * @param $parentQuote
     * @return null
     * @throws \Exception
     */
    public function applyGiftcard($result, $mageplazaGiftCardCheckoutHelper, $code, $giftCard, $immutableQuote, $parentQuote)
    {
        if (!empty($result)) {
            return $result;
        }

        if (!($giftCard instanceof \Mageplaza\GiftCard\Model\GiftCard)) {
            return null;
        }

        try {
            $this->mageplazaGiftCardCheckoutHelper = $mageplazaGiftCardCheckoutHelper;
            $this->removeMageplazaGiftCard($giftCard->getId(), $immutableQuote);
            $this->removeMageplazaGiftCard($giftCard->getId(), $parentQuote);
            // Apply Mageplaza Gift Card to the parent quote
            $this->applyMageplazaGiftCard($code, $immutableQuote);
            $this->applyMageplazaGiftCard($code, $parentQuote);

            $giftAmount = $giftCard->getBalance();
            $result = [
                'status' => 'success',
                'discount_code' => $code,
                'discount_amount' => abs(CurrencyUtils::toMinor($giftAmount, $immutableQuote->getQuoteCurrencyCode())),
                'description' => __('Gift Card'),
                'discount_type' => $this->discountHelper->getBoltDiscountType('by_fixed'),
            ];
            return $result;
        } catch (\Exception $e) {
            $result = [
                'status' => 'failure',
                'error_message' => $e->getMessage(),
            ];
            return $result;
        }
    }

    /**
     * @param $code
     * @param $quote
     */
    private function removeMageplazaGiftCard($code, $quote)
    {
        try {
            $this->mageplazaGiftCardCheckoutHelper->removeGiftCard($code, false, $quote);
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }

    /**
     * @param $code
     * @param $quote
     * @return int
     */
    private function applyMageplazaGiftCard($code, $quote)
    {
        try {
            $this->mageplazaGiftCardCheckoutHelper->addGiftCards($code, $quote);
            $totals = $quote->getTotals();
            return isset($totals[self::MAGEPLAZA_GIFTCARD]) ? $totals[self::MAGEPLAZA_GIFTCARD]->getValue() : 0;
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }

    /**
     * @param $result
     * @param $mageplazaGiftCardCheckoutHelper
     * @param $quote
     * @param $couponCode
     * @param $giftCard
     * @param $quote
     * @return bool
     */
    public function filterApplyingGiftCardCode($result, $mageplazaGiftCardCheckoutHelper, $couponCode, $giftCard, $quote)
    {
        $this->mageplazaGiftCardCheckoutHelper = $mageplazaGiftCardCheckoutHelper;
        if ($giftCard instanceof \Mageplaza\GiftCard\Model\GiftCard) {
            $this->removeMageplazaGiftCard($couponCode, $quote);
            $this->applyMageplazaGiftCard($couponCode, $quote);

            $result = true;
        }

        return $result;
    }

    /**
     * Load Magplaza Gift Card account object
     * @param $code
     * @param $storeId
     * @return |null
     */
    private function loadMageplazaGiftCard($code, $storeId)
    {
        try {
            $accountModel = $this->mageplazaGiftCardFactory->create()->load($code, 'code');

            return $accountModel && $accountModel->getId()
            && (!$accountModel->getStoreId() || $accountModel->getStoreId() == $storeId) && $accountModel->isActive()
                ? $accountModel : null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param $result
     * @param $mageplazaGiftCardFactory
     * @param $code
     * @param $quote
     * @return null
     */
    public function loadGiftcard($result, $mageplazaGiftCardFactory, $code, $quote)
    {
        $this->mageplazaGiftCardFactory = $mageplazaGiftCardFactory;

        if (!empty($result)) {
            return $result;
        }
        try {
            $storeId = $quote->getStoreId();
            return $this->loadMageplazaGiftCard($code, $storeId);
        } catch (LocalizedException $e) {
            return null;
        }
        return null;
    }

    /**
     * @param $result
     * @param $mageplazaGiftCardCheckoutHelper
     * @param $giftCard
     * @param $quote
     * @return bool
     */
    public function filterRemovingGiftCardCode($result, $mageplazaGiftCardCheckoutHelper, $giftCard, $quote)
    {
        $this->mageplazaGiftCardCheckoutHelper = $mageplazaGiftCardCheckoutHelper;
        if ($giftCard instanceof \Mageplaza\GiftCard\Model\GiftCard) {
            $this->removeMageplazaGiftCard($giftCard->getCode(), $quote);
            $result = true;
        }

        return $result;
    }
}
