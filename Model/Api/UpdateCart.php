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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebApiException;
use Bolt\Boltpay\Api\UpdateCartInterface;
use Bolt\Boltpay\Model\Api\UpdateCartCommon;
use Bolt\Boltpay\Model\Api\UpdateCartContext;
use Bolt\Boltpay\Model\Api\UpdateDiscountTrait;
use Bolt\Boltpay\Model\Api\UpdateCartItemTrait;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Api\Data\CartDataInterfaceFactory;
use Bolt\Boltpay\Api\Data\UpdateCartResultInterfaceFactory;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Exception\BoltException;

/**
 * Class UpdateCart
 *
 * @package Bolt\Boltpay\Model\Api
 */
class UpdateCart extends UpdateCartCommon implements UpdateCartInterface
{
    use UpdateDiscountTrait { UpdateDiscountTrait::__construct as private UpdateDiscountTraitConstructor; }

    use UpdateCartItemTrait { UpdateCartItemTrait::__construct as private UpdateCartItemTraitConstructor; }

    /**
     * @var CartDataInterfaceFactory
     */
    protected $cartDataFactory;

    /**
     * @var UpdateCartResultInterfaceFactory
     */
    protected $updateCartResultFactory;

    /**
     * @var array
     */
    protected $cartRequest;


    /**
     * UpdateCart constructor.
     *
     * @param UpdateCartContext $updateCartContext
     * @param CartDataInterfaceFactory $cartDataFactory
     * @param UpdateCartResultInterfaceFactory $updateCartResultFactory
     */
    public function __construct(
        UpdateCartContext $updateCartContext,
        CartDataInterfaceFactory $cartDataFactory,
        UpdateCartResultInterfaceFactory $updateCartResultFactory
    ) {
        parent::__construct($updateCartContext);
        $this->UpdateDiscountTraitConstructor($updateCartContext);
        $this->UpdateCartItemTraitConstructor($updateCartContext);
        $this->cartDataFactory = $cartDataFactory;
        $this->updateCartResultFactory = $updateCartResultFactory;
    }

