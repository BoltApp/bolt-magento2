<?php

namespace Bolt\Boltpay\Plugin;

use Magento\Checkout\Model\Session as CheckoutSession;

class ClearQuote
{
    public function afterClearQuote(CheckoutSession $subject)
    {
        $subject->setLoadInactive(false);
    }
}
