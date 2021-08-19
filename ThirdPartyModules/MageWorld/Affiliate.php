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
     * @var BoltSession
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
     * @param int|string $quoteId
     * @param mixed $checkoutSession
     */
    public function saveSessionData($sessionData, $quoteId, $checkoutSession)
    {
        try {
            if ($referral_code = $checkoutSession->getReferralCode()) {
                $sessionData['mwAffiliateReferralCode'] = $referral_code;
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return $sessionData;
    }
    
    /**
     * Restore MW affiliate referral code to the checkout session.
     *
     * @param Quote $quote
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
     * Restore MW affiliate referral code to the checkout session when the event sales_order_place_after is dispatched.
     *
     * @param Quote $quote
     */
    public function beforeSaveUpdateOrder($quote, $transaction)
    {
        try {
            $parentQuoteId = $transaction->order->cart->order_reference;
            $cacheIdentifier = BoltSession::BOLT_SESSION_PREFIX . $parentQuoteId;
            if ($serialized = $this->cache->load($cacheIdentifier)) {
                $sessionData = $this->serialize->unserialize($serialized);
                if (isset($sessionData["mwAffiliateReferralCode"])) {
                    $checkoutSession = $this->boltSessionHelper->getCheckoutSession();
                    $checkoutSession->setReferralCode($sessionData["mwAffiliateReferralCode"]);
                    if (!is_a($quote, Quote::class, true)) {
                        $parentQuote = $this->boltCartHelper->getQuoteById($parentQuoteId);
                        $checkoutSession->replaceQuote($parentQuote);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
}
