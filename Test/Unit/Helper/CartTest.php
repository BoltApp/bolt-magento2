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
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Model\Address;
use Magento\Framework\Registry;
use Magento\Quote\Model\Quote;
use Magento\Framework\Exception\NoSuchEntityException;
use \PHPUnit\Framework\TestCase;
use Magento\Framework\App\Helper\Context as ContextHelper;
use Magento\Framework\Session\SessionManager as CheckoutSession;
use Magento\Catalog\Model\ProductRepository;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Helper\Context;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\DataObjectFactory;
use Magento\Catalog\Helper\ImageFactory;
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
use Magento\Quote\Api\CartManagementInterface;
use Magento\Framework\DataObject;
use Bolt\Boltpay\Model\Request;
use Magento\Customer\Model\Customer;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Framework\Webapi\Exception as WebapiException;

/**
 * Class ConfigTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper
 * @coversDefaultClass \Bolt\Boltpay\Helper\Cart
 */
class CartTest extends TestCase
{
    const QUOTE_ID = 1001;
    const IMMUTABLE_QUOTE_ID = 1001;
    const CUSTOMER_ID = 234;
    const PARENT_QUOTE_ID = 1000;
    const PRODUCT_ID = 20102;
    const PRODUCT_PRICE = 100;
    const ORDER_ID = 100010001;
    const STORE_ID = 1;
    const CACHE_IDENTIFIER = 'de6571d30123102e4a49a9483881a05f';
    const PRODUCT_SKU = 'TestProduct';
    const SUPER_ATTRIBUTE = ["93" => "57", "136" => "383"];
    const API_KEY = 'c2ZkKs4Bd2GKMtzRRqB73dFtT5QtMQRv';
    const SIGNATURE = 'ZGEvY22bckLNUuZJZguEt2qZvrsyK8C6';

    private $contextHelper;
    private $checkoutSession;
    private $apiHelper;
    private $configHelper;
    private $customerSession;
    private $logHelper;
    private $bugsnag;
    private $imageHelperFactory;
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
    /** @var CartManagementInterface */
    private $quoteManagement;
    private $hookHelper;
    private $customerRepository;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $quoteMock;

    /**
     * @var CustomerRepository|\PHPUnit\Framework\MockObject\MockObject
     */
    private $coreRegistry;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->contextHelper = $this->createMock(ContextHelper::class);
        $this->quoteMock = $this->createMock(Quote::class);
        $this->checkoutSession = $this->createPartialMock(CheckoutSession::class, ['getQuote']);
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

        $imageHelper = $this->createMock(\Magento\Catalog\Helper\Image::class);
        $imageHelper->method('init')->willReturnSelf();
        $imageHelper->method('getUrl')->willReturn('no-image');

