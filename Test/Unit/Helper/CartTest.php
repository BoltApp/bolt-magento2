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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as BoltHelperCart;
use Bolt\Boltpay\Helper\Log;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Model\Request;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bugsnag\Report;
use Exception;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Session\Generic as GenericSession;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Customer\Model\Address;
use Magento\Framework\Registry;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
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
use Magento\Quote\Model\QuoteRepository as QuoteRepository;
use Magento\Sales\Model\OrderRepository as OrderRepository;
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
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use ReflectionException;
use Zend_Http_Client_Exception;
use Zend_Validate_Exception;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider as DeciderHelper;
use Magento\Catalog\Model\Config\Source\Product\Thumbnail as ThumbnailSource;;
use Magento\GroupedProduct\Block\Cart\Item\Renderer\Grouped as RendererGrouped;
use Magento\ConfigurableProduct\Block\Cart\Item\Renderer\Configurable as RendererConfigurable;

/**
 * @coversDefaultClass \Bolt\Boltpay\Helper\Cart
 */
class CartTest extends TestCase
{
    /** @var int Test quote id */
    const QUOTE_ID = 1234;

    /** @var int Test immutable quote id */
    const IMMUTABLE_QUOTE_ID = 1001;

    /** @var int Test parent quote id */
    const PARENT_QUOTE_ID = 1000;

    /** @var int Test customer id */
    const CUSTOMER_ID = 234;

    /** @var int Test product id */
    const PRODUCT_ID = 20102;

    /** @var int Test product price */
    const PRODUCT_PRICE = 100;

    /** @var int Test order increment id */
    const ORDER_INCREMENT_ID = 100010001;

    /** @var int Test store id */
    const STORE_ID = 1;

    /** @var int Test website id */
    const WEBSITE_ID = 1;

    /** @var string Test cache identifier */
    const CACHE_IDENTIFIER = 'de6571d30123102e4a49a9483881a05f';

    /** @var string Test product SKU */
    const PRODUCT_SKU = 'TestProduct';

    /** @var array Test product super attributes */
    const SUPER_ATTRIBUTE = ["93" => "57", "136" => "383"];

    /** @var string Test Bolt API key */
    const API_KEY = 'c2ZkKs4Bd2GKMtzRRqB73dFtT5QtMQRv';

    /** @var string Test Bolt API signature */
    const SIGNATURE = 'ZGEvY22bckLNUuZJZguEt2qZvrsyK8C6';

    const TOKEN = 'token';

    const HINT = 'hint!';

    const GIFT_MESSAGE_ID = '122';
    const GIFT_WRAPPING_ID = '123';
    const QUOTE_ITEM_ID = '124';

    /** @var array Address data containing all required fields */
    const COMPLETE_ADDRESS_DATA = [
        'first_name'      => "Bolt",
        'last_name'       => "Test",
        'locality'        => "New York",
        'street_address1' => "228 5th Avenue",
        'region'          => "New York",
        'postal_code'     => "10001",
        'country_code'    => "US",
        'email'           => self::EMAIL_ADDRESS,
    ];

    /** @var string Test currency code */
    const CURRENCY_CODE = 'USD';

    /** @var string Test email address */
    const EMAIL_ADDRESS = 'integration@bolt.com';

    /** @var Context|MockObject */
    private $contextHelper;

    /** @var CheckoutSession|MockObject */
    private $checkoutSession;

    /** @var ApiHelper|MockObject */
    private $apiHelper;

    /** @var ConfigHelper|MockObject */
    private $configHelper;

    /** @var CustomerSession|MockObject */
    private $customerSession;

    /** @var LogHelper|MockObject */
    private $logHelper;

    /** @var Bugsnag|MockObject */
    private $bugsnag;

    /** @var ImageFactory|MockObject */
    private $imageHelperFactory;

    /** @var ProductRepository|MockObject */
    private $productRepository;

    /** @var Emulation|MockObject */
    private $appEmulation;

    /** @var DataObjectFactory|MockObject */
    private $dataObjectFactory;

    /** @var QuoteFactory|MockObject */
    private $quoteFactory;

    /** @var TotalsCollector|MockObject */
    private $totalsCollector;

    /** @var QuoteRepository|MockObject */
    private $quoteRepository;

    /** @var OrderRepository|MockObject */
    private $orderRepository;

    /** @var SearchCriteriaBuilder|MockObject */
    private $searchCriteriaBuilder;

    /** @var QuoteResource|MockObject */
    private $quoteResource;

    /** @var SessionHelper|MockObject */
    private $sessionHelper;

    /** @var CheckoutHelper|MockObject */
    private $checkoutHelper;

    /** @var DiscountHelper|MockObject */
    private $discountHelper;

    /** @var CacheInterface|MockObject */
    private $cache;

    /** @var ResourceConnection|MockObject */
    private $resourceConnection;

    /** @var Total|MockObject */
    private $quoteAddressTotal;

    /** @var CartManagementInterface|MockObject */
    private $quoteManagement;

    /** @var HookHelper|MockObject */
    private $hookHelper;

    /** @var CustomerRepository|MockObject */
    private $customerRepository;

    /** @var MockObject|Quote */
    private $quoteMock;

    /** @var BoltHelperCart|MockObject */
    private $currentMock;

    /** @var Order|MockObject */
    private $orderMock;

    /** @var Product|MockObject */
    private $productMock;

    /** @var Image|MockObject */
    private $imageHelper;

    /** @var Quote|MockObject */
    private $immutableQuoteMock;

    /** @var Quote\Address|MockObject */
    private $quoteShippingAddress;

    /** @var Quote\Address|MockObject */
    private $quoteBillingAddress;

    /** @var MockObject|ObjectManager */
    private $objectManagerMock;

    /** @var MockObject|Customer */
    private $customerMock;

    /** @var array */
    private $testAddressData;

    /** @var array */
    private $giftwrapping;

    /**
     * @var Registry|\PHPUnit\Framework\MockObject\MockObject
     */
    private $coreRegistry;

    /**
     * @var MetricsClient
     */
    private $metricsClient;

    /** @var MockObject|DeciderHelper */
    private $deciderHelper;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->testAddressData = [
            'company'         => "",
            'country'         => "United States",
            'country_code'    => "US",
            'email'           => self::EMAIL_ADDRESS,
            'first_name'      => "IntegrationBolt",
            'last_name'       => "BoltTest",
            'locality'        => "New York",
            'phone'           => "132 231 1234",
            'postal_code'     => "10011",
            'region'          => "New York",
            'street_address1' => "228 7th Avenue",
            'street_address2' => "228 7th Avenue 2",
        ];
        $this->orderMock = $this->createMock(Order::class);
        $quoteMethods = [
            'getBoltReservedOrderId',
            'reserveOrderId',
            'setReservedOrderId',
            'getBillingAddress',
            'getShippingAddress',
            'getAllVisibleItems',
            'collectTotals',
            'getBoltParentQuoteId',
            'getReservedOrderId',
            'getId',
            'getQuoteCurrencyCode',
            'getUseCustomerBalance',
            'getUseRewardPoints',
            'getCustomerBalanceAmountUsed',
            'addProduct',
            'getTotals',
            'getStoreId',
            'getCustomerEmail',
            'isVirtual',
            'assignCustomer',
            'getCustomerGroupId',
            'setIsActive',
            'getData',
            'getStore',
            'save',
        ];
        $this->immutableQuoteMock = $this->createPartialMock(Quote::class, $quoteMethods);

        $addressMethods = [
            'setCollectShippingRates',
            'getFirstname',
            'getLastname',
            'getCompany',
            'getTelephone',
            'getStreetLine',
            'getCity',
            'getRegion',
            'getPostcode',
            'getCountryId',
            'getEmail',
            'getShippingMethod',
            'setShippingMethod',
            'save',
        ];
        $this->quoteShippingAddress = $this->createPartialMock(Quote\Address::class, $addressMethods);
        $this->quoteBillingAddress = $this->createPartialMock(Quote\Address::class, $addressMethods);

        $this->productMock = $this->createPartialMock(Product::class, ['getDescription', 'getTypeInstance']);
        $this->productMock->method('getTypeInstance')->willReturnSelf();
        $this->contextHelper = $this->createMock(ContextHelper::class);
        $this->quoteMock = $this->createPartialMock(Quote::class,[
            'getQuoteCurrencyCode','getAllVisibleItems',
            'getTotals','getStore','getStoreId',
            'getData','isVirtual','getId','getShippingAddress',
            'getBillingAddress','reserveOrderId','addProduct',
            'assignCustomer','setIsActive','getGiftMessageId',
            'getGwId'
        ]);
        $this->checkoutSession = $this->createPartialMock(CheckoutSession::class, ['getQuote']);
        $this->productRepository = $this->createPartialMock(ProductRepository::class, ['get', 'getbyId']);

