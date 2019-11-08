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

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as BoltHelperCart;
use Bolt\Boltpay\Helper\Log;
use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote;
use \PHPUnit\Framework\TestCase;
use Magento\Framework\App\Helper\Context as ContextHelper;
use Magento\Framework\Session\SessionManagerInterface as CheckoutSession;
use Magento\Catalog\Model\ProductRepository;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Helper\Context;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\BlockFactory;
use Magento\Store\Model\App\Emulation;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Quote\Api\CartRepositoryInterface as QuoteCartRepository;
use Magento\Sales\Api\OrderRepositoryInterface as OrderRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Magento\Framework\App\CacheInterface;
use Bolt\Boltpay\Model\Response;
use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Model\Quote\Address\Total;

/**
 * Class ConfigTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper
 */
class CartTest extends TestCase
{
    const QUOTE_ID = 1001;
    const PARENT_QUOTE_ID = 1000;
    const PRODUCT_ID = 20102;
    const PRODUCT_PRICE = 100;
    const ORDER_ID = 100010001;
    const STORE_ID = 1;
    const CACHE_IDENTIFIER = 'de6571d30123102e4a49a9483881a05f';
    const PRODUCT_SKU = 'TestProduct';

    private $contextHelper;
    private $checkoutSession;
    private $apiHelper;
    private $configHelper;
    private $customerSession;
    private $logHelper;
    private $bugsnag;
    private $blockFactory;
    private $productRepository;
    private $appEmulation;
    private $dataObjectFactory;
    private $quoteFactory;
    private $totalsCollector;
    private $quoteCartRepository;
    private $orderRepository;
    private $searchCriteriaBuilder;
    private $quoteResource;
    private $sessionHelper;
    private $checkoutHelper;
    private $discountHelper;
    /** @var CacheInterface */
    private $cache;
    private $resourceConnection;
    private $quoteAddressTotal;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->contextHelper = $this->createMock(ContextHelper::class);

        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->productRepository = $this->getProductRepositoryMock();

