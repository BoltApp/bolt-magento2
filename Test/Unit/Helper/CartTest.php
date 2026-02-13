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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as BoltHelperCart;
use Bolt\Boltpay\Helper\Log;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Model\Request;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
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
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Session\Generic as GenericSession;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Customer\Model\Address;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
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
use Magento\Catalog\Model\Config\Source\Product\Thumbnail as ThumbnailSource;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Serialize\SerializerInterface as Serialize;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Magento\SalesRule\Model\RuleRepository;
use Bolt\Boltpay\Helper\FeatureSwitch\Definitions;
use Magento\Msrp\Helper\Data as MsrpHelper;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;

/**
 * @coversDefaultClass \Bolt\Boltpay\Helper\Cart
 */
class CartTest extends BoltTestCase
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
    const CUSTOMER_GROUP_ID = '1';

    /** @var array Address data containing all required fields */
    const COMPLETE_ADDRESS_DATA = [
        'first_name'      => "Bolt",
        'last_name'       => "Test",
        'locality'        => "New York",
        'street_address1' => "228 5th Avenue",
        'postal_code'     => "10001",
        'region' => 'CA',
        'country_code'    => "US",
        'email'           => self::EMAIL_ADDRESS,
    ];

    /** @var string Test currency code */
    const CURRENCY_CODE = 'USD';

    /** @var string Test email address */
    const EMAIL_ADDRESS = 'integration@bolt.com';

    const COUPON_CODE = 'testcoupon';

    const COUPON_DESCRIPTION = 'test coupon';

    /** @var int Test original order entity id when editing orders */
    const ORIGINAL_ORDER_ENTITY_ID = 234567;

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
     * @var MetricsClient
     */
    private $metricsClient;

    /** @var MockObject|DeciderHelper */
    private $deciderHelper;

    /** @var MockObject|EventsForThirdPartyModules */
    private $eventsForThirdPartyModules;

    /** array of objects we need to delete after test */
    private $objectsToClean;

    /** @var MockObject|Serialize */
    private $serialize;

    /** @var MockObject|RuleRepository */
    private $ruleRepository;

    /** @var MockObject|ObjectManager */
    private $originalObjectManager;

    /** @var MockObject|MsrpHelper */
    private $msrpHelper;

    /** @var MockObject|PriceHelper */
    private $priceHelper;

    /** @var MockObject|Store */
    private $store;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        $this->originalObjectManager = ObjectManager::getInstance();
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
            'getCustomerId',
            'setIsActive',
            'getData',
            'getStore',
            'save',
            'getCouponCode',
            'setCustomerIsGuest',
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

        $this->productMock = $this->createPartialMock(Product::class, ['getDescription', 'getTypeInstance', 'getTypeId', 'isAvailable']);
        $this->productMock->method('getTypeInstance')->willReturnSelf();
        $this->contextHelper = $this->createMock(ContextHelper::class);
        $this->quoteMock = $this->createPartialMock(Quote::class, [
            'getQuoteCurrencyCode','getAllVisibleItems',
            'getTotals','getStore','getStoreId',
            'getData','isVirtual','getId','getShippingAddress',
            'getBillingAddress','reserveOrderId','addProduct',
            'assignCustomer','setIsActive','getGiftMessageId',
            'getGwId', 'getCustomerGroupId','setCustomerIsGuest',
        ]);
        $this->checkoutSession = $this->createPartialMock(CheckoutSession::class, ['getQuote', 'getBoltCollectSaleRuleDiscounts', 'getOrderId']);
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
        $this->metricsClient = $this->createMock(MetricsClient::class);
        $this->serialize = (new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this))->getObject(\Magento\Framework\Serialize\Serializer\Json::class);
        $this->deciderHelper = $this->createPartialMock(
            DeciderHelper::class,
            ['ifShouldDisablePrefillAddressForLoggedInCustomer', 'handleVirtualProductsAsPhysical',
             'isIncludeUserGroupIntoCart', 'isAddSessionIdToCartMetadata', 'isCustomizableOptionsSupport',
             'isPreventBoltCartForQuotesWithError','isAPIDrivenIntegrationEnabled','isUseRuleNameIfDescriptionEmpty',
             'isEnabledFetchCartViaApi', 'isMSRPPriceDisabled'
            ]
        );
        $this->deciderHelper->method('isEnabledFetchCartViaApi')->willReturn(false);
        $this->deciderHelper->method('isMSRPPriceDisabled')->willReturn(false);
        $this->eventsForThirdPartyModules = $this->createPartialMock(EventsForThirdPartyModules::class, ['runFilter','dispatchEvent']);
        $this->eventsForThirdPartyModules->method('runFilter')->will($this->returnArgument(1));
        $this->eventsForThirdPartyModules->method('dispatchEvent')->willReturnSelf();
        $this->ruleRepository = $this->createPartialMock(
            RuleRepository::class,
            ['getById']
        );
        $this->msrpHelper = $this->createPartialMock(MsrpHelper::class, ['canApplyMsrp']);
        $this->priceHelper = $this->createPartialMock(PriceHelper::class, ['currency']);
        $this->store = $this->createMock(Store::class);
        $this->currentMock = $this->getCurrentMock(null);
        $this->objectsToClean = [];
    }

    protected function tearDownInternal()
    {
        TestUtils::cleanupSharedFixtures($this->objectsToClean);
        ObjectManager::setInstance($this->originalObjectManager);
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
                    $this->metricsClient,
                    $this->deciderHelper,
                    $this->serialize,
                    $this->eventsForThirdPartyModules,
                    $this->ruleRepository,
                    $this->msrpHelper,
                    $this->priceHelper,
                    $this->store
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
            'getData',
            'getCouponCode'
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
                    'getCouponCode',
                    'getDiscountDescription',
                    'getAppliedRuleIds'
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
            $this->metricsClient,
            $this->deciderHelper,
            $this->serialize,
            $this->eventsForThirdPartyModules,
            $this->ruleRepository,
            $this->msrpHelper,
            $this->priceHelper,
            $this->store
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
        static::assertAttributeEquals($this->serialize, 'serialize', $instance);
        static::assertAttributeEquals($this->ruleRepository, 'ruleRepository', $instance);
        static::assertAttributeEquals($this->msrpHelper, 'msrpHelper', $instance);
        static::assertAttributeEquals($this->priceHelper, 'priceHelper', $instance);
        static::assertAttributeEquals($this->store, 'store', $instance);
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

        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        static::assertEquals($quoteId, $boltHelperCart->getQuoteById($quoteId)->getId());
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

        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        static::assertFalse($boltHelperCart->getQuoteById(self::IMMUTABLE_QUOTE_ID));
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

        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        TestHelper::setProperty(
            $boltHelperCart,
            'quotes',
            [$quoteId => $quote]
        );
        static::assertEquals($quote, $boltHelperCart->getQuoteById($quoteId));
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

        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = TestUtils::createQuote(['is_active' => true]);
        $quoteId = $quote->getId();
        static::assertEquals($quoteId, $boltHelperCart->getActiveQuoteById($quoteId)->getId());
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

        $order = TestUtils::createDumpyOrder();
        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        TestHelper::setProperty(
            $boltHelperCart,
            'orderData',
            [$order->getIncrementId() => $order]
        );
        static::assertEquals($order->getId(), $boltHelperCart->getOrderByIncrementId($order->getIncrementId(), true)->getId());
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * that saveQuote saves provided quote using {@see \Magento\Quote\Api\CartRepositoryInterface::save}
     *
     * @covers ::saveQuote
     */
    public function saveQuote_always_savesQuoteUsingQuoteRepository()
    {

        $quote = TestUtils::createQuote();
        $quote->setCustomerEmail('johnmc+testing@bolt.com');
        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $boltHelperCart->saveQuote($quote);
        $quoteId = $quote->getId();
        self::assertEquals(
            'johnmc+testing@bolt.com',
            TestUtils::getQuoteById($quoteId)->getCustomerEmail()
        );
    }

    /**
     * @test
     * that deleteQuote deletes provided quote using {@see \Magento\Quote\Api\CartRepositoryInterface::delete}
     *
     * @covers ::deleteQuote
     */
    public function deleteQuote_always_deletesQuoteUsingQuoteRepository()
    {

        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $boltHelperCart->deleteQuote($quote);
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('No such entity with cartId = '.$quoteId);
        TestUtils::getQuoteById($quoteId);
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

        $quote = TestUtils::createQuote();
        $quote->setCustomerEmail('johnmc+testing@bolt.com');
        $quote->save();
        $quoteId = $quote->getId();
        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $boltHelperCart->quoteResourceSave($quote);
        self::assertEquals(
            'johnmc+testing@bolt.com',
            TestUtils::getQuoteById($quoteId)->getCustomerEmail()
        );
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
        $configWriter = Bootstrap::getObjectManager()->create(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        $configWriter->save(
            ConfigHelper::XML_PATH_BOLT_ORDER_CACHING,
            $isBoltOrderCachingEnabled,
            $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            self::STORE_ID
        );
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);

        static::assertEquals(
            $isBoltOrderCachingEnabled,
            TestHelper::invokeMethod($cartHelper, 'isBoltOrderCachingEnabled', [self::STORE_ID])
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

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $cache = Bootstrap::getObjectManager()->create(CacheInterface::class);
        $cachedValue = false;
        $cache->save($cachedValue, self::CACHE_IDENTIFIER);
        static::assertFalse(TestHelper::invokeMethod($cartHelper, 'loadFromCache', [self::CACHE_IDENTIFIER]));
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

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $cache = Bootstrap::getObjectManager()->create(CacheInterface::class);
        $cachedValue = 'Test cache value';
        $cache->save($cachedValue, self::CACHE_IDENTIFIER);

        static::assertEquals(
            $cachedValue,
            TestHelper::invokeMethod(
                $cartHelper,
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
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $cache = Bootstrap::getObjectManager()->create(CacheInterface::class);
        $cache->save($this->serialize->serialize($cachedValue), self::CACHE_IDENTIFIER);
        static::assertEquals(
            $cachedValue,
            TestHelper::invokeMethod(
                $cartHelper,
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
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $testCartData = $this->getTestCartData();
        TestHelper::invokeMethod(
            $cartHelper,
            'saveToCache',
            [$testCartData, self::CACHE_IDENTIFIER, [], null, true]
        );
        $cache = Bootstrap::getObjectManager()->create(CacheInterface::class);
        $result = $cache->load(self::CACHE_IDENTIFIER);
        self::assertEquals($this->serialize->serialize($testCartData), $result);
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

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = TestUtils::createQuote();
        TestHelper::invokeMethod(
            $cartHelper,
            'setLastImmutableQuote',
            [$quote]
        );
        static::assertAttributeEquals(
            $quote,
            'lastImmutableQuote',
            $cartHelper
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

        $quote = TestUtils::createQuote();
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        TestHelper::setProperty(
            $cartHelper,
            'lastImmutableQuote',
            $quote
        );
        static::assertEquals(
            $quote,
            TestHelper::invokeMethod($cartHelper, 'getLastImmutableQuote')
        );
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

        $cartSession = Bootstrap::getObjectManager()->create(CheckoutSession::class);
        $quote = TestUtils::createQuote(['store_id'=>self::STORE_ID]);
        $cartSession->setQuote($quote);
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        static::assertEquals(self::STORE_ID, $cartHelper->getSessionQuoteStoreId());
        $cartSession->setQuote(null);
    }

    /**
     * @test
     * that getSessionQuoteStoreId returns store id from checkout session quote
     *
     * @covers ::getSessionQuoteStoreId
     */
    public function getSessionQuoteStoreId_withoutSessionQuote_returnsNull()
    {

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        static::assertEquals(null, $cartHelper->getSessionQuoteStoreId());
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

        $testCartData = $this->getTestCartData();
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $result = TestHelper::invokeMethod($cartHelper, 'getCartCacheIdentifier', [$testCartData]);
        unset($testCartData['display_id']);
        static::assertEquals(hash('md5', json_encode($testCartData)), $result);
    }

    /**
     * @test
     *
     * @covers ::getCartCacheIdentifier
     * @throws ReflectionException
     */
    public function getCartCacheIdentifier_withGiftMessageID_returnsCartCacheIdentifier()
    {

        $testCartData = $this->getTestCartData();
        $quote = TestUtils::createQuote([
            'gift_message_id'=> self::GIFT_MESSAGE_ID,
            'customer_group_id' => self::CUSTOMER_GROUP_ID,
            'customer_id' => self::CUSTOMER_ID,

        ]);

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        TestHelper::setProperty($cartHelper, 'lastImmutableQuote', $quote);

        $result = TestHelper::invokeMethod($cartHelper, 'getCartCacheIdentifier', [$testCartData]);
        unset($testCartData['display_id']);
        static::assertEquals(
            hash(
                'md5',
                json_encode($testCartData).self::GIFT_MESSAGE_ID.self::CUSTOMER_GROUP_ID.self::CUSTOMER_ID
            ),
            $result
        );
    }

    /**
     * @test
     *
     * @covers ::getCartCacheIdentifier
     * @throws ReflectionException
     */
    public function getCartCacheIdentifier_withGiftWrappingId_returnsCartCacheIdentifier()
    {

        $testCartData = $this->getTestCartData();
        $quote = TestUtils::createQuote([
            'gw_id'=> self::GIFT_WRAPPING_ID,
            'customer_group_id' => self::CUSTOMER_GROUP_ID,
            'customer_id' => self::CUSTOMER_ID,
        ]);

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        TestHelper::setProperty($cartHelper, 'lastImmutableQuote', $quote);

        $result = TestHelper::invokeMethod($cartHelper, 'getCartCacheIdentifier', [$testCartData]);
        unset($testCartData['display_id']);
        static::assertEquals(
            hash(
                'md5',
                json_encode($testCartData).self::GIFT_WRAPPING_ID.self::CUSTOMER_GROUP_ID.self::CUSTOMER_ID
            ),
            $result
        );
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
        static::assertEquals(hash('md5', json_encode($testCartData) . $addressCacheIdentifier. self::QUOTE_ITEM_ID.'-'.self::GIFT_WRAPPING_ID), $result);
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

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $boltOrder = (object)[
            'cart' => (object)[
                'metadata' => (object)[
                    'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID
                ]
            ]
        ];
        static::assertEquals(
            self::IMMUTABLE_QUOTE_ID,
            TestHelper::invokeMethod(
                $cartHelper,
                'getImmutableQuoteIdFromBoltOrder',
                [$boltOrder]
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

        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        static::assertTrue(TestHelper::invokeMethod($cartHelper, 'isQuoteAvailable', [$quoteId]));
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

        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        TestHelper::invokeMethod($cartHelper, 'updateQuoteTimestamp', [$quoteId]);
        self::assertNotNull(TestUtils::getQuoteById($quoteId)->getUpdatedAt());
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

        $pitem = $this->getQuoteItemMock();

        $this->quoteMock->expects(static::once())->method('getData')->willReturn(
            [
                'entity_id'          => self::PARENT_QUOTE_ID,
                'customer_firstname' => 'Test',
                'customer_lastname'  => 'Test',
                'customer_email'     => self::EMAIL_ADDRESS,
                'email'              => 'invalid.mail',
                'reserved_order_id'  => self::ORDER_INCREMENT_ID,
                'customer_id'        => self::CUSTOMER_ID,
                'items'              => [$pitem],
            ]
        );

        $citem = $this->getQuoteItemMock();
        $childQuoteMock = $this->createMock(Quote::class);
        $childQuoteMock->expects(static::atLeastOnce())->method('setData')->withConsecutive(
            ['customer_firstname', 'Test'],
            ['customer_lastname', 'Test'],
            ['customer_email', self::EMAIL_ADDRESS],
            ['customer_id', self::CUSTOMER_ID],
            ['items', [$citem]]
        );
        $childQuoteMock->expects(static::once())->method('save');
        $childQuoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn([$citem]);
        $currentMock->expects(static::exactly(2))->method('validateEmail')->withConsecutive(
            [self::EMAIL_ADDRESS],
            ['invalid.mail']
        )->willReturnOnConsecutiveCalls(true, false);

        TestHelper::invokeMethod($currentMock, 'transferData', [$this->quoteMock, $childQuoteMock]);
    }

    /**
     * @test
     * that transferData with default email and exclude fields parameters transfers data from provided parent Address to child Address, and unset cached_items_all field
     *
     * @covers ::transferData
     *
     * @throws ReflectionException if transferData method is not defined
     */
    public function transferData_withDefaultFields_transfersDataFromParentAddressToChildAddressAndSavesTheChild()
    {
        $currentMock = $this->getCurrentMock(['validateEmail']);

        $addressParentShippingAddress = $this->createMock(Quote\Address::class);

        $addressParentShippingAddress->expects(static::once())->method('getData')->willReturn(
            [
                'email'     => $this->testAddressData['email'],
                'firstname' => $this->testAddressData['first_name'],
                'lastname'  => $this->testAddressData['last_name'],
                'street'    => $this->testAddressData['street_address1'],
                'city'      => $this->testAddressData['locality'],
                'region'    => $this->testAddressData['region'],
                'postcode'  => $this->testAddressData['postal_code'],
            ]
        );

        $addressChildShippingAddress = $this->createMock(Quote\Address::class);

        $addressChildShippingAddress->expects(static::atLeastOnce())->method('setData')->withConsecutive(
            ['email', $this->testAddressData['email']],
            ['firstname', $this->testAddressData['first_name']],
            ['lastname', $this->testAddressData['last_name']],
            ['street', $this->testAddressData['street_address1']],
            ['city', $this->testAddressData['locality']],
            ['region', $this->testAddressData['region']],
            ['postcode', $this->testAddressData['postal_code']]
        );
        $addressChildShippingAddress->expects(static::once())->method('save');

        $currentMock->expects(static::once())->method('validateEmail')->with($this->testAddressData['email'])->willReturn(true);

        TestHelper::invokeMethod($currentMock, 'transferData', [$addressParentShippingAddress, $addressChildShippingAddress]);
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
        $currentMock = $this->getCurrentMock(['quoteResourceSave']);
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

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        static::assertEquals(
            $expectedResult,
            TestHelper::invokeMethod($cartHelper, 'isAddressComplete', [$address])
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

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        TestHelper::setProperty($cartHelper, 'checkoutSession', $sessionObject);
        static::assertEquals($expectedResult, TestHelper::invokeMethod($cartHelper, 'isBackendSession'));
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
                'sessionObject'  => Bootstrap::getObjectManager()->create(\Magento\Backend\Model\Session\Quote::class),
                'expectedResult' => true
            ],
            'Generic session' => [
                'sessionObject'  => Bootstrap::getObjectManager()->create(GenericSession::class),
                'expectedResult' => false
            ],
        ];
    }

    /**
     * @test
     * that getCalculationAddress returns billing address for virtual quotes
     *
     * @covers ::getCalculationAddress
     *
     * @throws ReflectionException if getCalculationAddress method is not defined
     */
    public function getCalculationAddress_withQuoteIsVirtual_returnBillingAddress()
    {
        $shippingAddress = [
            'region'                     => 'CA',
            'country_code'               => 'US',
            'email_address'              => 'shippingaddress@bolt.com',
            'street_address1'            => 'Test Street 11',
            'street_address2'            => 'Test Street 22',
            'locality'                   => 'Beverly Hills',
            'postal_code'                => '90210',
            'phone_number'               => '0123456789',
            'company'                    => 'Bolt',
            'random_empty_field'         => '',
            'another_random_empty_field' => [],
        ];

        $billingAddress = [
            'region'                     => 'CA',
            'country_code'               => 'US',
            'email_address'              => 'billingaddress@bolt.com',
            'street_address1'            => 'Test Street 1',
            'street_address2'            => 'Test Street 2',
            'locality'                   => 'Beverly Hills',
            'postal_code'                => '90210',
            'phone_number'               => '0123456789',
            'company'                    => 'Bolt',
            'random_empty_field'         => '',
            'another_random_empty_field' => [],
        ];


        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);

        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $product = TestUtils::createVirtualProduct();
        $this->objectsToClean[] = $product;
        $quote->addProduct($product, 1);
        $quote->setIsVirtual(true);
        $quote->getShippingAddress()->addData($shippingAddress);
        $quote->getBillingAddress()->addData($billingAddress);
        $quote->save();

        static::assertEquals(
            'billingaddress@bolt.com',
            TestHelper::invokeMethod(
                $cartHelper,
                'getCalculationAddress',
                [$quote]
            )->getEmailAddress()
        );
    }

    /**
     * @test
     * that getCalculationAddress returns shipping address for quotes which is not virtual
     *
     * @covers ::getCalculationAddress
     *
     * @throws ReflectionException if getCalculationAddress method is not defined
     */
    public function getCalculationAddress_withQuoteIsNotVirtual_returnShippingAddress()
    {
        $shippingAddress = [
            'region'                     => 'CA',
            'country_code'               => 'US',
            'email_address'              => 'shippingaddress@bolt.com',
            'street_address1'            => 'Test Street 11',
            'street_address2'            => 'Test Street 22',
            'locality'                   => 'Beverly Hills',
            'postal_code'                => '90210',
            'phone_number'               => '0123456789',
            'company'                    => 'Bolt',
            'random_empty_field'         => '',
            'another_random_empty_field' => [],
        ];

        $billingAddress = [
            'region'                     => 'CA',
            'country_code'               => 'US',
            'email_address'              => 'billingaddress@bolt.com',
            'street_address1'            => 'Test Street 1',
            'street_address2'            => 'Test Street 2',
            'locality'                   => 'Beverly Hills',
            'postal_code'                => '90210',
            'phone_number'               => '0123456789',
            'company'                    => 'Bolt',
            'random_empty_field'         => '',
            'another_random_empty_field' => [],
        ];


        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);

        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $quote->getShippingAddress()->addData($shippingAddress);
        $quote->getBillingAddress()->addData($billingAddress);
        $quote->save();

        static::assertEquals(
            'shippingaddress@bolt.com',
            TestHelper::invokeMethod(
                $cartHelper,
                'getCalculationAddress',
                [$quote]
            )->getEmailAddress()
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

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        static::assertEquals($expectedResult, $cartHelper->validateEmail($email));
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

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $checkoutSession = Bootstrap::getObjectManager()->create(CheckoutSession::class);
        $store = Bootstrap::getObjectManager()->create(Store::class)->setWebsiteId(self::WEBSITE_ID);
        $quote = TestUtils::createQuote(['store'=> $store]);
        $checkoutSession->setQuote($quote);

        static::assertEquals(self::WEBSITE_ID, TestHelper::invokeMethod($cartHelper, 'getWebsiteId'));
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

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $addressData = ['country_code' => 'PR'];
        $result = $cartHelper->handleSpecialAddressCases($addressData);
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

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = TestUtils::createQuote();
        static::assertFalse($cartHelper->hasProductRestrictions($quote));
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

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = TestUtils::createQuote();
        $configWriter = Bootstrap::getObjectManager()->create(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        $configWriter->save(
            ConfigHelper::XML_PATH_ADDITIONAL_CONFIG,
            json_encode(['toggleCheckout' => ['active' => true, 'productRestrictionMethods' => [], 'itemRestrictionMethods' => []]], true)
        );

        static::assertFalse($cartHelper->hasProductRestrictions($quote));
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

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = TestUtils::createQuote();
        $configWriter = Bootstrap::getObjectManager()->create(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        $configWriter->save(
            ConfigHelper::XML_PATH_ADDITIONAL_CONFIG,
            json_encode([
                'toggleCheckout' => [
                    'active'                    => true,
                    'productRestrictionMethods' => ['getIsRestricted'],
                    'itemRestrictionMethods'    => []
                ]
            ], true)
        );

        static::assertFalse($cartHelper->hasProductRestrictions($quote));
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
        $boltOrderResponse->setStoreId(1);
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

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Order was created. Please reload the page and try again');
        $this->currentMock->getBoltpayOrder(false, '');
    }

        /**
         * @test
         * that deactivateSessionQuote deactivates session quote if it is active
         *
         * @covers ::deactivateSessionQuote
         */
    public function deactivateSessionQuote_ifQuoteIsActive_deactivatesQuote()
    {

        $quote = TestUtils::createQuote(['is_active'=> true]);
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $cartHelper->deactivateSessionQuote($quote);
        self::assertFalse($quote->getIsActive());
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

        $order = TestUtils::createDumpyOrder();
        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        static::assertEquals($order->getId(), $boltHelperCart->doesOrderExist(
            ['display_id' => $order->getIncrementId()],
            null
        )->getId());
        TestUtils::cleanupSharedFixtures([$order]);
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

        $quote = TestUtils::createQuote();
        $order = TestUtils::createDumpyOrder(['quote_id' => $quote->getId()]);
        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        static::assertEquals(
            $order->getId(),
            $boltHelperCart->doesOrderExist(['display_id' => self::ORDER_INCREMENT_ID], $quote)->getId()
        );
        TestUtils::cleanupSharedFixtures([$order]);
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

        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        static::assertFalse(
            $boltHelperCart->doesOrderExist(
                ['display_id' => self::ORDER_INCREMENT_ID],
                $this->quoteMock
            )
        );
    }

        /**
         * @test
         */
    public function getOrderByQuoteId()
    {

        $order = TestUtils::createDumpyOrder(['quote_id' => self::QUOTE_ID]);
        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        static::assertEquals($order->getId(), $boltHelperCart->getOrderByQuoteId(self::QUOTE_ID)->getId());
        TestUtils::cleanupSharedFixtures([$order]);
    }

        /**
         * @test
         */
    public function getOrderById()
    {

        $order = TestUtils::createDumpyOrder();
        $orderId = $order->getId();
        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        static::assertEquals($order->getId(), $boltHelperCart->getOrderById($orderId)->getId());
        TestUtils::cleanupSharedFixtures([$order]);
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
        ->willReturn($boltOrder->toArray());
        $currentMock->expects(static::once())->method('getImmutableQuoteIdFromBoltOrder')->with($boltOrder->getResponse())
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
        $currentMock->expects(static::once())->method('getImmutableQuoteIdFromBoltOrder')->with($boltOrder->getResponse())
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
     * @dataProvider dataProvider_sessionIdToCartMetadataSwitch
     * @covers ::getCartData
     */
    public function getCartData_inMultistepWithNoDiscount_returnsCartData(
        $addSessionIdToMetadataValue,
        $metadataSessionIdAssertMethod
    ) {
        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = TestUtils::createQuote();
        $product = TestUtils::getSimpleProduct();
        $this->objectsToClean[] = $product;
        $quote->addProduct($product, 1);
        TestUtils::setQuoteToSession($quote);

        $sessionToMetadataSwitch = TestUtils::saveFeatureSwitch(
            Definitions::M2_ADD_SESSION_ID_TO_CART_METADATA,
            $addSessionIdToMetadataValue
        );
        $result = $boltHelperCart->getCartData(false, "");
        TestUtils::cleanupFeatureSwitch($sessionToMetadataSwitch);

        // check that created immutuble quote has correct parent quote id
        $immutable_quote_id = $result['metadata']['immutable_quote_id'];
        $immutable_quote = TestUtils::getQuoteById($immutable_quote_id);
        static::assertEquals($immutable_quote->getBoltParentQuoteId(), $quote->getId());

        // check image url
        static::assertMatchesRegularExpression(
            "|https?://localhost/(pub\/)?static/version\d+/frontend/Magento/luma/en_US/Magento_Catalog/images/product/placeholder/small_image.jpg|",
            $result['items'][0]['image_url']
        );
        unset($result['items'][0]['image_url']);

        // check that session id is saved in metadata
        $encrypted_session_id = $result['metadata']['encrypted_session_id'] ?? null;
        static::$metadataSessionIdAssertMethod($encrypted_session_id);
        unset($result['metadata']['encrypted_session_id']);

        $expected = [
            'order_reference' => $quote->getId(),
            'display_id'      => '',
            'currency'        => self::CURRENCY_CODE,
            'items'           => [
                [
                    'reference'           => $product->getId(),
                    'name'                => 'Test Product',
                    'total_amount'        => 10000,
                    'unit_price'          => 10000,
                    'quantity'            => 1,
                    'sku'                 => $product->getSku(),
                    'type'                => 'physical',
                    'description'         => 'Product Description',
                    'merchant_product_id' => $product->getId(),
                ]
            ],
            'discounts'       => [],
            'total_amount'    => 10000,
            'tax_amount'      => 0,
            'metadata'        => [
                'immutable_quote_id' => $immutable_quote_id,
            ],
        ];

        static::assertEquals($expected, $result);
    }

    /**
     * @test
     * @covers ::getCartData
     */
    public function getCartData_inAPIDrivenFlowAndMultistepWithNoDiscount_returnsCartData() {
        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = TestUtils::createQuote();
        $quote = TestUtils::getQuoteById($quote->getId());
        $product = TestUtils::createSimpleProduct();
        $product =  Bootstrap::getObjectManager()->create("Magento\Catalog\Model\ProductRepository")->getByID($product->getID());

        $quote->addProduct($product, 1);

        $quote->setTotalsCollectedFlag(false)->collectTotals();

        TestUtils::setQuoteToSession($quote);

        $apiDrivenFeatureSwitch = TestUtils::saveFeatureSwitch(Definitions::M2_ENABLE_API_DRIVEN_INTEGRAION, true);
        $result = $boltHelperCart->getCartData(false, "");
        TestUtils::cleanupFeatureSwitch($apiDrivenFeatureSwitch);

        // check image url
        static::assertMatchesRegularExpression(
            "|https?://localhost/(pub\/)?static/version\d+/frontend/Magento/luma/en_US/Magento_Catalog/images/product/placeholder/small_image.jpg|",
            $result['items'][0]['image_url']
        );
        unset($result['items'][0]['image_url']);

        $expected = [
            'order_reference' => $quote->getId(),
            'display_id'      => '',
            'currency'        => self::CURRENCY_CODE,
            'items'           => [
                [
                    'reference'           => $product->getId(),
                    'name'                => 'Test Product',
                    'total_amount'        => 10000,
                    'unit_price'          => 10000,
                    'quantity'            => 1,
                    'sku'                 => $product->getSku(),
                    'type'                => 'physical',
                    'description'         => 'Product Description',
                    'merchant_product_id' => $product->getId(),
                ]
            ],
            'discounts'       => [],
            'total_amount'    => 10000,
            'tax_amount'      => 0,
        ];

        static::assertEquals($expected, $result);
    }

    public function dataProvider_sessionIdToCartMetadataSwitch()
    {
        return [
            [true, 'assertNotEmpty'],
            [false, 'assertEmpty'],
        ];
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

        TestUtils::setQuoteToSession(null);
        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        static::assertEquals([], $boltHelperCart->getCartData(false, '', null));
    }

    /**
     * @test
     * that getCartData returns empty array if the quote has an error and the feature switch is enabled
     *
     * @covers ::getCartData
     *
     * @throws Exception from tested method
     */
    public function getCartData_withQuoteErrorAndFSEnabled_returnsEmptyArray()
    {

        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        TestUtils::setQuoteToSession($quote);
        $quote->setQuoteCurrencyCode('USD');
        TestUtils::saveFeatureSwitch(Definitions::M2_PREVENT_BOLT_CART_FOR_QUOTES_WITH_ERROR, true);
        $quote->addErrorInfo('error', null, null, 'Quote error');
        static::assertEquals([], $boltHelperCart->getCartData(false, '', null));
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

        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        TestUtils::setQuoteToSession($quote);
        $quote->setQuoteCurrencyCode("USD");
        $quote->save();
        static::assertEquals([], $boltHelperCart->getCartData(false, '', null));
    }

        /**
         * @test
         * that getCartData returns expected cart data when checkout type is payment and order payload is valid
         *
         * @covers ::getCartData
         * @dataProvider dataProvider_sessionIdToCartMetadataSwitch
         * @throws Exception from tested method
         */
    public function getCartData_whenPaymentOnlyAndHasOrderPayload_returnsCartData(
        $addSessionIdToMetadataValue,
        $metadataSessionIdAssertMethod
    ) {

        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $quote->setQuoteCurrencyCode("USD");

        $product = TestUtils::getSimpleProduct();
        $this->objectsToClean[] = $product;
        $quote->addProduct($product, 1);

        TestUtils::setAddressToQuote($this->testAddressData, $quote, 'shipping');
        TestUtils::setAddressToQuote($this->testAddressData, $quote, 'billing');

        $quote->getShippingAddress()->setShippingMethod('flatrate_flatrate')->setCollectShippingRates(true);
        $quote->collectTotals()->save();

        TestUtils::setQuoteToSession($quote);

        $sessionToMetadataSwitch = TestUtils::saveFeatureSwitch(
            Definitions::M2_ADD_SESSION_ID_TO_CART_METADATA,
            $addSessionIdToMetadataValue
        );
        $result = $boltHelperCart->getCartData(
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
        TestUtils::cleanupFeatureSwitch($sessionToMetadataSwitch);

        // check image url
        static::assertMatchesRegularExpression(
            "|https?://localhost/(pub\/)?static/version\d+/frontend/Magento/luma/en_US/Magento_Catalog/images/product/placeholder/small_image.jpg|",
            $result['items'][0]['image_url']
        );
        unset($result['items'][0]['image_url']);

        // check that session id is saved in metadata
        $encrypted_session_id = $result['metadata']['encrypted_session_id'] ?? null;
        static::$metadataSessionIdAssertMethod($encrypted_session_id);
        unset($result['metadata']['encrypted_session_id']);

        static::assertEquals(
            [
            'order_reference' => $quote->getId(),
            'display_id'      => '',
            'currency'        => self::CURRENCY_CODE,
            'items'           =>  [
                [
                    'reference'           => $product->getId(),
                    'name'                => 'Test Product',
                    'total_amount'        => 10000.0,
                    'unit_price'          => 10000,
                    'quantity'            => 1.0,
                    'sku'                 => $product->getSku(),
                    'type'                => 'physical',
                    'description'         => 'Product Description',
                    'merchant_product_id' => $product->getId(),
                ]
            ],
            'discounts' => [],
            'total_amount'    => 10500.0,
            'tax_amount'      => 0,
            'shipments'       => [
                [
                    'cost'             => 500,
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
                    'service'          => 'Flat Rate - Fixed',
                    'reference'        => 'flatrate_flatrate',
                ]
            ],
            'metadata'        => [
                'immutable_quote_id' => $quote->getId() + 1,
            ],
            ],
            $result
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

        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $quote->setQuoteCurrencyCode("USD");
        $product = TestUtils::createVirtualProduct();
        $this->objectsToClean[] = $product;
        $quote->addProduct($product, 1);
        TestUtils::setQuoteToSession($quote);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Billing address is missing. Please input all required fields in billing address form and try again');
        $boltHelperCart->getCartData(true, json_encode(
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
        ));
    }

        /**
         * @test
         * that getCartData returns expected cart data when checkout type is payment and quote is virtual
         *
         * @covers ::getCartData
         * @dataProvider dataProvider_sessionIdToCartMetadataSwitch
         * @throws Exception from tested method
         */
    public function getCartData_whenPaymentOnlyAndVirtualQuote_returnsCartData(
        $addSessionIdToMetadataValue,
        $metadataSessionIdAssertMethod
    ) {

        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $quote->setQuoteCurrencyCode("USD");

        $product = TestUtils::createVirtualProduct();
        $this->objectsToClean[] = $product;
        $quote->addProduct($product, 1);
        $quote->getBillingAddress()->addData($this->testAddressData);
        $quote->getShippingAddress()->addData($this->testAddressData);
        $quote->save();

        TestUtils::setQuoteToSession($quote);

        $sessionToMetadataSwitch = TestUtils::saveFeatureSwitch(
            Definitions::M2_ADD_SESSION_ID_TO_CART_METADATA,
            $addSessionIdToMetadataValue
        );
        $result = $boltHelperCart->getCartData(
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
        TestUtils::cleanupFeatureSwitch($sessionToMetadataSwitch);
        unset($result['items'][0]['image_url']);

        // check that session id is saved in metadata
        $encrypted_session_id = $result['metadata']['encrypted_session_id'] ?? null;
        static::$metadataSessionIdAssertMethod($encrypted_session_id);
        unset($result['metadata']['encrypted_session_id']);

        static::assertEquals(
            [
            'order_reference' => $quote->getId(),
            'display_id'      => '',
            'currency'        => 'USD',
            'items'           => [
                [
                    'reference'           => $product->getId(),
                    'name'                => 'Test Virtual Product',
                    'total_amount'        => 10000.0,
                    'unit_price'          => 10000,
                    'quantity'            => 1.0,
                    'sku'                 => $product->getSku(),
                    'type'                => 'digital',
                    'description'         => 'Product Description',
                    'merchant_product_id' => $product->getId(),
                ]
            ],
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
            'total_amount'    => 10000.0,
            'tax_amount'      => 0,
            'metadata'        =>
                [
                    'immutable_quote_id' => $quote->getId() + 1,
                ]
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
            ['getStore', 'getQuote', 'getOrderId']
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
            'getCalculationAddress'
            ]
        );
        $this->setUpAddressMock($this->quoteBillingAddress);
        $currentMock->expects(static::once())->method('createImmutableQuote')->with($this->quoteMock)
        ->willReturn($this->immutableQuoteMock);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($this->immutableQuoteMock)
        ->willReturn($this->quoteBillingAddress);
        $currentMock->expects(static::once())->method('getCartItems')->willReturn($getCartItemsResult);
        $currentMock->expects(static::once())->method('collectDiscounts')->willReturn($collectDiscountsResult);
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->quoteMock->expects(static::any())->method('getShippingAddress')
        ->willReturn($this->quoteShippingAddress);
        $this->immutableQuoteMock->expects(static::exactly(2))->method('isVirtual')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getBillingAddress')
        ->willReturn($this->quoteBillingAddress);
        $this->immutableQuoteMock->expects(static::any())->method('getShippingAddress')
        ->willReturn($this->getAddressMock());
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getBoltParentQuoteId')
        ->willReturn(self::PARENT_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getId')
        ->willReturn(self::IMMUTABLE_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getQuoteCurrencyCode')
        ->willReturn(self::CURRENCY_CODE);

        $storeMock = $this->createMock(\Magento\Store\Model\Store::class);
        $storeMock->method('getId')->willReturn(self::STORE_ID);
        $storeMock->method('getWebsiteId')->willReturn(1);

        $this->checkoutSession->method('getStore')->willReturn($storeMock);
        $this->deciderHelper->expects(self::once())->method('isAddSessionIdToCartMetadata')->willReturn(true);
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
     * that getCartData adds original_order_entity_id for edited orders (order id is present on session)
     *
     * @covers ::getCartData
     * @covers ::buildCartFromQuote
     */
    public function getCartData_forEditedBackendOrders_addsOriginalOrderEntityIdToMetadata()
    {
        $this->checkoutSession = $this->createPartialMock(
            \Magento\Backend\Model\Session\Quote::class,
            ['getStore', 'getQuote', 'getOrderId']
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
                'getCalculationAddress'
            ]
        );
        $this->setUpAddressMock($this->quoteBillingAddress);
        $currentMock->expects(static::once())->method('createImmutableQuote')->with($this->quoteMock)
            ->willReturn($this->immutableQuoteMock);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($this->immutableQuoteMock)
            ->willReturn($this->quoteBillingAddress);
        $currentMock->expects(static::once())->method('getCartItems')->willReturn($getCartItemsResult);
        $currentMock->expects(static::once())->method('collectDiscounts')->willReturn($collectDiscountsResult);
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->quoteMock->expects(static::any())->method('getShippingAddress')
            ->willReturn($this->quoteShippingAddress);
        $this->immutableQuoteMock->expects(static::exactly(2))->method('isVirtual')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getBillingAddress')
            ->willReturn($this->quoteBillingAddress);
        $this->immutableQuoteMock->expects(static::any())->method('getShippingAddress')
            ->willReturn($this->getAddressMock());
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getBoltParentQuoteId')
            ->willReturn(self::PARENT_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getId')
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getQuoteCurrencyCode')
            ->willReturn(self::CURRENCY_CODE);

        $storeMock = $this->createMock(\Magento\Store\Model\Store::class);
        $storeMock->method('getId')->willReturn(self::STORE_ID);
        $storeMock->method('getWebsiteId')->willReturn(1);

        $this->checkoutSession->method('getStore')->willReturn($storeMock);
        $this->checkoutSession->method('getOrderId')->willReturn(self::ORIGINAL_ORDER_ENTITY_ID);
        $this->deciderHelper->expects(self::once())->method('isAddSessionIdToCartMetadata')->willReturn(true);
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
        static::assertEquals(self::ORIGINAL_ORDER_ENTITY_ID, $result['metadata']['original_order_entity_id']);
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
            ['getStore', 'getQuote', 'getOrderId']
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
            'getCalculationAddress'
            ]
        );
        $this->setUpAddressMock($this->quoteBillingAddress);
        $currentMock->expects(static::once())->method('createImmutableQuote')->with($this->quoteMock)
        ->willReturn($this->immutableQuoteMock);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($this->immutableQuoteMock)
        ->willReturn($this->quoteBillingAddress);
        $currentMock->expects(static::once())->method('getCartItems')->willReturn($getCartItemsResult);
        $currentMock->expects(static::once())->method('collectDiscounts')->willReturn($collectDiscountsResult);
        $this->checkoutSession->expects(static::once())->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getAllVisibleItems')->willReturn(true);
        $this->quoteMock->expects(static::any())->method('getShippingAddress')
        ->willReturn($this->quoteShippingAddress);
        $this->immutableQuoteMock->expects(static::exactly(2))->method('isVirtual')->willReturn(true);
        $this->immutableQuoteMock->expects(static::once())->method('getBillingAddress')
        ->willReturn($this->quoteBillingAddress);
        $this->immutableQuoteMock->expects(static::any())->method('getShippingAddress')
        ->willReturn($this->getAddressMock());
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getBoltParentQuoteId')
        ->willReturn(self::PARENT_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getId')
        ->willReturn(self::IMMUTABLE_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getQuoteCurrencyCode')
        ->willReturn(self::CURRENCY_CODE);

        $storeMock = $this->createMock(\Magento\Store\Model\Store::class);
        $storeMock->method('getId')->willReturn(self::STORE_ID);
        $storeMock->method('getWebsiteId')->willReturn(1);

        $this->checkoutSession->method('getStore')->willReturn($storeMock);
        $this->deciderHelper->expects(self::once())->method('isAddSessionIdToCartMetadata')->willReturn(true);
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
            'getCalculationAddress'
            ]
        );
        $this->setUpAddressMock($this->quoteShippingAddress);
        $currentMock->expects(static::once())->method('createImmutableQuote')->with($this->quoteMock)
        ->willReturn($this->immutableQuoteMock);
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
        $this->deciderHelper->expects(self::once())->method('isAddSessionIdToCartMetadata')->willReturn(true);
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
            'display_id'      => '',
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
            'metadata'        => [
                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
                'encrypted_session_id' => ''
            ],
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
        $testDiscount = [
        'description' => 'Test discount',
        'amount' => 22345,
        'reference'   => self::COUPON_CODE,
        'discount_category' => 'coupon',
        'discount_type'   => 'fixed_amount',
        'type'   => 'fixed_amount',
        ];
        $collectDiscountsResult = [[$testDiscount], -10000, 0];
        $currentMock = $this->getCartDataSetUp($getCartItemsResult, $collectDiscountsResult);
        $this->immutableQuoteMock->expects(static::atLeastOnce())->method('getBoltParentQuoteId')
        ->willReturn(self::PARENT_QUOTE_ID);

        $this->quoteShippingAddress->expects(static::once())->method('setCollectShippingRates')->with(true);
        $this->quoteShippingAddress->expects(static::any())->method('getShippingMethod')
        ->willReturn('flatrate_flatrate');
        $this->quoteShippingAddress->expects(static::any())->method('setShippingMethod')->with('flatrate_flatrate');
        $this->deciderHelper->expects(self::once())->method('isAddSessionIdToCartMetadata')->willReturn(true);
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
            'display_id'      => '',
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
            'discounts'       => [$testDiscount],
            'total_amount'    => 0,
            'tax_amount'      => 0,
            'metadata'        => [
                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
                'encrypted_session_id' => ''
            ],
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

        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $quote->setQuoteCurrencyCode("USD");

        $product = TestUtils::getSimpleProduct();
        $this->objectsToClean[] = $product;
        $quote->addProduct($product, 1);

        TestUtils::setAddressToQuote($this->testAddressData, $quote, 'shipping');
        TestUtils::setAddressToQuote($this->testAddressData, $quote, 'billing');

        $quote->getShippingAddress()->setShippingMethod(null)->setCollectShippingRates(true);
        $quote->collectTotals()->save();
        TestUtils::setQuoteToSession($quote);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Shipping method is missing. Please select shipping method and try again');
        $boltHelperCart->getCartData(
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
    }

        /**
         * @test
         * that getCartData returns empty array and notifies error if shipping address is not complete
         *
         * @covers ::getCartData
         *
         * @throws Exception from tested method
         */
    public function getCartData_paymentOnlyAndShippingAddressIncomplete_returnsEmptyArrayAndNotifiesError()
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
            'getCalculationAddress'
            ]
        );
        $currentMock->expects(static::once())->method('createImmutableQuote')->with($this->quoteMock)
        ->willReturn($this->immutableQuoteMock);
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
        $this->deciderHelper->expects(self::once())->method('isAddSessionIdToCartMetadata')->willReturn(true);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Shipping address is missing. Please input all required fields in shipping address form and try again');
        $currentMock->getCartData(true, json_encode([]));
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
        $this->deciderHelper->expects(self::once())->method('isAddSessionIdToCartMetadata')->willReturn(true);
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

        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $quote->setBoltParentQuoteId(999999);
        $quote->setQuoteCurrencyCode(self::CURRENCY_CODE);
        $totalAmount = 10000;
        $diff = 0;
        $paymentOnly = false;

        list($discounts, $totalAmountResult, $diffResult) = $boltHelperCart->collectDiscounts(
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
        $currentMock = $this->getCurrentMock(['getLastImmutableQuote', 'getQuoteById', 'getSaleRuleDiscounts']);
        $shippingAddress = $this->getAddressMock();
        $quote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);
        $quote->method('getBoltParentQuoteId')->willReturn(999999);
        $currentMock->expects(static::once())->method('getQuoteById')->willReturn($quote);
        $quote->method('getTotals')->willReturn([]);
        $quote->expects(static::any())->method('getCouponCode')->willReturn(self::COUPON_CODE);
        $shippingAddress->expects(static::any())->method('getDiscountDescription')->willReturn(self::COUPON_DESCRIPTION);
        $this->discountHelper->expects(static::exactly(5))->method('getBoltDiscountType')->willReturn('fixed_amount');
        $quote->expects(static::once())->method('getUseCustomerBalance')->willReturn(false);
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getAmastyPayForEverything');
        $this->discountHelper->expects(static::never())->method('getUnirgyGiftCertBalanceByCode');
        $appliedDiscount = 10; // $
        $appliedDiscountNoCoupon = 15; // $

        $quote->method('getAppliedRuleIds')->willReturn('2,3,4,5,6');

        $rule2 = $this->getMockBuilder(DataObject::class)
        ->setMethods(['getCouponType', 'getDescription', 'getSimpleAction', 'getRuleId'])
        ->disableOriginalConstructor()
        ->getMock();
        $rule2->expects(static::once())->method('getCouponType')
        ->willReturn('SPECIFIC_COUPON');
        $rule2->expects(static::once())->method('getDescription')
        ->willReturn(self::COUPON_DESCRIPTION);
        $rule2->expects(static::once())->method('getRuleId')
            ->willReturn(2);
        $rule2->method('getSimpleAction')->willReturn('by_fixed');

        $rule3 = $this->getMockBuilder(DataObject::class)
        ->setMethods(['getCouponType', 'getDescription', 'getSimpleAction', 'getRuleId'])
        ->disableOriginalConstructor()
        ->getMock();
        $rule3->expects(static::once())->method('getCouponType')
        ->willReturn('NO_COUPON');
        $rule3->expects(static::any())->method('getDescription')
        ->willReturn('Shopping cart price rule for the cart over $10');
        $rule3->method('getSimpleAction')->willReturn('by_fixed');
        $rule3->expects(static::once())->method('getRuleId')
            ->willReturn(3);

        $rule4 = $this->getMockBuilder(DataObject::class)
        ->setMethods(['getCouponType', 'getDescription', 'getSimpleAction', 'getRuleId'])
        ->disableOriginalConstructor()
        ->getMock();
        $rule4->expects(static::once())->method('getCouponType')
        ->willReturn('');
        $rule4->expects(static::once())->method('getDescription')
        ->willReturn('');
        $rule4->method('getSimpleAction')->willReturn('by_fixed');
        $rule4->expects(static::once())->method('getRuleId')
            ->willReturn(4);

        $rule5 = $this->getMockBuilder(DataObject::class)
        ->setMethods(['getCouponType', 'getDescription', 'getSimpleAction', 'getRuleId'])
        ->disableOriginalConstructor()
        ->getMock();
        $rule5->expects(static::once())->method('getCouponType')
        ->willReturn('SPECIFIC_COUPON');
        $rule5->expects(static::once())->method('getDescription')
        ->willReturn('');
        $rule5->method('getSimpleAction')->willReturn('by_fixed');
        $rule5->expects(static::once())->method('getRuleId')
            ->willReturn(5);

        $rule6 = $this->getMockBuilder(DataObject::class)
            ->setMethods(['getCouponType', 'getDescription','getName','getSimpleAction', 'getRuleId'])
            ->disableOriginalConstructor()
            ->getMock();
        $rule6->expects(static::once())->method('getCouponType')
            ->willReturn('NO_COUPON');
        $rule6->expects(static::once())->method('getDescription')->willReturn(null);
        $rule6->expects(static::never())->method('getName');
        $rule6->method('getSimpleAction')->willReturn('by_fixed');
        $rule6->expects(static::once())->method('getRuleId')
            ->willReturn(6);

        $this->ruleRepository->expects(static::exactly(5))
        ->method('getById')
        ->withConsecutive(
            [2],
            [3],
            [4],
            [5],
            [6]
        )
        ->willReturnOnConsecutiveCalls($rule2, $rule3, $rule4, $rule5, $rule6);

        $currentMock->expects(static::once())
                ->method('getSaleRuleDiscounts')
                ->with($quote)
                ->willReturn([2 => $appliedDiscount, 3 => $appliedDiscountNoCoupon, 4 => 0, 5 => $appliedDiscount, 6 => $appliedDiscountNoCoupon]);

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
        $expectedDiscountAmountNoCoupon = 100 * $appliedDiscountNoCoupon;
        $expectedTotalAmount = $totalAmount - (2 * $expectedDiscountAmount) - 2 * $expectedDiscountAmountNoCoupon;
        $expectedDiscount = [
        [
            'rule_id' => 2,
            'description' => self::COUPON_DESCRIPTION,
            'amount'      => $expectedDiscountAmount,
            'reference'   => self::COUPON_CODE,
            'discount_category' => 'coupon',
            'discount_type'   => 'fixed_amount',
            'type'   => 'fixed_amount',
        ],
        [
            'rule_id' => 3,
            'description' => trim(__('Discount ') . 'Shopping cart price rule for the cart over $10'),
            'amount'      => $expectedDiscountAmountNoCoupon,
            'discount_category' => 'automatic_promotion',
            'discount_type'   => 'fixed_amount',
            'type'   => 'fixed_amount',
        ],
        [
            'rule_id' => 4,
            'description' => trim(__('Discount')),
            'amount'      => 0,
            'discount_category' => 'automatic_promotion',
            'discount_type'     => 'fixed_amount',
            'type'              => 'fixed_amount',
        ],
        [
            'rule_id' => 5,
            'description' => trim(__('Discount (') . self::COUPON_CODE . ')'),
            'amount'      => $expectedDiscountAmount,
            'reference'   => self::COUPON_CODE,
            'discount_category' => 'coupon',
            'discount_type'     => 'fixed_amount',
            'type'              => 'fixed_amount',
        ],
        [
            'rule_id' => 6,
            'description' => trim(__('Discount ')),
            'amount'      => $expectedDiscountAmountNoCoupon,
            'discount_category' => 'automatic_promotion',
            'discount_type'   => 'fixed_amount',
            'type'   => 'fixed_amount',
        ],
        ];
        static::assertEquals($expectedDiscount, $discounts);
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

        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $appliedDiscount = 10; // $
        $quote->setUseCustomerBalance(true);
        $quote->setCustomerBalanceAmountUsed($appliedDiscount);
        $quote->setBoltParentQuoteId(999999);
        $quote->setQuoteCurrencyCode(self::CURRENCY_CODE);
        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;
        list($discounts, $totalAmountResult, $diffResult) = $boltHelperCart->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $quote
        );
        static::assertEquals($diffResult, $diff);
        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;
        $expectedDiscount = [
        [
            'description' => 'Store Credit',
            'amount'      => $expectedDiscountAmount,
            'discount_category' => 'store_credit',
            'discount_type'   => 'fixed_amount',
            'type'   => 'fixed_amount',
        ]
        ];
        static::assertEquals($expectedDiscount, $discounts);
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
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $this->immutableQuoteMock->expects(static::once())->method('getTotals')->willReturn([]);
        $this->immutableQuoteMock->expects(static::once())->method('getQuoteCurrencyCode')
        ->willReturn(self::CURRENCY_CODE);
        $this->immutableQuoteMock->expects(static::once())->method('getUseCustomerBalance')->willReturn(true);
        $this->immutableQuoteMock->expects(static::any())->method('getCouponCode')->willReturn(false);
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
        $expectedDiscount = [
        [
            'description' => 'Store Credit',
            'amount'      => $expectedDiscountAmount,
            'discount_category' => 'store_credit',
            'discount_type'   => 'fixed_amount',
            'type'   => 'fixed_amount',
            'reference' => 'Store Credit'
        ]
        ];
        static::assertEquals($expectedDiscount, $discounts);
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

        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $this->immutableQuoteMock->expects(static::once())->method('getTotals')->willReturn([]);
        $this->immutableQuoteMock->expects(static::once())->method('getQuoteCurrencyCode')
        ->willReturn(self::CURRENCY_CODE);
        $this->immutableQuoteMock->expects(static::once())->method('getUseRewardPoints')->willReturn(true);
        $this->immutableQuoteMock->expects(static::any())->method('getCouponCode')->willReturn(false);

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
        $expectedDiscount = [
        [
            'description' => 'Reward Points',
            'amount'      => $expectedDiscountAmount,
            'discount_category' => 'store_credit',
            'discount_type'   => 'fixed_amount',
            'type'   => 'fixed_amount',
        ]
        ];
        static::assertEquals($expectedDiscount, $discounts);
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
        $totalAmount = 10000; // cents
        $diff = 0;
        $paymentOnly = true;
        $appliedDiscount = 10; // $

        $boltHelperCart = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $appliedDiscount = 10; // $
        $quote->setUseRewardPoints(true);
        $quote->setRewardCurrencyAmount($appliedDiscount);
        $quote->setBoltParentQuoteId(999999);
        $quote->setQuoteCurrencyCode(self::CURRENCY_CODE);

        list($discounts, $totalAmountResult, $diffResult) = $boltHelperCart->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $quote
        );
        static::assertEquals($diffResult, $diff);
        $expectedDiscountAmount = 100 * $appliedDiscount;
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;
        $expectedDiscount = [
        [
            'description' => 'Reward Points',
            'amount'      => $expectedDiscountAmount,
            'discount_category' => 'store_credit',
            'discount_type'   => 'fixed_amount',
            'type'   => 'fixed_amount',
        ]
        ];
        static::assertEquals($expectedDiscount, $discounts);
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
        $quote->expects(static::any())->method('getCouponCode')->willReturn(false);
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $quote->expects(static::once())->method('getUseCustomerBalance')->willReturn(false);
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(false);
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
        $expectedDiscount = [
        [
            'description' => '',
            'amount'      => $expectedDiscountAmount,
            'discount_category' => 'giftcard',
            'reference' => '12345',
            'discount_type'   => 'fixed_amount',
            'type'   => 'fixed_amount',
        ]
        ];
        static::assertEquals($expectedDiscount, $discounts);
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
        $mock = $this->getCurrentMock(['getLastImmutableQuote', 'getQuoteById', 'getSaleRuleDiscounts']);
        $shippingAddress = $this->getAddressMock();
        $quote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);
        $quote->method('getBoltParentQuoteId')->willReturn(999999);
        $mock->expects(static::once())->method('getQuoteById')->willReturn($quote);
        $quote->expects(static::once())->method('getUseCustomerBalance')->willReturn(false);
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(false);
        $giftVoucherDiscount = 5; // $
        $discountAmount = 10; // $
        $giftVoucher = "12345";
        $quote->expects(static::any())->method('getCouponCode')->willReturn($giftVoucher);
        $shippingAddress->expects(static::any())->method('getDiscountDescription')->willReturn(self::COUPON_DESCRIPTION);
        $this->discountHelper->expects(static::exactly(1))->method('getBoltDiscountType')->willReturn('fixed_amount');
        $this->quoteAddressTotal->expects(static::once())->method('getValue')->willReturn($giftVoucherDiscount);
        $this->quoteAddressTotal->expects(static::once())->method('getTitle')->willReturn("Gift Voucher");
        $quote->expects(static::any())->method('getTotals')->willReturn(
            [DiscountHelper::GIFT_VOUCHER => $this->quoteAddressTotal]
        );

        $quote->method('getAppliedRuleIds')->willReturn('2');

        $rule2 = $this->getMockBuilder(DataObject::class)
        ->setMethods(['getCouponType', 'getDescription','getSimpleAction', 'getRuleId'])
        ->disableOriginalConstructor()
        ->getMock();
        $rule2->expects(static::once())->method('getCouponType')
        ->willReturn('SPECIFIC_COUPON');
        $rule2->expects(static::once())->method('getDescription')
        ->willReturn(self::COUPON_DESCRIPTION);
        $rule2->method('getSimpleAction')->willReturn('by_fixed');
        $rule2->method('getRuleId')->willReturn(2);

        $this->ruleRepository->expects(static::once())
        ->method('getById')
        ->with(2)
        ->willReturn($rule2);

        $mock->expects(static::once())
                ->method('getSaleRuleDiscounts')
                ->with($quote)
                ->willReturn([2 => ($discountAmount),]);

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
        $expectedDiscount = [
        [
            'rule_id' => 2,
            'description' => self::COUPON_DESCRIPTION,
            'amount'      => $expectedRegularDiscountAmount,
            'reference'   => $giftVoucher,
            'discount_category' => 'coupon',
            'discount_type'   => 'fixed_amount',
            'type'   => 'fixed_amount',
        ],
        [
            'description' => 'Gift Voucher',
            'amount'      => $expectedGiftVoucherAmount,
        ]
        ];
        static::assertEquals($expectedDiscount, $discounts);
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
        $quote->expects(static::once())->method('getUseRewardPoints')->willReturn(false);
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $quote->expects(static::any())->method('getCouponCode')->willReturn(false);
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
        ->setMethods(['getId', 'getDescription', 'getTypeInstance', 'getCustomOption'])
        ->disableOriginalConstructor()
        ->getMock();

        $this->productMock->method('getCustomOption')->with('option_ids')->willReturn([]);
        $this->productMock->method('getDescription')->willReturn('Product Description');
        $this->productMock->method('getTypeInstance')->willReturn($productTypeConfigurableMock);

        $this->msrpHelper->method('canApplyMsrp')->with($this->productMock)->willReturn(true);

        $quoteItemMock = $this->getQuoteItemMock();
        $this->quoteMock->method('getAllVisibleItems')->willReturn([$quoteItemMock]);
        $this->quoteMock->method('getQuoteCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $this->quoteMock->method('getTotals')->willReturnSelf();


        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('no-image');

        $this->deciderHelper->expects(self::exactly(2))->method('isCustomizableOptionsSupport')->willReturn(true);

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
                                  ->setMethods(['getId', 'getDescription', 'getTypeInstance', 'getCustomOption'])
                                  ->disableOriginalConstructor()
                                  ->getMock();
        $this->productMock->method('getDescription')->willReturn('Product Description');
        $this->productMock->method('getTypeInstance')->willReturn($productTypeConfigurableMock);
        $this->productMock->method('getCustomOption')->with('option_ids')->willReturn([]);

        $this->msrpHelper->method('canApplyMsrp')->with($this->productMock)->willReturn(false);

        $quoteItemMock = $this->getQuoteItemMock();
        $this->quoteMock->method('getAllVisibleItems')->willReturn([$quoteItemMock]);
        $this->quoteMock->method('getQuoteCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $this->quoteMock->method('getTotals')->willReturnSelf();


        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('no-image');

        $this->configHelper->method('getProductAttributesList')->willReturn([$attributeName]);

        $this->productRepository->method('get')->with(self::PRODUCT_SKU)->willReturn($this->productMock);

        $this->deciderHelper->expects(self::exactly(2))->method('isCustomizableOptionsSupport')->willReturn(true);

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
            'getOptionByCode',
            'getProductType',
            ]
        );
        $productMock = $this->createMock(Product::class);
        $productMock->method('getId')->willReturn(self::PRODUCT_ID);
        $productMock->method('getName')->willReturn('Test Product');
        $productMock->method('getCustomOption')->with('option_ids')->willReturn([]);

        $quoteItem->method('getName')->willReturn('Test Product');
        $quoteItem->method('getSku')->willReturn(self::PRODUCT_SKU);
        $quoteItem->method('getQty')->willReturn(1);
        $quoteItem->method('getCalculationPrice')->willReturn(self::PRODUCT_PRICE);
        $quoteItem->method('getIsVirtual')->willReturn(false);
        $quoteItem->method('getProductId')->willReturn(self::PRODUCT_ID);
        $quoteItem->method('getProduct')->willReturn($productMock);
        $quoteItem->method('getOptionByCode')->with('option_ids')->willReturn([]);
        $quoteItem->method('getProductType')->willReturn('simple');
        $productMock->expects(static::once())->method('getTypeInstance')->willReturnSelf();

        $this->msrpHelper->method('canApplyMsrp')->with($productMock)->willReturn(false);

        $this->deciderHelper->expects(self::exactly(2))->method('isCustomizableOptionsSupport')->willReturn(true);

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
                                'reference'           => self::PRODUCT_ID,
                                'name'                => 'Test Product',
                                'total_amount'        => 10000,
                                'unit_price'          => 10000,
                                'quantity'            => 1.0,
                                'sku'                 => self::PRODUCT_SKU,
                                'type'                => 'physical',
                                'description'         => '',
                                'merchant_product_id' => self::PRODUCT_ID,
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
                'reference'           => 20102,
                'name'                => 'Test Product',
                'total_amount'        => 10000,
                'unit_price'          => 10000,
                'quantity'            => 1.0,
                'sku'                 => self::PRODUCT_SKU,
                'type'                => 'physical',
                'description'         => '',
                'merchant_product_id' => 20102,
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
        $this->deciderHelper->expects(self::exactly(2))->method('isCustomizableOptionsSupport')->willReturn(true);
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
        $productMock->method('getId')->willReturn(self::PRODUCT_ID);
        $productMock->method('getName')->willReturn('Test Product');
        $productMock->method('getCustomOption')->with('option_ids')->willReturn([]);

        $quoteItem->method('getName')->willReturn('Test Product');
        $quoteItem->method('getSku')->willReturn(self::PRODUCT_SKU);
        $quoteItem->method('getQty')->willReturn(1);
        $quoteItem->method('getCalculationPrice')->willReturn(self::PRODUCT_PRICE);
        $quoteItem->method('getIsVirtual')->willReturn(true);
        $quoteItem->method('getProductId')->willReturn(self::PRODUCT_ID);
        $quoteItem->method('getProduct')->willReturn($productMock);
        $productMock->expects(static::once())->method('getTypeInstance')->willReturnSelf();

        $this->msrpHelper->method('canApplyMsrp')->with($productMock)->willReturn(false);

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
                    'reference'           => 20102,
                    'name'                => 'Test Product',
                    'total_amount'        => 10000,
                    'unit_price'          => 10000,
                    'quantity'            => 1.0,
                    'sku'                 => self::PRODUCT_SKU,
                    'type'                => 'physical',
                    'description'         => '',
                    'merchant_product_id' => 20102,
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
        $this->deciderHelper->expects(self::exactly(2))->method('isCustomizableOptionsSupport')->willReturn(true);
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
        $productMock->method('getId')->willReturn(self::PRODUCT_ID);
        $productMock->method('getName')->willReturn('Test Product');
        $productMock->method('getCustomOption')->with('option_ids')->willReturn([]);

        $quoteItem->method('getName')->willReturn('Test Product');
        $quoteItem->method('getSku')->willReturn(self::PRODUCT_SKU);
        $quoteItem->method('getQty')->willReturn(1);
        $quoteItem->method('getCalculationPrice')->willReturn(self::PRODUCT_PRICE);
        $quoteItem->method('getIsVirtual')->willReturn(false);
        $quoteItem->method('getProductId')->willReturn(self::PRODUCT_ID);
        $quoteItem->method('getProduct')->willReturn($productMock);
        $productMock->expects(static::once())->method('getTypeInstance')->willReturnSelf();

        $this->msrpHelper->method('canApplyMsrp')->with($productMock)->willReturn(false);

        $this->imageHelper->method('init')
            ->withConsecutive([$productMock, 'product_small_image'], [$productMock, 'product_base_image'])
            ->willThrowException(new Exception());

        $this->appEmulation->expects(static::once())->method('stopEnvironmentEmulation');

        $this->quoteMock->method('getAllVisibleItems')->willReturn([$quoteItem]);
        $this->quoteMock->method('getQuoteCurrencyCode')->willReturn(self::CURRENCY_CODE);

        $this->giftwrapping = $this->getMockBuilder('\Magento\GiftWrapping\Model\Total\Quote\Giftwrapping')
            ->disableOriginalConstructor()
            ->setMethods(['getGwId','getGwItemsPrice','getGwCardPrice','getGwPrice','getText','getTitle','getCode','getGwItemIds'])
            ->getMock();
        ObjectManager::setInstance($this->objectManagerMock);
        $giftWrappingModel = $this->getMockBuilder('Magento\GiftWrapping\Model\Wrapping')
            ->disableOriginalConstructor()
            ->setMethods(['load','getImageUrl','getBasePrice', 'getDesign'])
            ->getMock();
        $giftWrappingModel->method('load')->willReturnSelf();
        $giftWrappingModel->method('getImageUrl')->willReturn('https://gift-wrap-image.url');
        $giftWrappingModel->method('getBasePrice')->willReturn('15');
        $giftWrappingModel->method('getDesign')->willReturn('Design');
        $this->objectManagerMock->expects(static::once())->method('create')
            ->with('Magento\GiftWrapping\Model\Wrapping')->willReturn($giftWrappingModel);
        $this->giftwrapping->method('getGwId')->willReturn(1);
        $this->giftwrapping->method('getGwItemIds')->willReturn(null);
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
                    'reference'           => 20102,
                    'name'                => 'Test Product',
                    'total_amount'        => 10000,
                    'unit_price'          => 10000,
                    'quantity'            => 1.0,
                    'sku'                 => self::PRODUCT_SKU,
                    'type'                => 'physical',
                    'description'         => '',
                    'merchant_product_id' => 20102,
                ],
                [
                    'reference' => 1,
                    'name' => 'Gift Wrapping [Design]',
                    'total_amount' => 1500,
                    'unit_price' => 1500,
                    'quantity' => 1,
                    'sku' => 'gift_id',
                    'type' => 'physical',
                    'image_url' => 'https://gift-wrap-image.url',
                ]
            ],
            $products
        );
        static::assertEquals(11500, $totalAmount);
        static::assertEquals(0, $diff);
    }

    /**
     * @test
     * that getCartItemsForOrder returns expected data and attributes
     *
     * @covers ::getCartItemsForOrder
     */
    public function getCartItemsForOrder()
    {
        $product = TestUtils::getSimpleProduct();
        $quantity = 2;

        $order = TestUtils::createDumpyOrder([], [], [TestUtils::createOrderItemByProduct($product, $quantity)]);

        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('no-image');

        list($products, $totalAmount, $diff) = $this->currentMock->getCartItemsForOrder($order, self::STORE_ID);

        static::assertCount(1, $products);
        static::assertEquals($products[0]['reference'], $product->getId());
        static::assertEquals($products[0]['name'], $product->getName());
        static::assertEquals($products[0]['unit_price'], CurrencyUtils::toMinor($product->getPrice(), self::CURRENCY_CODE));
        static::assertEquals($products[0]['total_amount'], CurrencyUtils::toMinor($product->getPrice() * $quantity, self::CURRENCY_CODE));
        static::assertEquals($products[0]['quantity'], $quantity);
        static::assertEquals($products[0]['sku'], $product->getSku());

        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * that getCartItemsForOrder returns expected data and attributes
     *
     * @covers ::getCartItemsForOrder
     */
    public function getCartItemsForOrder_WithDeletedProduct()
    {
        $product = TestUtils::createSimpleProduct();
        $quantity = 2;
        $order = TestUtils::createDumpyOrder([], [], [TestUtils::createOrderItemByProduct($product, $quantity)]);
        $product->delete();
        list($products, $totalAmount, $diff) = $this->currentMock->getCartItemsForOrder($order, self::STORE_ID);
        static::assertCount(0, $products[0]);
        TestUtils::cleanupSharedFixtures([$order]);
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
        $quoteItem->expects(self::once())->method('getProduct')->willReturn($parentProductMock);
        $quoteItem->method('getProductType')->willReturn(\Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE);


        $childProductMock = $this->createPartialMock(Product::class, ['getName','getThumbnail']);
        $childProductMock->method('getName')->willReturn('Child Product Name');
        $childProductMock->method('getThumbnail')->willReturn($childThumbnail);
        $quoteItemOption = $this->createPartialMock(\Magento\Quote\Model\Quote\Item\Option::class, ['getProduct']);
        $quoteItemOption->method('getProduct')->willReturn($childProductMock);
        $quoteItem->method('getOptionByCode')->with('simple_product')->willReturn($quoteItemOption);


        self::assertEquals($expectedProductName, $this->currentMock->getProductToGetImageForQuoteItem($quoteItem)->getName());
    }

    public function dataProvider_getProductToGetImageForQuoteItem_withConfigurableItem()
    {
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
        $quoteItem->expects(self::once())->method('getProduct')->willReturn($productMock);
        $quoteItem->method('getProductType')->willReturn(\Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE);

        $groupedProductMock = $this->createPartialMock(Product::class, ['getName','getThumbnail']);
        $groupedProductMock->method('getName')->willReturn('Grouped Product Name');

        $quoteItemOption = $this->createPartialMock(\Magento\Quote\Model\Quote\Item\Option::class, ['getProduct']);
        $quoteItemOption->method('getProduct')->willReturn($groupedProductMock);
        $quoteItem->method('getOptionByCode')->with('product_type')->willReturn($quoteItemOption);


        self::assertEquals($expectedProductName, $this->currentMock->getProductToGetImageForQuoteItem($quoteItem)->getName());
    }

    public function dataProvider_getProductToGetImageForQuoteItem_withGroupedItem()
    {
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
        'display_id'      => '',
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
        'metadata'        => [
            'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
        ],
        ];

        $this->quoteManagement->expects(static::once())->method('createEmptyCart')
        ->willReturn(self::IMMUTABLE_QUOTE_ID);
        $this->quoteFactory->method('create')->withAnyParameters()->willReturnSelf();
        $this->quoteFactory->method('load')->with(self::IMMUTABLE_QUOTE_ID)->willReturn($this->quoteMock);

        $this->productRepository->expects(static::once())->method('getById')->with(self::PRODUCT_ID)
        ->willReturn($this->productMock);

        $currentMock = $this->getCurrentMock(['getCartData', 'isBoltOrderCachingEnabled']);
        $currentMock->method('isBoltOrderCachingEnabled')->willReturn(false);
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
            'order_reference' => self::QUOTE_ID,
            'display_id'      => '',
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
                    'options'      => json_encode(
                        ['storeId' => self::STORE_ID, 'form_key' => 'Ai6JseqGStjryljF']
                    ),
                ],
            ],
            'discounts'       => [],
            'total_amount'    => self::PRODUCT_PRICE,
            'tax_amount'      => 0,
            'metadata'        => [
                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
            ],
        ];

        $cartMock = $this->getCurrentMock(['getCartData', 'isBoltOrderCachingEnabled']);
        $cartMock->expects(static::once())->method('isBoltOrderCachingEnabled')->willReturn(false);
        $cartMock->expects(static::once())->method('getCartData')->with(false, '', $this->quoteMock)
            ->willReturn($expectedCartData);
        $this->quoteManagement->expects(static::once())->method('createEmptyCart')
            ->willReturn(self::QUOTE_ID);
        $this->quoteFactory->method('create')->withAnyParameters()->willReturnSelf();
        $this->quoteFactory->method('load')->with(self::QUOTE_ID)->willReturn($this->quoteMock);
        $this->productRepository->expects(static::once())->method('getById')->with(self::PRODUCT_ID)
            ->willReturn($this->productMock);
        $this->quoteMock->expects(static::once())->method('setIsActive')->with(false);

        $this->store->expects(static::once())->method('setCurrentCurrencyCode')->with(self::CURRENCY_CODE);

        static::assertEquals($expectedCartData, $cartMock->createCartByRequest($request));
    }


    /**
     * @test
     * that that createCartByRequest creates cart with grouped product's children and returns expected cart data using
     * @see \Bolt\Boltpay\Helper\Cart::getCartData
     *
     * @covers ::createCartByRequest
     *
     * @throws Exception from tested method
     */
    public function createCartByRequest_withGroupedProductChildren_returnsExpectedCartData()
    {
        $request = [
            'type'     => 'cart.create',
            'items'    =>
                [
                    [
                        'reference'    => CartTest::PRODUCT_ID,
                        'name'         => 'Affirm Water Bottle 1',
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
                    ],
                    [
                        'reference'    => CartTest::PRODUCT_ID,
                        'name'         => 'Affirm Water Bottle 1',
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
            'order_reference' => self::QUOTE_ID,
            'display_id'      => self::ORDER_INCREMENT_ID . ' / ' . self::IMMUTABLE_QUOTE_ID,
            'currency'        => self::CURRENCY_CODE,
            'items'           => [
                [
                    'reference'    => self::PRODUCT_ID,
                    'name'         => 'Affirm Water Bottle 1',
                    'total_amount' => self::PRODUCT_PRICE,
                    'unit_price'   => self::PRODUCT_PRICE,
                    'quantity'     => 1,
                    'sku'          => self::PRODUCT_SKU,
                    'type'         => 'physical',
                    'description'  => 'Product description',
                ],
                [
                    'reference'    => self::PRODUCT_ID,
                    'name'         => 'Affirm Water Bottle 2',
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
        $this->productRepository->expects(static::exactly(2))->method('getById')->with(self::PRODUCT_ID)
            ->willReturn($this->productMock);
        $this->quoteMock->expects(static::once())->method('setIsActive')->with(false);

        $this->store->expects(static::once())->method('setCurrentCurrencyCode')->with(CartTest::CURRENCY_CODE);

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
        'order_reference' => self::QUOTE_ID,
        'display_id'      => self::ORDER_INCREMENT_ID,
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
        'metadata'        => [
            'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
        ],
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
        $this->quoteMock->expects(static::once())->method('setIsActive')->with(false);

        $this->store->expects(static::once())->method('setCurrentCurrencyCode')->with(self::CURRENCY_CODE);

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
        $this->quoteMock->expects(static::once())->method('setIsActive')->with(false);
        $currentMock->expects(static::once())->method('getCartData')->with(false, '', $this->quoteMock)
        ->willReturn($expectedCartData);

        $request['metadata']['encrypted_user_id'] = json_encode($payload + ['signature' => 'correct_signature']);

        $customer = $this->createMock(CustomerInterface::class);
        $this->customerRepository->method('getById')->willReturn($customer);
        $this->quoteMock->expects(static::once())->method('assignCustomer')->with($customer);
        $this->quoteMock->expects(static::once())->method('setCustomerIsGuest')->with(0);

        $this->store->expects(static::once())->method('setCurrentCurrencyCode')->with(CartTest::CURRENCY_CODE);

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

        $this->store->expects(static::once())->method('setCurrentCurrencyCode')->with(CartTest::CURRENCY_CODE);

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

        $this->store->expects(static::once())->method('setCurrentCurrencyCode')->with(CartTest::CURRENCY_CODE);

        static::assertEquals($expectedCartData, $currentMock->createCartByRequest($request));
    }

    /**
     * @test
     * that createCartByRequest returns cart frcache if Bolt order caching is enabled and a cart is found by identifier
     *
     * @covers ::createCartByRequest
     */
    public function createCartByRequest_withOrderCachingAndCartInCache_returnsCartFromCache()
    {

        $request = [
            'type'     => 'cart.create',
            'items'    => [
                [
                    'reference'    => CartTest::PRODUCT_ID,
                    'name'         => 'Product name',
                    'description'  => null,
                    'options'      => json_encode(
                        ['storeId' => CartTest::STORE_ID, 'form_key' => 'Ai6JseqGStjryljF']
                    ),
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
        $this->hookHelper->method('verifySignature')->willReturn(true);

        $cartData = [
            'order_reference' => self::IMMUTABLE_QUOTE_ID,
            'display_id'      => '',
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
            'metadata'        => [
                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
            ],
        ];
        $currentMock = $this->getCurrentMock(
            ['createCart', 'isBoltOrderCachingEnabled', 'getCartCacheIdentifier', 'loadFromCache','getOrderByQuoteId']
        );
        $currentMock->expects(static::once())->method('isBoltOrderCachingEnabled')->with(self::STORE_ID)
            ->willReturn(true);
        $currentMock->expects(static::once())->method('getOrderByQuoteId')
            ->willReturn(false);
        $currentMock->expects(static::once())->method('getCartCacheIdentifier')->with($request)
            ->willReturn(self::CACHE_IDENTIFIER);
        $currentMock->expects(static::once())->method('loadFromCache')->with(self::CACHE_IDENTIFIER)
            ->willReturn($cartData);
        $currentMock->expects(static::never())->method('createCart');

        static::assertEquals($cartData, $currentMock->createCartByRequest($request));
    }

    /**
     * @test
     * that createCartByRequest saves cart to cache if Bolt order caching is enabled
     *
     * @covers ::createCartByRequest
     */
    public function createCartByRequest_withOrderCaching_savesCartToCache()
    {

        $request = [
            'type'     => 'cart.create',
            'items'    => [
                [
                    'reference'    => CartTest::PRODUCT_ID,
                    'name'         => 'Product name',
                    'description'  => null,
                    'options'      => json_encode(
                        ['storeId' => CartTest::STORE_ID, 'form_key' => 'Ai6JseqGStjryljF']
                    ),
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
        $this->hookHelper->method('verifySignature')->willReturn(true);

        $cartData = [
            'order_reference' => self::IMMUTABLE_QUOTE_ID,
            'display_id'      => '',
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
            'metadata'        => [
                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
            ],
        ];
        $currentMock = $this->getCurrentMock(
            ['createCart', 'isBoltOrderCachingEnabled', 'getCartCacheIdentifier', 'loadFromCache', 'saveToCache']
        );
        $currentMock->expects(static::once())->method('isBoltOrderCachingEnabled')->with(self::STORE_ID)
            ->willReturn(true);
        $currentMock->expects(static::once())->method('getCartCacheIdentifier')->with($request)
            ->willReturn(self::CACHE_IDENTIFIER);
        $currentMock->expects(static::once())->method('loadFromCache')->with(self::CACHE_IDENTIFIER)->willReturn(false);
        $currentMock->expects(static::once())->method('createCart')->with($request['items'], $request['metadata'])
            ->willReturn($cartData);
        $currentMock->expects(static::once())->method('saveToCache')->with(
            $cartData,
            self::CACHE_IDENTIFIER,
            [\Bolt\Boltpay\Helper\Cart::BOLT_ORDER_TAG, \Bolt\Boltpay\Helper\Cart::BOLT_ORDER_TAG . '_' . $cartData['order_reference']],
            \Bolt\Boltpay\Helper\Cart::BOLT_ORDER_CACHE_LIFETIME
        );

        static::assertEquals($cartData, $currentMock->createCartByRequest($request));
    }

    /**
     * @test
     * that createCart creates cart with simple product and returns expected cart data using
     * @see \Bolt\Boltpay\Helper\Cart::getCartData
     *
     * @covers ::createCart
     *
     * @throws Exception from tested method
     */
    public function createCart_withGuestUserAndSimpleProduct_returnsExpectedCartData()
    {
        $items = [
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
        ];

        $expectedCartData = [
            'order_reference' => self::QUOTE_ID,
            'display_id'      => '',
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
            'metadata'        => [
                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
            ],
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
        $this->quoteMock->expects(static::once())->method('setIsActive')->with(false);
        $this->quoteResource->expects(static::once())->method('save')->with($this->quoteMock);
        $this->quoteRepository->expects(static::exactly(2))->method('save')->with($this->quoteMock);

        static::assertEquals($expectedCartData, $cartMock->createCart($items));
    }


    /**
     * @test
     * that that createCart creates cart with grouped product's children and returns expected cart data using
     * @see \Bolt\Boltpay\Helper\Cart::getCartData
     *
     * @covers ::createCart
     *
     * @throws Exception from tested method
     */
    public function createCart_withGroupedProductChildren_returnsExpectedCartData()
    {
        $items = [
            [
                'reference'    => CartTest::PRODUCT_ID,
                'name'         => 'Affirm Water Bottle 1',
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
            ],
            [
                'reference'    => CartTest::PRODUCT_ID,
                'name'         => 'Affirm Water Bottle 1',
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
        ];

        $expectedCartData = [
            'order_reference' => self::QUOTE_ID,
            'display_id'      => self::ORDER_INCREMENT_ID . ' / ' . self::IMMUTABLE_QUOTE_ID,
            'currency'        => self::CURRENCY_CODE,
            'items'           => [
                [
                    'reference'    => self::PRODUCT_ID,
                    'name'         => 'Affirm Water Bottle 1',
                    'total_amount' => self::PRODUCT_PRICE,
                    'unit_price'   => self::PRODUCT_PRICE,
                    'quantity'     => 1,
                    'sku'          => self::PRODUCT_SKU,
                    'type'         => 'physical',
                    'description'  => 'Product description',
                ],
                [
                    'reference'    => self::PRODUCT_ID,
                    'name'         => 'Affirm Water Bottle 2',
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
        $this->productRepository->expects(static::exactly(2))->method('getById')->with(self::PRODUCT_ID)
            ->willReturn($this->productMock);
        $this->quoteMock->expects(static::once())->method('setIsActive')->with(false);
        $this->quoteResource->expects(static::once())->method('save')->with($this->quoteMock);
        $this->quoteRepository->expects(static::exactly(2))->method('save')->with($this->quoteMock);

        static::assertEquals($expectedCartData, $cartMock->createCart($items));
    }

    /**
     * @test
     * that createCart creates cart with configurable product and returns expected cart data using
     * @see \Bolt\Boltpay\Helper\Cart::getCartData
     *
     * @covers ::createCart
     *
     * @throws Exception from tested method
     */
    public function createCart_withGuestUserAndConfigurableProduct_returnsExpectedCartData()
    {
        $items = [
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
        ];

        $items[0]['options'] = json_encode(
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
            'order_reference' => self::QUOTE_ID,
            'display_id'      => self::ORDER_INCREMENT_ID,
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
            'metadata'        => [
                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
            ],
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
        $this->quoteMock->expects(static::once())->method('setIsActive')->with(false);
        $this->quoteResource->expects(static::once())->method('save')->with($this->quoteMock);
        $this->quoteRepository->expects(static::exactly(2))->method('save')->with($this->quoteMock);

        static::assertEquals($expectedCartData, $cartMock->createCart($items));
    }

    /**
     * @test
     * that createCart assigns customer to quote by calling
     * @see \Bolt\Boltpay\Helper\Cart::assignQuoteCustomerByEncryptedUserId
     *
     * @covers ::createCart
     *
     * @throws Exception from tested method
     */
    public function createCart_withEncryptedUserIdInRequest_assignsCustomerToQuote()
    {
        list($request, $payload, $expectedCartData, $currentMock) = $this->createCartByRequestSetUp();
        $this->quoteMock->expects(static::once())->method('setIsActive')->with(false);
        $currentMock->expects(static::once())->method('getCartData')->with(false, '', $this->quoteMock)
            ->willReturn($expectedCartData);

        $items = $request['items'];
        $metadata = [
            'encrypted_user_id' =>  json_encode($payload + ['signature' => 'correct_signature']),
        ];

        $customer = $this->createMock(CustomerInterface::class);
        $this->customerRepository->method('getById')->willReturn($customer);
        $this->quoteMock->expects(static::once())->method('assignCustomer')->with($customer);
        $this->quoteMock->expects(static::once())->method('setCustomerIsGuest')->with(0);

        static::assertEquals($expectedCartData, $currentMock->createCart($items, $metadata));
    }

    /**
     * @test
     * that createCart throws BoltException with if a stock exception occurs when adding product to cart
     * @see \Bolt\Boltpay\Model\ErrorResponse::ERR_PPC_OUT_OF_STOCK used as exception code
     *
     * @covers ::createCart
     *
     * @throws Exception from tested method
     */
    public function createCart_withOutOfStockException_throwsBoltExceptionWithOutOfStockCode()
    {
        list($request, $payload, $expectedCartData, $currentMock) = $this->createCartByRequestSetUp();
        $items = $request['items'];
        $this->quoteMock->expects(static::once())->method('addProduct')
            ->with($this->productMock, new DataObject(['qty' => 1]))
            ->willThrowException(new Exception('Product that you are trying to add is not available.'));

        $this->expectException(BoltException::class);
        $this->expectExceptionCode(BoltErrorResponse::ERR_PPC_OUT_OF_STOCK);
        $this->expectExceptionMessage('Product that you are trying to add is not available.');

        static::assertEquals($expectedCartData, $currentMock->createCart($items));
    }

    /**
     * @test
     * that createCart throws BoltException with if a non-stock exception occurs when adding product to cart
     * @see \Bolt\Boltpay\Model\ErrorResponse::ERR_PPC_INVALID_QUANTITY used as exception code
     *
     * @covers ::createCart
     *
     * @throws Exception from tested method
     */
    public function createCart_withExceptionWhenAddingProductToCart_throwsBoltException()
    {
        list($request, $payload, $expectedCartData, $currentMock) = $this->createCartByRequestSetUp();

        $items = $request['items'];
        $this->quoteMock->expects(static::once())->method('addProduct')
            ->with($this->productMock, new DataObject(['qty' => 1]))
            ->willThrowException(new Exception('Product unavailable.'));

        $this->expectException(BoltException::class);
        $this->expectExceptionCode(BoltErrorResponse::ERR_PPC_INVALID_QUANTITY);
        $this->expectExceptionMessage('The requested qty is not available');

        static::assertEquals($expectedCartData, $currentMock->createCart($items));
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
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $result = $cartHelper->getHints(null, 'admin');
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
        $this->deciderHelper->expects(self::exactly(2))->method('ifShouldDisablePrefillAddressForLoggedInCustomer')->willReturn(false);
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

        if (!$ifShouldDisablePrefillAddressForLoggedInCustomer) {
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
        }

        $hints = $this->getCurrentMock()->getHints(null, 'product');

        if (!$ifShouldDisablePrefillAddressForLoggedInCustomer) {
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
            static::assertEquals($signedMerchantUserId, $hints['signed_merchant_user_id']);
        }

        $encryptedUserId = json_decode($hints['metadata']['encrypted_user_id'], true);
        self::assertEquals(self::CUSTOMER_ID, $encryptedUserId['user_id']);
    }

    public function provider_getHints_withNonVirtualQuoteAndCustomerLoggedIn_willReturnCustomerShippingAddressHints()
    {
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
        $this->deciderHelper->expects(self::exactly(2))->method('ifShouldDisablePrefillAddressForLoggedInCustomer')->willReturn(false);
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
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        $product = TestUtils::createVirtualProduct();
        $quote->addProduct($product, 1);
        TestUtils::setAddressToQuote($this->testAddressData, $quote, 'billing');
        $quote->save();
        $hints = $cartHelper->getHints($quoteId, 'multipage');
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
        TestUtils::cleanupSharedFixtures([$product]);
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
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        TestUtils::setAddressToQuote($this->testAddressData, $quote, 'shipping');
        $quote->save();
        $hints = $cartHelper->getHints($quoteId, 'multipage');
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
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = TestUtils::createQuote();
        $testAddressData = [
            'company'         => "",
            'country'         => "United States",
            'country_code'    => "US",
            'email'           => "test@bolt.com",
            'first_name'      => "IntegrationBolt",
            'last_name'       => "BoltTest",
            'locality'        => "New York",
            'phone'           => "8005550111",
            'postal_code'     => "10011",
            'region'          => "New York",
            'street_address1' => "228 7th Avenue",
            'street_address2' => "228 7th Avenue 2",
        ];
        TestUtils::setAddressToQuote($testAddressData, $quote, 'shipping');
        $quote->save();
        TestUtils::setQuoteToSession($quote);
        $hints = $cartHelper->getHints();
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
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = TestUtils::createQuote();
        $testAddressData = [
            'company'         => "",
            'country'         => "United States",
            'country_code'    => "US",
            'email'           => "na@bolt.com",
            'first_name'      => "IntegrationBolt",
            'last_name'       => "BoltTest",
            'locality'        => "New York",
            'phone'           => "8005550111",
            'postal_code'     => "10011",
            'region'          => "New York",
            'street_address1' => "228 7th Avenue",
            'street_address2' => "228 7th Avenue 2",
        ];
        TestUtils::setAddressToQuote($testAddressData, $quote, 'shipping');
        $quote->save();
        TestUtils::setQuoteToSession($quote);
        $hints = $cartHelper->getHints();
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
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = TestUtils::createQuote();
        $testAddressData = [
            'company'         => "",
            'country'         => "United States",
            'country_code'    => "US",
            'email'           => "test@bolt.com",
            'first_name'      => "IntegrationBolt",
            'last_name'       => "BoltTest",
            'locality'        => "New York",
            'phone'           => "8005550111",
            'postal_code'     => "10011",
            'region'          => "New York",
            'street_address1' => "tbd",
            'street_address2' => "",
        ];
        TestUtils::setAddressToQuote($testAddressData, $quote, 'shipping');
        $quote->save();
        TestUtils::setQuoteToSession($quote);
        $hints = $cartHelper->getHints();
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

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = TestUtils::createQuote();
        $this->expectExceptionMessage("Incorrect encrypted_user_id");
        $this->expectExceptionCode(6306);
        $this->expectException(\Magento\Framework\Webapi\Exception::class);
        TestHelper::invokeMethod(
            $cartHelper,
            'assignQuoteCustomerByEncryptedUserId',
            [$quote, $encryptedUserId]
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

        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        $quote = TestUtils::createQuote();
        $this->expectExceptionMessage("Incorrect signature");
        $this->expectException(\Magento\Framework\Webapi\Exception::class);
        $this->expectExceptionCode(6306);
        $payload = ['user_id' => 1, 'timestamp' => time() - 3600 - 1];
        $encryptedUserId = json_encode($payload + ['signature' => 'incorrect_signature']);
        TestHelper::invokeMethod(
            $cartHelper,
            'assignQuoteCustomerByEncryptedUserId',
            [$quote, $encryptedUserId]
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
        $this->quoteMock->expects(self::once())->method('setCustomerIsGuest')->with(0);
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
                'display_id'      => '100050001',
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
                'metadata'        =>
                    [
                        'immutable_quote_id' => self::QUOTE_ID,
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

    /**
     * @test
     * that getCustomerByEmail returns customer model by email if it exists
     *
     * @covers ::getCustomerByEmail
     */
    public function getCustomerByEmail_withExistingCustomerEmail_returnsCustomerModel()
    {
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);

        $store = Bootstrap::getObjectManager()->get(\Magento\Store\Model\StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();

        $websiteRepository = Bootstrap::getObjectManager()->get(\Magento\Store\Api\WebsiteRepositoryInterface::class);
        $websiteId = $websiteRepository->get('base')->getId();
        TestUtils::createCustomer(
            $websiteId,
            $storeId,
            [
                "street_address1" => "street",
                "street_address2" => "",
                "locality"        => "Los Angeles",
                "region"          => "California",
                'region_code'     => 'CA',
                'region_id'       => '12',
                "postal_code"     => "11111",
                "country_code"    => "US",
                "country"         => "United States",
                "name"            => "lastname firstname",
                "first_name"      => "firstname",
                "last_name"       => "lastname",
                "phone_number"    => "11111111",
                "email_address"   => self::EMAIL_ADDRESS,
            ]
        );
        static::assertEquals(
            self::EMAIL_ADDRESS,
            $cartHelper->getCustomerByEmail(self::EMAIL_ADDRESS, $websiteId)->getEmail()
        );
    }

    /**
     * @test
     * that getCustomerByEmail returns false if unable to retrieve customer by email address
     *
     * @covers ::getCustomerByEmail
     */
    public function getCustomerByEmail_withExceptionOnGettingTheCustomer_returnsFalse()
    {
        $cartHelper = Bootstrap::getObjectManager()->create(BoltHelperCart::class);
        static::assertFalse(
            $cartHelper->getCustomerByEmail('test@gmail.com', self::WEBSITE_ID)
        );
    }

    /**
     * @test
     * that convertExternalFieldsToCacheIdentifier returns cache identifier affected by the customer group id
     *
     * @covers ::convertExternalFieldsToCacheIdentifier
     */
    public function convertExternalFieldsToCacheIdentifier_always_appendsCustomerGroupIdToCacheIdentifier()
    {
        $groupId = random_int(1,10000);
        $this->immutableQuoteMock->expects(static::once())->method('getCustomerGroupId')->willReturn($groupId);
        $result = TestHelper::invokeMethod(
            $this->currentMock,
            'convertExternalFieldsToCacheIdentifier',
            [$this->immutableQuoteMock]
        );
        static::assertStringContainsString((string)$groupId, $result);
    }

    /**
     * @test
     * that convertExternalFieldsToCacheIdentifier returns cache identifier affected by the customer id
     *
     * @covers ::convertExternalFieldsToCacheIdentifier
     */
    public function convertExternalFieldsToCacheIdentifier_always_appendsCustomerIdToCacheIdentifier()
    {
        $customerId = random_int(1,10000);
        $this->immutableQuoteMock->expects(static::once())->method('getCustomerId')->willReturn($customerId);
        $result = TestHelper::invokeMethod(
            $this->currentMock,
            'convertExternalFieldsToCacheIdentifier',
            [$this->immutableQuoteMock]
        );
        static::assertStringContainsString((string)$customerId, $result);
    }

        /**
         * @test
         * that getSaleRuleDiscounts properly handles free shipping promotion (with coupon code)
         *
         * @covers ::getSaleRuleDiscounts
         *
         * @throws NoSuchEntityException from tested method
         */
    public function getSaleRuleDiscounts_withFreeShippingCouponSpecificCoupon_collectsCoupon()
    {
        $currentMock = $this->getCurrentMock(['getCalculationAddress', 'isCollectDiscountsByPlugin']);

        $shippingAddress = $this->getAddressMock();
        $shippingAddress->expects(static::once())->method('getAppliedRuleIds')->willReturn('2');

        $quote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($quote)->willReturn($shippingAddress);
        $currentMock->expects(static::once())->method('isCollectDiscountsByPlugin')->with($quote)->willReturn(true);

        $checkoutSession = $this->createPartialMock(
            CheckoutSession::class,
            ['getBoltCollectSaleRuleDiscounts']
        );
        $checkoutSession->expects(static::once())
            ->method('getBoltCollectSaleRuleDiscounts')
            ->willReturn([]);
        $this->sessionHelper->expects(static::once())
            ->method('getCheckoutSession')
            ->willReturn($checkoutSession);

        $rule2 = $this->getMockBuilder(DataObject::class)
            ->setMethods(['getCouponType'])
            ->disableOriginalConstructor()
            ->getMock();
        $rule2->expects(static::once())->method('getCouponType')
            ->willReturn('SPECIFIC_COUPON');

        $this->ruleRepository->expects(static::once())
            ->method('getById')
            ->with(2)
            ->willReturn($rule2);

        $ruleDiscountDetails = $currentMock->getSaleRuleDiscounts($quote);
        static::assertEquals([2 => 0], $ruleDiscountDetails);
    }

        /**
         * @test
         * that getSaleRuleDiscounts properly handles free shipping promotion (no coupon code)
         *
         * @covers ::getSaleRuleDiscounts
         *
         * @throws NoSuchEntityException from tested method
         */
    public function getSaleRuleDiscounts_withFreeShippingCouponNoCoupon_collectsCoupon()
    {
        $currentMock = $this->getCurrentMock(['getCalculationAddress', 'isCollectDiscountsByPlugin']);

        $shippingAddress = $this->getAddressMock();
        $shippingAddress->expects(static::once())->method('getAppliedRuleIds')->willReturn('2');

        $quote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);
        $currentMock->expects(static::once())->method('getCalculationAddress')->with($quote)->willReturn($shippingAddress);
        $currentMock->expects(static::once())->method('isCollectDiscountsByPlugin')->with($quote)->willReturn(true);

        $checkoutSession = $this->createPartialMock(
            CheckoutSession::class,
            ['getBoltCollectSaleRuleDiscounts']
        );
        $checkoutSession->expects(static::once())
            ->method('getBoltCollectSaleRuleDiscounts')
            ->willReturn([]);
        $this->sessionHelper->expects(static::once())
            ->method('getCheckoutSession')
            ->willReturn($checkoutSession);

        $rule2 = $this->getMockBuilder(DataObject::class)
            ->setMethods(['getCouponType'])
            ->disableOriginalConstructor()
            ->getMock();
        $rule2->expects(static::once())->method('getCouponType')
            ->willReturn('NO_COUPON');

        $this->ruleRepository->expects(static::once())
            ->method('getById')
            ->with(2)
            ->willReturn($rule2);

        $ruleDiscountDetails = $currentMock->getSaleRuleDiscounts($quote);
        static::assertEquals([], $ruleDiscountDetails);
    }

    /**
     * @test
     *
     * @covers ::getSkuFromQuoteItem
     */
    public function getSkuFromQuoteItem_withBundleItem()
    {
        $quoteItem = $this->createPartialMock(
            Item::class,
            [
                'getProduct',
                'getProductType',
                'getSku'
            ]
        );

        $productMock = $this->createPartialMock(Product::class, ['getData','setData','getTypeInstance']);
        $productMock->method('getData')->with('sku_type')->willReturn(1);
        $productMock->expects(static::exactly(2))->method('setData')->withConsecutive(
            ['sku_type', 0],
            ['sku_type', 1]
        );
        $productTypeBundleMock = $this->getMockBuilder(\Magento\Bundle\Model\Product\Type::class)
                                            ->setMethods(['getSku'])
                                            ->disableOriginalConstructor()
                                            ->getMock();
        $productTypeBundleMock->method('getSku')->with($productMock)->willReturn('test-bundle-sku');
        $productMock->method('getTypeInstance')->willReturn($productTypeBundleMock);
        $quoteItem->expects(self::once())->method('getProduct')->willReturn($productMock);
        $quoteItem->method('getProductType')->willReturn(\Magento\Catalog\Model\Product\Type::TYPE_BUNDLE);
        $quoteItem->expects(self::never())->method('getSku')->willReturn($productMock);

        $this->assertEquals('test-bundle-sku', $this->currentMock->getSkuFromQuoteItem($quoteItem));
    }
}
