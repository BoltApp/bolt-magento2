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

namespace Bolt\Boltpay\ThirdPartyModules\Amasty;

use Amasty\GiftCardAccount\Api\Data\GiftCardAccountInterface;
use Amasty\GiftCardAccount\Model\GiftCardAccount\GiftCardCartProcessor;
use Amasty\GiftCardAccount\Model\OptionSource\AccountStatus;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

/**
 * Class GiftCardAccount
 * @package Bolt\Boltpay\ThirdPartyModules\Amasty
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
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param Bugsnag            $bugsnagHelper Bugsnag helper instance
     * @param Discount           $discountHelper
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        Discount $discountHelper,
        ResourceConnection $resourceConnection
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->discountHelper = $discountHelper;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Restores Amasty Giftcard balances used in an order that is going to be deleted
     *
     * @param \Amasty\GiftCardAccount\Model\GiftCardAccount\Repository         $giftcardRepository
     * @param \Amasty\GiftCardAccount\Model\GiftCardExtension\Order\Repository $giftcardOrderRepository
     * @param Order                                                            $order
     */
    public function beforeFailedPaymentOrderSave($giftcardRepository, $giftcardOrderRepository, $order)
    {
        try {
            $giftcardOrderExtension = $giftcardOrderRepository->getByOrderId($order->getId());
            foreach ($giftcardOrderExtension->getGiftCards() as $orderGiftcard) {
                try {
                    /** @see GiftCardCartProcessor::GIFT_CARD_ID */
                    $giftcard = $giftcardRepository->getById($orderGiftcard['id']);
                    $giftcard->setCurrentValue(
                        /** @see GiftCardCartProcessor::GIFT_CARD_BASE_AMOUNT */
                        (float)($giftcard->getCurrentValue() + $orderGiftcard['b_amount'])
                    );
                    /** @see \Amasty\GiftCardAccount\Model\OptionSource\AccountStatus::STATUS_ACTIVE */
                    $giftcard->setStatus(1);
                    $giftcardRepository->save($giftcard);
                } catch (\Magento\Framework\Exception\LocalizedException $e) {
                    $this->bugsnagHelper->notifyException($e);
                }
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            //no giftcards applied on order, safe to ignore
        }
    }

    /**
     * @param array                                                          $result
     * @param \Amasty\GiftCardAccount\Api\GiftCardAccountRepositoryInterface $giftcardAccountRepository
     * @param \Amasty\GiftCardAccount\Api\GiftCardQuoteRepositoryInterface   $giftcardQuoteRepository
     * @param \Magento\Quote\Model\Quote                                     $quote
     * @param \Magento\Quote\Model\Quote                                     $parentQuote
     * @param bool                                                           $paymentOnly
     * @return array
     */
    public function collectDiscounts(
        $result,
        $giftcardAccountRepository,
        $giftcardQuoteRepository,
        $quote,
        $parentQuote,
        $paymentOnly
    ) {
        list ($discounts, $totalAmount, $diff) = $result;

        try {
            $currencyCode = $quote->getQuoteCurrencyCode();
            /** @var \Magento\Quote\Model\Quote\Address\Total[] */
            $totals = $quote->getTotals();
            $totalDiscount = $totals[Discount::AMASTY_GIFTCARD] ?? null;
            $roundedDiscountAmount = 0;
            $discountAmount = 0;
            ///////////////////////////////////////////////////////////////////////////
            // If Amasty gift cards can be used for shipping and tax (PayForEverything)
            // accumulate all the applied gift cards balance as discount amount. If the
            // final discounts sum is greater than the cart total amount ($totalAmount < 0)
            // the "fixed_amount" type is added below.
            ///////////////////////////////////////////////////////////////////////////
            if ($totalDiscount && $totalDiscount->getValue() && $this->discountHelper->getAmastyPayForEverything()) {
                $giftcardQuote = $giftcardQuoteRepository->getByQuoteId($quote->getId());
                foreach ($giftcardQuote->getGiftCards() as $appliedGiftcardData) {
                    $giftcard = $giftcardAccountRepository->getById($appliedGiftcardData['id']);
                    $amount = abs($giftcard->getCurrentValue());
                    $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                    $giftCardCode = $giftcard->getCodeModel()->getCode();
                    $discountItem = [
                        'description'       => __('Gift Card ') . $giftCardCode,
                        'amount'            => $roundedAmount,
                        'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_GIFTCARD,
                        'reference'         => $giftCardCode,
                        'discount_type'     => $this->discountHelper->getBoltDiscountType('by_fixed'),
                        // For v1/discounts.code.apply and v2/cart.update
                        'type'              => $this->discountHelper->getBoltDiscountType('by_fixed'),
                        // For v1/merchant/order
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
     * @param null                                                           $result
     * @param \Amasty\GiftCardAccount\Api\GiftCardAccountRepositoryInterface $giftcardAccountRepository
     * @param string                                                         $couponCode
     * @param string                                                         $storeId
     * @return GiftCardAccountInterface|null
     */
    public function loadGiftcard($result, $giftcardAccountRepository, $couponCode, $storeId)
    {
        try {
            return $giftcardAccountRepository->getByCode($couponCode);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $result;
        }
    }

    /**
     * @see \Bolt\Boltpay\Model\Api\DiscountCodeValidation::applyingGiftCardCode
     *
     *
     * @param null                                                                $result
     * @param \Amasty\GiftCardAccount\Model\GiftCardAccount\GiftCardCartProcessor $giftcardProcessor
     * @param string                                                              $code
     * @param GiftCardAccountInterface                                            $giftCard
     * @param Quote                                                               $immutableQuote
     * @param Quote                                                               $parentQuote
     * @return array
     */
    public function applyGiftcard(
        $result,
        $giftcardProcessor,
        $code,
        $giftCard,
        $immutableQuote,
        $parentQuote
    ) {
        if (!$giftCard instanceof GiftCardAccountInterface) {
            return $result;
        }
        try {
            foreach ([$parentQuote, $immutableQuote] as $quote) {
                $isGiftcardApplied = !empty(
                array_filter(
                    $quote->getExtensionAttributes()->getAmGiftcardQuote()->getGiftCards(),
                    function ($giftCardData) use ($giftCard) {
                        return $giftCard->getAccountId() == $giftCardData['id'];
                    }
                )
                );
                if ($isGiftcardApplied) {
                    continue;
                }
                $giftcardProcessor->applyToCart($giftCard, $quote);
            }

            return [
                'status'          => 'success',
                'discount_code'   => $code,
                'discount_amount' => abs(
                    CurrencyUtils::toMinor($giftCard->getCurrentValue(), $parentQuote->getQuoteCurrencyCode())
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
     * @param bool                           $result
     * @param GiftCardCartProcessor          $giftcardProcessor
     * @param string                         $couponCode
     * @param mixed|GiftCardAccountInterface $giftCard
     * @param Quote                          $quote
     * @return bool
     */
    public function filterApplyingGiftCardCode(
        $result,
        $giftcardProcessor,
        $couponCode,
        $giftCard,
        $quote
    ) {
        if (!$giftCard instanceof GiftCardAccountInterface) {
            return $result;
        }
        try {
            $giftcardProcessor->applyToCart($giftCard, $quote);
            $result = true;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
        }
        return $result;
    }

    /**
     * @param bool                     $result
     * @param GiftCardCartProcessor    $giftcardProcessor
     * @param GiftCardAccountInterface $giftCard
     * @param Quote                    $quote
     *
     * @return bool
     */
    public function filterRemovingGiftCardCode(
        $result,
        $giftcardProcessor,
        $giftCard,
        $quote
    ) {
        if (!$giftCard instanceof GiftCardAccountInterface) {
            return $result;
        }
        try {
            $giftcardProcessor->removeFromCart($giftCard, $quote);
            $result = true;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
        }
        return $result;
    }

    /**
     * @param Quote $source
     * @param Quote $destination
     */
    public function replicateQuoteData(
        $source,
        $destination
    ) {
        if ($source->getId() == $destination->getId()) {
            return;
        }
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        $giftCardTable = $this->resourceConnection->getTableName('amasty_giftcard_quote');

        // Clear previously applied gift cart codes from the immutable quote
        $sql = "DELETE FROM {$giftCardTable} WHERE quote_id = :destination_quote_id";
        $connection->query($sql, ['destination_quote_id' => $destination->getId()]);

        // Copy all gift cart codes applied to the parent quote to the immutable quote
        $sql = "INSERT INTO {$giftCardTable} (quote_id, gift_cards, gift_amount, base_gift_amount, gift_amount_used, base_gift_amount_used)
                        SELECT :destination_quote_id, gift_cards, gift_amount, base_gift_amount, gift_amount_used, base_gift_amount_used
                        FROM {$giftCardTable} WHERE quote_id = :source_quote_id";

        $connection->query(
            $sql,
            ['destination_quote_id' => $destination->getId(), 'source_quote_id' => $source->getId()]
        );

        $connection->commit();
    }
}
