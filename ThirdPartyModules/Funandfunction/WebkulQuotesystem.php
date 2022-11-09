<?php

namespace Bolt\Boltpay\ThirdPartyModules\Funandfunction;

use Magento\Checkout\Model\Session as CheckoutSession;
use Funandfunction\WebkulQuotesystem\Model\WkQuoteSession;

class WebkulQuotesystem
{
    /**
     * @var WkQuoteSession
     */
    protected $wkQuoteSession;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    public function __construct(
        CheckoutSession $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
    }

    public function replicateQuoteData($source, $destination)
    {
        $this->wkQuoteSession->setWkQuoteId($destination->getId());
        $this->checkoutSession->replaceQuote($destination);
    }
}
