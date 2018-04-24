<?php
/**
 *
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Bolt\Boltpay\Controller\Cart;

use Bolt\Boltpay\Helper\Cart as CartHelper;
use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\DataObjectFactory;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * Class Data.
 * Create Bolt order controller.
 *
 * Called from the replace.phtml javascript block on checklout button click.
 *
 * @package Bolt\Boltpay\Controller\Cart
 */
class Data extends Action
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var CartHelper
     */
    private $cartHelper;

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
     * @param CartHelper $cartHelper
     * @param ConfigHelper $configHelper
     * @param Bugsnag $bugsnag
     * @param DataObjectFactory $dataObjectFactory
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CartHelper $cartHelper,
        ConfigHelper $configHelper,
        Bugsnag $bugsnag,
        DataObjectFactory $dataObjectFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cartHelper        = $cartHelper;
        $this->configHelper      = $configHelper;
        $this->bugsnag           = $bugsnag;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * Get cart data for bolt pay ajax
     *
     * @return Json
     * @throws Exception
     */
    public function execute()
    {
        try {
            // flag to determinate the type of checkout / data sent to Bolt
            $payment_only        = $this->getRequest()->getParam('payment_only');
            // additional data collected from the (one page checkout) page,
            // i.e. billing address to be saved with the order
            $place_order_payload = $this->getRequest()->getParam('place_order_payload');
            // call the Bolt API
            $boltpayOrder = $this->cartHelper->getBoltpayOrder($payment_only, $place_order_payload);

            // format and send the response
            $cart = [
                'orderToken'  => $boltpayOrder ? $boltpayOrder->getResponse()->token : '',
                'authcapture' => $this->configHelper->getAutomaticCaptureMode()
            ];

            $hints = $this->cartHelper->getHints($place_order_payload);

            $result = $this->dataObjectFactory->create();
            $result->setData('cart', $cart);
            $result->setData('hints', $hints);

            return $this->resultJsonFactory->create()->setData($result->getData());
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }
    }
}
