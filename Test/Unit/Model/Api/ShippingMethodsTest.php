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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Model\Api\ShippingMethods as BoltShippingMethods;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Api\Data\ShippingOptionsInterfaceFactory;
use Bolt\Boltpay\Api\Data\ShippingTaxInterfaceFactory;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Magento\SalesRule\Model\RuleFactory as RuleFactory;
use Magento\SalesRule\Model\Rule;

/**
 * Class ShippingMethodsTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 */
class ShippingMethodsTest extends TestCase
{
    /**
     * @var BoltShippingMethods
     */
    private $currentMock;

    /**
     * @var HookHelper
     */
    private $hookHelper;

    /**
     * @var RegionModel
     */
    private $regionModel;

    /**
     * @var ShippingOptionsInterfaceFactory
     */
    private $factoryShippingOptionsMock;

    /**
     * @var ShippingTaxInterfaceFactory
     */
    private $shippingTaxInterfaceFactory;

    /**
     * @var ShippingOptionInterfaceFactory
     */
    private $shippingOptionInterfaceFactory;

    /**
     * @var TotalsCollector
     */
    private $totalsCollector;

    /**
     * @var ShippingMethodConverter
     */
    private $converter;

    /**
     * @var logHelper
     */
    private $logHelper;

    /**
     * @var BoltErrorResponse
     */
    private $errorResponse;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var PriceHelper
     */
    private $priceHelper;

    /**
     * @var SessionHelper
     */
    private $sessionHelper;

    /**
     * @var DiscountHelper
     */
    private $discountHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var MetricsClient
     */
    private $metricsClient;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var CartHelper
     */
    private $ruleFactory;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->createFactoryMocks();

