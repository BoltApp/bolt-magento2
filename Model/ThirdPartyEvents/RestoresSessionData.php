<?php

namespace Bolt\Boltpay\Model\ThirdPartyEvents;

trait RestoresSessionData
{
    /**
     * @param array                      $sessionData
     * @param \Magento\Quote\Model\Quote $quote
     *
     * @return void
     */
    abstract public function restoreSessionData($sessionData, $quote);
}
