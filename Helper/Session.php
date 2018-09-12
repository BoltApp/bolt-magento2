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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Backend\Model\Session\Quote as AdminCheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\App\CacheInterface;
use Magento\Quote\Model\Quote;
use Magento\Framework\App\State;

/**
 * Boltpay Session helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Session extends AbstractHelper
{
    const BOLT_SESSION_PREFIX  = 'BOLT_SESSION_';

    /** @var CheckoutSession */
    private $checkoutSession;

    /** @var AdminCheckoutSession */
    private $adminCheckoutSession;

    /** @var CustomerSession */
    private $customerSession;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /** @var CacheInterface */
    private $cache;

    /** @var State */
    private $appState;

    /**
     * @param Context           $context
     * @param CheckoutSession   $checkoutSession
     * @param CustomerSession   $customerSession
     * @param LogHelper         $logHelper
     * @param CacheInterface    $cache
     * @param State $appState
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        AdminCheckoutSession $adminCheckoutSession,
        CustomerSession $customerSession,
        LogHelper $logHelper,
        CacheInterface $cache,
        State $appState
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->adminCheckoutSession = $adminCheckoutSession;
        $this->customerSession = $customerSession;
        $this->logHelper = $logHelper;
        $this->cache = $cache;
        $this->appState = $appState;
    }

    /**
     * Cache the session id for the quote
     *
     * @param int|string $parentQuoteId
     * @param mixed $checkoutSession
     */
    public function saveSession($parentQuoteId, $checkoutSession) {
        // cache the session id by parent quote id
        $cacheIdentifier = self::BOLT_SESSION_PREFIX . $parentQuoteId;
        $sessionData = [
            "sessionType" => $checkoutSession instanceof \Magento\Checkout\Model\Session ? "frontend" : "admin",
            "sessionID"   => $checkoutSession->getSessionId()
         ];
        $this->cache->save(serialize($sessionData), $cacheIdentifier, [], 86400);
    }

    /**
     * Emulate session from cached session id
     *
     * @param mixed $session
     * @param string $sessionID
     */
    private function setSession($session, $sessionID) {
        // close current session
        $session->writeClose();
        // set session id (to value loaded from cache)
        $session->setSessionId($sessionID);
        // re-start the session
        $session->start();
    }

    /**
     * @param Quote $quote
     * @return void
     */
    private function replaceQuote($quote) {
        $this->checkoutSession->replaceQuote($quote);
    }

    /**
     * Load logged in customer checkout and customer sessions from cached session id.
     * Replace parent quote with immutable quote in checkout session.
     *
     * @param Quote $immutableQuote
     * @throws \Magento\Framework\Exception\SessionException
     */
    public function loadSession($immutableQuote) {

        // not an API call, no need to emulate session
        if ($this->appState->getAreaCode() != \Magento\Framework\App\Area::AREA_WEBAPI_REST) {
            $this->replaceQuote($immutableQuote);
            return;
        }

        $customerId = $immutableQuote->getCustomerId();
        $cacheIdentifier = self::BOLT_SESSION_PREFIX . $immutableQuote->getBoltParentQuoteId();

        if ($serialized = $this->cache->load($cacheIdentifier)) {

            $sessionData = unserialize($serialized);
            $sessionID = $sessionData["sessionID"];

            if ($sessionData["sessionType"] == "frontend") {
                // shipping and tax, orphaned transaction
                // cart belongs to logged in customer?
                if ($customerId) {
                    $this->setSession($this->checkoutSession, $sessionID);
                    $this->setSession($this->customerSession, $sessionID);
                }
            } else {
                // orphaned transaction
                $this->setSession($this->adminCheckoutSession, $sessionID);
            }
        }

        if ($customerId) {
            $this->customerSession->loginById($customerId);
        }
        $this->replaceQuote($immutableQuote);
    }
}
