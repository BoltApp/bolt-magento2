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
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Exception\BoltException;

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
     * @var MetricsClient
     */
    private $metricsClient;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param CartHelper $cartHelper
     * @param ConfigHelper $configHelper
     * @param Bugsnag $bugsnag
     * @param MetricsClient $metricsClient
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CartHelper $cartHelper,
        ConfigHelper $configHelper,
        Bugsnag $bugsnag,
        MetricsClient $metricsClient
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cartHelper        = $cartHelper;
        $this->configHelper      = $configHelper;
        $this->bugsnag           = $bugsnag;
        $this->metricsClient   = $metricsClient;
    }

    /**
     * Get cart data for bolt pay ajax
     *
     * @return Json
     * @throws Exception
     */
    public function execute()
    {
        $startTime = $this->metricsClient->getCurrentTime();
        $result = $this->resultJsonFactory->create();

        try {
            if ($this->cartHelper->hasProductRestrictions()) {
                throw new BoltException(__('The cart has products not allowed for Bolt checkout'));
            }

            if (!$this->cartHelper->isCheckoutAllowed()) {
                throw new BoltException(__('Guest checkout is not allowed.'));
            }

            // flag to determinate the type of checkout / data sent to Bolt
            $payment_only = $this->getRequest()->getParam('payment_only');
            // additional data collected from the (one page checkout) page,
            // i.e. billing address to be saved with the order
            $place_order_payload = $this->getRequest()->getParam('place_order_payload');
            // call the Bolt API
            $boltpayOrder = $this->cartHelper->getBoltpayOrder($payment_only, $place_order_payload);

            // format and send the response
            $response = $boltpayOrder ? $boltpayOrder->getResponse() : null;

            if ($response) {
                $responseData = json_decode(json_encode($response), true);
                $this->metricsClient->processMetric("order_token.success", 1, "order_token.latency", $startTime);
            } else {
                $responseData['cart'] = [];
                $this->metricsClient->processMetric("order_token.failure", 1,"order_token.latency", $startTime);
            }

            // get immutable quote id stored with cart data
            list(, $cartReference) = $response ? explode(' / ', $responseData['cart']['display_id']) : [null, ''];

            $cart = array_merge($responseData['cart'], [
                'orderToken'    => $response ? $responseData['token'] : '',
                'cartReference' => $cartReference,
            ]);

            if (isset($cart['currency']['currency']) && $cart['currency']['currency']) {
                // cart data validation requirement
                $cart['currency']['currency_code'] = $cart['currency']['currency'];
            }

            $hints = $this->cartHelper->getHints($cartReference, 'cart');

            $result->setData([
                'status' => 'success',
                'cart' => $cart,
                'hints' => $hints,
                'backUrl' => '',
            ]);
        } catch (BoltException $e) {
            $result->setData([
                'status' => 'success',
                'restrict' => true,
                'message' => $e->getMessage(),
                'backUrl' => '',
            ]);
            $this->metricsClient->processMetric("order_token.failure", 1,"order_token.latency", $startTime);
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);

            $result->setData([
                'status' => 'failure',
                'message' => $e->getMessage(),
                'backUrl' => '',
            ]);
            $this->metricsClient->processMetric("order_token.failure", 1,"order_token.latency", $startTime);
        } finally {
            return $result;
        }
    }
}
