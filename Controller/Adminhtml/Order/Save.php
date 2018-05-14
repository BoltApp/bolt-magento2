<?php
/**
 *
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Bolt\Boltpay\Controller\Adminhtml\Order;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Helper\Admin\Order as orderAdminHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Sales\Model\Order;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * Class Save.
 * Converts / saves the quote into an order.
 * Updates the order payment/transaction info. Closes the quote / order session.
 *
 * @package Bolt\Boltpay\Controller\Adminhtml\Order
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
     * @param orderAdminHelper  $orderAdminHelper
     * @param ConfigHelper      $configHelper
     * @param Bugsnag           $bugsnag
     * @param DataObjectFactory $dataObjectFactory
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CheckoutSession $checkoutSession,
        orderAdminHelper $orderAdminHelper,
        configHelper $configHelper,
        Bugsnag $bugsnag,
        DataObjectFactory $dataObjectFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->checkoutSession   = $checkoutSession;
        $this->orderAdminHelper  = $orderAdminHelper;
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
            // call order save and update
            list($quote, $order) = $this->orderAdminHelper->saveUpdateOrder($reference, false);

            $orderId = $order->getId();
            // clear the session data
            if ($orderId) {
                //Clear quote session
                $this->clearQuoteSession($quote);
                //Clear order session
                $this->clearOrderSession($order);
            }
            // return the success page redirect URL
            $result = $this->resultJsonFactory->create();

            $result->setData(array('success_url' => $this->_url->getUrl('test/test/test/')));
            return $result;

        } catch (Exception $e) {
            
            $this->bugsnag->notifyException($e);
            $result = $this->resultJsonFactory->create();
            $result->setHttpResponseCode(422);
            $result->setData(array('status' => 'error', 'code' => '1000','message' => $e->getMessage()));
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
