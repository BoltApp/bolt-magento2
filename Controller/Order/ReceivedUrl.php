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
use Magento\Framework\App\Response\RedirectInterface;

/**
 * Class ReceivedUrl
 *
 * @package Bolt\Boltpay\Controller\Order
 */
class ReceivedUrl extends Action
{
    use ReceivedUrlTrait;

    /**
     * @var BackendUrl
     */
    private $backendUrl;

    /**
     * @var RedirectInterface\
     */
    private $redirect;

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
        RedirectInterface $redirect
    ) {
        parent::__construct($context);
        $this->configHelper = $configHelper;
        $this->cartHelper = $cartHelper;
        $this->bugsnag = $bugsnag;
        $this->logHelper = $logHelper;
        $this->checkoutSession = $checkoutSession;
        $this->orderHelper = $orderHelper;
        $this->backendUrl = $backendUrl;
        $this->redirect = $redirect;
    }

    /**
     * @return boolean
     */
    protected function redirectToAdminIfNeeded($quote)
    {
        if (!$quote->getBoltIsBackendOrder()) {
            return false;
        }
        // Set admin scope
        $this->backendUrl->setScope(0);
        $refererUrl = $this->redirect->getRefererUrl();
        $adminUrl = $this->backendUrl->getUrl("sales/order_create/index/", []);
        if (substr($refererUrl, 0, strlen($adminUrl)) !== $adminUrl) {
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
