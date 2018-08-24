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

namespace Bolt\Boltpay\Controller\Cart;

use Bolt\Boltpay\Helper\Cart as CartHelper;
use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
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
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param CartHelper $cartHelper
     * @param ConfigHelper $configHelper
     * @param Bugsnag $bugsnag
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CartHelper $cartHelper,
        ConfigHelper $configHelper,
        Bugsnag $bugsnag
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cartHelper        = $cartHelper;
        $this->configHelper      = $configHelper;
        $this->bugsnag           = $bugsnag;
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
            $response = $boltpayOrder ? $boltpayOrder->getResponse() : null;

            // get immutable quote id stored with cart data
            list(, $cartReference) = $response ? explode(' / ', $response->cart->display_id) : [null, ''];

            $cart = [
                'orderToken'  => $response ? $response->token : '',
                'authcapture' => $this->configHelper->getAutomaticCaptureMode(),
                'cartReference' => $cartReference,
            ];

            $hints = $this->cartHelper->getHints($place_order_payload, $cartReference);

            $result = $this->resultJsonFactory->create();

            return $result->setData([
                'status' => 'success',
                'cart'   => $cart,
                'hints'  => $hints,
            ]);

        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }
    }
}
