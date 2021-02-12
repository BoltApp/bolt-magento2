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

namespace Bolt\Boltpay\ThirdPartyModules\Amasty;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Quote\Model\Quote;

/**
 * Class GiftCard
 * @package Bolt\Boltpay\ThirdPartyModules\Amasty
 */
class GiftCard
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
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param Bugsnag                                   $bugsnagHelper Bugsnag helper instance
     * @param Discount                                  $discountHelper
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        Discount $discountHelper,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->discountHelper = $discountHelper;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Restores Amasty Giftcard balances used in an order that is going to be deleted
     *
     * @param \Amasty\GiftCard\Model\ResourceModel\Quote\CollectionFactory $giftcardQuoteCollectionFactory
     * @param \Amasty\GiftCard\Api\CodeRepositoryInterface                 $giftcardCodeRepository
     * @param \Amasty\GiftCard\Api\AccountRepositoryInterface              $giftcardAccountRepository
     * @param \Magento\Sales\Model\Order                                   $order
     */
    public function beforeFailedPaymentOrderSave(
        $giftcardQuoteCollectionFactory,
        $giftcardCodeRepository,
        $giftcardAccountRepository,
        $order
    ) {
        try {
            $giftcardQuotes = $giftcardQuoteCollectionFactory->create()
                ->getGiftCardsByQuoteId($order->getQuoteId());
            /** @var \Amasty\GiftCard\Model\Quote $giftcardQuote */
            foreach ($giftcardQuotes->getItems() as $giftcardQuote) {
                try {
                    $giftcardAccount = $giftcardAccountRepository->getById($giftcardQuote->getAccountId());
                    $giftcardCode = $giftcardCodeRepository->getById($giftcardAccount->getCodeId());
                    /** @see \Amasty\GiftCard\Model\Code::STATE_UNUSED */
                    $giftcardCode->setUsed(0);
                    $giftcardCodeRepository->save($giftcardCode);
                    $giftcardAccount->setCurrentValue(
                        (float)($giftcardAccount->getCurrentValue() + $giftcardQuote->getBaseGiftAmount())
                    );
                    /** @see \Amasty\GiftCard\Model\Account::STATUS_ACTIVE */
                    $giftcardAccount->setStatusId(1);
                    $giftcardAccountRepository->save($giftcardAccount);
                } catch (\Magento\Framework\Exception\LocalizedException $e) {
                    $this->bugsnagHelper->notifyException($e);
                }
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            //no giftcards applied on order, safe to ignore
        }
    }

    /**
     * @param array                                                        $result
     * @param \Amasty\GiftCard\Model\ResourceModel\Quote\CollectionFactory $giftcardQuoteCollectionFactory
     * @param \Magento\Quote\Model\Quote                                   $quote
     * @param \Magento\Quote\Model\Quote                                   $parentQuote
     * @param bool                                                         $paymentOnly
     * @return array
     */
    public function collectDiscounts(
        $result,
        $giftcardQuoteCollectionFactory,
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
                $giftcardQuotes = $giftcardQuoteCollectionFactory->create()->joinAccount()
                    ->getGiftCardsByQuoteId($quote->getId());
                /** @var \Amasty\GiftCard\Model\Quote|\Amasty\GiftCard\Model\Account $giftcard */
                $discountType = $this->discountHelper->getBoltDiscountType('by_fixed');
                foreach ($giftcardQuotes->getItems() as $giftcard) {
                    $amount = abs($giftcard->getCurrentValue());
                    $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                    $giftCardCode = $giftcard->getCode();
                    $discountItem = [
                        'description'       => __('Gift Card ') . $giftCardCode,
                        'amount'            => $roundedAmount,
                        'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_GIFTCARD,
                        'reference'         => $giftCardCode,
                        'discount_type'     => $discountType,
                        // For v1/discounts.code.apply and v2/cart.update
                        'type'              => $discountType,
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
     * @param mixed|null                            $result
     * @param \Amasty\GiftCard\Model\AccountFactory $giftcardAccountFactory
     * @param string                                $couponCode
     * @param Quote                                 $quote
     * @return \Amasty\GiftCard\Model\Account
     */
    public function loadGiftcard($result, $giftcardAccountFactory, $couponCode, $quote)
    {
        try {
            $giftcardAccount = $giftcardAccountFactory->create()->loadByCode($couponCode);
            return $giftcardAccount->getAccountId() ? $giftcardAccount : $result;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $result;
        }
    }

    /**
     * @see \Bolt\Boltpay\Model\Api\DiscountCodeValidation::applyingGiftCardCode
     *
     *
     * @param mixed                                            $result
     * @param \Amasty\GiftCard\Model\GiftCardManagementFactory $giftcardManagementFactory
     * @param string                                           $code
     * @param \Amasty\GiftCard\Model\Account                   $giftCard
     * @param Quote                                            $immutableQuote
     * @param Quote                                            $parentQuote
     * @return array
     */
    public function applyGiftcard(
        $result,
        $giftcardManagementFactory,
        $code,
        $giftCard,
        $immutableQuote,
        $parentQuote
    ) {
        if (!$giftCard instanceof \Amasty\GiftCard\Model\Account) {
            return $result;
        }
        try {
            /** Because {@see \Amasty\GiftCard\Model\GiftCardManagement::$codes} shouldn't persist  */
            $giftcardManagement = $giftcardManagementFactory->create();
            if (!$giftcardManagement->isCodeAlreadyInQuote($giftCard, $parentQuote->getId())) {

                $giftcardManagement->set($parentQuote->getId(), $code);
                $this->replicateQuoteData($parentQuote, $immutableQuote);
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
     * @param bool                                      $result
     * @param \Amasty\GiftCard\Model\GiftCardManagement $giftCardManagement
     * @param string                                    $couponCode
     * @param \Amasty\GiftCard\Model\Account            $giftCard
     * @param Quote                                     $quote
     * @return bool
     */
    public function filterApplyingGiftCardCode(
        $result,
        $giftCardManagement,
        $couponCode,
        $giftCard,
        $quote
    ) {
        if (!$giftCard instanceof \Amasty\GiftCard\Model\Account) {
            return $result;
        }
        try {
            $giftCardManagement->set($quote->getId(), $giftCard->getCode());
            return true;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return false;
        }
    }

    /**
     * @param bool                                      $result
     * @param \Amasty\GiftCard\Model\GiftCardManagement $giftCardManagement
     * @param \Amasty\GiftCard\Model\Account            $giftCard
     * @param Quote                                     $quote
     * @return bool
     */
    public function filterRemovingGiftCardCode(
        $result,
        $giftCardManagement,
        $giftCard,
        $quote
    ) {
        if (!$giftCard instanceof \Amasty\GiftCard\Model\Account) {
            return $result;
        }
        try {
            $giftCardTable = $this->resourceConnection->getTableName('amasty_amgiftcard_quote');

            $sql = "DELETE FROM {$giftCardTable} WHERE code_id = :code_id AND quote_id = :quote_id";
            $this->resourceConnection->getConnection()->query(
                $sql,
                [
                    'code_id'  => $giftCard->getCodeId(),
                    'quote_id' => $quote->getId()
                ]
            );

            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            $quote->setDataChanges(true);
            return true;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return false;
        }
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
        try {
            $connection = $this->resourceConnection->getConnection();
            $connection->beginTransaction();
            $giftCardTable = $this->resourceConnection->getTableName('amasty_amgiftcard_quote');

            // Clear previously applied gift cart codes from the immutable quote
            $sql = "DELETE FROM {$giftCardTable} WHERE quote_id = :destination_quote_id";
            $connection->query($sql, ['destination_quote_id' => $destination->getId()]);

            // Copy all gift cart codes applied to the parent quote to the immutable quote
            $sql = "INSERT INTO {$giftCardTable} (quote_id, code_id, account_id, base_gift_amount, code)
                        SELECT :destination_quote_id, code_id, account_id, base_gift_amount, code
                        FROM {$giftCardTable} WHERE quote_id = :source_quote_id";

            $connection->query(
                $sql,
                ['destination_quote_id' => $destination->getId(), 'source_quote_id' => $source->getId()]
            );

            $connection->commit();
        } catch (\Zend_Db_Statement_Exception $e) {
            $connection->rollBack();
            $this->bugsnagHelper->notifyException($e);
        }
    }
    
    /**
     * @param Quote $quote
     */
    public function clearExternalData($quote)
    {
        $connection = $this->resourceConnection->getConnection();
        try {
            $giftCardTable = $this->resourceConnection->getTableName('amasty_amgiftcard_quote');

            $sql = "DELETE FROM {$giftCardTable} WHERE quote_id = :quote_id";
            $bind = [
                'quote_id' => $quote->getId()
            ];

            $connection->query($sql, $bind);
        } catch (\Zend_Db_Statement_Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
    
    /**
     * @param Quote $quote
     */
    public function deleteRedundantDiscounts($quote)
    {
        $connection = $this->resourceConnection->getConnection();
        try {
            $giftCardTable = $this->resourceConnection->getTableName('amasty_amgiftcard_quote');
            $quoteTable = $this->resourceConnection->getTableName('quote');

            $sql = "DELETE FROM {$giftCardTable} WHERE quote_id IN 
                    (SELECT entity_id FROM {$quoteTable} 
                    WHERE bolt_parent_quote_id = :bolt_parent_quote_id AND entity_id != :entity_id)";
            
            $bind = [
                'bolt_parent_quote_id' => $quote->getBoltParentQuoteId(),
                'entity_id' => $quote->getBoltParentQuoteId()
            ];

            $connection->query($sql, $bind);
        } catch (\Zend_Db_Statement_Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
    
    /**
     * Remove Amasty Gift Card and update quote totals
     *
     * @param int $codeId
     * @param Quote $quote
     */
    public function removeAmastyGiftCard($codeId, $quote)
    {
        try {
            $connection = $this->resourceConnection->getConnection();

            $giftCardTable = $this->resourceConnection->getTableName('amasty_amgiftcard_quote');

            $sql = "DELETE FROM {$giftCardTable} WHERE code_id = :code_id AND quote_id = :quote_id";
            $connection->query($sql, ['code_id' => $codeId, 'quote_id' => $quote->getId()]);

            $this->discountHelper->updateTotals($quote);
            
        } catch (\Zend_Db_Statement_Exception $e) {
            $this->bugsnag->notifyException($e);
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }
}
