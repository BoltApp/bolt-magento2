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

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Helper\Order as orderHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Sales\Model\Order;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * Class Save.
 * Converts / saves the quote into an order.
 * Updates the order payment/transaction info. Closes the quote / order session.
 */
class Save extends Action
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /** @var CheckoutSession */
    private $checkoutSession;

    /**
     * @var orderAdminHelper
     */
    private $orderHelper;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @param Context           $context
     * @param JsonFactory       $resultJsonFactory
     * @param CheckoutSession   $checkoutSession
     * @param orderHelper       $orderHelper
     * @param ConfigHelper      $configHelper
     * @param Bugsnag           $bugsnag
     * @param DataObjectFactory $dataObjectFactory
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CheckoutSession $checkoutSession,
        orderHelper $orderHelper,
        configHelper $configHelper,
        Bugsnag $bugsnag,
        DataObjectFactory $dataObjectFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->checkoutSession   = $checkoutSession;
        $this->orderHelper       = $orderHelper;
        $this->configHelper      = $configHelper;
        $this->bugsnag           = $bugsnag;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * @return Json
     * @throws Exception
     */
    public function execute()
    {
        try {
            // get the transaction reference parameter
            $reference = $this->getRequest()->getParam('reference');
            $storeId = $this->getRequest()->getParam('store_id');
            // call order save and update
            list($quote, $order) = $this->orderHelper->saveUpdateOrder($reference, $storeId);

            $orderId = $order->getId();
            // clear quote session
            $this->clearQuoteSession($quote);
            // clear order session
            $this->clearOrderSession($order);
            // return the success page redirect URL
            $result = $this->resultJsonFactory->create();

            $result->setData(['success_url' => $this->_url->getUrl('sales/order/view/', ['order_id' => $orderId])]);

            return $result;
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            $result = $this->resultJsonFactory->create();
            $result->setHttpResponseCode(422);
            $result->setData(['status' => 'error', 'code' => '1000','message' => $e->getMessage()]);

            return $result;
        }
    }

    /**
     * Clear quote session after successful order
     *
     * @param Quote
     *
     * @return void
     */
    private function clearQuoteSession($quote)
    {
        $this->checkoutSession->setLastQuoteId($quote->getId())
                              ->setLastSuccessQuoteId($quote->getId())
                              ->clearHelperData();
    }

    /**
     * Clear order session after successful order
     *
     * @param Order
     *
     * @return void
     */
    private function clearOrderSession($order)
    {
        $this->checkoutSession->setLastOrderId($order->getId())
                              ->setLastRealOrderId($order->getIncrementId())
                              ->setLastOrderStatus($order->getStatus());
    }
}
