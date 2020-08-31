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

namespace Bolt\Boltpay\ThirdPartyModules\Aheadworks;

use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Framework\Exception\LocalizedException;

class Giftcard
{
    public function collectDiscounts($result, $aheadworksGiftcardManagement, $quote)
    {
        list ($discounts, $totalAmount, $diff) = $result;

        try {
            $parentQuoteId = $quote->getData('bolt_parent_quote_id');
            $currencyCode = $quote->getQuoteCurrencyCode();
            foreach ($aheadworksGiftcardManagement->get($parentQuoteId, false) as $giftcardQuote) {
                $discounts[] = [
                    'description' => "Gift Card ({$giftcardQuote->getGiftcardCode()})",
                    'amount'      => CurrencyUtils::toMinor($giftcardQuote->getGiftcardBalance(), $currencyCode),
                    'type'        => 'fixed_amount'
                ];
                $totalAmount -= CurrencyUtils::toMinor($giftcardQuote->getGiftcardAmount(), $currencyCode);
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {        
            return [$discounts, $totalAmount, $diff];
        }
    }

    public function loadGiftcard($result, $aheadworksGiftcardRepository, $code, $storeId)
    {
        if (!empty($result)) {
            return $result;
        }
        try {
            return $aheadworksGiftcardRepository->getByCode($code, $storeId);
        } catch (LocalizedException $e) {
            return null;
        }
        return null;
    }

    public function applyGiftcard($result, $aheadworksGiftcardManagement, $code, $giftCard, $immutableQuote, $parentQuote)
    {
        if (!empty($result)) {
            return $result;
        }
        if (!($giftCard instanceof \Aheadworks\Giftcard\Api\Data\GiftcardInterface)) {
            return null;
        }
        try {
            // on subsequent validation calls from Bolt checkout
            // try removing the gift card before adding it
            $aheadworksGiftcardManagement->remove($parentQuote->getId(), $giftCard->getCode(), false);
        } catch (\Exception $e) {
            // gift card not yet added
        }
        try {
            $aheadworksGiftcardManagement->set($parentQuote->getId(), $giftCard->getCode(), false);

            $result = [
                'status'          => 'success',
                'discount_code'   => $code,
                'discount_amount' => abs(
                    CurrencyUtils::toMinor($giftCard->getBalance(), $parentQuote->getQuoteCurrencyCode())
                ),
                'description'     => __('Gift Card (%1)', $giftCard->getCode()),
                'discount_type'   => 'fixed_amount',
            ];
            return $result;
        } catch (\Exception $e) {
            $result = [
                'status' =>'failure',
                'error_message' => $e->getMessage(),
            ];
            return $result;
        }
    }
}