        $this->apiHelper = $this->createMock(ApiHelper::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->logHelper = $this->getMockBuilder(LogHelper::class)
            ->setMethods(['addInfoLog'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->logHelper->method('addInfoLog')
            ->withAnyParameters()
            ->willReturnSelf();

        $this->bugsnag = $this->getMockBuilder(Bugsnag::class)
            ->setMethods(['notifyError', 'notifyException'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->blockFactory = $this->getMockBuilder(BlockFactory::class)
            ->setMethods(['createBlock', 'getImage', 'getImageUrl'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->blockFactory->method('createBlock')
            ->with('Magento\Catalog\Block\Product\ListProduct')
            ->willReturnSelf();
        $this->blockFactory->method('getImage')
            ->withAnyParameters()
            ->willReturnSelf();
        $this->blockFactory->method('getImageUrl')
            ->willReturn('no-image');

        $this->appEmulation = $this->getMockBuilder(Emulation::class)
            ->setMethods(['stopEnvironmentEmulation', 'startEnvironmentEmulation'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->dataObjectFactory = $this->getMockBuilder(DataObjectFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteFactory = $this->getMockBuilder(QuoteFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->totalsCollector = $this->createMock(TotalsCollector::class);
        $this->quoteCartRepository = $this->createMock(QuoteCartRepository::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->quoteResource = $this->createMock(QuoteResource::class);
        $this->sessionHelper = $this->createMock(SessionHelper::class);
        $this->checkoutHelper = $this->createMock(CheckoutHelper::class);
        $this->discountHelper = $this->createMock(DiscountHelper::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->quoteAddressTotal = $this->getMockBuilder(Total::class)
            ->setMethods(['getValue', 'setValue', 'getTitle'])
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @test
     */
    public function getCartData_multistepNoDiscount()
    {
        $billingAddress = $this->getBillingAddress();
        $shippingAddress = $this->getShippingAddress();
        $quote = $this->getQuoteMock($billingAddress, $shippingAddress);

        $quote->method('getTotals')
            ->willReturn([]);

        $this->checkoutSession = $this->getMockBuilder(\Magento\Framework\Session\SessionManager::class)
            ->setMethods(['getQuote'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutSession->expects($this->any())
            ->method('getQuote')
            ->willReturn($quote);

        $this->searchCriteriaBuilder = $this->getMockBuilder(SearchCriteriaBuilder::class)
            ->setMethods(['addFilter', 'create'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->searchCriteriaBuilder->expects($this->once())
            ->method('addFilter')
            ->withAnyParameters()
            ->willReturnSelf();
        $this->searchCriteriaBuilder->expects($this->once())
            ->method('create')
            ->willReturn($this->createMock(\Magento\Framework\Api\SearchCriteria::class));

        $methods = ['getList', 'getItems', 'getForCustomer', 'getActive',
            'getActiveForCustomer', 'save', 'delete', 'get'];
        $this->quoteCartRepository = $this->getMockBuilder(QuoteCartRepository::class)
            ->setMethods($methods)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();
        $this->quoteCartRepository->expects($this->any())
            ->method('getList')
            ->with($this->createMock(\Magento\Framework\Api\SearchCriteria::class))
            ->willReturnSelf();
        $this->quoteCartRepository->expects($this->any())
            ->method('getItems')
            ->will($this->returnValue([$quote]));

        $currentMock = new BoltHelperCart(
            $this->contextHelper,
            $this->checkoutSession,
            $this->productRepository,
            $this->apiHelper,
            $this->configHelper,
            $this->customerSession,
            $this->logHelper,
            $this->bugsnag,
            $this->dataObjectFactory,
            $this->blockFactory,
            $this->appEmulation,
            $this->quoteFactory,
            $this->totalsCollector,
            $this->quoteCartRepository,
            $this->orderRepository,
            $this->searchCriteriaBuilder,
            $this->quoteResource,
            $this->sessionHelper,
            $this->checkoutHelper,
            $this->discountHelper,
            $this->cache,
            $this->resourceConnection
        );

        $paymentOnly = false;
        $placeOrderPayload = '';
        $immutableQuote = $quote;

        $result = $currentMock->getCartData($paymentOnly, $placeOrderPayload, $immutableQuote);

        $expected = [
            'order_reference' => self::PARENT_QUOTE_ID,
            'display_id' => '100010001 / '.self::QUOTE_ID,
            'currency' => '$',
            'items' => [[
                'reference' => self::PRODUCT_ID,
                'name'  => 'Test Product',
                'total_amount'  => 10000,
                'unit_price'    => 10000,
                'quantity'      => 1,
                'sku'           => self::PRODUCT_SKU,
                'type'          => 'physical',
                'description'   => 'Product Description',
                'image_url'     => 'no-image'
            ]],
            "billing_address" => [
                'first_name' => "IntegrationBolt",
                'last_name' => "BoltTest",
                'company' => "",
                'phone' => "132 231 1234",
                'street_address1' => "228 7th Avenue",
                'street_address2' => null,
                'locality' => "New York",
                'region' => "New York",
                'postal_code' => "10011",
                'country_code' => "US",
                'email'=> "integration@bolt.com"
            ],
            'discounts' => [],
            'total_amount' => 10000,
            'tax_amount' => 0
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * Get quote mock with quote items
     *
     * @param $billingAddress
     * @param $shippingAddress
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getQuoteMock($billingAddress, $shippingAddress)
    {
        $quoteItem = $this->getMockBuilder(\Magento\Quote\Model\Quote\Item::class)
            ->setMethods([
                'getSku', 'getQty', 'getCalculationPrice', 'getName', 'getIsVirtual',
                'getProductId', 'getProduct'
            ])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteItem->method('getName')
            ->willReturn('Test Product');
        $quoteItem->method('getSku')
            ->willReturn('TestProduct');
        $quoteItem->method('getQty')
            ->willReturn(1);
        $quoteItem->method('getCalculationPrice')
            ->willReturn(self::PRODUCT_PRICE);
        $quoteItem->method('getIsVirtual')
            ->willReturn(false);
        $quoteItem->method('getProductId')
            ->willReturn(self::PRODUCT_ID);
        $quoteItem->method('getProduct')
            ->willReturn($this->getProductMock());

        $quoteMethods = [
            'getId', 'getBoltParentQuoteId', 'getSubtotal', 'getAllVisibleItems',
            'getAppliedRuleIds', 'isVirtual', 'getShippingAddress', 'collectTotals',
            'getQuoteCurrencyCode', 'getBillingAddress', 'getReservedOrderId', 'getTotals',
            'getStoreId', 'getUseRewardPoints', 'getUseCustomerBalance', 'getRewardCurrencyAmount',
            'getCustomerBalanceAmountUsed','getData'
        ];
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $quote->method('getId')
            ->willReturn(self::QUOTE_ID);
        $quote->method('getReservedOrderId')
            ->willReturn('100010001');
        $quote->method('getBoltParentQuoteId')
            ->willReturn(self::PARENT_QUOTE_ID);
        $quote->method('getSubtotal')
            ->willReturn(self::PRODUCT_PRICE);
        $quote->method('getAllVisibleItems')
            ->willReturn([$quoteItem]);
        $quote->method('getAppliedRuleIds')
            ->willReturn('2,3');
        $quote->method('isVirtual')
            ->willReturn(false);
        $quote->method('getBillingAddress')
            ->willReturn($billingAddress);
        $quote->method('getShippingAddress')
            ->willReturn($shippingAddress);
        $quote->method('getQuoteCurrencyCode')
            ->willReturn('$');
        $quote->method('collectTotals')
            ->willReturnSelf();
        //$quote->method('getTotals')
        //    ->willReturn([]);
        $quote->expects($this->any())
            ->method('getStoreId')
            ->will($this->returnValue("1"));
       // $quote->method('getUseRewardPoints')
       //     ->willReturn(false);
        //$quote->method('getUseCustomerBalance')
        //    ->willReturn(false);

        return $quote;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getBillingAddress()
    {
        $addressData = $this->getAddressData();
        $billingAddress = $this->getMockBuilder(Quote\Address::class)
            ->setMethods([
                'getFirstname', 'getLastname', 'getCompany', 'getTelephone', 'getStreetLine',
                'getCity', 'getRegion', 'getPostcode', 'getCountryId', 'getEmail',
                'getDiscountAmount', 'getCouponCode'
            ])
            ->disableOriginalConstructor()
            ->getMock();

        $billingAddress->method('getFirstname')
            ->willReturn($addressData['first_name']);
        $billingAddress->method('getLastname')
            ->willReturn($addressData['last_name']);
        $billingAddress->method('getCompany')
            ->willReturn($addressData['company']);
        $billingAddress->method('getTelephone')
            ->willReturn($addressData['phone']);
        $billingAddress->method('getStreetLine')
            ->will($this->returnValueMap([
                [1, $addressData['street_address1']],
                [2, $addressData['street_address2']]
            ]));
        $billingAddress->method('getCity')
            ->willReturn($addressData['locality']);
        $billingAddress->method('getRegion')
            ->willReturn($addressData['region']);
        $billingAddress->method('getPostcode')
            ->willReturn($addressData['postal_code']);
        $billingAddress->method('getCountryId')
            ->willReturn($addressData['country_code']);
        $billingAddress->method('getEmail')
            ->willReturn($addressData['email']);

        return $billingAddress;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getShippingAddress()
    {
        return $this->getBillingAddress();
    }

    /**
     * @return array
     */
    private function getAddressData()
    {
        return [
            'company' => "",
            'country' => "United States",
            'country_code' => "US",
            'email' => "integration@bolt.com",
            'first_name' => "IntegrationBolt",
            'last_name' => "BoltTest",
            'locality' => "New York",
            'phone' => "132 231 1234",
            'postal_code' => "10011",
            'region' => "New York",
            'street_address1' => "228 7th Avenue",
            'street_address2' => null,
        ];
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getProductRepositoryMock()
    {
        $product = $this->getProductMock();

        $this->productRepository = $this->getMockBuilder(ProductRepository::class)
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->productRepository->method('get')
            ->with(self::PRODUCT_SKU)
            ->willReturn($product);

        return $this->productRepository;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getProductMock()
    {
        $product = $this->getMockBuilder(Product::class)
            ->setMethods(['getId', 'getDescription', 'getTypeInstance', 'getOrderOptions'])
            ->disableOriginalConstructor()
            ->getMock();

        $product->method('getId')
            ->willReturn(self::PRODUCT_ID);
        $product->method('getDescription')
            ->willReturn('Product Description');
        $product->method('getTypeInstance')
            ->willReturnSelf();
        $product->method('getOrderOptions')
            ->withAnyParameters()
            ->willReturn([]);

        return $product;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getHelperCartMock()
    {
        $methods = [
            'getCartData',
            'isBoltOrderCachingEnabled',
            'getCartCacheIdentifier',
            'loadFromCache',
            'getImmutableQuoteIdFromBoltOrder',
            'isQuoteAvailable',
            'deleteQuote',
            'getLastImmutableQuote',
            'saveCartSession',
            'getSessionQuoteStoreId',
            'boltCreateOrder',
            'saveToCache',
            'updateQuoteTimestamp',
            'clearExternalData',
            'convertCustomAddressFieldsToCacheIdentifier',
            'getCustomAddressFieldsPascalCaseArray',
            'getCalculationAddress'
        ];

        $mock = $this->createPartialMock(BoltHelperCart::class, $methods);

        return $mock;
    }

    /**
     * @return array
     */
    private function getCart()
    {
        $parentQuoteId = self::PARENT_QUOTE_ID;
        $quoteId = self::QUOTE_ID;
        $orderId = self::ORDER_ID;

        $cart = <<<CART
        {
          "order_reference": "$parentQuoteId",
          "display_id": "$orderId / $quoteId",
          "total_amount": 50000,
          "tax_amount": 1000,
          "currency": "USD",
          "items": [
            {
              "name": "Beaded Long Dress",
              "reference": "123ABC",
              "total_amount": 50000,
              "unit_price": 50000,
              "quantity": 1,
              "image_url": "https://images.example.com/dress.jpg",
              "type": "physical",
              "properties": [
                {
                  "name": "color",
                  "value": "blue"
                }
              ]
            }
          ],
          "discounts": [
            {
              "amount": 1000,
              "description": "10 dollars off",
              "reference": "DISCOUNT-10",
              "details_url": "http://example.com/info/discount-10"
            }
          ]
        }
CART;
        $cart = json_decode($cart, true);

        return $cart;
    }

    /**
     * @return object
     */
    private function getOrder()
    {
        $parentQuoteId = self::PARENT_QUOTE_ID;
        $quoteId = self::QUOTE_ID;
        $orderId = self::ORDER_ID;

        $order = <<<ORDER
        {
          "token": "f34fff50f8b89db7cbe867326404d782fb688bdd8b26ab3affe8c0ba22b2ced5",
          "cart": {
            "order_reference": "$parentQuoteId",
            "display_id": "$orderId / $quoteId",
            "subtotal_amount": null,
            "total_amount": {
              "amount": 50000,
              "currency": "USD",
              "currency_symbol": "$"
            },
            "tax_amount": {
              "amount": 1000,
              "currency": "USD",
              "currency_symbol": "$"
            },
            "items": [
              {
                "reference": "",
                "name": "Beaded Long Dress",
                "total_amount": {
                  "amount": 50000,
                  "currency": "USD",
                  "currency_symbol": "$"
                },
                "unit_price": {
                  "amount": 50000,
                  "currency": "USD",
                  "currency_symbol": "$"
                },
                "quantity": 1,
                "type": "physical"
              }
            ],
            "shipments": [
              {
                "shipping_address": {
                  "street_address1": "123 Baker Street",
                  "locality": "San Francisco",
                  "region": "California",
                  "postal_code": "94550",
                  "country_code": "US",
                  "name": "John Doe",
                  "first_name": "John",
                  "last_name": "Doe"
                },
                "shipping_method": "unknown"
              }
            ],
            "discounts": [
              {
                "amount": {
                  "amount": 1000,
                  "currency": "USD",
                  "currency_symbol": "$"
                },
                "description": "10 dollars off",
                "reference": "DISCOUNT-10",
                "details_url": "http://example.com/info/discount-10"
              }
            ]
          }
        }
ORDER;
        $order = json_decode($order);

        return $order;
    }

    /**
     * @return Response
     */
    private function getBoltOrderResponse()
    {
        $order = $this->getOrder();
        $boltOrderResponse = new \Bolt\Boltpay\Model\Response;
        $boltOrderResponse->setResponse($order);
        return $boltOrderResponse;
    }

    /**
     * @test
     */
    public function testGetBoltpayOrderCachingDisabled()
    {
        $mock = $this->getHelperCartMock();

        $cart = $this->getCart();
        $boltOrder = $this->getBoltOrderResponse();

        $mock->expects($this->once())
            ->method('getCartData')
            ->with(false, '')
            ->willReturn($cart);

        $mock->expects($this->once())
            ->method('getSessionQuoteStoreId')
            ->willReturn(self::STORE_ID);

        $mock->expects($this->once())
            ->method('isBoltOrderCachingEnabled')
            ->with(self::STORE_ID)
            ->willReturn(false);

        $mock->expects($this->never())
            ->method('getCartCacheIdentifier');

        $mock->expects($this->never())
            ->method('loadFromCache');

        $mock->expects($this->never())
            ->method('getImmutableQuoteIdFromBoltOrder');

        $mock->expects($this->never())
            ->method('isQuoteAvailable');

        $mock->expects($this->never())
            ->method('getLastImmutableQuote');

        $mock->expects($this->never())
            ->method('deleteQuote');

        $mock->expects($this->once())
            ->method('saveCartSession')
            ->with(self::PARENT_QUOTE_ID);

        $mock->expects($this->once())
            ->method('boltCreateOrder')
            ->with($cart, self::STORE_ID)
            ->willReturn($boltOrder);

        $mock->expects($this->never())
            ->method('saveToCache');

        $result = $mock->getBoltpayOrder(false, '');

        $this->assertEquals($result, $boltOrder);
    }

    /**
     * @test
     */
    public function testGetBoltpayOrderNotCached()
    {
        $mock = $this->getHelperCartMock();

        $cart = $this->getCart();
        $boltOrder = $this->getBoltOrderResponse();

        $mock->expects($this->once())
            ->method('getCartData')
            ->with(false, '')
            ->willReturn($cart);

        $mock->expects($this->once())
            ->method('getSessionQuoteStoreId')
            ->willReturn(self::STORE_ID);

        $mock->expects($this->once())
            ->method('isBoltOrderCachingEnabled')
            ->with(self::STORE_ID)
            ->willReturn(true);

        $mock->expects($this->once())
            ->method('getCartCacheIdentifier')
            ->willReturn(self::CACHE_IDENTIFIER);

        $mock->expects($this->once())
            ->method('loadFromCache')
            ->with(self::CACHE_IDENTIFIER)
            ->willReturn(false);

        $mock->expects($this->never())
            ->method('getImmutableQuoteIdFromBoltOrder');

        $mock->expects($this->never())
            ->method('isQuoteAvailable');

        $mock->expects($this->never())
            ->method('getLastImmutableQuote');

        $mock->expects($this->never())
            ->method('deleteQuote');

        $mock->expects($this->once())
            ->method('saveCartSession')
            ->with(self::PARENT_QUOTE_ID);

        $mock->expects($this->once())
            ->method('boltCreateOrder')
            ->with($cart, self::STORE_ID)
            ->willReturn($boltOrder);

        $mock->expects($this->once())
            ->method('saveToCache')
            ->with($boltOrder, self::CACHE_IDENTIFIER, [BoltHelperCart::BOLT_ORDER_TAG], 3600);

        $result = $mock->getBoltpayOrder(false, '');

        $this->assertEquals($result, $boltOrder);
    }

    /**
     * @test
     */
    public function testGetBoltpayOrderCachedQuoteAvailable()
    {
        $mock = $this->getHelperCartMock();

        $cart = $this->getCart();
        $boltOrder = $this->getBoltOrderResponse();
        $immutableQuote = $this->getQuoteMock($this->getBillingAddress(), $this->getShippingAddress());

        $mock->expects($this->once())
            ->method('getCartData')
            ->with(false, '')
            ->willReturn($cart);

        $mock->expects($this->once())
            ->method('getSessionQuoteStoreId')
            ->willReturn(self::STORE_ID);

        $mock->expects($this->once())
            ->method('isBoltOrderCachingEnabled')
            ->with(self::STORE_ID)
            ->willReturn(true);

        $mock->expects($this->once())
            ->method('getCartCacheIdentifier')
            ->willReturn(self::CACHE_IDENTIFIER);

        $mock->expects($this->once())
            ->method('loadFromCache')
            ->with(self::CACHE_IDENTIFIER)
            ->willReturn($boltOrder);

        $mock->expects($this->once())
            ->method('getImmutableQuoteIdFromBoltOrder')
            ->with($boltOrder)
            ->willReturn(self::QUOTE_ID);

        $mock->expects($this->once())
            ->method('isQuoteAvailable')
            ->with(self::QUOTE_ID)
            ->willReturn(true);

        $mock->expects($this->exactly(2))
            ->method('getLastImmutableQuote')
            ->willReturn($immutableQuote);

        $mock->expects($this->once())
            ->method('deleteQuote')
            ->with($immutableQuote);

        $mock->expects($this->never())
            ->method('saveCartSession');

        $mock->expects($this->never())
            ->method('boltCreateOrder');

        $mock->expects($this->never())
            ->method('saveToCache');

        $result = $mock->getBoltpayOrder(false, '');

        $this->assertEquals($result, $boltOrder);
    }

    /**
     * @test
     */
    public function testGetBoltpayOrderCachedQuoteNotAvailable()
    {
        $mock = $this->getHelperCartMock();

        $cart = $this->getCart();
        $boltOrder = $this->getBoltOrderResponse();

        $mock->expects($this->once())
            ->method('getCartData')
            ->with(false, '')
            ->willReturn($cart);

        $mock->expects($this->once())
            ->method('getSessionQuoteStoreId')
            ->willReturn(self::STORE_ID);

        $mock->expects($this->once())
            ->method('isBoltOrderCachingEnabled')
            ->with(self::STORE_ID)
            ->willReturn(true);

        $mock->expects($this->once())
            ->method('getCartCacheIdentifier')
            ->willReturn(self::CACHE_IDENTIFIER);

        $mock->expects($this->once())
            ->method('loadFromCache')
            ->with(self::CACHE_IDENTIFIER)
            ->willReturn($boltOrder);

        $mock->expects($this->once())
            ->method('getImmutableQuoteIdFromBoltOrder')
            ->with($boltOrder)
            ->willReturn(self::QUOTE_ID);

        $mock->expects($this->once())
            ->method('isQuoteAvailable')
            ->with(self::QUOTE_ID)
            ->willReturn(false);

        $mock->expects($this->never())
            ->method('getLastImmutableQuote');

        $mock->expects($this->never())
            ->method('deleteQuote');

        $mock->expects($this->once())
            ->method('saveCartSession')
            ->with(self::PARENT_QUOTE_ID);

        $mock->expects($this->once())
            ->method('boltCreateOrder')
            ->with($cart, self::STORE_ID)
            ->willReturn($boltOrder);

        $mock->expects($this->once())
            ->method('saveToCache')
            ->with($boltOrder, self::CACHE_IDENTIFIER, [BoltHelperCart::BOLT_ORDER_TAG], 3600);

        $result = $mock->getBoltpayOrder(false, '');

        $this->assertEquals($result, $boltOrder);
    }

    /**
     * @return BoltHelperCart
     */
    protected function getCurrentMock()
    {
        return $this->getMockBuilder(BoltHelperCart::class)
            ->setMethods([
                'getLastImmutableQuote',
                'getCalculationAddress',
                'getQuoteById'
            ])->setConstructorArgs([
                $this->contextHelper,
                $this->checkoutSession,
                $this->productRepository,
                $this->apiHelper,
                $this->configHelper,
                $this->customerSession,
                $this->logHelper,
                $this->bugsnag,
                $this->dataObjectFactory,
                $this->blockFactory,
                $this->appEmulation,
                $this->quoteFactory,
                $this->totalsCollector,
                $this->quoteCartRepository,
                $this->orderRepository,
                $this->searchCriteriaBuilder,
                $this->quoteResource,
                $this->sessionHelper,
                $this->checkoutHelper,
                $this->discountHelper,
                $this->cache,
                $this->resourceConnection
            ])->getMock();
    }

    /**
     * @test
     */
    public function collectDiscounts_NoDiscounts()
    {
        $mock = $this->getCurrentMock();
        $shippingAddress = $this->getShippingAddress();

        $quote = $this->getQuoteMock($this->getBillingAddress(), $this->getShippingAddress());

        $quote->method('getBoltParentQuoteId')
            ->willReturn(999999);

        $quote->method('getTotals')
            ->willReturn([]);

        $mock->expects($this->once())
            ->method('getLastImmutableQuote')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getCalculationAddress')
            ->with($quote)
            ->willReturn($shippingAddress);

        $shippingAddress->expects($this->once())
            ->method('getDiscountAmount')
            ->willReturn(0);

        $quote->expects($this->once())
            ->method('getUseCustomerBalance')
            ->willReturn(false);

        $this->discountHelper->expects($this->once())
            ->method('isMirasvitStoreCreditAllowed')
            ->with($quote)
            ->willReturn(false);

        $this->discountHelper->expects($this->never())
            ->method('getAheadworksStoreCredit');

        $quote->expects($this->once())
            ->method('getUseRewardPoints')
            ->willReturn(false);

        $this->discountHelper->expects($this->never())
            ->method('getAmastyPayForEverything');

        $this->discountHelper->expects($this->never())
            ->method('getMageplazaGiftCardCodesFromSession');

        $this->discountHelper->expects($this->never())
            ->method('getUnirgyGiftCertBalanceByCode');

        $totalAmount = 10000;
        $diff = 0;
        $paymentOnly = false;

        list($discounts, $totalAmountResult, $diffResult) = $mock->collectDiscounts($totalAmount, $diff, $paymentOnly);

        $this->assertEquals($diffResult, $diff);
        $expectedTotalAmount = 10000;
        $this->assertEquals($expectedTotalAmount, $totalAmountResult);
        $expectedDiscounts = [];
        $this->assertEquals($expectedDiscounts, $discounts);
    }

    /**
     * @test
     */
    public function collectDiscounts_Coupon()
    {
        $mock = $this->getCurrentMock();
        $shippingAddress = $this->getShippingAddress();

        $quote = $this->getQuoteMock($this->getBillingAddress(), $shippingAddress);

        $quote->method('getBoltParentQuoteId')
            ->willReturn(999999);

        $mock->expects($this->once())
            ->method('getQuoteById')
            ->willReturn($quote);

        $quote->method('getTotals')
            ->willReturn([]);

        $mock->expects($this->once())
            ->method('getLastImmutableQuote')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getCalculationAddress')
            ->with($quote)
            ->willReturn($shippingAddress);

        $shippingAddress->expects($this->any())
            ->method('getCouponCode')
            ->willReturn('123456');

        $quote->expects($this->once())
            ->method('getUseCustomerBalance')
            ->willReturn(false);

        $this->discountHelper->expects($this->once())
            ->method('isMirasvitStoreCreditAllowed')
            ->with($quote)
            ->willReturn(false);

        $this->discountHelper->expects($this->never())
            ->method('getAheadworksStoreCredit');

        $quote->expects($this->once())
            ->method('getUseRewardPoints')
            ->willReturn(false);

        $this->discountHelper->expects($this->never())
            ->method('getAmastyPayForEverything');

        $this->discountHelper->expects($this->never())
            ->method('getMageplazaGiftCardCodesFromSession');

        $this->discountHelper->expects($this->never())
            ->method('getUnirgyGiftCertBalanceByCode');

        $appliedDiscount = 10; // $

        $shippingAddress->expects($this->once())
            ->method('getDiscountAmount')
            ->willReturn($appliedDiscount);

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = false;

        list($discounts, $totalAmountResult, $diffResult) = $mock->collectDiscounts($totalAmount, $diff, $paymentOnly);

        $this->assertEquals($diffResult, $diff);
        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;
        $this->assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        $this->assertEquals($expectedTotalAmount, $totalAmountResult);
    }

    /**
     * @test
     */
    public function collectDiscounts_StoreCreditWithPaymentOnly()
    {
        $mock = $this->getCurrentMock();
        $shippingAddress = $this->getShippingAddress();

        $quote = $this->getQuoteMock($this->getBillingAddress(), $shippingAddress);

        $quote->method('getTotals')
            ->willReturn([]);

        $quote->method('getBoltParentQuoteId')
            ->willReturn(999999);

        $mock->expects($this->once())
            ->method('getQuoteById')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getLastImmutableQuote')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getCalculationAddress')
            ->with($quote)
            ->willReturn($shippingAddress);

        $shippingAddress->expects($this->any())
            ->method('getCouponCode')
            ->willReturn(false);

        $shippingAddress->expects($this->any())
            ->method('getDiscountAmount')
            ->willReturn(false);

        $quote->expects($this->once())->method('getUseCustomerBalance')
            ->willReturn(true);

        $this->discountHelper->expects($this->once())
            ->method('isMirasvitStoreCreditAllowed')
            ->with($quote)
            ->willReturn(false);

        $this->discountHelper->expects($this->never())
            ->method('getAheadworksStoreCredit');

        $quote->expects($this->once())
            ->method('getUseRewardPoints')
            ->willReturn(false);

        $this->discountHelper->expects($this->never())
            ->method('getAmastyPayForEverything');

        $this->discountHelper->expects($this->never())
            ->method('getMageplazaGiftCardCodesFromSession');

        $this->discountHelper->expects($this->never())
            ->method('getUnirgyGiftCertBalanceByCode');

        $appliedDiscount = 10; // $

        $quote->expects($this->once())
            ->method('getCustomerBalanceAmountUsed')
            ->willReturn($appliedDiscount);

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;

        list($discounts, $totalAmountResult, $diffResult) = $mock->collectDiscounts($totalAmount, $diff, $paymentOnly);

        $this->assertEquals($diffResult, $diff);

        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;

        $this->assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        $this->assertEquals($expectedTotalAmount, $totalAmountResult);
    }

    /**
     * @test
     */
    public function collectDiscounts_MirasvitStoreCredit()
    {
        $mock = $this->getCurrentMock();
        $shippingAddress = $this->getShippingAddress();

        $quote = $this->getQuoteMock($this->getBillingAddress(), $shippingAddress);

        $quote->method('getBoltParentQuoteId')
            ->willReturn(999999);

        $mock->expects($this->once())
            ->method('getQuoteById')
            ->willReturn($quote);

        $quote->method('getTotals')
            ->willReturn([]);

        $mock->expects($this->once())
            ->method('getLastImmutableQuote')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getCalculationAddress')
            ->with($quote)
            ->willReturn($shippingAddress);

        $shippingAddress->expects($this->any())
            ->method('getCouponCode')
            ->willReturn(false);

        $shippingAddress->expects($this->any())
            ->method('getDiscountAmount')
            ->willReturn(false);

        $quote->expects($this->once())
            ->method('getUseCustomerBalance')
            ->willReturn(false);

        $this->discountHelper->expects($this->once())
            ->method('isMirasvitStoreCreditAllowed')
            ->with($quote)
            ->willReturn(true);

        $this->discountHelper->expects($this->never())
            ->method('getAheadworksStoreCredit');

        $quote->expects($this->once())
            ->method('getUseRewardPoints')
            ->willReturn(false);

        $this->discountHelper->expects($this->never())
            ->method('getAmastyPayForEverything');

        $this->discountHelper->expects($this->never())
            ->method('getMageplazaGiftCardCodesFromSession');

        $this->discountHelper->expects($this->never())
            ->method('getUnirgyGiftCertBalanceByCode');

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;
        $appliedDiscount = 10; // $

        $this->discountHelper->expects($this->once())
            ->method('getMirasvitStoreCreditAmount')
            ->with($quote, $paymentOnly)
            ->willReturn($appliedDiscount);


        list($discounts, $totalAmountResult, $diffResult) = $mock->collectDiscounts($totalAmount, $diff, $paymentOnly);

        $this->assertEquals($diffResult, $diff);

        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;

        $this->assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        $this->assertEquals($expectedTotalAmount, $totalAmountResult);
    }

    /**
     * @test
     */
    public function collectDiscounts_RewardPointsWithPaymentOnly()
    {

        $mock = $this->getCurrentMock();
        $shippingAddress = $this->getShippingAddress();

        $quote = $this->getQuoteMock($this->getBillingAddress(), $shippingAddress);

        $quote->method('getBoltParentQuoteId')
            ->willReturn(999999);

        $mock->expects($this->once())
            ->method('getQuoteById')
            ->willReturn($quote);

        $quote->method('getTotals')
            ->willReturn([]);

        $mock->expects($this->once())
            ->method('getLastImmutableQuote')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getCalculationAddress')
            ->with($quote)
            ->willReturn($shippingAddress);

        $shippingAddress->expects($this->any())
            ->method('getCouponCode')
            ->willReturn(false);

        $shippingAddress->expects($this->any())
            ->method('getDiscountAmount')
            ->willReturn(false);

        $quote->expects($this->once())
            ->method('getUseCustomerBalance')
            ->willReturn(false);

        $this->discountHelper->expects($this->once())
            ->method('isMirasvitStoreCreditAllowed')
            ->with($quote)
            ->willReturn(false);

        $quote->expects($this->once())
            ->method('getUseRewardPoints')
            ->willReturn(true);

        $this->discountHelper->expects($this->never())
            ->method('getAmastyPayForEverything');

        $this->discountHelper->expects($this->never())
            ->method('getMageplazaGiftCardCodesFromSession');

        $this->discountHelper->expects($this->never())
            ->method('getUnirgyGiftCertBalanceByCode');

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;
        $appliedDiscount = 10; // $

        $quote->expects($this->once())
            ->method('getRewardCurrencyAmount')
            ->willReturn($appliedDiscount);

        list($discounts, $totalAmountResult, $diffResult) = $mock->collectDiscounts($totalAmount, $diff, $paymentOnly);

        $this->assertEquals($diffResult, $diff);

        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;

        $this->assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        $this->assertEquals($expectedTotalAmount, $totalAmountResult);
    }

    /**
     * @test
     */
    public function collectDiscounts_AheadworksStoreCredit()
    {

        $mock = $this->getCurrentMock();
        $shippingAddress = $this->getShippingAddress();

        $quote = $this->getQuoteMock($this->getBillingAddress(), $shippingAddress);

        $quote->method('getBoltParentQuoteId')
            ->willReturn(999999);

        $mock->expects($this->once())
            ->method('getQuoteById')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getLastImmutableQuote')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getCalculationAddress')
            ->with($quote)
            ->willReturn($shippingAddress);

        $shippingAddress->expects($this->any())
            ->method('getCouponCode')
            ->willReturn(false);

        $shippingAddress->expects($this->any())
            ->method('getDiscountAmount')
            ->willReturn(false);

        $quote->expects($this->once())
            ->method('getUseCustomerBalance')
            ->willReturn(false);

        $this->discountHelper->expects($this->once())
            ->method('isMirasvitStoreCreditAllowed')
            ->with($quote)
            ->willReturn(false);

        $quote->expects($this->once())
            ->method('getUseRewardPoints')
            ->willReturn(false);

        $this->discountHelper->expects($this->never())
            ->method('getAmastyPayForEverything');

        $this->discountHelper->expects($this->never())
            ->method('getMageplazaGiftCardCodesFromSession');

        $this->discountHelper->expects($this->never())
            ->method('getUnirgyGiftCertBalanceByCode');

        $appliedDiscount = 10; // $

        $quote->expects($this->any())
            ->method('getTotals')
            ->willReturn([DiscountHelper::AHEADWORKS_STORE_CREDIT => $this->quoteAddressTotal]);

        $this->discountHelper->expects($this->once())
            ->method('getAheadworksStoreCredit')
            ->with($quote->getCustomerId())
            ->willReturn($appliedDiscount);

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;

        list($discounts, $totalAmountResult, $diffResult) = $mock->collectDiscounts($totalAmount, $diff, $paymentOnly);

        $this->assertEquals($diffResult, $diff);

        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;

        $this->assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        $this->assertEquals($expectedTotalAmount, $totalAmountResult);
    }

    /**
     * @test
     */
    public function collectDiscounts_BssStoreCredit()
    {
        $appliedDiscount = 10; // $
        $mock = $this->getCurrentMock();
        $shippingAddress = $this->getShippingAddress();

        $quote = $this->getQuoteMock($this->getBillingAddress(), $shippingAddress);

        $quote->method('getBoltParentQuoteId')
            ->willReturn(999999);

        $mock->expects($this->once())
            ->method('getQuoteById')
            ->willReturn($quote);


        $mock->expects($this->once())
            ->method('getLastImmutableQuote')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getCalculationAddress')
            ->with($quote)
            ->willReturn($shippingAddress);

        $shippingAddress->expects($this->any())
            ->method('getCouponCode')
            ->willReturn(false);

        $shippingAddress->expects($this->any())
            ->method('getDiscountAmount')
            ->willReturn(false);

        $quote->expects($this->once())
            ->method('getUseCustomerBalance')
            ->willReturn(false);

        $this->discountHelper->expects($this->once())
            ->method('isMirasvitStoreCreditAllowed')
            ->with($quote)
            ->willReturn(false);

        $quote->expects($this->once())
            ->method('getUseRewardPoints')
            ->willReturn(false);

        $this->discountHelper->expects($this->never())
            ->method('getAmastyPayForEverything');

        $this->discountHelper->expects($this->never())
            ->method('getMageplazaGiftCardCodesFromSession');

        $this->discountHelper->expects($this->never())
            ->method('getUnirgyGiftCertBalanceByCode');

        $quote->expects($this->any())
            ->method('getTotals')
            ->willReturn([DiscountHelper::BSS_STORE_CREDIT => $this->quoteAddressTotal]);

        $this->discountHelper->expects($this->once())
            ->method('isBssStoreCreditAllowed')
            ->willReturn(true);

        $this->discountHelper->expects($this->once())
            ->method('getBssStoreCreditAmount')
            ->withAnyParameters()
            ->willReturn($appliedDiscount);

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;

        list($discounts, $totalAmountResult, $diffResult) = $mock->collectDiscounts($totalAmount, $diff, $paymentOnly);

        $this->assertEquals($diffResult, $diff);

        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;

        $this->assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        $this->assertEquals($expectedTotalAmount, $totalAmountResult);
    }

    /**
     * @test
     */
    public function collectDiscounts_Amasty_Giftcard()
    {

        $mock = $this->getCurrentMock();
        $shippingAddress = $this->getShippingAddress();

        $quote = $this->getQuoteMock($this->getBillingAddress(), $shippingAddress);

        $quote->method('getBoltParentQuoteId')
            ->willReturn(999999);

        $mock->expects($this->once())
            ->method('getQuoteById')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getLastImmutableQuote')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getCalculationAddress')
            ->with($quote)
            ->willReturn($shippingAddress);

        $shippingAddress->expects($this->any())
            ->method('getCouponCode')
            ->willReturn(false);

        $shippingAddress->expects($this->any())
            ->method('getDiscountAmount')
            ->willReturn(false);

        $quote->expects($this->once())
            ->method('getUseCustomerBalance')
            ->willReturn(false);

        $this->discountHelper->expects($this->once())
            ->method('isMirasvitStoreCreditAllowed')
            ->with($quote)
            ->willReturn(false);

        $quote->expects($this->once())
            ->method('getUseRewardPoints')
            ->willReturn(false);

        $this->discountHelper->expects($this->never())
            ->method('getAheadworksStoreCredit');

        $this->discountHelper->expects($this->never())
            ->method('getMageplazaGiftCardCodesFromSession');

        $this->discountHelper->expects($this->never())
            ->method('getUnirgyGiftCertBalanceByCode');

        $appliedDiscount = 10; // $
        $amastyGiftCode = "12345";

        $this->discountHelper->expects($this->once())
            ->method('getAmastyPayForEverything')
            ->willReturn(true);
        $this->discountHelper->expects($this->once())
            ->method('getAmastyGiftCardCodesFromTotals')
            ->willReturn($amastyGiftCode);

        $this->discountHelper->expects($this->once())
            ->method('getAmastyGiftCardCodesCurrentValue')
            ->with($amastyGiftCode)
            ->willReturn($appliedDiscount);


        $this->quoteAddressTotal->expects($this->once())
        ->method('getValue')
        ->willReturn($appliedDiscount);

        $quote->expects($this->any())
            ->method('getTotals')
            ->willReturn([DiscountHelper::AMASTY_GIFTCARD => $this->quoteAddressTotal]);

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;

        list($discounts, $totalAmountResult, $diffResult) = $mock->collectDiscounts($totalAmount, $diff, $paymentOnly);

        $this->assertEquals($diffResult, $diff);

        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;

        $this->assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        $this->assertEquals($expectedTotalAmount, $totalAmountResult);
    }

    /**
     * @test
     */
    public function collectDiscounts_Mageplaza_GiftCard()
    {

        $mock = $this->getCurrentMock();
        $shippingAddress = $this->getShippingAddress();

        $quote = $this->getQuoteMock($this->getBillingAddress(), $shippingAddress);

        $quote->method('getBoltParentQuoteId')
            ->willReturn(999999);

        $mock->expects($this->once())
            ->method('getQuoteById')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getLastImmutableQuote')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getCalculationAddress')
            ->with($quote)
            ->willReturn($shippingAddress);

        $shippingAddress->expects($this->any())
            ->method('getCouponCode')
            ->willReturn(false);

        $shippingAddress->expects($this->any())
            ->method('getDiscountAmount')
            ->willReturn(false);

        $quote->expects($this->once())
            ->method('getUseCustomerBalance')
            ->willReturn(false);

        $this->discountHelper->expects($this->once())
            ->method('isMirasvitStoreCreditAllowed')
            ->with($quote)
            ->willReturn(false);

        $quote->expects($this->once())
            ->method('getUseRewardPoints')
            ->willReturn(false);

        $this->discountHelper->expects($this->never())
            ->method('getAheadworksStoreCredit');

        $this->discountHelper->expects($this->never())
            ->method('getUnirgyGiftCertBalanceByCode');

        $appliedDiscount = 10; // $
        $mageplazaGiftCode = "12345";

        $this->discountHelper->expects($this->once())
            ->method('getMageplazaGiftCardCodesFromSession')
            ->willReturn($mageplazaGiftCode);

        $this->discountHelper->expects($this->once())
            ->method('getMageplazaGiftCardCodesCurrentValue')
            ->with($mageplazaGiftCode)
            ->willReturn($appliedDiscount);


        $this->quoteAddressTotal->expects($this->once())
            ->method('getValue')
            ->willReturn($appliedDiscount);

        $quote->expects($this->any())
            ->method('getTotals')
            ->willReturn([DiscountHelper::MAGEPLAZA_GIFTCARD => $this->quoteAddressTotal]);

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;

        list($discounts, $totalAmountResult, $diffResult) = $mock->collectDiscounts($totalAmount, $diff, $paymentOnly);

        $this->assertEquals($diffResult, $diff);

        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;

        $this->assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        $this->assertEquals($expectedTotalAmount, $totalAmountResult);
    }

    /**
     * @test
     */
    public function collectDiscounts_Unirgy_Giftcert()
    {

        $mock = $this->getCurrentMock();
        $shippingAddress = $this->getShippingAddress();

        $quote = $this->getQuoteMock($this->getBillingAddress(), $shippingAddress);

        $quote->method('getBoltParentQuoteId')
            ->willReturn(999999);

        $mock->expects($this->once())
            ->method('getQuoteById')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getLastImmutableQuote')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getCalculationAddress')
            ->with($quote)
            ->willReturn($shippingAddress);

        $shippingAddress->expects($this->any())
            ->method('getCouponCode')
            ->willReturn(false);

        $shippingAddress->expects($this->any())
            ->method('getDiscountAmount')
            ->willReturn(false);

        $quote->expects($this->once())
            ->method('getUseCustomerBalance')
            ->willReturn(false);

        $this->discountHelper->expects($this->once())
            ->method('isMirasvitStoreCreditAllowed')
            ->with($quote)
            ->willReturn(false);

        $quote->expects($this->once())
            ->method('getUseRewardPoints')
            ->willReturn(false);

        $this->discountHelper->expects($this->never())
            ->method('getAheadworksStoreCredit');

        $appliedDiscount = 10; // $
        $unirgyGiftcertCode = "12345";

        $quote->expects($this->any())
            ->method("getData")
            ->with("giftcert_code")
            ->willReturn($unirgyGiftcertCode);

        $this->discountHelper->expects($this->once())
            ->method('getUnirgyGiftCertBalanceByCode')
            ->with($unirgyGiftcertCode)
            ->willReturn($appliedDiscount);

        $this->quoteAddressTotal->expects($this->once())
            ->method('getValue')
            ->willReturn($appliedDiscount);

        $quote->expects($this->any())
            ->method('getTotals')
            ->willReturn([DiscountHelper::UNIRGY_GIFT_CERT => $this->quoteAddressTotal]);

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;

        list($discounts, $totalAmountResult, $diffResult) = $mock->collectDiscounts($totalAmount, $diff, $paymentOnly);

        $this->assertEquals($diffResult, $diff);

        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;

        $this->assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        $this->assertEquals($expectedTotalAmount, $totalAmountResult);
    }

    /**
     * @test
     */
    public function collectDiscounts_GiftVoucher()
    {

        $mock = $this->getCurrentMock();
        $shippingAddress = $this->getShippingAddress();

        $quote = $this->getQuoteMock($this->getBillingAddress(), $shippingAddress);

        $quote->method('getBoltParentQuoteId')
            ->willReturn(999999);

        $mock->expects($this->once())
            ->method('getQuoteById')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getLastImmutableQuote')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getCalculationAddress')
            ->with($quote)
            ->willReturn($shippingAddress);

        $quote->expects($this->once())
            ->method('getUseCustomerBalance')
            ->willReturn(false);

        $this->discountHelper->expects($this->once())
            ->method('isMirasvitStoreCreditAllowed')
            ->with($quote)
            ->willReturn(false);

        $quote->expects($this->once())
            ->method('getUseRewardPoints')
            ->willReturn(false);

        $this->discountHelper->expects($this->never())
            ->method('getAheadworksStoreCredit');

        $appliedDiscount = 10; // $
        $giftVaucher = "12345";

        $shippingAddress->expects($this->any())
            ->method('getCouponCode')
            ->willReturn($giftVaucher);

        $this->quoteAddressTotal->expects($this->once())
            ->method('getValue')
            ->willReturn($appliedDiscount);

        $this->quoteAddressTotal->expects($this->once())
            ->method('getTitle')
            ->willReturn("Gift Voucher");

        $shippingAddress->expects($this->once())
            ->method('getDiscountAmount')
            ->willReturn($appliedDiscount);

        $quote->expects($this->any())
            ->method('getTotals')
            ->willReturn([DiscountHelper::GIFT_VOUCHER => $this->quoteAddressTotal]);

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;

        list($discounts, $totalAmountResult, $diffResult) = $mock->collectDiscounts($totalAmount, $diff, $paymentOnly);

        $this->assertEquals($diffResult, $diff);

        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;

        $this->assertEquals($expectedDiscountAmount, $discounts[1]['amount']);
        $this->assertEquals($expectedTotalAmount, $totalAmountResult);
    }

    /**
     * @test
     */
    public function collectDiscounts_OtherDiscount()
    {

        $mock = $this->getCurrentMock();
        $shippingAddress = $this->getShippingAddress();

        $quote = $this->getQuoteMock($this->getBillingAddress(), $shippingAddress);

        $quote->method('getBoltParentQuoteId')
            ->willReturn(999999);

        $mock->expects($this->once())
            ->method('getQuoteById')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getLastImmutableQuote')
            ->willReturn($quote);

        $mock->expects($this->once())
            ->method('getCalculationAddress')
            ->with($quote)
            ->willReturn($shippingAddress);

        $quote->expects($this->once())
            ->method('getUseCustomerBalance')
            ->willReturn(false);

        $this->discountHelper->expects($this->once())
            ->method('isMirasvitStoreCreditAllowed')
            ->with($quote)
            ->willReturn(false);

        $quote->expects($this->once())
            ->method('getUseRewardPoints')
            ->willReturn(false);

        $this->discountHelper->expects($this->never())
            ->method('getAheadworksStoreCredit');

        $shippingAddress->expects($this->any())
            ->method('getDiscountAmount')
            ->willReturn(false);

        $shippingAddress->expects($this->any())
            ->method('getCouponCode')
            ->willReturn(false);

        $appliedDiscount = 10; // $
        $discountCode = "12345";

        $this->quoteAddressTotal->expects($this->once())
            ->method('getValue')
            ->willReturn($appliedDiscount);

        $this->quoteAddressTotal->expects($this->once())
            ->method('getTitle')
            ->willReturn("Other Discount");

        $quote->expects($this->any())
            ->method('getTotals')
            ->willReturn([DiscountHelper::GIFT_VOUCHER_AFTER_TAX => $this->quoteAddressTotal]);

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;

        list($discounts, $totalAmountResult, $diffResult) = $mock->collectDiscounts($totalAmount, $diff, $paymentOnly);

        $this->assertEquals($diffResult, $diff);

        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;

        $this->assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        $this->assertEquals($expectedTotalAmount, $totalAmountResult);
    }

}
