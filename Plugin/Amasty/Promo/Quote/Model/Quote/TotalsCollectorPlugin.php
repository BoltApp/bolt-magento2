<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2024 Amasty (https://www.amasty.com)
 * @package Amasty_Promo
 */

namespace Bolt\Boltpay\Plugin\Amasty\Promo\Quote\Model\Quote;

use Bolt\Boltpay\Helper\Hook;
use Amasty\Promo\Plugin\Quote\Model\Quote\TotalsCollectorPlugin as AmastyTotalsCollectorPlugin;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;

class TotalsCollectorPlugin
{
    /**
     * @var State
     */
    private $state;

    /**
     * @param State $state
     */
    public function __construct(State $state)
    {
        $this->state = $state;
    }

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
        // during add/remove bolt addon items (route etc.) we need to skip amasty promo items processing
        // because during web-api rest calls from bolt amasty doesn't have access to the current session promo items state data: "ampromo_items"
        // which could lead to the restoring already removed items
        if (Hook::$fromBolt && $this->state->getAreaCode() === Area::AREA_WEBAPI_REST) {
            $address->setData('amastyFreeGiftProcessed', true);
        }

        if (Hook::$is_from_remove_discount_hook) {
            $proceedFlag = function () use ($subject) {
                $subject->proceedFlag = false;
            };
            $proceedFlag->call($subject);
        }
    }
}
