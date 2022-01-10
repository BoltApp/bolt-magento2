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

namespace Bolt\Boltpay\ThirdPartyModules\Teamwork;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Customer\Helper\Session\CurrentCustomer;

class Token
{
    const TEAMWORK_TOKEN = 'teamworkstoken';

    /**
     * @var Discount
     */
    protected $discountHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * Token constructor.
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
     * @param $quote
     * @param $parentQuote
     * @param $paymentOnly
     * @return array
     * @throws \Exception
     */
    public function collectDiscounts($result,
                                     $quote,
                                     $parentQuote,
                                     $paymentOnly)
    {
        list ($discounts, $totalAmount, $diff) = $result;

        $amount = abs($quote->getTeamworkToken());
        try {
            if ($amount > 0) {
                $currencyCode = $quote->getQuoteCurrencyCode();
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                $discounts[] = [
                    'description' => 'Reward Points',
                    'reference' => self::TEAMWORK_TOKEN,
                    'amount' => $roundedAmount,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    'discount_type' => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/discounts.code.apply and v2/cart.update
                    'type' => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/merchant/order
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
}
