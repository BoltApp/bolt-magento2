<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_Promo
 */

namespace Bolt\Boltpay\Plugin\Amasty\Promo\Quote\Model\Quote;

use Bolt\Boltpay\Helper\Hook;
use Amasty\Promo\Plugin\Quote\Model\Quote\TotalsCollectorPlugin as AmastyTotalsCollectorPlugin;


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
        if (Hook::$is_from_remove_discount_hook) {
            $proceedFlag = function () use ($subject) {
                $subject->proceedFlag = false;
            };
            $proceedFlag->call($subject);
        }
    }
}