    /**
     * Update cart with items and discounts.
     *
     * @api
     * @param mixed $cart
     * @param mixed $add_items
     * @param mixed $remove_items
     * @param mixed $discount_codes_to_add
     * @param mixed $discount_codes_to_remove
     * @return \Bolt\Boltpay\Api\Data\UpdateCartResultInterface
     */
    public function execute($cart, $add_items = null, $remove_items = null, $discount_codes_to_add = null, $discount_codes_to_remove = null)
    {
        try {
            $this->cartRequest = $cart;

            // Bolt server sends immutableQuoteId as order reference
            $immutableQuoteId = $cart['order_reference'];

            $result = $this->validateQuote($immutableQuoteId);
            list($parentQuote, $immutableQuote) = $result;

            $storeId = $parentQuote->getStoreId();
            $websiteId = $parentQuote->getStore()->getWebsiteId();

            $this->preProcessWebhook($storeId);

            $parentQuote->getStore()->setCurrentCurrencyCode($parentQuote->getQuoteCurrencyCode());

            $this->updateSession($parentQuote, $cart['metadata'] ?? []);

            if (!empty($cart['shipments'][0]['reference'])) {
                $this->setShipment($cart['shipments'][0], $immutableQuote);
                $this->setShipment($cart['shipments'][0], $parentQuote);
            }

            // Add discounts
            if( !empty($discount_codes_to_add) ){
                // Get the coupon code
                $discount_code = $discount_codes_to_add[0];
                $couponCode = trim($discount_code);

                $result = $this->verifyCouponCode($couponCode, $parentQuote);

                list($coupon, $giftCard) = $result;

                $result = $this->applyDiscount($couponCode, $coupon, $giftCard, $parentQuote);

                if (!$result) {
                    // Already sent a response with error, so just return.
                    return false;
                }
            }

            // Remove discounts
            if( !empty($discount_codes_to_remove) ){
                $discount_code = $discount_codes_to_remove[0];
                $couponCode = trim($discount_code);

                $discounts = $this->getAppliedStoreCredit($couponCode, $parentQuote);

                if (!$discounts) {
                    $quoteCart = $this->getQuoteCart($parentQuote);
                    $discounts = $quoteCart['discounts'];
                }

                if(empty($discounts)){
                    $this->sendErrorResponse(
                        BoltErrorResponse::ERR_CODE_INVALID,
                        'Coupon code does not exist!',
                        422,
                        $parentQuote
                    );
                    return false;
                }

                $discounts = array_column($discounts, 'discount_category', 'reference');

                // This throws /Exception now instead of sending a response. This method already has a catch
                // and handling of this exception type.
                $result = $this->removeDiscount($couponCode, $discounts, $parentQuote, $websiteId, $storeId);

                // This should never evaluate to true, leaving this in here until we refactor this handler
                // to use helper methods and centralize the sending of error responses.
                if (!$result) {
                    // Already sent a response with error, so just return.
                    return false;
                }
            }

            // Add items
            if ( !empty($add_items) ) {
                $cartItems = $this->getCartItems($parentQuote);

                foreach ($add_items as $addItem) {
                    $product = $this->getProduct($addItem['product_id'], $storeId);
                    if (!$product) {
                        // Already sent a response with error, so just return.
                        return false;
                    }

                    $quoteItem = $this->getQuoteItemByProduct($addItem, $cartItems);

                    if (!isset($addItem['currency'])) {
                        $addItem['currency'] = $parentQuote->getQuoteCurrencyCode();
                    }
                    $addItem['update'] = 'add';
                    $result = $this->verifyItemData($product, $addItem, $quoteItem, $websiteId);
                    if (!$result) {
                        // Already sent a response with error, so just return.
                        return false;
                    }

                    $result = $this->addItemToQuote($product, $parentQuote, $addItem, $quoteItem);
                    if (!$result) {
                        // Already sent a response with error, so just return.
                        return false;
                    }
                }

                $this->updateTotals($parentQuote);
            }

            // Remove items
            if ( !empty($remove_items) ) {
                $cartItems = $this->getCartItems($parentQuote);

                foreach ($remove_items as $removeItem) {
                    $quoteItem = $this->getQuoteItemByProduct($removeItem, $cartItems);
                    if (!$quoteItem) {
                        $this->sendErrorResponse(
                            BoltErrorResponse::ERR_CART_ITEM_ADD_FAILED,
                            sprintf('The product [%s] does not exist in the quote.', $removeItem['product_id']),
                            422
                        );

                        return false;
                    }

                    $product = $this->getProduct($removeItem['product_id'], $storeId);
                    if (!$product) {
                        // Already sent a response with error, so just return.
                        return false;
                    }

                    $removeItem['update'] = 'remove';
                    $result = $this->verifyItemData($product, $removeItem, $quoteItem, $websiteId);
                    if (!$result) {
                        // Already sent a response with error, so just return.
                        return false;
                    }

                    $result = $this->removeItemFromQuote($quoteItem, $removeItem, $parentQuote);
                    if (!$result) {
                        // Already sent a response with error, so just return.
                        return false;
                    }
                }

                $this->updateTotals($parentQuote);
            }

            $this->cartHelper->replicateQuoteData($parentQuote, $immutableQuote);

            $this->cache->clean([\Bolt\Boltpay\Helper\Cart::BOLT_ORDER_TAG . '_' . $parentQuote->getId()]);

            $result = $this->generateResult($immutableQuote);

            //this is only true for universal api requests which requires a different format of response.
            //TODO: MA-493 refactoring will have this return an interface
            if (isset($this->request->getBodyParams()['data'])) {
                $result = [
                    'event' => "cart.update",
                    'status' => "success",
                    'data' => [
                        'order_create' => $result['order_create']
                    ]
                ];
            }
            $this->sendSuccessResponse($result);

        } catch (BoltException $e) {
            $this->sendErrorResponse(
                $e->getCode(),
                $e->getMessage(),
                422
            );

            return false;
        } catch (WebApiException $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                $e->getHttpCode(),
                ($immutableQuote) ? $immutableQuote : null
            );

            return false;
        } catch (LocalizedException $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                500
            );

