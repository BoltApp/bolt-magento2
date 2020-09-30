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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebApiException;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\SalesRule\Model\Coupon;
use Magento\Catalog\Api\Data\ProductInterface;
use Bolt\Boltpay\Api\UpdateCartInterface;
use Bolt\Boltpay\Api\Data\CartDataInterface;
use Bolt\Boltpay\Api\Data\UpdateCartResultInterface;
use Bolt\Boltpay\Model\Api\UpdateCartCommon;
use Bolt\Boltpay\Model\Api\UpdateCartContext;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Api\Data\CartDataInterfaceFactory;
use Bolt\Boltpay\Api\Data\UpdateCartResultInterfaceFactory;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Model\Api\UpdateCart;
use Bolt\Boltpay\Model\Api\UpdateDiscountTrait;
use Bolt\Boltpay\Test\Unit\TestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;


/**
 * Class UpdateCartTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\UpdateCart
 */
class UpdateCartTest extends TestCase
{
    const PARENT_QUOTE_ID = "1000";
    const IMMUTABLE_QUOTE_ID = "1001";
    const RULE_ID = 6;
    const COUPON_ID = 5;
    const WEBSITE_ID = 1;
    const CUSTOMER_ID = 100;
    const USAGE_LIMIT = 100;
    const COUPON_CODE = 'TEST_COUPON';
    const STORE_ID = 1;
    
    /**
     * @var CartDataInterfaceFactory|MockObject
     */
    private $cartDataFactory;

    /**
     * @var UpdateCartResultInterfaceFactory|MockObject
     */
    private $updateCartResultFactory;
    
    /**
     * @var array
     */
    private $cartRequest;
    
    /**
     * @var SessionHelper|MockObject
     */
    private $sessionHelper;
    
    /**
     * @var UpdateCartContext|MockObject
     */
    private $updateCartContext;
    
    /**
     * @var BoltErrorResponse|MockObject
     */
    private $errorResponse;
    
    /**
     * @var Response|MockObject
     */
    private $response;
    
    /**
     * @var LogHelper|MockObject
     */
    private $logHelper;
    
    /**
     * @var Coupon|MockObject
     */
    private $couponMock;
    
    /**
     * @var CartHelper|MockObject
     */
    private $cartHelper;

    /**
     * @var UpdateCart|MockObject
     */
    private $currentMock;

    protected function setUp()
    {
        $this->updateCartContext = $this->getMockBuilder(UpdateCartContext::class)
            ->setMethods(['getSessionHelper'])
            ->disableOriginalConstructor()
            ->getMock();        

        $this->sessionHelper = $this->createMock(SessionHelper::class);
        $this->cartDataFactory = $this->createMock(CartDataInterfaceFactory::class);
        $this->updateCartResultFactory = $this->createMock(UpdateCartResultInterfaceFactory::class);
        $this->response = $this->createMock(Response::class);
        $this->errorResponse = $this->createMock(BoltErrorResponse::class);
        $this->logHelper = $this->createMock(LogHelper::class);
    }

