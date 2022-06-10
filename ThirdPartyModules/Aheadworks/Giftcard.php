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
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Service\OrderService;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;

class Giftcard
{
    /**
     * @var OrderService Magento order management model
     */
    private $orderService;

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var Bolt\Boltpay\Helper\FeatureSwitch\Decider
     */
    private $featureSwitches;

    /**
     * @param OrderService $orderService Magento order service instance
     * @param Bugsnag $bugsnagHelper Bugsnag helper instance
     * @param Decider  $featureSwitches
     */
    public function __construct(
        OrderService  $orderService,
        Bugsnag       $bugsnagHelper,
        Decider       $featureSwitches
    ) {
        $this->orderService   = $orderService;
        $this->bugsnagHelper  = $bugsnagHelper;
        $this->featureSwitches = $featureSwitches;
    }

    /**
     * @param $result
     * @param $aheadworksGiftcardManagement
     * @param $quote
     * @return array
     */
    public function collectDiscounts($result, $aheadworksGiftcardManagement, $quote, $parentQuote, $paymentOnly)
    {
        list ($discounts, $totalAmount, $diff) = $result;

        try {
            $quoteExtensionAttributes = $quote->getExtensionAttributes();
            $parentQuoteId = $quote->getData('bolt_parent_quote_id');
            $currencyCode = $quote->getQuoteCurrencyCode();
            if ($parentQuoteId) {
                $giftCardQuotations = $aheadworksGiftcardManagement->get($parentQuoteId, false);
            } elseif($quoteExtensionAttributes &&
                method_exists($quoteExtensionAttributes, 'getAwGiftcardCodes') &&
                !empty($quoteExtensionAttributes->getAwGiftcardCodes())
            ) {
                $giftCardQuotations = $quoteExtensionAttributes->getAwGiftcardCodes();
            } else {
                throw new LocalizedException(__('No AW GiftCards Quotations found, quote_id %', $quote->getId()));
            }

            foreach ($giftCardQuotations as $giftcardQuote) {
                $discounts[] = [
                    'reference'         => $giftcardQuote->getGiftcardCode(),
                    'description'       => "Gift Card ({$giftcardQuote->getGiftcardCode()})",
                    'amount'            => CurrencyUtils::toMinor($giftcardQuote->getGiftcardBalance(), $currencyCode),
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_GIFTCARD,
                    // For v1/discounts.code.apply and v2/cart.update
                    'discount_type'     => Discount::BOLT_DISCOUNT_TYPE_FIXED,
                    // For v1/merchant/order
                    'type'              => Discount::BOLT_DISCOUNT_TYPE_FIXED,
                ];
                $totalAmount -= CurrencyUtils::toMinor($giftcardQuote->getGiftcardAmount(), $currencyCode);
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return [$discounts, $totalAmount, $diff];
        }
    }

    /**
     * @param $result
     * @param $aheadworksGiftcardRepository
     * @param $code
     * @param $quote
     * @return null
     */
    public function loadGiftcard($result, $aheadworksGiftcardRepository, $code, $quote)
    {
        if ($result !== null) {
            return $result;
        }

        try {
            $websiteId = $quote->getStore()->getWebsiteId();
            return $aheadworksGiftcardRepository->getByCode($code, $websiteId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return null;
        } catch (\Exception $e) {
            if ($this->featureSwitches->isReturnErrWhenRunFilter()) {
                return $e;
            } else {
                $this->bugsnagHelper->notifyException($e);
                return null;
            }
        }
    }

    /**
     * @param $result
     * @param $aheadworksGiftcardManagement
     * @param $code
     * @param $giftCard
     * @param $immutableQuote
     * @param $parentQuote
     * @return array|null
     */
    public function applyGiftcard(
        $result,
        $aheadworksGiftcardManagement,
        $code,
        $giftCard,
        $immutableQuote,
        $parentQuote
    ) {
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

    /**
     * @param $result
     * @param $aheadworksGiftcardCartService
     * @param $quote
     * @param $couponCode
     * @param $giftCard
     * @param $quote
     * @return bool
     */
    public function filterApplyingGiftCardCode($result, $aheadworksGiftcardCartService, $couponCode, $giftCard, $quote)
    {
        if ($giftCard instanceof \Aheadworks\Giftcard\Model\Giftcard) {
            try {
                // on subsequent validation calls from Bolt checkout
                // try removing the gift card before adding it
                $aheadworksGiftcardCartService->remove($quote->getId(), $couponCode, false);
            } catch (\Exception $e) {

            }

            $aheadworksGiftcardCartService->set($quote->getId(), $couponCode, false);

            $result = true;
        }

        return $result;
    }

    /**
     * @param $result
     * @param $aheadworksGiftcardCartService
     * @param $giftCard
     * @param $quote
     * @return bool
     */
    public function filterRemovingGiftCardCode($result, $aheadworksGiftcardCartService, $giftCard, $quote)
    {
        if ($giftCard instanceof \Aheadworks\Giftcard\Model\Giftcard) {
            $aheadworksGiftcardCartService->remove($quote->getId(), $giftCard->getCode(), false);

            $result = true;
        }

        return $result;
    }

    /**
     * Fetch transaction details info
     *
     * Plugin for {@see \Bolt\Boltpay\Helper\Order::deleteOrder}
     * Used to restore Aheadworks Giftcard balance for failed payment orders by manually executing the appropriate
     * plugin {@see \Aheadworks\Giftcard\Plugin\Model\Service\OrderServicePlugin::aroundCancel}
     * because it is plugged into {@see \Magento\Sales\Api\OrderManagementInterface::cancel} instead of
     * {@see \Magento\Sales\Model\Order::cancel} which we call in {@see \Bolt\Boltpay\Helper\Order::deleteOrder}
     *
     * @param \Aheadworks\Giftcard\Plugin\Model\Service\OrderServicePlugin $aheadworksGiftcardOrderServicePlugin
     * @param \Magento\Sales\Model\Order $order to be deleted
     */
    public function beforeFailedPaymentOrderSave($aheadworksGiftcardOrderServicePlugin, $order)
    {
        $aheadworksGiftcardOrderServicePlugin->aroundCancel(
            $this->orderService,
            function ($orderId) {
                return true;
            },
            $order->getId()
        );
    }

    /**
     * @param \Aheadworks\Giftcard\Model\Service\GiftcardCartService $aheadworksGiftcardOrderServicePlugin
     * @param \Aheadworks\Giftcard\Model\ResourceModel\Giftcard\Quote\CollectionFactory $aheadworksGiftcardQuoteCollectionFactory
     * @param Quote $sourceQuote
     * @param Quote $destinationQuote
     */
    public function replicateQuoteData($aheadworksGiftcardOrderServicePlugin, $aheadworksGiftcardQuoteCollectionFactory, $sourceQuote, $destinationQuote)
    {
        try {
            // Retrieve codes from applied Aw Giftcards
            $sourceGiftcardCodes = $this->getAppliedAwGiftcardCodes($sourceQuote);
            $destinationGiftcardCodes = $this->getAppliedAwGiftcardCodes($destinationQuote);

            // Get Aw Giftcard codes need to be removed
            $removeGiftcardCodes = array_diff($destinationGiftcardCodes, $sourceGiftcardCodes);

            // Add Aw Giftcard which exists in $sourceQuote but not $destinationQuote
            foreach ($sourceGiftcardCodes as $code) {
                try {
                    // on subsequent validation calls from Bolt checkout
                    // try removing the gift card before adding it
                    $aheadworksGiftcardOrderServicePlugin->remove($destinationQuote->getId(), $code, false);
                } catch (\Exception $e) {
    
                }
                $aheadworksGiftcardOrderServicePlugin->set($destinationQuote->getId(), $code, false);
            }
            // Remove Aw Giftcard which exists in $destinationQuote but not $sourceQuote
            foreach ($removeGiftcardCodes as $code) {
                try {
                    $aheadworksGiftcardOrderServicePlugin->remove($destinationQuote->getId(), $code, false);
                } catch (\Exception $e) {
                    
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }

    /**
     * @param Quote $quote
     */
    private function getAppliedAwGiftcardCodes($quote) {
        $awGiftcardCodes = [];
        $quoteGiftcards = ($quote->getExtensionAttributes() && $quote->getExtensionAttributes()->getAwGiftcardCodes())
                            ? $quote->getExtensionAttributes()->getAwGiftcardCodes()
                            : [];
        if (!empty($quoteGiftcards)) {
            foreach ($quoteGiftcards as $giftcard) {
                if ($giftcard->isRemove()) {
                    continue;
                }
                $awGiftcardCodes[] = $giftcard->getGiftcardCode();
            }
        }
        
        return $awGiftcardCodes;
    }
}
