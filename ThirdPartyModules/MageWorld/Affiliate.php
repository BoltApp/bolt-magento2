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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\MageWorld;

use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface as Serialize;
use Bolt\Boltpay\Helper\Session as BoltSession;
use Bolt\Boltpay\Helper\Cart as BoltCart;
use Magento\Quote\Model\Quote;

class Affiliate
{

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;
    
    /**
     * @var CacheInterface
     */
    private $cache;
    
    /**
     * @var Serialize
     */
    private $serialize;
    
    /**
     * @var BoltSession|mixed
     */
    private $boltSessionHelper;
    
    /**
     * @var BoltCart
     */
    private $boltCartHelper;

    /**
     * @param Bugsnag         $bugsnagHelper
     * @param CacheInterface  $cache
     * @param Serialize       $serialize
     * @param BoltSession     $boltSessionHelper
     * @param BoltCart        $boltCartHelper
     */
    public function __construct(
        Bugsnag         $bugsnagHelper,
        CacheInterface  $cache,
        Serialize       $serialize,
        BoltSession     $boltSessionHelper,
        BoltCart        $boltCartHelper
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->cache = $cache;
        $this->serialize = $serialize;
        $this->boltSessionHelper = $boltSessionHelper;
        $this->boltCartHelper = $boltCartHelper;
    }
    
    /**
     * Insert the MW affiliate referral code into $identifier, so the cart cache can be calculated correctly.
     *
     * @param string $identifier
     * @param Quote $immutableQuote
     * @param array $cart
     */
    public function getCartCacheIdentifier($identifier, $immutableQuote, $cart)
    {
        try {
            $checkoutSession = $this->boltSessionHelper->getCheckoutSession();
            if ($referral_code = $checkoutSession->getReferralCode()) {
                $identifier .= $referral_code;
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return $identifier;
    }

    /**
     * Save MW affiliate referral code into cache.
     *
     * @param array $sessionData
     * @param $mwAffiliateHelperData
     * @param int|string $quoteId
     * @param mixed $checkoutSession
     */
    public function saveSessionData($sessionData, $mwAffiliateHelperData, $quoteId, $checkoutSession)
    {
        try {
            if ($referralCode = $checkoutSession->getReferralCode()) {
                $sessionData['mwAffiliateReferralCode'] = $referralCode;
            }
            if ($customer = $mwAffiliateHelperData->getCookie('customer')) {
                $sessionData['mwAffiliateCustomer'] = $customer;
            }
            if ($mwReferralFrom = $mwAffiliateHelperData->getCookie('mw_referral_from')) {
                $sessionData['mwAffiliateReferralFrom'] = $mwReferralFrom;
            }
            if ($mwReferralFromDomain = $mwAffiliateHelperData->getCookie('mw_referral_from_domain')) {
                $sessionData['mwAffiliateReferralFromDomain'] = $mwReferralFromDomain;
            }
            if ($mwReferralTo = $mwAffiliateHelperData->getCookie('mw_referral_to')) {
                $sessionData['mwAffiliateReferralTo'] = $mwReferralTo;
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return $sessionData;
    }
    
    /**
     * Restore MW affiliate referral code to the checkout session.
     *
     * @param Quote|mixed $quote
     */
    public function afterLoadSession($quote)
    {
        try {
            $cacheIdentifier = BoltSession::BOLT_SESSION_PREFIX . $quote->getBoltParentQuoteId();
            if ($serialized = $this->cache->load($cacheIdentifier)) {
                $sessionData = $this->serialize->unserialize($serialized);
                if (isset($sessionData["mwAffiliateReferralCode"])) {
                    $checkoutSession = $this->boltSessionHelper->getCheckoutSession();
                    $checkoutSession->setReferralCode($sessionData["mwAffiliateReferralCode"]);
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
    
    /**
     * Restore MW affiliate referral link info/referral code to the cookies/session.
     *
     * @param $result
     * @param $mwAffiliateHelperData
     * @param $mwAffiliateObserverSalesOrderAfter
     * @param $order
     */
    public function beforeGetOrderByIdProcessNewOrder($result, $mwAffiliateHelperData, $mwAffiliateObserverSalesOrderAfter, $order)
    {
        try {
            $cacheIdentifier = BoltSession::BOLT_SESSION_PREFIX . $order->getQuoteId();
            if ($serialized = $this->cache->load($cacheIdentifier)) {
                $sessionData = $this->serialize->unserialize($serialized);
                if (!isset($sessionData["mwAffiliateReferralCode"])) {
                    $this->setAffiliateCookies($sessionData);   
                } else {
                    $this->boltSessionHelper->getCheckoutSession()->setReferralCode($sessionData["mwAffiliateReferralCode"]);
                }
                // In Bolt pre-auth checkout process, the order creation does not trigger sales_order_place_after event
                // (instead, this event would be triggered when the payment is captured), as a result, the affiliate commission is missing.
                // To fix this issue, we execute the observer \MW\Affiliate\Observer\SalesOrderAfter programmatically.
                $event = new \Magento\Framework\DataObject(['order' => $order]);
                $observer = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\Event\Observer::class);
                $observer->setEvent($event);
                $mwAffiliateObserverSalesOrderAfter->execute($observer);
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
    }
    
    /**
     * Update affiliate invitation via cookies.
     *
     * @param array $sessionData
     */
    private function setAffiliateCookies($sessionData)
    {
        if (isset($sessionData["mwAffiliateCustomer"])) {
            $_COOKIE['customer'] = $sessionData["mwAffiliateCustomer"];
        }
        if (isset($sessionData["mwAffiliateReferralFrom"])) {
            $_COOKIE['mw_referral_from'] = $sessionData["mwAffiliateReferralFrom"];
        }
        if (isset($sessionData["mwAffiliateReferralFromDomain"])) {
            $_COOKIE['mw_referral_from_domain'] = $sessionData["mwAffiliateReferralFromDomain"];
        }
        if (isset($sessionData["mwAffiliateReferralTo"])) {
            $_COOKIE['mw_referral_to'] = $sessionData["mwAffiliateReferralTo"];
        }
    }
}
