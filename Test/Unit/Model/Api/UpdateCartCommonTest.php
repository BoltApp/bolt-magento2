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
use Magento\Quote\Model\Quote;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Quote\Api\CartRepositoryInterface as CartRepository;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Quote\Model\Quote\Address;
use Magento\SalesRule\Model\RuleRepository;
use Magento\SalesRule\Model\Coupon;
use Magento\SalesRule\Model\ResourceModel\Coupon\UsageFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\SalesRule\Model\Rule\CustomerFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockStateInterface;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Model\Api\UpdateCartContext;
use Bolt\Boltpay\Model\Api\UpdateCartCommon;
use Bolt\Boltpay\Helper\ArrayHelper;
use Bolt\Boltpay\Test\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\Framework\App\CacheInterface;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;

/**
 * Class UpdateCartCommonTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\UpdateCartCommon
 */
class UpdateCartCommonTest extends TestCase
{
    const PARENT_QUOTE_ID = 1000;
    const IMMUTABLE_QUOTE_ID = 1001;
    const STORE_ID = 1;
    const CURRENCY_CODE = 'USD';

    /**
     * @var Request|MockObject
     */
    private $request;

    /**
     * @var Response|MockObject
     */
    private $response;

    /**
     * @var LogHelper|MockObject
     */
    private $logHelper;

    /**
     * @var Bugsnag|MockObject
     */
    private $bugsnag;

    /**
     * @var CartHelper|MockObject
     */
    private $cartHelper;

    /**
     * @var HookHelper|MockObject
     */
    private $hookHelper;

    /**
     * @var BoltErrorResponse|MockObject
     */
    private $errorResponse;

    /**
     * @var RegionModel|MockObject
     */
    private $regionModel;

    /**
     * @var OrderHelper|MockObject
     */
    private $orderHelper;

    /**
     * @var RuleRepository|MockObject
     */
    private $ruleRepository;

    /**
     * @var UsageFactory|MockObject
     */
    private $usageFactory;

    /**
     * @var DataObjectFactory|MockObject
     */
    private $objectFactory;

    /**
     * @var TimezoneInterface|MockObject
     */
    private $timezone;

    /**
     * @var CustomerFactory|MockObject
     */
    private $customerFactory;

    /**
     * @var ConfigHelper|MockObject
     */
    private $configHelper;

    /**
     * @var CheckoutSession|MockObject
     */
    private $checkoutSession;

    /**
     * @var DiscountHelper|MockObject
     */
    private $discountHelper;

    /**
     * @var TotalsCollector|MockObject
     */
    private $totalsCollector;
    
    /**
     * @var SessionHelper|MockObject
     */
    private $sessionHelper;
    
    /**
     * @var UpdateCartContext|MockObject
     */
    private $updateCartContext;
    
    /**
     * @var UpdateCartCommon|MockObject
     */
    private $currentMock;
    
    /**
     * @var CacheInterface
     */
    private $cache;
    
    /**
     * @var EventsForThirdPartyModules
     */
    protected $eventsForThirdPartyModules;
    
    /**
     * @var CartRepository
     */
    protected $cartRepository;
    
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepositoryInterface;
    
    /**
     * @var StockStateInterface
     */
    protected $stockStateInterface;


