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
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\PriceProperty;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\PricePropertyFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\StoreItem;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\StoreItemFactory;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface as ProductRepository;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\Context;
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
     * @var SearchCriteriaBuilder|MockObject
     */
    private $searchCriteriaBuilder;

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

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        $this->context = $this->createMock(Context::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
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

        $this->currentMock = $this->getMockBuilder(AutomatedTestingHelper::class)
            ->setMethods(['getAutomatedTestingConfig', 'getProduct', 'convertToStoreItem', 'createQuoteWithItem', 'getShippingMethods'])
            ->setConstructorArgs([
                $this->context,
                $this->productRepository,
                $this->searchCriteriaBuilder,
                $this->configFactory,
                $this->storeItemFactory,
                $this->cartFactory,
                $this->cartItemFactory,
                $this->pricePropertyFactory,
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
        $cart = $this->createMock(Cart::class);
        $cart->expects(static::once())->method('setItems')->willReturnSelf();
        $cart->expects(static::once())->method('setShipping')->willReturnSelf();
        $cart->expects(static::once())->method('setExpectedShippingMethods')->willReturnSelf();
        $cart->expects(static::once())->method('setTax')->willReturnSelf();
        $cart->expects(static::once())->method('setSubTotal')->willReturnSelf();
        $this->cartFactory->expects(static::once())->method('create')->willReturn($cart);
        $config = $this->createMock(AutomatedTestingConfig::class);
        $config->expects(static::once())->method('setStoreItems')->with(static::callback(
            function ($cartItems) {
                return count($cartItems) === 3;
            }
        ))->willReturnSelf();
        $config->expects(static::once())->method('setCart')->willReturnSelf();
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
        $cart = $this->createMock(Cart::class);
        $cart->expects(static::once())->method('setItems')->willReturnSelf();
        $cart->expects(static::once())->method('setShipping')->willReturnSelf();
        $cart->expects(static::once())->method('setExpectedShippingMethods')->willReturnSelf();
        $cart->expects(static::once())->method('setTax')->willReturnSelf();
        $cart->expects(static::once())->method('setSubTotal')->willReturnSelf();
        $this->cartFactory->expects(static::once())->method('create')->willReturn($cart);
        $config = $this->createMock(AutomatedTestingConfig::class);
        $config->expects(static::once())->method('setStoreItems')->with(static::callback(
            function ($cartItems) {
                return count($cartItems) === 1;
            }
        ))->willReturnSelf();
        $config->expects(static::once())->method('setCart')->willReturnSelf();
        $this->configFactory->expects(static::once())->method('create')->willReturn($config);
        TestHelper::invokeMethod($this->currentMock, 'getAutomatedTestingConfig');
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
}
