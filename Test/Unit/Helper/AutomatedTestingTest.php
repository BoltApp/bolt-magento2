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
 *
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\AutomatedTesting as AutomatedTestingHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\Cart;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\CartFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\CartItem;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\CartItemFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\Config as AutomatedTestingConfig;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\ConfigFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\Order;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\PriceProperty;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\PricePropertyFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\StoreItem;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\StoreItemFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\OrderFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\OrderItemFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\AddressFactory;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Exception;
use Magento\Catalog\Api\Data\ProductSearchResultsInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Catalog\Api\ProductRepositoryInterface as ProductRepository;
use Magento\Sales\Api\OrderRepositoryInterface as OrderRepository;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface as QuoteRepository;
use Magento\Quote\Model\Cart\ShippingMethod;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote\Address\Rate;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Sales\Model\Order as ModelOrder;
use Magento\Sales\Model\Order\Address as ModelOrderAddress;
use Magento\Sales\Model\Order\Item as ModelOrderItem;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass \Bolt\Boltpay\Helper\AutomatedTesting
 */
class AutomatedTestingTest extends BoltTestCase
{
    /**
     * @var Context|MockObject
     */
    private $context;

    /**
     * @var ProductRepository|MockObject
     */
    private $productRepository;

    /**
     * @var OrderRepository|MockObject
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder|MockObject
     */
    private $searchCriteriaBuilder;

    /**
     * @var SortOrderBuilder|MockObject
     */
    private $sortOrderBuilder;

    /**
     * @var ConfigFactory|MockObject
     */
    private $configFactory;

    /**
     * @var StoreItemFactory|MockObject
     */
    private $storeItemFactory;

    /**
     * @var CartFactory|MockObject
     */
    private $cartFactory;

    /**
     * @var CartItemFactory|MockObject
     */
    private $cartItemFactory;

    /**
     * @var PricePropertyFactory|MockObject
     */
    private $pricePropertyFactory;

    /**
     * @var ShippingMethodConverter|MockObject
     */
    private $shippingMethodConverter;

    /**
     * @var Bugsnag|MockObject
     */
    private $bugsnag;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var QuoteFactory|MockObject
     */
    private $quoteFactory;

    /**
     * @var CartManagementInterface|MockObject
     */
    private $quoteManagement;

    /**
     * @var QuoteRepository|MockObject
     */
    private $quoteRepository;

    /**
     * @var StockRegistryInterface|MockObject
     */
    private $stockRegistry;

    /**
     * @var AutomatedTestingHelper|MockObject
     */
    private $currentMock;

    /***
     * @var OrderItemFactory|MockObject
     */
    private $orderItemFactory;

    /***
     * @var OrderFactory|MockObject
     */
    private $orderFactory;

