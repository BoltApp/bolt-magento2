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

namespace Bolt\Boltpay\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Controller\ReceivedUrlTrait;

class ReceivedUrl extends Action
{
    use ReceivedUrlTrait;

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
        OrderHelper $orderHelper
    ) {
        parent::__construct($context);
        $this->configHelper = $configHelper;
        $this->cartHelper = $cartHelper;
        $this->bugsnag = $bugsnag;
        $this->logHelper = $logHelper;
        $this->checkoutSession = $checkoutSession;
        $this->orderHelper = $orderHelper;
    }

    /**
     * @return boolean
     */
    protected function redirectToAdminIfNeeded($quote)
    {
        return false; // already admin
    }

    /**
     * @return string
     */
    protected function getErrorRedirectUrl()
    {
        return $this->_backendUrl->getUrl('sales/order', ['_secure' => true]);
    }

    /**
     * @param Order $order
     * @return string
     */
    protected function getRedirectUrl($order)
    {
        $storeId = $order->getStoreId();
        $params = [
            '_secure' => true,
            'order_id' => $order->getId(),
            'store_id' => $storeId
        ];
        // Set admin scope
        $this->_backendUrl->setScope(0);

        return $this->_backendUrl->getUrl('sales/order/view', $params);
    }

    /**
     * Here we don't validate URL form/secret keys because administrator is redirected here from frontend
     * This poses no security risk as this endpoint requires payload signed by Bolt to work
     * Potentially move signature validation here
     */
    public function _processUrlKeys()
    {
        return true;
    }
}