        $this->apiHelper = $this->createMock(ApiHelper::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->logHelper = $this->createPartialMock(LogHelper::class, ['addInfoLog']);
        $this->logHelper->method('addInfoLog')->withAnyParameters()->willReturnSelf();
        $this->bugsnag = $this->createPartialMock(
            Bugsnag::class,
            ['notifyError', 'notifyException', 'registerCallback']
        );

        $this->imageHelper = $this->createMock(Image::class);

        $this->imageHelperFactory = $this->createMock(ImageFactory::class);
        $this->imageHelperFactory->method('create')->willReturn($this->imageHelper);

        $this->appEmulation = $this->createPartialMock(
            Emulation::class,
            ['stopEnvironmentEmulation', 'startEnvironmentEmulation']
        );
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->quoteFactory = $this->getMockBuilder(QuoteFactory::class)
            ->setMethods(['create', 'load'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->totalsCollector = $this->createMock(TotalsCollector::class);
        $this->quoteRepository = $this->createPartialMock(
            QuoteRepository::class,
            ['getList', 'getItems', 'getActive', 'save', 'delete']
        );
        $this->orderRepository = $this->createPartialMock(OrderRepository::class, ['getList', 'getItems']);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->quoteResource = $this->createMock(QuoteResource::class);
        $this->sessionHelper = $this->createMock(SessionHelper::class);
        $this->checkoutHelper = $this->createMock(CheckoutHelper::class);
        $this->discountHelper = $this->createMock(DiscountHelper::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->quoteAddressTotal = $this->createPartialMock(Total::class, ['getValue', 'setValue', 'getTitle']);
        $this->quoteManagement = $this->createMock(CartManagementInterface::class);
        $this->hookHelper = $this->createMock(HookHelper::class);
        $this->customerRepository = $this->createMock(CustomerRepository::class);
        $this->objectManagerMock = $this->getMockBuilder(ObjectManagerInterface::class)
            ->getMockForAbstractClass();
        $this->customerMock = $this->createPartialMock(Customer::class, ['getEmail']);
        $this->coreRegistry = $this->createMock(Registry::class);
        $this->metricsClient = $this->createMock(MetricsClient::class);
        $this->deciderHelper = $this->createPartialMock(DeciderHelper::class, ['ifShouldDisablePrefillAddressForLoggedInCustomer','handleVirtualProductsAsPhysical']);
        $this->currentMock = $this->getCurrentMock(null);
    }

    /**
     * Returns mocked instance of the tested class
     *
     * @param array $methods to be mocked
     *
     * @return MockObject|BoltHelperCart
     */
    protected function getCurrentMock($methods = ['getLastImmutableQuote', 'getCalculationAddress', 'getQuoteById'])
    {
        return $this->getMockBuilder(BoltHelperCart::class)
            ->setMethods($methods)
            ->setConstructorArgs(
                [
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
                    $this->quoteRepository,
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
                    $this->coreRegistry,
                    $this->metricsClient,
                    $this->deciderHelper
                ]
            )
            ->getMock();
    }

    /**
     * Configures provided address mock to return valid address data
     *
     * @param MockObject $addressMock
     */
    private function setUpAddressMock($addressMock)
    {
        $addressMock->method('getFirstname')->willReturn($this->testAddressData['first_name']);
        $addressMock->method('getLastname')->willReturn($this->testAddressData['last_name']);
        $addressMock->method('getCompany')->willReturn($this->testAddressData['company']);
        $addressMock->method('getTelephone')->willReturn($this->testAddressData['phone']);
        $addressMock->method('getStreetLine')
            ->willReturnMap(
                [
                    [1, $this->testAddressData['street_address1']],
                    [2, $this->testAddressData['street_address2']]
                ]
            );
        $addressMock->method('getCity')->willReturn($this->testAddressData['locality']);
        $addressMock->method('getRegion')->willReturn($this->testAddressData['region']);
        $addressMock->method('getPostcode')->willReturn($this->testAddressData['postal_code']);
        $addressMock->method('getCountryId')->willReturn($this->testAddressData['country_code']);
        $addressMock->method('getEmail')->willReturn($this->testAddressData['email']);
    }

    /**
     * Get quote mock with quote items
     *
     * @param Address|MockObject $billingAddress
     * @param Address|MockObject $shippingAddress
     *
     * @return Quote|MockObject
     */
    private function getQuoteMock($billingAddress = null, $shippingAddress = null)
    {
        if (!$billingAddress) {
            $billingAddress = $this->getAddressMock();
        }
        if (!$shippingAddress) {
            $shippingAddress = $this->getAddressMock();
        }
        $quoteItem = $this->getQuoteItemMock();

        $quoteMethods = [
            'getId',
            'getBoltParentQuoteId',
            'getSubtotal',
            'getAllVisibleItems',
            'getAppliedRuleIds',
            'isVirtual',
            'getShippingAddress',
            'collectTotals',
            'getQuoteCurrencyCode',
            'getBillingAddress',
            'getReservedOrderId',
            'getTotals',
            'getStoreId',
            'getUseRewardPoints',
            'getUseCustomerBalance',
            'getRewardCurrencyAmount',
            'getCustomerBalanceAmountUsed',
            'getData'
        ];
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $quote->method('getId')->willReturn(self::IMMUTABLE_QUOTE_ID);
        $quote->method('getReservedOrderId')->willReturn('100010001');
        $quote->method('getBoltParentQuoteId')->willReturn(self::PARENT_QUOTE_ID);
        $quote->method('getSubtotal')->willReturn(self::PRODUCT_PRICE);
        $quote->method('getAllVisibleItems')->willReturn([$quoteItem]);
        $quote->method('getAppliedRuleIds')->willReturn('2,3');
        $quote->method('isVirtual')->willReturn(false);
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getQuoteCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $quote->method('collectTotals')->willReturnSelf();
        $quote->expects(static::any())->method('getStoreId')->willReturn("1");

        return $quote;
    }

    /**
     * Creates mocked instance of quote address
     *
     * @return Quote\Address|MockObject
     */
    private function getAddressMock()
    {
        $addressMock = $this->getMockBuilder(Quote\Address::class)
            ->setMethods(
                [
                    'getFirstname',
                    'getLastname',
                    'getCompany',
                    'getTelephone',
                    'getStreetLine',
                    'getCity',
                    'getRegion',
                    'getPostcode',
                    'getCountryId',
                    'getEmail',
                    'getDiscountAmount',
                    'getCouponCode'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $this->setUpAddressMock($addressMock);

        return $addressMock;
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
            'display_id'      => self::ORDER_INCREMENT_ID . ' / ' . self::IMMUTABLE_QUOTE_ID,
            'total_amount'    => 50000,
            'tax_amount'      => 1000,
            'currency'        => 'USD',
            'items'           =>
                [
                    [
                        'name'         => 'Beaded Long Dress',
                        'reference'    => '123ABC',
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
            'discounts'       =>
                [
                    [
                        'amount'      => 1000,
                        'description' => '10 dollars off',
                        'reference'   => 'DISCOUNT-10',
                        'details_url' => 'http://example.com/info/discount-10',
                    ],
                ],
        ];
    }

    /**
     * @test
     * that __construct assigns provided arguments to appropriate properties
     *
     * @covers ::__construct
     */
    public function __construct_always_assignsParametersToProperties()
    {
        $instance = new BoltHelperCart(
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
            $this->quoteRepository,
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
            $this->coreRegistry,
            $this->metricsClient,
            $this->deciderHelper
        );
        static::assertAttributeEquals($this->checkoutSession, 'checkoutSession', $instance);
        static::assertAttributeEquals($this->productRepository, 'productRepository', $instance);
        static::assertAttributeEquals($this->apiHelper, 'apiHelper', $instance);
        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
        static::assertAttributeEquals($this->customerSession, 'customerSession', $instance);
        static::assertAttributeEquals($this->logHelper, 'logHelper', $instance);
        static::assertAttributeEquals($this->bugsnag, 'bugsnag', $instance);
        static::assertAttributeEquals($this->imageHelperFactory, 'imageHelperFactory', $instance);
        static::assertAttributeEquals($this->appEmulation, 'appEmulation', $instance);
        static::assertAttributeEquals($this->dataObjectFactory, 'dataObjectFactory', $instance);
        static::assertAttributeEquals($this->quoteFactory, 'quoteFactory', $instance);
        static::assertAttributeEquals($this->totalsCollector, 'totalsCollector', $instance);
        static::assertAttributeEquals($this->quoteRepository, 'quoteRepository', $instance);
        static::assertAttributeEquals($this->orderRepository, 'orderRepository', $instance);
        static::assertAttributeEquals($this->searchCriteriaBuilder, 'searchCriteriaBuilder', $instance);
        static::assertAttributeEquals($this->quoteResource, 'quoteResource', $instance);
        static::assertAttributeEquals($this->sessionHelper, 'sessionHelper', $instance);
        static::assertAttributeEquals($this->checkoutHelper, 'checkoutHelper', $instance);
        static::assertAttributeEquals($this->discountHelper, 'discountHelper', $instance);
        static::assertAttributeEquals($this->cache, 'cache', $instance);
        static::assertAttributeEquals($this->resourceConnection, 'resourceConnection', $instance);
        static::assertAttributeEquals($this->quoteManagement, 'quoteManagement', $instance);
        static::assertAttributeEquals($this->hookHelper, 'hookHelper', $instance);
        static::assertAttributeEquals($this->customerRepository, 'customerRepository', $instance);
        static::assertAttributeEquals($this->metricsClient, 'metricsClient', $instance);
    }

    /**
     * @test
     * that instantiating the class via object manager configures properties with instances of expected classes
     *
     * @covers ::__construct
     */
    public function __construct_always_objectManagerProvidesExpectedClassInstances()
    {
        $om = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        $instance = $om->getObject(BoltHelperCart::class);
        static::assertAttributeInstanceOf(\Magento\Framework\Session\SessionManagerInterface::class, 'checkoutSession', $instance);
        static::assertAttributeInstanceOf(ProductRepository::class, 'productRepository', $instance);
        static::assertAttributeInstanceOf(ApiHelper::class, 'apiHelper', $instance);
        static::assertAttributeInstanceOf(ConfigHelper::class, 'configHelper', $instance);
        static::assertAttributeInstanceOf(CustomerSession::class, 'customerSession', $instance);
        static::assertAttributeInstanceOf(LogHelper::class, 'logHelper', $instance);
        static::assertAttributeInstanceOf(Bugsnag::class, 'bugsnag', $instance);
        static::assertAttributeInstanceOf(DataObjectFactory::class, 'dataObjectFactory', $instance);
        static::assertAttributeInstanceOf(ImageFactory::class, 'imageHelperFactory', $instance);
        static::assertAttributeInstanceOf(Emulation::class, 'appEmulation', $instance);
        static::assertAttributeInstanceOf(QuoteFactory::class, 'quoteFactory', $instance);
        static::assertAttributeInstanceOf(TotalsCollector::class, 'totalsCollector', $instance);
        static::assertAttributeInstanceOf(\Magento\Quote\Api\CartRepositoryInterface::class, 'quoteRepository', $instance);
        static::assertAttributeInstanceOf(\Magento\Sales\Api\OrderRepositoryInterface::class, 'orderRepository', $instance);
        static::assertAttributeInstanceOf(SearchCriteriaBuilder::class, 'searchCriteriaBuilder', $instance);
        static::assertAttributeInstanceOf(QuoteResource::class, 'quoteResource', $instance);
        static::assertAttributeInstanceOf(SessionHelper::class, 'sessionHelper', $instance);
        static::assertAttributeInstanceOf(CheckoutHelper::class, 'checkoutHelper', $instance);
        static::assertAttributeInstanceOf(DiscountHelper::class, 'discountHelper', $instance);
        static::assertAttributeInstanceOf(CacheInterface::class, 'cache', $instance);
        static::assertAttributeInstanceOf(ResourceConnection::class, 'resourceConnection', $instance);
        static::assertAttributeInstanceOf(CartManagementInterface::class, 'quoteManagement', $instance);
        static::assertAttributeInstanceOf(HookHelper::class, 'hookHelper', $instance);
        static::assertAttributeInstanceOf(CustomerRepository::class, 'customerRepository', $instance);
        static::assertAttributeInstanceOf(Registry::class, 'coreRegistry', $instance);
    }

    /**
     * @test
     * that isCheckoutAllowed returns true only if customer is logged in or guest checkout is enabled for the quote
     *
     * @covers ::isCheckoutAllowed
     *
     * @dataProvider isCheckoutAllowed_withVariousSessionStatesProvider
     *
     * @param bool $isLoggedIn flag
     * @param bool $isAllowedGuestCheckout flag
     * @param bool $expectedResult of the method call
     */
    public function isCheckoutAllowed_withVariousSessionStates_determinesIfCheckoutIsAllowed(
        $isLoggedIn,
        $isAllowedGuestCheckout,
        $expectedResult
    ) {
        $this->customerSession->expects(static::once())->method('isLoggedIn')->willReturn($isLoggedIn);
        $this->checkoutSession->method('getQuote')->willReturn(static::createMock(Quote::class));
        $this->checkoutHelper->expects($isLoggedIn ? static::never() : static::once())->method('isAllowedGuestCheckout')
            ->willReturn($isAllowedGuestCheckout);
        static::assertEquals($expectedResult, $this->currentMock->isCheckoutAllowed());
    }

    /**
     * Data provider for {@see isCheckoutAllowed_withVariousSessionStates_determinesIfCheckoutIsAllowed}
     *
     * @return array containing flags for logged in, is allowed guest checkout and excpected result
     */
    public function isCheckoutAllowed_withVariousSessionStatesProvider()
    {
        return [
            ['isLoggedIn' => false, 'isAllowedGuestCheckout' => false, 'expectedResult' => false],
            ['isLoggedIn' => false, 'isAllowedGuestCheckout' => true, 'expectedResult' => true],
            ['isLoggedIn' => true, 'isAllowedGuestCheckout' => false, 'expectedResult' => true],
            ['isLoggedIn' => true, 'isAllowedGuestCheckout' => true, 'expectedResult' => true],
        ];
    }

    /**
     * @test
     * that getQuoteById loads quote from repository if it's not already cached inside
     * @see \Bolt\Boltpay\Helper\Cart::$quotes
     *
     * @covers ::getQuoteById
     *
     * @throws ReflectionException if $quotes property doesn't exist
     */
    public function getQuoteById_withQuoteIdNotCached_loadsQuoteById()
    {
        TestHelper::setProperty($this->currentMock, 'quotes', []);
        $quoteMock = $this->createMock(Quote::class);
        $this->searchCriteriaBuilder->expects(static::once())->method('addFilter')
            ->with('main_table.entity_id', self::IMMUTABLE_QUOTE_ID)->willReturnSelf();
        $searchCriteriaMock = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilder->expects(static::once())->method('create')->willReturn($searchCriteriaMock);
        $this->quoteRepository->expects(static::once())->method('getList')->willReturnSelf();
        $this->quoteRepository->expects(static::once())->method('getItems')->willReturn([$quoteMock]);
        static::assertEquals($quoteMock, $this->currentMock->getQuoteById(self::IMMUTABLE_QUOTE_ID));
        static::assertAttributeEquals(
            [self::IMMUTABLE_QUOTE_ID => $quoteMock],
            'quotes',
            $this->currentMock
        );
    }

    /**
     * @test
     * that getQuoteById returns false if it is unable to load quote by id
     *
     * @covers ::getQuoteById
     *
     * @throws ReflectionException if $quotes property doesn't exist
     */
    public function getQuoteById_withQuoteIdNotCachedAndNotLoaded_returnsFalse()
    {
        TestHelper::setProperty($this->currentMock, 'quotes', []);
        $this->searchCriteriaBuilder->expects(static::once())->method('addFilter')
            ->with('main_table.entity_id', self::IMMUTABLE_QUOTE_ID)->willReturnSelf();
        $searchCriteriaMock = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilder->expects(static::once())->method('create')->willReturn($searchCriteriaMock);
        $this->quoteRepository->expects(static::once())->method('getList')->willReturnSelf();
        $this->quoteRepository->expects(static::once())->method('getItems')->willReturn([]);
        static::assertFalse($this->currentMock->getQuoteById(self::IMMUTABLE_QUOTE_ID));
        static::assertAttributeEquals([], 'quotes', $this->currentMock);
    }

    /**
     * @test
     * that getQuoteById returns quote from {@see \Bolt\Boltpay\Helper\Cart::$quotes} if is set
     *
     * @covers ::getQuoteById
     *
     * @throws ReflectionException if $quotes property doesn't exist
     */
    public function getQuoteById_withQuoteIdCached_returnsFromPropertyCache()
    {
        $quoteMock = $this->quoteMock;
        TestHelper::setProperty(
            $this->currentMock,
            'quotes',
            [self::IMMUTABLE_QUOTE_ID => $quoteMock]
        );
        $this->searchCriteriaBuilder->expects(static::never())->method('addFilter');
        $this->searchCriteriaBuilder->expects(static::never())->method('create');
        $this->quoteRepository->expects(static::never())->method('getList');
        $this->quoteRepository->expects(static::never())->method('getItems');
        static::assertEquals($quoteMock, $this->currentMock->getQuoteById(self::IMMUTABLE_QUOTE_ID));
        static::assertAttributeEquals(
            [self::IMMUTABLE_QUOTE_ID => $quoteMock],
            'quotes',
            $this->currentMock
        );
    }

    /**
     * @test
     * that getActiveQuoteById returns active quote by {@see \Magento\Quote\Api\CartRepositoryInterface::getActive}
     *
     * @covers ::getActiveQuoteById
     *
     * @throws NoSuchEntityException if active quote cannot be found
     */
    public function getActiveQuoteById_always_getsActiveQuoteFromRepository()
    {
        $this->quoteRepository->expects(static::once())->method('getActive')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn($this->quoteMock);
        static::assertEquals($this->quoteMock, $this->currentMock->getActiveQuoteById(self::IMMUTABLE_QUOTE_ID));
    }

    /**
     * @test
     * that getOrderByIncrementId loads order by increment id when forceLoad parameter is set to true
     * even when order already exists in {@see \Bolt\Boltpay\Helper\Cart::$orderData}
     *
     * @covers ::getOrderByIncrementId
     *
     * @throws ReflectionException if orderData property does not exist
     */
    public function getOrderByIncrementId_whenOrderCachedAndForceLoadTrue_loadsOrderByIncrementId()
    {
        TestHelper::setProperty(
            $this->currentMock,
            'orderData',
            [self::ORDER_INCREMENT_ID => $this->orderMock]
        );
        $this->searchCriteriaBuilder->expects(static::once())->method('addFilter')
            ->with('increment_id', self::ORDER_INCREMENT_ID, 'eq')->willReturnSelf();
        $searchCriteriaMock = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilder->expects(static::once())->method('create')->willReturn($searchCriteriaMock);
        $this->orderRepository->expects(static::once())->method('getList')->willReturnSelf();
        $this->orderRepository->expects(static::once())->method('getItems')->willReturn([$this->orderMock]);
        static::assertEquals(
            $this->orderMock,
            $this->currentMock->getOrderByIncrementId(self::ORDER_INCREMENT_ID, true)
        );
    }

    /**
     * @test
     * that saveQuote saves provided quote using {@see \Magento\Quote\Api\CartRepositoryInterface::save}
     *
     * @covers ::saveQuote
     */
    public function saveQuote_always_savesQuoteUsingQuoteRepository()
    {
        $this->quoteRepository->expects(static::once())->method('save')->with($this->quoteMock);
        $this->currentMock->saveQuote($this->quoteMock);
    }

    /**
     * @test
     * that deleteQuote deletes provided quote using {@see \Magento\Quote\Api\CartRepositoryInterface::delete}
     *
     * @covers ::deleteQuote
     */
    public function deleteQuote_always_deletesQuoteUsingQuoteRepository()
    {
        $this->quoteRepository->expects(static::once())->method('delete')->with($this->quoteMock);
        $this->currentMock->deleteQuote($this->quoteMock);
    }

    /**
     * @test
     * that quoteResourceSave saves provided quote using {@see \Magento\Quote\Model\ResourceModel\Quote::save}
     *
     * @covers ::quoteResourceSave
     *
     * @throws AlreadyExistsException if unable to save quote
     */
    public function quoteResourceSave_always_savesQuoteUsingResource()
    {
        $this->quoteResource->expects(static::once())->method('save')->with($this->quoteMock);
        $this->currentMock->quoteResourceSave($this->quoteMock);
    }

    /**
     * @test
     * that isBoltOrderCachingEnabled returns value from {@see \Bolt\Boltpay\Helper\Config::isBoltOrderCachingEnabled}
     *
     * @covers ::isBoltOrderCachingEnabled
     *
     * @throws ReflectionException if isBoltOrderCachingEnabled method doesn't exist
     */
    public function isBoltOrderCachingEnabled_always_returnsValueFromConfigHelper()
    {
        $isBoltOrderCachingEnabled = true;
        $this->configHelper->expects(static::once())->method('isBoltOrderCachingEnabled')->with(self::STORE_ID)
            ->willReturn($isBoltOrderCachingEnabled);
        static::assertEquals(
            $isBoltOrderCachingEnabled,
            TestHelper::invokeMethod($this->currentMock, 'isBoltOrderCachingEnabled', [self::STORE_ID])
        );
    }

    /**
     * @test
     * that loadFromCache returns false if identifier provided cannot be found in the cache
     *
     * @covers ::loadFromCache
     *
     * @throws ReflectionException if loadFromCache method is not defined
     */
    public function loadFromCache_whenIdentifierNotFoundInCache_returnsFalse()
    {
        $this->cache->expects(static::once())->method('load')->with(self::CACHE_IDENTIFIER)->willReturn(false);
        static::assertFalse(TestHelper::invokeMethod($this->currentMock, 'loadFromCache', [self::CACHE_IDENTIFIER]));
    }

    /**
     * @test
     * that loadFromCache returns value from cache without unserialization
     * if it exists in cache and unserialize parameter is set to false
     *
     * @covers ::loadFromCache
     *
     * @throws ReflectionException if loadFromCache method is not defined
     */
    public function loadFromCache_whenIdentifierIsFoundInCacheAndUnserializeFalse_returnsValue()
    {
        $cachedValue = 'Test cache value';
        $this->cache->expects(static::once())->method('load')->with(self::CACHE_IDENTIFIER)->willReturn($cachedValue);
        static::assertEquals(
            $cachedValue,
            TestHelper::invokeMethod(
                $this->currentMock,
                'loadFromCache',
                [self::CACHE_IDENTIFIER, false]
            )
        );
    }

    /**
     * @test
     * that loadFromCache returns unserialized value from cache
     * if it exists in cache and unserialize parameter is set to true
     *
     * @covers ::loadFromCache
     *
     * @throws ReflectionException if loadFromCache method is not defined
     */
    public function loadFromCache_whenIdentifierIsFoundInCacheAndUnserializeTrue_returnsUnserializedValue()
    {
        $cachedValue = $this->getTestCartData();
        $this->cache->expects(static::once())->method('load')->with(self::CACHE_IDENTIFIER)
            ->willReturn(TestHelper::serialize($this, $cachedValue));
        static::assertEquals(
            $cachedValue,
            TestHelper::invokeMethod(
                $this->currentMock,
                'loadFromCache',
                [self::CACHE_IDENTIFIER, true]
            )
        );
    }

    /**
     * @test
     * that saveToCache saves serialized value to cache when serialize parameter is true
     *
     * @covers ::saveToCache
     *
     * @throws ReflectionException if saveToCache method is not defined
     */
    public function saveToCache_whenSerializeParameterIsTrue_savesSerializedDataToCache()
    {
        $testCartData = $this->getTestCartData();
        $this->cache->expects(static::once())->method('save')->with(
            TestHelper::serialize($this, $testCartData),
            self::CACHE_IDENTIFIER,
            [],
            null
        );
        TestHelper::invokeMethod(
            $this->currentMock,
            'saveToCache',
            [$testCartData, self::CACHE_IDENTIFIER, [], null, true]
        );
    }

    /**
     * @test
     * that saveToCache saves non-serialized value to cache when serialize parameter is false
     *
     * @covers ::saveToCache
     *
     * @throws ReflectionException if saveToCache method is not defined
     */
    public function saveToCache_whenSerializeParameterIsFalse_savesNonSerializedDataToCache()
    {
        $this->cache->expects(static::once())->method('save')->with(
            $this->getTestCartData(),
            self::CACHE_IDENTIFIER,
            [],
            null
        );
        TestHelper::invokeMethod(
            $this->currentMock,
            'saveToCache',
            [$this->getTestCartData(), self::CACHE_IDENTIFIER, [], null, false]
        );
    }

    /**
     * @test
     * that setLastImmutableQuote sets provided quote to {@see \Bolt\Boltpay\Helper\Cart::$lastImmutableQuote}
     *
     * @covers ::setLastImmutableQuote
     *
     * @throws ReflectionException if setLastImmutableQuote method is not defined
     */
    public function setLastImmutableQuote_always_setsLastImmutableQuoteProperty()
    {
        TestHelper::invokeMethod(
            $this->currentMock,
            'setLastImmutableQuote',
            [$this->quoteMock]
        );
        static::assertAttributeEquals(
            $this->quoteMock,
            'lastImmutableQuote',
            $this->currentMock
        );
    }

    /**
     * @test
     * that getLastImmutableQuote gets provided quote to {@see \Bolt\Boltpay\Helper\Cart::$lastImmutableQuote}
     *
     * @covers ::getLastImmutableQuote
     *
     * @throws ReflectionException if getLastImmutableQuote method is not defined
     */
    public function getLastImmutableQuote_always_returnsLastImmutableQuoteProperty()
    {
        TestHelper::setProperty(
            $this->currentMock,
            'lastImmutableQuote',
            $this->quoteMock
        );
        static::assertEquals($this->quoteMock, TestHelper::invokeMethod($this->currentMock, 'getLastImmutableQuote'));
    }

    /**
     * @test
     * that saveCartSession calls {@see \Bolt\Boltpay\Helper\Session::saveSession} with provided quote id and checkout
     * session
     *
     * @covers ::saveCartSession
     *
     * @throws ReflectionException if saveSession method is not defined
     */
    public function saveCartSession_always_savesCartSession()
    {
        $this->sessionHelper->expects(static::once())->method('saveSession')
            ->with(self::IMMUTABLE_QUOTE_ID, $this->checkoutSession);
        TestHelper::invokeMethod($this->currentMock, 'saveCartSession', [self::IMMUTABLE_QUOTE_ID]);
    }

    /**
     * @test
     * that getSessionQuoteStoreId returns store id from checkout session quote
     *
     * @covers ::getSessionQuoteStoreId
     */
    public function getSessionQuoteStoreId_withSessionQuote_returnsSessionQuoteStoreId()
    {
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->method('getStoreId')->willReturn(self::STORE_ID);
        static::assertEquals(self::STORE_ID, $this->currentMock->getSessionQuoteStoreId());
    }

    /**
     * @test
     * that getSessionQuoteStoreId returns store id from checkout session quote
     *
     * @covers ::getSessionQuoteStoreId
     */
    public function getSessionQuoteStoreId_withoutSessionQuote_returnsNull()
    {
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn(null);
        static::assertEquals(null, $this->currentMock->getSessionQuoteStoreId());
    }

    /**
     * @test
     * that boltCreateOrder builds and sends API request to create order and returns response
     *
     * @covers ::boltCreateOrder
     *
     * @throws ReflectionException if boltCreateOrder method is not defined
     */
    public function boltCreateOrder_always_sendsApiRequest()
    {
        $cartData = $this->getTestCartData();
        $requestMock = $this->createMock(Request::class);
        $responseMock = $this->createMock(Response::class);
        $this->configHelper->expects(static::once())->method('getApiKey')->willReturn(self::API_KEY);
        $requestDataMock = $this->createPartialMock(
            DataObject::class,
            ['setApiData', 'setDynamicApiUrl', 'setApiKey']
        );
        $this->dataObjectFactory->expects(static::once())->method('create')->willReturn($requestDataMock);
        $requestDataMock->expects(static::once())->method('setApiData')->with(['cart' => $cartData]);
        $requestDataMock->expects(static::once())->method('setDynamicApiUrl')->with(ApiHelper::API_CREATE_ORDER);
        $requestDataMock->expects(static::once())->method('setApiKey')->with(self::API_KEY);
        $this->apiHelper->expects(static::once())->method('buildRequest')->with($requestDataMock)
            ->willReturn($requestMock);
        $this->apiHelper->expects(static::once())->method('sendRequest')->with($requestMock)
            ->willReturn($responseMock);
        static::assertEquals(
            $responseMock,
            TestHelper::invokeMethod($this->currentMock, 'boltCreateOrder', [$cartData, self::STORE_ID])
        );
    }

    /**
     * @test
     * that getCartCacheIdentifier returns cache identifier for provided cart data
     *
     * @covers ::getCartCacheIdentifier
     *
     * @throws ReflectionException if getCartCacheIdentifier method doesn't exist
     */
    public function getCartCacheIdentifier_always_returnsCartCacheIdentifier()
    {
        $currentMock = $this->getCurrentMock(
            [
                'convertCustomAddressFieldsToCacheIdentifier',
                'getLastImmutableQuote',
            ]
        );
        $testCartData = $this->getTestCartData();
        $addressCacheIdentifier = 'Test_Test_Test';
        $currentMock->expects(static::once())->method('getLastImmutableQuote')->willReturn($this->quoteMock);
        $currentMock->expects(static::once())->method('convertCustomAddressFieldsToCacheIdentifier')
            ->with($this->quoteMock)->willReturn($addressCacheIdentifier);
        $result = TestHelper::invokeMethod($currentMock, 'getCartCacheIdentifier', [$testCartData]);
        unset($testCartData['display_id']);
        static::assertEquals(hash('md5',json_encode($testCartData) . $addressCacheIdentifier), $result);
    }

    /**
     * @test
     *
     * @covers ::getCartCacheIdentifier
     * @throws ReflectionException
     */
    public function getCartCacheIdentifier_withGiftMessageID_returnsCartCacheIdentifier()
    {
        $currentMock = $this->getCurrentMock(
            [
                'convertCustomAddressFieldsToCacheIdentifier',
                'getLastImmutableQuote',
            ]
        );
        $testCartData = $this->getTestCartData();
        $addressCacheIdentifier = 'Test_Test_Test';
        $this->quoteMock->expects(static::once())->method('getGiftMessageId')->willReturn(self::GIFT_MESSAGE_ID);
        $currentMock->expects(static::once())->method('getLastImmutableQuote')->willReturn($this->quoteMock);
        $currentMock->expects(static::once())->method('convertCustomAddressFieldsToCacheIdentifier')
            ->with($this->quoteMock)->willReturn($addressCacheIdentifier);
        $result = TestHelper::invokeMethod($currentMock, 'getCartCacheIdentifier', [$testCartData]);
        unset($testCartData['display_id']);
        static::assertEquals(hash('md5',json_encode($testCartData) . $addressCacheIdentifier. self::GIFT_MESSAGE_ID), $result);
    }

    /**
     * @test
     *
     * @covers ::getCartCacheIdentifier
     * @throws ReflectionException
     */
    public function getCartCacheIdentifier_withGiftWrappingId_returnsCartCacheIdentifier()
    {
        $currentMock = $this->getCurrentMock(
            [
                'convertCustomAddressFieldsToCacheIdentifier',
                'getLastImmutableQuote',
            ]
        );
        $testCartData = $this->getTestCartData();
        $addressCacheIdentifier = 'Test_Test_Test';
        $this->quoteMock->expects(static::once())->method('getGwId')->willReturn(self::GIFT_WRAPPING_ID);
        $currentMock->expects(static::once())->method('getLastImmutableQuote')->willReturn($this->quoteMock);
        $currentMock->expects(static::once())->method('convertCustomAddressFieldsToCacheIdentifier')
            ->with($this->quoteMock)->willReturn($addressCacheIdentifier);
        $result = TestHelper::invokeMethod($currentMock, 'getCartCacheIdentifier', [$testCartData]);
        unset($testCartData['display_id']);
        static::assertEquals(hash('md5',json_encode($testCartData) . $addressCacheIdentifier. self::GIFT_WRAPPING_ID), $result);
    }

    /**
     * @test
     *
     * @covers ::getCartCacheIdentifier
     * @throws ReflectionException
     */
    public function getCartCacheIdentifier_withGiftWrappingItemIds_returnsCartCacheIdentifier()
    {
        $currentMock = $this->getCurrentMock(
            [
                'convertCustomAddressFieldsToCacheIdentifier',
                'getLastImmutableQuote',
            ]
        );
        $testCartData = $this->getTestCartData();
        $addressCacheIdentifier = 'Test_Test_Test';

        $quoteItem = $this->getMockBuilder(Item::class)
            ->setMethods(
                [
                    'getItemId',
                    'getGwId'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $quoteItem->method('getItemId')->willReturn(self::QUOTE_ITEM_ID);
        $quoteItem->method('getGwId')->willReturn(self::GIFT_WRAPPING_ID);
        $this->quoteMock->method('getAllVisibleItems')->willReturn([$quoteItem]);
        $currentMock->expects(static::once())->method('getLastImmutableQuote')->willReturn($this->quoteMock);
        $currentMock->expects(static::once())->method('convertCustomAddressFieldsToCacheIdentifier')
            ->with($this->quoteMock)->willReturn($addressCacheIdentifier);
        $result = TestHelper::invokeMethod($currentMock, 'getCartCacheIdentifier', [$testCartData]);
        unset($testCartData['display_id']);
        static::assertEquals(hash('md5',json_encode($testCartData) . $addressCacheIdentifier. self::QUOTE_ITEM_ID.'-'.self::GIFT_WRAPPING_ID), $result);
    }

    /**
     * @test
     * that getCustomAddressFieldsPascalCaseArray returns prefetch address fields from configuration
     * as pascal case array
     *
     * @covers ::getCustomAddressFieldsPascalCaseArray
     *
     * @throws ReflectionException if getCustomAddressFieldsPascalCaseArray method is not defined
     */
    public function getCustomAddressFieldsPascalCaseArray_always_returnsCustomAddressFieldsInPascalCaseArray()
    {
        $this->configHelper->expects(static::once())->method('getPrefetchAddressFields')->with(self::STORE_ID)
            ->willReturn('test_test_,_test_test,_test_test,test');
        $result = TestHelper::invokeMethod(
            $this->currentMock,
            'getCustomAddressFieldsPascalCaseArray',
            [self::STORE_ID]
        );
        static::assertEquals(
            [
                'TestTest',
                'TestTest',
                'TestTest',
                'Test',
            ],
            $result
        );
    }

    /**
     * @test
     * that convertCustomAddressFieldsToCacheIdentifier creates cache identifier based on custom address fields
     *
     * @covers ::convertCustomAddressFieldsToCacheIdentifier
     */
    public function convertCustomAddressFieldsToCacheIdentifier_always_returnsCacheIdentifier()
    {
        $this->quoteMock->expects(static::once())->method('getStoreId')->willReturn(self::STORE_ID);
        $customAddressFields = ['CustomField', 'OtherCustomField', 'NextField'];
        $addressMock = new DataObject(['custom_field' => 'custom_value', 'next_field' => 'next_value']);
        $currentMock = $this->getCurrentMock(['getCustomAddressFieldsPascalCaseArray', 'getCalculationAddress']);
        $currentMock->expects(static::once())->method('getCustomAddressFieldsPascalCaseArray')->with(self::STORE_ID)
            ->willReturn($customAddressFields);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($this->quoteMock)
            ->willReturn($addressMock);
        $result = $currentMock->convertCustomAddressFieldsToCacheIdentifier($this->quoteMock);
        static::assertEquals('_custom_value_next_value', $result);
    }

    /**
     * @test
     * that getImmutableQuoteIdFromBoltOrder returns immutable quote id from bolt order
     *
     * @covers ::getImmutableQuoteIdFromBoltOrder
     *
     * @throws ReflectionException if getImmutableQuoteIdFromBoltOrder method is not defined
     */
    public function getImmutableQuoteIdFromBoltOrder_always_returnsImmutableQuote()
    {
        $responseMock = $this->createPartialMock(Response::class, ['getResponse']);
        $responseMock->expects(static::once())->method('getResponse')->willReturn(
            (object)[
                'cart' => (object)[
                    'display_id' => self::ORDER_INCREMENT_ID . ' / ' . self::IMMUTABLE_QUOTE_ID
                ]
            ]
        );
        static::assertEquals(
            self::IMMUTABLE_QUOTE_ID,
            TestHelper::invokeMethod(
                $this->currentMock,
                'getImmutableQuoteIdFromBoltOrder',
                [$responseMock]
            )
        );
    }

    /**
     * @test
     * that isQuoteAvailable determines if quote is available by attempting to load the quote
     *
     * @covers ::isQuoteAvailable
     *
     * @throws ReflectionException if isQuoteAvailable method is not defined
     */
    public function isQuoteAvailable_always_determinesIfQuoteIsAvailable()
    {
        $currentMock = $this->getCurrentMock(['getQuoteById']);
        $currentMock->expects(static::once())->method('getQuoteById')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn($this->quoteMock);
        static::assertTrue(TestHelper::invokeMethod($currentMock, 'isQuoteAvailable', [self::IMMUTABLE_QUOTE_ID]));
    }

    /**
     * @test
     * that updateQuoteTimestamp executes SQL query to set updated_at field to current timestamp for provided quote id
     *
     * @covers ::updateQuoteTimestamp
     *
     * @throws ReflectionException if updateQuoteTimestamp method does not exist
     */
    public function updateQuoteTimestamp_always_updatesQuoteTimestamp()
    {
        $connectionMock = $this->createMock(Mysql::class);
        $this->resourceConnection->expects(static::once())->method('getConnection')->willReturn($connectionMock);
        $connectionMock->expects(static::once())->method('beginTransaction');
        $this->resourceConnection->expects(static::once())->method('getTableName')->with('quote')
            ->willReturn('quote');
        $connectionMock->expects(static::once())->method('query')->with(
            "UPDATE quote SET updated_at = CURRENT_TIMESTAMP WHERE entity_id = :entity_id",
            [
                'entity_id' => self::IMMUTABLE_QUOTE_ID
            ]
        );
        $connectionMock->expects(static::once())->method('commit');
        TestHelper::invokeMethod($this->currentMock, 'updateQuoteTimestamp', [self::IMMUTABLE_QUOTE_ID]);
    }

    /**
     * @test
     * that updateQuoteTimestamp rolls back transaction and notifies exception
     * if a {@see \Zend_Db_Statement_Exception} gets thrown during timestamp update
     *
     * @covers ::updateQuoteTimestamp
     *
     * @throws ReflectionException if updateQuoteTimestamp method does not exist
     */
    public function updateQuoteTimestamp_ifDBExceptionOccurs_notifiesExceptionAndRollsBack()
    {
        $connectionMock = $this->createMock(Mysql::class);
        $this->resourceConnection->expects(static::once())->method('getConnection')->willReturn($connectionMock);
        $connectionMock->expects(static::once())->method('beginTransaction');
        $this->resourceConnection->expects(static::once())->method('getTableName')->with('quote')
            ->willReturn('quote');
        $connectionMock->expects(static::once())->method('query')->with(
            "UPDATE quote SET updated_at = CURRENT_TIMESTAMP WHERE entity_id = :entity_id",
            [
                'entity_id' => self::IMMUTABLE_QUOTE_ID
            ]
        );
        $exception = new \Zend_Db_Statement_Exception();
        $connectionMock->expects(static::once())->method('commit')->willThrowException($exception);
        $connectionMock->expects(static::once())->method('rollBack');
        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception);
        TestHelper::invokeMethod($this->currentMock, 'updateQuoteTimestamp', [self::IMMUTABLE_QUOTE_ID]);
    }

    /**
     * @test
     * that clearExternalData calls {@see \Bolt\Boltpay\Helper\Discount::clearAmastyGiftCard} and
     * {@see \Bolt\Boltpay\Helper\Discount::clearAmastyRewardPoints}
     *
     * @covers ::clearExternalData
     *
     * @throws ReflectionException if clearExternalData method does not exist
     */
    public function clearExternalData_always_callsDiscountHelperMethods()
    {
        $this->discountHelper->expects(static::once())->method('clearAmastyGiftCard')->with($this->quoteMock);
        $this->discountHelper->expects(static::once())->method('clearAmastyRewardPoints')->with($this->quoteMock);
        TestHelper::invokeMethod($this->currentMock, 'clearExternalData', [$this->quoteMock]);
    }

    /**
     * @test
     * that getSignResponse sends provided data to be signed to the appropriate endpoint
     *
     * @covers ::getSignResponse
     *
     * @throws ReflectionException if getSignResponse method doesn't exist
     */
    public function getSignResponse_withSuccessfullRequest_returnsSignResponse()
    {
        $signRequest = ['merchant_user_id' => self::CUSTOMER_ID];
        $requestMock = $this->createMock(Request::class);
        $responseMock = $this->createMock(Response::class);
        $this->configHelper->expects(static::once())->method('getApiKey')->willReturn(self::API_KEY);
        $requestDataMock = $this->createPartialMock(
            DataObject::class,
            ['setApiData', 'setDynamicApiUrl', 'setApiKey']
        );
        $this->dataObjectFactory->expects(static::once())->method('create')->willReturn($requestDataMock);
        $requestDataMock->expects(static::once())->method('setApiData')->with($signRequest);
        $requestDataMock->expects(static::once())->method('setDynamicApiUrl')->with(ApiHelper::API_SIGN);
        $requestDataMock->expects(static::once())->method('setApiKey')->with(self::API_KEY);
        $this->apiHelper->expects(static::once())->method('buildRequest')->with($requestDataMock)
            ->willReturn($requestMock);
        $this->apiHelper->expects(static::once())->method('sendRequest')->with($requestMock)
            ->willReturn($responseMock);
        static::assertEquals(
            $responseMock,
            TestHelper::invokeMethod($this->currentMock, 'getSignResponse', [$signRequest, self::STORE_ID])
        );
    }

    /**
     * @test
     * that getSignResponse returns null if an exception occurs during sending the sign request
     *
     * @covers ::getSignResponse
     *
     * @throws ReflectionException if getSignResponse method doesn't exist
     */
    public function getSignResponse_withExceptionDuringRequest_returnsNull()
    {
        $signRequest = ['merchant_user_id' => self::CUSTOMER_ID];
        $requestMock = $this->createMock(Request::class);
        $this->configHelper->expects(static::once())->method('getApiKey')->willReturn(self::API_KEY);
        $requestDataMock = $this->createPartialMock(
            DataObject::class,
            ['setApiData', 'setDynamicApiUrl', 'setApiKey']
        );
        $this->dataObjectFactory->expects(static::once())->method('create')->willReturn($requestDataMock);
        $requestDataMock->expects(static::once())->method('setApiData')->with($signRequest);
        $requestDataMock->expects(static::once())->method('setDynamicApiUrl')->with(ApiHelper::API_SIGN);
        $requestDataMock->expects(static::once())->method('setApiKey')->with(self::API_KEY);
        $this->apiHelper->expects(static::once())->method('buildRequest')->with($requestDataMock)
            ->willReturn($requestMock);
        $this->apiHelper->expects(static::once())->method('sendRequest')->with($requestMock)
            ->willThrowException(new Exception());
        static::assertNull(
            TestHelper::invokeMethod($this->currentMock, 'getSignResponse', [$signRequest, self::STORE_ID])
        );
    }

    /**
     * @test
     * that getHints skips pre-fill for Apple Pay related data
     *
     * @covers ::getHints
     *
     * @throws NoSuchEntityException from tested method
     */
    public function getHints_withApplePayRelatedData_skipsPreFill()
    {
        $quoteMock = $this->createPartialMock(Quote::class, ['getCustomerEmail', 'isVirtual', 'getShippingAddress']);
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($quoteMock);
        $quoteMock->expects(static::once())->method('isVirtual')->willReturn(false);
        $shippingAddress = $this->createPartialMock(Quote\Address::class, ['getTelephone']);
        $shippingAddress->expects(static::once())->method('getTelephone')->willReturn('8005550111');
        $quoteMock->expects(static::once())->method('getCustomerEmail')->willReturn('na@bolt.com');
        $quoteMock->expects(static::once())->method('getShippingAddress')->willReturn($shippingAddress);
        $hints = $this->currentMock->getHints();
        static::assertEquals((object)[], $hints['prefill']);
    }

    /**
     * @test
     * that getEncodeUserId returns JSON object containing user id, timestamp and signature
     *
     * @covers ::getEncodeUserId
     *
     * @throws ReflectionException if getEncodeUserId method is not defined
     */
    public function getEncodeUserId_always_returnsSignedCustomerIdJSON()
    {
        $customerMock = $this->createMock(Customer::class);
        $customerMock->expects(static::once())->method('getId')->willReturn(self::CUSTOMER_ID);
        $signature = base64_encode(sha1('bolt'));
        $this->hookHelper->expects(static::once())->method('computeSignature')->with(
            static::callback(
                function ($payloadJSON) {
                    static::assertJson($payloadJSON);
                    $payload = json_decode($payloadJSON, true);
                    static::assertEquals(self::CUSTOMER_ID, $payload['user_id']);
                    return true;
                }
            )
        )->willReturn($signature);
        $this->customerSession->expects(static::once())->method('getCustomer')->willReturn($customerMock);
        $resultJSON = TestHelper::invokeMethod($this->currentMock, 'getEncodeUserId');
        static::assertJson($resultJSON);
        $result = json_decode($resultJSON, true);
        static::assertEquals($signature, $result['signature']);
        static::assertEquals(self::CUSTOMER_ID, $result['user_id']);
    }

    /**
     * @test
     * that transferData with default email and exclude fields parameters transfers data from provided parent to child
     *
     * @covers ::transferData
     *
     * @throws ReflectionException if transferData method is not defined
     */
    public function transferData_withDefaultFields_transfersDataFromParentToChildAndSavesTheChild()
    {
        $currentMock = $this->getCurrentMock(['validateEmail']);

        $this->quoteMock->expects(static::once())->method('getData')->willReturn(
            [
                'entity_id'          => self::PARENT_QUOTE_ID,
                'customer_firstname' => 'Test',
                'customer_lastname'  => 'Test',
                'customer_email'     => self::EMAIL_ADDRESS,
                'email'              => 'invalid.mail',
                'reserved_order_id'  => self::ORDER_INCREMENT_ID,
                'customer_id'        => self::CUSTOMER_ID,
            ]
        );

        $childQuoteMock = $this->createMock(Quote::class);
        $childQuoteMock->expects(static::atLeastOnce())->method('setData')->withConsecutive(
            ['customer_firstname', 'Test'],
            ['customer_lastname', 'Test'],
            ['customer_email', self::EMAIL_ADDRESS],
            ['customer_id', self::CUSTOMER_ID]
        );
        $childQuoteMock->expects(static::once())->method('save');
        $currentMock->expects(static::exactly(2))->method('validateEmail')->withConsecutive(
            [self::EMAIL_ADDRESS],
            ['invalid.mail']
        )->willReturnOnConsecutiveCalls(true, false);

        TestHelper::invokeMethod($currentMock, 'transferData', [$this->quoteMock, $childQuoteMock]);
    }

    /**
     * Setup method for tests covering {@see \Bolt\Boltpay\Helper\Cart::replicateQuoteData}
     *
     * @return MockObject[]|Quote[]|BoltHelperCart[] containing source quote, destination quote and current mock
     */
    private function replicateQuoteDataSetUp()
    {
        $sourceQuote = $this->createMock(Quote::class);
        $destinationQuote = $this->createMock(Quote::class);
        $sourceQuoteBillingAddress = $this->createMock(Quote\Address::class);
        $sourceQuoteShippingAddress = $this->createMock(Quote\Address::class);
        $sourceQuote->method('getBillingAddress')->willReturn($sourceQuoteBillingAddress);
        $sourceQuote->method('getShippingAddress')->willReturn($sourceQuoteShippingAddress);
        $destinationQuoteBillingAddress = $this->createMock(Quote\Address::class);
        $destinationQuoteShippingAddress = $this->createMock(Quote\Address::class);
        $destinationQuote->method('getBillingAddress')->willReturn($destinationQuoteBillingAddress);
        $destinationQuote->method('getShippingAddress')->willReturn($destinationQuoteShippingAddress);
        $currentMock = $this->getCurrentMock(['transferData', 'quoteResourceSave']);
        return [$sourceQuote, $destinationQuote, $currentMock];
    }

    /**
     * @test
     * that replicateQuoteData skips replication if both source and destination point to the same quote
     *
     * @covers ::replicateQuoteData
     *
     * @throws AlreadyExistsException from tested method
     * @throws Zend_Validate_Exception from tested method
     */
    public function replicateQuoteData_withSourceAndDestinationHavingSameId_skipsReplicatingQuoteData()
    {
        list($sourceQuote, $destinationQuote, $currentMock) = $this->replicateQuoteDataSetUp();
        $sourceQuote->expects(self::once())->method('getId')->willReturn(self::PARENT_QUOTE_ID);
        $destinationQuote->expects(self::once())->method('getId')->willReturn(self::PARENT_QUOTE_ID);
        $destinationQuote->expects(self::never())->method('removeAllItems');
        $sourceQuote->expects(self::never())->method('getAllVisibleItems');
        $currentMock->expects(static::never())->method('transferData');
        $currentMock->expects(static::never())->method('quoteResourceSave');
        $currentMock->replicateQuoteData($sourceQuote, $destinationQuote);
    }

    /**
     * @test
     * that replicateQuoteData copies quote data from source to destination
     *
     * @covers ::replicateQuoteData
     *
     * @throws AlreadyExistsException from method tested
     * @throws Zend_Validate_Exception from method tested
     */
    public function replicateQuoteData_withValidQuotes_replicatesQuoteData()
    {
        list($sourceQuote, $destinationQuote, $currentMock) = $this->replicateQuoteDataSetUp();
        $sourceQuote->expects(static::atLeastOnce())->method('getId')->willReturn(self::PARENT_QUOTE_ID);
        $destinationQuote->expects(self::atLeastOnce())->method('getId')->willReturn(self::IMMUTABLE_QUOTE_ID);
        $destinationQuote->expects(self::atLeastOnce())->method('getIsActive')->willReturn(true);
        $destinationQuote->expects(self::once())->method('removeAllItems');
        $quoteItem = $this->getMockBuilder(Item::class)
            ->setMethods(
                [
                    'getHasChildren',
                    'getChildren'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $quoteItem->expects(self::atLeastOnce())->method('getHasChildren')->willReturn(true);
        $quoteChildItem = $this->getMockBuilder(Item::class)
            ->setMethods(
                [
                    'setParentItem'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $quoteChildItem->expects(self::atLeastOnce())->method('setParentItem')->willReturn($quoteChildItem);
        $quoteItem->expects(self::atLeastOnce())->method('getChildren')->willReturn([$quoteChildItem]);
        $sourceQuote->method('getAllVisibleItems')->willReturn([$quoteItem]);
        $destinationQuote->expects(self::atLeastOnce())->method('addItem');
        $sourceQuote->method('getData')->willReturn([]);
        $sourceQuote->getBillingAddress()->method('getData')->willReturn([]);
        $sourceQuote->getShippingAddress()->method('getData')->willReturn([]);
        $currentMock->expects(static::once())->method('quoteResourceSave')->with($destinationQuote);
        $this->discountHelper->expects(static::once())->method('cloneAmastyGiftCards')
            ->with(self::PARENT_QUOTE_ID, self::IMMUTABLE_QUOTE_ID);
        $this->discountHelper->expects(static::once())->method('setAmastyRewardPoints')
            ->with($sourceQuote, $destinationQuote);
        $currentMock->replicateQuoteData($sourceQuote, $destinationQuote);
    }

    /**
     * Setup method for tests covering {@see \Bolt\Boltpay\Helper\Cart::reserveOrderId}
     *
     * @return MockObject[]|Quote[]|BoltHelperCart[]
     */
    private function reserveOrderIdSetUp()
    {
        $currentMock = $this->getCurrentMock(['quoteResourceSave']);
        $immutableQuote = $this->createPartialMock(
            Quote::class,
            [
                'getBoltReservedOrderId',
                'reserveOrderId',
                'getReservedOrderId',
                'setBoltReservedOrderId'
            ]
        );
        $parentQuote = $this->createPartialMock(Quote::class, ['setBoltReservedOrderId']);
        return [$currentMock, $immutableQuote, $parentQuote];
    }

    /**
     * @test
     * When bolt_reserved_order_id property on the immutable quote is empty, reserveOrderId reserves order id
     * for the immutable quote , then assigns the order id to immutable and parent quote bolt_reserved_order_id property
     *
     * @covers ::reserveOrderId
     *
     * @throws ReflectionException if reserveOrderId method is not defined
     */
    public function reserveOrderId_whenOrderIdNotReserved_reservesOrderId()
    {
        list($currentMock, $immutableQuote, $parentQuote) = $this->reserveOrderIdSetUp();
        $immutableQuote->expects(static::once())->method('getBoltReservedOrderId')->willReturn(null);
        $immutableQuote->expects(static::once())->method('reserveOrderId')->willReturnSelf();
        $immutableQuote->expects(static::once())->method('getReservedOrderId')->willReturn(self::ORDER_INCREMENT_ID);
        $immutableQuote->expects(static::once())->method('setBoltReservedOrderId')->with(self::ORDER_INCREMENT_ID);
        $parentQuote->expects(static::once())->method('setBoltReservedOrderId')->with(self::ORDER_INCREMENT_ID);
        $currentMock->expects(static::exactly(2))->method('quoteResourceSave')->withConsecutive(
            [$parentQuote],
            [$immutableQuote]
        );
        TestHelper::invokeMethod($currentMock, 'reserveOrderId', [$immutableQuote, $parentQuote]);
    }

    /**
     * @test
     * When bolt_reserved_order_id property on the immutable quote
     *
     * @covers ::reserveOrderId
     *
     * @throws ReflectionException if reserveOrderId method is not defined
     */
    public function reserveOrderId_whenOrderIdIsReserved_setsReservedOrderIdToImmutableQuote()
    {
        list($currentMock, $immutableQuote, $parentQuote) = $this->reserveOrderIdSetUp();
        $immutableQuote->expects(static::once())->method('getBoltReservedOrderId')
            ->willReturn(self::ORDER_INCREMENT_ID);
        $immutableQuote->expects(static::never())->method('getReservedOrderId');
        $immutableQuote->expects(static::never())->method('setBoltReservedOrderId');
        $parentQuote->expects(static::never())->method('setBoltReservedOrderId');
        $immutableQuote->expects(static::never())->method('setBoltReservedOrderId')->with(self::ORDER_INCREMENT_ID);
        $currentMock->expects(static::once())->method('quoteResourceSave')->with($immutableQuote);
        TestHelper::invokeMethod($currentMock, 'reserveOrderId', [$immutableQuote, $parentQuote]);
    }

    /**
     * @test
     * that createImmutableQuote returns new quote that has data replicated to it from provided quote
     *
     * @covers ::createImmutableQuote
     *
     * @throws ReflectionException if createImmutableQuote method is not defined
     */
    public function createImmutableQuote_always_createsImmutableQuote()
    {
        $quoteMock = $this->createPartialMock(
            Quote::class,
            [
                'setBoltCheckoutType',
                'getBoltParentQuoteId',
                'setBoltParentQuoteId',
                'getId'
            ]
        );
        $immutableQuoteMock = $this->createMock(Quote::class);
        $this->checkoutSession = $this->createMock(\Magento\Backend\Model\Session\Quote::class);
        $currentMock = $this->getCurrentMock(['quoteResourceSave', 'replicateQuoteData']);
        $quoteMock->expects(static::once())->method('setBoltCheckoutType')
            ->with(BoltHelperCart::BOLT_CHECKOUT_TYPE_BACKOFFICE);
        $quoteMock->expects(static::once())->method('getBoltParentQuoteId')->willReturn(null);
        $quoteMock->expects(static::once())->method('getId')->willReturn(self::PARENT_QUOTE_ID);
        $quoteMock->expects(static::once())->method('setBoltParentQuoteId')->with(self::PARENT_QUOTE_ID);
        $currentMock->expects(static::once())->method('quoteResourceSave')->with($quoteMock);
        $this->quoteFactory->expects(static::once())->method('create')->willReturn($immutableQuoteMock);
        $currentMock->expects(static::once())->method('replicateQuoteData')->with($quoteMock, $immutableQuoteMock);
        static::assertEquals(
            $immutableQuoteMock,
            TestHelper::invokeMethod($currentMock, 'createImmutableQuote', [$quoteMock])
        );
    }

    /**
     * @test
     * that isAddressComplete returns false for addresses missing at least one of the required fields
     *
     * @covers ::isAddressComplete
     *
     * @dataProvider isAddressComplete_withVariousAddressesProvider
     *
     * @param array $address data to be checked
     * @param bool  $expectedResult of the method call
     *
     * @throws ReflectionException if isAddressComplete method is undefined
     */
    public function isAddressComplete_withVariousAddresses_determinesIfAddressIsComplete($address, $expectedResult)
    {
        static::assertEquals(
            $expectedResult,
            TestHelper::invokeMethod($this->currentMock, 'isAddressComplete', [$address])
        );
    }

    /**
     * Data provider for {@see isAddressComplete_withVariousAddresses_determinesIfAddressIsComplete}
     * Returns one dataset with complete address data, and one for each of the required fields with that field missing
     *
     * @return array containing address data and expected method result
     */
    public function isAddressComplete_withVariousAddressesProvider()
    {
        $completeAddress = self::COMPLETE_ADDRESS_DATA;
        return array_reduce(
            array_keys($completeAddress),
            function ($datasets, $addressField) use ($completeAddress) {
                unset($completeAddress[$addressField]);
                return $datasets + [
                        "Address with missing '$addressField' field" => [
                            'address'        => $completeAddress,
                            'expectedResult' => false
                        ]
                    ];
            },
            ['Complete address' => ['address' => $completeAddress, 'expectedResult' => true]]
        );
    }

    /**
     * @test
     * that logAddressData registers Bugsnag callback that adds provided address data to meta data
     *
     * @covers ::logAddressData
     *
     * @throws ReflectionException if logAddressData method is not defined
     */
    public function logAddressData_always_registersBugsnagCallback()
    {
        $this->bugsnag->expects(static::once())->method('registerCallback')->with(
            static::callback(
                function ($callback) {
                    $reportMock = $this->createMock(Report::class);
                    $reportMock->expects(static::once())->method('setMetaData')
                        ->with(['ADDRESS_DATA' => self::COMPLETE_ADDRESS_DATA]);
                    $callback($reportMock);
                    return true;
                }
            )
        );
        TestHelper::invokeMethod($this->currentMock, 'logAddressData', [self::COMPLETE_ADDRESS_DATA]);
    }

    /**
     * @test
     * that isBackendSession returns true only if {@see \Bolt\Boltpay\Helper\Cart::$checkoutSession} property
     * is instance of {@see \Magento\Backend\Model\Session\Quote}
     *
     * @covers ::isBackendSession
     *
     * @dataProvider isBackendSession_withVariousSessionObjectsProvider
     *
     * @param SessionManagerInterface $sessionObject to be assigned to checkoutSession property
     * @param bool                    $expectedResult of the method call
     *
     * @throws ReflectionException if isBackendSession method is not available
     */
    public function isBackendSession_withVariousSessionObjects_determinesIfCheckoutSessionIsBackend(
        $sessionObject,
        $expectedResult
    ) {
        TestHelper::setProperty($this->currentMock, 'checkoutSession', $sessionObject);
        static::assertEquals($expectedResult, TestHelper::invokeMethod($this->currentMock, 'isBackendSession'));
    }

    /**
     * Data provider for {@see isBackendSession_withVariousSessionObjects_determinesIfCheckoutSessionIsBackend}
     *
     * @return array containing session object
     */
    public function isBackendSession_withVariousSessionObjectsProvider()
    {
        return [
            'Backend session' => [
                'sessionObject'  => $this->createMock(\Magento\Backend\Model\Session\Quote::class),
                'expectedResult' => true
            ],
            'Generic session' => [
                'sessionObject'  => $this->createMock(GenericSession::class),
                'expectedResult' => false
            ],
        ];
    }

    /**
     * @test
     * that getCalculationAddress returns billing address for virtual quotes, otherwise shipping address
     *
     * @covers ::getCalculationAddress
     *
     * @dataProvider getCalculationAddress_withVariousQuoteStatesProvider
     *
     * @param bool $isVirtual flag whether the quote is virtual
     *
     * @throws ReflectionException if getCalculationAddress method is not defined
     */
    public function getCalculationAddress_withVariousQuoteStates_returnsCalculationAddress($isVirtual)
    {
        $billingAddress = $this->createMock(Quote\Address::class);
        $shippingAddress = $this->createMock(Quote\Address::class);
        $this->quoteMock->method('isVirtual')->willReturn($isVirtual);
        $this->quoteMock->method('getBillingAddress')->willReturn($billingAddress);
        $this->quoteMock->method('getShippingAddress')->willReturn($shippingAddress);
        static::assertEquals(
            $isVirtual ? $billingAddress : $shippingAddress,
            TestHelper::invokeMethod(
                $this->currentMock,
                'getCalculationAddress',
                [$this->quoteMock]
            )
        );
    }

    /**
     * Data provider for {@see getCalculationAddress_withVariousQuoteStates_returnsCalculationAddress}
     *
     * @return array containing isVirtual quote flags
     */
    public function getCalculationAddress_withVariousQuoteStatesProvider()
    {
        return [
            ['isVirtual' => true],
            ['isVirtual' => false],
        ];
    }

    /**
     * @test
     * that validateEmail validates email addresses successfully
     *
     * @dataProvider validateEmail_withVarousEmailAddressesProvider
     *
     * @covers ::validateEmail
     *
     * @param string $email to be validated
     * @param bool   $expectedResult of the method call
     *
     * @throws Zend_Validate_Exception if validation class is not found
     */
    public function validateEmail_withVarousEmailAddresses_determinesIfEmailIsValid($email, $expectedResult)
    {
        static::assertEquals($expectedResult, $this->currentMock->validateEmail($email));
    }

    /**
     * Data provider for {@see validateEmail_withVarousEmailAddresses_determinesIfEmailIsValid}
     *
     * @return array containing email and expected result
     */
    public function validateEmail_withVarousEmailAddressesProvider()
    {
        return [
            ['email' => self::EMAIL_ADDRESS, 'expectedResult' => true],
            ['email' => 'test@bolt', 'expectedResult' => false],
            ['email' => 'test@.com', 'expectedResult' => false],
            ['email' => 'testbolt.com', 'expectedResult' => false],
            ['email' => 'bolt.com', 'expectedResult' => false],
        ];
    }

    /**
     * @test
     * that getWebsiteId returns website id from session quote
     *
     * @covers ::getWebsiteId
     *
     * @throws ReflectionException if getWebsiteId method is not defined
     */
    public function getWebsiteId_always_returnsWebsiteIdFromSessionQuote()
    {
        $storeMock = $this->createMock(Store::class);
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('getStore')->willReturn($storeMock);
        $storeMock->expects(static::once())->method('getWebsiteId')->willReturn(self::WEBSITE_ID);
        static::assertEquals(self::WEBSITE_ID, TestHelper::invokeMethod($this->currentMock, 'getWebsiteId'));
    }

    /**
     * @test
     * that handleSpecialAddressCases handles special Puerto Rico address case by
     *
     * @covers ::handleSpecialAddressCases
     * @covers ::handlePuertoRico
     */
    public function handleSpecialAddressCases_withPuertoRicoAddress_handlessPuertoRicoAddressSpecialCase()
    {
        $addressData = ['country_code' => 'PR'];
        $result = $this->currentMock->handleSpecialAddressCases($addressData);
        static::assertEquals(
            ['country_code' => 'US', 'country' => 'United States', 'region' => 'Puerto Rico'],
            $result
        );
    }

    /**
     * @test
     * that hasProductRestrictions returns false if toggle checkout config is empty
     *
     * @covers ::hasProductRestrictions
     *
     * @throws NoSuchEntityException if getToggleCheckout method is not defined
     */
    public function hasProductRestrictions_withToggleCheckoutEmpty_returnsFalse()
    {
        $this->configHelper->expects(static::once())->method('getToggleCheckout')->willReturn(null);
        static::assertFalse($this->currentMock->hasProductRestrictions($this->quoteMock));
    }

    /**
     * @test
     * that hasProductRestrictions returns false if toggle checkout config restriction methods are empty
     *
     * @covers ::hasProductRestrictions
     *
     * @throws NoSuchEntityException if getToggleCheckout method is not defined
     */
    public function hasProductRestrictions_withNoProductAndItemRestrictionMethods_returnsFalse()
    {
        $this->configHelper->expects(static::once())->method('getToggleCheckout')->willReturn(
            (object)['active' => true, 'productRestrictionMethods' => [], 'itemRestrictionMethods' => []]
        );
        static::assertFalse($this->currentMock->hasProductRestrictions($this->quoteMock));
    }

    /**
     * @test
     * that hasProductRestrictions returns false if there are no quote items
     *
     * @covers ::hasProductRestrictions
     *
     * @throws NoSuchEntityException if getToggleCheckout method is not defined
     */
    public function hasProductRestrictions_withNoQuoteItems_returnsFalse()
    {
        $this->configHelper->expects(static::once())->method('getToggleCheckout')->willReturn(
            (object)[
                'active'                    => true,
                'productRestrictionMethods' => ['getIsRestricted'],
                'itemRestrictionMethods'    => []
            ]
        );
        $this->quoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn([]);
        static::assertFalse($this->currentMock->hasProductRestrictions($this->quoteMock));
    }

    /**
     * @test
     * that hasProductRestrictions returns true if quote item returns true for one of the restricted methods
     *
     * @covers ::hasProductRestrictions
     *
     * @throws NoSuchEntityException if getToggleCheckout method is not defined
     */
    public function hasProductRestrictions_whenQuoteItemReturnsTrueForRestrictedMethod_returnsTrue()
    {
        $this->configHelper->expects(static::once())->method('getToggleCheckout')->willReturn(
            (object)[
                'active'                    => true,
                'productRestrictionMethods' => [],
                'itemRestrictionMethods'    => ['getIsRestricted']
            ]
        );
        $quoteItemMock = $this->createPartialMock(Quote\Item::class, ['getIsRestricted']);
        $quoteItemMock->expects(static::once())->method('getIsRestricted')->willReturn(true);
        $this->quoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn([$quoteItemMock]);
        static::assertTrue($this->currentMock->hasProductRestrictions($this->quoteMock));
    }

    /**
     * @test
     * that hasProductRestrictions returns true if quote item product returns true for one of the restricted methods
     *
     * @covers ::hasProductRestrictions
     *
     * @throws NoSuchEntityException if getToggleCheckout method is not defined
     */
    public function hasProductRestrictions_whenQuoteItemProductReturnsTrueForRestrictedMethod_returnsTrue()
    {
        $this->configHelper->expects(static::once())->method('getToggleCheckout')->willReturn(
            (object)[
                'active'                    => true,
                'productRestrictionMethods' => ['getIsRestricted'],
                'itemRestrictionMethods'    => []
            ]
        );
        $productMock = $this->createPartialMock(Product::class, ['getIsRestricted']);
        $quoteItemMock = $this->createMock(Quote\Item::class);
        $quoteItemMock->expects(static::once())->method('getSku')->willReturn(self::PRODUCT_SKU);
        $productMock->expects(static::once())->method('getIsRestricted')->willReturn(true);
        $this->productRepository->expects(static::once())->method('get')->with(self::PRODUCT_SKU)
            ->willReturn($productMock);
        $this->quoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn([$quoteItemMock]);
        static::assertTrue($this->currentMock->hasProductRestrictions($this->quoteMock));
    }

    /**
     * Setup method for tests covering {@see \Bolt\Boltpay\Helper\Cart::getBoltpayOrder}
     *
     * @return MockObject[]|BoltHelperCart[]|array[]|Response[]
     */
    private function getBoltpayOrderSetUp()
    {
        $currentMock = $this->getCurrentMock(
            [
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
                'doesOrderExist',
                'deactivateSessionQuote'
            ]
        );
        $cart = $this->getTestCartData();
        $parentQuoteId = CartTest::PARENT_QUOTE_ID;
        $quoteId = CartTest::IMMUTABLE_QUOTE_ID;
        $orderId = CartTest::ORDER_INCREMENT_ID;

        $order = json_decode(
        /** @lang JSON */
<<<ORDER
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
ORDER
        );
        $boltOrderResponse = new Response();
        $boltOrderResponse->setResponse($order);
        return [$currentMock, $cart, $boltOrderResponse];
        }

        /**
        * @test
        * that getBoltpayOrder always creates new Bolt order when caching is disabled
        *
        * @covers ::getBoltpayOrder
        *
        * @throws LocalizedException from tested method
        * @throws Zend_Http_Client_Exception from tested method
        */
        public function getBoltpayOrder_withCachingDisabled_createsBoltOrder()
        {
        list($currentMock, $cart, $boltOrder) = $this->getBoltpayOrderSetUp();

        $currentMock->expects(static::once())->method('getCartData')->with(false, '')->willReturn($cart);
        $currentMock->expects(static::once())->method('getSessionQuoteStoreId')->willReturn(self::STORE_ID);
        $currentMock->expects(static::once())->method('isBoltOrderCachingEnabled')->with(self::STORE_ID)
            ->willReturn(false);
        $currentMock->expects(static::never())->method('getCartCacheIdentifier');
        $currentMock->expects(static::never())->method('loadFromCache');
        $currentMock->expects(static::never())->method('getImmutableQuoteIdFromBoltOrder');
        $currentMock->expects(static::never())->method('isQuoteAvailable');
        $currentMock->expects(static::never())->method('getLastImmutableQuote');
        $currentMock->expects(static::never())->method('deleteQuote');
        $currentMock->expects(static::once())->method('saveCartSession')->with(self::PARENT_QUOTE_ID);
        $currentMock->expects(static::once())->method('boltCreateOrder')->with($cart, self::STORE_ID)
            ->willReturn($boltOrder);
        $currentMock->expects(static::never())->method('saveToCache');
        $result = $currentMock->getBoltpayOrder(false, '');
        static::assertEquals($result, $boltOrder);
        }

        /**
        * @test
        * that getBoltpayOrder deactivates session quote if order for the cart already exists
        *
        * @covers ::getBoltpayOrder
        *
        * @throws LocalizedException from tested method
        * @throws Zend_Http_Client_Exception from tested method
        */
        public function getBoltpayOrder_ifOrderExists_deactivatesSessionQuote()
        {
        $this->currentMock = $this->getCurrentMock(
            [
                'getCartData',
                'doesOrderExist',
                'deactivateSessionQuote',
                'getSessionQuoteStoreId',
                'isBoltOrderCachingEnabled',
            ]
        );
        $this->currentMock->expects(static::once())->method('getCartData')->with(false, '')
            ->willReturn($this->quoteMock);
        $this->currentMock->expects(static::once())->method('doesOrderExist')->with($this->quoteMock)->willReturn(true);
        $this->currentMock->expects(static::once())->method('deactivateSessionQuote')->willReturnSelf();
        $this->currentMock->expects(static::never())->method('getSessionQuoteStoreId')->willReturn(static::STORE_ID);
        $this->currentMock->expects(static::never())->method('isBoltOrderCachingEnabled')->with(static::STORE_ID)
            ->willReturn(false);

        static::assertNull($this->currentMock->getBoltpayOrder(false, ''));
        }

        /**
        * @test
        * that deactivateSessionQuote deactivates session quote if it is active
        *
        * @covers ::deactivateSessionQuote
        */
        public function deactivateSessionQuote_ifQuoteIsActive_deactivatesQuote()
        {
        $quoteMock = $this->createPartialMock(Quote::class, ['getIsActive', 'getId', 'setIsActive', 'save']);

        $quoteMock->expects(self::once())->method('getIsActive')->willReturn(true);
        $quoteMock->expects(self::once())->method('getId')->willReturn(self::QUOTE_ID);
        $quoteMock->expects(self::once())->method('setIsActive')->with(false)->willReturnSelf();
        $quoteMock->expects(self::once())->method('save')->willReturnSelf();

        $this->bugsnag->method('notifyError')
            ->with('Deactivate quote that associates with an existing order', 'QuoteId: ' . self::QUOTE_ID);

        $this->checkoutSession->expects(static::any())->method('getQuote')->willReturn($quoteMock);

        $this->currentMock->deactivateSessionQuote($quoteMock);
        }

        /**
        * @test
        * that doesOrderExists return order from {@see \Bolt\Boltpay\Helper\Cart::getOrderByIncrementId}
        * based on order increment id found in cart display id
        *
        * @covers ::doesOrderExist
        */
        public function doesOrderExist_withExistingOrder_basedOnGetOrderByIncrementId_returnsOrder()
        {
        $currentMock = $this->getCurrentMock(['getOrderByIncrementId']);
        $currentMock->expects(static::once())->method('getOrderByIncrementId')->with(self::ORDER_INCREMENT_ID)
            ->willReturn($this->orderMock);
        static::assertEquals(
            $this->orderMock,
            $currentMock->doesOrderExist(
                ['display_id' => self::ORDER_INCREMENT_ID . ' / ' . self::IMMUTABLE_QUOTE_ID],
                $this->quoteMock
            )
        );
        }

        /**
        * @test
        * that doesOrderExists return order from {@see \Bolt\Boltpay\Helper\Cart::getOrderByQuoteId}
        * based on session quote Id
        *
        * @covers ::doesOrderExist
        */
        public function doesOrderExist_withExistingOrder_basedOnGetOrderByQuoteId_returnsOrder()
        {
        $currentMock = $this->getCurrentMock(['getOrderByIncrementId','getOrderByQuoteId']);
        $currentMock->expects(static::once())->method('getOrderByIncrementId')->with(self::ORDER_INCREMENT_ID)
            ->willReturn(false);
        $this->quoteMock->expects(static::once())->method('getId')
            ->willReturn(self::PARENT_QUOTE_ID);
        $currentMock->expects(static::once())->method('getOrderByQuoteId')->with(self::PARENT_QUOTE_ID)
            ->willReturn($this->orderMock);

        static::assertEquals(
            $this->orderMock,
            $currentMock->doesOrderExist(
                ['display_id' => self::ORDER_INCREMENT_ID . ' / ' . self::IMMUTABLE_QUOTE_ID],
                $this->quoteMock
            )
        );
        }

        /**
        * @test
        * that doesOrderExists returns false if order cannot be found based on order increment id found in cart display id and session quote id
        * @see \Bolt\Boltpay\Helper\Cart::getOrderByIncrementId
        * @see \Bolt\Boltpay\Helper\Cart::getOrderByQuoteId
        *
        * @covers ::doesOrderExist
        */
        public function doesOrderExist_getOrderByIncrementIdReturnsFalse_getOrderByQuoteIdReturnsFalse_returnsFalse()
        {
        $currentMock = $this->getCurrentMock(['getOrderByIncrementId','getOrderByQuoteId']);
        $currentMock->expects(static::once())->method('getOrderByIncrementId')->with(self::ORDER_INCREMENT_ID)
            ->willReturn(false);

        $this->quoteMock->expects(static::once())->method('getId')
            ->willReturn(self::PARENT_QUOTE_ID);
        $currentMock->expects(static::once())->method('getOrderByQuoteId')->with(self::PARENT_QUOTE_ID)
            ->willReturn(false);

        static::assertFalse(
            $currentMock->doesOrderExist(
                ['display_id' => self::ORDER_INCREMENT_ID . ' / ' . self::IMMUTABLE_QUOTE_ID],
                $this->quoteMock
            )
        );
        }

        /**
        * @test
        */
        public function getOrderByQuoteId()
        {
        $quoteId = self::QUOTE_ID;

        $searchCriteria = $this->createMock(\Magento\Framework\Api\SearchCriteria::class);

        $this->searchCriteriaBuilder->expects($this->once())->method('addFilter')->with('quote_id', $quoteId, 'eq')->willReturnSelf();
        $this->searchCriteriaBuilder->expects($this->once())->method('create')->willReturn($searchCriteria);

        $orderInterface = $this->createMock(\Magento\Sales\Api\Data\OrderInterface::class);
        $orderInterface2 = $this->createMock(\Magento\Sales\Api\Data\OrderInterface::class);
        $collection = [$orderInterface, $orderInterface2];
        $orderSearchResultInterface = $this->createMock(\Magento\Sales\Api\Data\OrderSearchResultInterface::class);
        $orderSearchResultInterface->expects($this->once())->method('getItems')->willReturn($collection);

        $this->orderRepository->expects($this->once())->method('getList')->with($searchCriteria)->willReturn($orderSearchResultInterface);
        $this->assertSame($orderInterface, $this->currentMock->getOrderByQuoteId($quoteId));
        }

        /**
        * @test
        * that getBoltpayOrder creates new Bolt order when cache is enabled but current cart cannot be loaded
        *
        * @covers ::getBoltpayOrder
        *
        * @throws LocalizedException from tested method
        * @throws Zend_Http_Client_Exception from tested method
        */
        public function getBoltpayOrder_whenCacheEnabledAndEmpty_createsBoltOrderAndSavesToCache()
        {
        list($currentMock, $cart, $boltOrder) = $this->getBoltpayOrderSetUp();
        $currentMock->expects(static::once())->method('getCartData')->with(false, '')->willReturn($cart);
        $currentMock->expects(static::once())->method('getSessionQuoteStoreId')->willReturn(self::STORE_ID);
        $currentMock->expects(static::once())->method('isBoltOrderCachingEnabled')->with(self::STORE_ID)
            ->willReturn(true);
        $currentMock->expects(static::once())->method('getCartCacheIdentifier')->willReturn(self::CACHE_IDENTIFIER);
        $currentMock->expects(static::once())->method('loadFromCache')->with(self::CACHE_IDENTIFIER)->willReturn(false);
        $currentMock->expects(static::never())->method('getImmutableQuoteIdFromBoltOrder');
        $currentMock->expects(static::never())->method('isQuoteAvailable');
        $currentMock->expects(static::never())->method('getLastImmutableQuote');
        $currentMock->expects(static::never())->method('deleteQuote');
        $currentMock->expects(static::once())->method('saveCartSession')->with(self::PARENT_QUOTE_ID);
        $currentMock->expects(static::once())->method('boltCreateOrder')->with($cart, self::STORE_ID)
            ->willReturn($boltOrder);
        $currentMock->expects(static::once())->method('saveToCache')->with(
            $boltOrder,
            self::CACHE_IDENTIFIER,
            [BoltHelperCart::BOLT_ORDER_TAG, BoltHelperCart::BOLT_ORDER_TAG . '_' . self::PARENT_QUOTE_ID],
            3600
        );
        $result = $currentMock->getBoltpayOrder(false, '');
        static::assertEquals($result, $boltOrder);
        }

        /**
        * @test
        * that getBoltpayOrder returns Bolt order from cache if it exists in cache and related immutable quote is available
        *
        * @covers ::getBoltpayOrder
        *
        * @throws LocalizedException from tested method
        * @throws Zend_Http_Client_Exception from tested method
        */
        public function getBoltpayOrder_whenInCacheAndQuoteIsAvailable_returnsFromCache()
        {
        list($currentMock, $cart, $boltOrder) = $this->getBoltpayOrderSetUp();
        $immutableQuote = $this->getQuoteMock($this->getAddressMock(), $this->getAddressMock());
        $currentMock->expects(static::once())->method('getCartData')->with(false, '')->willReturn($cart);
        $currentMock->expects(static::once())->method('getSessionQuoteStoreId')->willReturn(self::STORE_ID);
        $currentMock->expects(static::once())->method('isBoltOrderCachingEnabled')
            ->with(self::STORE_ID)->willReturn(true);
        $currentMock->expects(static::once())->method('getCartCacheIdentifier')->willReturn(self::CACHE_IDENTIFIER);
        $currentMock->expects(static::once())->method('loadFromCache')->with(self::CACHE_IDENTIFIER)
            ->willReturn($boltOrder);
        $currentMock->expects(static::once())->method('getImmutableQuoteIdFromBoltOrder')->with($boltOrder)
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
        $currentMock->expects(static::once())->method('isQuoteAvailable')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn(true);
        $currentMock->expects(static::exactly(2))->method('getLastImmutableQuote')->willReturn($immutableQuote);
        $currentMock->expects(static::once())->method('updateQuoteTimestamp')->with(self::IMMUTABLE_QUOTE_ID);
        $currentMock->expects(static::once())->method('clearExternalData')->with($immutableQuote);
        $currentMock->expects(static::once())->method('deleteQuote')->with($immutableQuote);
        $currentMock->expects(static::never())->method('saveCartSession');
        $currentMock->expects(static::never())->method('boltCreateOrder');
        $currentMock->expects(static::never())->method('saveToCache');
        $result = $currentMock->getBoltpayOrder(false, '');

        static::assertEquals($result, $boltOrder);
        }

        /**
        * @test
        * that getBoltpayOrder returns Bolt order from cache if it exists in cache and related immutable quote is available
        *
        * @covers ::getBoltpayOrder
        *
        * @throws LocalizedException from tested method
        * @throws Zend_Http_Client_Exception from tested method
        */
        public function getBoltpayOrder_whenInCacheAndQuoteIsNotAvailable_createsNewOrder()
        {
        list($currentMock, $cart, $boltOrder) = $this->getBoltpayOrderSetUp();
        $currentMock->expects(static::once())->method('getCartData')->with(false, '')->willReturn($cart);
        $currentMock->expects(static::once())->method('getSessionQuoteStoreId')->willReturn(self::STORE_ID);
        $currentMock->expects(static::once())->method('isBoltOrderCachingEnabled')->with(self::STORE_ID)
            ->willReturn(true);
        $currentMock->expects(static::once())->method('getCartCacheIdentifier')->willReturn(self::CACHE_IDENTIFIER);
        $currentMock->expects(static::once())->method('loadFromCache')->with(self::CACHE_IDENTIFIER)
            ->willReturn($boltOrder);
        $currentMock->expects(static::once())->method('getImmutableQuoteIdFromBoltOrder')->with($boltOrder)
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
        $currentMock->expects(static::once())->method('isQuoteAvailable')->with(self::IMMUTABLE_QUOTE_ID)->willReturn(
            false
        );
        $currentMock->expects(static::never())->method('getLastImmutableQuote');
        $currentMock->expects(static::never())->method('deleteQuote');
        $currentMock->expects(static::once())->method('saveCartSession')->with(self::PARENT_QUOTE_ID);
        $currentMock->expects(static::once())->method('boltCreateOrder')->with($cart, self::STORE_ID)
            ->willReturn($boltOrder);
        $currentMock->expects(static::once())->method('saveToCache')
            ->with(
                $boltOrder,
                self::CACHE_IDENTIFIER,
                [BoltHelperCart::BOLT_ORDER_TAG, BoltHelperCart::BOLT_ORDER_TAG . '_' . self::PARENT_QUOTE_ID],
                3600
            );
        $result = $currentMock->getBoltpayOrder(false, '');
        static::assertEquals($result, $boltOrder);
        }

        /**
        * @test
        * that getBoltpayOrder doesn't create Bolt order
        * if {@see \Bolt\Boltpay\Helper\Cart::getCartData} returns empty array
        *
        * @covers ::getBoltpayOrder
        *
        * @throws LocalizedException from tested method
        * @throws Zend_Http_Client_Exception from tested method
        */
        public function getBoltpayOrder_withEmptyCartData_returnsWithoutCreatingOrder()
        {
        $currentMock = $this->getCurrentMock(['getCartData', 'boltCreateOrder']);
        $currentMock->expects(static::once())->method('getCartData')->willReturn([]);
        $currentMock->expects(static::never())->method('boltCreateOrder')->willReturn(null);
        static::assertNull($currentMock->getBoltpayOrder(false, ''));
        }

        /**
        * @test
        * that getCartData returns expected cart data when checkout type is multistep and there is no discount
        *
        * @covers ::getCartData
        *
        * @throws Exception from tested method
        */
        public function getCartData_inMultistepWithNoDiscount_returnsCartData()
        {
        $billingAddress = $this->getAddressMock();
        $shippingAddress = $this->getAddressMock();
        $quote = $this->getQuoteMock($billingAddress, $shippingAddress);

        $quote->method('getTotals')->willReturn([]);
        $this->checkoutSession->expects(static::any())->method('getQuote')->willReturn($quote);
        $this->searchCriteriaBuilder->expects(static::once())->method('addFilter')->withAnyParameters()
            ->willReturnSelf();
        $this->searchCriteriaBuilder->expects(static::once())->method('create')
            ->willReturn($this->createMock(SearchCriteria::class));

        $this->quoteRepository->expects(static::any())
            ->method('getList')
            ->with($this->createMock(SearchCriteria::class))
            ->willReturnSelf();
        $this->quoteRepository->expects(static::any())->method('getItems')->willReturn([$quote]);

        $paymentOnly = false;
        $placeOrderPayload = '';
        $immutableQuote = $quote;

        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('no-image');

        $this->productMock->method('getDescription')->willReturn('Product Description');

        $result = $this->currentMock->getCartData($paymentOnly, $placeOrderPayload, $immutableQuote);

        $expected = [
            'order_reference' => self::PARENT_QUOTE_ID,
            'display_id'      => '100010001 / ' . self::IMMUTABLE_QUOTE_ID,
            'currency'        => self::CURRENCY_CODE,
            'items'           => [
                [
                    'reference'    => self::PRODUCT_ID,
                    'name'         => 'Test Product',
                    'total_amount' => 10000,
                    'unit_price'   => 10000,
                    'quantity'     => 1,
                    'sku'          => self::PRODUCT_SKU,
                    'type'         => 'physical',
                    'description'  => 'Product Description',
                    'image_url'    => 'no-image'
                ]
            ],
            'billing_address' => [
                'first_name'      => 'IntegrationBolt',
                'last_name'       => 'BoltTest',
                'company'         => '',
                'phone'           => '132 231 1234',
                'street_address1' => '228 7th Avenue',
                'street_address2' => '228 7th Avenue 2',
                'locality'        => 'New York',
                'region'          => 'New York',
                'postal_code'     => '10011',
                'country_code'    => 'US',
                'email'           => self::EMAIL_ADDRESS
            ],
            'discounts'       => [],
            'total_amount'    => 10000,
            'tax_amount'      => 0
        ];

        static::assertEquals($expected, $result);
        }

        /**
        * @test
        * that getCartData returns empty array if parent quote has no items and immutable quote is not provided
        *
        * @covers ::getCartData
        *
        * @throws Exception from tested method
        */
        public function getCartData_withParentQuoteEmptyAndNoImmutableQuote_returnsEmptyArray()
        {
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn([]);
        static::assertEquals([], $this->currentMock->getCartData(false, '', null));
        }

        /**
        * @test
        * that getCartData returns empty array if immutable quote has no items
        *
        * @covers ::getCartData
        *
        * @throws Exception from tested method
        */
        public function getCartData_withEmptyImmutableQuote_returnsEmptyArray()
        {
        $currentMock = $this->getCurrentMock(['createImmutableQuote', 'reserveOrderId']);
        $currentMock->expects(static::once())->method('createImmutableQuote')->with($this->quoteMock)
            ->willReturn($this->immutableQuoteMock);
        $currentMock->expects(static::once())->method('reserveOrderId')
            ->with($this->immutableQuoteMock, $this->quoteMock);
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn([]);
        static::assertEquals([], $currentMock->getCartData(false, '', null));
        }

        /**
        * @test
        * that getCartData returns expected cart data when checkout type is payment and order payload is valid
        *
        * @covers ::getCartData
        *
        * @throws Exception from tested method
        */
        public function getCartData_whenPaymentOnlyAndHasOrderPayload_returnsCartData()
        {
        $currentMock = $this->getCurrentMock(
            [
                'setLastImmutableQuote',
                'getCartItems',
                'getQuoteById',
                'collectDiscounts',
                'createImmutableQuote',
                'reserveOrderId',
                'getCalculationAddress'
            ]
        );
        $testDiscounts = [
            [
                'description' => 'Test discount',
                'amount'      => 1000
            ]
        ];
        $testItems = [
            [
                'reference'    => 20102,
                'name'         => 'Test Product',
                'total_amount' => 10000,
                'unit_price'   => 10000,
                'quantity'     => 1.0,
                'sku'          => self::PRODUCT_SKU,
                'type'         => 'physical',
                'description'  => '',
            ],
        ];
        $this->setUpAddressMock($this->quoteShippingAddress);
        $currentMock->expects(static::once())->method('createImmutableQuote')->with($this->quoteMock)
            ->willReturn($this->immutableQuoteMock);
        $currentMock->expects(static::once())->method('reserveOrderId')
            ->with($this->immutableQuoteMock, $this->quoteMock);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($this->immutableQuoteMock)
            ->willReturn($this->quoteShippingAddress);
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->quoteShippingAddress->expects(static::atLeastOnce())->method('getShippingMethod')
            ->willReturn('flatrate_flatrate');
        $this->quoteMock->expects(static::any())->method('getShippingAddress')
            ->willReturn($this->quoteShippingAddress);
        $this->immutableQuoteMock->expects(static::once())->method('getBillingAddress')
            ->willReturn($this->getAddressMock());
        $this->immutableQuoteMock->expects(static::any())->method('getShippingAddress')
            ->willReturn($this->getAddressMock());
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getBoltParentQuoteId')
            ->willReturn(self::PARENT_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getReservedOrderId')
            ->willReturn(self::ORDER_INCREMENT_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getId')
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getQuoteCurrencyCode')
            ->willReturn(self::CURRENCY_CODE);
        $this->immutableQuoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $currentMock->expects(static::once())->method('getCartItems')->willReturn([$testItems, 10000, 0]);
        $currentMock->expects(static::once())->method('collectDiscounts')->willReturn([$testDiscounts, 9000, 0]);
        static::assertEquals(
            [
                'order_reference' => self::PARENT_QUOTE_ID,
                'display_id'      => self::ORDER_INCREMENT_ID . ' / ' . self::IMMUTABLE_QUOTE_ID,
                'currency'        => self::CURRENCY_CODE,
                'items'           => $testItems,
                'discounts'       => $testDiscounts,
                'total_amount'    => 9000.0,
                'tax_amount'      => 0,
                'shipments'       => [
                    [
                        'cost'             => 0,
                        'tax_amount'       => 0,
                        'shipping_address' => [
                            'first_name'      => 'IntegrationBolt',
                            'last_name'       => 'BoltTest',
                            'company'         => '',
                            'phone'           => '132 231 1234',
                            'street_address1' => '228 7th Avenue',
                            'street_address2' => '228 7th Avenue 2',
                            'locality'        => 'New York',
                            'region'          => 'New York',
                            'postal_code'     => '10011',
                            'country_code'    => 'US',
                            'email'           => self::EMAIL_ADDRESS,
                        ],
                        'service'          => null,
                        'reference'        => null,
                    ]
                ]
            ],
            $currentMock->getCartData(
                true,
                json_encode(
                    [
                        'billingAddress' => [
                            'firstname'    => "IntegrationBolt",
                            'lastname'     => "BoltTest",
                            'company'      => "Bolt",
                            'telephone'    => "132 231 1234",
                            'street'       => ["228 7th Avenue", "228 7th Avenue"],
                            'city'         => "New York",
                            'region'       => "New York",
                            'country'      => "United States",
                            'country_code' => "US",
                            'email'        => self::EMAIL_ADDRESS,
                            'postal_code'  => "10011",
                        ]
                    ]
                )
            )
        );
        }

        /**
        * @test
        * that getCartData returns empty array and notifies error when billing address data is insufficient
        * for virtual quote
        *
        * @covers ::getCartData
        *
        * @throws Exception from tested method
        */
        public function getCartData_withVirtualQuoteAndInsufficientBillingAddressData_notifiesErrorAndReturnsEmptyArray()
        {
        $currentMock = $this->getCurrentMock(
            [
                'setLastImmutableQuote',
                'getCartItems',
                'getQuoteById',
                'collectDiscounts',
                'createImmutableQuote',
                'reserveOrderId',
                'getCalculationAddress'
            ]
        );
        $this->setUpAddressMock($this->quoteShippingAddress);
        $currentMock->expects(static::once())->method('createImmutableQuote')->with($this->quoteMock)
            ->willReturn($this->immutableQuoteMock);
        $currentMock->expects(static::once())->method('reserveOrderId')
            ->with($this->immutableQuoteMock, $this->quoteMock);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($this->immutableQuoteMock)
            ->willReturn($this->quoteShippingAddress);
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->quoteMock->expects(static::any())->method('getShippingAddress')
            ->willReturn($this->quoteShippingAddress);
        $this->immutableQuoteMock->expects(static::once())->method('isVirtual')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getBillingAddress')
            ->willReturn($this->getAddressMock());
        $this->immutableQuoteMock->expects(static::any())->method('getShippingAddress')
            ->willReturn($this->getAddressMock());
        $this->immutableQuoteMock->expects(static::never())->method('getBoltParentQuoteId')
            ->willReturn(self::PARENT_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getReservedOrderId')
            ->willReturn(self::ORDER_INCREMENT_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getId')
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getQuoteCurrencyCode')
            ->willReturn(self::CURRENCY_CODE);
        $this->bugsnag->expects(static::once())->method('notifyError')
            ->with('Order create error', 'Billing address data insufficient.');
        $result = $currentMock->getCartData(
            true,
            json_encode(
                [
                    'billingAddress' => [
                        'firstname'    => "IntegrationBolt",
                        'lastname'     => "BoltTest",
                        'company'      => "Bolt",
                        'telephone'    => "132 231 1234",
                        'street'       => ["228 7th Avenue", "228 7th Avenue"],
                        'city'         => "New York",
                        'region'       => "New York",
                        'country'      => "United States",
                        'country_code' => "US",
                        'email'        => self::EMAIL_ADDRESS,
                        'postal_code'  => "10011",
                    ]
                ]
            )
        );
        static::assertEquals([], $result);
        }

        /**
        * @test
        * that getCartData returns expected cart data when checkout type is payment and quote is virtual
        *
        * @covers ::getCartData
        *
        * @throws Exception from tested method
        */
        public function getCartData_whenPaymentOnlyAndVirtualQuote_returnsCartData()
        {
        $testItem = [
            'reference'    => self::PRODUCT_ID,
            'name'         => 'Test Product',
            'total_amount' => 12345,
            'unit_price'   => 12345,
            'quantity'     => 1.0,
            'sku'          => self::PRODUCT_SKU,
            'type'         => 'physical',
            'description'  => '',
        ];
        $getCartItemsResult = [[$testItem], 12345, 0];
        $collectDiscountsResult = [[], 12345, 0];
        $currentMock = $this->getCurrentMock(
            [
                'setLastImmutableQuote',
                'getCartItems',
                'getQuoteById',
                'collectDiscounts',
                'createImmutableQuote',
                'reserveOrderId',
                'getCalculationAddress'
            ]
        );
        $this->setUpAddressMock($this->quoteBillingAddress);
        $currentMock->expects(static::once())->method('createImmutableQuote')->with($this->quoteMock)
            ->willReturn($this->immutableQuoteMock);
        $currentMock->expects(static::once())->method('reserveOrderId')
            ->with($this->immutableQuoteMock, $this->quoteMock);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($this->immutableQuoteMock)
            ->willReturn($this->quoteBillingAddress);
        $currentMock->expects(static::once())->method('getCartItems')->willReturn($getCartItemsResult);
        $currentMock->expects(static::once())->method('collectDiscounts')->willReturn($collectDiscountsResult);
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->quoteMock->expects(static::any())->method('getShippingAddress')
            ->willReturn($this->quoteShippingAddress);
        $this->immutableQuoteMock->expects(static::once())->method('isVirtual')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getBillingAddress')
            ->willReturn($this->quoteBillingAddress);
        $this->immutableQuoteMock->expects(static::any())->method('getShippingAddress')
            ->willReturn($this->getAddressMock());
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getBoltParentQuoteId')
            ->willReturn(self::PARENT_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getReservedOrderId')
            ->willReturn(self::ORDER_INCREMENT_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getId')
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getQuoteCurrencyCode')
            ->willReturn(self::CURRENCY_CODE);
        $result = $currentMock->getCartData(
            true,
            json_encode(
                [
                    'billingAddress' => [
                        'firstname' => "IntegrationBolt",
                        'lastname'  => "BoltTest",
                        'company'   => "Bolt",
                        'telephone' => "132 231 1234",
                        'street'    => ["228 7th Avenue", "228 7th Avenue"],
                        'city'      => "New York",
                        'region'    => "New York",
                        'country'   => "United States",
                        'countryId' => "US",
                        'email'     => self::EMAIL_ADDRESS,
                        'postcode'  => "10011",
                    ]
                ]
            )
        );
        static::assertEquals(
            [
                'order_reference' => 1000,
                'display_id'      => '100010001 / 1001',
                'currency'        => 'USD',
                'items'           => [$testItem],
                'billing_address' =>
                    [
                        'first_name'      => 'IntegrationBolt',
                        'last_name'       => 'BoltTest',
                        'company'         => 'Bolt',
                        'phone'           => '132 231 1234',
                        'street_address1' => '228 7th Avenue',
                        'street_address2' => '228 7th Avenue',
                        'locality'        => 'New York',
                        'region'          => 'New York',
                        'postal_code'     => '10011',
                        'country_code'    => 'US',
                        'email'           => self::EMAIL_ADDRESS,
                    ],
                'discounts'       => [],
                'total_amount'    => 12345,
                'tax_amount'      => 0,
            ],
            $result
        );
        }

        /**
        * @test
        * that getCartData populates registry rule_data when executed from backend
        *
        * @covers ::getCartData
        */
        public function getCartData_fromBackendAndCustomerGroupIdNotInQuote_initializesRuleData()
        {
        $this->checkoutSession = $this->createPartialMock(
            \Magento\Backend\Model\Session\Quote::class,
            ['getStore', 'getCustomerGroupId', 'getQuote']
        );
        $testItem = [
            'reference'    => self::PRODUCT_ID,
            'name'         => 'Test Product',
            'total_amount' => 12345,
            'unit_price'   => 12345,
            'quantity'     => 1.0,
            'sku'          => self::PRODUCT_SKU,
            'type'         => 'physical',
            'description'  => '',
        ];
        $getCartItemsResult = [[$testItem], 12345, 0];
        $collectDiscountsResult = [[], 12345, 0];
        $currentMock = $this->getCurrentMock(
            [
                'setLastImmutableQuote',
                'getCartItems',
                'getQuoteById',
                'collectDiscounts',
                'createImmutableQuote',
                'reserveOrderId',
                'getCalculationAddress'
            ]
        );
        $this->setUpAddressMock($this->quoteBillingAddress);
        $currentMock->expects(static::once())->method('createImmutableQuote')->with($this->quoteMock)
            ->willReturn($this->immutableQuoteMock);
        $currentMock->expects(static::once())->method('reserveOrderId')
            ->with($this->immutableQuoteMock, $this->quoteMock);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($this->immutableQuoteMock)
            ->willReturn($this->quoteBillingAddress);
        $currentMock->expects(static::once())->method('getCartItems')->willReturn($getCartItemsResult);
        $currentMock->expects(static::once())->method('collectDiscounts')->willReturn($collectDiscountsResult);
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->quoteMock->expects(static::any())->method('getShippingAddress')
            ->willReturn($this->quoteShippingAddress);
        $this->immutableQuoteMock->expects(static::once())->method('isVirtual')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getBillingAddress')
            ->willReturn($this->quoteBillingAddress);
        $this->immutableQuoteMock->expects(static::any())->method('getShippingAddress')
            ->willReturn($this->getAddressMock());
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getBoltParentQuoteId')
            ->willReturn(self::PARENT_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getReservedOrderId')
            ->willReturn(self::ORDER_INCREMENT_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getId')
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getQuoteCurrencyCode')
            ->willReturn(self::CURRENCY_CODE);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getCustomerGroupId')
            ->willReturn(null);

        $storeMock = $this->createMock(\Magento\Store\Model\Store::class);
        $storeMock->method('getId')->willReturn(self::STORE_ID);
        $storeMock->method('getWebsiteId')->willReturn(1);

        $this->checkoutSession->method('getStore')->willReturn($storeMock);
        $this->checkoutSession->method('getCustomerGroupId')
            ->willReturn(\Magento\Customer\Api\Data\GroupInterface::NOT_LOGGED_IN_ID);
        $this->coreRegistry->expects(static::once())->method('unregister')->with('rule_data');
        $this->coreRegistry->expects(static::once())->method('register')->with(
            'rule_data',
            new DataObject(
                [
                    'store_id'          => self::STORE_ID,
                    'website_id'        => 1,
                    'customer_group_id' => \Magento\Customer\Api\Data\GroupInterface::NOT_LOGGED_IN_ID
                ]
            )
        );
        $currentMock->getCartData(
            true,
            json_encode(
                [
                    'billingAddress' => [
                        'firstname' => "IntegrationBolt",
                        'lastname'  => "BoltTest",
                        'company'   => "Bolt",
                        'telephone' => "132 231 1234",
                        'street'    => ["228 7th Avenue", "228 7th Avenue"],
                        'city'      => "New York",
                        'region'    => "New York",
                        'country'   => "United States",
                        'countryId' => "US",
                        'email'     => self::EMAIL_ADDRESS,
                        'postcode'  => "10011",
                    ]
                ]
            )
        );
        }

        /**
        * @test
        * that getCartData populates registry rule_data when executed from backend
        *
        * @covers ::getCartData
        */
        public function getCartData_fromBackendAndCustomerGroupIdInQuote_initializesRuleData()
        {
        $this->checkoutSession = $this->createPartialMock(
            \Magento\Backend\Model\Session\Quote::class,
            ['getStore', 'getCustomerGroupId', 'getQuote']
        );
        $testItem = [
            'reference'    => self::PRODUCT_ID,
            'name'         => 'Test Product',
            'total_amount' => 12345,
            'unit_price'   => 12345,
            'quantity'     => 1.0,
            'sku'          => self::PRODUCT_SKU,
            'type'         => 'physical',
            'description'  => '',
        ];
        $getCartItemsResult = [[$testItem], 12345, 0];
        $collectDiscountsResult = [[], 12345, 0];
        $currentMock = $this->getCurrentMock(
            [
                'setLastImmutableQuote',
                'getCartItems',
                'getQuoteById',
                'collectDiscounts',
                'createImmutableQuote',
                'reserveOrderId',
                'getCalculationAddress'
            ]
        );
        $this->setUpAddressMock($this->quoteBillingAddress);
        $currentMock->expects(static::once())->method('createImmutableQuote')->with($this->quoteMock)
            ->willReturn($this->immutableQuoteMock);
        $currentMock->expects(static::once())->method('reserveOrderId')
            ->with($this->immutableQuoteMock, $this->quoteMock);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($this->immutableQuoteMock)
            ->willReturn($this->quoteBillingAddress);
        $currentMock->expects(static::once())->method('getCartItems')->willReturn($getCartItemsResult);
        $currentMock->expects(static::once())->method('collectDiscounts')->willReturn($collectDiscountsResult);
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->quoteMock->expects(static::any())->method('getShippingAddress')
            ->willReturn($this->quoteShippingAddress);
        $this->immutableQuoteMock->expects(static::once())->method('isVirtual')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getBillingAddress')
            ->willReturn($this->quoteBillingAddress);
        $this->immutableQuoteMock->expects(static::any())->method('getShippingAddress')
            ->willReturn($this->getAddressMock());
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getBoltParentQuoteId')
            ->willReturn(self::PARENT_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getReservedOrderId')
            ->willReturn(self::ORDER_INCREMENT_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getId')
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getQuoteCurrencyCode')
            ->willReturn(self::CURRENCY_CODE);
        $this->immutableQuoteMock->expects(static::exactly(2))->method('getCustomerGroupId')
            ->willReturn(\Magento\Customer\Api\Data\GroupInterface::CUST_GROUP_ALL);

        $storeMock = $this->createMock(\Magento\Store\Model\Store::class);
        $storeMock->method('getId')->willReturn(self::STORE_ID);
        $storeMock->method('getWebsiteId')->willReturn(1);

        $this->checkoutSession->method('getStore')->willReturn($storeMock);
        $this->checkoutSession->expects(static::never())->method('getCustomerGroupId');
        $this->coreRegistry->expects(static::once())->method('unregister')->with('rule_data');
        $this->coreRegistry->expects(static::once())->method('register')->with(
            'rule_data',
            new DataObject(
                [
                    'store_id'          => self::STORE_ID,
                    'website_id'        => 1,
                    'customer_group_id' => \Magento\Customer\Api\Data\GroupInterface::CUST_GROUP_ALL
                ]
            )
        );
        $currentMock->getCartData(
            true,
            json_encode(
                [
                    'billingAddress' => [
                        'firstname' => "IntegrationBolt",
                        'lastname'  => "BoltTest",
                        'company'   => "Bolt",
                        'telephone' => "132 231 1234",
                        'street'    => ["228 7th Avenue", "228 7th Avenue"],
                        'city'      => "New York",
                        'region'    => "New York",
                        'country'   => "United States",
                        'countryId' => "US",
                        'email'     => self::EMAIL_ADDRESS,
                        'postcode'  => "10011",
                    ]
                ]
            )
        );
        }

        /**
        * Setup method for tests covering {@see \Bolt\Boltpay\Helper\Cart::getCartData}
        *
        * @param array $getCartItemsResult stubbed result for {@see \Bolt\Boltpay\Helper\Cart::getCartItems}
        * @param array $collectDiscountsResult stubbed result for {@see \Bolt\Boltpay\Helper\Cart::collectDiscounts}
        *
        * @return BoltHelperCart|MockObject
        */
        private function getCartDataSetUp(array $getCartItemsResult, array $collectDiscountsResult)
        {
        $currentMock = $this->getCurrentMock(
            [
                'setLastImmutableQuote',
                'getCartItems',
                'getQuoteById',
                'collectDiscounts',
                'createImmutableQuote',
                'reserveOrderId',
                'getCalculationAddress'
            ]
        );
        $this->setUpAddressMock($this->quoteShippingAddress);
        $currentMock->expects(static::once())->method('createImmutableQuote')->with($this->quoteMock)
            ->willReturn($this->immutableQuoteMock);
        $currentMock->expects(static::once())->method('reserveOrderId')
            ->with($this->immutableQuoteMock, $this->quoteMock);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($this->immutableQuoteMock)
            ->willReturn($this->quoteShippingAddress);
        $currentMock->expects(static::any())->method('getCartItems')->willReturn($getCartItemsResult);
        $currentMock->expects(static::any())->method('collectDiscounts')->willReturn($collectDiscountsResult);
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->quoteMock->expects(static::any())->method('getShippingAddress')
            ->willReturn($this->quoteShippingAddress);
        $this->immutableQuoteMock->expects(static::once())->method('isVirtual')->willReturn(false);
        $this->immutableQuoteMock->expects(static::once())->method('getBillingAddress')
            ->willReturn($this->getAddressMock());
        $this->immutableQuoteMock->expects(static::any())->method('getShippingAddress')
            ->willReturn($this->getAddressMock());
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getReservedOrderId')
            ->willReturn(self::ORDER_INCREMENT_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getId')
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getQuoteCurrencyCode')
            ->willReturn(self::CURRENCY_CODE);
        return $currentMock;
        }

        /**
        * @test
        * that getCartData adds diff to the first item total to pass bolt order create check
        *
        * @covers ::getCartData
        *
        * @throws Exception from tested method
        */
        public function getCartData_withDiffAboveThreshold_diffIsAddedToFirstItem()
        {
        $testItem = [
            'reference'    => self::PRODUCT_ID,
            'name'         => 'Test Product',
            'total_amount' => 12345,
            'unit_price'   => 12345,
            'quantity'     => 1.0,
            'sku'          => self::PRODUCT_SKU,
            'type'         => 'physical',
            'description'  => '',
        ];
        $getCartItemsResult = [[$testItem], 12345, 123];
        $collectDiscountsResult = [[], 12345, 123];
        $currentMock = $this->getCartDataSetUp($getCartItemsResult, $collectDiscountsResult);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getBoltParentQuoteId')
            ->willReturn(self::PARENT_QUOTE_ID);

        $this->quoteShippingAddress->expects(static::once())->method('setCollectShippingRates')->with(true);
        $this->quoteShippingAddress->expects(static::any())->method('getShippingMethod')
            ->willReturn('flatrate_flatrate');
        $this->quoteShippingAddress->expects(static::any())->method('setShippingMethod')->with('flatrate_flatrate');
        $this->bugsnag->expects(static::once())->method('registerCallback')->with(
            static::callback(
                function ($callback) {
                    $reportMock = $this->createMock(Report::class);
                    $reportMock->expects(static::once())->method('setMetaData')->with(
                        static::arrayHasKey('TOTALS_DIFF')
                    );
                    $callback($reportMock);
                    return true;
                }
            )
        );
        $this->bugsnag->expects(static::once())->method('notifyError')
            ->with('Cart Totals Mismatch', 'Totals adjusted by 123.');
        $result = $currentMock->getCartData(
            true,
            json_encode(
                [
                    'billingAddress' => [
                        'firstname'    => "IntegrationBolt",
                        'lastname'     => "BoltTest",
                        'company'      => "Bolt",
                        'telephone'    => "132 231 1234",
                        'street'       => ["228 7th Avenue", "228 7th Avenue"],
                        'city'         => "New York",
                        'region'       => "New York",
                        'country'      => "United States",
                        'country_code' => "US",
                        'email'        => self::EMAIL_ADDRESS,
                        'postal_code'  => "10011",
                    ]
                ]
            )
        );
        static::assertEquals(
            [
                'order_reference' => 1000,
                'display_id'      => '100010001 / 1001',
                'currency'        => 'USD',
                'items'           => [
                    ['total_amount' => 12468] + $testItem,
                ],
                'shipments'       => [
                    [
                        'cost'             => 0,
                        'tax_amount'       => 0,
                        'shipping_address' =>
                            [
                                'first_name'      => 'IntegrationBolt',
                                'last_name'       => 'BoltTest',
                                'company'         => '',
                                'phone'           => '132 231 1234',
                                'street_address1' => '228 7th Avenue',
                                'street_address2' => '228 7th Avenue 2',
                                'locality'        => 'New York',
                                'region'          => 'New York',
                                'postal_code'     => '10011',
                                'country_code'    => 'US',
                                'email'           => self::EMAIL_ADDRESS,
                            ],
                        'service'          => null,
                        'reference'        => null,
                    ],
                ],
                'discounts'       => [],
                'total_amount'    => 12468,
                'tax_amount'      => 0,
            ],
            $result
        );
        }

        /**
        * @test
        * that getCartData adds fixed amount type to all discounts if total amount is negative and sets total to 0
        *
        * @covers ::getCartData
        *
        * @throws Exception from tested method
        */
        public function getCartData_withNegativeTotalAmount_returnsZeroTotalAndAppliesFixedAmountTypeToDiscounts()
        {
        $testItem = [
            'reference'    => self::PRODUCT_ID,
            'name'         => 'Test Product',
            'total_amount' => 12345,
            'unit_price'   => 12345,
            'quantity'     => 1.0,
            'sku'          => self::PRODUCT_SKU,
            'type'         => 'physical',
            'description'  => '',
        ];
        $getCartItemsResult = [[$testItem], 12345, 0];
        $testDiscount = ['description' => 'Test discount', 'amount' => 22345];
        $collectDiscountsResult = [[$testDiscount], -10000, 0];
        $currentMock = $this->getCartDataSetUp($getCartItemsResult, $collectDiscountsResult);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getBoltParentQuoteId')
            ->willReturn(self::PARENT_QUOTE_ID);

        $this->quoteShippingAddress->expects(static::once())->method('setCollectShippingRates')->with(true);
        $this->quoteShippingAddress->expects(static::any())->method('getShippingMethod')
            ->willReturn('flatrate_flatrate');
        $this->quoteShippingAddress->expects(static::any())->method('setShippingMethod')->with('flatrate_flatrate');
        $result = $currentMock->getCartData(
            true,
            json_encode(
                [
                    'billingAddress' => [
                        'firstname'    => "IntegrationBolt",
                        'lastname'     => "BoltTest",
                        'company'      => "Bolt",
                        'telephone'    => "132 231 1234",
                        'street'       => ["228 7th Avenue", "228 7th Avenue"],
                        'city'         => "New York",
                        'region'       => "New York",
                        'country'      => "United States",
                        'country_code' => "US",
                        'email'        => self::EMAIL_ADDRESS,
                        'postal_code'  => "10011",
                    ]
                ]
            )
        );
        static::assertEquals(
            [
                'order_reference' => 1000,
                'display_id'      => '100010001 / 1001',
                'currency'        => 'USD',
                'items'           => [$testItem,],
                'shipments'       => [
                    [
                        'cost'             => 0,
                        'tax_amount'       => 0,
                        'shipping_address' =>
                            [
                                'first_name'      => 'IntegrationBolt',
                                'last_name'       => 'BoltTest',
                                'company'         => '',
                                'phone'           => '132 231 1234',
                                'street_address1' => '228 7th Avenue',
                                'street_address2' => '228 7th Avenue 2',
                                'locality'        => 'New York',
                                'region'          => 'New York',
                                'postal_code'     => '10011',
                                'country_code'    => 'US',
                                'email'           => self::EMAIL_ADDRESS,
                            ],
                        'service'          => null,
                        'reference'        => null,
                    ],
                ],
                'discounts'       => [$testDiscount + ['type' => 'fixed_amount']],
                'total_amount'    => 0,
                'tax_amount'      => 0,
            ],
            $result
        );
        }

        /**
        * @test
        * that getCartData returns empty array and notifies error if shipping method is not set
        *
        * @covers ::getCartData
        *
        * @throws Exception from tested method
        */
        public function getCartData_paymentOnlyAndShippingMethodMissing_notifiesErrorAndReturnsEmptyArray()
        {
        $testItem = [
            'reference'    => self::PRODUCT_ID,
            'name'         => 'Test Product',
            'total_amount' => 12345,
            'unit_price'   => 12345,
            'quantity'     => 1.0,
            'sku'          => self::PRODUCT_SKU,
            'type'         => 'physical',
            'description'  => '',
        ];
        $getCartItemsResult = [[$testItem], 12345, 123];
        $collectDiscountsResult = [[], 12345, 123];
        $currentMock = $this->getCartDataSetUp($getCartItemsResult, $collectDiscountsResult);

        $this->quoteShippingAddress->expects(static::once())->method('setCollectShippingRates')->with(true);
        $this->quoteShippingAddress->expects(static::any())->method('getShippingMethod')->willReturn(null);
        $this->quoteShippingAddress->expects(static::any())->method('setShippingMethod')->with(null);

        $this->bugsnag->expects(static::once())->method('notifyError')
            ->with('Order create error', 'Shipping method not set.');
        $result = $currentMock->getCartData(
            true,
            json_encode(
                [
                    'billingAddress' => [
                        'firstname'    => "IntegrationBolt",
                        'lastname'     => "BoltTest",
                        'company'      => "Bolt",
                        'telephone'    => "132 231 1234",
                        'street'       => ["228 7th Avenue", "228 7th Avenue"],
                        'city'         => "New York",
                        'region'       => "New York",
                        'country'      => "United States",
                        'country_code' => "US",
                        'email'        => self::EMAIL_ADDRESS,
                        'postal_code'  => "10011",
                    ]
                ]
            )
        );
        static::assertEquals([], $result);
        }

        /**
        * @test
        * that getCartData returns empty array and notifies error if shipping address is not complete
        *
        * @covers ::getCartData
        *
        * @throws Exception from tested method
        */
        public function getCartData_paymentOnlyAndShippingAddresIncomplete_returnsEmptyArrayAndNotifiesError()
        {
        $testItem = [
            'reference'    => self::PRODUCT_ID,
            'name'         => 'Test Product',
            'total_amount' => 12345,
            'unit_price'   => 12345,
            'quantity'     => 1.0,
            'sku'          => self::PRODUCT_SKU,
            'type'         => 'physical',
            'description'  => '',
        ];
        $getCartItemsResult = [[$testItem], 12345, 123];
        $collectDiscountsResult = [[], 12345, 123];
        $currentMock = $this->getCurrentMock(
            [
                'setLastImmutableQuote',
                'getCartItems',
                'getQuoteById',
                'collectDiscounts',
                'createImmutableQuote',
                'reserveOrderId',
                'getCalculationAddress'
            ]
        );
        $currentMock->expects(static::once())->method('createImmutableQuote')->with($this->quoteMock)
            ->willReturn($this->immutableQuoteMock);
        $currentMock->expects(static::once())->method('reserveOrderId')
            ->with($this->immutableQuoteMock, $this->quoteMock);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($this->immutableQuoteMock)
            ->willReturn($this->quoteShippingAddress);
        $currentMock->expects(static::any())->method('getCartItems')->willReturn($getCartItemsResult);
        $currentMock->expects(static::any())->method('collectDiscounts')->willReturn($collectDiscountsResult);
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->quoteMock->expects(static::any())->method('getShippingAddress')
            ->willReturn($this->quoteShippingAddress);
        $this->immutableQuoteMock->expects(static::once())->method('isVirtual')->willReturn(false);
        $this->immutableQuoteMock->expects(static::once())->method('getBillingAddress')
            ->willReturn($this->getAddressMock());
        $this->immutableQuoteMock->expects(static::any())->method('getShippingAddress')
            ->willReturn($this->getAddressMock());
        $this->immutableQuoteMock->expects(static::never())->method('getBoltParentQuoteId')
            ->willReturn(self::PARENT_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getReservedOrderId')
            ->willReturn(self::ORDER_INCREMENT_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getId')
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getQuoteCurrencyCode')
            ->willReturn(self::CURRENCY_CODE);
        $this->quoteShippingAddress->expects(static::once())->method('setCollectShippingRates')->with(true);
        $this->quoteShippingAddress->expects(static::any())->method('getShippingMethod')
            ->willReturn('flatrate_flatrate');
        $this->quoteShippingAddress->expects(static::any())->method('setShippingMethod')->with('flatrate_flatrate');
        $this->bugsnag->expects(static::once())->method('notifyError')
            ->with('Order create error', 'Shipping address data insufficient.');
        $result = $currentMock->getCartData(true, json_encode([]));
        static::assertEquals([], $result);
        }

        /**
        * @test
        * that getCartData will get email address from customer session
        *
        * @covers ::getCartData
        *
        * @throws Exception from tested method
        */
        public function getCartData_withEmptyAddressEmail_retrievesAddressFromSessionCustom()
        {
        $currentMock = $this->getCurrentMock(
            [
                'setLastImmutableQuote',
                'getCartItems',
                'getQuoteById',
                'collectDiscounts',
                'createImmutableQuote',
                'reserveOrderId',
                'getCalculationAddress'
            ]
        );
        $this->quoteShippingAddress->method('getFirstname')->willReturn($this->testAddressData['first_name']);
        $this->quoteShippingAddress->method('getLastname')->willReturn($this->testAddressData['last_name']);
        $this->quoteShippingAddress->method('getCompany')->willReturn($this->testAddressData['company']);
        $this->quoteShippingAddress->method('getTelephone')->willReturn($this->testAddressData['phone']);
        $this->quoteShippingAddress->method('getStreetLine')
            ->willReturnMap(
                [
                    [1, $this->testAddressData['street_address1']],
                    [2, $this->testAddressData['street_address2']]
                ]
            );
        $this->quoteShippingAddress->method('getCity')->willReturn($this->testAddressData['locality']);
        $this->quoteShippingAddress->method('getRegion')->willReturn($this->testAddressData['region']);
        $this->quoteShippingAddress->method('getPostcode')->willReturn($this->testAddressData['postal_code']);
        $this->quoteShippingAddress->method('getCountryId')->willReturn($this->testAddressData['country_code']);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($this->immutableQuoteMock)
            ->willReturn($this->quoteShippingAddress);
        $this->immutableQuoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getQuoteCurrencyCode')
            ->willReturn(self::CURRENCY_CODE);
        $this->immutableQuoteMock->expects(static::once())->method('getBillingAddress')
            ->willReturn($this->quoteBillingAddress);
        $this->immutableQuoteMock->expects(static::once())->method('getShippingAddress')
            ->willReturn($this->quoteShippingAddress);
        $this->customerSession->expects(static::once())->method('getCustomer')->willReturn($this->customerMock);
        $this->customerMock->expects(static::once())->method('getEmail')->willReturn(self::EMAIL_ADDRESS);
        $this->quoteShippingAddress->expects(static::atLeastOnce())->method('getShippingMethod')
            ->willReturn('flatrate_flatrate');
        $testItems = [
            [
                'reference'    => 20102,
                'name'         => 'Test Product',
                'total_amount' => 10000,
                'unit_price'   => 10000,
                'quantity'     => 1.0,
                'sku'          => self::PRODUCT_SKU,
                'type'         => 'physical',
                'description'  => '',
            ],
        ];
        $currentMock->expects(static::once())->method('getCartItems')->willReturn([$testItems, 123456, 0]);
        $currentMock->expects(static::once())->method('collectDiscounts')->willReturn([[], 12345, 0]);
        $result = $currentMock->getCartData(true, json_encode([]), $this->immutableQuoteMock);
        static::assertEquals(self::EMAIL_ADDRESS, $result['shipments'][0]['shipping_address']['email']);
        }

        /**
        * @test
        * that collectDiscounts returns provided parameters unchanged and an empty array for discounts
        * if there are no discounts applied
        *
        * @covers ::collectDiscounts
        *
        * @throws LocalizedException  from tested method
        * @throws NoSuchEntityException from tested method
        */
        public function collectDiscounts_withNoDiscounts_returnsParametersUnchanged()
        {
        $currentMock = $this->getCurrentMock();
        $shippingAddress = $this->getAddressMock();

        $quote = $this->getQuoteMock($this->getAddressMock(), $this->getAddressMock());

        $quote->method('getBoltParentQuoteId')->willReturn(999999);
        $quote->method('getTotals')->willReturn([]);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($quote)
            ->willReturn($shippingAddress);
        $shippingAddress->expects(static::once())->method('getDiscountAmount')->willReturn(0);
        $quote->expects(static::once())->method('getUseCustomerBalance')->willReturn(false);
        $this->discountHelper->expects(static::once())->method('isMirasvitStoreCreditAllowed')->with($quote)
            ->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAheadworksStoreCredit');
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAmastyPayForEverything');
        $this->discountHelper->expects(static::never())->method('getMageplazaGiftCardCodes');
        $this->discountHelper->expects(static::never())->method('getUnirgyGiftCertBalanceByCode');

        $totalAmount = 10000;
        $diff = 0;
        $paymentOnly = false;

        list($discounts, $totalAmountResult, $diffResult) = $currentMock->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $quote
        );

        static::assertEquals($diffResult, $diff);
        static::assertEquals(10000, $totalAmountResult);
        static::assertEquals([], $discounts);
        }

        /**
        * @test
        * that collectDiscounts handles default coupon code discount
        *
        * @covers ::collectDiscounts
        *
        * @throws NoSuchEntityException from method tested
        */
        public function collectDiscounts_withCouponCode_collectsCouponCodeDiscount()
        {
        $currentMock = $this->getCurrentMock();
        $shippingAddress = $this->getAddressMock();
        $quote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);
        $quote->method('getBoltParentQuoteId')->willReturn(999999);
        $currentMock->expects(static::once())->method('getQuoteById')->willReturn($quote);
        $quote->method('getTotals')->willReturn([]);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($quote)
            ->willReturn($shippingAddress);
        $shippingAddress->expects(static::any())->method('getCouponCode')->willReturn('123456');
        $quote->expects(static::once())->method('getUseCustomerBalance')->willReturn(false);
        $this->discountHelper->expects(static::once())->method('isMirasvitStoreCreditAllowed')->with($quote)
            ->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAheadworksStoreCredit');
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAmastyPayForEverything');
        $this->discountHelper->expects(static::never())->method('getMageplazaGiftCardCodes');
        $this->discountHelper->expects(static::never())->method('getUnirgyGiftCertBalanceByCode');
        $appliedDiscount = 10; // $
        $shippingAddress->expects(static::once())->method('getDiscountAmount')->willReturn($appliedDiscount);

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = false;
        list($discounts, $totalAmountResult, $diffResult) = $currentMock->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $quote
        );

        static::assertEquals($diffResult, $diff);
        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;
        static::assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        static::assertEquals($expectedTotalAmount, $totalAmountResult);
        }

        /**
        * @test
        * that collectDiscounts collects Store Credit from quote property if checkout type is payment
        *
        * @covers ::collectDiscounts
        *
        * @throws NoSuchEntityException from tested method
        */
        public function collectDiscounts_withStoreCreditAndPaymentOnly_collectsDiscountFromQuote()
        {
        $currentMock = $this->getCurrentMock();
        $shippingAddress = $this->getAddressMock();
        $quote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);
        $quote->method('getTotals')->willReturn([]);
        $quote->method('getBoltParentQuoteId')->willReturn(999999);
        $currentMock->expects(static::once())->method('getQuoteById')->willReturn($quote);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($quote)
            ->willReturn($shippingAddress);
        $shippingAddress->expects(static::any())->method('getCouponCode')->willReturn(false);
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $quote->expects(static::once())->method('getUseCustomerBalance')->willReturn(true);
        $this->discountHelper->expects(static::once())->method('isMirasvitStoreCreditAllowed')->with($quote)
            ->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAheadworksStoreCredit');
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAmastyPayForEverything');
        $this->discountHelper->expects(static::never())->method('getMageplazaGiftCardCodes');
        $this->discountHelper->expects(static::never())->method('getUnirgyGiftCertBalanceByCode');
        $appliedDiscount = 10; // $
        $quote->expects(static::once())->method('getCustomerBalanceAmountUsed')->willReturn($appliedDiscount);
        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;
        list($discounts, $totalAmountResult, $diffResult) = $currentMock->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $quote
        );
        static::assertEquals($diffResult, $diff);
        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;
        static::assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        static::assertEquals($expectedTotalAmount, $totalAmountResult);
        }

        /**
        * @test
        * that collectDiscounts properly handles Magento EE Store Credit when checkout type is not payment
        *
        * @covers ::collectDiscounts
        *
        * @throws NoSuchEntityException from tested method
        */
        public function collectDiscounts_withStoreCreditAndNotPaymentOnly_loadsCustomerBalanceUsingModel()
        {
        $currentMock = $this->getCurrentMock(
            [
                'getWebsiteId',
                'getQuoteById',
                'getCalculationAddress'
            ]
        );
        $shippingAddress = $this->getAddressMock();

        $currentMock->expects(static::once())->method('getWebsiteId')->willReturn(self::WEBSITE_ID);
        $currentMock->expects(static::once())->method('getQuoteById')->willReturn($this->quoteMock);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($this->immutableQuoteMock)
            ->willReturn($shippingAddress);
        $shippingAddress->expects(static::any())->method('getCouponCode')->willReturn(false);
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $this->immutableQuoteMock->expects(static::once())->method('getTotals')->willReturn([]);
        $this->immutableQuoteMock->expects(static::once())->method('getQuoteCurrencyCode')
            ->willReturn(self::CURRENCY_CODE);
        $this->immutableQuoteMock->expects(static::once())->method('getUseCustomerBalance')->willReturn(true);
        $this->discountHelper->expects(static::once())->method('isMirasvitStoreCreditAllowed')
            ->with($this->immutableQuoteMock)
            ->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAheadworksStoreCredit');
        $this->immutableQuoteMock->expects(static::once())->method('getUseRewardPoints')->willReturn(false);

        ObjectManager::setInstance($this->objectManagerMock);
        $customerBalanceMock = $this->getMockBuilder('Magento\CustomerBalance\Model\Balance')
            ->disableOriginalConstructor()
            ->setMethods(['setCustomer', 'setWebsiteId', 'loadByCustomer', 'getAmount'])
            ->getMock();
        $this->objectManagerMock->expects(static::once())->method('create')
            ->with('Magento\CustomerBalance\Model\Balance')->willReturn($customerBalanceMock);

        $this->customerSession->expects(static::once())->method('getCustomer')->willReturn($this->customerMock);
        $customerBalanceMock->expects(static::once())->method('setCustomer')->with($this->customerMock)
            ->willReturnSelf();
        $customerBalanceMock->expects(static::once())->method('setWebsiteId')->with(self::WEBSITE_ID)
            ->willReturnSelf();
        $customerBalanceMock->expects(static::once())->method('loadByCustomer')->willReturnSelf();
        $appliedDiscount = 10; // $
        $customerBalanceMock->expects(static::once())->method('getAmount')->willReturn($appliedDiscount);

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = false;

        list($discounts, $totalAmountResult, $diffResult) = $currentMock->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $this->immutableQuoteMock
        );

        static::assertEquals($diffResult, $diff);

        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;

        static::assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        static::assertEquals('fixed_amount', $discounts[0]['type']);
        static::assertEquals('Store Credit', $discounts[0]['description']);
        static::assertEquals($expectedTotalAmount, $totalAmountResult);
        }

        /**
        * @test
        * that collectDiscounts properly handles Magento EE Reward Points when checkout type is not payment
        *
        * @covers ::collectDiscounts
        *
        * @throws NoSuchEntityException from tested method
        */
        public function collectDiscounts_withRewardPointsAndNotPaymentOnly_loadsRewardPointsUsingModel()
        {
        $currentMock = $this->getCurrentMock(
            [
                'getWebsiteId',
                'getQuoteById',
                'getCalculationAddress'
            ]
        );
        $shippingAddress = $this->getAddressMock();

        $currentMock->expects(static::once())->method('getWebsiteId')->willReturn(self::WEBSITE_ID);
        $currentMock->expects(static::once())->method('getQuoteById')->willReturn($this->quoteMock);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($this->immutableQuoteMock)
            ->willReturn($shippingAddress);
        $shippingAddress->expects(static::any())->method('getCouponCode')->willReturn(false);
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $this->immutableQuoteMock->expects(static::once())->method('getTotals')->willReturn([]);
        $this->immutableQuoteMock->expects(static::once())->method('getQuoteCurrencyCode')
            ->willReturn(self::CURRENCY_CODE);
        $this->immutableQuoteMock->expects(static::once())->method('getUseRewardPoints')->willReturn(true);

        ObjectManager::setInstance($this->objectManagerMock);
        $rewardModelMock = $this->getMockBuilder('Magento\Reward\Model\Reward')
            ->disableOriginalConstructor()
            ->setMethods(['setCustomer', 'setWebsiteId', 'loadByCustomer', 'getCurrencyAmount'])
            ->getMock();
        $this->objectManagerMock->expects(static::once())->method('create')
            ->with('Magento\Reward\Model\Reward')->willReturn($rewardModelMock);

        $this->customerSession->expects(static::once())->method('getCustomer')->willReturn($this->customerMock);
        $rewardModelMock->expects(static::once())->method('setCustomer')->with($this->customerMock)
            ->willReturnSelf();
        $rewardModelMock->expects(static::once())->method('setWebsiteId')->with(self::WEBSITE_ID)
            ->willReturnSelf();
        $rewardModelMock->expects(static::once())->method('loadByCustomer')->willReturnSelf();
        $appliedDiscount = 10; // $
        $rewardModelMock->expects(static::once())->method('getCurrencyAmount')->willReturn($appliedDiscount);

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = false;

        list($discounts, $totalAmountResult, $diffResult) = $currentMock->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $this->immutableQuoteMock
        );

        static::assertEquals($diffResult, $diff);

        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;

        static::assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        static::assertEquals('fixed_amount', $discounts[0]['type']);
        static::assertEquals('Reward Points', $discounts[0]['description']);
        static::assertEquals($expectedTotalAmount, $totalAmountResult);
        }

        /**
        * @test
        * that collectDiscounts properly handles Mirasvit Reward Points using
        * @see \Bolt\Boltpay\Helper\Discount::getMirasvitRewardsAmount to retrieve discount amount and
        * rewards/general/point_unit_name config value for discount description
        *
        * @covers ::collectDiscounts
        *
        * @throws NoSuchEntityException from tested method
        */
        public function collectDiscounts_withMirasvitRewardPoints_collectsDiscount()
        {
        $currentMock = $this->getCurrentMock(
            [
                'getWebsiteId',
                'getQuoteById',
                'getCalculationAddress'
            ]
        );
        $shippingAddress = $this->getAddressMock();

        $currentMock->expects(static::once())->method('getQuoteById')->willReturn($this->quoteMock);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($this->immutableQuoteMock)
            ->willReturn($shippingAddress);
        $shippingAddress->expects(static::any())->method('getCouponCode')->willReturn(false);
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $this->immutableQuoteMock->expects(static::once())->method('getStoreId')->willReturn(self::STORE_ID);
        $this->immutableQuoteMock->expects(static::once())->method('getTotals')->willReturn([]);
        $this->immutableQuoteMock->expects(static::once())->method('getQuoteCurrencyCode')
            ->willReturn(self::CURRENCY_CODE);
        $this->immutableQuoteMock->expects(static::once())->method('getUseRewardPoints')->willReturn(false);

        $appliedDiscount = 10; // $
        $this->discountHelper->expects(static::once())->method('getMirasvitRewardsAmount')->with($this->quoteMock)
            ->willReturn($appliedDiscount);
        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->configHelper->method('getScopeConfig')->willReturn($scopeConfigMock);
        $scopeConfigMock->expects(static::once())->method('getValue')->with(
            'rewards/general/point_unit_name',
            ScopeInterface::SCOPE_STORE,
            self::STORE_ID
        )->willReturn('Mirasvit Reward Points');

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = false;

        list($discounts, $totalAmountResult, $diffResult) = $currentMock->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $this->immutableQuoteMock
        );

        static::assertEquals($diffResult, $diff);

        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;

        static::assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        static::assertEquals('fixed_amount', $discounts[0]['type']);
        static::assertEquals('Mirasvit Reward Points', $discounts[0]['description']);
        static::assertEquals($expectedTotalAmount, $totalAmountResult);
        }

        /**
        * @test
        * that collectDiscounts properly handles Mirasvit Store Credit using
        * @see \Bolt\Boltpay\Helper\Discount::getMirasvitStoreCreditAmount
        *
        * @covers ::collectDiscounts
        *
        * @throws NoSuchEntityException from tested method
        */
        public function collectDiscounts_withMirasvitStoreCredit_collectsDiscount()
        {
        $mock = $this->getCurrentMock();
        $shippingAddress = $this->getAddressMock();
        $quote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);
        $quote->method('getBoltParentQuoteId')->willReturn(999999);
        $mock->expects(static::once())->method('getQuoteById')->willReturn($quote);
        $quote->method('getTotals')->willReturn([]);
        $mock->expects(static::once())->method('getCalculationAddress')->with($quote)->willReturn($shippingAddress);
        $shippingAddress->expects(static::any())->method('getCouponCode')->willReturn(false);
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $quote->expects(static::once())->method('getUseCustomerBalance')->willReturn(false);
        $this->discountHelper->expects(static::once())->method('isMirasvitStoreCreditAllowed')->with($quote)
            ->willReturn(true);
        $this->discountHelper->expects(static::never())->method('getAheadworksStoreCredit');
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAmastyPayForEverything');
        $this->discountHelper->expects(static::never())->method('getMageplazaGiftCardCodes');
        $this->discountHelper->expects(static::never())->method('getUnirgyGiftCertBalanceByCode');
        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;
        $appliedDiscount = 10; // $
        $this->discountHelper->expects(static::once())->method('getMirasvitStoreCreditAmount')
            ->with($quote, $paymentOnly)->willReturn($appliedDiscount);
        list($discounts, $totalAmountResult, $diffResult) = $mock->collectDiscounts($totalAmount, $diff, $paymentOnly, $quote);
        static::assertEquals($diffResult, $diff);
        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;
        static::assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        static::assertEquals($expectedTotalAmount, $totalAmountResult);
        }

        /**
        * @test
        * that collectDiscounts collects Reward Points from quote property if checkout type is payment
        *
        * @covers ::collectDiscounts
        *
        * @throws NoSuchEntityException from tested method
        */
        public function collectDiscounts_withRewardPointsAndPaymentOnly_collectsRewardPointsFromQuote()
        {
        $currentMock = $this->getCurrentMock();
        $shippingAddress = $this->getAddressMock();
        $quote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);
        $quote->method('getBoltParentQuoteId')->willReturn(999999);
        $currentMock->expects(static::once())->method('getQuoteById')->willReturn($quote);
        $quote->method('getTotals')->willReturn([]);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($quote)
            ->willReturn($shippingAddress);
        $shippingAddress->expects(static::any())->method('getCouponCode')->willReturn(false);
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $quote->expects(static::once())->method('getUseCustomerBalance')->willReturn(false);
        $this->discountHelper->expects(static::once())->method('isMirasvitStoreCreditAllowed')->with($quote)
            ->willReturn(false);
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(true);
        $this->discountHelper->expects(static::never())->method('getAmastyPayForEverything');
        $this->discountHelper->expects(static::never())->method('getMageplazaGiftCardCodes');
        $this->discountHelper->expects(static::never())->method('getUnirgyGiftCertBalanceByCode');
        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;
        $appliedDiscount = 10; // $
        $quote->expects(static::once())->method('getRewardCurrencyAmount')->willReturn($appliedDiscount);
        list($discounts, $totalAmountResult, $diffResult) = $currentMock->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $quote
        );
        static::assertEquals($diffResult, $diff);
        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;
        static::assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        static::assertEquals($expectedTotalAmount, $totalAmountResult);
        }

        /**
        * @test
        * that collectDiscounts properly handles Aheadworks Store Credit by reading amount from giftcert balance
        * using {@see \Bolt\Boltpay\Helper\Discount::getAheadworksStoreCredit}
        *
        * @covers ::collectDiscounts
        *
        * @throws NoSuchEntityException from tested method
        */
        public function collectDiscounts_withAheadworksStoreCredit_collectsAheadworksStoreCredit()
        {
        $currentMock = $this->getCurrentMock();
        $shippingAddress = $this->getAddressMock();
        /** @var Quote|MockObject $quote */
        $quote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);
        $quote->method('getBoltParentQuoteId')->willReturn(999999);
        $currentMock->expects(static::once())->method('getQuoteById')->willReturn($quote);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($quote)
            ->willReturn($shippingAddress);
        $shippingAddress->expects(static::any())->method('getCouponCode')->willReturn(false);
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $quote->expects(static::once())->method('getUseCustomerBalance')->willReturn(false);
        $this->discountHelper->expects(static::once())->method('isMirasvitStoreCreditAllowed')->with($quote)
            ->willReturn(false);
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAmastyPayForEverything');
        $this->discountHelper->expects(static::never())->method('getMageplazaGiftCardCodes');
        $this->discountHelper->expects(static::never())->method('getUnirgyGiftCertBalanceByCode');
        $appliedDiscount = 10; // $
        $quote->expects(static::any())->method('getTotals')->willReturn(
            [DiscountHelper::AHEADWORKS_STORE_CREDIT => $this->quoteAddressTotal]
        );
        $this->discountHelper->expects(static::once())->method('getAheadworksStoreCredit')
            ->with($quote->getCustomerId())->willReturn($appliedDiscount);
        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;
        list($discounts, $totalAmountResult, $diffResult) = $currentMock->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $quote
        );
        static::assertEquals($diffResult, $diff);
        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;
        static::assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        static::assertEquals($expectedTotalAmount, $totalAmountResult);
        }

        /**
        * @test
        * that collectDiscounts properly handles BSS Store Credit by reading amount from giftcert balance
        * using {@see \Bolt\Boltpay\Helper\Discount::getBssStoreCreditAmount}
        *
        * @covers ::collectDiscounts
        *
        * @throws NoSuchEntityException from tested method
        */
        public function collectDiscounts_withBssStoreCredit_collectsBssStoreCredit()
        {
        $appliedDiscount = 10; // $
        $currentMock = $this->getCurrentMock();
        $shippingAddress = $this->getAddressMock();
        $quote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);
        $quote->method('getBoltParentQuoteId')->willReturn(999999);
        $currentMock->expects(static::once())->method('getQuoteById')->willReturn($quote);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($quote)
            ->willReturn($shippingAddress);
        $shippingAddress->expects(static::any())->method('getCouponCode')->willReturn(false);
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $quote->expects(static::once())->method('getUseCustomerBalance')->willReturn(false);
        $this->discountHelper->expects(static::once())->method('isMirasvitStoreCreditAllowed')->with($quote)
            ->willReturn(false);
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAmastyPayForEverything');
        $this->discountHelper->expects(static::never())->method('getMageplazaGiftCardCodes');
        $this->discountHelper->expects(static::never())->method('getUnirgyGiftCertBalanceByCode');
        $quote->expects(static::any())->method('getTotals')
            ->willReturn([DiscountHelper::BSS_STORE_CREDIT => $this->quoteAddressTotal]);
        $this->discountHelper->expects(static::once())->method('isBssStoreCreditAllowed')->willReturn(true);
        $this->discountHelper->expects(static::once())->method('getBssStoreCreditAmount')->withAnyParameters()
            ->willReturn($appliedDiscount);
        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;
        list($discounts, $totalAmountResult, $diffResult) = $currentMock->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $quote
        );
        static::assertEquals($diffResult, $diff);
        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;
        static::assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        static::assertEquals($expectedTotalAmount, $totalAmountResult);
        }

        /**
        * @test
        * that collectDiscounts properly handles Amasty Giftcert by reading amount from giftcert balance
        * using {@see \Bolt\Boltpay\Helper\Discount::getAmastyGiftCardCodesCurrentValue} instead of quote total
        *
        * @covers ::collectDiscounts
        *
        * @throws NoSuchEntityException from tested method
        */
        public function collectDiscounts_withAmastyGiftcard_collectsAmastyGiftcard()
        {
        $currentMock = $this->getCurrentMock();
        $shippingAddress = $this->getAddressMock();
        $quote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);
        $quote->method('getBoltParentQuoteId')->willReturn(999999);
        $currentMock->expects(static::once())->method('getQuoteById')->willReturn($quote);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($quote)
            ->willReturn($shippingAddress);
        $shippingAddress->expects(static::any())->method('getCouponCode')->willReturn(false);
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $quote->expects(static::once())->method('getUseCustomerBalance')->willReturn(false);
        $this->discountHelper->expects(static::once())->method('isMirasvitStoreCreditAllowed')->with($quote)
            ->willReturn(false);
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAheadworksStoreCredit');
        $this->discountHelper->expects(static::never())->method('getMageplazaGiftCardCodes');
        $this->discountHelper->expects(static::never())->method('getUnirgyGiftCertBalanceByCode');
        $appliedDiscount = 10; // $
        $amastyGiftCode = "12345";
        $this->discountHelper->expects(static::once())->method('getAmastyPayForEverything')->willReturn(true);
        $this->discountHelper->expects(static::once())->method('getAmastyGiftCardCodesFromTotals')
            ->willReturn($amastyGiftCode);

        $this->discountHelper->expects(static::once())->method('getAmastyGiftCardCodesCurrentValue')
            ->with($amastyGiftCode)->willReturn($appliedDiscount);
        $this->quoteAddressTotal->expects(static::once())->method('getValue')->willReturn(5);
        $quote->expects(static::any())->method('getTotals')

            ->willReturn([DiscountHelper::AMASTY_GIFTCARD => $this->quoteAddressTotal]);
        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;
        list($discounts, $totalAmountResult, $diffResult) = $currentMock->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $quote
        );
        static::assertEquals($diffResult, $diff);
        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;
        static::assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        static::assertEquals($expectedTotalAmount, $totalAmountResult);
        }

        /**
        * @test
        * that collectDiscounts collects Amasty Store Credit if it exists in quote totals
        *
        * @covers ::collectDiscounts
        *
        * @throws NoSuchEntityException from tested method
        */
        public function collectDiscounts_withAmastyStoreCreditInQuoteTotals_collectsAmastyStoreCredit()
        {
        $currentMock = $this->getCurrentMock();
        $shippingAddress = $this->getAddressMock();

        $quote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);

        $quote->method('getBoltParentQuoteId')->willReturn(999999);
        $currentMock->expects(static::once())->method('getQuoteById')->willReturn($quote);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($quote)
            ->willReturn($shippingAddress);

        $shippingAddress->expects(static::any())->method('getCouponCode')->willReturn(false);
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $quote->expects(static::once())->method('getUseCustomerBalance')->willReturn(false);
        $this->discountHelper->expects(static::once())->method('isMirasvitStoreCreditAllowed')->with($quote)
            ->willReturn(false);
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAheadworksStoreCredit');
        $this->discountHelper->expects(static::never())->method('getMageplazaGiftCardCodes');
        $this->discountHelper->expects(static::never())->method('getUnirgyGiftCertBalanceByCode');
        $appliedDiscount = 10; // $
        $this->quoteAddressTotal->expects(static::once())->method('getValue')->willReturn($appliedDiscount);
        $this->quoteAddressTotal->expects(static::once())->method('getTitle')->willReturn('Store Credit');
        $quote->expects(static::any())->method('getTotals')
            ->willReturn([DiscountHelper::AMASTY_STORECREDIT => $this->quoteAddressTotal]);

        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;

        list($discounts, $totalAmountResult, $diffResult) = $currentMock->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $quote
        );
        static::assertEquals(
            ['description' => 'Store Credit', 'amount' => $appliedDiscount * 100, 'type' => 'fixed_amount'],
            $discounts[0]
        );

        static::assertEquals($diffResult, $diff);

        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;

        static::assertEquals($expectedTotalAmount, $totalAmountResult);
        }

        /**
        * @test
        * that collectDiscounts properly handles Mageplaza Giftcert by reading amount from giftcert balance
        * using {@see \Bolt\Boltpay\Helper\Discount::getMageplazaGiftCardCodesCurrentValue} instead of quote total
        *
        * @covers ::collectDiscounts
        *
        * @throws NoSuchEntityException from tested method
        */
        public function collectDiscounts_withMageplazaGiftCard_collectsMageplazaGiftCard()
        {
        $mock = $this->getCurrentMock();
        $shippingAddress = $this->getAddressMock();
        $quote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);
        $quote->method('getBoltParentQuoteId')->willReturn(999999);
        $mock->expects(static::once())->method('getQuoteById')->willReturn($quote);
        $mock->expects(static::once())->method('getCalculationAddress')->with($quote)->willReturn($shippingAddress);
        $shippingAddress->expects(static::any())->method('getCouponCode')->willReturn(false);
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $quote->expects(static::once())->method('getUseCustomerBalance')->willReturn(false);
        $this->discountHelper->expects(static::once())->method('isMirasvitStoreCreditAllowed')->with($quote)
            ->willReturn(false);
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAheadworksStoreCredit');
        $this->discountHelper->expects(static::never())->method('getUnirgyGiftCertBalanceByCode');
        $appliedDiscount = 10; // $
        $mageplazaGiftCode = "12345";
        $this->discountHelper->expects(static::once())->method('getMageplazaGiftCardCodes')
            ->willReturn($mageplazaGiftCode);
        $this->discountHelper->expects(static::once())->method('getMageplazaGiftCardCodesCurrentValue')
            ->with($mageplazaGiftCode)->willReturn($appliedDiscount);
        $this->quoteAddressTotal->expects(static::once())->method('getValue')->willReturn(5);
        $quote->expects(static::any())->method('getTotals')
            ->willReturn([DiscountHelper::MAGEPLAZA_GIFTCARD => $this->quoteAddressTotal]);
        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;
        list($discounts, $totalAmountResult, $diffResult) = $mock->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $quote
        );
        static::assertEquals($diffResult, $diff);
        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;
        static::assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        static::assertEquals($expectedTotalAmount, $totalAmountResult);
        }

        /**
        * @test
        * that collectDiscounts properly handles Unirgy Giftcert by reading amount from giftcert balance instead of total
        *
        * @covers ::collectDiscounts
        *
        * @throws NoSuchEntityException from tested method
        */
        public function collectDiscounts_withUnirgyGiftcert_collectsUnirgyGiftcert()
        {
        $mock = $this->getCurrentMock();
        $shippingAddress = $this->getAddressMock();
        $quote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);
        $quote->method('getBoltParentQuoteId')->willReturn(999999);
        $mock->expects(static::once())->method('getQuoteById')->willReturn($quote);
        $mock->expects(static::once())->method('getCalculationAddress')->with($quote)->willReturn($shippingAddress);
        $shippingAddress->expects(static::any())->method('getCouponCode')->willReturn(false);
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $quote->expects(static::once())->method('getUseCustomerBalance')->willReturn(false);
        $this->discountHelper->expects(static::once())->method('isMirasvitStoreCreditAllowed')->with($quote)
            ->willReturn(false);
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAheadworksStoreCredit');
        $appliedDiscount = 10; // $
        $unirgyGiftcertCode = "12345";
        $quote->expects(static::any())->method("getData")->with("giftcert_code")->willReturn($unirgyGiftcertCode);
        $this->discountHelper->expects(static::once())->method('getUnirgyGiftCertBalanceByCode')
            ->with($unirgyGiftcertCode)->willReturn($appliedDiscount);
        $this->quoteAddressTotal->expects(static::once())->method('getValue')->willReturn(5);
        $quote->expects(static::any())->method('getTotals')
            ->willReturn([DiscountHelper::UNIRGY_GIFT_CERT => $this->quoteAddressTotal]);
        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;
        list($discounts, $totalAmountResult, $diffResult) = $mock->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $quote
        );
        static::assertEquals($diffResult, $diff);
        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;
        static::assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        static::assertEquals($expectedTotalAmount, $totalAmountResult);
        }

        /**
        * @test
        * that collectDiscounts properly handles gift voucher discount by subtracting it from regular discount
        *
        * @covers ::collectDiscounts
        *
        * @throws NoSuchEntityException from tested method
        */
        public function collectDiscounts_withGiftVoucher_collectsGiftVoucher()
        {
        $mock = $this->getCurrentMock();
        $shippingAddress = $this->getAddressMock();
        $quote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);
        $quote->method('getBoltParentQuoteId')->willReturn(999999);
        $mock->expects(static::once())->method('getQuoteById')->willReturn($quote);
        $mock->expects(static::once())->method('getCalculationAddress')->with($quote)->willReturn($shippingAddress);
        $quote->expects(static::once())->method('getUseCustomerBalance')->willReturn(false);
        $this->discountHelper->expects(static::once())->method('isMirasvitStoreCreditAllowed')->with($quote)
            ->willReturn(false);
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAheadworksStoreCredit');
        $giftVoucherDiscount = 5; // $
        $discountAmount = 10; // $
        $giftVaucher = "12345";
        $shippingAddress->expects(static::any())->method('getCouponCode')->willReturn($giftVaucher);
        $this->quoteAddressTotal->expects(static::once())->method('getValue')->willReturn($giftVoucherDiscount);
        $this->quoteAddressTotal->expects(static::once())->method('getTitle')->willReturn("Gift Voucher");
        $shippingAddress->expects(static::once())->method('getDiscountAmount')->willReturn($discountAmount);
        $quote->expects(static::any())->method('getTotals')->willReturn(
            [DiscountHelper::GIFT_VOUCHER => $this->quoteAddressTotal]
        );
        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;
        list($discounts, $totalAmountResult, $diffResult) = $mock->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $quote
        );
        static::assertEquals($diffResult, $diff);
        $expectedGiftVoucherAmount = 100 * $giftVoucherDiscount;
        $expectedRegularDiscountAmount = 100 * ($discountAmount - $giftVoucherDiscount);
        $expectedTotalAmount = $totalAmount - $expectedRegularDiscountAmount - $expectedGiftVoucherAmount;
        static::assertEquals($expectedRegularDiscountAmount, $discounts[0]['amount']);
        static::assertEquals($expectedGiftVoucherAmount, $discounts[1]['amount']);
        static::assertEquals($expectedTotalAmount, $totalAmountResult);
        }

        /**
        * @test
        * that collectDiscounts properly handles discount contained within {@see \Bolt\Boltpay\Helper\Cart::$discountTypes}
        * which doesn't require any special handling
        *
        * @covers ::collectDiscounts
        *
        * @throws NoSuchEntityException from tested method
        */
        public function collectDiscounts_withOtherDiscount_collectsOtherDiscount()
        {
        $currentMock = $this->getCurrentMock();
        $shippingAddress = $this->getAddressMock();
        $quote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);
        $quote->method('getBoltParentQuoteId')->willReturn(999999);
        $currentMock->expects(static::once())->method('getQuoteById')->willReturn($quote);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($quote)
            ->willReturn($shippingAddress);
        $quote->expects(static::once())->method('getUseCustomerBalance')->willReturn(false);
        $this->discountHelper->expects(static::once())->method('isMirasvitStoreCreditAllowed')->with($quote)
            ->willReturn(false);
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAheadworksStoreCredit');
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $shippingAddress->expects(static::any())->method('getCouponCode')->willReturn(false);
        $appliedDiscount = 10; // $
        $this->quoteAddressTotal->expects(static::once())->method('getValue')->willReturn($appliedDiscount);
        $this->quoteAddressTotal->expects(static::once())->method('getTitle')->willReturn("Other Discount");
        $quote->expects(static::any())->method('getTotals')->willReturn(
            [DiscountHelper::GIFT_VOUCHER_AFTER_TAX => $this->quoteAddressTotal]
        );
        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;
        list($discounts, $totalAmountResult, $diffResult) = $currentMock->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $quote
        );
        static::assertEquals($diffResult, $diff);

        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;

        static::assertEquals($expectedDiscountAmount, $discounts[0]['amount']);
        static::assertEquals($expectedTotalAmount, $totalAmountResult);
        }

        /**
        * @test
        * that getCartItems converts product attribute value to string if it's a boolean
        *
        * @covers ::getCartItems
        */
        public function getCartItems_withBooleanItemOption_convertsOptionValueToString()
        {
        $color = 'Blue';
        $size = 'S';
        $insurence = true;
        $quoteItemOptions = [
            'attributes_info' => [
                ['label' => 'Size', 'value' => $size],
                ['label' => 'Color', 'value' => $color],
                ['label' => 'Insurence', 'value' => $insurence],
            ]
        ];
        $productTypeConfigurableMock = $this->getMockBuilder(Configurable::class)
            ->setMethods(['getOrderOptions'])
            ->disableOriginalConstructor()
            ->getMock();
        $productTypeConfigurableMock->method('getOrderOptions')->willReturn($quoteItemOptions);

        $this->productMock = $this->getMockBuilder(Product::class)
            ->setMethods(['getId', 'getDescription', 'getTypeInstance'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->productMock->method('getDescription')->willReturn('Product Description');
        $this->productMock->method('getTypeInstance')->willReturn($productTypeConfigurableMock);

        $quoteItemMock = $this->getQuoteItemMock();
        $this->quoteMock->method('getAllVisibleItems')->willReturn([$quoteItemMock]);
        $this->quoteMock->method('getQuoteCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $this->quoteMock->method('getTotals')->willReturnSelf();


        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('no-image');

        list($products, $totalAmount, $diff) = $this->currentMock->getCartItems(
            $this->quoteMock,
            self::STORE_ID
        );

        $resultProductProperties = $products[0]['properties'];
        static::assertCount(1, $products);
        static::assertArrayHasKey('properties', $products[0]);
        static::assertCount(3, $resultProductProperties);
        static::assertInternalType('string', $resultProductProperties[0]->value);
        static::assertInternalType('string', $resultProductProperties[1]->value);
        static::assertInternalType('string', $resultProductProperties[2]->value);
        static::assertEquals(
            [
                (object)['name' => 'Size', 'value' => 'S'],
                (object)['name' => 'Color', 'value' => 'Blue'],
                (object)['name' => 'Insurence', 'value' => 'true'],
            ],
            $resultProductProperties
        );
        static::assertEquals($size, $products[0]['size']);
        static::assertEquals($color, $products[0]['color']);
        }

    /**
     * @test
     * that getCartItems returns additional product attributes
     *
     * @covers ::getCartItems
     */
    public function getCartItems_withAdditionalAttributes_returnPropertiesAndAttributes()
    {
        $attributeName = 'test_attribute';

        $color                       = 'Blue';
        $size                        = 'S';
        $quoteItemOptions            = [
            'attributes_info' => [
                ['label' => 'Size', 'value' => 'S'],
            ]
        ];
        $productTypeConfigurableMock = $this->getMockBuilder(Configurable::class)
                                            ->setMethods(['getOrderOptions'])
                                            ->disableOriginalConstructor()
                                            ->getMock();
        $productTypeConfigurableMock->method('getOrderOptions')->willReturn($quoteItemOptions);

        $this->productMock = $this->getMockBuilder(Product::class)
                                  ->setMethods(['getId', 'getDescription', 'getTypeInstance'])
                                  ->disableOriginalConstructor()
                                  ->getMock();
        $this->productMock->method('getDescription')->willReturn('Product Description');
        $this->productMock->method('getTypeInstance')->willReturn($productTypeConfigurableMock);

        $quoteItemMock = $this->getQuoteItemMock();
        $this->quoteMock->method('getAllVisibleItems')->willReturn([$quoteItemMock]);
        $this->quoteMock->method('getQuoteCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $this->quoteMock->method('getTotals')->willReturnSelf();


        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('no-image');

        $this->configHelper->method('getProductAttributesList')->willReturn([$attributeName]);
        $productMock = $this->getMockBuilder(Product::class)
                                          ->setMethods(['getData', 'getAttributeText'])
                                          ->disableOriginalConstructor()
                                          ->getMock();
        $productMock->method('getData')->with($attributeName)->willReturn(true);
        $productMock->method('getAttributeText')->with($attributeName)->willReturn('Yes');

        $this->productRepository->method('get')->with(self::PRODUCT_SKU, false, self::STORE_ID)->willReturn($productMock);

        list($products, $totalAmount, $diff) = $this->currentMock->getCartItems(
            $this->quoteMock,
            self::STORE_ID
        );

        static::assertCount(1, $products);
        static::assertArrayHasKey('properties', $products[0]);
        $resultProductProperties = $products[0]['properties'];
        static::assertEquals(
            [
                (object)['name' => 'Size', 'value' => 'S'],
                (object)['name' => 'test_attribute', 'value' => 'Yes', 'type' => 'attribute'],
            ],
            $resultProductProperties
        );
    }

        /**
        * @test
        * that getCartItems will notify 'Item image missing' error if both attempts to retrieve image url fail
        *
        * @covers ::getCartItems
        */
        public function getCartItems_withExceptionsRetrievingProductAndImages_notifiesErrors()
        {
        $this->appEmulation->expects(static::once())->method('startEnvironmentEmulation')
            ->with(self::STORE_ID, \Magento\Framework\App\Area::AREA_FRONTEND, true);
        $quoteItem = $this->createPartialMock(
            Item::class,
            [
                'getCalculationPrice',
                'getQty',
                'getProduct',
                'getProductId',
                'getName',
                'getSku',
                'getIsVirtual',
            ]
        );
        $productMock = $this->createMock(Product::class);
        $quoteItem->method('getName')->willReturn('Test Product');
        $quoteItem->method('getSku')->willReturn(self::PRODUCT_SKU);
        $quoteItem->method('getQty')->willReturn(1);
        $quoteItem->method('getCalculationPrice')->willReturn(self::PRODUCT_PRICE);
        $quoteItem->method('getIsVirtual')->willReturn(false);
        $quoteItem->method('getProductId')->willReturn(self::PRODUCT_ID);
        $quoteItem->method('getProduct')->willReturn($productMock);
        $productMock->expects(static::once())->method('getTypeInstance')->willReturnSelf();

        $this->imageHelper->method('init')
            ->withConsecutive([$productMock, 'product_small_image'], [$productMock, 'product_base_image'])
            ->willThrowException(new Exception());

        $this->bugsnag->expects(static::once())->method('registerCallback')->with(
            static::callback(
                function ($callback) {
                    $reportMock = $this->createMock(Report::class);
                    $reportMock->expects(static::once())->method('setMetaData')->with(
                        [
                            'ITEM' => [
                                'reference'    => self::PRODUCT_ID,
                                'name'         => 'Test Product',
                                'total_amount' => 10000,
                                'unit_price'   => 10000,
                                'quantity'     => 1.0,
                                'sku'          => self::PRODUCT_SKU,
                                'type'         => 'physical',
                                'description'  => '',
                            ]
                        ]
                    );
                    $callback($reportMock);
                    return true;
                }
            )
        );
        $this->bugsnag->expects(static::once())->method('notifyError')->withConsecutive(
            ['Item image missing', 'SKU: ' . self::PRODUCT_SKU]
        );
        $this->appEmulation->expects(static::once())->method('stopEnvironmentEmulation');

        $this->quoteMock->method('getAllVisibleItems')->willReturn([$quoteItem]);
        $this->quoteMock->method('getQuoteCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $this->quoteMock->method('getTotals')->willReturnSelf();

        list($products, $totalAmount, $diff) = $this->currentMock->getCartItems(
            $this->quoteMock,
            self::STORE_ID
        );
        static::assertEquals(
            [
                [
                    'reference'    => 20102,
                    'name'         => 'Test Product',
                    'total_amount' => 10000,
                    'unit_price'   => 10000,
                    'quantity'     => 1.0,
                    'sku'          => self::PRODUCT_SKU,
                    'type'         => 'physical',
                    'description'  => '',
                ],
            ],
            $products
        );
        static::assertEquals(10000, $totalAmount);
        static::assertEquals(0, $diff);
        }

    /**
     * @test
     * that getCartItems will notify 'Item image missing' error if both attempts to retrieve image url fail
     *
     * @covers ::getCartItems
     */
    public function getCartItems_withFeatureSwitchHandleVirtualProductsAsPhysical_returnPhysicalCart()
    {
        $this->deciderHelper->expects(self::once())->method('handleVirtualProductsAsPhysical')->willReturn(true);
        $quoteItem = $this->createPartialMock(
            Item::class,
            [
                'getCalculationPrice',
                'getQty',
                'getProduct',
                'getProductId',
                'getName',
                'getSku',
                'getIsVirtual',
            ]
        );
        $productMock = $this->createMock(Product::class);
        $quoteItem->method('getName')->willReturn('Test Product');
        $quoteItem->method('getSku')->willReturn(self::PRODUCT_SKU);
        $quoteItem->method('getQty')->willReturn(1);
        $quoteItem->method('getCalculationPrice')->willReturn(self::PRODUCT_PRICE);
        $quoteItem->method('getIsVirtual')->willReturn(true);
        $quoteItem->method('getProductId')->willReturn(self::PRODUCT_ID);
        $quoteItem->method('getProduct')->willReturn($productMock);
        $productMock->expects(static::once())->method('getTypeInstance')->willReturnSelf();

        $this->imageHelper->method('init')
        ->withConsecutive([$productMock, 'product_small_image'], [$productMock, 'product_base_image'])
        ->willThrowException(new Exception());

        $this->quoteMock->method('getAllVisibleItems')->willReturn([$quoteItem]);
        $this->quoteMock->method('getQuoteCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $this->quoteMock->method('getTotals')->willReturnSelf();

        list($products, $totalAmount, $diff) = $this->currentMock->getCartItems(
            $this->quoteMock,
            self::STORE_ID
        );
        static::assertEquals(
            [
                [
                    'reference'    => 20102,
                    'name'         => 'Test Product',
                    'total_amount' => 10000,
                    'unit_price'   => 10000,
                    'quantity'     => 1.0,
                    'sku'          => self::PRODUCT_SKU,
                    'type'         => 'physical',
                    'description'  => '',
                ],
            ],
            $products
        );
    }



      /**
       * @test
       *
       * @covers ::getCartItems
       */
      public function getCartItems_withGiftWrapping()
      {
          $this->appEmulation->expects(static::once())->method('startEnvironmentEmulation')
              ->with(self::STORE_ID, \Magento\Framework\App\Area::AREA_FRONTEND, true);
          $quoteItem = $this->createPartialMock(
              Item::class,
              [
                  'getCalculationPrice',
                  'getQty',
                  'getProduct',
                  'getProductId',
                  'getName',
                  'getSku',
                  'getIsVirtual',
              ]
          );
          $productMock = $this->createMock(Product::class);
          $quoteItem->method('getName')->willReturn('Test Product');
          $quoteItem->method('getSku')->willReturn(self::PRODUCT_SKU);
          $quoteItem->method('getQty')->willReturn(1);
          $quoteItem->method('getCalculationPrice')->willReturn(self::PRODUCT_PRICE);
          $quoteItem->method('getIsVirtual')->willReturn(false);
          $quoteItem->method('getProductId')->willReturn(self::PRODUCT_ID);
          $quoteItem->method('getProduct')->willReturn($productMock);
          $productMock->expects(static::once())->method('getTypeInstance')->willReturnSelf();

          $this->imageHelper->method('init')
              ->withConsecutive([$productMock, 'product_small_image'], [$productMock, 'product_base_image'])
              ->willThrowException(new Exception());

          $this->appEmulation->expects(static::once())->method('stopEnvironmentEmulation');

          $this->quoteMock->method('getAllVisibleItems')->willReturn([$quoteItem]);
          $this->quoteMock->method('getQuoteCurrencyCode')->willReturn(self::CURRENCY_CODE);

          $this->giftwrapping = $this->getMockBuilder('\Magento\GiftWrapping\Model\Total\Quote\Giftwrapping')
              ->disableOriginalConstructor()
              ->setMethods(['getGwId','getGwItemsPrice','getGwCardPrice','getGwPrice','getText','getTitle','getCode'])
              ->getMock();

          $this->giftwrapping->method('getGwId')->willReturn(1);
          $this->giftwrapping->method('getGwItemsPrice')->willReturn('10');
          $this->giftwrapping->method('getGwCardPrice')->willReturn('0');
          $this->giftwrapping->method('getGwPrice')->willReturn('5');
          $this->giftwrapping->method('getTitle')->willReturnSelf();
          $this->giftwrapping->method('getText')->willReturn('Gift Wrapping');
          $this->giftwrapping->method('getCode')->willReturn('gift_id');
          $this->quoteMock->method('getTotals')->willReturn(['giftwrapping' => $this->giftwrapping]);

          list($products, $totalAmount, $diff) = $this->currentMock->getCartItems(
              $this->quoteMock,
              self::STORE_ID
          );
          static::assertEquals(
              [
                  [
                      'reference'    => 20102,
                      'name'         => 'Test Product',
                      'total_amount' => 10000,
                      'unit_price'   => 10000,
                      'quantity'     => 1.0,
                      'sku'          => self::PRODUCT_SKU,
                      'type'         => 'physical',
                      'description'  => '',
                  ],
                  [
                      'reference' => 1,
                      'name' => 'Gift Wrapping',
                      'total_amount' => 1500,
                      'unit_price' => 1500,
                      'quantity' => 1,
                      'sku' => 'gift_id',
                      'type' => 'physical',
                  ]
              ],
              $products
          );
          static::assertEquals(11500, $totalAmount);
          static::assertEquals(0, $diff);
      }

        /**
         * @test
         * @dataProvider dataProvider_getProductToGetImageForQuoteItem_withConfigurableItem
         *
         * @covers ::getCartItems
         *
         * @param $imageConfig
         * @param $childThumbnail
         * @param $expectedProductName
         */
        public function getProductToGetImageForQuoteItem_withConfigurableItem($imageConfig, $childThumbnail, $expectedProductName)
        {
            $quoteItem = $this->createPartialMock(
                Item::class,
                [
                    'getProduct',
                    'getProductType',
                    'getOptionByCode'
                ]
            );

            $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);

            $scopeConfigMock->expects(static::once())->method('getValue')->with(
                'checkout/cart/configurable_product_image',
                ScopeInterface::SCOPE_STORE
            )->willReturn($imageConfig);
            $this->configHelper->method('getScopeConfig')->willReturn($scopeConfigMock);

            $parentProductMock = $this->createMock(Product::class);
            $parentProductMock->method('getName')->willReturn('Parent Product Name');
            $quoteItem->method('getProduct')->willReturn($parentProductMock);
            $quoteItem->method('getProductType')->willReturn(\Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE);


            $childProductMock = $this->createPartialMock(Product::class, ['getName','getThumbnail']);
            $childProductMock->method('getName')->willReturn('Child Product Name');
            $childProductMock->method('getThumbnail')->willReturn($childThumbnail);
            $quoteItemOption = $this->createPartialMock(\Magento\Quote\Model\Quote\Item\Option::class, ['getProduct']);
            $quoteItemOption->method('getProduct')->willReturn($childProductMock);
            $quoteItem->method('getOptionByCode')->with('simple_product')->willReturn($quoteItemOption);


            self::assertEquals($expectedProductName, $this->currentMock->getProductToGetImageForQuoteItem($quoteItem)->getName());
        }

        public function dataProvider_getProductToGetImageForQuoteItem_withConfigurableItem() {
            return [
                [ThumbnailSource::OPTION_USE_OWN_IMAGE, 'Child Image URL', 'Child Product Name'],
                [ThumbnailSource::OPTION_USE_OWN_IMAGE, null, 'Parent Product Name'],
                [ThumbnailSource::OPTION_USE_PARENT_IMAGE, 'Child Image URL', 'Parent Product Name']
            ];
        }

        /**
         * @test
         * @dataProvider dataProvider_getProductToGetImageForQuoteItem_withGroupedItem
         *
         * @param $imageConfig
         * @param $childThumbnail
         * @param $expectedProductName
         */
        public function getProductToGetImageForQuoteItem_withGroupedItem($imageConfig, $childThumbnail, $expectedProductName)
        {
            $quoteItem = $this->createPartialMock(
                Item::class,
                [
                    'getProduct',
                    'getProductType',
                    'getOptionByCode'
                ]
            );

            $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);

            $scopeConfigMock->expects(static::once())->method('getValue')->with(
                'checkout/cart/grouped_product_image',
                ScopeInterface::SCOPE_STORE
            )->willReturn($imageConfig);
            $this->configHelper->method('getScopeConfig')->willReturn($scopeConfigMock);

            $productMock = $this->createPartialMock(Product::class, ['getName','getThumbnail']);
            $productMock->method('getName')->willReturn('Child Product Name');
            $productMock->method('getThumbnail')->willReturn($childThumbnail);
            $quoteItem->method('getProduct')->willReturn($productMock);
            $quoteItem->method('getProductType')->willReturn(\Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE);


            $groupedProductMock = $this->createPartialMock(Product::class, ['getName','getThumbnail']);
            $groupedProductMock->method('getName')->willReturn('Grouped Product Name');

            $quoteItemOption = $this->createPartialMock(\Magento\Quote\Model\Quote\Item\Option::class, ['getProduct']);
            $quoteItemOption->method('getProduct')->willReturn($groupedProductMock);
            $quoteItem->method('getOptionByCode')->with('product_type')->willReturn($quoteItemOption);


            self::assertEquals($expectedProductName, $this->currentMock->getProductToGetImageForQuoteItem($quoteItem)->getName());
        }

        public function dataProvider_getProductToGetImageForQuoteItem_withGroupedItem() {
            return [
                [ThumbnailSource::OPTION_USE_OWN_IMAGE, 'Child Image URL', 'Child Product Name'],
                [ThumbnailSource::OPTION_USE_OWN_IMAGE, null, 'Grouped Product Name'],
                [ThumbnailSource::OPTION_USE_PARENT_IMAGE, 'Child Image URL', 'Grouped Product Name']
            ];
        }

      /**
       * @return MockObject
       */
      private function getQuoteItemMock()
      {
        $quoteItem = $this->getMockBuilder(Item::class)
            ->setMethods(
                [
                    'getSku',
                    'getQty',
                    'getCalculationPrice',
                    'getName',
                    'getIsVirtual',
                    'getProductId',
                    'getProduct'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $quoteItem->method('getName')->willReturn('Test Product');
        $quoteItem->method('getSku')->willReturn(self::PRODUCT_SKU);
        $quoteItem->method('getQty')->willReturn(1);
        $quoteItem->method('getCalculationPrice')->willReturn(self::PRODUCT_PRICE);
        $quoteItem->method('getIsVirtual')->willReturn(false);
        $quoteItem->method('getProductId')->willReturn(self::PRODUCT_ID);
        $quoteItem->method('getProduct')->willReturn($this->productMock);

        return $quoteItem;
        }

        /**
        * Setup method for tests covering {@see \Bolt\Boltpay\Helper\Cart::createCartByRequest}
        *
        * @return array containing request data, payload, expected cart data and current mock
        */
        private function createCartByRequestSetUp()
        {
        $request = [
            'type'     => 'cart.create',
            'items'    =>
                [
                    [
                        'reference'    => CartTest::PRODUCT_ID,
                        'name'         => 'Product name',
                        'description'  => null,
                        'options'      => json_encode(['storeId' => CartTest::STORE_ID]),
                        'total_amount' => CartTest::PRODUCT_PRICE,
                        'unit_price'   => CartTest::PRODUCT_PRICE,
                        'tax_amount'   => 0,
                        'quantity'     => 1,
                        'uom'          => null,
                        'upc'          => null,
                        'sku'          => null,
                        'isbn'         => null,
                        'brand'        => null,
                        'manufacturer' => null,
                        'category'     => null,
                        'tags'         => null,
                        'properties'   => null,
                        'color'        => null,
                        'size'         => null,
                        'weight'       => null,
                        'weight_unit'  => null,
                        'image_url'    => null,
                        'details_url'  => null,
                        'tax_code'     => null,
                        'type'         => 'unknown'
                    ]
                ],
            'currency' => self::CURRENCY_CODE,
            'metadata' => null,
        ];
        $payload = ['user_id' => 1, 'timestamp' => time()];
        $this->hookHelper->method('verifySignature')->willReturn(true);

        $expectedCartData = [
            'order_reference' => self::IMMUTABLE_QUOTE_ID,
            'display_id'      => self::ORDER_INCREMENT_ID . ' / ' . self::IMMUTABLE_QUOTE_ID,
            'currency'        => self::CURRENCY_CODE,
            'items'           => [
                [
                    'reference'    => self::PRODUCT_ID,
                    'name'         => 'Affirm Water Bottle ',
                    'total_amount' => self::PRODUCT_PRICE,
                    'unit_price'   => self::PRODUCT_PRICE,
                    'quantity'     => 1,
                    'sku'          => self::PRODUCT_SKU,
                    'type'         => 'physical',
                    'description'  => 'Product description',
                ],
            ],
            'discounts'       => [],
            'total_amount'    => self::PRODUCT_PRICE,
            'tax_amount'      => 0,
        ];

        $this->quoteManagement->expects(static::once())->method('createEmptyCart')
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
        $this->quoteFactory->method('create')->withAnyParameters()->willReturnSelf();
        $this->quoteFactory->method('load')->with(self::IMMUTABLE_QUOTE_ID)->willReturn($this->quoteMock);

        $this->productRepository->expects(static::once())->method('getById')->with(self::PRODUCT_ID)
            ->willReturn($this->productMock);

        $currentMock = $this->getCurrentMock(['getCartData']);
        return [$request, $payload, $expectedCartData, $currentMock];
        }

        /**
        * @test
        * that createCartByRequest creates cart with simple product and returns expected cart data using
        * @see \Bolt\Boltpay\Helper\Cart::getCartData
        *
        * @covers ::createCartByRequest
        *
        * @throws Exception from tested method
        */
        public function createCartByRequest_withGuestUserAndSimpleProduct_returnsExpectedCartData()
        {
        $request = [
            'type'     => 'cart.create',
            'items'    =>
                [
                    [
                        'reference'    => CartTest::PRODUCT_ID,
                        'name'         => 'Product name',
                        'description'  => null,
                        'options'      => json_encode(['storeId' => CartTest::STORE_ID]),
                        'total_amount' => CartTest::PRODUCT_PRICE,
                        'unit_price'   => CartTest::PRODUCT_PRICE,
                        'tax_amount'   => 0,
                        'quantity'     => 1,
                        'uom'          => null,
                        'upc'          => null,
                        'sku'          => null,
                        'isbn'         => null,
                        'brand'        => null,
                        'manufacturer' => null,
                        'category'     => null,
                        'tags'         => null,
                        'properties'   => null,
                        'color'        => null,
                        'size'         => null,
                        'weight'       => null,
                        'weight_unit'  => null,
                        'image_url'    => null,
                        'details_url'  => null,
                        'tax_code'     => null,
                        'type'         => 'unknown'
                    ]
                ],
            'currency' => self::CURRENCY_CODE,
            'metadata' => null,
        ];

        $expectedCartData = [
            'order_reference' => self::IMMUTABLE_QUOTE_ID,
            'display_id'      => self::ORDER_INCREMENT_ID . ' / ' . self::IMMUTABLE_QUOTE_ID,
            'currency'        => self::CURRENCY_CODE,
            'items'           => [
                [
                    'reference'    => self::PRODUCT_ID,
                    'name'         => 'Affirm Water Bottle ',
                    'total_amount' => self::PRODUCT_PRICE,
                    'unit_price'   => self::PRODUCT_PRICE,
                    'quantity'     => 1,
                    'sku'          => self::PRODUCT_SKU,
                    'type'         => 'physical',
                    'description'  => 'Product description',
                ],
            ],
            'discounts'       => [],
            'total_amount'    => self::PRODUCT_PRICE,
            'tax_amount'      => 0,
        ];

        $cartMock = $this->getCurrentMock(['getCartData']);
        $cartMock->expects(static::once())->method('getCartData')->with(false, '', $this->quoteMock)
            ->willReturn($expectedCartData);
        $this->quoteManagement->expects(static::once())->method('createEmptyCart')
            ->willReturn(self::QUOTE_ID);
        $this->quoteFactory->method('create')->withAnyParameters()->willReturnSelf();
        $this->quoteFactory->method('load')->with(self::QUOTE_ID)->willReturn($this->quoteMock);
        $this->productRepository->expects(static::once())->method('getById')->with(self::PRODUCT_ID)
            ->willReturn($this->productMock);
        $this->quoteMock->expects(static::once())->method('reserveOrderId');
        $this->quoteMock->expects(static::once())->method('setIsActive')->with(false);

        static::assertEquals($expectedCartData, $cartMock->createCartByRequest($request));
        }

        /**
        * @test
        * that createCartByRequest creates cart with configurable product and returns expected cart data using
        * @see \Bolt\Boltpay\Helper\Cart::getCartData
        *
        * @covers ::createCartByRequest
        *
        * @throws Exception from tested method
        */
        public function createCartByRequest_withGuestUserAndConfigurableProduct_returnsExpectedCartData()
        {
        $request = [
            'type'     => 'cart.create',
            'items'    => [
                [
                    'reference'    => CartTest::PRODUCT_ID,
                    'name'         => 'Product name',
                    'description'  => null,
                    'options'      => json_encode(['storeId' => CartTest::STORE_ID]),
                    'total_amount' => CartTest::PRODUCT_PRICE,
                    'unit_price'   => CartTest::PRODUCT_PRICE,
                    'tax_amount'   => 0,
                    'quantity'     => 1,
                    'type'         => 'unknown'
                ]
            ],
            'currency' => self::CURRENCY_CODE,
            'metadata' => null,
        ];
        $request['items'][0]['options'] = json_encode(
            [
                "product"                      => self::PRODUCT_ID,
                "selected_configurable_option" => "",
                "item"                         => self::PRODUCT_ID,
                "related_product"              => "",
                "form_key"                     => "8xaF8eKXVaiRVM53",
                "super_attribute"              => self::SUPER_ATTRIBUTE,
                "qty"                          => "1",
                'storeId'                      => self::STORE_ID
            ],
            JSON_FORCE_OBJECT
        );

        $expectedCartData = [
            'order_reference' => self::IMMUTABLE_QUOTE_ID,
            'display_id'      => self::ORDER_INCREMENT_ID . ' / ' . self::IMMUTABLE_QUOTE_ID,
            'currency'        => self::CURRENCY_CODE,
            'items'           => [
                [
                    'reference'    => self::PRODUCT_ID,
                    'name'         => 'Affirm Water Bottle ',
                    'total_amount' => self::PRODUCT_PRICE,
                    'unit_price'   => self::PRODUCT_PRICE,
                    'quantity'     => 1,
                    'sku'          => self::PRODUCT_SKU,
                    'type'         => 'physical',
                    'description'  => 'Product description',
                ],
            ],
            'discounts'       => [],
            'total_amount'    => self::PRODUCT_PRICE,
            'tax_amount'      => 0,
        ];

        $cartMock = $this->getCurrentMock(['getCartData']);
        $cartMock->expects(static::once())->method('getCartData')->with(false, '', $this->quoteMock)
            ->willReturn($expectedCartData);
        $this->quoteManagement->expects(static::once())->method('createEmptyCart')
            ->willReturn(self::QUOTE_ID);
        $this->quoteFactory->method('create')->withAnyParameters()->willReturnSelf();
        $this->quoteFactory->method('load')->with(self::QUOTE_ID)->willReturn($this->quoteMock);
        $this->productRepository->expects(static::once())->method('getById')->with(self::PRODUCT_ID)
            ->willReturn($this->productMock);
        $this->quoteMock->expects(static::once())->method('reserveOrderId');
        $this->quoteMock->expects(static::once())->method('setIsActive')->with(false);

        static::assertEquals($expectedCartData, $cartMock->createCartByRequest($request));
        }

        /**
        * @test
        * that createCartByRequest assigns customer to quote by calling
        * @see \Bolt\Boltpay\Helper\Cart::assignQuoteCustomerByEncryptedUserId
        *
        * @covers ::createCartByRequest
        *
        * @throws Exception from tested method
        */
        public function createCartByRequest_withEncryptedUserIdInRequest_assignsCustomerToQuote()
        {
        list($request, $payload, $expectedCartData, $currentMock) = $this->createCartByRequestSetUp();
        $this->quoteMock->expects(static::once())->method('reserveOrderId');
        $this->quoteMock->expects(static::once())->method('setIsActive')->with(false);
        $currentMock->expects(static::once())->method('getCartData')->with(false, '', $this->quoteMock)
            ->willReturn($expectedCartData);

        $request['metadata']['encrypted_user_id'] = json_encode($payload + ['signature' => 'correct_signature']);

        $customer = $this->createMock(CustomerInterface::class);
        $this->customerRepository->method('getById')->willReturn($customer);
        $this->quoteMock->expects(static::once())->method('assignCustomer')->with($customer);

        static::assertEquals($expectedCartData, $currentMock->createCartByRequest($request));
        }

        /**
        * @test
        * that createCartByRequest throws BoltException with if a stock exception occurs when adding product to cart
        * @see \Bolt\Boltpay\Model\ErrorResponse::ERR_PPC_OUT_OF_STOCK used as exception code
        *
        * @covers ::createCartByRequest
        *
        * @throws Exception from tested method
        */
        public function createCartByRequest_withOutOfStockException_throwsBoltExceptionWithOutOfStockCode()
        {
        list($request, $payload, $expectedCartData, $currentMock) = $this->createCartByRequestSetUp();

        $this->quoteMock->expects(static::once())->method('addProduct')
            ->with($this->productMock, new DataObject(['qty' => 1]))
            ->willThrowException(new Exception('Product that you are trying to add is not available.'));

        $this->expectException(BoltException::class);
        $this->expectExceptionCode(BoltErrorResponse::ERR_PPC_OUT_OF_STOCK);
        $this->expectExceptionMessage('Product that you are trying to add is not available.');

        static::assertEquals($expectedCartData, $currentMock->createCartByRequest($request));
        }

        /**
        * @test
        * that createCartByRequest throws BoltException with if a non-stock exception occurs when adding product to cart
        * @see \Bolt\Boltpay\Model\ErrorResponse::ERR_PPC_INVALID_QUANTITY used as exception code
        *
        * @covers ::createCartByRequest
        *
        * @throws Exception from tested method
        */
        public function createCartByRequest_withExceptionWhenAddingProductToCart_throwsBoltException()
        {
        list($request, $payload, $expectedCartData, $currentMock) = $this->createCartByRequestSetUp();

        $this->quoteMock->expects(static::once())->method('addProduct')
            ->with($this->productMock, new DataObject(['qty' => 1]))
            ->willThrowException(new Exception('Product unavailable.'));

        $this->expectException(BoltException::class);
        $this->expectExceptionCode(BoltErrorResponse::ERR_PPC_INVALID_QUANTITY);
        $this->expectExceptionMessage('The requested qty is not available');

        static::assertEquals($expectedCartData, $currentMock->createCartByRequest($request));
        }

        /**
        * @test
        * that getHints returns virtual_terminal_mode set to true when provided checkout type is admin
        *
        * @covers ::getHints
        *
        * @throws NoSuchEntityException from tested method
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
            Customer::class,
            [
                'getId',
                'getEmail',
                'getDefaultBillingAddress',
                'getDefaultShippingAddress'
            ]
        );
        $this->deciderHelper->expects(self::once())->method('ifShouldDisablePrefillAddressForLoggedInCustomer')->willReturn(false);
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
        * @dataProvider provider_getHints_withNonVirtualQuoteAndCustomerLoggedIn_willReturnCustomerShippingAddressHints
        * @param bool $ifShouldDisablePrefillAddressForLoggedInCustomer
        * @throws NoSuchEntityException from tested method
        */
        public function getHints_withNonVirtualQuoteAndCustomerLoggedIn_willReturnCustomerShippingAddressHints($ifShouldDisablePrefillAddressForLoggedInCustomer)
        {
        $customerMock = $this->createPartialMock(
            Customer::class,
            [
                'getId',
                'getEmail',
                'getDefaultBillingAddress',
                'getDefaultShippingAddress'
            ]
        );
        $this->deciderHelper->expects(self::any())->method('ifShouldDisablePrefillAddressForLoggedInCustomer')->willReturn($ifShouldDisablePrefillAddressForLoggedInCustomer);
        $customerMock->expects(self::atLeastOnce())->method('getId')->willReturn(self::CUSTOMER_ID);
        $customerMock->expects(self::atLeastOnce())->method('getEmail')->willReturn(self::EMAIL_ADDRESS);
        $this->customerSession->expects(static::once())->method('isLoggedIn')->willReturn(true);
        $this->customerSession->expects(static::atLeastOnce())->method('getCustomer')->willReturn($customerMock);
        $requestMock = $this->createMock(Request::class);
        $responseMock = $this->createPartialMock(Response::class, ['getResponse']);
        $requestDataMock = $this->createPartialMock(DataObject::class, ['setApiData', 'setDynamicApiUrl', 'setApiKey']);
        if (!$ifShouldDisablePrefillAddressForLoggedInCustomer) {
            $signRequest = ['merchant_user_id' => self::CUSTOMER_ID];

            $this->configHelper->expects(static::once())->method('getApiKey')->willReturn(self::API_KEY);

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
        }

        $shippingAddressMock = $this->createPartialMock(
            Address::class,
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
            ]
        );
        $shippingAddressMock->expects(static::once())->method('getFirstname')->willReturn('IntegrationBolt');
        $shippingAddressMock->expects(static::once())->method('getLastname')->willReturn('BoltTest');
        $shippingAddressMock->expects(static::once())->method('getEmail')->willReturn(self::EMAIL_ADDRESS);
        $shippingAddressMock->expects(static::once())->method('getTelephone')->willReturn('132 231 1234');
        $shippingAddressMock->expects(static::exactly(2))->method('getStreetLine')->willReturnOnConsecutiveCalls(
            '228 7th Avenue',
            '228 7th Avenue1'
        );
        $shippingAddressMock->expects(static::once())->method('getCity')->willReturn('New York');
        $shippingAddressMock->expects(static::once())->method('getRegion')->willReturn('New York');
        $shippingAddressMock->expects(static::once())->method('getPostcode')->willReturn('10011');
        $shippingAddressMock->expects(static::once())->method('getCountryId')->willReturn('1111');
        $customerMock->expects(static::once())->method('getDefaultShippingAddress')->willReturn($shippingAddressMock);
        $hints = $this->getCurrentMock()->getHints(null, 'product');

        static::assertEquals(
            (object) [
                'firstName'    => 'IntegrationBolt',
                'lastName'     => 'BoltTest',
                'email'        => self::EMAIL_ADDRESS,
                'phone'        => '132 231 1234',
                'addressLine1' => '228 7th Avenue',
                'addressLine2' => '228 7th Avenue1',
                'city'         => 'New York',
                'state'        => 'New York',
                'zip'          => '10011',
                'country'      => '1111',
            ],
            $hints['prefill']
        );


        if (!$ifShouldDisablePrefillAddressForLoggedInCustomer) {
            static::assertEquals($signedMerchantUserId, $hints['signed_merchant_user_id']);
        }
        $encryptedUserId = json_decode($hints['metadata']['encrypted_user_id'], true);
        self::assertEquals(self::CUSTOMER_ID, $encryptedUserId['user_id']);
        }

        public function provider_getHints_withNonVirtualQuoteAndCustomerLoggedIn_willReturnCustomerShippingAddressHints(){
            return [
                [true],
                [false],
            ];
        }

        /**
        * @test
        * that getHints will return hints from customer default billing address when quote is virtual
        *
        * @covers ::getHints
        *
        * @throws NoSuchEntityException from tested method
        */
        public function getHints_withVirtualQuoteAndCustomerLoggedIn_willReturnCustomerBillingAddressHints()
        {
        $customerMock = $this->createPartialMock(
            Customer::class,
            [
                'getId',
                'getEmail',
                'getDefaultBillingAddress',
                'getDefaultShippingAddress'
            ]
        );
        $this->deciderHelper->expects(self::once())->method('ifShouldDisablePrefillAddressForLoggedInCustomer')->willReturn(false);
        $customerMock->expects(self::atLeastOnce())->method('getId')->willReturn(self::CUSTOMER_ID);
        $customerMock->expects(self::atLeastOnce())->method('getEmail')->willReturn(self::EMAIL_ADDRESS);
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
        $billingAddressMock = $this->createPartialMock(
            Address::class,
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
            ]
        );
        $billingAddressMock->expects(static::once())->method('getFirstname')->willReturn('IntegrationBolt');
        $billingAddressMock->expects(static::once())->method('getLastname')->willReturn('BoltTest');
        $billingAddressMock->expects(static::once())->method('getEmail')->willReturn(self::EMAIL_ADDRESS);
        $billingAddressMock->expects(static::once())->method('getTelephone')->willReturn('132 231 1234');
        $billingAddressMock->expects(static::exactly(2))->method('getStreetLine')
            ->willReturnOnConsecutiveCalls('228 7th Avenue', null);
        $billingAddressMock->expects(static::once())->method('getCity')->willReturn('New York');
        $billingAddressMock->expects(static::once())->method('getRegion')->willReturn('New York');
        $billingAddressMock->expects(static::once())->method('getPostcode')->willReturn('10011');
        $billingAddressMock->expects(static::once())->method('getCountryId')->willReturn('1111');
        $customerMock->expects(static::once())->method('getDefaultBillingAddress')->willReturn($billingAddressMock);
        $this->checkoutSession->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->method('isVirtual')->willReturn(true);
        $hints = $this->getCurrentMock()->getHints(null, 'multipage');
        static::assertEquals(
            (object)
            [
                'firstName'    => 'IntegrationBolt',
                'lastName'     => 'BoltTest',
                'email'        => self::EMAIL_ADDRESS,
                'phone'        => '132 231 1234',
                'addressLine1' => '228 7th Avenue',
                'city'         => 'New York',
                'state'        => 'New York',
                'zip'          => '10011',
                'country'      => '1111',
            ],
            $hints['prefill']
        );
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
        $currentMock = $this->getCurrentMock(['getQuoteById']);
        $currentMock->expects(static::once())->method('getQuoteById')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('isVirtual')->willReturn(true);
        $this->quoteMock->expects(static::once())->method('getBillingAddress')->willReturn($this->getAddressMock());
        $hints = $currentMock->getHints(self::IMMUTABLE_QUOTE_ID, 'multipage');
        static::assertEquals(
            [
                'prefill' => (object)[
                    'firstName'    => 'IntegrationBolt',
                    'lastName'     => 'BoltTest',
                    'email'        => self::EMAIL_ADDRESS,
                    'phone'        => '132 231 1234',
                    'addressLine1' => '228 7th Avenue',
                    'addressLine2' => '228 7th Avenue 2',
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
        $this->quoteMock->expects(static::once())->method('getShippingAddress')->willReturn($this->getAddressMock());
        $hints = $currentMock->getHints(self::IMMUTABLE_QUOTE_ID, 'multipage');
        static::assertEquals(
            [
                'prefill' => (object)[
                    'firstName'    => 'IntegrationBolt',
                    'lastName'     => 'BoltTest',
                    'email'        => self::EMAIL_ADDRESS,
                    'phone'        => '132 231 1234',
                    'addressLine1' => '228 7th Avenue',
                    'addressLine2' => '228 7th Avenue 2',
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
        $shippingAddress = $this->getMockBuilder(Quote\Address::class)
            ->setMethods(['getStreetLine'])
            ->disableOriginalConstructor()
            ->getMock();
        $shippingAddress->method('getStreetLine')
            ->willReturn('tbd');
        $quoteMock->expects(static::once())->method('getShippingAddress')->willReturn($shippingAddress);
        $hints = $this->getCurrentMock()->getHints();
        static::assertEquals((object)[], $hints['prefill']);
        }

        /**
        * @test
        * that assignQuoteCustomerByEncryptedUserId throws {@see \Magento\Framework\Webapi\Exception}
        * if provided encrypted user id is incomplete
        *
        * @covers ::assignQuoteCustomerByEncryptedUserId
        *
        * @dataProvider assignQuoteCustomerByEncryptedUserId_withInvalidEncryptedUserIdProvider
        *
        * @param string|null $encryptedUserId
        *
        * @throws ReflectionException if assignQuoteCustomerByEncryptedUserId method doesn't exist
        */
        public function assignQuoteCustomerByEncryptedUserId_withInvalidEncryptedUserId_throwsException($encryptedUserId)
        {
        $this->expectExceptionMessage("Incorrect encrypted_user_id");
        $this->expectExceptionCode(6306);
        $this->expectException(\Magento\Framework\Webapi\Exception::class);
        TestHelper::invokeMethod(
            $this->currentMock,
            'assignQuoteCustomerByEncryptedUserId',
            [$this->quoteMock, $encryptedUserId]
        );
        }

        /**
        * Data provider for {@see assignQuoteCustomerByEncryptedUserId_withInvalidEncryptedUserId_throwsException}
        *
        * @return array containing incomplete encrypted user ids
        */
        public function assignQuoteCustomerByEncryptedUserId_withInvalidEncryptedUserIdProvider()
        {
        return [
            'Not defined'       => ['encryptedUserId' => null],
            'Missing user id'   => [
                'encryptedUserId' => json_encode(
                    ['timestamp' => time(), 'signature' => 'signature']
                )
            ],
            'Missing timestamp' => ['encryptedUserId' => json_encode(['user_id' => 234, 'signature' => 'signature'])],
            'Missing signature' => ['encryptedUserId' => json_encode(['user_id' => 234, 'timestamp' => time()])],
        ];
        }

        /**
        * @test
        * that assignQuoteCustomerByEncryptedUserId throws {@see \Magento\Framework\Webapi\Exception}
        * if signature of encrypted user id is invalid
        *
        * @covers ::assignQuoteCustomerByEncryptedUserId
        *
        * @throws Exception from tested method
        */
        public function assignQuoteCustomerByEncryptedUserId_withInvalidSignature_throwsException()
        {
        $this->expectExceptionMessage("Incorrect signature");
        $this->expectException(\Magento\Framework\Webapi\Exception::class);
        $this->expectExceptionCode(6306);
        $payload = ['user_id' => 1, 'timestamp' => time() - 3600 - 1];
        $encryptedUserId = json_encode($payload + ['signature' => 'incorrect_signature']);
        $this->hookHelper->expects(self::once())->method('verifySignature')->willReturn(false);
        TestHelper::invokeMethod(
            $this->currentMock,
            'assignQuoteCustomerByEncryptedUserId',
            [$this->quoteMock, $encryptedUserId]
        );
        }

        /**
        * @test
        * that assignQuoteCustomerByEncryptedUserId throws {@see \Magento\Framework\Webapi\Exception}
        * if timestamp in provided encrypted user id is older than 1 hour
        *
        * @covers ::assignQuoteCustomerByEncryptedUserId
        *
        * @throws Exception from tested method
        */
        public function assignQuoteCustomerByEncryptedUserId_withOutdatedTimestamp_throwsException()
        {
        $this->expectExceptionMessage("Outdated encrypted_user_id");
        $this->expectException(\Magento\Framework\Webapi\Exception::class);
        $this->expectExceptionCode(6306);
        $payload = ['user_id' => 1, 'timestamp' => time() - 3600 - 1];
        $encryptedUserId = json_encode($payload + ['signature' => 'correct_signature']);
        $this->hookHelper->expects(self::once())->method('verifySignature')->willReturn(true);
        TestHelper::invokeMethod(
            $this->currentMock,
            'assignQuoteCustomerByEncryptedUserId',
            [$this->quoteMock, $encryptedUserId]
        );
        }

        /**
        * @test
        * that assignQuoteCustomerByEncryptedUserId throws {@see \Magento\Framework\Webapi\Exception}
        * if customer with provided id cannot be found
        *
        * @covers ::assignQuoteCustomerByEncryptedUserId
        *
        * @throws Exception from tested method
        */
        public function assignQuoteCustomerByEncryptedUserId_withNonExistingUserIdInRequest_throwsException()
        {
        $this->expectExceptionMessage("Incorrect user_id");
        $this->expectException(\Magento\Framework\Webapi\Exception::class);
        $this->expectExceptionCode(6306);
        $payload = ['user_id' => 2, 'timestamp' => time()];
        $encryptedUserId = json_encode($payload + ['signature' => 'correct_signature']);
        $this->hookHelper->expects(self::once())->method('verifySignature')->willReturn(true);
        $this->customerRepository->expects(static::once())->method('getById')->with(2)
            ->willThrowException(new NoSuchEntityException());
        TestHelper::invokeMethod(
            $this->currentMock,
            'assignQuoteCustomerByEncryptedUserId',
            [$this->quoteMock, $encryptedUserId]
        );
        }

        /**
        * @test
        * that assignQuoteCustomerByEncryptedUserId assigns customer to quote using
        * {@see \Magento\Quote\Model\Quote::assignCustomer} based on provided encrypted user(customer) id
        *
        * @covers ::assignQuoteCustomerByEncryptedUserId
        *
        * @throws ReflectionException if assignQuoteCustomerByEncryptedUserId method is not defined
        */
        public function assignQuoteCustomerByEncryptedUserId_withValidEncryptedUserId_assignsCustomerToQuote()
        {
        $payload = ['user_id' => 1, 'timestamp' => time()];
        $signature = 'correct_signature';
        $encryptedUserId = json_encode($payload + ['signature' => $signature]);
        $this->hookHelper->expects(self::once())->method('verifySignature')->with(json_encode($payload), $signature)
            ->willReturn(true);
        $customerMock = $this->createMock(\Magento\Customer\Model\Data\Customer::class);
        $this->customerRepository->expects(self::once())->method('getById')->willReturn($customerMock);
        $this->quoteMock->expects(self::once())->method('assignCustomer')->with($customerMock);
        TestHelper::invokeMethod(
            $this->currentMock,
            'assignQuoteCustomerByEncryptedUserId',
            [$this->quoteMock, $encryptedUserId]
        );
        }

        private function calculateCartAndHints_initResponseData()
        {
        $requestShippingAddress = 'String';

        $response = (object) ( [
            'cart' =>
                (object) ( [
                    'order_reference' => self::QUOTE_ID,
                    'display_id'      => '100050001 / 1234',
                    'shipments'       =>
                        [
                            0 =>
                                (object) ( [
                                    'shipping_address' => $requestShippingAddress,
                                    'shipping_method' => 'unknown',
                                    'service'         => 'Flat Rate - Fixed',
                                    'cost'            =>
                                        (object) ( [
                                            'amount'          => 500,
                                            'currency'        => 'USD',
                                            'currency_symbol' => '$',
                                        ] ),
                                    'tax_amount'      =>
                                        (object) ( [
                                            'amount'          => 0,
                                            'currency'        => 'USD',
                                            'currency_symbol' => '$',
                                        ] ),
                                    'reference'       => 'flatrate_flatrate'
                                ] ),
                        ],
                ] ),
            'token' => self::TOKEN
        ] );

        //weird bit of stuff here, copied from the code under test
        $responseData = json_decode(json_encode($response), true);

        $expectedCart = array_merge($responseData['cart'], [
            'orderToken' => self::TOKEN,
            'cartReference' => self::QUOTE_ID
        ]);

        return [$response,$expectedCart];
        }

        /**
        * @test
        */
        public function calculateCartAndHints_happyPath()
        {
        list($response,$expectedCart) = $this->calculateCartAndHints_initResponseData();
        $expected = [
            'status' => 'success',
            'cart' => $expectedCart,
            'hints' => self::HINT,
            'backUrl' => ''
        ];

        $boltpayOrder = $this->getMockBuilder(Response::class)
                                   ->setMethods(['getResponse'])
                                   ->disableOriginalConstructor()
                                   ->getMock();
        $boltpayOrder->method('getResponse')->willReturn($response);
        $currentMock = $this->getCurrentMock(
            [
                'isCheckoutAllowed',
                'hasProductRestrictions',
                'getBoltpayOrder',
                'getHints',
            ]
        );

        $currentMock->method('isCheckoutAllowed')->willReturn(true);
        $currentMock->method('hasProductRestrictions')->willReturn(false);
        $currentMock->method('getHints')->willReturn(self::HINT);
        $currentMock->method('getBoltpayOrder')
                   ->withAnyParameters()
                   ->willReturn($boltpayOrder);

        $this->assertEquals($expected, $currentMock->calculateCartAndHints());
        }

        /**
        * @test
        */
        public function calculateCartAndHints_HasProductRestrictions()
        {
        list($response,$expectedCart) = $this->calculateCartAndHints_initResponseData();

        $expected = [
            'status' => 'success',
            'restrict' => true,
            'message' => 'The cart has products not allowed for Bolt checkout',
            'backUrl' => ''
        ];

        $boltpayOrder = $this->getMockBuilder(Response::class)
                             ->setMethods(['getResponse'])
                             ->disableOriginalConstructor()
                             ->getMock();
        $boltpayOrder->method('getResponse')->willReturn($response);

        $currentMock = $this->getCurrentMock(
            [
                'isCheckoutAllowed',
                'hasProductRestrictions',
                'getBoltpayOrder',
                'getHints',
            ]
        );

        $currentMock->method('isCheckoutAllowed')->willReturn(true);
        $currentMock->method('hasProductRestrictions')->willReturn(true);
        $currentMock->method('getHints')->willReturn(self::HINT);
        $currentMock->method('getBoltpayOrder')
                    ->withAnyParameters()
                    ->willReturn($boltpayOrder);

        $this->assertEquals($expected, $currentMock->calculateCartAndHints());
        }

        /**
        * @test
        */
        public function calculateCartAndHints_DisallowedCheckout()
        {
        list($response,$expectedCart) = $this->calculateCartAndHints_initResponseData();
        $expected = [
            'status' => 'success',
            'restrict' => true,
            'message' => 'Guest checkout is not allowed.',
            'backUrl' => ''
        ];

        $boltpayOrder = $this->getMockBuilder(Response::class)
                             ->setMethods(['getResponse'])
                             ->disableOriginalConstructor()
                             ->getMock();
        $boltpayOrder->method('getResponse')->willReturn($response);
        $currentMock = $this->getCurrentMock(
            [
                'isCheckoutAllowed',
                'hasProductRestrictions',
                'getBoltpayOrder',
                'getHints',
            ]
        );

        $currentMock->method('isCheckoutAllowed')->willReturn(false);
        $currentMock->method('hasProductRestrictions')->willReturn(false);
        $currentMock->method('getHints')->willReturn(self::HINT);
        $currentMock->method('getBoltpayOrder')
                    ->withAnyParameters()
                    ->willReturn($boltpayOrder);

        $this->assertEquals($expected, $currentMock->calculateCartAndHints());
        }

        /**
        * @test
        */
        public function calculateCartAndHints_GeneralException()
        {
        $exceptionMessage = 'Test exception message';
        $exception = new \Exception($exceptionMessage);
        list($response,$expectedCart) = $this->calculateCartAndHints_initResponseData();
        $expected = [
            'status' => 'failure',
            'message' => $exceptionMessage,
            'backUrl' => '',
        ];

        $boltpayOrder = $this->getMockBuilder(Response::class)
                             ->setMethods(['getResponse'])
                             ->disableOriginalConstructor()
                             ->getMock();
        $boltpayOrder->method('getResponse')->willReturn($response);
        $currentMock = $this->getCurrentMock(
            [
                'isCheckoutAllowed',
                'hasProductRestrictions',
                'getBoltpayOrder',
                'getHints',
            ]
        );

        $currentMock->method('isCheckoutAllowed')->willReturn(true);
        $currentMock->method('hasProductRestrictions')->willReturn(false);
        $currentMock->method('getHints')->willReturn(self::HINT);
        $currentMock->method('getBoltpayOrder')->willThrowException($exception);

        $this->assertEquals($expected, $currentMock->calculateCartAndHints());
        }

        /**
        * @test
        */
        public function calculateCartAndHints_NullResponse()
        {
        list($response,$expectedCart) = $this->calculateCartAndHints_initResponseData();
        $expected = [
            'status' => 'success',
            'cart' => [
                'orderToken' => '',
                'cartReference' => ''
            ],
            'hints' => null,
            'backUrl' => ''
        ];

        $boltpayOrder = $this->getMockBuilder(Response::class)
                             ->setMethods(['getResponse'])
                             ->disableOriginalConstructor()
                             ->getMock();
        $boltpayOrder->method('getResponse')->willReturn($response);
        $currentMock = $this->getCurrentMock(
            [
                'isCheckoutAllowed',
                'hasProductRestrictions',
                'getBoltpayOrder',
                'getHints',
            ]
        );

        $currentMock->method('isCheckoutAllowed')->willReturn(true);
        $currentMock->method('hasProductRestrictions')->willReturn(false);
        $currentMock->method('getBoltpayOrder')->willReturn(null);

        $this->assertEquals($expected, $currentMock->calculateCartAndHints());
        }
        }
