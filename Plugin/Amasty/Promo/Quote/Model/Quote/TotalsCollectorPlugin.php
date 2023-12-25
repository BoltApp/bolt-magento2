<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_Promo
 */

namespace Bolt\Boltpay\Plugin\Amasty\Promo\Quote\Model\Quote;

use Bolt\Boltpay\Helper\Hook;
use Amasty\Promo\Plugin\Quote\Model\Quote\TotalsCollectorPlugin as AmastyTotalsCollectorPlugin;
use Magento\Quote\Model\Quote;


class TotalsCollectorPlugin
{
    /**
     * @param AmastyTotalsCollectorPlugin $subject
     * @param \Magento\Quote\Model\Quote\TotalsCollector $nextSubject
     * @param callable $nextProceed
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Quote\Model\Quote\Address $address
     */
    public function beforeAroundCollectAddressTotals(
        AmastyTotalsCollectorPlugin $subject,
        \Magento\Quote\Model\Quote\TotalsCollector $nextSubject,
        callable $nextProceed,
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Model\Quote\Address $address
    )
    {
        /** @var Quote|mixed $quote */
        // legacy flow: skip amasty promo calculation for immutable quote collect total calls
        // amasty promo define the db table with relations between quote id and promo items
        // because id's of immutable quote and original quote are different, amasty promo can't find promo items status during immutable quote collect totals
        // which leads to mismatch between original quote and immutable quote which leads to restoring already removed items in card
        if ($quote->getBoltParentQuoteId() != null && $quote->getBoltParentQuoteId() != $quote->getId()) {
            $address->setData('amastyFreeGiftProcessed', true);
        }
        /** @var mixed $subject*/
        if (Hook::$is_from_remove_discount_hook) {
            $proceedFlag = function () use ($subject) {
                $subject->proceedFlag = false;
            };
            $proceedFlag->call($subject);
        }
    }
}
