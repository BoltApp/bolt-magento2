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

namespace Bolt\Boltpay\ThirdPartyModules\Aheadworks;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Api\CartRepositoryInterface;

class StoreCredit
{
    const AHEADWORKS_STORE_CREDIT = 'aw_store_credit';

    /**
     * @var $aheadworksCustomerStoreCreditManagement
     */
    private $aheadworksCustomerStoreCreditManagement;

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;
    
    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;
    
    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * StoreCredit constructor.
     * @param Bugsnag                 $bugsnagHelper
     * @param Config                  $configHelper
     * @param CartRepositoryInterface $quoteRepository
     * @param CustomerSession         $customerSession
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        Config $configHelper,
        CartRepositoryInterface $quoteRepository,
        CustomerSession $customerSession
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->configHelper = $configHelper;
        $this->quoteRepository = $quoteRepository;
        $this->customerSession = $customerSession;
    }

    /**
     * @param $result
     * @param $aheadworksCustomerStoreCreditManagement
     * @param $quote
     * @param $parentQuote
     * @param $paymentOnly
     * @return array
     */
    public function collectDiscounts(
        $result,
        $aheadworksCustomerStoreCreditManagement,
        $quote,
        $parentQuote,
        $paymentOnly
    ) {
        $this->aheadworksCustomerStoreCreditManagement = $aheadworksCustomerStoreCreditManagement;

        list ($discounts, $totalAmount, $diff) = $result;
        $totals = $quote->getTotals();
        try {
            if (array_key_exists(Discount::AHEADWORKS_STORE_CREDIT, $totals)) {
                $amount = abs((float)$this->aheadworksCustomerStoreCreditManagement
                    ->getCustomerStoreCreditBalance($quote->getCustomerId()));
                $currencyCode = $quote->getQuoteCurrencyCode();
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                $discounts[] = [
                    'description' => 'Store Credit',
                    'amount' => $roundedAmount,
                    'reference' => self::AHEADWORKS_STORE_CREDIT,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    'discount_type' => Discount::BOLT_DISCOUNT_TYPE_FIXED, // For v1/discounts.code.apply and v2/cart.update
                    'type' => Discount::BOLT_DISCOUNT_TYPE_FIXED, // For v1/merchant/order
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
     * Return code if the quote has Aheadworks store credits.
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
        if ($couponCode == self::AHEADWORKS_STORE_CREDIT && $quote->getAwUseStoreCredit()) {
            $result[] = $couponCode;
        }
        
        return $result;
    }
    
    /**
     * Remove Aheadworks store credits from the quote.
     *
     * @param $amastyApplyStoreCreditToQuote
     * @param $couponCode
     * @param $quote
     * @param $websiteId
     * @param $storeId
     *
     */
    public function removeAppliedStoreCredit(
        $couponCode,
        $quote,
        $websiteId,
        $storeId
    ) {
        try {
            if ($couponCode == self::AHEADWORKS_STORE_CREDIT && $quote->getAwUseStoreCredit()) {
                $quote->setAwUseStoreCredit(false);
                $this->quoteRepository->save($quote->collectTotals());
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Add Aheadworks Store Credit to layout to be rendered below the cart.
     * We may add extra css for styling the related section per the merchant's theme when needed.
     *
     * @param array                                  $jsLayout
     * @param CustomerStoreCreditManagementInterface $customerStoreCreditManagement
     *
     * @return array
     */
    public function collectCartDiscountJsLayout(
        $jsLayout,
        $customerStoreCreditManagement
    ) {
        if ($this->customerSession->isLoggedIn()
            && $this->configHelper->getUseAheadworksStoreCreditConfig()
            && $customerStoreCreditManagement->getCustomerStoreCreditBalance($this->customerSession->getCustomerId()) > 0) {
            $jsLayout["aw-store-credit"] = [
                "sortOrder" => 0,
                "component" => "Aheadworks_StoreCredit/js/view/payment/store-credit",
                "config"    => [
                    'template' => 'Aheadworks_StoreCredit/payment/store-credit'
                ],
                "children"  => [
                    "errors" => [
                        "sortOrder"   => 0,
                        "component"   => "Aheadworks_StoreCredit/js/view/payment/store-credit-messages",
                        "displayArea" => "messages",
                    ]
                ]
            ];
        }
        return $jsLayout;
    }
}
