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

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObjectFactory;
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
     * @var OrderHelper
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
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param CheckoutSession $checkoutSession
     * @param OrderHelper $orderHelper
     * @param ConfigHelper $configHelper
     * @param Bugsnag $bugsnag
     * @param DataObjectFactory $dataObjectFactory
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CheckoutSession $checkoutSession,
        OrderHelper $orderHelper,
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
            $sessionQuote = $this->checkoutSession->getQuote();
            // get the transaction reference parameter
            $reference = $this->getRequest()->getParam('reference');
            // call order save and update
            list($quote, $order) = $this->orderHelper->saveUpdateOrder($reference);
            if ($sessionQuote && $sessionQuote->getId() != $quote->getId()) {
                // If we had quote linked to session and it's not immutableQuote
                // then we in product page, non-pre-auth checkout flow
                // Session quote represents regular cart and we want to save it and restore after order creation
                $this->replaceQuote($sessionQuote);
            } else {
                // clear the session data
                $this->replaceQuote($quote);
            }
            //Clear quote session
            $this->clearQuoteSession($quote);
            //Clear order session
            $this->clearOrderSession($order);

            // return the success page redirect URL
            $result = $this->resultJsonFactory->create();
            return $result->setData([
                'status' => 'success',
                'success_url' => $this->_url->getUrl($this->configHelper->getSuccessPageRedirect()),
            ]);
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            $result = $this->resultJsonFactory->create();
            $result->setHttpResponseCode(422);
            return $result->setData([
                'status' => 'error',
                'code' => 6009,
                'message' => $e->getMessage(),
                'reference' => $reference,
            ]);
        }
    }

    /**
     * @param Quote $quote
     * @return void
     */
    private function replaceQuote($quote)
    {
        $this->checkoutSession->replaceQuote($quote);
    }

    /**
     * Clear quote session after successful order
     *
     * @param Quote $quote
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
     * @param Order $order
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
