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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Controller\ReceivedUrlTrait;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface as Serialize;
use Bolt\Boltpay\Controller\ReceivedUrlInterface;

class ReceivedUrl extends Action implements ReceivedUrlInterface
{
    use ReceivedUrlTrait;

    /**
     * @var BackendUrl
     */
    private $backendUrl;

    /**
     * ReceivedUrl constructor.
     *
     * @param Context         $context
     * @param ConfigHelper    $configHelper
     * @param CartHelper      $cartHelper
     * @param Bugsnag         $bugsnag
     * @param LogHelper       $logHelper
     * @param CheckoutSession $checkoutSession
     * @param OrderHelper     $orderHelper
     * @param BackendUrl      $backendUrl
     * @param CacheInterface  $cache
     * @param Serialize       $serialize
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        CartHelper $cartHelper,
        Bugsnag $bugsnag,
        LogHelper $logHelper,
        CheckoutSession $checkoutSession,
        OrderHelper $orderHelper,
        BackendUrl $backendUrl,
        CacheInterface $cache,
        Serialize $serialize
    ) {
        parent::__construct($context);
        $this->configHelper = $configHelper;
        $this->cartHelper = $cartHelper;
        $this->bugsnag = $bugsnag;
        $this->logHelper = $logHelper;
        $this->checkoutSession = $checkoutSession;
        $this->orderHelper = $orderHelper;
        $this->backendUrl = $backendUrl;
        $this->cache = $cache;
        $this->serialize = $serialize;
    }

    /**
     * Determines if the referrer header to the current request is not set.
     * This sometimes happens for pay-by-link requests.
     *
     * @return bool true if the request referrer is not set otherwise false
     */
    private function hasNoUrlReferer()
    {
        return ! $this->getRequest()->getServer('HTTP_REFERER');
    }

    /**
     * Determines if the referrer to the current request is from Bolt CDN (pay-by-link request).
     *
     * @return bool true if the request referrer is a page on Bolt CDN otherwise false
     */
    private function hasBoltUrlReferer()
    {
        $refererUrl = $this->getRequest()->getServer('HTTP_REFERER');
        $cdnUrl = $this->configHelper->getCdnUrl();
        return substr($refererUrl, 0, strlen((string)$cdnUrl)) === $cdnUrl;
    }

    /**
     * Redirect user to admin's order confirmation page if this is backoffice order placed by admin page
     * @param quote Quote
     * @return boolean
     */
    protected function redirectToAdminIfNeeded($quote)
    {
        if ($quote->getBoltCheckoutType() != CartHelper::BOLT_CHECKOUT_TYPE_BACKOFFICE) {
            return false;
        }

        if ($this->hasNoUrlReferer() || $this->hasBoltUrlReferer()) {
            return false;
        }

        $params = [
            '_secure' => true,
            'store_id' => $quote->getStoreId(),
            '_query' => $this->getRequest()->getParams(),
        ];
        $redirectUrl = $this->backendUrl->getUrl('boltpay/order/receivedurl', $params);
        $this->_redirect($redirectUrl);
        return true;
    }

    /**
     * @return string
     */
    protected function getErrorRedirectUrl()
    {
        return '/';
    }

    /**
     * @param Order $order
     * @return string
     */
    protected function getRedirectUrl($order)
    {
        $storeId = $order->getStoreId();
        $this->_url->setScope($storeId);
        $urlPath = $this->configHelper->getSuccessPageRedirect($storeId);

        return $this->_url->getUrl($urlPath);
    }
}
