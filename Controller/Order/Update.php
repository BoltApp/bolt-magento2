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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Controller\Order;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Model\Api\CreateOrder as ModelApiCreateOrder;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class Save.
 * Converts / saves the quote into an order.
 * Updates the order payment/transaction info. Closes the quote / order session.
 *
 * @package Bolt\Boltpay\Controller\Order
 */
class Update extends Action
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
     * @var ModelApiCreateOrder
     */
    private $createOrderApi;
    private $cartHelper;

    /**
     * @param Context             $context
     * @param JsonFactory         $resultJsonFactory
     * @param CheckoutSession     $checkoutSession
     * @param OrderHelper         $orderHelper
     * @param ConfigHelper        $configHelper
     * @param Bugsnag             $bugsnag*
     * @param ModelApiCreateOrder $modelApiCreateOrder
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CheckoutSession $checkoutSession,
        OrderHelper $orderHelper,
        CartHelper $cartHelper,
        configHelper $configHelper,
        Bugsnag $bugsnag,
        ModelApiCreateOrder $modelApiCreateOrder
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->checkoutSession   = $checkoutSession;
        $this->orderHelper       = $orderHelper;
        $this->configHelper      = $configHelper;
        $this->bugsnag           = $bugsnag;
        $this->cartHelper = $cartHelper;
        $this->createOrderApi = $modelApiCreateOrder;
    }

    /**
     * @return Json
     * @throws Exception
     */
    public function execute()
    {
        try {
            HookHelper::$fromBolt = true;

//            $transaction = $this->getBoltRequestDemoData();
//
//            $requestType = $transaction->type;
//            if ($requestType !== 'order.update') {
//                throw new LocalizedException(__('Invalid hook type!'));
//            }


            // return the success page redirect URL
            $result = $this->resultJsonFactory->create();
            return $result->setData([
                'status'    => 'success',
                'message'   => 'order.update - in progress',

            ]);
        } catch (LocalizedException $e) {
            $this->bugsnag->notifyException($e);
            $result = $this->resultJsonFactory->create();
            $result->setHttpResponseCode(422);

            return $result->setData([
                'status' => 'error',
                'code' => 6009,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function getBoltRequestDemoData()
    {
        $request = '[
  {
    "type": "order.create",
    "order": {
      "token": "e1244801dda0778e95cb66258047b1102ebb0833177a6e1f1400820ed9bcc4aa",
      "cart": {
        "order_reference": "836",
        "display_id": "000000178 / 862",
        "currency": {
          "currency": "USD",
          "currency_symbol": "$"
        },
        "subtotal_amount": {
          "amount": 5400,
          "currency": "USD",
          "currency_symbol": "$"
        },
        "total_amount": {
          "amount": 5900,
          "currency": "USD",
          "currency_symbol": "$"
        },
        "tax_amount": {
          "amount": 0,
          "currency": "USD",
          "currency_symbol": "$"
        },
        "shipping_amount": {
          "amount": 500,
          "currency": "USD",
          "currency_symbol": "$"
        },
        "discount_amount": {
          "amount": 0,
          "currency": "USD",
          "currency_symbol": "$"
        },
        "billing_address": {
          "id": "AAjAjtZGQ4yGK",
          "street_address1": "228 7th Avenue",
          "locality": "New York",
          "region": "New York",
          "postal_code": "10011",
          "country_code": "US",
          "name": "YevhenBolt BoltTest",
          "first_name": "YevhenBolt",
          "last_name": "BoltTest",
          "phone_number": "1231231234",
          "email_address": "yevhen@bolt.com"
        },
        "items": [
          {
            "reference": "163",
            "name": "Hero Hoodie",
            "description": "Gray and black color blocking sets you apart as the Hero Hoodie keeps you warm on the bus, campus or cold mean streets. Slanted outsize front pockets keep your style real . . . convenient.\n&bull; Full-zip gray and black hoodie.&bull; Ribbed hem.&bull; Standard fit.&bull; Drawcord hood cinch.&bull; Water-resistant coating.",
            "total_amount": {
              "amount": 5400,
              "currency": "USD",
              "currency_symbol": "$"
            },
            "unit_price": {
              "amount": 5400,
              "currency": "USD",
              "currency_symbol": "$"
            },
            "quantity": 1,
            "sku": "MH07-L-Green",
            "image_url": "https://bolt-magento2-0.guaranteed.site/pub/media/catalog/product/cache/a8be410535bd10951f8c121e888302ad/m/h/mh07-gray_main.jpg",
            "type": "physical",
            "properties": [
              {
                "name": "Size",
                "value": "L"
              },
              {
                "name": "Color",
                "value": "Green"
              }
            ]
          }
        ],
        "shipments": [
          {
            "shipping_address": {
              "id": "AAbsPrgWzgq2r",
              "street_address1": "228 7th Avenue",
              "locality": "New York",
              "region": "New York",
              "postal_code": "10011",
              "country_code": "US",
              "country": "United States",
              "name": "YevhenBolt BoltTest",
              "first_name": "YevhenBolt",
              "last_name": "BoltTest",
              "phone_number": "1231231234",
              "email_address": "yevhen@bolt.com"
            },
            "shipping_method": "unknown",
            "service": "Flat Rate - Fixed",
            "cost": {
              "amount": 500,
              "currency": "USD",
              "currency_symbol": "$"
            },
            "tax_amount": {
              "amount": 0,
              "currency": "USD",
              "currency_symbol": "$"
            },
            "reference": "flatrate_flatrate"
          }
        ]
      }
    },
    "currency": "USD"
  }
]';

        $resultRequest = json_decode($request);
        $result = $resultRequest[0];

        return $result;
    }
}
