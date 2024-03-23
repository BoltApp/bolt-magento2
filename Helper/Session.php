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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
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
use Magento\Framework\App\Area;
use Magento\Framework\Data\Form\FormKey;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Magento\Framework\Serialize\SerializerInterface as Serialize;

/**
 * Boltpay Session helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Session extends AbstractHelper
{
    const BOLT_SESSION_PREFIX  = 'BOLT_SESSION_';
    const BOLT_SESSION_PREFIX_FORM_KEY  = 'BOLT_SESSION_FORM_KEY_';
    const ENCRYPTED_SESSION_DATA_KEY = 'encrypted_session_data';
    const ENCRYPTED_SESSION_ID_KEY = 'encrypted_session_id';

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

    /** @var FormKey */
    private $formKey;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var Serialize */
    private $serialize;

    /** @var EventsForThirdPartyModules */
    private $eventsForThirdPartyModules;

    /**
     * @param Context                    $context
     * @param CheckoutSession            $checkoutSession
     * @param AdminCheckoutSession       $adminCheckoutSession
     * @param CustomerSession            $customerSession
     * @param LogHelper                  $logHelper
     * @param CacheInterface             $cache
     * @param State                      $appState
     * @param FormKey                    $formKey
     * @param ConfigHelper               $configHelper
     * @param Serialize                  $serialize
     * @param EventsForThirdPartyModules $eventsForThirdPartyModules
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        AdminCheckoutSession $adminCheckoutSession,
        CustomerSession $customerSession,
        LogHelper $logHelper,
        CacheInterface $cache,
        State $appState,
        FormKey $formKey,
        ConfigHelper $configHelper,
        EventsForThirdPartyModules $eventsForThirdPartyModules,
        Serialize $serialize
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->adminCheckoutSession = $adminCheckoutSession;
        $this->customerSession = $customerSession;
        $this->logHelper = $logHelper;
        $this->cache = $cache;
        $this->appState = $appState;
        $this->formKey = $formKey;
        $this->configHelper = $configHelper;
        $this->eventsForThirdPartyModules = $eventsForThirdPartyModules;
        $this->serialize = $serialize;
    }

    /**
     * Cache the session id for the quote
     *
     * @param int|string $quoteId
     * @param mixed $checkoutSession
     */
    public function saveSession($quoteId, $checkoutSession)
    {
        // cache the session id by (parent) quote id
        $cacheIdentifier = self::BOLT_SESSION_PREFIX . $quoteId;
        $sessionData = [
            "sessionType" => $checkoutSession instanceof \Magento\Checkout\Model\Session ? "frontend" : "admin",
            "sessionID"   => $checkoutSession->getSessionId()
        ];
        $sessionData = $this->eventsForThirdPartyModules->runFilter('saveSessionData', $sessionData, $quoteId, $checkoutSession);
        $this->cache->save($this->serialize->serialize($sessionData), $cacheIdentifier, [], 86400);
    }

    /**
     * Emulate session from cached session id
     *
     * @param \Magento\Framework\Session\SessionManager $session
     * @param string                                    $sessionID
     * @param int                                       $storeId
     *
     * @throws \Magento\Framework\Exception\SessionException
     */
    protected function setSession($session, $sessionID, $storeId)
    {
        if (! $this->configHelper->isSessionEmulationEnabled($storeId)) {
            return;
        }
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
    private function replaceQuote($quote)
    {
        $this->checkoutSession->replaceQuote($quote);
    }

    /**
     * Load logged in customer checkout and customer sessions from cached session id.
     * Replace parent quote with immutable quote in checkout session.
     *
     * @param Quote|mixed $quote
     * @param array $metadata
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function loadSession($quote, $metadata = [])
    {
        // not an API call, no need to emulate session
        if ($this->appState->getAreaCode() != \Magento\Framework\App\Area::AREA_WEBAPI_REST) {
            $this->replaceQuote($quote);
            return;
        }

        $metadata = (array)$metadata;

        $customerId = $quote->getCustomerId();
        $cacheIdentifier = self::BOLT_SESSION_PREFIX . $quote->getBoltParentQuoteId();

        if (key_exists(self::ENCRYPTED_SESSION_DATA_KEY, $metadata)) {
            $sessionData = json_decode(
                $this->configHelper->decrypt(base64_decode($metadata[self::ENCRYPTED_SESSION_DATA_KEY])),
                true
            );
            $this->eventsForThirdPartyModules->dispatchEvent('restoreSessionData', $sessionData, $quote);
        }

        if (key_exists(self::ENCRYPTED_SESSION_ID_KEY, $metadata)) {
            $sessionID = $this->configHelper->decrypt(base64_decode($metadata[self::ENCRYPTED_SESSION_ID_KEY]));
            $storeId = $quote->getStoreId();
            if ($quote->getData('bolt_checkout_type') == \Bolt\Boltpay\Helper\Cart::BOLT_CHECKOUT_TYPE_BACKOFFICE) {
                $this->setSession($this->adminCheckoutSession, $sessionID, $storeId);
            } else {
                $this->setSession($this->checkoutSession, $sessionID, $storeId);
                $this->setSession($this->customerSession, $sessionID, $storeId);
            }
        }
        // @todo remove when update.cart hook starts supporting metadata
        elseif ($serialized = $this->cache->load($cacheIdentifier)) {
            $sessionData = $this->serialize->unserialize($serialized);
            $sessionID = $sessionData['sessionID'];
            $storeId = $quote->getStoreId();

            if ($sessionData['sessionType'] == 'frontend') {
                // shipping and tax, orphaned transaction
                // cart belongs to logged in customer?
                $this->setSession($this->checkoutSession, $sessionID, $storeId);
                $this->setSession($this->customerSession, $sessionID, $storeId);
            } else {
                // orphaned transaction
                $this->setSession($this->adminCheckoutSession, $sessionID, $storeId);
            }
        }

        if ($customerId) {
            $this->customerSession->loginById($customerId);
        }
        $this->replaceQuote($quote);
        $this->eventsForThirdPartyModules->dispatchEvent("afterLoadSession", $quote);
    }

    /**
     * Load and set cached form key
     *
     * @param Quote $quote
     */
    public function setFormKey($quote)
    {
        $cacheIdentifier = self::BOLT_SESSION_PREFIX_FORM_KEY . $quote->getId();
        $this->formKey->set($this->cache->load($cacheIdentifier));
    }

    /**
     * Store form key in cache for use later from the create order API call
     *
     * @param Quote $quote
     */
    public function cacheFormKey($quote)
    {
        $cacheIdentifier = self::BOLT_SESSION_PREFIX_FORM_KEY . $quote->getId();
        $this->cache->save($this->formKey->getFormKey(), $cacheIdentifier, [], 14400);
    }

    /**
     * @return AdminCheckoutSession|CheckoutSession|mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCheckoutSession()
    {
        return ($this->appState->getAreaCode() == Area::AREA_ADMINHTML) ? $this->adminCheckoutSession : $this->checkoutSession;
    }
}
