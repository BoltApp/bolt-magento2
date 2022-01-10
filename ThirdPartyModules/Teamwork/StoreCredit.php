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

class StoreCredit
{
    const TEAMWORK_STORECREDIT = 'teamworkstorecredit';

    /**
     * @var Discount
     */
    protected $discountHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var \Teamwork\StoreCredit\Model\StoreCreditStorage
     */
    protected $teamworkStoreCreditStorage;

    protected $currentCustomer;

    /**
     * StoreCredit constructor.
     * @param Discount $discountHelper
     * @param Bugsnag $bugsnagHelper
     * @param CurrentCustomer $currentCustomer
     */
    public function __construct(
        Discount $discountHelper,
        Bugsnag $bugsnagHelper,
        CurrentCustomer $currentCustomer
    )
    {
        $this->discountHelper = $discountHelper;
        $this->bugsnagHelper = $bugsnagHelper;
        $this->currentCustomer = $currentCustomer;
    }

    /**
     * @param array $result
     * @param \Teamwork\StoreCredit\Model\StoreCreditStorage $teamworkStoreCreditStorage
     * @param $quote
     * @param $parentQuote
     * @param $paymentOnly
     * @return array
     * @throws \Exception
     */
    public function collectDiscounts($result,
                                     $teamworkStoreCreditStorage,
                                     $quote,
                                     $parentQuote,
                                     $paymentOnly)
    {
        list ($discounts, $totalAmount, $diff) = $result;

        $this->teamworkStoreCreditStorage = $teamworkStoreCreditStorage;
        $storeCredit = abs($quote->getTeamworkStorecredit());

        try {
            // Check whether the Teamwork Store Credit is allowed for quote
            if ($storeCredit > 0) {
                $currencyCode = $quote->getQuoteCurrencyCode();
                $amount = abs($this->teamworkStoreCreditStorage->getStoreCredits($this->currentCustomer->getCustomer()));
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);

                $discounts[] = [
                    'description' => 'Store Credit',
                    'reference' => self::TEAMWORK_STORECREDIT,
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
