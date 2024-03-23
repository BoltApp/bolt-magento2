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

namespace Bolt\Boltpay\ThirdPartyModules\Amasty;

use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface as Serialize;
use Bolt\Boltpay\Helper\Session as BoltSession;
use Magento\Quote\Model\Quote;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;

class Affiliate
{
    const AMASTY_CURRENT_AFFILIATE_ACCOUNT_CODE = 'current_affiliate_account_code';

    /**
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

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
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @param Bugsnag $bugsnagHelper
     * @param CacheInterface $cache
     * @param Serialize $serialize
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Bugsnag         $bugsnagHelper,
        CacheInterface  $cache,
        Serialize       $serialize,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->cache = $cache;
        $this->serialize = $serialize;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Save Amasty affiliate referral code into cache.
     *
     * @param array $sessionData
     * @param int|string $quoteId
     * @param mixed $checkoutSession
     */
    public function saveSessionData($sessionData, $quoteId, $checkoutSession)
    {
        $sessionData[self::AMASTY_CURRENT_AFFILIATE_ACCOUNT_CODE] = $this->cookieManager->getCookie(self::AMASTY_CURRENT_AFFILIATE_ACCOUNT_CODE);

        return $sessionData;
    }

    /**
     * Restore Amasty affiliate referral code to cookie.
     *
     * @param Quote|mixed $quote
     */
    public function afterLoadSession($quote)
    {
        try {
            $cacheIdentifier = BoltSession::BOLT_SESSION_PREFIX . $quote->getBoltParentQuoteId();
            if ($serialized = $this->cache->load($cacheIdentifier)) {
                $sessionData = $this->serialize->unserialize($serialized);
                if (isset($sessionData[self::AMASTY_CURRENT_AFFILIATE_ACCOUNT_CODE])) {
                    $this->addToCookies($sessionData[self::AMASTY_CURRENT_AFFILIATE_ACCOUNT_CODE]);
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }

    /**
     * Trigger Amasty Affiliate sales_order_place_after event
     * @param $result
     * @param $amastyAffiliateObserverSalesOrderAfter
     * @param $order
     * @return mixed
     */
    public function beforeGetOrderByIdProcessNewOrder($result, $amastyAffiliateObserverSalesOrderAfter, $order)
    {
        try {
            $cacheIdentifier = BoltSession::BOLT_SESSION_PREFIX . $order->getQuoteId();
            if ($serialized = $this->cache->load($cacheIdentifier)) {
                $sessionData = $this->serialize->unserialize($serialized);
                if (isset($sessionData[self::AMASTY_CURRENT_AFFILIATE_ACCOUNT_CODE])) {
                    $this->addToCookies($sessionData[self::AMASTY_CURRENT_AFFILIATE_ACCOUNT_CODE]);
                }
                // In Bolt pre-auth checkout process, the order creation does not trigger sales_order_place_after event
                // (instead, this event would be triggered when the payment is captured), as a result, the affiliate commission is missing.
                // To fix this issue, we execute the observer \Amasty\Affiliate\Observer\SalesOrderAfterPlaceObserver programmatically.
                $event = new \Magento\Framework\DataObject(['order' => $order]);
                $observer = \Magento\Framework\App\ObjectManager::getInstance()->create(\Magento\Framework\Event\Observer::class);
                $observer->setEvent($event);
                $observer->setData('order', $order);
                $observer->setEventName('sales_order_place_after');
                $amastyAffiliateObserverSalesOrderAfter->execute($observer);
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
    }

    /**
     * Add current affiliate referring code to cookies
     *
     * @param string $accountCode
     */
    private function addToCookies($accountCode)
    {
        // phpcs:ignore
        $_COOKIE[self::AMASTY_CURRENT_AFFILIATE_ACCOUNT_CODE] = $accountCode;
    }
}