        $this->imageHelperFactory = $this->createMock(ImageFactory::class);
        $this->imageHelperFactory->method('create')->willReturn($imageHelper);

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
        $this->quoteManagement = $this->createMock(CartManagementInterface::class);
        $this->hookHelper = $this->createMock(HookHelper::class);
        $this->customerRepository = $this->createMock(CustomerRepository::class);
        $this->coreRegistry = $this->createMock(Registry::class);
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
            $this->imageHelperFactory,
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
            $this->resourceConnection,
            $this->quoteManagement,
            $this->hookHelper,
            $this->customerRepository,
            $this->coreRegistry
        );

        $paymentOnly = false;
        $placeOrderPayload = '';
        $immutableQuote = $quote;

        $result = $currentMock->getCartData($paymentOnly, $placeOrderPayload, $immutableQuote);

        $expected = [
            'order_reference' => self::PARENT_QUOTE_ID,
            'display_id' => '100010001 / '.self::QUOTE_ID,
            'currency' => 'USD',
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
        $quoteItem = $this->getQuoteItemMock();

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
            ->willReturn('USD');
        $quote->method('collectTotals')
            ->willReturnSelf();
        $quote->expects($this->any())
            ->method('getStoreId')
            ->will($this->returnValue("1"));

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
            'getCalculationAddress',
            'doesOrderExist'
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
    public function getBoltpayOrderCachingDisabled()
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
    public function getBoltpayOrder_IfOrderExists()
    {
        $mock = $this->getHelperCartMock();

        $cart = $this->getCart();
        $mock->expects(self::once())
            ->method('getCartData')
            ->with(false, '')
            ->willReturn($cart);

        $mock->expects(self::once())
            ->method('doesOrderExist')
            ->with($cart)
            ->willReturn(true);

        $mock->expects(self::never())
            ->method('getSessionQuoteStoreId')
            ->willReturn(self::STORE_ID);

        $mock->expects(self::never())
            ->method('isBoltOrderCachingEnabled')
            ->with(self::STORE_ID)
            ->willReturn(false);

         $mock->getBoltpayOrder(false, '');
    }

    /**
     * @test
     */
    public function getBoltpayOrderNotCached()
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
    public function getBoltpayOrderCachedQuoteAvailable()
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
    public function getBoltpayOrderCachedQuoteNotAvailable()
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
    protected function getCurrentMock($methods = array())
    {
        $methods = array_merge([
            'getLastImmutableQuote',
            'getCalculationAddress',
            'getQuoteById'
        ], $methods);
        return $this->getMockBuilder(BoltHelperCart::class)
            ->setMethods($methods)
            ->setConstructorArgs([
                $this->contextHelper,
                $this->checkoutSession,
                $this->productRepository,
                $this->apiHelper,
                $this->configHelper,
                $this->customerSession,
                $this->logHelper,
                $this->bugsnag,
                $this->dataObjectFactory,
                $this->imageHelperFactory,
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
                $this->resourceConnection,
                $this->quoteManagement,
                $this->hookHelper,
                $this->customerRepository,
                $this->coreRegistry
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
            ->method('getMageplazaGiftCardCodes');

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
            ->method('getMageplazaGiftCardCodes');

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
            ->method('getMageplazaGiftCardCodes');

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
            ->method('getMageplazaGiftCardCodes');

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
            ->method('getMageplazaGiftCardCodes');

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
            ->method('getMageplazaGiftCardCodes');

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
            ->method('getMageplazaGiftCardCodes');

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
            ->method('getMageplazaGiftCardCodes');

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
     * that collectDiscounts collects Amasty Store Credit if it exists in quote totals
     *
     * @covers ::collectDiscounts
     */
    public function collectDiscounts_withAmastyStoreCreditInQuoteTotals_collectsAmastyStoreCredit()
    {
        $currentMock = $this->getCurrentMock();
        $shippingAddress = $this->getShippingAddress();

        $quote = $this->getQuoteMock($this->getBillingAddress(), $shippingAddress);

        $quote->method('getBoltParentQuoteId')->willReturn(999999);
        $currentMock->expects($this->once())->method('getQuoteById')->willReturn($quote);
        $currentMock->expects($this->once())->method('getLastImmutableQuote')->willReturn($quote);
        $currentMock->expects($this->once())->method('getCalculationAddress')->with($quote)
            ->willReturn($shippingAddress);

        $shippingAddress->expects($this->any())->method('getCouponCode')->willReturn(false);
        $shippingAddress->expects($this->any())->method('getDiscountAmount')->willReturn(false);
        $quote->expects($this->once())->method('getUseCustomerBalance')->willReturn(false);
        $this->discountHelper->expects($this->once())->method('isMirasvitStoreCreditAllowed')->with($quote)
            ->willReturn(false);
        $quote->expects($this->once())->method('getUseRewardPoints')->willReturn(false);
        $this->discountHelper->expects($this->never())->method('getAheadworksStoreCredit');
        $this->discountHelper->expects($this->never())->method('getMageplazaGiftCardCodes');
        $this->discountHelper->expects($this->never())->method('getUnirgyGiftCertBalanceByCode');
        $appliedDiscount = 10; // $
        $this->quoteAddressTotal->expects($this->once())->method('getValue')->willReturn($appliedDiscount);
        $this->quoteAddressTotal->expects($this->once())->method('getTitle')->willReturn('Store Credit');
        $quote->expects($this->any())->method('getTotals')
            ->willReturn([DiscountHelper::AMASTY_STORECREDIT => $this->quoteAddressTotal]);

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;

        list($discounts, $totalAmountResult, $diffResult) = $currentMock->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly
        );
        $this->assertEquals(
            ['description' => 'Store Credit', 'amount' => $appliedDiscount * 100, 'type' => 'fixed_amount'],
            $discounts[0]
        );

        $this->assertEquals($diffResult, $diff);

        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;

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
            ->method('getMageplazaGiftCardCodes')
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

    /**
     * @test
     *      Convert attribute to string if it's a boolean before sending to the Bolt API
     */
    public function getCartItems_AttributeInfoValue_Boolean()
    {
        $color = 'Blue';
        $size = 'S';
        $quoteItemOptions = [
            'attributes_info' => [
                [
                    'label' => 'Size',
                    'value' => $size
                ],
                [
                    'label' => 'Color',
                    'value' => $color
                ]
            ]
        ];
        $productTypeConfigurableMock = $this->getMockBuilder(Configurable::class)
            ->setMethods(['getOrderOptions'])
            ->disableOriginalConstructor()
            ->getMock();
        $productTypeConfigurableMock->method('getOrderOptions')->willReturn($quoteItemOptions);

        $productMock = $this->getMockBuilder(Product::class)
            ->setMethods(['getId', 'getDescription', 'getTypeInstance'])
            ->disableOriginalConstructor()
            ->getMock();
        $productMock->method('getDescription')->willReturn('Product Description');
        $productMock->method('getTypeInstance')->willReturn($productTypeConfigurableMock);

        $quoteItemMock = $this->getQuoteItemMock($productMock);

        list($products, $totalAmount, $diff) = $this->getCurrentMock()->getCartItems('USD', [$quoteItemMock], self::STORE_ID);

        $this->assertCount(1, $products);
        $this->assertArrayHasKey('properties', $products[0]);
        $this->assertCount(2, $products[0]['properties']);
        $this->assertInternalType('string', $products[0]['properties'][0]->value);
        $this->assertEquals($size, $products[0]['size']);
        $this->assertEquals($color, $products[0]['color']);
    }

    /**
     * @param null $product
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getQuoteItemMock($product = null)
    {
        $quoteItem = $this->getMockBuilder(\Magento\Quote\Model\Quote\Item::class)
            ->setMethods([
                'getSku',
                'getQty',
                'getCalculationPrice',
                'getName',
                'getIsVirtual',
                'getProductId',
                'getProduct'
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
            ->willReturn($product ? $product : $this->getProductMock());

        return $quoteItem;
    }

    private function createCartByRequest_GetExpectedCartData() {
        return [
            'order_reference' => SELF::QUOTE_ID,
            'display_id' => SELF::ORDER_ID.' / '.SELF::QUOTE_ID,
            'currency' => 'USD',
            'items' => [
                [ 'reference' => SELF::PRODUCT_ID,
                    'name' => 'Affirm Water Bottle ',
                    'total_amount' => SELF::PRODUCT_PRICE,
                    'unit_price' => SELF::PRODUCT_PRICE,
                    'quantity' => 1,
                    'sku' => SELF::PRODUCT_SKU,
                    'type' => 'physical',
                    'description' => 'Product description',
                ],
            ],
            'discounts' => [],
            'total_amount' => SELF::PRODUCT_PRICE,
            'tax_amount' => 0,
        ];
    }

    private function createCartByRequest_GetBaseRequest() {
        return [
            'type' => 'cart.create',
            'items' =>
                [
                    [
                        'reference' => SELF::PRODUCT_ID,
                        'name' => 'Product name',
                        'description' => NULL,
                        'options' => json_encode(['storeId'=>SELF::STORE_ID]),
                        'total_amount' => SELF::PRODUCT_PRICE,
                        'unit_price' => SELF::PRODUCT_PRICE,
                        'tax_amount' => 0,
                        'quantity' => 1,
                        'uom' => NULL,
                        'upc' => NULL,
                        'sku' => NULL,
                        'isbn' => NULL,
                        'brand' => NULL,
                        'manufacturer' => NULL,
                        'category' => NULL,
                        'tags' => NULL,
                        'properties' => NULL,
                        'color' => NULL,
                        'size' => NULL,
                        'weight' => NULL,
                        'weight_unit' => NULL,
                        'image_url' => NULL,
                        'details_url' => NULL,
                        'tax_code' => NULL,
                        'type' => 'unknown'
                    ]
                ],
            'currency' => 'USD',
            'metadata' => NULL,
        ];
    }
    private function onceOrAny($isSuccessfulCase) {
        if ($isSuccessfulCase) {
            return $this->once();
        } else {
            return $this->any();
        }
    }

    private function createCartByRequest_CreateQuoteMock($isSuccessfulCase = true, $options = null) {
        if (is_null($options)) {
            $options = ['qty'=>1];
        }
        if ($isSuccessfulCase) {
            $expects = $this->once();
        } else {
            $expects = $this->any();
        }
        $this->quoteManagement = $this->getMockForAbstractClass(
            \Magento\Quote\Api\CartManagementInterface::class,
            [],
            '',
            false,
            true,
            true,
            ['createEmptyCart']
        );
        $this->quoteManagement->expects($this->once())
            ->method('createEmptyCart')
            ->willReturn(SELF::QUOTE_ID);

        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->getMock();

        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(['addProduct','reserveOrderId','collectTotals','save','getId','getReservedOrderId','setBoltReservedOrderId','assignCustomer','setBoltParentQuoteId','setIsActive','setStoreId'])
            ->disableOriginalConstructor()
            ->getMock();
        $quote->expects($this->once())
            ->method('setBoltParentQuoteId')
            ->with(SELF::QUOTE_ID);
        $quote->expects($this->onceOrAny($isSuccessfulCase))
            ->method('addProduct')
            ->with(
                $product,
                new \Magento\Framework\DataObject($options)
            );
        $quote->expects($this->onceOrAny($isSuccessfulCase))
            ->method('setStoreId')
            ->with(self::STORE_ID);
        $quote->expects($this->onceOrAny($isSuccessfulCase))
            ->method('reserveOrderId');
        $quote->expects($this->onceOrAny($isSuccessfulCase))
            ->method('getReservedOrderId')
            ->willReturn(self::ORDER_ID);
        $quote->expects($this->onceOrAny($isSuccessfulCase))
            ->method('setBoltReservedOrderId')
            ->with(self::ORDER_ID);
        $quote->expects($this->onceOrAny($isSuccessfulCase))
            ->method('setIsActive')
            ->with(false);
        $quote->expects($this->onceOrAny($isSuccessfulCase))
            ->method('collectTotals')
            ->willReturnSelf();
        $quote->expects($this->onceOrAny($isSuccessfulCase))
            ->method('save');

        $this->quoteFactory = $this->getMockBuilder(QuoteFactory::class)
            ->setMethods(['create','load'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteFactory->method('create')
            ->withAnyParameters()
            ->willReturnSelf();
        $this->quoteFactory->method('load')
            ->with(SELF::QUOTE_ID)
            ->willReturn($quote);

        $this->productRepository = $this->getMockBuilder(ProductRepository::class)
            ->setMethods(['getbyId'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->productRepository->method('getbyId')
            ->with(SELF::PRODUCT_ID)
            ->willReturn($product);
        return $quote;
    }

    /**
     * @test
     */
    public function createCartByRequest_GuestUser_SimpleProduct() {
        $request = $this->createCartByRequest_GetBaseRequest();

        $quote = $this->createCartByRequest_CreateQuoteMock();

        $expectedCartData = $this->createCartByRequest_GetExpectedCartData();

        $cartMock = $this->getCurrentMock(['getCartData']);
        $cartMock->expects($this->once())
            ->method('getCartData')
            ->with(false,'',$quote)
            ->willReturn($expectedCartData);

        $this->assertEquals($expectedCartData, $cartMock->createCartByRequest($request));
    }

    /**
     * @test
     */
    public function createCartByRequest_GuestUser_ConfigurableProduct() {
        $request = $this->createCartByRequest_GetBaseRequest();
        $request['items'][0]['options'] = json_encode([
            "product" => SELF::PRODUCT_ID,
            "selected_configurable_option" => "",
            "item" => SELF::PRODUCT_ID,
            "related_product" => "",
            "form_key" => "8xaF8eKXVaiRVM53",
            "super_attribute" => SELF::SUPER_ATTRIBUTE,
            "qty" => "1",
            'storeId' => SELF::STORE_ID], JSON_FORCE_OBJECT);

        $options = [
            "product" => SELF::PRODUCT_ID,
            "selected_configurable_option" => "",
            "related_product" => "",
            "item" => SELF::PRODUCT_ID,
            "super_attribute" => SELF::SUPER_ATTRIBUTE,
            "qty" => 1
        ];

        $quote = $this->createCartByRequest_CreateQuoteMock(true, $options);

        $expectedCartData = $this->createCartByRequest_GetExpectedCartData();

        $cartMock = $this->getCurrentMock(['getCartData']);
        $cartMock->expects($this->once())
            ->method('getCartData')
            ->with(false,'',$quote)
            ->willReturn($expectedCartData);

        $this->assertEquals($expectedCartData, $cartMock->createCartByRequest($request));
    }


    private function createCartByRequest_TuneMocksForSignature($expected_payload) {
        $this->hookHelper->method('verifySignature')
            ->will($this->returnCallback(function($payload, $hmac_header) use ($expected_payload) {
                return $payload==json_encode( $expected_payload ) && $hmac_header == 'correct_signature';
            }));
    }

    private function createCartByRequest_CreateCustomerMock() {
        $customer = $this->createMock(\Magento\Customer\Api\Data\CustomerInterface::class);
        $this->customerRepository->method('getById')
            ->will($this->returnCallback(function($user_id) use ($customer) {
                if ($user_id<>1) {
                    throw new \Exception;
                }
                return $customer;
            }));
        return $customer;
    }

    /**
     * @test
     */
    public function createCartByRequest_LoggedInUser() {
        $request = $this->createCartByRequest_GetBaseRequest();
        $payload = ['user_id'=>1, 'timestamp' => time()];
        $request['metadata']['encrypted_user_id'] = json_encode($payload + ['signature'=>'correct_signature']);
        $this->createCartByRequest_TuneMocksForSignature($payload);
        $customer = $this->createCartByRequest_CreateCustomerMock();

        $quote = $this->createCartByRequest_CreateQuoteMock();
        $quote->expects($this->once())->method('assignCustomer')->with($customer);

        $expectedCartData = $this->createCartByRequest_GetExpectedCartData();

        $cartMock = $this->getCurrentMock(['getCartData']);
        $cartMock->expects($this->once())
            ->method('getCartData')
            ->with(false,'',$quote)
            ->willReturn($expectedCartData);

        $this->assertEquals($expectedCartData, $cartMock->createCartByRequest($request));
    }

    /**
     * @test
     */
    public function createCartByRequest_LoggedInUser_IncorrectEncryptedUserID() {
        $request = $this->createCartByRequest_GetBaseRequest();
        $payload = ['user_id'=>1];
        $request['metadata']['encrypted_user_id'] = json_encode($payload + ['signature'=>'correct_signature']);
        $this->createCartByRequest_TuneMocksForSignature($payload);
        $customer = $this->createCartByRequest_CreateCustomerMock();

        $quote = $this->createCartByRequest_CreateQuoteMock(false);

        try{
            $this->getCurrentMock()->createCartByRequest($request);
            $this->fail("Expected exception not thrown");
        }catch(WebapiException $e){
            $this->assertEquals(6306, $e->getCode());
            $this->assertEquals("Incorrect encrypted_user_id", $e->getMessage());
        }
    }

    /**
     * @test
     */
    public function createCartByRequest_LoggedInUser_IncorrectSignature() {
        $request = $this->createCartByRequest_GetBaseRequest();
        $payload = ['user_id'=>1, 'timestamp' => time()];
        $request['metadata']['encrypted_user_id'] = json_encode($payload + ['signature'=>'incorrect_signature']);
        $this->createCartByRequest_TuneMocksForSignature($payload);
        $customer = $this->createCartByRequest_CreateCustomerMock();

        $quote = $this->createCartByRequest_CreateQuoteMock(false);

        try{
            $this->getCurrentMock()->createCartByRequest($request);
            $this->fail("Expected exception not thrown");
        }catch(WebapiException $e){
            $this->assertEquals(6306, $e->getCode());
            $this->assertEquals("Incorrect signature", $e->getMessage());
        }
    }

    /**
     * @test
     */
    public function createCartByRequest_LoggedInUser_OutdatedEnctyptedUserId() {
        $request = $this->createCartByRequest_GetBaseRequest();
        $payload = ['user_id'=>1, 'timestamp' => time()-3600-1];
        $request['metadata']['encrypted_user_id'] = json_encode($payload + ['signature'=>'correct_signature']);
        $this->createCartByRequest_TuneMocksForSignature($payload);
        $customer = $this->createCartByRequest_CreateCustomerMock();

        $quote = $this->createCartByRequest_CreateQuoteMock(false);

        try{
            $this->getCurrentMock()->createCartByRequest($request);
            $this->fail("Expected exception not thrown");
        }catch(WebapiException $e){
            $this->assertEquals(6306, $e->getCode());
            $this->assertEquals("Outdated encrypted_user_id", $e->getMessage());
        }
    }

    /**
     * @test
     */
    public function createCartByRequest_LoggedInUser_WrongUserId() {
        $request = $this->createCartByRequest_GetBaseRequest();
        $payload = ['user_id'=>2, 'timestamp' => time()];
        $request['metadata']['encrypted_user_id'] = json_encode($payload + ['signature'=>'correct_signature']);
        $this->createCartByRequest_TuneMocksForSignature($payload);
        $customer = $this->createCartByRequest_CreateCustomerMock();

        $quote = $this->createCartByRequest_CreateQuoteMock(false);

        try{
            $this->getCurrentMock()->createCartByRequest($request);
            $this->fail("Expected exception not thrown");
        }catch(WebapiException $e){
            $this->assertEquals(6306, $e->getCode());
            $this->assertEquals("Incorrect user_id", $e->getMessage());
        }
    }
    /**
     * @test
     * that getHints returns virtual_terminal_mode set to true when provided checkout type is admin
     * @covers ::getHints
     *
     * @throws NoSuchEntityException
     */
    public function getHints_whenCheckoutTypeIsAdmin_setsVirtualTerminalModeToTrue()
    {
        $result = $this->getCurrentMock()->getHints(null, 'admin');
        static::assertTrue($result['virtual_terminal_mode']);
    }
    /**
     * @test
     * that getHints returns encrypted user id if checkout type is product and customer is logged in
     *
     * @covers ::getHints
     *
     * @throws NoSuchEntityException from tested method
     */
    public function getHints_whenCheckoutTypeIsProductAndCustomerLoggedIn_returnsHintsWithEncryptedUserId()
    {
        $customerMock = $this->createPartialMock(
            \Magento\Customer\Model\Customer::class,
            [
                'getId',
                'getEmail',
                'getDefaultBillingAddress',
                'getDefaultShippingAddress'
            ]
        );
        $customerMock->expects(self::atLeastOnce())->method('getId')->willReturn(self::CUSTOMER_ID);
        $customerMock->expects(self::atLeastOnce())->method('getEmail')->willReturn('test@bolt.com');
        $this->customerSession->expects(static::once())->method('isLoggedIn')->willReturn(true);
        $this->customerSession->expects(static::atLeastOnce())->method('getCustomer')->willReturn($customerMock);
        $signRequest = ['merchant_user_id' => self::CUSTOMER_ID];
        $requestMock = $this->createMock(Request::class);
        $responseMock = $this->createPartialMock(Response::class, ['getResponse']);
        $this->configHelper->expects(static::once())->method('getApiKey')->willReturn(self::API_KEY);
        $requestDataMock = $this->createPartialMock(DataObject::class, ['setApiData', 'setDynamicApiUrl', 'setApiKey']);
        $this->dataObjectFactory->expects(static::once())->method('create')->willReturn($requestDataMock);
        $requestDataMock->expects(static::once())->method('setApiData')->with($signRequest);
        $requestDataMock->expects(static::once())->method('setDynamicApiUrl')->with(ApiHelper::API_SIGN);
        $requestDataMock->expects(static::once())->method('setApiKey')->with(self::API_KEY);
        $this->apiHelper->expects(static::once())->method('buildRequest')->with($requestDataMock)
            ->willReturn($requestMock);
        $this->apiHelper->expects(static::once())->method('sendRequest')->with($requestMock)->willReturn($responseMock);
        $signedMerchantUserId = [
            'merchant_user_id' => self::CUSTOMER_ID,
            'signature'        => self::SIGNATURE,
            'nonce'            => 999999
        ];
        $responseMock->expects(static::once())->method('getResponse')->willReturn((object)$signedMerchantUserId);
        $hints = $this->getCurrentMock()->getHints(null, 'product');
        static::assertEquals((object)['email' => 'test@bolt.com'], $hints['prefill']);
        static::assertEquals($signedMerchantUserId, $hints['signed_merchant_user_id']);
        $encryptedUserId = json_decode($hints['metadata']['encrypted_user_id'], true);
        self::assertEquals(self::CUSTOMER_ID, $encryptedUserId['user_id']);
    }

    /**
     * @test
     * that getHints will return hints from customer default shipping address when quote is not virtual
     *
     * @covers ::getHints
     *
     * @throws NoSuchEntityException from tested method
     * @throws \ReflectionException if unable to create mock
     */
    public function getHints_withNonVirtualQuoteAndCustomerLoggedIn_willReturnCustomerShippingAddressHints()
    {
        $customerMock = $this->createPartialMock(
            \Magento\Customer\Model\Customer::class,
            [
                'getId',
                'getEmail',
                'getDefaultBillingAddress',
                'getDefaultShippingAddress'
            ]
        );
        $customerMock->expects(self::atLeastOnce())->method('getId')->willReturn(self::CUSTOMER_ID);
        $customerMock->expects(self::atLeastOnce())->method('getEmail')->willReturn('test@bolt.com');
        $this->customerSession->expects(static::once())->method('isLoggedIn')->willReturn(true);
        $this->customerSession->expects(static::atLeastOnce())->method('getCustomer')->willReturn($customerMock);
        $signRequest = ['merchant_user_id' => self::CUSTOMER_ID];
        $requestMock = $this->createMock(Request::class);
        $responseMock = $this->createPartialMock(Response::class, ['getResponse']);
        $this->configHelper->expects(static::once())->method('getApiKey')->willReturn(self::API_KEY);
        $requestDataMock = $this->createPartialMock(DataObject::class, ['setApiData', 'setDynamicApiUrl', 'setApiKey']);
        $this->dataObjectFactory->expects(static::once())->method('create')->willReturn($requestDataMock);
        $requestDataMock->expects(static::once())->method('setApiData')->with($signRequest);
        $requestDataMock->expects(static::once())->method('setDynamicApiUrl')->with(ApiHelper::API_SIGN);
        $requestDataMock->expects(static::once())->method('setApiKey')->with(self::API_KEY);
        $this->apiHelper->expects(static::once())->method('buildRequest')->with($requestDataMock)
            ->willReturn($requestMock);
        $this->apiHelper->expects(static::once())->method('sendRequest')->with($requestMock)->willReturn($responseMock);
        $signedMerchantUserId = [
            'merchant_user_id' => self::CUSTOMER_ID,
            'signature'        => self::SIGNATURE,
            'nonce'            => 999999
        ];
        $responseMock->expects(static::once())->method('getResponse')->willReturn((object)$signedMerchantUserId);
        $shippingAddressMock = $this->createPartialMock(Address::class,
            [
                'getFirstname',
                'getLastname',
                'getEmail',
                'getTelephone',
                'getStreetLine',
                'getCity',
                'getRegion',
                'getPostcode',
                'getCountryId',
            ]);
        $shippingAddressMock->expects(static::once())->method('getFirstname')->willReturn('IntegrationBolt');
        $shippingAddressMock->expects(static::once())->method('getLastname')->willReturn('BoltTest');
        $shippingAddressMock->expects(static::once())->method('getEmail')->willReturn('integration@bolt.com');
        $shippingAddressMock->expects(static::once())->method('getTelephone')->willReturn('132 231 1234');
        $shippingAddressMock->expects(static::exactly(2))->method('getStreetLine')->willReturnOnConsecutiveCalls('228 7th Avenue', '228 7th Avenue1');
        $shippingAddressMock->expects(static::once())->method('getCity')->willReturn('New York');
        $shippingAddressMock->expects(static::once())->method('getRegion')->willReturn('New York');
        $shippingAddressMock->expects(static::once())->method('getPostcode')->willReturn('10011');
        $shippingAddressMock->expects(static::once())->method('getCountryId')->willReturn('1111');
        $customerMock->expects(static::once())->method('getDefaultShippingAddress')->willReturn($shippingAddressMock);
        $hints = $this->getCurrentMock()->getHints(null, 'product');
        static::assertEquals((object)
        [
           'firstName' => 'IntegrationBolt',
           'lastName' => 'BoltTest',
           'email' => 'test@bolt.com',
           'phone' => '132 231 1234',
           'addressLine1' => '228 7th Avenue',
           'addressLine2' => '228 7th Avenue1',
           'city' => 'New York',
           'state' => 'New York',
           'zip' => '10011',
           'country' => '1111',
        ], $hints['prefill']);
        static::assertEquals($signedMerchantUserId, $hints['signed_merchant_user_id']);
        $encryptedUserId = json_decode($hints['metadata']['encrypted_user_id'], true);
        self::assertEquals(self::CUSTOMER_ID, $encryptedUserId['user_id']);
    }

    /**
     * @test
     * that getHints will return hints from customer default billing address when quote is virtual
     *
     * @covers ::getHints
     */
    public function getHints_withVirtualQuoteAndCustomerLoggedIn_willReturnCustomerBillingAddressHints()
    {
        $customerMock = $this->createPartialMock(
            \Magento\Customer\Model\Customer::class,
            [
                'getId',
                'getEmail',
                'getDefaultBillingAddress',
                'getDefaultShippingAddress'
            ]
        );
        $customerMock->expects(self::atLeastOnce())->method('getId')->willReturn(self::CUSTOMER_ID);
        $customerMock->expects(self::atLeastOnce())->method('getEmail')->willReturn('test@bolt.com');
        $this->customerSession->expects(static::once())->method('isLoggedIn')->willReturn(true);
        $this->customerSession->expects(static::atLeastOnce())->method('getCustomer')->willReturn($customerMock);
        $signRequest = ['merchant_user_id' => self::CUSTOMER_ID];
        $requestMock = $this->createMock(Request::class);
        $responseMock = $this->createPartialMock(Response::class, ['getResponse']);
        $this->configHelper->expects(static::once())->method('getApiKey')->willReturn(self::API_KEY);
        $requestDataMock = $this->createPartialMock(DataObject::class, ['setApiData', 'setDynamicApiUrl', 'setApiKey']);
        $this->dataObjectFactory->expects(static::once())->method('create')->willReturn($requestDataMock);
        $requestDataMock->expects(static::once())->method('setApiData')->with($signRequest);
        $requestDataMock->expects(static::once())->method('setDynamicApiUrl')->with(ApiHelper::API_SIGN);
        $requestDataMock->expects(static::once())->method('setApiKey')->with(self::API_KEY);
        $this->apiHelper->expects(static::once())->method('buildRequest')->with($requestDataMock)
            ->willReturn($requestMock);
        $this->apiHelper->expects(static::once())->method('sendRequest')->with($requestMock)->willReturn($responseMock);
        $signedMerchantUserId = [
            'merchant_user_id' => self::CUSTOMER_ID,
            'signature'        => self::SIGNATURE,
            'nonce'            => 999999
        ];
        $responseMock->expects(static::once())->method('getResponse')->willReturn((object)$signedMerchantUserId);
        $billingAddressMock = $this->createPartialMock(Address::class,
            [
                'getFirstname',
                'getLastname',
                'getEmail',
                'getTelephone',
                'getStreetLine',
                'getCity',
                'getRegion',
                'getPostcode',
                'getCountryId',
            ]);
        $billingAddressMock->expects(static::once())->method('getFirstname')->willReturn('IntegrationBolt');
        $billingAddressMock->expects(static::once())->method('getLastname')->willReturn('BoltTest');
        $billingAddressMock->expects(static::once())->method('getEmail')->willReturn('integration@bolt.com');
        $billingAddressMock->expects(static::once())->method('getTelephone')->willReturn('132 231 1234');
        $billingAddressMock->expects(static::exactly(2))->method('getStreetLine')->willReturnOnConsecutiveCalls('228 7th Avenue', '228 7th Avenue1');
        $billingAddressMock->expects(static::once())->method('getCity')->willReturn('New York');
        $billingAddressMock->expects(static::once())->method('getRegion')->willReturn('New York');
        $billingAddressMock->expects(static::once())->method('getPostcode')->willReturn('10011');
        $billingAddressMock->expects(static::once())->method('getCountryId')->willReturn('1111');
        $customerMock->expects(static::once())->method('getDefaultBillingAddress')->willReturn($billingAddressMock);
        $this->checkoutSession->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->method('isVirtual')->willReturn(true);
        $hints = $this->getCurrentMock()->getHints(null, 'multipage');
        static::assertEquals((object)
        [
            'firstName' => 'IntegrationBolt',
            'lastName' => 'BoltTest',
            'email' => 'test@bolt.com',
            'phone' => '132 231 1234',
            'addressLine1' => '228 7th Avenue',
            'addressLine2' => '228 7th Avenue1',
            'city' => 'New York',
            'state' => 'New York',
            'zip' => '10011',
            'country' => '1111',
        ], $hints['prefill']);
    }

    /**
     * @test
     * that getHints returns hints from quote billing address if checkout type is not product and quote is virtual
     *
     * @covers ::getHints
     *
     * @throws NoSuchEntityException from tested method
     */
    public function getHints_withNonProductCheckoutTypeAndVirtualQuote_returnsHintsForQuoteBillingAddress()
    {
        $currentMock = $this->getCurrentMock();
        $currentMock->expects(static::once())->method('getQuoteById')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('isVirtual')->willReturn(true);
        $this->quoteMock->expects(static::once())->method('getBillingAddress')->willReturn($this->getBillingAddress());
        $hints = $currentMock->getHints(self::IMMUTABLE_QUOTE_ID, 'multipage');
        static::assertEquals(
            [
                'prefill' => (object)[
                    'firstName'    => 'IntegrationBolt',
                    'lastName'     => 'BoltTest',
                    'email'        => 'integration@bolt.com',
                    'phone'        => '132 231 1234',
                    'addressLine1' => '228 7th Avenue',
                    'city'         => 'New York',
                    'state'        => 'New York',
                    'zip'          => '10011',
                    'country'      => 'US',
                ]
            ],
            $hints
        );
    }

    /**
     * @test
     * that getHints returns hints from quote shipping address if checkout type is not product and quote is not virtual
     *
     * @covers ::getHints
     *
     * @throws NoSuchEntityException from tested method
     */
    public function getHints_withNonProductCheckoutTypeAndNonVirtualQuote_returnsHintsForQuoteShippingAddress()
    {
        $currentMock = $this->getCurrentMock();
        $currentMock->expects(static::once())->method('getQuoteById')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('isVirtual')->willReturn(false);
        $this->quoteMock->expects(static::once())->method('getShippingAddress')->willReturn($this->getShippingAddress());
        $hints = $currentMock->getHints(self::IMMUTABLE_QUOTE_ID, 'multipage');
        static::assertEquals(
            [
                'prefill' => (object)[
                    'firstName'    => 'IntegrationBolt',
                    'lastName'     => 'BoltTest',
                    'email'        => 'integration@bolt.com',
                    'phone'        => '132 231 1234',
                    'addressLine1' => '228 7th Avenue',
                    'city'         => 'New York',
                    'state'        => 'New York',
                    'zip'          => '10011',
                    'country'      => 'US',
                ]
            ],
            $hints
        );
    }

    /**
     * @test
     * that getHints skips pre-fill for Apple Pay related data when phone is 8005550111
     *
     * @covers ::getHints
     *
     * @throws NoSuchEntityException from tested method
     */
    public function getHints_withApplePayRelatedDataPhone_skipsPreFill()
    {
        $quoteMock = $this->createPartialMock(Quote::class, ['getCustomerEmail', 'isVirtual', 'getShippingAddress']);
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($quoteMock);
        $quoteMock->expects(static::once())->method('isVirtual')->willReturn(false);
        $shippingAddress = $this->createPartialMock(Quote\Address::class, ['getTelephone']);
        $shippingAddress->expects(static::once())->method('getTelephone')->willReturn('8005550111');
        $quoteMock->expects(static::once())->method('getCustomerEmail')->willReturn('na@bolt.com');
        $quoteMock->expects(static::once())->method('getShippingAddress')->willReturn($shippingAddress);
        $hints = $this->getCurrentMock()->getHints();
        static::assertEquals((object)[], $hints['prefill']);
    }

    /**
     * @test
     * that getHints skips pre-fill for Apple Pay related data when email is na@bolt.com
     *
     * @covers ::getHints
     *
     * @throws NoSuchEntityException from tested method
     */
    public function getHints_withApplePayRelatedDataEmail_skipsPreFill()
    {
        $quoteMock = $this->createPartialMock(Quote::class, ['getCustomerEmail', 'isVirtual', 'getShippingAddress']);
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($quoteMock);
        $quoteMock->expects(static::once())->method('isVirtual')->willReturn(false);
        $shippingAddress = $this->createPartialMock(Quote\Address::class, ['getEmail']);
        $quoteMock->expects(static::once())->method('getCustomerEmail')->willReturn('na@bolt.com');
        $quoteMock->expects(static::once())->method('getShippingAddress')->willReturn($shippingAddress);
        $hints = $this->getCurrentMock()->getHints();
        static::assertEquals((object)[], $hints['prefill']);
    }

    /**
     * @test
     * that getHints skips pre-fill for Apple Pay related data when address line is tbd
     *
     * @covers ::getHints
     *
     * @throws NoSuchEntityException from tested method
     */
    public function getHints_withApplePayRelatedDataAddressLine_skipsPreFill()
    {
        $quoteMock = $this->createPartialMock(Quote::class, ['isVirtual', 'getShippingAddress']);
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($quoteMock);
        $quoteMock->expects(static::once())->method('isVirtual')->willReturn(false);
        $shippingAddress = $this->getMockBuilder(Quote\Address::class)->setMethods(['getStreetLine'])->disableOriginalConstructor()
            ->getMock();;
        $shippingAddress->method('getStreetLine')
            ->willReturn('tbd');
        $quoteMock->expects(static::once())->method('getShippingAddress')->willReturn($shippingAddress);
        $hints = $this->getCurrentMock()->getHints();
        static::assertEquals((object)[], $hints['prefill']);
    }
    /**
     * @test
     * that getCartData populates registry rule_data when executed from backend
     *
     * @covers ::getCartData
     */
    public function getCartData_fromBackend_initializesRuleData()
    {
        $billingAddress = $this->getBillingAddress();
        $shippingAddress = $this->getShippingAddress();
        $immutableQuote = $this->getQuoteMock($billingAddress, $shippingAddress);
        $this->checkoutSession = $this->createMock(\Magento\Backend\Model\Session\Quote::class);
        $currentMock = $this->getCurrentMock(['isBackendSession', 'getCartItems', 'collectDiscounts']);
        $immutableQuote->method('getAllVisibleItems')->willReturn(true);
        $currentMock->expects($this->once())->method('getCartItems')->willReturn([[['total_amount' => 0]], 0, 0]);

        $storeMock = $this->createMock(\Magento\Store\Model\Store::class);
        $storeMock->method('getId')->willReturn(self::STORE_ID);
        $storeMock->method('getWebsiteId')->willReturn(1);

        $this->checkoutSession->method('getStore')->willReturn($storeMock);
        $this->coreRegistry->expects($this->once())->method('unregister')->with('rule_data');
        $this->coreRegistry->expects($this->once())->method('register')->with(
            'rule_data',
            new DataObject(
                [
                    'store_id'          => self::STORE_ID,
                    'website_id'        => 1,
                    'customer_group_id' => \Magento\Customer\Api\Data\GroupInterface::NOT_LOGGED_IN_ID
                ]
            )
        );
        $immutableQuote->expects($this->once())->method('collectTotals');
        $currentMock->getCartData(false, '', $immutableQuote);
    }
}
