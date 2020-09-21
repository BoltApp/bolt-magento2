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

use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Quote\Model\Quote;
use Magento\Directory\Model\Region as RegionModel;
use Magento\SalesRule\Model\RuleRepository;
use Magento\SalesRule\Model\Coupon;
use Magento\SalesRule\Model\ResourceModel\Coupon\UsageFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\SalesRule\Model\Rule\CustomerFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Framework\App\CacheInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Model\Api\UpdateCartContext;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class UpdateCartContextTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\UpdateCartContext
 */
class UpdateCartContextTest extends TestCase
{
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
     * @var CacheInterface|MockObject
     */
    private $cache;
    
    /**
     * @var EventsForThirdPartyModules
     */
    protected $eventsForThirdPartyModules;
    
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepositoryInterface;
    
    /**
     * @var StockStateInterface
     */
    protected $stockStateInterface;
    
    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepositoryInterface;

    /**
     * @var UpdateCartContext|MockObject
     */
    private $currentMock;

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
        $this->productRepositoryInterface = $this->createMock(ProductRepositoryInterface::class);
        $this->stockStateInterface = $this->createMock(StockStateInterface::class);
        $this->cartRepositoryInterface = $this->createMock(CartRepositoryInterface::class);

        $this->currentMock = $this->getMockBuilder(UpdateCartContext::class)
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
                    $this->cartRepositoryInterface
                ]
            )
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     *
     * @test
     * that set internal properties
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $instance = new UpdateCartContext(
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
            $this->cartRepositoryInterface
        );
        
        $this->assertAttributeInstanceOf(Request::class, 'request', $instance);
        $this->assertAttributeInstanceOf(Response::class, 'response', $instance);
        $this->assertAttributeInstanceOf(HookHelper::class, 'hookHelper', $instance);
        $this->assertAttributeInstanceOf(BoltErrorResponse::class, 'errorResponse', $instance);
        $this->assertAttributeInstanceOf(LogHelper::class, 'logHelper', $instance);
        $this->assertAttributeInstanceOf(Bugsnag::class, 'bugsnag', $instance);
        $this->assertAttributeInstanceOf(RegionModel::class, 'regionModel', $instance);
        $this->assertAttributeInstanceOf(OrderHelper::class, 'orderHelper', $instance);
        $this->assertAttributeInstanceOf(CartHelper::class, 'cartHelper', $instance);
        $this->assertAttributeInstanceOf(CheckoutSession::class, 'checkoutSession', $instance);
        $this->assertAttributeInstanceOf(RuleRepository::class, 'ruleRepository', $instance);
        $this->assertAttributeInstanceOf(UsageFactory::class, 'usageFactory', $instance);
        $this->assertAttributeInstanceOf(DataObjectFactory::class, 'objectFactory', $instance);
        $this->assertAttributeInstanceOf(TimezoneInterface::class, 'timezone', $instance);
        $this->assertAttributeInstanceOf(CustomerFactory::class, 'customerFactory', $instance);
        $this->assertAttributeInstanceOf(ConfigHelper::class, 'configHelper', $instance);
        $this->assertAttributeInstanceOf(DiscountHelper::class, 'discountHelper', $instance);
        $this->assertAttributeInstanceOf(TotalsCollector::class, 'totalsCollector', $instance);
        $this->assertAttributeInstanceOf(SessionHelper::class, 'sessionHelper', $instance);
        $this->assertAttributeInstanceOf(CacheInterface::class, 'cache', $instance);
        $this->assertAttributeInstanceOf(EventsForThirdPartyModules::class, 'eventsForThirdPartyModules', $instance);
        $this->assertAttributeInstanceOf(ProductRepositoryInterface::class, 'productRepositoryInterface', $instance);
        $this->assertAttributeInstanceOf(StockStateInterface::class, 'stockStateInterface', $instance);
        $this->assertAttributeInstanceOf(CartRepositoryInterface::class, 'cartRepositoryInterface', $instance);
    }

    /**
     * @test
     * that getRequest would returns Request instance
     *
     * @covers ::getRequest
     */
    public function getRequest_always_returnsRequest()
    {
        $this->assertEquals($this->request, $this->currentMock->getRequest());
    }

    /**
     * @test
     * that getResponse would returns Response instance
     *
     * @covers ::getResponse
     */
    public function getResponse_always_returnsResponse()
    {
        $this->assertEquals($this->response, $this->currentMock->getResponse());
    }

    /**
     * @test
     * that getHookHelper would returns HookHelper instance
     *
     * @covers ::getHookHelper
     */
    public function getHookHelper_always_returnsHookHelper()
    {
        $this->assertEquals($this->hookHelper, $this->currentMock->getHookHelper());
    }

    /**
     * @test
     * that getBoltErrorResponse would returns BoltErrorResponse instance
     *
     * @covers ::getBoltErrorResponse
     */
    public function getBoltErrorResponse_always_returnsBoltErrorResponse()
    {
        $this->assertEquals($this->errorResponse, $this->currentMock->getBoltErrorResponse());
    }

    /**
     * @test
     * that getLogHelper would returns LogHelper instance
     *
     * @covers ::getLogHelper
     */
    public function getLogHelper_always_returnsLogHelper()
    {
        $this->assertEquals($this->logHelper, $this->currentMock->getLogHelper());
    }

    /**
     * @test
     * that getBugsnag would returns Bugsnag instance
     *
     * @covers ::getBugsnag
     */
    public function getBugsnag_always_returnsBugsnag()
    {
        $this->assertEquals($this->bugsnag, $this->currentMock->getBugsnag());
    }

    /**
     * @test
     * that getRegionModel would returns RegionModel instance
     *
     * @covers ::getRegionModel
     */
    public function getRegionModel_always_returnsRegionModel()
    {
        $this->assertEquals($this->regionModel, $this->currentMock->getRegionModel());
    }

    /**
     * @test
     * that getOrderHelper would returns OrderHelper instance
     *
     * @covers ::getOrderHelper
     */
    public function getOrderHelper_always_returnsOrderHelper()
    {
        $this->assertEquals($this->orderHelper, $this->currentMock->getOrderHelper());
    }

    /**
     * @test
     * that getCartHelper would returns CartHelper instance
     *
     * @covers ::getCartHelper
     */
    public function getCartHelper_always_returnsCartHelper()
    {
        $this->assertEquals($this->cartHelper, $this->currentMock->getCartHelper());
    }

    /**
     * @test
     * that getRuleRepository would returns RuleRepository instance
     *
     * @covers ::getRuleRepository
     */
    public function getRuleRepository_always_returnsRuleRepository()
    {
        $this->assertEquals($this->ruleRepository, $this->currentMock->getRuleRepository());
    }

    /**
     * @test
     * that getUsageFactory would returns UsageFactory instance
     *
     * @covers ::getUsageFactory
     */
    public function getUsageFactory_always_returnsUsageFactory()
    {
        $this->assertEquals($this->usageFactory, $this->currentMock->getUsageFactory());
    }
    
    /**
     * @test
     * that getObjectFactory would returns DataObjectFactory instance
     *
     * @covers ::getObjectFactory
     */
    public function getObjectFactory_always_returnsDataObjectFactory()
    {
        $this->assertEquals($this->objectFactory, $this->currentMock->getObjectFactory());
    }
    
    /**
     * @test
     * that getTimezone would returns TimezoneInterface instance
     *
     * @covers ::getTimezone
     */
    public function getTimezone_always_returnsTimezoneInterface()
    {
        $this->assertEquals($this->timezone, $this->currentMock->getTimezone());
    }
    
    /**
     * @test
     * that getCustomerFactory would returns CustomerFactory instance
     *
     * @covers ::getCustomerFactory
     */
    public function getCustomerFactory_always_returnsCustomerFactory()
    {
        $this->assertEquals($this->customerFactory, $this->currentMock->getCustomerFactory());
    }
    
    /**
     * @test
     * that getConfigHelper would returns ConfigHelper instance
     *
     * @covers ::getConfigHelper
     */
    public function getConfigHelper_always_returnsConfigHelper()
    {
        $this->assertEquals($this->configHelper, $this->currentMock->getConfigHelper());
    }
    
    /**
     * @test
     * that getCheckoutSession would returns CheckoutSession instance
     *
     * @covers ::getCheckoutSession
     */
    public function getCheckoutSession_always_returnsCheckoutSession()
    {
        $this->assertEquals($this->checkoutSession, $this->currentMock->getCheckoutSession());
    }
    
    /**
     * @test
     * that getDiscountHelper would returns DiscountHelper instance
     *
     * @covers ::getDiscountHelper
     */
    public function getDiscountHelper_always_returnsDiscountHelper()
    {
        $this->assertEquals($this->discountHelper, $this->currentMock->getDiscountHelper());
    }
    
    /**
     * @test
     * that getTotalsCollector would returns TotalsCollector instance
     *
     * @covers ::getTotalsCollector
     */
    public function getTotalsCollector_always_returnsTotalsCollector()
    {
        $this->assertEquals($this->totalsCollector, $this->currentMock->getTotalsCollector());
    }
    
    /**
     * @test
     * that getSessionHelper would returns SessionHelper instance
     *
     * @covers ::getSessionHelper
     */
    public function getSessionHelper_always_returnsSessionHelper()
    {
        $this->assertEquals($this->sessionHelper, $this->currentMock->getSessionHelper());
    }
    
    /**
     * @test
     * that getCache would returns Cache instance
     *
     * @covers ::getCache
     */
    public function getCache_always_returnsCache()
    {
        $this->assertEquals($this->cache, $this->currentMock->getCache());
    }
    
    /**
     * @test
     * that getEventsForThirdPartyModules would returns EventsForThirdPartyModules instance
     *
     * @covers ::getEventsForThirdPartyModules
     */
    public function getEventsForThirdPartyModules_always_returnsEventsForThirdPartyModules()
    {
        $this->assertEquals($this->eventsForThirdPartyModules, $this->currentMock->getEventsForThirdPartyModules());
    }
    
    /**
     * @test
     * that getProductRepositoryInterface would returns ProductRepositoryInterface instance
     *
     * @covers ::getProductRepositoryInterface
     */
    public function getProductRepositoryInterface_always_returnsProductRepositoryInterface()
    {
        $this->assertEquals($this->productRepositoryInterface, $this->currentMock->getProductRepositoryInterface());
    }
    
    /**
     * @test
     * that getStockStateInterface would returns StockStateInterface instance
     *
     * @covers ::getStockStateInterface
     */
    public function getStockStateInterface_always_returnsStockStateInterface()
    {
        $this->assertEquals($this->stockStateInterface, $this->currentMock->getStockStateInterface());
    }
    
    /**
     * @test
     * that getCartRepositoryInterface would returns CartRepositoryInterface instance
     *
     * @covers ::getCartRepositoryInterface
     */
    public function getCartRepositoryInterface_always_returnsCartRepositoryInterface()
    {
        $this->assertEquals($this->cartRepositoryInterface, $this->currentMock->getCartRepositoryInterface());
    }
}
