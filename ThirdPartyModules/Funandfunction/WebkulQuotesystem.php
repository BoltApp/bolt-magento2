<?php

namespace Bolt\Boltpay\ThirdPartyModules\Funandfunction;

use Bolt\Boltpay\Helper\Session as SessionHelper;
use Funandfunction\WebkulQuotesystem\Model\WkQuoteSession;

class WebkulQuotesystem
{
    /**
     * @var WkQuoteSession
     */
    protected $wkQuoteSession;

    /**
     * @var SessionHelper
     */
    protected $sessionHelper;

    public function __construct(
        SessionHelper $sessionHelper
    ) {
        $this->sessionHelper   = $sessionHelper;
    }

    public function beforePrepareQuote($quote)
    {
        $this->wkQuoteSession->setWkQuoteId($quote->getId());
    }
}