    /***
     * @var AddressFactory|MockObject
     */
    private $addressFactory;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        $this->context = $this->createMock(Context::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->sortOrderBuilder = $this->createMock(SortOrderBuilder::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->configFactory = $this->createMock(ConfigFactory::class);
        $this->storeItemFactory = $this->createMock(StoreItemFactory::class);
        $this->cartFactory = $this->createMock(CartFactory::class);
        $this->cartItemFactory = $this->createMock(CartItemFactory::class);
        $this->pricePropertyFactory = $this->createMock(PricePropertyFactory::class);
        $this->shippingMethodConverter = $this->createMock(ShippingMethodConverter::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->quoteFactory = $this->createMock(QuoteFactory::class);
        $this->quoteManagement = $this->createMock(CartManagementInterface::class);
        $this->quoteRepository = $this->createMock(QuoteRepository::class);
        $this->stockRegistry = $this->createMock(StockRegistryInterface::class);
        $this->orderFactory = $this->createMock(OrderFactory::class);
        $this->orderItemFactory = $this->createMock(OrderItemFactory::class);
        $this->addressFactory = $this->createMock(AddressFactory::class);

        $this->currentMock = $this->getMockBuilder(AutomatedTestingHelper::class)
            ->setMethods(['getAutomatedTestingConfig',
                'getProduct',
                'convertToStoreItem',
                'createQuoteWithItem',
                'getShippingMethods',
                'getPastOrder',
                'convertToAddress',
                'convertToOrder'])
            ->setConstructorArgs([
                $this->context,
                $this->productRepository,
                $this->searchCriteriaBuilder,
                $this->sortOrderBuilder,
                $this->configFactory,
                $this->storeItemFactory,
                $this->cartFactory,
                $this->cartItemFactory,
                $this->pricePropertyFactory,
                $this->addressFactory,
                $this->orderFactory,
                $this->orderRepository,
                $this->orderItemFactory,
                $this->shippingMethodConverter,
                $this->bugsnag,
                $this->storeManager,
                $this->quoteFactory,
                $this->quoteManagement,
                $this->quoteRepository,
                $this->stockRegistry
            ])
            ->getMock();
    }

    /**
     * @test
     */
    public function getAutomatedTestingConfig_exceptionThrown_returnsErrorString()
    {
        $this->currentMock->expects(static::once())->method('getProduct')->willThrowException(new Exception('test error'));
        $this->bugsnag->expects(static::once())->method('notifyException');
        static::assertEquals(
            'test error',
            TestHelper::invokeMethod($this->currentMock, 'getAutomatedTestingConfig')
        );
    }

    /**
     * @test
     */
    public function getAutomatedTestingConfig_noSimpleProductsFound_returnsErrorString()
    {
        $this->currentMock->expects(static::once())->method('getProduct')->with('simple')->willReturn(null);
        static::assertEquals(
            'no simple products found',
            TestHelper::invokeMethod($this->currentMock, 'getAutomatedTestingConfig')
        );
    }

    /**
     * @test
     */
    public function getAutomatedTestingConfig_noShippingMethodsFound_returnsErrorString()
    {
        $product = $this->createMock(Product::class);
        $this->currentMock->expects(static::exactly(3))->method('getProduct')->withConsecutive(['simple'], ['virtual'], ['sale'])->willReturn($product);
        $this->currentMock->expects(static::exactly(3))->method('convertToStoreItem')->willReturn(null);
        $quote = $this->createMock(Quote::class);
        $this->currentMock->expects(static::once())->method('createQuoteWithItem')->willReturn($quote);
        $this->currentMock->expects(static::once())->method('getShippingMethods')->willReturn([]);
        static::assertEquals(
            'no shipping methods found',
            TestHelper::invokeMethod($this->currentMock, 'getAutomatedTestingConfig')
        );
    }

    /**
     * @test
     */
    public function getAutomatedTestingConfig_noErrors_returnsSimpleVirtualAndSaleProducts()
    {
        $product = $this->createMock(Product::class);
        $this->currentMock->expects(static::exactly(3))->method('getProduct')->withConsecutive(['simple'], ['virtual'], ['sale'])->willReturn($product);
        $storeItem = $this->createMock(StoreItem::class);
        $this->currentMock->expects(static::exactly(3))->method('convertToStoreItem')->willReturn($storeItem);
        $address = $this->createMock(Address::class);
        $quote = $this->createMock(Quote::class);
        $quote->expects(static::any())->method('getShippingAddress')->willReturn($address);
        $this->currentMock->expects(static::once())->method('createQuoteWithItem')->willReturn($quote);
        $shippingMethod = $this->createMock(PriceProperty::class);
        $this->currentMock->expects(static::once())->method('getShippingMethods')->willReturn([$shippingMethod]);
        $cartItem = $this->createMock(CartItem::class);
        $cartItem->expects(static::once())->method('setName')->willReturnSelf();
        $cartItem->expects(static::once())->method('setPrice')->willReturnSelf();
        $cartItem->expects(static::once())->method('setQuantity')->willReturnSelf();
        $this->cartItemFactory->expects(static::once())->method('create')->willReturn($cartItem);
        $tax = $this->createMock(PriceProperty::class);
        $tax->expects(static::once())->method('setName')->willReturnSelf();
        $tax->expects(static::once())->method('setPrice')->willReturnSelf();
        $this->pricePropertyFactory->expects(static::once())->method('create')->willReturn($tax);
        $cart = $this->createMock(Cart::class);
        $cart->expects(static::once())->method('setItems')->willReturnSelf();
        $cart->expects(static::once())->method('setShipping')->willReturnSelf();
        $cart->expects(static::once())->method('setExpectedShippingMethods')->willReturnSelf();
        $cart->expects(static::once())->method('setTax')->willReturnSelf();
        $cart->expects(static::once())->method('setSubTotal')->willReturnSelf();
        $this->cartFactory->expects(static::once())->method('create')->willReturn($cart);
        $order = $this->createMock(Order::class);
        $pastOrder = $this->createMock(ModelOrder::class);
        $this->currentMock->expects(static::once())->method('getPastOrder')->willReturn($pastOrder);
        $this->currentMock->expects(static::once())->method('convertToOrder')->willReturn($order);
        $config = $this->createMock(AutomatedTestingConfig::class);
        $config->expects(static::once())->method('setStoreItems')->with(static::callback(
            function ($cartItems) {
                return count($cartItems) === 3;
            }
        ))->willReturnSelf();
        $config->expects(static::once())->method('setCart')->willReturnSelf();
        $config->expects(static::once())->method('setPastOrder')->willReturnSelf();
        $this->configFactory->expects(static::once())->method('create')->willReturn($config);
        TestHelper::invokeMethod($this->currentMock, 'getAutomatedTestingConfig');
    }

    /**
     * @test
     */
    public function getAutomatedTestingConfig_noErrorsButNoVirtualProducts_returnsOnlySimpleProduct()
    {
        $product = $this->createMock(Product::class);
        $this->currentMock->expects(static::exactly(3))->method('getProduct')->withConsecutive(['simple'], ['virtual'], ['sale'])->willReturn($product);
        $storeItem = $this->createMock(StoreItem::class);
        $this->currentMock->expects(static::exactly(3))->method('convertToStoreItem')->willReturnOnConsecutiveCalls($storeItem, null, null);
        $address = $this->createMock(Address::class);
        $quote = $this->createMock(Quote::class);
        $quote->expects(static::any())->method('getShippingAddress')->willReturn($address);
        $this->currentMock->expects(static::once())->method('createQuoteWithItem')->willReturn($quote);
        $shippingMethod = $this->createMock(PriceProperty::class);
        $this->currentMock->expects(static::once())->method('getShippingMethods')->willReturn([$shippingMethod]);
        $cartItem = $this->createMock(CartItem::class);
        $cartItem->expects(static::once())->method('setName')->willReturnSelf();
        $cartItem->expects(static::once())->method('setPrice')->willReturnSelf();
        $cartItem->expects(static::once())->method('setQuantity')->willReturnSelf();
        $this->cartItemFactory->expects(static::once())->method('create')->willReturn($cartItem);
        $tax = $this->createMock(PriceProperty::class);
        $tax->expects(static::once())->method('setName')->willReturnSelf();
        $tax->expects(static::once())->method('setPrice')->willReturnSelf();
        $this->pricePropertyFactory->expects(static::once())->method('create')->willReturn($tax);
        $cart = $this->createMock(Cart::class);
        $cart->expects(static::once())->method('setItems')->willReturnSelf();
        $cart->expects(static::once())->method('setShipping')->willReturnSelf();
        $cart->expects(static::once())->method('setExpectedShippingMethods')->willReturnSelf();
        $cart->expects(static::once())->method('setTax')->willReturnSelf();
        $cart->expects(static::once())->method('setSubTotal')->willReturnSelf();
        $order = $this->createMock(Order::class);
        $pastOrder = $this->createMock(ModelOrder::class);
        $this->currentMock->expects(static::once())->method('getPastOrder')->willReturn($pastOrder);
        $this->currentMock->expects(static::once())->method('convertToOrder')->willReturn($order);
        $this->cartFactory->expects(static::once())->method('create')->willReturn($cart);
        $config = $this->createMock(AutomatedTestingConfig::class);
        $config->expects(static::once())->method('setStoreItems')->with(static::callback(
            function ($cartItems) {
                return count($cartItems) === 1;
            }
        ))->willReturnSelf();
        $config->expects(static::once())->method('setCart')->willReturnSelf();
        $config->expects(static::once())->method('setPastOrder')->willReturnSelf();
        $this->configFactory->expects(static::once())->method('create')->willReturn($config);
        TestHelper::invokeMethod($this->currentMock, 'getAutomatedTestingConfig');
    }

    /**
     * @test
     */
    public function getProduct_returnsPhysicalProduct()
    {
        $this->searchCriteriaBuilder->expects(static::exactly(3))->method('addFilter')->withConsecutive(
            ['status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED],
            ['visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE, 'neq'],
            ['type_id', 'physical']
        )->willReturnSelf();
        $searchCriteria = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilder->expects(static::once())->method('create')->willReturn($searchCriteria);
        $productSearchResults = $this->createMock(ProductSearchResultsInterface::class);
        $this->productRepository->expects(static::once())->method('getList')->with($searchCriteria)->willReturn($productSearchResults);
        $product = $this->createMock(Product::class);
        $productSearchResults->expects(static::once())->method('getItems')->willReturn([$product]);
        $product->expects(static::once())->method('getId')->willReturn(1);
        $stockItem = $this->createMock(StockItemInterface::class);
        $this->stockRegistry->expects(static::once())->method('getStockItem')->with(1)->willReturn($stockItem);
        $stockItem->expects(static::once())->method('getIsInStock')->willReturn(true);
        static::assertEquals($product, TestHelper::invokeMethod($this->currentMock, 'getProduct', ['physical']));
    }

    /**
     * @test
     */
    public function getProduct_returnsSaleProduct()
    {
        $this->searchCriteriaBuilder->expects(static::exactly(3))->method('addFilter')->withConsecutive(
            ['status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED],
            ['visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE, 'neq'],
            ['type_id', [\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE, \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL], 'in']
        )->willReturnSelf();
        $searchCriteria = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilder->expects(static::once())->method('create')->willReturn($searchCriteria);
        $productSearchResults = $this->createMock(ProductSearchResultsInterface::class);
        $this->productRepository->expects(static::once())->method('getList')->with($searchCriteria)->willReturn($productSearchResults);
        $product = $this->createMock(Product::class);
        $productSearchResults->expects(static::once())->method('getItems')->willReturn([$product]);
        $product->expects(static::once())->method('getId')->willReturn(1);
        $stockItem = $this->createMock(StockItemInterface::class);
        $this->stockRegistry->expects(static::once())->method('getStockItem')->with(1)->willReturn($stockItem);
        $stockItem->expects(static::once())->method('getIsInStock')->willReturn(true);
        $product->expects(static::once())->method('getFinalPrice')->willReturn(0.99);
        $priceInfo = $this->createMock(PriceInfoInterface::class);
        $product->expects(static::once())->method('getPriceInfo')->willReturn($priceInfo);
        $price = $this->createMock(PriceInterface::class);
        $priceInfo->expects(static::once())->method('getPrice')->with('regular_price')->willReturn($price);
        $price->expects(static::once())->method('getValue')->willReturn(1);
        static::assertEquals($product, TestHelper::invokeMethod($this->currentMock, 'getProduct', ['sale']));
    }

    /**
     * @test
     */
    public function getProduct_returnsNull_ifNoProductSatisfiesConditions()
    {
        $this->searchCriteriaBuilder->expects(static::exactly(3))->method('addFilter')->withConsecutive(
            ['status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED],
            ['visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE, 'neq'],
            ['type_id', 'physical']
        )->willReturnSelf();
        $searchCriteria = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilder->expects(static::once())->method('create')->willReturn($searchCriteria);
        $productSearchResults = $this->createMock(ProductSearchResultsInterface::class);
        $this->productRepository->expects(static::once())->method('getList')->with($searchCriteria)->willReturn($productSearchResults);
        $productSearchResults->expects(static::once())->method('getItems')->willReturn([]);
        static::assertEquals(null, TestHelper::invokeMethod($this->currentMock, 'getProduct', ['physical']));
    }

    /**
     * @test
     */
    public function convertToStoreItem_returnsNull_ifProductIsNull()
    {
        static::assertEquals(null, TestHelper::invokeMethod($this->currentMock, 'convertToStoreItem', [null, 'virtual']));
    }

    /**
     * @test
     */
    public function convertToStoreItem_returnsStoreItem_ifProductIsNotNull()
    {
        $product = $this->createMock(Product::class);
        $product->expects(static::once())->method('getProductUrl')->willReturn('http://test.com/url');
        $product->expects(static::once())->method('getName')->willReturn('  a product');
        $product->expects(static::once())->method('getFinalPrice')->willReturn('1.99');
        $storeItem = $this->createMock(StoreItem::class);
        $this->storeItemFactory->expects(static::once())->method('create')->willReturn($storeItem);
        $storeItem->expects(static::once())->method('setItemUrl')->with('http://test.com/url')->willReturnSelf();
        $storeItem->expects(static::once())->method('setName')->with('a product')->willReturnSelf();
        $storeItem->expects(static::once())->method('setPrice')->with('$1.99')->willReturnSelf();
        $storeItem->expects(static::once())->method('setType')->with('physical')->willReturnSelf();
        static::assertEquals($storeItem, TestHelper::invokeMethod($this->currentMock, 'convertToStoreItem', [$product, 'physical']));
    }

    /**
     * @test
     */
    public function createQuoteWithItem_returnsQuote()
    {
        $this->quoteManagement->expects(static::once())->method('createEmptyCart')->willReturn(1);
        $quote = $this->createMock(Quote::class);
        $this->quoteFactory->expects(static::once())->method('create')->willReturn($quote);
        $quote->expects(static::once())->method('load')->with(1)->willReturnSelf();
        $store = $this->createMock(Store::class);
        $store->expects(static::once())->method('getId')->willReturn(1);
        $this->storeManager->expects(static::once())->method('getStore')->willReturn($store);
        $quote->expects(static::once())->method('setStoreId')->with(1);
        $product = $this->createMock(Product::class);
        $quote->expects(static::once())->method('addProduct')->with($product, 1);
        $address = $this->createMock(Address::class);
        $address->expects(static::once())->method('addData')->with([
            'street'     => '1235 Howard St Ste D',
            'city'       => 'San Francisco',
            'country_id' => 'US',
            'region'     => 'CA',
            'postcode'   => '94103'
        ]);
        $quote->expects(static::once())->method('getShippingAddress')->willReturn($address);
        $this->quoteRepository->expects(static::once())->method('save');
        static::assertEquals($quote, TestHelper::invokeMethod($this->currentMock, 'createQuoteWithItem', [$product]));
    }

    /**
     * @test
     */
    public function getShippingMethods_returnsEmptyArray_ifNoShippingMethods()
    {
        $address = $this->createMock(Address::class);
        $quote = $this->createMock(Quote::class);
        $quote->expects(static::once())->method('getShippingAddress')->willReturn($address);
        $address->expects(static::once())->method('getGroupedAllShippingRates')->willReturn([]);
        static::assertEquals([], TestHelper::invokeMethod($this->currentMock, 'getShippingMethods', [$quote]));
    }

    /**
     * @test
     */
    public function getShippingMethods_returnsCorrectShippingMethods()
    {
        $address = $this->createPartialMock(Address::class, ['getGroupedAllShippingRates', 'setCollectShippingRates', 'setShippingMethod', 'save']);
        $quote = $this->createMock(Quote::class);
        $quote->expects(static::once())->method('getShippingAddress')->willReturn($address);
        $rate = $this->createMock(Rate::class);
        $address->expects(static::once())->method('getGroupedAllShippingRates')->willReturn([[$rate]]);
        $shippingMethod = $this->createMock(ShippingMethod::class);
        $this->shippingMethodConverter->expects(static::once())->method('modelToDataObject')->with($rate, 'USD')->willReturn($shippingMethod);
        $address->expects(static::once())->method('setCollectShippingRates')->with(true)->willReturnSelf();
        $shippingMethod->expects(static::once())->method('getCarrierCode')->willReturn('fs');
        $shippingMethod->expects(static::once())->method('getMethodCode')->willReturn('fs');
        $address->expects(static::once())->method('setShippingMethod')->with('fs_fs')->willReturnSelf();
        $address->expects(static::once())->method('save');
        $shippingMethod->expects(static::once())->method('getCarrierTitle')->willReturn('freeshipping');
        $shippingMethod->expects(static::once())->method('getMethodTitle')->willReturn('freeshipping');
        $shippingMethod->expects(static::once())->method('getAmount')->willReturn(0.0);
        $atcShippingMethod = $this->createMock(PriceProperty::class);
        $atcShippingMethod->expects(static::once())->method('setName')->with('freeshipping - freeshipping')->willReturnSelf();
        $atcShippingMethod->expects(static::once())->method('setPrice')->with('Free')->willReturnSelf();
        $this->pricePropertyFactory->expects(static::once())->method('create')->willReturn($atcShippingMethod);
        static::assertEquals([$atcShippingMethod], TestHelper::invokeMethod($this->currentMock, 'getShippingMethods', [$quote]));
    }

    /**
     * @test
     */
    public function getPastOrder_returnsOrderWithNonZeroTaxAndDiscount()
    {
        $sortOrder = $this->createMock(SortOrder::class);
        $this->sortOrderBuilder->expects(static::once())->method('setField')->with('entity_id')->willReturnSelf();
        $this->sortOrderBuilder->expects(static::once())->method('setDirection')->with('DESC')->willReturnSelf();
        $this->sortOrderBuilder->expects(static::once())->method('create')->willReturn($sortOrder);

        $this->searchCriteriaBuilder->expects(static::exactly(2))->method('addFilter')->withConsecutive(
            ['discount_amount', 0, 'lt'],
            ['tax_amount', 0, 'gt']
        )->willReturnSelf();

        $searchCriteria = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilder->expects(static::exactly(2))->method('setPageSize')->with(1)->willReturnSelf();
        $this->searchCriteriaBuilder->expects(static::exactly(2))->method('setCurrentPage')->with(1)->willReturnSelf();
        $this->searchCriteriaBuilder->expects(static::exactly(2))->method('setSortOrders')->with([$sortOrder])->willReturnSelf();
        $this->searchCriteriaBuilder->expects(static::exactly(2))->method('create')->willReturn($searchCriteria);

        $pastOrder = $this->createMock(ModelOrder::class);
        $orderSearchResults = $this->createMock(OrderSearchResultInterface::class);
        $this->orderRepository->expects(static::once())->method('getList')->with($searchCriteria)->willReturn($orderSearchResults);
        $orderSearchResults->expects(static::once())->method('getItems')->willReturn([$pastOrder]);
        static::assertEquals($pastOrder, TestHelper::invokeMethod($this->currentMock, 'getPastOrder', []));
    }

    /**
     * @test
     */
    public function getPastOrder_returnsDefaultOrder()
    {
        $sortOrder = $this->createMock(SortOrder::class);
        $this->sortOrderBuilder->expects(static::once())->method('setField')->with('entity_id')->willReturnSelf();
        $this->sortOrderBuilder->expects(static::once())->method('setDirection')->with('DESC')->willReturnSelf();
        $this->sortOrderBuilder->expects(static::once())->method('create')->willReturn($sortOrder);

        $this->searchCriteriaBuilder->expects(static::exactly(2))->method('addFilter')->withConsecutive(
            ['discount_amount', 0, 'lt'],
            ['tax_amount', 0, 'gt']
        )->willReturnSelf();

        $searchCriteria = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilder->expects(static::exactly(2))->method('setPageSize')->with(1)->willReturnSelf();
        $this->searchCriteriaBuilder->expects(static::exactly(2))->method('setCurrentPage')->with(1)->willReturnSelf();
        $this->searchCriteriaBuilder->expects(static::exactly(2))->method('setSortOrders')->with([$sortOrder])->willReturnSelf();
        $this->searchCriteriaBuilder->expects(static::exactly(2))->method('create')->willReturn($searchCriteria);

        $defaultOrder = $this->createMock(ModelOrder::class);
        $orderSearchResults = $this->createMock(OrderSearchResultInterface::class);
        $this->orderRepository->expects(static::exactly(2))->method('getList')->with($searchCriteria)->willReturn($orderSearchResults);
        $orderSearchResults->expects(static::exactly(2))->method('getItems')->willReturnOnConsecutiveCalls([], [$defaultOrder]);
        static::assertEquals($defaultOrder, TestHelper::invokeMethod($this->currentMock, 'getPastOrder', []));
    }

    /**
     * @test
     */
    public function convertToAddress_returnsNull_ifAddressIsNull()
    {
        static::assertEquals(null, TestHelper::invokeMethod($this->currentMock, 'convertToAddress', [null]));
    }

    /**
     * @test
     */
    public function convertToOrder_returnsNull_ifOrderIsNull()
    {
        static::assertEquals(null, TestHelper::invokeMethod($this->currentMock, 'convertToOrder', [null]));
    }

    /**
     * @test
     */
    public function convertToAddress_returnsAddress_ifAddressIsNotNull()
    {
        $addressIn = $this->createMock(ModelOrderAddress::class);
        $addressIn->expects(static::once())->method('getFirstName')->willReturn('Bolt');
        $addressIn->expects(static::once())->method('getLastName')->willReturn('User');
        $addressIn->expects(static::once())->method('getStreet')->willReturn(['25 upper street']);
        $addressIn->expects(static::once())->method('getCity')->willReturn('San Francisco');
        $addressIn->expects(static::once())->method('getRegion')->willReturn('California');
        $addressIn->expects(static::once())->method('getPostCode')->willReturn('12345');
        $addressIn->expects(static::once())->method('getTelephone')->willReturn('1234567890');
        $addressIn->expects(static::once())->method('getCountryId')->willReturn('US');
        $addressOut = $this->createMock(\Bolt\Boltpay\Model\Api\Data\AutomatedTesting\Address::class);
        $this->addressFactory->expects(static::once())->method('create')->willReturn($addressOut);
        $addressOut->expects(static::once())->method('setFirstName')->with('Bolt')->willReturnSelf();
        $addressOut->expects(static::once())->method('setLastName')->with('User')->willReturnSelf();
        $addressOut->expects(static::once())->method('setStreet')->with('25 upper street')->willReturnSelf();
        $addressOut->expects(static::once())->method('setCity')->with('San Francisco')->willReturnSelf();
        $addressOut->expects(static::once())->method('setRegion')->with('California')->willReturnSelf();
        $addressOut->expects(static::once())->method('setPostalCode')->with('12345')->willReturnSelf();
        $addressOut->expects(static::once())->method('setTelephone')->with('1234567890')->willReturnSelf();
        $addressOut->expects(static::once())->method('setCountry')->with('US')->willReturnSelf();
        static::assertEquals($addressOut, TestHelper::invokeMethod($this->currentMock, 'convertToAddress', [$addressIn]));
    }

    /**
     * @test
     */
    public function convertToOrder_returnsOrder_ifOrderIsNotNull()
    {
        $orderIn = $this->createMock(ModelOrder::class);
        $addressIn = $this->createMock(ModelOrderAddress::class);
        $addressOut = $this->createMock(\Bolt\Boltpay\Model\Api\Data\AutomatedTesting\Address::class);
        $orderItemIn = $this->createMock(ModelOrderItem::class);
        $product = $this->createMock(Product::class);
        $product->expects(static::once())->method('getProductUrl')->willReturn('http://test.com/url');
        $orderItemIn->expects(static::once())->method('getName')->willReturn('item A');
        $orderItemIn->expects(static::once())->method('getSku')->willReturn('WS12-XL-Purple');
        $orderItemIn->expects(static::once())->method('getProduct')->willReturn($product);
        $orderItemIn->expects(static::once())->method('getPrice')->willReturn('24.0000');
        $orderItemIn->expects(static::once())->method('getQtyOrdered')->willReturn('3.0000');
        $orderItemIn->expects(static::exactly(2))->method('getRowTotal')->willReturn('72.000');
        $orderItemIn->expects(static::exactly(2))->method('getTaxAmount')->willReturn('7.2000');
        $orderItemIn->expects(static::once())->method('getTaxPercent')->willReturn('10.0000');
        $orderItemIn->expects(static::exactly(2))->method('getDiscountAmount')->willReturn('5.0000');

        $orderItemOut = $this->createMock(\Bolt\Boltpay\Model\Api\Data\AutomatedTesting\OrderItem::class);
        $this->orderItemFactory->expects(static::once())->method('create')->willReturn($orderItemOut);
        $orderItemOut->expects(static::once())->method('setProductName')->with('item A')->willReturnSelf();
        $orderItemOut->expects(static::once())->method('setProductSku')->with('WS12-XL-Purple')->willReturnSelf();
        $orderItemOut->expects(static::once())->method('setProductUrl')->with('http://test.com/url')->willReturnSelf();
        $orderItemOut->expects(static::once())->method('setPrice')->with('$24.00')->willReturnSelf();
        $orderItemOut->expects(static::once())->method('setQuantityOrdered')->with('3.0000')->willReturnSelf();
        $orderItemOut->expects(static::once())->method('setSubtotal')->with('$72.00')->willReturnSelf();
        $orderItemOut->expects(static::once())->method('setTaxAmount')->with('$7.20')->willReturnSelf();
        $orderItemOut->expects(static::once())->method('setTaxPercent')->with('10.0000')->willReturnSelf();
        $orderItemOut->expects(static::once())->method('setDiscountAmount')->with('$5.00')->willReturnSelf();
        $orderItemOut->expects(static::once())->method('setTotal')->with('$74.20')->willReturnSelf();

        $orderIn->expects(static::once())->method('getAllVisibleItems')->willReturn([$orderItemIn]);
        $orderIn->expects(static::once())->method('getBillingAddress')->willReturn($addressIn);
        $orderIn->expects(static::once())->method('getShippingAddress')->willReturn($addressIn);
        $orderIn->expects(static::once())->method('getShippingDescription')->willReturn('Flat Rate - Fixed');
        $orderIn->expects(static::once())->method('getShippingAmount')->willReturn('22.0000');
        $orderIn->expects(static::once())->method('getSubtotal')->willReturn('72.0000');
        $orderIn->expects(static::once())->method('getTaxAmount')->willReturn('7.2000');
        $orderIn->expects(static::once())->method('getDiscountAmount')->willReturn('-5.0000');
        $orderIn->expects(static::once())->method('getGrandTotal')->willReturn('74.2000');

        $orderOut = $this->createMock(Order::class);
        $this->orderFactory->expects(static::once())->method('create')->willReturn($orderOut);
        $this->currentMock->expects(static::exactly(2))->method('convertToAddress')->willReturn($addressOut);
        $orderOut->expects(static::once())->method('setBillingAddress')->with($addressOut)->willReturnSelf();
        $orderOut->expects(static::once())->method('setShippingAddress')->with($addressOut)->willReturnSelf();
        $orderOut->expects(static::once())->method('setShippingMethod')->with('Flat Rate - Fixed')->willReturnSelf();
        $orderOut->expects(static::once())->method('setShippingAmount')->with('$22.00')->willReturnSelf();
        $orderOut->expects(static::once())->method('setOrderItems')->with([$orderItemOut])->willReturnSelf();
        $orderOut->expects(static::once())->method('setSubtotal')->with('$72.00')->willReturnSelf();
        $orderOut->expects(static::once())->method('setTax')->with('$7.20')->willReturnSelf();
        $orderOut->expects(static::once())->method('setDiscount')->with('$-5.00')->willReturnSelf();
        $orderOut->expects(static::once())->method('setGrandTotal')->with('$74.20')->willReturnSelf();

        static::assertEquals($orderOut, TestHelper::invokeMethod($this->currentMock, 'convertToOrder', [$orderIn]));
    }
}