            return false;
        } catch (\Exception $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                500
            );

            return false;
        }

        return true;
    }

    /**
     * Handles discounts.code.apply event type in universal api
     *
     * This is mostly just pulled from UpdateCart::execute as all the functionality is there
     * but discounts.code.apply requires a specific subset of the functionality in execute
     *
     * @api
     * @param string $discount_code
     * @param mixed $cart
     * @param string $customer_name
     * @param string $customer_email
     * @param int $customer_phone
     * @return void
     */
    public function discountHandler($discount_code, $cart, $customer_name = null, $customer_email = null, $customer_phone = null)
    {
        // Bolt server sends immutableQuoteId as metadata
        $immutableQuoteId = $cart['metadata']['immutable_quote_id'];

        $result = $this->validateQuote($immutableQuoteId);
        list($parentQuote, $immutableQuote) = $result;

        $storeId = $parentQuote->getStoreId();
        $websiteId = $parentQuote->getStore()->getWebsiteId();

        $this->preProcessWebhook($storeId);

        $parentQuote->getStore()->setCurrentCurrencyCode($parentQuote->getQuoteCurrencyCode());

        // Load logged in customer checkout and customer sessions from cached session id.
        // Replace the quote with $parentQuote in checkout session.
        $this->sessionHelper->loadSession($parentQuote, $cart['metadata'] ?? []);
        $this->cartHelper->resetCheckoutSession($this->sessionHelper->getCheckoutSession());

        if(!empty($discount_code)){
            // Get the coupon code
            $couponCode = trim($discount_code);

            $result = $this->verifyCouponCode($couponCode, $parentQuote);

            list($coupon, $giftCard) = $result;

            $result = $this->applyDiscount($couponCode, $coupon, $giftCard, $parentQuote);
        }

        $this->cartHelper->replicateQuoteData($parentQuote, $immutableQuote);

        $this->cache->clean([\Bolt\Boltpay\Helper\Cart::BOLT_ORDER_TAG . '_' . $parentQuote->getId()]);

        //need to rename one of the keys of the result array for universal api
        $result['discount_description'] = $result['description'];
        unset($result['description']);
        unset($result['status']);

        $responseBody = [
            'event' => 'discounts.code.apply',
            'status' => 'success',
            'data' => $result
        ];

        $this->sendSuccessResponse($responseBody);

    }

    /**
     * @param Quote $quote
     * @return array
     * @throws \Exception
     */
    protected function getQuoteCart($quote)
    {
        $has_shipment = !empty($this->cartRequest['shipments'][0]['reference']);
        //make sure we recollect totals
        $quote->setTotalsCollectedFlag(false);
        $cart = $this->cartHelper->getCartData($has_shipment, null, $quote);
        if (empty($cart)) {
            throw new \Exception('Something went wrong when getting cart data.');
        }
        return $cart;
    }

    /**
     * @param int        $errCode
     * @param string     $message
     * @param int        $httpStatusCode
     * @param null|Quote $quote
     *
     * @return void
     * @throws \Exception
     */
    protected function sendErrorResponse($errCode, $message, $httpStatusCode, $quote = null)
    {
        $additionalErrorResponseData = [];
        if ($quote) {
            $additionalErrorResponseData = $this->getQuoteCart($quote);
        }

        $encodeErrorResult = $this->errorResponse
            ->prepareUpdateCartErrorMessage($errCode, $message, $additionalErrorResponseData);

        $this->logHelper->addInfoLog('### sendErrorResponse');
        $this->logHelper->addInfoLog($encodeErrorResult);

        $this->bugsnag->notifyException(new \Exception($message));

        $this->response->setHttpResponseCode($httpStatusCode);
        $this->response->setBody($encodeErrorResult);
        $this->response->sendResponse();
    }

    /**
     * @param array $result
     * @param Quote $quote
     * @return array
     * @throws \Exception
     */
    protected function sendSuccessResponse($result, $quote = null)
    {
        $result = str_replace(array("\r\n", "\n", "\r"), ' ', json_encode($result));
        $this->logHelper->addInfoLog('### sendSuccessResponse');
        $this->logHelper->addInfoLog($result);
        $this->logHelper->addInfoLog('=== END ===');

        $this->response->setBody($result);
        $this->response->sendResponse();
    }

    /**
     * @param Quote $quote
     * @param array $cart
     * @return UpdateCartResultInterface
     * @throws \Exception
     */
    public function generateResult($quote)
    {
        $cartData = $this->cartDataFactory->create();
        $quoteCart = $this->getQuoteCart($quote);

        $cartData->setDisplayId($quoteCart['display_id']);
        $cartData->setCurrency($quoteCart['currency']);
        $cartData->setItems($quoteCart['items']);
        $cartData->setDiscounts($quoteCart['discounts']);
        $cartData->setTotalAmount($quoteCart['total_amount']);
        $cartData->setTaxAmount($quoteCart['tax_amount']);
        $cartData->setOrderReference($quoteCart['order_reference']);
        $cartData->setShipments( (!empty($quoteCart['shipments'])) ? $quoteCart['shipments'] : [] );

        $updateCartResult = $this->updateCartResultFactory->create();
        $updateCartResult->setOrderCreate($cartData);
        $updateCartResult->setOrderReference($quoteCart['order_reference']);
        $updateCartResult->setStatus('success');

        return $updateCartResult->getCartResult();
    }

}
