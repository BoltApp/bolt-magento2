<?php

namespace Bolt\Boltpay\Model\ThirdPartyEvents;

trait CollectsSessionData
{
    /**
     * @param array                      $sessionData
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Quote\Model\Quote $immutableQuote
     *
     * @return array
     */
    abstract public function collectSessionData($sessionData, $quote, $immutableQuote);
}
