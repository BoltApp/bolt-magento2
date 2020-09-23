<?php

namespace Bolt\Custom\Plugin\Helper;

/**
 * Plugin for {@see \Bolt\Boltpay\Helper\Session}
 */
class SessionPlugin
{
    /**
     * @var \Magento\Framework\Encryption\Encryptor
     */
    protected $encryptor;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Bolt\Boltpay\Helper\Bugsnag
     */
    protected $bugsnag;
    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;
    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    private $cache;
    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * BaseWebhookPlugin constructor.
     * @param \Magento\Framework\Encryption\Encryptor $encryptor
     * @param \Magento\Checkout\Model\Session         $checkoutSession
     * @param \Magento\Customer\Model\Session         $customerSession
     * @param \Magento\Framework\App\CacheInterface   $cache
     * @param \Magento\Framework\App\State            $appState
     * @param \Bolt\Boltpay\Helper\Bugsnag            $bugsnag
     * @param \Magento\Framework\Registry             $registry
     */
    public function __construct(
        \Magento\Framework\Encryption\Encryptor $encryptor,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Framework\App\State $appState,
        \Bolt\Boltpay\Helper\Bugsnag $bugsnag,
        \Magento\Framework\Registry $registry
    ) {
        $this->encryptor = $encryptor;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->cache = $cache;
        $this->appState = $appState;
        $this->bugsnag = $bugsnag;
        $this->registry = $registry;
    }

    /**
     * Before plugin for {@see \Bolt\Boltpay\Helper\Session::loadSession}
     * Used to log to Bugsnag whether the method is called if an exception occurs
     *
     * @param \Bolt\Boltpay\Helper\Session $subject original Bolt session helper
     * @param \Magento\Quote\Model\Quote   $quote used to find session id to be restored
     *
     * @return \Magento\Quote\Model\Quote[] unchanged
     */
    public function beforeLoadSession(\Bolt\Boltpay\Helper\Session $subject, $quote)
    {
        $this->bugsnag->registerCallback(
            function ($report) use ($quote) {
                /** @var \Bugsnag\Report $report */
                $report->setMetaData(
                    [
                        'SESSION' => [
                            'BEFORE_LOAD_SESSION_CALLED'   => true,
                            'BEFORE_LOAD_SESSION_QUOTE_ID' => $quote->getId(),
                        ]
                    ]
                );
            }
        );
        $this->bugsnag->notifyError('Plugin executed', '\Bolt\Custom\Plugin\Helper\SessionPlugin::beforeLoadSession');

        $sessionMetadata = $this->registry->registry(\Bolt\Custom\Plugin\Model\Api\BaseWebhookPlugin::BOLT_METADATA);
        if (is_array($sessionMetadata) && $encryptedSessionId = $sessionMetadata['encrypted_id']) {
            try {
                $sessionId = $this->encryptor->decrypt($encryptedSessionId);
                $cacheIdentifier = \Bolt\Boltpay\Helper\Session::BOLT_SESSION_PREFIX . $quote->getBoltParentQuoteId();
                $this->cache->save(
                    \serialize(['sessionID' => $sessionId, 'sessionType' => $sessionMetadata['type']]),
                    $cacheIdentifier
                );
            } catch (\Exception $e) {
                $this->bugsnag->notifyException($e);
            }
        }

        return [$quote];
    }

    /**
     * After plugin for {@see \Bolt\Boltpay\Helper\Session::loadSession}
     * Used to load session for guest users (which we don't do by default),
     * since ID.me integration stores its data there regardless
     *
     * @see \IDme\GroupVerification\Controller\Authorize\Verify::setUserData
     * @see \IDme\GroupVerification\Helper\Data::getUserGroup
     *
     * @param \Bolt\Boltpay\Helper\Session $subject original Bolt session helper
     * @param null                         $result result of the original method call
     * @param \Magento\Quote\Model\Quote   $quote used to find session id to be restored
     *
     * @return null original method call is void
     *
     * @throws \Magento\Framework\Exception\LocalizedException if area code is not set
     */
    public function afterLoadSession(\Bolt\Boltpay\Helper\Session $subject, $result, $quote)
    {
        $this->bugsnag->registerCallback(
            function ($report) {
                /** @var \Bugsnag\Report $report */
                $report->setMetaData(
                    [
                        'SESSION' => [
                            'RESTORE_ATTEMPTED' => true,
                            'AREA_CODE'         => $this->appState->getAreaCode()
                        ]
                    ]
                );
            }
        );
        $this->bugsnag->notifyError('Plugin executed', '\Bolt\Custom\Plugin\Helper\SessionPlugin::afterLoadSession');
        // not an API call, no need to emulate session
        if ($this->appState->getAreaCode() != \Magento\Framework\App\Area::AREA_WEBAPI_REST) {
            return $result;
        }

        $cacheIdentifier = \Bolt\Boltpay\Helper\Session::BOLT_SESSION_PREFIX . $quote->getBoltParentQuoteId();
        $customerId = $quote->getCustomerId();

        $this->bugsnag->registerCallback(
            function ($report) use ($cacheIdentifier) {
                /** @var \Bugsnag\Report $report */
                $report->setMetaData(
                    [
                        'SESSION' => [
                            'RESTORE_CACHE_IDENTIFIER'   => $cacheIdentifier,
                            'RESTORE_SESSION_CACHE_DATA' => $this->cache->load($cacheIdentifier)
                        ]
                    ]
                );
            }
        );

        if ($serialized = $this->cache->load($cacheIdentifier)) {
            $this->bugsnag->notifyError('Session loaded from cache', 'Cache data: ' . $serialized);
            $sessionData = unserialize($serialized);
            $sessionID = $sessionData["sessionID"];

            if (!$customerId && $sessionData["sessionType"] == "frontend") {
                $this->setSession($this->checkoutSession, $sessionID);
                $this->setSession($this->customerSession, $sessionID);
            }
        }

        $this->addCustomerSessionData();

        return $result;
    }

    /**
     * Emulate session from cached session id
     *
     * @param \Magento\Framework\Session\SessionManagerInterface $session on which to set session id
     * @param string                                             $sessionID to be applied to session
     */
    protected function setSession($session, $sessionID)
    {
        $this->bugsnag->registerCallback(
            function ($report) use ($session, $sessionID) {
                /** @var \Bugsnag\Report $report */
                $report->setMetaData(
                    [
                        'SESSION' => [
                            "RESTORED_SESSION_CLASS[" . get_class($session) . "]" => $sessionID,
                        ]
                    ]
                );
            }
        );
        $this->bugsnag->notifyError('Restoring session', get_class($session) . ' to ' . $sessionID);
        // close current session
        $session->writeClose();
        // set session id (to value loaded from cache)
        $session->setSessionId($sessionID);
        // re-start the session
        $session->start();
    }

    /**
     * Restores session data using provided transaction cart metadata array
     */
    protected function addCustomerSessionData()
    {
        $sessionMetadata = $this->registry->registry(\Bolt\Custom\Plugin\Model\Api\BaseWebhookPlugin::BOLT_METADATA);
        if (!empty($sessionMetadata) && !empty($sessionMetadata['customer_data'])) {
            foreach ($sessionMetadata['customer_data'] as $key => $value) {
                $this->customerSession->setData($key, $value);
            }
        }
    }
}