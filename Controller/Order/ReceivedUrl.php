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

class ReceivedUrl extends Action
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
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        CartHelper $cartHelper,
        Bugsnag $bugsnag,
        LogHelper $logHelper,
        CheckoutSession $checkoutSession,
        OrderHelper $orderHelper,
        BackendUrl $backendUrl
    ) {
        parent::__construct($context);
        $this->configHelper = $configHelper;
        $this->cartHelper = $cartHelper;
        $this->bugsnag = $bugsnag;
        $this->logHelper = $logHelper;
        $this->checkoutSession = $checkoutSession;
        $this->orderHelper = $orderHelper;
        $this->backendUrl = $backendUrl;
    }

    /**
     * Determines if referrer to the current request is admin order creation page
     *
     * @return bool true if request referrer is admin order create page otherwise false
     */
    private function hasAdminUrlReferer()
    {
        $this->backendUrl->setScope(0);
        /**
         * @see \Magento\Framework\App\Response\RedirectInterface::getRefererUrl can't be used
         * because {@see \Magento\Store\App\Response\Redirect::_isUrlInternal} check fails
         * when admin is on a different (sub)domain compared to store
         */
        $refererUrl = $this->getRequest()->getServer('HTTP_REFERER');
        $adminUrl = $this->backendUrl->getUrl("sales/order_create/index", ['_nosecret' => true]);
        return substr($refererUrl, 0, strlen($adminUrl)) === $adminUrl;
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

        if (!$this->hasAdminUrlReferer()) {
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
