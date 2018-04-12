<?php
/**
 *
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Bolt\Boltpay\Controller\Order;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObject;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Sales\Model\Order;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * Class Save.
 * Converts / saves the quote into an order.
 * Updates the order payment/transaction info. Closes the quote / order session.
 *
 * @package Bolt\Boltpay\Controller\Order
 */
class Save extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /** @var CheckoutSession */
    protected $checkoutSession;

	/**
	 * @var OrderHelper
	 */
	protected $orderHelper;

	/**
	 * @var ConfigHelper
	 */
	protected $configHelper;

	/**
	 * @var Bugsnag
	 */
	protected $bugsnag;


	/**
	 * @param Context $context
	 * @param JsonFactory $resultJsonFactory
	 * @param CheckoutSession $checkoutSession
	 * @param OrderHelper $orderHelper
	 * @param ConfigHelper $configHelper
	 * @param Bugsnag $bugsnag
	 *
	 * @codeCoverageIgnore
	 */
    public function __construct(
        Context         $context,
        JsonFactory     $resultJsonFactory,
        CheckoutSession $checkoutSession,
	    OrderHelper     $orderHelper,
	    configHelper    $configHelper,
	    Bugsnag         $bugsnag
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->checkoutSession   = $checkoutSession;
	    $this->orderHelper       = $orderHelper;
	    $this->configHelper      = $configHelper;
	    $this->bugsnag           = $bugsnag;
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
			list($quote, $order) = $this->orderHelper->saveUpdateOrder($reference);

			$orderId = $order->getId();
			// clear the session data
			if($orderId){
				//Clear quote session
				$this->clearQuoteSession($quote);
				//Clear order session
				$this->clearOrderSession($order);
			}
			// return the success page redirect URL
			$result = new DataObject();
			$result->setData('success_url', $this->_url->getUrl($this->configHelper->getSuccessPageRedirect()));
			return $this->resultJsonFactory->create()->setData($result->getData());
		} catch ( Exception $e ) {
			$this->bugsnag->notifyException($e);
			throw $e;
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
