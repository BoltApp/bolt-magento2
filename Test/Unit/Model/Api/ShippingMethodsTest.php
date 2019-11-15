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
use Magento\Framework\Webapi\Exception as WebapiException;
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
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Class ShippingMethodsTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\ShippingMethods
 */
class ShippingMethodsTest extends TestCase
{
    const QUOTE_ID = 1001;
    const CACHE_IDENTIFIER = "1000_10000_US_New York_10001____TestProduct_1_2_3";
    const STORE_ID = 1;
    /**
     * @var BoltShippingMethods|MockObject
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
     * @var Bugsnag|MockObject
     */
    private $bugsnag;

    /**
     * @var MetricsClient
     */
    private $metricsClient;

    /**
     * @var CartHelper|MockObject
     */
    private $cartHelper;

    /**
     * @var RuleFactory
     */
    private $ruleFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address|MockObject
     */
    private $shippingAddressMock;

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
                'getQuoteById',
                'validateEmail',
                'convertCustomAddressFieldsToCacheIdentifier',
                'handleSpecialAddressCases',
                'getCartItems'
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
            ->setMethods(['setHttpResponseCode', 'setBody', 'sendResponse'])
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
        $this->ruleFactory = $this->getMockBuilder(RuleFactory::class)
            ->setMethods(
                [
                    'create'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $this->metricsClient = $this->createMock(MetricsClient::class);

        $this->bugsnag = $this->getMockBuilder(Bugsnag::class)
            ->setMethods(['notifyException', 'notifyError', 'registerCallback'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->bugsnag->method('notifyException')
            ->willReturnSelf();

        $this->shippingAddressMock = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
            ->setMethods(
                [
                    'addData',
                    'setCollectShippingRates',
                    'setShippingMethod',
                    'getGroupedAllShippingRates',
                    'getShippingDiscountAmount',
                    'getShippingAmount',
                    'save',
                    'getDiscountAmount',
                    'getDiscountDescription'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $this->shippingAddressMock->method('setShippingMethod')->withAnyParameters()->willReturnSelf();
        $this->shippingAddressMock->method('save')->willReturnSelf();
        $this->shippingAddressMock->method('setCollectShippingRates')->with(true)->willReturnSelf();
        $this->shippingAddressMock->method('getShippingDiscountAmount')->willReturn(0);
        $this->shippingAddressMock->method('getShippingAmount')->willReturn(5);
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

        $this->initCurrentMock(['throwUnknownQuoteIdException', 'catchExceptionAndSendError']);

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

        $quote = $this->getQuoteMock($shippingAddress);
        $quote->method('getStoreId')->willReturn(1);

        $this->cartHelper->method('validateEmail')
            ->with($shippingAddress['email'])
            ->willReturn(true);

        $this->configHelper->method('getResetShippingCalculation')
            ->withAnyParameters()
            ->willReturn(false);

        $this->cartHelper->expects(self::once())->method('handleSpecialAddressCases')
            ->with($shippingAddress)
            ->willReturn($shippingAddress);

        $this->configHelper = $this->getMockBuilder(ConfigHelper::class)
            ->setMethods(['getStoreVersion'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->configHelper->method('getStoreVersion')->willReturn('2.3.3');

        $methods = ['sendErrorResponse', 'checkCartItems', 'getQuoteById',
            'shippingEstimation', 'preprocessHook', 'couponInvalidForShippingAddress'
        ];

        $this->sessionHelper->expects(self::once())->method('loadSession')->willReturn(null);

        $this->initCurrentMock($methods, false);

        $this->currentMock->expects(self::once())->method('preprocessHook')->willReturn(null);
        $this->currentMock->expects(self::once())->method('checkCartItems')->with($cart)->willReturn(null);

        $this->currentMock->expects(self::exactly(2))->method('getQuoteById')
            ->withConsecutive([$quoteId], [$parentQuoteId])->willReturnOnConsecutiveCalls($quote, $quote);

        $shippingOptionData = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $shippingOptionData
            ->setService('Flat Rate - Fixed')
            ->setCost(5600)
            ->setReference('flatrate_flatrate')
            ->setTaxAmount(0);

        $this->currentMock->method('shippingEstimation')
            ->willReturn($shippingOptionData);

        $this->currentMock->expects(self::once())->method('couponInvalidForShippingAddress')
            ->withAnyParameters()->willReturn(false);

        $result = $this->currentMock->getShippingMethods($cart, $shippingAddress);

        $this->assertEquals($result, $shippingOptionData);
    }


    /**
     * @test
     */
    public function getShippingMethodsWithFullAddressData_taxAdjustedAndInvalidCoupon()
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

        $quote = $this->getQuoteMock($this->shippingAddressMock);
        $quote->method('getStoreId')->willReturn(1);

        $this->cartHelper->method('validateEmail')
            ->with($shippingAddress['email'])
            ->willReturn(true);

        $this->configHelper->method('getResetShippingCalculation')
            ->withAnyParameters()
            ->willReturn(false);

        $this->cartHelper->expects(self::once())->method('handleSpecialAddressCases')
            ->with($shippingAddress)
            ->willReturn($shippingAddress);

        $this->configHelper = $this->getMockBuilder(ConfigHelper::class)
            ->setMethods(['getStoreVersion'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->configHelper->method('getStoreVersion')->willReturn('2.3.3');

        $methods = ['sendErrorResponse', 'checkCartItems', 'getQuoteById',
                    'shippingEstimation', 'preprocessHook', 'couponInvalidForShippingAddress'
        ];

        $this->sessionHelper->expects(self::once())->method('loadSession')->willReturn(null);

        $this->initCurrentMock($methods, false);

        $this->currentMock->expects(self::once())->method('preprocessHook')->willReturn(null);
        $this->currentMock->expects(self::once())->method('checkCartItems')->with($cart)->willReturn(null);

        $this->currentMock->expects(self::exactly(2))->method('getQuoteById')
            ->withConsecutive([$quoteId], [$parentQuoteId])->willReturnOnConsecutiveCalls($quote, $quote);

        $shippingOptionData = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $shippingOptionData
            ->setService('Flat Rate - Fixed')
            ->setCost(5600)
            ->setReference('flatrate_flatrate')
            ->setTaxAmount(0);

        $shippingOptionsData = new \Bolt\Boltpay\Model\Api\Data\ShippingOptions();
        $shippingOptionsData->setShippingOptions([$shippingOptionData]);

        $this->currentMock->method('shippingEstimation')
            ->willReturn($shippingOptionsData);

        $this->currentMock->expects(self::once())->method('couponInvalidForShippingAddress')
            ->withAnyParameters()->willReturn(true);

        self::setProperty($this->currentMock, 'taxAdjusted', true);
        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function (callable $fn) use ($shippingOptionsData) {
                $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                $reportMock->expects(self::once())->method('setMetaData')->with(
                    [
                        'SHIPPING OPTIONS' => [print_r($shippingOptionsData, 1)]
                    ]
                );
                $fn($reportMock);
            }
        );
        $this->bugsnag->expects(self::once())->method('notifyError')
            ->with('Cart Totals Mismatch', "Totals adjusted.");

        $result = $this->currentMock->getShippingMethods($cart, $shippingAddress);

        $this->assertEquals($result, $shippingOptionsData);
    }

    /**
     * @test
     */
    public function getShippingMethods_webApiException()
    {
        $this->initCurrentMock(['preprocessHook', 'getQuoteById'], false);
        $this->currentMock->method('getQuoteById')->willReturn(true);
        $e = new WebapiException(__('Precondition Failed'), 6001, 412);
        $this->currentMock->method('preprocessHook')->willThrowException(
            $e
        );
        $this->expectErrorResponse($e->getCode(), $e->getMessage(), $e->getHttpCode());
        $this->assertNull($this->currentMock->getShippingMethods(['display_id' => '100050001 / ' . 1001], []));
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

        $this->initCurrentMock();

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid email: ' . $shippingAddress['email']);

        $this->cartHelper->expects(self::once())->method('handleSpecialAddressCases')
            ->with($shippingAddress)
            ->willReturn($shippingAddress);

        $result = $this->currentMock->getShippingMethods($cart, $shippingAddress);

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function throwUnknownQuoteIdException()
    {
        $this->initCurrentMock();
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(__('Unknown quote id: %1.', self::QUOTE_ID)->render());
        self::invokePrivateMethod(
            $this->currentMock,
            'throwUnknownQuoteIdException',
            [self::QUOTE_ID]
        );
    }

    /**
     * @test
     */
    public function applyExternalQuoteData_thirdPartyRewards()
    {
        $amRewardsPoint = 100;
        $mirasvitRewardsPoint = 200;
        $this->initCurrentMock();
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(['getAmrewardsPoint'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->discountHelper->expects(self::once())->method('applyExternalDiscountData')->with($quote);
        $quote->expects(self::atLeastOnce())->method('getAmrewardsPoint')->willReturn($amRewardsPoint);

        $this->discountHelper->expects(self::once())->method('getMirasvitRewardsAmount')->with($quote)
        ->willReturn($mirasvitRewardsPoint);

        self::assertEquals(
            $amRewardsPoint.$mirasvitRewardsPoint,
            $this->currentMock->applyExternalQuoteData($quote)
        );
    }

    /**
     * @test
     * @throws \ReflectionException
     */
    public function testDiscountAppliedToShipping()
    {
        $this->setUpRuleFactoryMock();
        $this->initCurrentMock();

        $testMethod = new \ReflectionMethod(BoltShippingMethods::class, 'doesDiscountApplyToShipping');
        $testMethod->setAccessible(true);

        $quoteMock = $this->getQuoteMock([]);

        $this->assertEquals(true, $testMethod->invokeArgs($this->currentMock, [$quoteMock]));
    }

    /**
     * @test
     */
    public function shippingEstimation_withoutEmailForApplePay()
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

        $quote = $this->getQuoteMock($shippingAddress);

        $this->initCurrentMock();

        $result = $this->currentMock->shippingEstimation($quote, $shippingAddressData);

        $this->assertEquals($this->factoryShippingOptionsMock, $result);
    }

    /**
     * @test
     */
    public function shippingEstimation_discountAppliedToShipping()
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

        $quote = $this->getQuoteMock($shippingAddress);

        $this->setUpRuleFactoryMock();
        $this->initCurrentMock();

        $result = $this->currentMock->shippingEstimation($quote, $shippingAddressData);

        $this->assertEquals($this->factoryShippingOptionsMock, $result);
    }

    /**
     * @test
     */
    public function shippingEstimation_cached()
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
        $shippingAddress->expects($this->never())
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
        $shippingAddress->expects($this->never())
            ->method('getGroupedAllShippingRates')
            ->willReturn($shippingRates);

        $quote = $this->getQuoteMock($shippingAddress);

        $this->setUpRuleFactoryMock();
        $this->initCurrentMock();

        $this->cache->expects(self::once())->method('load')->with(self::anything())
            ->willReturn(serialize($this->factoryShippingOptionsMock));

        $result = $this->currentMock->shippingEstimation($quote, $shippingAddressData);

        $this->assertEquals($this->factoryShippingOptionsMock, $result);
    }

    /**
     * @test
     * @throws \ReflectionException
     */
    public function resetShippingCalculationIfNeeded()
    {
        $this->initCurrentMock();

        $this->configHelper->expects(self::once())->method('getResetShippingCalculation')
            ->with(self::STORE_ID)->willReturn(true);

        $shippingAddressMock = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
            ->setMethods(['removeAllShippingRates', 'setCollectShippingRates'])
            ->disableOriginalConstructor()
            ->getMock();

        $shippingAddressMock->expects(self::once())->method('removeAllShippingRates');
        $shippingAddressMock->expects(self::once())->method('setCollectShippingRates')->with(true);

        self::invokePrivateMethod(
            $this->currentMock,
            'resetShippingCalculationIfNeeded',
            [
                $shippingAddressMock,
                self::STORE_ID
            ]
        );
    }

    /**
     * @test
     */
    public function getShippingOptions()
    {
        $this->initCurrentMock();
        $shippingAddress = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
            ->setMethods(
                [
                    'addData',
                    'setCollectShippingRates',
                    'setShippingMethod',
                    'getGroupedAllShippingRates',
                    'getShippingDiscountAmount',
                    'getShippingAmount',
                    'save'
                ]
            )
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

        $quote = $this->getQuoteMock($shippingAddress);
        $addressData = [
            'country_id' => 'US',
            'postcode'   => '10001',
            'region'     => 'New York',
            'city'       => 'New York',
        ];

        $this->currentMock->getShippingOptions($quote, $addressData);
    }

    /**
     * @test
     */
    public function getShippingOptions_discount()
    {
        $shippingDiscountAmount = 10;
        $this->initCurrentMock();
        $shippingAddress = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
            ->setMethods(
                [
                    'addData',
                    'setCollectShippingRates',
                    'setShippingMethod',
                    'getGroupedAllShippingRates',
                    'getShippingDiscountAmount',
                    'getShippingAmount',
                    'save'
                ]
            )
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
            ->willReturn($shippingDiscountAmount);
        $shippingAddress->method('getShippingAmount')
            ->willReturn('5');

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingRates = [['flatrate' => $addressRate]];
        $shippingAddress->expects($this->once())
            ->method('getGroupedAllShippingRates')
            ->willReturn($shippingRates);

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

        $quote = $this->getQuoteMock($shippingAddress);
        $addressData = [
            'country_id' => 'US',
            'postcode'   => '10001',
            'region'     => 'New York',
            'city'       => 'New York',
        ];

        $this->currentMock->getShippingOptions($quote, $addressData);
    }

    /**
     * @test
     */
    public function getShippingOptions_couponCode()
    {
        $this->initCurrentMock();
        $shippingAddress = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
            ->setMethods(
                [
                    'addData',
                    'setCollectShippingRates',
                    'setShippingMethod',
                    'getGroupedAllShippingRates',
                    'getShippingDiscountAmount',
                    'getShippingAmount',
                    'save'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $shippingAddress->method('setShippingMethod')
            ->withAnyParameters()
            ->willReturnSelf();
        $shippingAddress->method('save')
            ->willReturnSelf();
        $shippingAddress->expects($this->exactly(2))
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

        $quote = $this->getQuoteMock($shippingAddress);
        self::setProperty($quote, '_data', ['coupon_code' => '123']);

        $quote->expects(self::exactly(2))->method('setCouponCode')->withConsecutive([''], ['123'])
            ->willReturnSelf();

        $addressData = [
            'country_id' => 'US',
            'postcode'   => '10001',
            'region'     => 'New York',
            'city'       => 'New York',
        ];

        $this->currentMock->getShippingOptions($quote, $addressData);
    }


    /**
     * @test
     */
    public function getShippingOptions_virtual()
    {
        $taxAmount = 10;
        $this->initCurrentMock();

        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(['isVirtual', 'getBillingAddress', 'collectTotals'])
            ->disableOriginalConstructor()
            ->getMock();

        $quote->expects(self::once())->method('isVirtual')->willReturn(true);

        $billingAddress = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
            ->setMethods(
                [
                    'addData',
                    'getTaxAmount'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $billingAddress->expects(self::once())->method('getTaxAmount')->willReturn($taxAmount);
        $quote->expects(self::once())->method('getBillingAddress')->willReturn($billingAddress);
        $quote->expects(self::once())->method('collectTotals');

        $this->totalsCollector->expects(self::once())->method('collectAddressTotals')
            ->with($quote, $billingAddress);

        $this->shippingOptionInterfaceFactory->method('create')
            ->willReturnSelf();
        $this->shippingOptionInterfaceFactory->method('setService')
            ->with(BoltShippingMethods::NO_SHIPPING_SERVICE)
            ->willReturnSelf();
        $this->shippingOptionInterfaceFactory->method('setCost')
            ->with(0)
            ->willReturnSelf();
        $this->shippingOptionInterfaceFactory->method('setReference')
            ->with(BoltShippingMethods::NO_SHIPPING_REFERENCE)
            ->willReturnSelf();
        $this->shippingOptionInterfaceFactory->method('setTaxAmount')
            ->with($taxAmount * 100)
            ->willReturnSelf();

        $addressData = [
            'country_id' => 'US',
            'postcode'   => '10001',
            'region'     => 'New York',
            'city'       => 'New York',
        ];

        $this->currentMock->getShippingOptions($quote, $addressData);
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

        $this->initCurrentMock();

        $reflection = new \ReflectionClass($this->currentMock);
        $reflectionProperty = $reflection->getProperty('quote');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->currentMock, $immutableQuoteMock);

        $testMethod = new \ReflectionMethod(BoltShippingMethods::class, 'couponInvalidForShippingAddress');
        $testMethod->setAccessible(true);

        $this->assertTrue($testMethod->invokeArgs($this->currentMock, [$parentQuoteCoupon]));
    }

    /**
     * @test
     * @covers ::checkCartItems
     */
    public function checkCartItems_noQuoteItems()
    {
        $this->initCurrentMock();
        $quote = $this->createPartialMock(Quote::class, ['getAllVisibleItems']);
        $quote->expects(self::once())->method('getAllVisibleItems')->willReturn([]);
        self::setProperty($this->currentMock, 'quote', $quote);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The Cart is empty.');

        self::invokePrivateMethod(
            $this->currentMock,
            'checkCartItems',
            [
                [
                    'items' => []
                ]
            ]
        );
    }

    /**
     * @test
     * @covers ::checkCartItems
     */
    public function checkCartItems_totalsMismatch()
    {
        $cart = [
            'items' => [
                [
                    'sku'          => 'TestProduct2',
                    'quantity'     => 5,
                    'total_amount' => 100
                ]
            ]
        ];
        $this->initCurrentMock();
        $quote = $this->getQuoteMock([]);
        self::setProperty($this->currentMock, 'quote', $quote);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Cart Items data data has changed.');

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function (callable $callback) use ($quote, $cart) {
                $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                $reportMock->expects(self::once())
                    ->method('setMetaData')->with(
                        [
                            'CART_MISMATCH' => [
                                'cart_items'  => $cart['items'],
                                'quote_items' => null,
                            ]
                        ]
                    );
                $callback($reportMock);
            }
        );


        self::invokePrivateMethod(
            $this->currentMock,
            'checkCartItems',
            [
                $cart
            ]
        );
    }


    private function expectErrorResponse($errCode, $message, $httpStatusCode)
    {
        $body = [
            'status' => 'failure',
            'error'  => [
                'code'    => $errCode,
                'message' => $message,
            ],
        ];
        $this->errorResponse->expects(self::once())->method('prepareErrorMessage')
            ->with($errCode, $message)->willReturn($body);
        $this->response->expects(self::once())->method('setHttpResponseCode')
            ->with($httpStatusCode);

        $this->response->expects(self::once())->method('setBody')
            ->with($body);
        $this->response->expects(self::once())->method('sendResponse');
    }

    /**
     * Get quote mock with quote items
     *
     * @param $shippingAddress
     * @param $quoteId
     * @param $parentQuoteId
     * @return MockObject
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
            'getQuoteCurrencyCode', 'getStoreId', 'setCouponCode', 'save'
        ];
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $quote->method('getId')
            ->willReturn($quoteId);
        // TODO: need to cover multi-website cases where different store_id
//        $quote->method('getStoreId')
//            ->willReturn(null);
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
        $quote->method('save')
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
            ->setMethods(['create', 'setService', 'setCost', 'setReference', 'setTaxAmount'])
            ->disableOriginalConstructor()
            ->getMock();

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

    private function initCurrentMock($methods = [], $enableProxyingToOriginalMethods = true)
    {
        $buider = $this->getMockBuilder(BoltShippingMethods::class)
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
            ->setMethods($methods);

        if($enableProxyingToOriginalMethods) {
            $buider->enableProxyingToOriginalMethods();
        }

        $this->currentMock = $buider->getMock();
    }

    /**
     * Invoke a private method of an object.
     *
     * @param object $object
     * @param string $method
     * @param array $args
     * @param string|null $class
     * @return mixed
     * @throws \ReflectionException
     */
    public static function invokePrivateMethod($object, $method, $args = [], $class = null)
    {
        if (is_null($class)) {
            $class = $object;
        }

        $method = new \ReflectionMethod($class, $method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    public static function setProperty($object, $property, $value)
    {
        $reflection = new \ReflectionClass(($object instanceof MockObject) ? get_parent_class($object) : $object);
        $reflectionProperty = $reflection->getProperty($property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $value);
    }
}