    /**
     * @param array $methods
     * @param bool $enableOriginalConstructor
     * @param bool $enableProxyingToOriginalMethods
     */
    private function initCurrentMock(
        $methods = [],
        $sessionHelper = null,
        $cartDataFactory = null,
        $updateCartResultFactory = null,
        $enableProxyingToOriginalMethods = false,
        $enableOriginalConstructor = true
    ) {
        if(!$sessionHelper) {
            $sessionHelper = $this->sessionHelper;
        }
        $this->updateCartContext->method('getSessionHelper')->willReturn($sessionHelper);
        
        if(!$cartDataFactory) {
            $cartDataFactory = $this->cartDataFactory;
        }
        if(!$updateCartResultFactory) {
            $updateCartResultFactory = $this->updateCartResultFactory;
        }
        
        $builder = $this->getMockBuilder(UpdateCart::class)
            ->setConstructorArgs(
                [
                    $this->updateCartContext,
                    $cartDataFactory,
                    $updateCartResultFactory
                ]
            )
            ->setMethods($methods);

        if ($enableOriginalConstructor) {
            $builder->enableOriginalConstructor();
        } else {
            $builder->disableOriginalConstructor();
        }

        if ($enableProxyingToOriginalMethods) {
            $builder->enableProxyingToOriginalMethods();
        } else {
            $builder->disableProxyingToOriginalMethods();
        }

        $this->currentMock = $builder->getMock();
        
        $this->response = $this->createMock(Response::class);
        $this->errorResponse = $this->createMock(BoltErrorResponse::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        
        $this->couponMock = $this->getMockBuilder(Coupon::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $this->cartHelper = $this->getMockBuilder(CartHelper::class)
            ->setMethods(
                [
                    'replicateQuoteData',
                    'resetCheckoutSession',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        
        TestHelper::setProperty($this->currentMock, 'response', $this->response);
        TestHelper::setProperty($this->currentMock, 'errorResponse', $this->errorResponse);
        TestHelper::setProperty($this->currentMock, 'logHelper', $this->logHelper);
        TestHelper::setProperty($this->currentMock, 'cartHelper', $this->cartHelper);
    }
    
    /**
     * Get quote mock with quote items
     *
     * @param $couponCode
     * @param $shippingAddress
     * @param $customerId
     * @param bool $isVirtual
     * @param int $quoteId
     * @param int $parentQuoteId
     * @return MockObject
     * @throws \ReflectionException
     * @oaram $couponCode
     */
    private function getQuoteMock(
        $quoteId = self::IMMUTABLE_QUOTE_ID,
        $parentQuoteId = self::PARENT_QUOTE_ID,
        $customerId = null,
        $isVirtual = false,        
        $couponCode = null
    ) {
        $quoteItem = $this->getMockBuilder(\Magento\Quote\Model\Quote\Item::class)
            ->setMethods(['getSku', 'getQty'])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteItem->method('getSku')->willReturn('TestProduct');
        $quoteItem->method('getQty')->willReturn(1);

        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(
                [
                    'getId',
                    'getBoltParentQuoteId',
                    'getSubtotal',
                    'getAllVisibleItems',
                    'getAppliedRuleIds',
                    'isVirtual',
                    'collectTotals',
                    'getQuoteCurrencyCode',
                    'getItemsCount',
                    'getCustomerId',
                    'setCouponCode',
                    'getCouponCode',
                    'getStoreId',
                    'getStore',
                    'getWebsiteId',
                    'save',
                    'getGiftCardsAmount',
                    'setCurrentCurrencyCode'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $quote->method('getId')->willReturn($quoteId);
        $quote->method('getBoltParentQuoteId')->willReturn($parentQuoteId);
        $quote->method('getSubtotal')->willReturn(100);
        $quote->method('getAllVisibleItems')->willReturn([$quoteItem]);
        $quote->method('getAppliedRuleIds')->willReturn('2,3');
        $quote->method('isVirtual')->willReturn($isVirtual);
        $quote->method('getQuoteCurrencyCode')->willReturn('USD');
        $quote->method('collectTotals')->willReturnSelf();
        $quote->method('getItemsCount')->willReturn(1);
        $quote->method('getCustomerId')->willReturn($customerId);
        $quote->expects(self::any())->method('setCouponCode')->willReturnSelf();
        $quote->method('getCouponCode')->willReturn($couponCode);
        $quote->method('getStoreId')->willReturn(self::STORE_ID);
        $quote->method('getStore')->willReturnSelf();
        $quote->method('getWebsiteId')->willReturn(self::WEBSITE_ID);
        $quote->method('save')->willReturnSelf();
        $quote->method('setCurrentCurrencyCode')->with('USD')->willReturnSelf();

        return $quote;
    }
    
    private function getShippingAddr()
    {
        return [
            'company'         => '',
            'country'         => 'United States',
            'country_code'    => 'US',
            'first_name'      => 'Bolt',
            'last_name'       => 'Test',
            'locality'        => 'New York',
            'phone'           => '+1 231 231 1234',
            'postal_code'     => '10001',
            'region'          => 'New York',
            'street_address1' => '228 5th Avenue',
            'street_address2' => '',
            'email_address'   => 'test@bolt.com',
        ];
    }
    
    private function getShipments()
    {
        return [
            'shipping_address' => $this->getShippingAddr(),
            'shipping_method' => 'unknown',
            'service' => 'Flat Rate - Fixed',
            'cost' => [
                'amount' => 500,
                'currency' => 'USD',
                'currency_symbol' => '$',
            ],
            'tax_amount' => [
                'amount' => 0,
                'currency' => 'USD',
                'currency_symbol' => '$',
            ],
            'reference' => 'flatrate_flatrate',
        ];
    }
    
    private function getRequestCart()
    {        
        $request_cart = [
            'order_reference' => self::IMMUTABLE_QUOTE_ID,
            'display_id'  => '',
            'shipments' => [
                0 => $this->getShipments(),
            ],
            'add_items' => [],
            'remove_items' => [],
        ];
        
        return $request_cart;
    }
    
    /**
     * Returns test cart data
     *
     * @return array
     */
    private function getTestCartData()
    {
        return [
            'order_reference' => self::PARENT_QUOTE_ID,
            'display_id' => '',
            'currency' => 'USD',
            'total_amount' => 50500,
            'tax_amount' => 1000,            
            'items' => [
                [
                    'name'         => 'Beaded Long Dress',
                    'reference'    => 101,
                    'total_amount' => 50000,
                    'unit_price'   => 50000,
                    'quantity'     => 1,
                    'image_url'    => 'https://images.example.com/dress.jpg',
                    'type'         => 'physical',
                    'properties'   =>
                        [
                            [
                                'name'  => 'color',
                                'value' => 'blue',
                            ],
                        ],
                ],
            ],
            'discounts' => [],
            'shipments' => [
                0 => $this->getShipments(),
            ],
        ];
    }

    private function expectErrorResponse($errCode, $message, $httpStatusCode, $quote = null)
    {
        $additionalErrorResponseData = [];        
        $encodeErrorResult = '';
        $this->errorResponse->expects(self::once())->method('prepareUpdateCartErrorMessage')
            ->with($errCode, $message, $additionalErrorResponseData)->willReturn($encodeErrorResult);
        $this->response->expects(self::once())->method('setHttpResponseCode')->with($httpStatusCode);
        $this->response->expects(self::once())->method('setBody')->with($encodeErrorResult);
        $this->response->expects(self::once())->method('sendResponse');
    }

    private function expectSuccessResponse($result)
    {
        $this->response->expects(self::once())->method('setBody')->with(json_encode($result));
        $this->response->expects(self::once())->method('sendResponse');
    }

    /**
     * @test
     * that sets internal properties
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $this->initCurrentMock();

        $this->assertAttributeInstanceOf(
            CartDataInterfaceFactory::class,
            'cartDataFactory',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            UpdateCartResultInterfaceFactory::class,
            'updateCartResultFactory',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            SessionHelper::class,
            'sessionHelper',
            $this->currentMock
        );
    }
    
    /**
     * @test
     * that execute would return false if validate quote fails
     *
     * @covers ::execute
     */
    public function execute_validateQuoteFail_returnFalse()
    {            
        $this->initCurrentMock(['validateQuote']);
        
        $this->currentMock->expects(self::once())->method('validateQuote')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn(false);
        
        $requestCart = $this->getRequestCart();

        $this->assertFalse($this->currentMock->execute($requestCart));
    }
    
    /**
     * @test
     * that execute would send success response and return true if add coupon successfully
     *
     * @covers ::execute
     */
    public function execute_addCoupon_sendSuccessResponse_returnTrue()
    {
        $requestCart = $this->getRequestCart();
        $discount_codes_to_add = [self::COUPON_CODE];
        
        $parentQuoteMock = $this->getQuoteMock(
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID            
        );
        
        $sessionHelper = $this->getMockBuilder(SessionHelper::class)
            ->setMethods(['loadSession','getCheckoutSession'])
            ->disableOriginalConstructor()
            ->getMock();
        $sessionHelper->expects(self::once())->method('loadSession')->with($parentQuoteMock);
        
        $checkoutSession = $this->createMock(CheckoutSession::class);
        $sessionHelper->expects(self::once())->method('getCheckoutSession')
            ->willReturn($checkoutSession);

        $this->initCurrentMock([
            'validateQuote',
            'preProcessWebhook',
            'setShipment',
            'generateResult',
            'verifyCouponCode',
            'applyDiscount'
        ], $sessionHelper);        
        
        $immutableQuoteMock = $this->getQuoteMock();
        
        $this->currentMock->expects(self::once())->method('validateQuote')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn([$parentQuoteMock,$immutableQuoteMock]);
            
        $this->currentMock->expects(self::once())->method('preProcessWebhook')
            ->with(self::STORE_ID);
            
        $this->currentMock->expects($this->exactly(2))
            ->method('setShipment')
            ->withConsecutive(
                [$requestCart['shipments'][0], $immutableQuoteMock],
                [$requestCart['shipments'][0], $parentQuoteMock]
            );

        $this->currentMock->expects(self::once())->method('verifyCouponCode')
            ->with(self::COUPON_CODE, self::WEBSITE_ID, self::STORE_ID)
            ->willReturn([$this->couponMock, null]);
        
        $this->currentMock->expects(self::once())->method('applyDiscount')
            ->with(self::COUPON_CODE, $this->couponMock, null, $parentQuoteMock)
            ->willReturn(true);
        
        $this->cartHelper->expects(self::once())->method('resetCheckoutSession')
            ->with($checkoutSession);
        $this->cartHelper->expects(self::once())->method('replicateQuoteData')
            ->with($parentQuoteMock, $immutableQuoteMock);

        $testCartData = $this->getTestCartData();
        $testCartData['discounts'] = [
            [
                'description' => 'Discount Test Coupon',
                'amount' => 1000,
                'reference' => self::COUPON_CODE,
                'discount_category' => 'coupon',
                'discount_type' => 'fixed_amount'
            ]
        ];
        $result = [
            'status' => 'success',
            'order_reference' => self::PARENT_QUOTE_ID,
            'order_create' => [
                'cart' => $testCartData,
            ]
        ];
        
        $this->currentMock->expects(self::once())->method('generateResult')
            ->with($immutableQuoteMock)
            ->willReturn($result);
            
        $this->expectSuccessResponse($result);

        $this->assertTrue($this->currentMock->execute($requestCart, null, null, $discount_codes_to_add));
    }
    
    /**
     * @test
     * that that execute would send success response and return true if remove coupon successfully
     *
     * @covers ::execute
     */
    public function execute_removeCoupon_sendSuccessResponse_returnTrue()
    {
        $requestCart = $this->getRequestCart();
        $requestCart['discounts'] = [
            [
                'description' => 'Discount Test Coupon',
                'amount' => 1000,
                'reference' => self::COUPON_CODE,
                'discount_category' => 'coupon',
                'discount_type' => 'fixed_amount'
            ]
        ];
        $discount_codes_to_remove = [self::COUPON_CODE];
        
        $parentQuoteMock = $this->getQuoteMock(
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID            
        );
        
        $sessionHelper = $this->getMockBuilder(SessionHelper::class)
            ->setMethods(['loadSession','getCheckoutSession'])
            ->disableOriginalConstructor()
            ->getMock();
        $sessionHelper->expects(self::once())->method('loadSession')->with($parentQuoteMock);
        
        $checkoutSession = $this->createMock(CheckoutSession::class);
        $sessionHelper->expects(self::once())->method('getCheckoutSession')
            ->willReturn($checkoutSession);
            
        $this->initCurrentMock([
            'validateQuote',
            'preProcessWebhook',
            'setShipment',
            'generateResult',
            'getQuoteCart',
            'removeDiscount'
        ], $sessionHelper);        
        
        $immutableQuoteMock = $this->getQuoteMock();
        
        $this->currentMock->expects(self::once())->method('validateQuote')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn([$parentQuoteMock,$immutableQuoteMock]);
            
        $this->currentMock->expects(self::once())->method('preProcessWebhook')
            ->with(self::STORE_ID);
            
        $this->currentMock->expects($this->exactly(2))
            ->method('setShipment')
            ->withConsecutive(
                [$requestCart['shipments'][0], $immutableQuoteMock],
                [$requestCart['shipments'][0], $parentQuoteMock]
            );

        $quoteDiscount = [
            [
                'description' => 'Discount Test Coupon',
                'amount'      => 1000,
                'reference'   => self::COUPON_CODE,
                'discount_category' => 'coupon',
                'discount_type'   => 'fixed_amount',
            ]
        ];
        $this->currentMock->expects(self::once())->method('getQuoteCart')
            ->with($parentQuoteMock)
            ->willReturn(['discounts' => $quoteDiscount]);
        
        $this->currentMock->expects(self::once())->method('removeDiscount')
            ->with(self::COUPON_CODE, [self::COUPON_CODE => 'coupon'], $parentQuoteMock, self::WEBSITE_ID, self::STORE_ID)
            ->willReturn(true);
        
        $this->cartHelper->expects(self::once())->method('resetCheckoutSession')
            ->with($checkoutSession);
        $this->cartHelper->expects(self::once())->method('replicateQuoteData')
            ->with($parentQuoteMock, $immutableQuoteMock);

        $result = [
            'status' => 'success',
            'order_reference' => self::PARENT_QUOTE_ID,
            'order_create' => [
                'cart' => $this->getTestCartData(),
            ]
        ];
        
        $this->currentMock->expects(self::once())->method('generateResult')
            ->with($immutableQuoteMock)
            ->willReturn($result);
            
        $this->expectSuccessResponse($result);

        $this->assertTrue($this->currentMock->execute($requestCart, null, null, null, $discount_codes_to_remove));
    }
    
    /**
     * @test
     * that that execute would send success response and return true if add item successfully
     *
     * @covers ::execute
     */
    public function execute_addItem_sendSuccessResponse_returnTrue()
    {
        $requestCart = $this->getRequestCart();
        $add_items = [
            [
                'price'      => 4500,
                'currency'   => 'USD',
                'product_id' => 100,
                'quantity'   => 1,
            ]
        ];
        $requestCart['add_items'] = $add_items;
        
        $parentQuoteMock = $this->getQuoteMock(
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID            
        );
        
        $sessionHelper = $this->getMockBuilder(SessionHelper::class)
            ->setMethods(['loadSession','getCheckoutSession'])
            ->disableOriginalConstructor()
            ->getMock();
        $sessionHelper->expects(self::once())->method('loadSession')->with($parentQuoteMock);
            
        $this->initCurrentMock([
            'validateQuote',
            'preProcessWebhook',
            'setShipment',
            'generateResult',
            'getProduct',
            'verifyItemData',
            'addItemToQuote',
            'updateTotals'
        ], $sessionHelper);
        
        $immutableQuoteMock = $this->getQuoteMock();
        
        $this->currentMock->expects($this->exactly(2))
            ->method('setShipment')
            ->withConsecutive(
                [$requestCart['shipments'][0], $immutableQuoteMock],
                [$requestCart['shipments'][0], $parentQuoteMock]
            );
            
        $this->currentMock->expects(self::once())->method('validateQuote')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn([$parentQuoteMock,$immutableQuoteMock]);
            
        $this->currentMock->expects(self::once())->method('preProcessWebhook')
            ->with(self::STORE_ID);
        
        $product = $this->createMock(ProductInterface::class);
        $this->currentMock->expects(self::once())->method('getProduct')
            ->with(100,self::STORE_ID)
            ->willReturn($product);
        
        $this->currentMock->expects(self::once())->method('verifyItemData')
            ->with($product, $add_items[0], self::WEBSITE_ID)
            ->willReturn(true);
        
        $this->currentMock->expects(self::once())->method('addItemToQuote')
            ->with($product, $parentQuoteMock, $add_items[0])
            ->willReturn(true);
        
        $this->currentMock->expects(self::once())->method('updateTotals')
            ->with($parentQuoteMock);
        
        $checkoutSession = $this->createMock(CheckoutSession::class);
        $sessionHelper->expects(self::once())->method('getCheckoutSession')
            ->willReturn($checkoutSession);
        
        $this->cartHelper->expects(self::once())->method('resetCheckoutSession')
            ->with($checkoutSession);    
        $this->cartHelper->expects(self::once())->method('replicateQuoteData')
            ->with($parentQuoteMock, $immutableQuoteMock);

        $cartData = $this->getTestCartData();
        $cartData['items'][] = [
            'name'         => 'Test Product',
            'reference'    => 100,
            'total_amount' => 4500,
            'unit_price'   => 4500,
            'quantity'     => 1,
            'image_url'    => 'https://images.example.com/dress1.jpg',
            'type'         => 'physical',
            'properties'   => [],
        ];
        $result = [
            'status' => 'success',
            'order_reference' => self::PARENT_QUOTE_ID,
            'order_create' => [
                'cart' => $cartData,
            ]
        ];
        
        $this->currentMock->expects(self::once())->method('generateResult')
            ->with($immutableQuoteMock)
            ->willReturn($result);
            
        $this->expectSuccessResponse($result);

        $this->assertTrue($this->currentMock->execute($requestCart, $add_items, null, null, null));
    }
    
    /**
     * @test
     * that that execute would send success response and return true if remove item successfully
     *
     * @covers ::execute
     */
    public function execute_removeItem_sendSuccessResponse_returnTrue()
    {
        $requestCart = $this->getRequestCart();
        $remove_items = [
            [
                'price'      => 4500,
                'currency'   => 'USD',
                'product_id' => 100,
                'quantity'   => 1,
            ]
        ];
        $requestCart['remove_items'] = $remove_items;
        
        $parentQuoteMock = $this->getQuoteMock(
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID            
        );
        
        $sessionHelper = $this->getMockBuilder(SessionHelper::class)
            ->setMethods(['loadSession','getCheckoutSession'])
            ->disableOriginalConstructor()
            ->getMock();
        $sessionHelper->expects(self::once())->method('loadSession')->with($parentQuoteMock);
            
        $this->initCurrentMock([
            'validateQuote',
            'preProcessWebhook',
            'setShipment',
            'generateResult',
            'removeItemFromQuote',
            'updateTotals',
            'getCartItems'
        ], $sessionHelper);
        
        $immutableQuoteMock = $this->getQuoteMock();
        
        $this->currentMock->expects($this->exactly(2))
            ->method('setShipment')
            ->withConsecutive(
                [$requestCart['shipments'][0], $immutableQuoteMock],
                [$requestCart['shipments'][0], $parentQuoteMock]
            );
        
        $this->currentMock->expects(self::once())->method('validateQuote')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn([$parentQuoteMock,$immutableQuoteMock]);
            
        $this->currentMock->expects(self::once())->method('preProcessWebhook')
            ->with(self::STORE_ID);
        
        $this->currentMock->expects(self::once())->method('updateTotals')
            ->with($parentQuoteMock);
        
        $checkoutSession = $this->createMock(CheckoutSession::class);
        $sessionHelper->expects(self::once())->method('getCheckoutSession')
            ->willReturn($checkoutSession);
        
        $this->cartHelper->expects(self::once())->method('resetCheckoutSession')
            ->with($checkoutSession);
        $this->cartHelper->expects(self::once())->method('replicateQuoteData')
            ->with($parentQuoteMock, $immutableQuoteMock);
        
        $cartItems = [
            [
                'reference'    => 100,
                'quantity'     => 1,
                'quote_item_id'=> 60,
            ],
            [
                'reference'    => 101,
                'quantity'     => 1,
                'quote_item_id'=> 61,
            ]
        ];
        $this->currentMock->expects(self::once())->method('getCartItems')
            ->with($parentQuoteMock)
            ->willReturn($cartItems);
        
        $this->currentMock->expects(self::once())->method('removeItemFromQuote')
            ->with($cartItems, $remove_items[0], $parentQuoteMock)
            ->willReturn(true);
        
        $result = [
            'status' => 'success',
            'order_reference' => self::PARENT_QUOTE_ID,
            'order_create' => [
                'cart' => $this->getTestCartData(),
            ]
        ];
        
        $this->currentMock->expects(self::once())->method('generateResult')
            ->with($immutableQuoteMock)
            ->willReturn($result);
            
        $this->expectSuccessResponse($result);

        $this->assertTrue($this->currentMock->execute($requestCart, null, $remove_items, null, null));
    }
    
    /**
     * @test
     *
     * @covers ::execute
     */
    public function execute_preProcessWebhook_throwLocalizedException()
    {
        $requestCart = $this->getRequestCart();
        
        $parentQuoteMock = $this->getQuoteMock(
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID            
        );
            
        $this->initCurrentMock(['validateQuote', 'preProcessWebhook', 'getQuoteCart']);
        
        $immutableQuoteMock = $this->getQuoteMock();
        
        $this->currentMock->expects(self::never())->method('getQuoteCart');
            
        $this->currentMock->expects(self::once())->method('validateQuote')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn([$parentQuoteMock,$immutableQuoteMock]);

        $this->currentMock->expects(self::once())->method('preProcessWebhook')
            ->willThrowException(new LocalizedException(__('Localized Exception')));

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_SERVICE,
            'Localized Exception',
            500
        );

        $this->assertFalse($this->currentMock->execute($requestCart));
    }
    
    /**
     * @test
     *
     * @covers ::generateResult
     */
    public function generateResult()
    {
        $immutableQuoteMock = $this->getQuoteMock();
        $quoteCart = $this->getTestCartData();
        
        $cartData =$this->createMock(CartDataInterface::class);
        
        $cartData->expects(self::once())->method('setDisplayId')->with($quoteCart['display_id']);
        $cartData->expects(self::once())->method('setCurrency')->with($quoteCart['currency']);
        $cartData->expects(self::once())->method('setItems')->with($quoteCart['items']);
        $cartData->expects(self::once())->method('setDiscounts')->with($quoteCart['discounts']);
        $cartData->expects(self::once())->method('setTotalAmount')->with($quoteCart['total_amount']);
        $cartData->expects(self::once())->method('setTaxAmount')->with($quoteCart['tax_amount']);
        $cartData->expects(self::once())->method('setOrderReference')->with($quoteCart['order_reference']);
        $cartData->expects(self::once())->method('setShipments')->with($quoteCart['shipments']);
        
        $cartDataFactory = $this->createMock(CartDataInterfaceFactory::class);
        $cartDataFactory->expects(self::once())->method('create')
            ->willReturn($cartData);
        
        $updateCartResult =$this->createMock(UpdateCartResultInterface::class);

        $updateCartResult->expects(self::once())->method('setOrderCreate')->with($cartData);
        $updateCartResult->expects(self::once())->method('setOrderReference')->with($quoteCart['order_reference']);
        $updateCartResult->expects(self::once())->method('setStatus')->with('success');
        
        $result = [
            'status' => 'success',
            'order_reference' => self::PARENT_QUOTE_ID,
            'order_create' => [
                'cart' => $this->getTestCartData(),
            ]
        ];
        
        $updateCartResult->expects(self::once())->method('getCartResult')
            ->willReturn($result);
        
        $updateCartResultFactory = $this->createMock(UpdateCartResultInterfaceFactory::class);
        $updateCartResultFactory->expects(self::once())->method('create')
            ->willReturn($updateCartResult);
            
        $this->initCurrentMock(['getQuoteCart'], null, $cartDataFactory, $updateCartResultFactory);
        
        $this->currentMock->expects(self::once())->method('getQuoteCart')
            ->with($immutableQuoteMock)
            ->willReturn($quoteCart);
            
        self::assertEquals($result, $this->currentMock->generateResult($immutableQuoteMock));
    }
}