    protected function setUp()
    {
        $this->request = $this->createMock(Request::class);
        $this->response = $this->createMock(Response::class);
        $this->hookHelper = $this->createMock(HookHelper::class);
        $this->errorResponse = $this->createMock(BoltErrorResponse::class);
        $this->logHelper = $this->createMock(LogHelper::class);       
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->regionModel = $this->createMock(RegionModel::class);
        $this->orderHelper = $this->createMock(OrderHelper::class);
        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->ruleRepository = $this->createMock(RuleRepository::class);
        $this->usageFactory = $this->createMock(UsageFactory::class);
        $this->objectFactory = $this->createMock(DataObjectFactory::class);
        $this->timezone = $this->createMock(TimezoneInterface::class);
        $this->customerFactory = $this->createMock(CustomerFactory::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->discountHelper = $this->createMock(DiscountHelper::class);
        $this->totalsCollector = $this->createMock(TotalsCollector::class);
        $this->sessionHelper = $this->createMock(SessionHelper::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->eventsForThirdPartyModules = $this->createMock(EventsForThirdPartyModules::class);
        $this->cartRepository = $this->createMock(CartRepository::class);
        $this->productRepositoryInterface = $this->createMock(ProductRepositoryInterface::class);
        $this->stockStateInterface = $this->createMock(StockStateInterface::class);

        $this->updateCartContext = $this->getMockBuilder(UpdateCartContext::class)
            ->setConstructorArgs(
                [
                    $this->request,
                    $this->response,
                    $this->hookHelper,
                    $this->errorResponse,
                    $this->logHelper,
                    $this->bugsnag,
                    $this->regionModel,
                    $this->orderHelper,
                    $this->cartHelper,
                    $this->checkoutSession,
                    $this->ruleRepository,
                    $this->usageFactory,
                    $this->objectFactory,
                    $this->timezone,
                    $this->customerFactory,
                    $this->configHelper,
                    $this->discountHelper,
                    $this->totalsCollector,
                    $this->sessionHelper,
                    $this->cache,
                    $this->eventsForThirdPartyModules,
                    $this->productRepositoryInterface,
                    $this->stockStateInterface,
                    $this->cartRepository
                ]
            )
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    private function initCurrentMock(
        $mockedMethods = [],
        $callOriginalConstructor = true,
        $callOriginalClone = true,
        $callAutoload = true,
        $cloneArguments = false
    ) {
        $this->currentMock = $this->getMockForAbstractClass(
            UpdateCartCommon::class,
            [$this->updateCartContext],
            '',
            $callOriginalConstructor,
            $callOriginalClone,
            $callAutoload,
            $mockedMethods,
            $cloneArguments
        );
    }
    
    /**
     * @param int $quoteId
     * @param int $parentQuoteId
     * @param array $methods
     * @return MockObject
     */
    private function getQuoteMock(
        $quoteId = self::IMMUTABLE_QUOTE_ID,
        $parentQuoteId = self::PARENT_QUOTE_ID,
        $methods = []
    ) {
        $quoteMethods = array_merge([
            'getId', 'getBoltParentQuoteId', 'getStoreId', 'getQuoteCurrencyCode'
        ], $methods);

        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();
        $quote->method('getId')
            ->willReturn($quoteId);
        $quote->method('getBoltParentQuoteId')
            ->willReturn($parentQuoteId);
        $quote->method('getQuoteCurrencyCode')
            ->willReturn(self::CURRENCY_CODE);
        $quote->method('getStoreId')
            ->willReturn(self::STORE_ID);
        return $quote;
    }
    
    private function createHelperMocks()
    {
        $this->hookHelper = $this->createMock(HookHelper::class);

        $this->logHelper = $this->createMock(LogHelper::class);

        $this->cartHelper = $this->getMockBuilder(CartHelper::class)
            ->setMethods(
                [
                    'getActiveQuoteById',
                    'getQuoteById',
                    'handleSpecialAddressCases',
                    'validateEmail',
                    'getCartData'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $this->configHelper = $this->getMockBuilder(ConfigHelper::class)
            ->setMethods(['getIgnoredShippingAddressCoupons'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->discountHelper = $this->getMockBuilder(DiscountHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderHelper = $this->getMockBuilder(OrderHelper::class)
            ->setMethods(
                [
                    'getExistingOrder',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $this->bugsnag = $this->getMockBuilder(Bugsnag::class)
            ->setMethods(['notifyException', 'notifyError'])
            ->disableOriginalConstructor()
            ->getMock();
    }
    
    /**
     * @return array
     */
    private function setAddressSetUp()
    {
        $quoteAddressMock = $this->createPartialMock(
            Address::class,
            [
                'setShouldIgnoreValidation',
                'setShippingMethod',
                'setCollectShippingRates',
                'collectShippingRates',
                'addData',
                'save'
            ]
        );
        $addressData = [
            'first_name'                 => 'Test',
            'last_name'                  => 'Bolt',
            'region'                     => 'CA',
            'country_code'               => 'US',
            'email_address'              => 'test@bolt.com',
            'street_address1'            => 'Test Street 1',
            'street_address2'            => 'Test Street 2',
            'locality'                   => 'Beverly Hills',
            'postal_code'                => '90210',
            'phone_number'               => '0123456789',
            'company'                    => 'Bolt',
            'random_empty_field'         => '',
            'another_random_empty_field' => [],
        ];
        return [$quoteAddressMock, $addressData];
    }

    /**
     *
     * @test
     * that sets internal properties
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $this->initCurrentMock();

        $this->assertAttributeInstanceOf(
            Request::class,
            'request',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            Response::class,
            'response',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            LogHelper::class,
            'logHelper',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            Bugsnag::class,
            'bugsnag',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            CartHelper::class,
            'cartHelper',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            HookHelper::class,
            'hookHelper',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            BoltErrorResponse::class,
            'errorResponse',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            RegionModel::class,
            'regionModel',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            OrderHelper::class,
            'orderHelper',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            RegionModel::class,
            'regionModel',
            $this->currentMock
        );
    }

    /**
     * @test
     * that preProcessWebhook would forward to hook helper preProcessWebhook
     *
     * @covers ::preProcessWebhook
     */
    public function preProcessWebhook()
    {
        $this->initCurrentMock();
        $this->hookHelper->expects(self::once())->method('preProcessWebhook')
            ->with(self::STORE_ID);
        TestHelper::invokeMethod(
            $this->currentMock,
            'preProcessWebhook',
            [self::STORE_ID]
        );
    }
    
    /**
     * @test
     * that validateQuote would return false if parent quote id is empty
     *
     * @covers ::validateQuote
     */
    public function validateQuote_parentQuoteIdEmpty_returnFalse()
    {
        $this->initCurrentMock();

        $this->cartHelper->expects(self::once())->method('getQuoteById')
        ->with(self::IMMUTABLE_QUOTE_ID)
        ->willReturn($this->getQuoteMock(
            null,
            self::PARENT_QUOTE_ID
        ));

        $this->assertFalse($this->currentMock->validateQuote(self::IMMUTABLE_QUOTE_ID));
    }
    
    /**
     * @test
     * that validateQuote would return false if order already was created
     *
     * @covers ::validateQuote
     */
    public function validateQuote_orderAlreadyCreated_returnFalse()
    {
        $this->initCurrentMock();
        
        $this->cartHelper->expects(self::once())->method('getQuoteById')
        ->with(self::IMMUTABLE_QUOTE_ID)
        ->willReturn($this->getQuoteMock(
            self::IMMUTABLE_QUOTE_ID,
            self::PARENT_QUOTE_ID
        ));

        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::PARENT_QUOTE_ID)
            ->willReturn($this->getQuoteMock(
                self::PARENT_QUOTE_ID,
                self::PARENT_QUOTE_ID
            ));

        $this->orderHelper->expects(self::once())->method('getExistingOrder')
            ->with(null,self::PARENT_QUOTE_ID)->willReturn(true);

        $this->assertFalse($this->currentMock->validateQuote(self::IMMUTABLE_QUOTE_ID));
    }

    /**
     * @test
     * that validateQuote would return false if immutable quote does not exist
     *
     * @covers ::validateQuote
     */
    public function validateQuote_immutableQuoteNotExist_returnFalse()
    {
        $this->initCurrentMock();

        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)->willReturn(null);

        $this->assertFalse($this->currentMock->validateQuote(self::IMMUTABLE_QUOTE_ID));
    }

    /**
     * @test
     * that validateQuote would return false if immutable quote does not have any item
     *
     * @covers ::validateQuote
     */
    public function validateQuote_immutableQuoteNoItem_returnFalse()
    {
        $this->initCurrentMock();
        
        $parentQuoteMock = $this->getQuoteMock(
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );

        $immutableQuoteMock = $this->getQuoteMock(
            self::IMMUTABLE_QUOTE_ID,
            self::PARENT_QUOTE_ID,
            ['getItemsCount']
        );
        
        $immutableQuoteMock->expects(self::once())->method('getItemsCount')->willReturn(0);
        
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::PARENT_QUOTE_ID)->willReturn($parentQuoteMock);
        
        $this->orderHelper->expects(self::once())->method('getExistingOrder')
            ->with(null, self::PARENT_QUOTE_ID)->willReturn(false);
        
        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)->willReturn($immutableQuoteMock);
        
        $this->assertFalse($this->currentMock->validateQuote(self::IMMUTABLE_QUOTE_ID));
    }
    
    /**
     * @test
     * that validateQuote would return parent quote and immutable quote if all works
     *
     * @covers ::validateQuote
     */
    public function validateQuote_allWork_returnQuotes()
    {
        $this->initCurrentMock();
        
        $parentQuoteMock = $this->getQuoteMock(
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );

        $immutableQuoteMock = $this->getQuoteMock(
            self::IMMUTABLE_QUOTE_ID,
            self::PARENT_QUOTE_ID,
            ['getItemsCount']
        );
        
        $immutableQuoteMock->expects(self::once())->method('getItemsCount')->willReturn(1);
        
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::PARENT_QUOTE_ID)->willReturn($parentQuoteMock);
        
        $this->orderHelper->expects(self::once())->method('getExistingOrder')
            ->with(null, self::PARENT_QUOTE_ID)->willReturn(false);
        
        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)->willReturn($immutableQuoteMock);
        
        $this->assertEquals([$parentQuoteMock, $immutableQuoteMock], $this->currentMock->validateQuote(self::IMMUTABLE_QUOTE_ID));
    }
    
    /**
     * @test
     * that validateQuote would return false if has exception
     *
     * @covers ::validateQuote
     */
    public function validateQuote_hasException_returnFalse()
    {
        $this->initCurrentMock();
        
        $exception = new NoSuchEntityException();
        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)->willThrowException($exception);
        
        $this->assertFalse($this->currentMock->validateQuote(self::IMMUTABLE_QUOTE_ID));
    }
    
    /**
     * @test
     *
     * @covers ::setShipment
     */
    public function setShipment()
    {
        $this->initCurrentMock();
        
        list($quoteAddress, $address) = $this->setAddressSetUp();
        
        $shipment = [
            'reference' => 'flatrate_flatrate',
            'shipping_address' => $address
        ];

        $immutableQuoteMock = $this->getQuoteMock(
            self::IMMUTABLE_QUOTE_ID,
            self::PARENT_QUOTE_ID,
            ['getShippingAddress']
        );
        
        $this->cartHelper->expects(static::once())->method('handleSpecialAddressCases')
            ->with($address)->willReturn($address);
        
        $this->regionModel->expects(static::once())->method('loadByName')
            ->with($address['region'], $address['country_code'])->willReturnSelf();
        $this->regionModel->expects(static::once())->method('getId')->willReturn(12);

        $this->cartHelper->expects(static::once())->method('validateEmail')->with($address['email_address'])
            ->willReturn(true);

        $quoteAddress->expects(static::once())->method('setShouldIgnoreValidation')->with(true);
        $quoteAddress->expects(static::once())->method('addData')
            ->with(
                [
                    'firstname'  => 'Test',
                    'lastname'   => 'Bolt',
                    'street'     => "Test Street 1\nTest Street 2",
                    'city'       => 'Beverly Hills',
                    'country_id' => 'US',
                    'region'     => 'CA',
                    'postcode'   => '90210',
                    'telephone'  => '0123456789',
                    'region_id'  => 12,
                    'company'    => 'Bolt',
                    'email'      => 'test@bolt.com',
                ]
            )
            ->willReturnSelf();
        $quoteAddress->expects(static::once())->method('setShippingMethod')->with('flatrate_flatrate')->willReturnSelf();
        $quoteAddress->expects(static::once())->method('setCollectShippingRates')->with(true)->willReturnSelf();
        $quoteAddress->expects(static::once())->method('collectShippingRates')->willReturnSelf();
        $quoteAddress->expects(static::once())->method('save');
        
        $immutableQuoteMock->expects(self::once())->method('getShippingAddress')->willReturn($quoteAddress);
        
        $this->currentMock->setShipment($shipment, $immutableQuoteMock);
    }
    
    /**
     * @test
     * @covers ::updateTotals
     *
     */
    public function updateTotals_always_collectsTotalsAndSavesTheQuote()
    {
        $this->initCurrentMock();
        
        $quote = $this->getQuoteMock(
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID,
            [
                'getShippingAddress',
                'setTotalsCollectedFlag',
                'collectTotals',
                'setDataChanges',
            ]
        );

        $quote->expects(static::once())->method('getShippingAddress')->willReturnSelf();
        $quote->expects(static::once())->method('setTotalsCollectedFlag')->with(false)->willReturnSelf();
        $quote->expects(static::once())->method('collectTotals')->willReturnSelf();
        $quote->expects(static::once())->method('setDataChanges')->with(true)->willReturnSelf();

        $this->cartRepository->expects(static::once())->method('save')->with($quote)->willReturnSelf();
        TestHelper::invokeMethod($this->currentMock, 'updateTotals', [$quote]);
    }
    
    /**
     * @test
     * @covers ::getCartItems
     *
     */
    public function getCartItems_returnItemsWithProperties()
    {
        $this->initCurrentMock();
        
        $quoteItem = $this->getMockBuilder(\Magento\Quote\Model\Quote\Item::class)
            ->setMethods(['getProductId', 'getQty', 'getId'])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteItem->method('getProductId')->willReturn(100);
        $quoteItem->method('getQty')->willReturn(1);
        $quoteItem->method('getId')->willReturn(60);
        
        $quote = $this->getQuoteMock(
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID,
            [
                'getAllVisibleItems',
            ]
        );
        
        $quote->method('getAllVisibleItems')->willReturn([$quoteItem]);
        
        $expected_result = [
            [
                'reference'     => 100,
                'quantity'      => 1,
                'quote_item_id' => 60,
            ]
        ];
        
        $result = TestHelper::invokeMethod($this->currentMock, 'getCartItems', [$quote]);
        
        $this->assertEquals($expected_result, $result);
    }
}
