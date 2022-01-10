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

namespace Bolt\Boltpay\ThirdPartyModules\ImaginationMedia;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;

class TmwGiftCard
{
    const IMAGINATIONMEDIA_GIFTCARD = 'imaginationmediagifcard';

    /**
     * @var Discount
     */
    protected $discountHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var  \ImaginationMedia\TmwGiftCard\Helper\Data
     */
    private $tmwGiftCardHelper;

    /**
     * GiftCardDiscount constructor.
     * @param Discount $discountHelper
     * @param Bugsnag $bugsnagHelper
     */
    public function __construct(
        Discount $discountHelper,
        Bugsnag $bugsnagHelper
    )
    {
        $this->discountHelper = $discountHelper;
        $this->bugsnagHelper = $bugsnagHelper;
    }

    /**
     * @param $result
     * @param \ImaginationMedia\TmwGiftCard\Helper\Data $tmwGiftCardHelper
     * @param $quote
     * @param $parentQuote
     * @param $paymentOnly
     * @return array
     */
    public function collectDiscounts(
        $result,
        $tmwGiftCardHelper,
        $quote,
        $parentQuote,
        $paymentOnly
    )
    {
        $this->tmwGiftCardHelper = $tmwGiftCardHelper;
        list ($discounts, $totalAmount, $diff) = $result;
        $giftCard = abs($tmwGiftCardHelper->getTotalCardsAmount($parentQuote));

        try {
            if ($giftCard > 0) {
                $currencyCode = $quote->getQuoteCurrencyCode();
                $roundedAmount = CurrencyUtils::toMinor($giftCard, $currencyCode);

                $discounts[] = [
                    'description' => 'Giftcard',
                    'reference' => self::IMAGINATIONMEDIA_GIFTCARD,
                    'amount' => $roundedAmount,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_GIFTCARD,
                    'discount_type' => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/discounts.code.apply and v2/cart.update
                    'type' => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/merchant/order
                ];

                $diff -= CurrencyUtils::toMinorWithoutRounding($giftCard, $currencyCode) - $roundedAmount;
                $totalAmount -= $roundedAmount;
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return [$discounts, $totalAmount, $diff];
        }
    }
}
