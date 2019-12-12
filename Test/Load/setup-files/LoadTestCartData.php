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

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\LoadTestCartDataInterface;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\Webapi\Rest\Request;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Catalog\Api\ProductRepositoryInterface as ProductRepository;
use Magento\Catalog\Model\Product as Product;
use Magento\Quote\Model\QuoteFactory as QuoteFactory;
use Magento\Quote\Api\Data\CartItemInterfaceFactory as CartItemInterfaceFactory;
use Magento\Checkout\Model\Session as CheckoutSession;

use Magento\Quote\Model\Quote;

/**
 * Class CartData
 * Web hook endpoint. Load Test.
 *
 * @package Bolt\Boltpay\Model\Api
 */
class LoadTestCartData implements LoadTestCartDataInterface
{
    const E_BOLT_GENERAL_ERROR = 2001001;

    /**
     * @var HookHelper
     */
    private $hookHelper;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /*
     * @var CheckoutSession
     */
    private $checkoutSession;

    /*
     * @var ProductRepository
     */
    private $productRepository;

    /*
     * @var Product
     */
    private $product;

    /*
     * @var QuoteFactory
     */
    private $quoteFactory;

    /*
     * @var CartItemInterfaceFactory
     */
    private $cartItemFactory;

    /**
     * @param HookHelper                $hookHelper
     * @param CartHelper                $cartHelper
     * @param LogHelper                 $logHelper
     * @param Request                   $request
     * @param Response                  $response
     * @param ProductRepository         $productRepository
     * @param CheckoutSession           $checkoutSession
     * @param CartItemInterfaceFactory  $cartItemFactory
     * @param Product                   $product
     * @param QuoteFactory              $quoteFactory
     */
    public function __construct(
        HookHelper $hookHelper,
        CartHelper $cartHelper,
        LogHelper $logHelper,
        Request $request,
        Response $response,
        ProductRepository $productRepository,
        CheckoutSession $checkoutSession,
        CartItemInterfaceFactory $cartItemFactory,
        Product $product,
        QuoteFactory $quoteFactory
    )
    {
        $this->product = $product;
        $this->quoteFactory = $quoteFactory;
        $this->checkoutSession = $checkoutSession;
        $this->hookHelper = $hookHelper;
        $this->logHelper = $logHelper;
        $this->request = $request;
        $this->response = $response;
        $this->cartHelper = $cartHelper;
        $this->productRepository = $productRepository;
        $this->cartItemFactory = $cartItemFactory;
    }

    /**
     * Load-test hook: Populate cart and create bolt order.
     *
     * @api
     * @inheritDoc
     * @see CartDataInterface
     */
    public function execute($cart = null) {
        try {
            $payload = $this->request->getContent();

            $this->logHelper->addInfoLog('[-= LoadTest endpoint =-]');
            $this->logHelper->addInfoLog($payload);

            if (empty($cart)) {
                throw new BoltException(
                    __('Missing cart data.'),
                    null,
                    self::E_BOLT_GENERAL_ERROR
                );
            }

            $this->preProcessWebhook();

            // Create cart with the given products
            $quote = $this->quoteFactory->create();
            foreach ($cart as $item) {
                $product = $this->productRepository->get($item["sku"]);
                $quoteItem = $this->cartItemFactory->create();
                $quoteItem->setProduct($product);
                $quoteItem->setPrice($product->getPrice());
                $quote->addItem($quoteItem);
                $quote->addProduct($product);
            }
            $quote->collectTotals()->save();

            // Save the cart to the checkout session
            $this->checkoutSession->replaceQuote($quote);

            // Create the order on Bolt
            $boltOrderResponse = $this->cartHelper->getBoltpayOrder(false, "")->getResponse();
            $orderToken = $boltOrderResponse->token;
            $orderReference = $boltOrderResponse->cart->order_reference;
            $this->sendResponse(200, [
                'order_token' => $orderToken,
                'order_reference' => $orderReference
            ]);
        } catch (\Exception $e) {
            $this->sendResponse(422, [
                'status' => 'failure',
                'error' => [[
                    'code' => self::E_BOLT_GENERAL_ERROR,
                    'data' => [[
                        'reason' => $e->getMessage(),
                    ]]
                ]]
            ]);
        } finally {
            $this->response->sendResponse();
        }
    }

    /**
     * @param null $storeId
     * @throws LocalizedException
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function preProcessWebhook($storeId = null)
    {
        HookHelper::$fromBolt = true;

        $this->hookHelper->preProcessWebhook($storeId);
    }

    /**
     * @param int   $code
     * @param array $body
     */
    public function sendResponse($code, array $body)
    {
        $this->response->setHttpResponseCode($code);
        $this->response->setBody(json_encode($body));
    }

}
