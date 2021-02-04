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

use Aheadworks\RewardPoints\Api\CustomerRewardPointsManagementInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Service\OrderService;

class RewardPoints
{
    const AHEADWORKS_REWARD_POINTS = 'aw_reward_points';

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var Discount
     */
    protected $discountHelper;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * StoreCredit constructor.
     *
     * @param Discount                $discountHelper
     * @param Bugsnag                 $bugsnagHelper
     * @param Config                  $configHelper
     * @param CustomerSession         $customerSession
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderService            $orderService
     */
    public function __construct(
        Discount $discountHelper,
        Bugsnag $bugsnagHelper,
        Config $configHelper,
        CustomerSession $customerSession,
        CartRepositoryInterface $quoteRepository,
        OrderService $orderService
    ) {
        $this->discountHelper = $discountHelper;
        $this->bugsnagHelper = $bugsnagHelper;
        $this->quoteRepository = $quoteRepository;
        $this->orderService = $orderService;
        $this->configHelper = $configHelper;
        $this->customerSession = $customerSession;
    }

    /**
     * @param array                                   $result
     * @param CustomerRewardPointsManagementInterface $aheadworksCustomerRewardPointsManagement
     * @param \Aheadworks\RewardPoints\Model\Config   $aheadworksConfig
     * @param Quote                                   $quote
     * @param Quote                                   $parentQuote
     * @param bool                                    $paymentOnly
     *
     * @return array
     */
    public function collectDiscounts(
        $result,
        $aheadworksCustomerRewardPointsManagement,
        $aheadworksConfig,
        $quote,
        $parentQuote,
        $paymentOnly
    ) {

        list ($discounts, $totalAmount, $diff) = $result;
        try {
            if ($quote->getData('aw_use_reward_points')) {
                $currencyCode = $quote->getQuoteCurrencyCode();
                $amount = $aheadworksConfig->isApplyingPointsToShipping($quote->getStore()->getWebsiteId())
                    ? abs(
                        $aheadworksCustomerRewardPointsManagement->getCustomerRewardPointsBalanceBaseCurrency(
                            $quote->getCustomerId()
                        )
                    )
                    : abs(
                        $quote->getData('base_aw_reward_points_amount')
                    );
                $discountType = $this->discountHelper->getBoltDiscountType('by_fixed');
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                $discounts[] = [
                    'description'       => __('Reward Points'),
                    'amount'            => $roundedAmount,
                    'reference'         => self::AHEADWORKS_REWARD_POINTS,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    'discount_type'     => $discountType, // For v1/discounts.code.apply and v2/cart.update
                    'type'              => $discountType, // For v1/merchant/order
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
     * Return code if the quote has Aheadworks reward points.
     *
     * @param array  $result
     * @param string $couponCode
     * @param Quote  $quote
     *
     * @return array
     */
    public function filterVerifyAppliedStoreCredit(
        $result,
        $couponCode,
        $quote
    ) {
        if ($couponCode == self::AHEADWORKS_REWARD_POINTS && $quote->getData('aw_use_reward_points')) {
            $result[] = $couponCode;
        }

        return $result;
    }

    /**
     * Remove Aheadworks reward points from the quote.
     *
     * @param string $couponCode
     * @param Quote  $quote
     * @param int    $websiteId
     * @param int    $storeId
     *
     * @throws \Exception
     */
    public function removeAppliedStoreCredit(
        $couponCode,
        $quote,
        $websiteId,
        $storeId
    ) {
        try {
            if ($couponCode == self::AHEADWORKS_REWARD_POINTS && $quote->getData('aw_use_reward_points')) {
                $quote->setData('aw_use_reward_points', false);
                $this->quoteRepository->save($quote->collectTotals());
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }

    /**
     * Fetch transaction details info
     *
     * Used to restore Aheadworks RewardPoints balance for failed payment orders by manually executing the appropriate
     * plugin {@see \Aheadworks\RewardPoints\Plugin\Model\Service\OrderServicePlugin::aroundCancel}
     * because it is plugged into {@see \Magento\Sales\Api\OrderManagementInterface::cancel} instead of
     * {@see \Magento\Sales\Model\Order::cancel} which we call in {@see \Bolt\Boltpay\Helper\Order::deleteOrder}
     *
     * @param \Aheadworks\RewardPoints\Plugin\Model\Service\OrderServicePlugin $aheadworksRewardPointsOrderServicePlugin
     * @param \Magento\Sales\Model\Order                                       $order to be deleted
     */
    public function beforeFailedPaymentOrderSave($aheadworksRewardPointsOrderServicePlugin, $order)
    {
        $aheadworksRewardPointsOrderServicePlugin->aroundCancel(
            $this->orderService,
            function ($orderId) {
                return true;
            },
            $order->getId()
        );
    }

    /**
     * Add Aheadworks Reward Points to layout to be rendered below the cart
     *
     * @param array                                   $jsLayout
     * @param CustomerRewardPointsManagementInterface $customerRewardPointsManagement
     *
     * @return array
     */
    public function collectCartDiscountJsLayout(
        $jsLayout,
        $customerRewardPointsManagement
    ) {
        $customerId = $this->customerSession->getCustomerId();
        if ($this->customerSession->isLoggedIn()
            && $this->configHelper->getUseAheadworksRewardPointsConfig()
            && $customerRewardPointsManagement->getCustomerRewardPointsOnceMinBalance($customerId) == 0
            && $customerRewardPointsManagement->isCustomerRewardPointsSpendRateByGroup($customerId)
            && $customerRewardPointsManagement->isCustomerRewardPointsSpendRate($customerId)) {
            $jsLayout["aw-reward-points"] = [
                "sortOrder" => 0,
                "component" => "Aheadworks_RewardPoints/js/view/payment/reward-points",
                "config"    => [
                    'template' => 'Bolt_Boltpay/third-party-modules/aheadworks/reward-points/cart/reward-points'
                ],
                "children"  => [
                    "errors" => [
                        "sortOrder"   => 0,
                        "component"   => "Aheadworks_RewardPoints/js/view/payment/reward-points-messages",
                        "displayArea" => "messages",
                    ]
                ]
            ];
        }
        return $jsLayout;
    }
}