        $this->totalsCollector = $this->getMockBuilder(TotalsCollector::class)
            ->setMethods(['collectAddressTotals'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->totalsCollector->method('collectAddressTotals')
            ->withAnyParameters()
            ->willReturnSelf();

        $this->cartHelper = $this->getMockBuilder(CartHelper::class)
            ->setMethods([
                'getQuoteById', 'validateEmail', 'convertCustomAddressFieldsToCacheIdentifier'
            ])->disableOriginalConstructor()
            ->getMock();

        $this->cartHelper->expects($this->any())
            ->method('convertCustomAddressFieldsToCacheIdentifier')
            ->willReturn("");

        $this->configHelper = $this->getMockBuilder(ConfigHelper::class)
            ->setMethods(['getPrefetchShipping', 'getResetShippingCalculation', 'getIgnoredShippingAddressCoupons'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->configHelper->method('getPrefetchShipping')
            ->withAnyParameters()
            ->willReturn(true);
        $this->configHelper->method('getIgnoredShippingAddressCoupons')
            ->willReturn([]);
        $this->configHelper->expects($this->any())
            ->method('getResetShippingCalculation')
            ->with(null)
            ->willReturn(false);

        $shipMethodObject = $this->getMockBuilder(\Magento\Quote\Model\Cart\ShippingMethod::class)
            ->setMethods([
                'getCarrierCode', 'getMethodCode', 'getMethodTitle', 'getCarrierTitle',
                'getAmount', 'getBaseAmount', 'getErrorMessage'
            ])
            ->disableOriginalConstructor()
            ->getMock();
        $shipMethodObject->method('getErrorMessage')
            ->willReturn(false);
        $shipMethodObject->method('getCarrierCode')
            ->willReturn('flatrate');
        $shipMethodObject->method('getMethodCode')
            ->willReturn('flatrate');
        $shipMethodObject->method('getMethodTitle')
            ->willReturn('Fixed');
        $shipMethodObject->method('getCarrierTitle')
            ->willReturn('Flate Rate');
        $shipMethodObject->method('getAmount')
            ->willReturn((int)5);
        $shipMethodObject->method('getBaseAmount')
            ->willReturn((int)5);

        $this->converter = $this->getMockBuilder(ShippingMethodConverter::class)
            ->setMethods(['modelToDataObject'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->converter->method('modelToDataObject')
            ->withAnyParameters()
            ->willReturn($shipMethodObject);

        $this->errorResponse = $this->getMockBuilder(BoltErrorResponse::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->response = $this->getMockBuilder(Response::class)
            ->setMethods(['sendResponse'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->logHelper = $this->createMock(LogHelper::class);
        $this->hookHelper = $this->createMock(HookHelper::class);
        $this->regionModel = $this->createMock(RegionModel::class);
        $this->request = $this->createMock(Request::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->priceHelper = $this->createMock(PriceHelper::class);
        $this->sessionHelper = $this->createMock(SessionHelper::class);
        $this->discountHelper = $this->createMock(DiscountHelper::class);
        $this->ruleFactory = $this->createMock(RuleFactory::class);
        $this->metricsClient = $this->createMock(MetricsClient::class);

        $this->bugsnag = $this->getMockBuilder(Bugsnag::class)
            ->setMethods(['notifyException'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->bugsnag->method('notifyException')
            ->willReturnSelf();
    }

    /**
     * @test
     */
    public function getShippingMethodsIfQuoteEmptyReturnException()
    {
        $quoteId = 1001;
        $cart = [
            'display_id' => '100050001 / ' . $quoteId
        ];
        $shippingAddress = [
            'street_address1' => 'test'
        ];

        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(['getId', 'isVirtual'])
            ->disableOriginalConstructor()
            ->getMock();
        $quote->method('getId')
            ->willReturn(false);
        $quote->method('isVirtual')
            ->willReturn(false);

        $methods = ['sendErrorResponse', 'proceedWithHook', 'checkCartItems', 'notifyException',
            'validateQuote', 'loadSessionByQuote', 'throwUnknownQuoteIdException', 'catchExceptionAndSendError'
        ];
        $this->currentMock = $this->getMockBuilder(BoltShippingMethods::class)
            ->setMethods($methods)
            ->setConstructorArgs([
                $this->hookHelper,
                $this->regionModel,
                $this->factoryShippingOptionsMock,
                $this->shippingTaxInterfaceFactory,
                $this->cartHelper,
                $this->totalsCollector,
                $this->converter,
                $this->shippingOptionInterfaceFactory,
                $this->bugsnag,
                $this->metricsClient,
                $this->logHelper,
                $this->errorResponse,
                $this->response,
                $this->configHelper,
                $this->request,
                $this->cache,
                $this->priceHelper,
                $this->sessionHelper,
                $this->discountHelper,
                $this->ruleFactory
            ])
            ->getMock();

        $message = new Phrase('Unprocessable Entity: Unknown quote id: ' . $quoteId);
        $this->currentMock->method('throwUnknownQuoteIdException')
            ->will($this->throwException(new LocalizedException($message)));
        $this->currentMock->method('catchExceptionAndSendError')
            ->withAnyParameters()
            ->will($this->throwException(new LocalizedException($message)));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unprocessable Entity: Unknown quote id: ' . $quoteId);

        $result = $this->currentMock->getShippingMethods($cart, $shippingAddress);

        // If another exception happens, the test will fail.
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function getShippingMethodsWithFullAddressData()
    {
        $quoteId = 1001;
        $parentQuoteId = 1000;

        $cart = [
            'display_id'      => '100050001 / ' . $quoteId,
            'order_reference' => $parentQuoteId
        ];
        $shippingAddress = [
            'company'         => "",
            'country'         => "United States",
            'country_code'    => "US",
            'email'           => "integration@bolt.com",
            'first_name'      => "YevhenBolt",
            'last_name'       => "BoltTest2",
            'locality'        => "New York",
            'phone'           => "+1 231 231 1234",
            'postal_code'     => "10001",
            'region'          => "New York",
            'street_address1' => "228 5th Avenue",
            'street_address2' => "",
        ];

        $this->cartHelper->method('validateEmail')
            ->withAnyParameters()
            ->willReturn(true);

        $this->configHelper->method('getResetShippingCalculation')
            ->withAnyParameters()
            ->willReturn(false);

        $methods = ['sendErrorResponse', 'proceedWithHook', 'checkCartItems', 'getQuoteById',
            'notifyException', 'validateQuote', 'loadSessionByQuote', 'throwQuoteIdException',
            'validateAddressData', 'shippingEstimation'
        ];
        $this->currentMock = $this->getMockBuilder(BoltShippingMethods::class)
            ->setMethods($methods)
            ->setConstructorArgs([
                $this->hookHelper,
                $this->regionModel,
                $this->factoryShippingOptionsMock,
                $this->shippingTaxInterfaceFactory,
                $this->cartHelper,
                $this->totalsCollector,
                $this->converter,
                $this->shippingOptionInterfaceFactory,
                $this->bugsnag,
                $this->metricsClient,
                $this->logHelper,
                $this->errorResponse,
                $this->response,
                $this->configHelper,
                $this->request,
                $this->cache,
                $this->priceHelper,
                $this->sessionHelper,
                $this->discountHelper,
                $this->ruleFactory
            ])
            ->getMock();

        $this->currentMock->method('getQuoteById')
            ->will(
                $this->returnCallback(function ($arg) use ($quoteId, $parentQuoteId, $shippingAddress) {
                    if ($arg == $quoteId) {
                        return $this->getQuoteMock($shippingAddress, $quoteId, $parentQuoteId);
                    }

                    return $this->getQuoteMock($shippingAddress, $parentQuoteId, $quoteId);
                })
            );

        $this->currentMock->method('validateAddressData')
            ->willReturnSelf();

        $option = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $option
            ->setService('Flat Rate - Fixed')
            ->setCost(5600)
            ->setReference('flatrate_flatrate')
            ->setTaxAmount(0)
        ;

        $shippingOptionData = [$option];

        $this->currentMock->method('shippingEstimation')
            ->willReturn($shippingOptionData);

        $result = $this->currentMock->getShippingMethods($cart, $shippingAddress);

        $this->assertEquals($result, $shippingOptionData);
    }

    /**
     * @test
     */
    public function getShippingMethodsWithFullAddressDataAndIncorrectEmail()
    {
        $quoteId = 1001;
        $cart = [
            'display_id' => '100050001 / ' . $quoteId,
            'items'      => [
                [
                    'sku'      => 'TestProduct',
                    'quantity' => '1'
                ]
            ]
        ];
        $shippingAddress = [
            'company'         => "",
            'country'         => "United States",
            'country_code'    => "US",
            'email'           => "integration@bolt",
            'first_name'      => "YevhenBolt",
            'last_name'       => "BoltTest2",
            'locality'        => "New York",
            'phone'           => "+1 231 231 1234",
            'postal_code'     => "10001",
            'region'          => "New York",
            'street_address1' => "228 5th Avenue",
            'street_address2' => "",
        ];

        $quote = $this->getQuoteMock($shippingAddress);
        $this->cartHelper->method('getQuoteById')
            ->with($quoteId)
            ->willReturn($quote);
        $this->cartHelper->method('validateEmail')
            ->with($shippingAddress['email'])
            ->willReturn(false);

        $message = new Phrase('Invalid email: ' . $shippingAddress['email']);
        $this->response->method('sendResponse')
            ->withAnyParameters()
            ->will($this->throwException(new LocalizedException($message)));

        $currentTestObject = $this->getCurrentTestObject();

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid email: ' . $shippingAddress['email']);

        $result = $currentTestObject->getShippingMethods($cart, $shippingAddress);

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function shippingEstimationWithoutEmailForApplePay()
    {
        $shippingAddressData = [
            'company'         => null,
            'country'         => "United States",
            'country_code'    => "US",
            'email'           => null,
            'first_name'      => "n/a",
            'last_name'       => "n/a",
            'locality'        => "New York",
            'region'          => "New York",
            'phone'           => null,
            'postal_code'     => "10001",
            'street_address1' => "",
            'street_address2' => null,
            'street_address3' => null,
            'street_address4' => null,
        ];

        $shortAddressApplePay = [
            'country_id' => 'US',
            'postcode'   => 10001,
            'region'     => 'New York',
            'city'       => 'New York'
        ];

        $shippingAddress = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
            ->setMethods(['addData', 'setCollectShippingRates', 'setShippingMethod', 'getGroupedAllShippingRates',
                'getShippingDiscountAmount', 'getShippingAmount', 'save'
            ])
            ->disableOriginalConstructor()
            ->getMock();
        $shippingAddress->method('addData')
            ->with($shortAddressApplePay)
            ->willReturnSelf();
        $shippingAddress->method('setShippingMethod')
            ->withAnyParameters()
            ->willReturnSelf();
        $shippingAddress->method('save')
            ->willReturnSelf();
        $shippingAddress->expects($this->once())
            ->method('setCollectShippingRates')
            ->with(true)
            ->willReturnSelf();
        $shippingAddress->method('getShippingDiscountAmount')
            ->willReturn('0');
        $shippingAddress->method('getShippingAmount')
            ->willReturn('5');

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingRates = [['flatrate' => $addressRate]];
        $shippingAddress->expects($this->once())
            ->method('getGroupedAllShippingRates')
            ->willReturn($shippingRates);

        $quote = $this->getQuoteMock($shippingAddress);

        $currentTestObject = $this->getCurrentTestObject();

        $result = $currentTestObject->shippingEstimation($quote, $shippingAddressData);

        $this->assertEquals($this->factoryShippingOptionsMock, $result);
    }

    /**
     * @test
     * @throws \ReflectionException
     */
    public function testDiscountAppliedToShipping()
    {
        $this->setUpRuleFactoryMock();
        $currentTestObject = $this->getCurrentTestObject();

        $testMethod = new \ReflectionMethod(BoltShippingMethods::class, 'doesDiscountApplyToShipping');
        $testMethod->setAccessible(true);

        $quoteMock = $this->getQuoteMock([]);

        $this->assertEquals(true, $testMethod->invokeArgs($currentTestObject, [$quoteMock]));
    }

    /**
     * @test
     */
    public function shippingEstimationWithDiscountAppliedToShipping()
    {
        $email = "integration@bolt.com";
        $shippingAddressData = [
            'company'         => "",
            'country'         => "United States",
            'country_code'    => "US",
            'email'           => $email,
            'first_name'      => "John",
            'last_name'       => "McCombs",
            'locality'        => "Knoxville",
            'phone'           => "+1 231 231 1234",
            'postal_code'     => "37921",
            'region'          => "Tennessee",
            'street_address1' => "4553 Annalee Way",
            'street_address2' => "",
        ];

        $this->cartHelper->method('validateEmail')
            ->with($email)
            ->willReturn(true);

        $shippingAddress = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
            ->setMethods(['addData', 'setCollectShippingRates', 'setShippingMethod', 'getGroupedAllShippingRates',
                'getShippingDiscountAmount', 'getShippingAmount', 'save'
            ])
            ->disableOriginalConstructor()
            ->getMock();
        $shippingAddress->method('setShippingMethod')
            ->withAnyParameters()
            ->willReturnSelf();
        $shippingAddress->method('save')
            ->willReturnSelf();
        $shippingAddress->expects($this->once())
            ->method('setCollectShippingRates')
            ->with(true)
            ->willReturnSelf();
        $shippingAddress->method('getShippingDiscountAmount')
            ->willReturn('0');
        $shippingAddress->method('getShippingAmount')
            ->willReturn('5');

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingRates = [['flatrate' => $addressRate]];
        $shippingAddress->expects($this->once())
            ->method('getGroupedAllShippingRates')
            ->willReturn($shippingRates);

        $quote = $this->getQuoteMock($shippingAddress);

        $this->setUpRuleFactoryMock();
        $currentTestObject = $this->getCurrentTestObject();

        $result = $currentTestObject->shippingEstimation($quote, $shippingAddressData);

        $this->assertEquals($this->factoryShippingOptionsMock, $result);
    }

    /**
     * @test
     * @throws \ReflectionException
     */
    public function testCouponInvalidForShippingAddress()
    {
        $parentQuoteCoupon = 'IGNORED_SHIPPING_ADDRESS_COUPON';
        $configCoupons = ['BOLT_TEST', 'ignored_shipping_address_coupon'];
        $immutableQuoteMock = $this->getQuoteMock([]);

        $this->configHelper = $this->getMockBuilder(ConfigHelper::class)
            ->setMethods(['getIgnoredShippingAddressCoupons'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->configHelper->method('getIgnoredShippingAddressCoupons')->with(null)->willReturn($configCoupons);

        $currentTestObject = $this->getCurrentTestObject();

        $reflection = new \ReflectionClass($currentTestObject);
        $reflectionProperty = $reflection->getProperty('quote');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($currentTestObject, $immutableQuoteMock);

        $testMethod = new \ReflectionMethod(BoltShippingMethods::class, 'couponInvalidForShippingAddress');
        $testMethod->setAccessible(true);

        $this->assertTrue($testMethod->invokeArgs($currentTestObject, [$parentQuoteCoupon]));
    }

    /**
     * Get quote mock with quote items
     *
     * @param $shippingAddress
     * @param $quoteId
     * @param $parentQuoteId
     * @return \PHPUnit\Framework\MockObject\MockObject
     * @throws \ReflectionException
     */
    private function getQuoteMock($shippingAddress, $quoteId = 1001, $parentQuoteId = 1000)
    {
        $quoteItem = $this->getMockBuilder(\Magento\Quote\Model\Quote\Item::class)
            ->setMethods(['getSku', 'getQty', 'getCalculationPrice'])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteItem->method('getSku')
            ->willReturn('TestProduct');
        $quoteItem->method('getQty')
            ->willReturn(1);


        $quoteMethods = [
            'getId', 'getBoltParentQuoteId', 'getSubtotal', 'getAllVisibleItems',
            'getAppliedRuleIds', 'isVirtual', 'getShippingAddress', 'collectTotals',
            'getQuoteCurrencyCode', 'getStoreId'
        ];
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $quote->method('getId')
            ->willReturn($quoteId);
        // TODO: need to cover multi-website cases where different store_id
        $quote->method('getStoreId')
            ->willReturn(null);
        $quote->method('getBoltParentQuoteId')
            ->willReturn($parentQuoteId);
        $quote->method('getSubtotal')
            ->willReturn(100);
        $quote->method('getAllVisibleItems')
            ->willReturn([$quoteItem]);
        $quote->method('getAppliedRuleIds')
            ->willReturn('2,3');
        $quote->method('isVirtual')
            ->willReturn(false);
        $quote->method('getShippingAddress')
            ->willReturn($shippingAddress);
        $quote->method('getQuoteCurrencyCode')
            ->willReturn('$');
        $quote->method('collectTotals')
            ->willReturnSelf();

        return $quote;
    }

    private function createFactoryMocks()
    {
        $this->factoryShippingOptionsMock = $this->getMockBuilder(ShippingOptionsInterfaceFactory::class)
            ->setMethods(['create', 'setShippingOptions', 'setTaxResult'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->shippingTaxInterfaceFactory = $this->getMockBuilder(ShippingTaxInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create', 'setAmount'])
            ->getMock();

        $this->shippingOptionInterfaceFactory = $this->getMockBuilder(ShippingOptionInterfaceFactory::class)
            ->setMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->shippingOptionInterfaceFactory = $this->getMockBuilder(ShippingOptionInterfaceFactory::class)
            ->setMethods(['create', 'setService', 'setCost', 'setReference', 'setTaxAmount'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->shippingOptionInterfaceFactory->method('create')
            ->willReturnSelf();
        $this->shippingOptionInterfaceFactory->method('setService')
            ->with('Flate Rate - Fixed')
            ->willReturnSelf();
        $this->shippingOptionInterfaceFactory->method('setCost')
            ->with(500)
            ->willReturnSelf();
        $this->shippingOptionInterfaceFactory->method('setReference')
            ->with('flatrate_flatrate')
            ->willReturnSelf();
        $this->shippingOptionInterfaceFactory->method('setTaxAmount')
            ->with(0)
            ->willReturnSelf();

        $this->shippingTaxInterfaceFactory = $this->getMockBuilder(ShippingTaxInterfaceFactory::class)
            ->setMethods(['create', 'setAmount'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->shippingTaxInterfaceFactory->method('create')
            ->willReturnSelf();
        $this->shippingTaxInterfaceFactory->method('setAmount')
            ->with(0)
            ->willReturnSelf();

        $this->factoryShippingOptionsMock->method('create')
            ->willReturnSelf();
        $this->factoryShippingOptionsMock->method('setShippingOptions')
            ->withAnyParameters()
            ->willReturnSelf();
        $this->factoryShippingOptionsMock->method('setTaxResult')
            ->with($this->shippingTaxInterfaceFactory)
            ->willReturnSelf();
    }

    private function setUpRuleFactoryMock()
    {
        $ruleMock = $this->getMockBuilder(Rule::class)
            ->setMethods(['load', 'getApplyToShipping'])
            ->disableOriginalConstructor()
            ->getMock();
        $ruleMock->method('load')->with(2)->willReturnSelf();
        $ruleMock->method('getApplyToShipping')->willReturn(true);

        $this->ruleFactory->method('create')->willReturn($ruleMock);
    }

    /**
     * @return BoltShippingMethods
     */
    private function getCurrentTestObject()
    {
        return new BoltShippingMethods(
            $this->hookHelper,
            $this->regionModel,
            $this->factoryShippingOptionsMock,
            $this->shippingTaxInterfaceFactory,
            $this->cartHelper,
            $this->totalsCollector,
            $this->converter,
            $this->shippingOptionInterfaceFactory,
            $this->bugsnag,
            $this->metricsClient,
            $this->logHelper,
            $this->errorResponse,
            $this->response,
            $this->configHelper,
            $this->request,
            $this->cache,
            $this->priceHelper,
            $this->sessionHelper,
            $this->discountHelper,
            $this->ruleFactory
        );
    }
}
