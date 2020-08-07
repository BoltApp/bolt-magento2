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

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Model\Api\ShippingMethods as BoltShippingMethods;
use Bolt\Boltpay\Test\Unit\TestHelper;
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
use Magento\Store\Model\Store;
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
    const PARENT_QUOTE_ID = 1000;
    const IMMUTABLE_QUOTE_ID = 1001;
    const INCREMENT_ID = 100050001;
    const DISPLAY_ID = self::INCREMENT_ID . ' / ' . self::IMMUTABLE_QUOTE_ID;
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
     * @var ShippingOptionInterfaceFactory|MockObject
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
     * @var \Magento\Quote\Model\Cart\ShippingMethod|MockObject
     */
    private $shipMethodObject;

    /**
     * @var Store
     */
    private $storeMock;

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
            ->setMethods([
                'getPrefetchShipping',
                'getResetShippingCalculation',
                'getIgnoredShippingAddressCoupons',
                'isPickupInStoreShippingMethodCode',
                'getPickupAddressData',
                'setAddressToInStoreAddress'
            ])
            ->disableOriginalConstructor()
            ->getMock();
        $this->configHelper->method('getPrefetchShipping')
            ->withAnyParameters()
            ->willReturn(true);
        $this->configHelper->method('getIgnoredShippingAddressCoupons')
            ->willReturn([]);

        $this->shipMethodObject = $this->getMockBuilder(\Magento\Quote\Model\Cart\ShippingMethod::class)
            ->setMethods(
                [
                'getCarrierCode', 'getMethodCode', 'getMethodTitle', 'getCarrierTitle',
                'getAmount', 'getBaseAmount', 'getErrorMessage'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $this->shipMethodObject->method('getCarrierCode')
            ->willReturn('flatrate');
        $this->shipMethodObject->method('getMethodCode')
            ->willReturn('flatrate');
        $this->shipMethodObject->method('getMethodTitle')
            ->willReturn('Fixed');
        $this->shipMethodObject->method('getCarrierTitle')
            ->willReturn('Flate Rate');
        $this->shipMethodObject->method('getAmount')
            ->willReturn((int)5);
        $this->shipMethodObject->method('getBaseAmount')
            ->willReturn((int)5);

        $this->converter = $this->getMockBuilder(ShippingMethodConverter::class)
            ->setMethods(['modelToDataObject'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->converter->method('modelToDataObject')
            ->withAnyParameters()
            ->willReturn($this->shipMethodObject);

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
        $this->priceHelper->method('currency')->willReturnArgument(0);
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
    public function getShippingMethods_emptyQuote()
    {
        $cart = [
            'display_id' => self::DISPLAY_ID
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

        $this->initCurrentMock(['catchExceptionAndSendError']);

        $exception =  new BoltException(
            __('Unknown quote id: %1.', self::IMMUTABLE_QUOTE_ID)
        );

        $this->currentMock->expects(self::once())->method('catchExceptionAndSendError')
            ->with($exception, "Something went wrong with your cart. Please reload the page and checkout again.", 6103);

        $result = $this->currentMock->getShippingMethods($cart, $shippingAddress);

        // If another exception happens, the test will fail.
        $this->assertNull($result);
    }

    /**
     * @test
     * @dataProvider getShippingMethods_dataProvider
     */
    public function getShippingMethods_fullAddressData($cart, $parentQuoteId)
    {
        $shippingAddress = [
            'company'         => "",
            'country'         => "United States",
            'country_code'    => "US",
            'email'           => "integration@bolt.com",
            'first_name'      => "YevhenBolt",
            'last_name'       => "BoltTest2",
            'locality'        => "New York",
            'phone'           => "2312311234",
            'postal_code'     => "10001",
            'region'          => "New York",
            'street_address1' => "228 5th Avenue",
            'street_address2' => "",
        ];

        $quote = $this->getQuoteMock($shippingAddress);
        $quote->method('getStoreId')->willReturn(self::STORE_ID);

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
            'shippingEstimation', 'couponInvalidForShippingAddress'
        ];

        $this->sessionHelper->expects(self::once())->method('loadSession')->willReturn(null);

        $this->initCurrentMock($methods, false);

        $this->hookHelper->method('preProcessWebhook')
            ->withAnyParameters()
            ->willReturnSelf();
        $this->currentMock->expects(self::once())->method('checkCartItems')->with($cart)->willReturn(null);

        $this->currentMock->expects(self::exactly(2))->method('getQuoteById')
            ->withConsecutive([self::IMMUTABLE_QUOTE_ID], [$parentQuoteId])
            ->willReturnOnConsecutiveCalls($quote, $quote);

        $this->storeMock->expects(self::once())->method('setCurrentCurrencyCode')->with("USD");

        $shippingOptions = $this->getShippingOptions();

        $this->currentMock->method('shippingEstimation')
            ->willReturn($shippingOptions);

        $this->currentMock->expects(self::once())->method('couponInvalidForShippingAddress')
            ->withAnyParameters()->willReturn(false);

        $result = $this->currentMock->getShippingMethods($cart, $shippingAddress);

        $this->assertTrue(HookHelper::$fromBolt);
        $this->assertEquals($shippingOptions, $result);
    }

    public function getShippingMethods_dataProvider()
    {
        return [
            // common case
            [[
                'display_id'      => self::DISPLAY_ID,
                'order_reference' => self::PARENT_QUOTE_ID
            ],self::PARENT_QUOTE_ID],
            // product page checkout case
            [[
                'display_id'      => self::DISPLAY_ID,
                'order_reference' => self::IMMUTABLE_QUOTE_ID
            ],self::IMMUTABLE_QUOTE_ID]
        ];
    }

    /**
     * @test
     * @dataProvider getShippingMethods_dataProvider
     */
    public function getShippingMethods_taxAdjustedAndInvalidCoupon($cart, $parentQuoteId)
    {
        $shippingAddress = [
            'company'         => "",
            'country'         => "United States",
            'country_code'    => "US",
            'email'           => "integration@bolt.com",
            'first_name'      => "YevhenBolt",
            'last_name'       => "BoltTest2",
            'locality'        => "New York",
            'phone'           => "2312311234",
            'postal_code'     => "10001",
            'region'          => "New York",
            'street_address1' => "228 5th Avenue",
            'street_address2' => "",
        ];

        $quote = $this->getQuoteMock($this->shippingAddressMock);
        $quote->method('getStoreId')->willReturn(self::STORE_ID);

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
            ->withConsecutive([self::IMMUTABLE_QUOTE_ID], [$parentQuoteId])
            ->willReturnOnConsecutiveCalls($quote, $quote);

        $shippingOptions = $this->getShippingOptions();

        $this->currentMock->method('shippingEstimation')
            ->willReturn($shippingOptions);

        $this->currentMock->expects(self::once())->method('couponInvalidForShippingAddress')
            ->withAnyParameters()->willReturn(true);

        self::setInaccessibleProperty($this->currentMock, 'taxAdjusted', true);
        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function (callable $fn) use ($shippingOptions) {
                $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                $reportMock->expects(self::once())->method('setMetaData')->with(
                    [
                        'SHIPPING OPTIONS' => [print_r($shippingOptions, 1)]
                    ]
                );
                $fn($reportMock);
            }
        );
        $this->bugsnag->expects(self::once())->method('notifyError')
            ->with('Cart Totals Mismatch', "Totals adjusted.");

        $result = $this->currentMock->getShippingMethods($cart, $shippingAddress);

        $this->assertEquals($shippingOptions, $result);
    }

    /**
     * @test
     */
    public function getShippingMethods_webApiException()
    {
        $this->initCurrentMock(['preprocessHook', 'getQuoteById'], false);
        $this->currentMock->method('getQuoteById')->willReturn(true);
        $e = new WebapiException(__('Precondition Failed'), 6001, 412);
        $this->currentMock->method('preprocessHook')->willThrowException($e);
        $this->expectErrorResponse($e->getCode(), $e->getMessage(), $e->getHttpCode());
        $this->assertNull($this->currentMock->getShippingMethods(['display_id' => self::DISPLAY_ID], []));
    }

    /**
     * @test
     */
    public function getShippingMethods_incorrectEmail()
    {
        $cart = [
            'display_id' => self::DISPLAY_ID,
            'items'      => [
                [
                    'sku'      => 'TestProduct',
                    'quantity' => '2',
                    'total_amount' => '60000'
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
            'phone'           => "2312311234",
            'postal_code'     => "10001",
            'region'          => "New York",
            'street_address1' => "228 5th Avenue",
            'street_address2' => "",
        ];

        $quote = $this->getQuoteMock($shippingAddress);
        $this->cartHelper->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn($quote);

        $this->cartHelper->expects(self::once())->method('handleSpecialAddressCases')
            ->with($shippingAddress)
            ->willReturn($shippingAddress);

        $this->cartHelper->method('validateEmail')
            ->with($shippingAddress['email'])
            ->willReturn(false);

        $this->initCurrentMock(['sendErrorResponse']);

        $this->bugsnag->expects(self::once())->method('notifyException');

        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(
                BoltErrorResponse::ERR_UNIQUE_EMAIL_REQUIRED,
                'Invalid email: ' . $shippingAddress['email'],
                422
            );

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
        $this->expectExceptionMessage(__('Unknown quote id: %1.', self::IMMUTABLE_QUOTE_ID)->render());
        self::invokeInaccessibleMethod(
            $this->currentMock,
            'throwUnknownQuoteIdException',
            [self::IMMUTABLE_QUOTE_ID]
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
    public function doesDiscountApplyToShipping()
    {
        $this->setUpRuleFactoryMock();
        $this->initCurrentMock();

        $quoteMock = $this->getQuoteMock([]);

        $this->assertTrue(
            self::invokeInaccessibleMethod(
                $this->currentMock,
                'doesDiscountApplyToShipping',
                [$quoteMock]
            )
        );
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

        $shippingAddress = $this->getShippingAddressMock(5, 0);

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingRates = [['flatrate' => $addressRate]];
        $shippingAddress->expects($this->once())
            ->method('getGroupedAllShippingRates')
            ->willReturn($shippingRates);

        $this->setupShippingOptionFactory(
            'Flate Rate - Fixed',
            'flatrate_flatrate',
            500,
            0
        );

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
            'phone'           => "2312311234",
            'postal_code'     => "37921",
            'region'          => "Tennessee",
            'street_address1' => "4553 Annalee Way",
            'street_address2' => "",
        ];

        $this->cartHelper->method('validateEmail')
            ->with($email)
            ->willReturn(true);

        $shippingDiscountAmount = 10;
        $shippingAddress = $this->getShippingAddressMock(15, $shippingDiscountAmount);

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingRates = [['flatrate' => $addressRate]];
        $shippingAddress->expects($this->once())
            ->method('getGroupedAllShippingRates')
            ->willReturn($shippingRates);

        $this->setupShippingOptionFactory(
            "Flate Rate - Fixed [{$shippingDiscountAmount} discount]",
            'flatrate_flatrate',
            500,
            0
        );

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
            'phone'           => "2312311234",
            'postal_code'     => "37921",
            'region'          => "Tennessee",
            'street_address1' => "4553 Annalee Way",
            'street_address2' => "",
        ];

        $this->cartHelper->method('validateEmail')
            ->with($email)
            ->willReturn(true);

        $shippingAddress = $this->getShippingAddressMock(5, 0);

        $shippingAddress->expects($this->never())
            ->method('setCollectShippingRates');

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingRates = [['flatrate' => $addressRate]];
        $shippingAddress->expects($this->never())
            ->method('getGroupedAllShippingRates');

        $quote = $this->getQuoteMock($shippingAddress);

        $this->setUpRuleFactoryMock();
        $this->initCurrentMock();



        $this->cache->expects(self::once())->method('load')->with(self::anything())
            ->willReturn(TestHelper::serialize($this, $this->factoryShippingOptionsMock));

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

        self::invokeInaccessibleMethod(
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
    public function shippingEstimation_freeShippingDiscount()
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
            'phone'           => "2312311234",
            'postal_code'     => "37921",
            'region'          => "Tennessee",
            'street_address1' => "4553 Annalee Way",
            'street_address2' => "",
        ];

        $this->cartHelper->method('validateEmail')
            ->with($email)
            ->willReturn(true);

        $shippingAddress = $this->getShippingAddressMock(15, 15);

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingRates = [['flatrate' => $addressRate]];
        $shippingAddress->expects($this->once())
            ->method('getGroupedAllShippingRates')
            ->willReturn($shippingRates);

        $this->setupShippingOptionFactory(
            "Flate Rate - Fixed [free shipping discount]",
            'flatrate_flatrate',
            0,
            0
        );

        $quote = $this->getQuoteMock($shippingAddress);

        $this->setUpRuleFactoryMock();
        $this->initCurrentMock();

        $result = $this->currentMock->shippingEstimation($quote, $shippingAddressData);

        $this->assertEquals($this->factoryShippingOptionsMock, $result);
    }

    /**
     * @test
     */
    public function getShippingOptions_error()
    {
        $this->initCurrentMock(['resetShippingCalculationIfNeeded'], true);
        $shippingAddress = $this->getShippingAddressMock(10, 0);

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->setMethods(['getErrorMessage'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->shipMethodObject->method('getErrorMessage')->willReturn('Error');

        $shippingRates = [['flatrate' => $addressRate]];
        $shippingAddress->expects($this->once())
            ->method('getGroupedAllShippingRates')
            ->willReturn($shippingRates);

        $this->setupShippingOptionFactory(
            'Flate Rate - Fixed',
            'flatrate_flatrate',
            1000,
            0
        );

        $quote = $this->getQuoteMock($shippingAddress);
        $addressData = [
            'country_id' => 'US',
            'postcode'   => '10001',
            'region'     => 'New York',
            'city'       => 'New York',
        ];

        $this->bugsnag->expects(self::atLeastOnce())->method('registerCallback')->willReturnCallback(
            function (callable $callback) {
                $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                $reportMock->expects(self::once())->method('setMetaData');
                $callback($reportMock);
            }
        );

        $this->expectException(BoltException::class);
        $this->expectExceptionMessage('No Shipping Methods retrieved');
        $this->expectExceptionCode(BoltErrorResponse::ERR_SERVICE);

        self::setInaccessibleProperty($this->currentMock, 'threshold', 0);
        $this->currentMock->getShippingOptions($quote, $addressData);
        self::setInaccessibleProperty($this->currentMock, 'threshold', 1);
    }

    /**
     * @test
     */
    public function getShippingOptions_couponCode()
    {
        $this->initCurrentMock();

        $shippingAddress = $this->getShippingAddressMock(5, 0);

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingRates = [['flatrate' => $addressRate]];
        $shippingAddress->expects($this->once())
            ->method('getGroupedAllShippingRates')
            ->willReturn($shippingRates);

        $this->setupShippingOptionFactory(
            'Flate Rate - Fixed',
            'flatrate_flatrate',
            500,
            0
        );

        $quote = $this->getQuoteMock($shippingAddress);
        $quote->method('getCouponCode')->willReturn(123);

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
    public function getShippingOptions_IfStorePickupMethodExist()
    {
        $this->initCurrentMock();

        $shippingAddress = $this->getShippingAddressMock(5, 0);

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingRates = [['flatrate' => $addressRate]];
        $shippingAddress->expects($this->once())
            ->method('getGroupedAllShippingRates')
            ->willReturn($shippingRates);

        $this->setupShippingOptionFactory(
            'Flate Rate - Fixed',
            'flatrate_flatrate',
            500,
            0
        );
        $this->configHelper->expects(self::exactly(2))->method('isPickupInStoreShippingMethodCode')->with('flatrate_flatrate')->willReturn(true);
        $this->configHelper->expects(self::once())->method('getPickupAddressData')->willReturn([
            'city' => 'Knoxville',
            'country_id' => 'US',
            'postcode' => '37921',
            'region_code' => 'TN',
            'region_id' => '56',
            'street' => '4535 ANNALEE Way
Room 4000',
        ]);

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
     * that getShippingOptions unsets shipping amount for discount when discount applies to shipping
     * before collecting shipping totals to allow {@see \Magento\SalesRule\Model\Validator::processShippingAmount}
     * to correctly process shipping discount
     *
     * @covers ::getShippingOptions
     */
    public function getShippingOptions_whenDiscountAppliesToShipping_unsetsShippingAmountForDiscount()
    {
        $this->initCurrentMock(['doesDiscountApplyToShipping'], false);

        $shippingAddress = $this->getShippingAddressMock(5, 2.34);

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingRates = [['flatrate' => $addressRate]];
        $shippingAddress->expects(static::once())->method('getGroupedAllShippingRates')
            ->willReturn($shippingRates);
        $shippingAddress->expects(static::once())->method('unsShippingAmountForDiscount')
            ->willReturn($shippingRates);
        $shippingAddress->expects(static::once())->method('unsBaseShippingAmountForDiscount')
            ->willReturn($shippingRates);

        $this->setupShippingOptionFactory(
            'Flate Rate - Fixed [2.34 discount]',
            'flatrate_flatrate',
            266,
            0
        );

        $quote = $this->getQuoteMock($shippingAddress);
        $quote->method('getCouponCode')->willReturn(123);

        $quote->expects(self::exactly(2))->method('setCouponCode')->withConsecutive([''], ['123'])
            ->willReturnSelf();

        $this->currentMock->expects(static::once())->method('doesDiscountApplyToShipping')->with($quote)
            ->willReturn(true);

        $this->totalsCollector->expects(static::exactly(4))->method('collectAddressTotals')
            ->with($quote, $shippingAddress);

        $addressData = [
            'country_id' => 'US',
            'postcode'   => '10001',
            'region'     => 'New York',
            'city'       => 'New York',
        ];

        $this->currentMock->getShippingOptions($quote, $addressData);
    }

    /**
     * Setup method for {@see getShippingOptions_afterShippingEstimation_collectsAddressTotalsWithEmptyShippingMethod}
     *
     * @return MockObject[]|\Magento\Quote\Model\Quote\Address[]|Quote[] containing shipping address and quote mocks
     */
    private function getShippingOptions_afterShippingEstimationSetUp()
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
                    'unsShippingAmountForDiscount',
                    'unsBaseShippingAmountForDiscount',
                    'save'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $shippingAddress->method('save')->willReturnSelf();
        $shippingAddress->method('setCollectShippingRates')->with(true)->willReturnSelf();
        $shippingAddress->method('getShippingDiscountAmount')->willReturn(0);
        $shippingAddress->method('getShippingAmount')->willReturn(5);

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->disableOriginalConstructor()
            ->getMock();
        $shippingRates = [['flatrate' => $addressRate]];
        $shippingAddress->expects(static::once())->method('getGroupedAllShippingRates')->willReturn($shippingRates);
        $this->setupShippingOptionFactory(
            'Flate Rate - Fixed',
            'flatrate_flatrate',
            500,
            0
        );
        $quote = $this->getQuoteMock($shippingAddress);
        return [$shippingAddress, $quote];
    }

    /**
     * @test
     * that getShippingOptions collects address totals after setting it back to null after shipping estimation.
     * After totals collection the address is saved.
     *
     * @covers ::getShippingOptions
     */
    public function getShippingOptions_afterShippingEstimation_collectsAddressTotalsWithEmptyShippingMethod()
    {
        list($shippingAddress, $quote) = $this->getShippingOptions_afterShippingEstimationSetUp();

        //used to communicate between callback closures
        $storage = new \Magento\Framework\DataObject();

        $shippingAddress->expects(static::exactly(3))->method('setShippingMethod')
            ->withConsecutive([null], ['flatrate_flatrate'], [null])
            ->willReturnCallback(
                function ($shippingMethod) use ($storage) {
                    $storage->setCurrentShippingMethod($shippingMethod);
                }
            );

        $collectAddressTotalsMatcher = self::exactly(3);
        $this->totalsCollector->expects($collectAddressTotalsMatcher)->method('collectAddressTotals')
            ->willReturnCallback(
                function () use ($storage, $collectAddressTotalsMatcher) {
                    $invocationCountToShippingMethod = [
                        1 => null,
                        2 => 'flatrate_flatrate',
                        3 => null
                    ];

                    $invocationCount = $collectAddressTotalsMatcher->getInvocationCount();
                    if ($invocationCountToShippingMethod[$invocationCount] !== $storage->getCurrentShippingMethod()) {
                        throw new \PHPUnit\Framework\ExpectationFailedException(
                            'Wrong shipping method during address total collection'
                        );
                    }
                }
            );
        $shippingAddress->expects(static::once())->method('save')->willReturnCallback(
            function () use ($collectAddressTotalsMatcher) {
                if ($collectAddressTotalsMatcher->getInvocationCount() !== 3) {
                    throw new \PHPUnit\Framework\ExpectationFailedException(
                        'Shipping address save called too early'
                    );
                }
            }
        );

        $this->currentMock->getShippingOptions(
            $quote,
            [
                'country_id' => 'US',
                'postcode'   => '10001',
                'region'     => 'New York',
                'city'       => 'New York',
            ]
        );
    }

    /**
     * @test
     */
    public function getShippingOptions_virtual()
    {
        $taxAmount = 10;
        $this->initCurrentMock();

        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(['isVirtual', 'getBillingAddress', 'collectTotals', 'getQuoteCurrencyCode'])
            ->disableOriginalConstructor()
            ->getMock();
        $quote->method("getQuoteCurrencyCode")->willReturn("USD");
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

        $this->setupShippingOptionFactory(
            BoltShippingMethods::NO_SHIPPING_SERVICE,
            BoltShippingMethods::NO_SHIPPING_REFERENCE,
            0,
            $taxAmount * 100
        );

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
    public function couponInvalidForShippingAddress()
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

        self::setInaccessibleProperty(
            $this->currentMock,
            'quote',
            $immutableQuoteMock
        );

        $this->assertTrue(
            self::invokeInaccessibleMethod(
                $this->currentMock,
                'couponInvalidForShippingAddress',
                [$parentQuoteCoupon]
            )
        );
    }

    /**
     * @test
     * @covers ::checkCartItems
     */
    public function checkCartItems_noQuoteItems()
    {
        $this->initCurrentMock();
        $quote = $this->createPartialMock(Quote::class, ['getAllVisibleItems','getTotals']);
        $quote->expects(self::once())->method('getAllVisibleItems')->willReturn([]);
        $quote->expects(self::once())->method('getTotals')->willReturnSelf();
        self::setInaccessibleProperty($this->currentMock, 'quote', $quote);

        $this->expectException(BoltException::class);
        $this->expectExceptionCode(6103);
        $this->expectExceptionMessage('The cart is empty. Please reload the page and checkout again.');

        self::invokeInaccessibleMethod(
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
        self::setInaccessibleProperty($this->currentMock, 'quote', $quote);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionCode(6103);
        $this->expectExceptionMessage('Something in your cart has changed and needs to be revised. Please reload the page and checkout again.');

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function (callable $callback) use ($quote, $cart) {
                $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                $reportMock->expects(self::once())
                    ->method('setMetaData')->with(
                        [
                            'CART_MISMATCH' => [
                                'cart_total' => [ 'TestProduct2' => 100 ],
                                'quote_total' => ['TestProduct' => 60000 ],
                                'cart_items'  => $cart['items'],
                                'quote_items' => null,
                            ]
                        ]
                    );
                $callback($reportMock);
            }
        );

        self::invokeInaccessibleMethod(
            $this->currentMock,
            'checkCartItems',
            [
                $cart
            ]
        );
    }

    /**
     * @test
     * @covers ::checkCartItems
     * @doesNotPerformAssertions
     */
    public function checkCartItems_noTotalsMismatchForRoundingError()
    {
        $cart = [
            'display_id' => self::DISPLAY_ID,
            'items'      => [
                [
                    'sku'      => 'TestProduct',
                    'quantity' => '2',
                    'total_amount' => 60000 // round(299.995) * 2, not round(299.995 * 2)
                ]
            ]
        ];
        $this->initCurrentMock();
        $quote = $this->getQuoteMock([]);
        self::setInaccessibleProperty($this->currentMock, 'quote', $quote);

        // no exception expected

        self::invokeInaccessibleMethod(
            $this->currentMock,
            'checkCartItems',
            [
                $cart
            ]
        );
    }

    /**
     * @param $errCode
     * @param $message
     * @param $httpStatusCode
     */
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
     * @param  $shippingAddress
     * @param  $quoteId
     * @param  $parentQuoteId
     * @return MockObject
     */
    private function getQuoteMock(
        $shippingAddress,
        $quoteId = self::IMMUTABLE_QUOTE_ID,
        $parentQuoteId = self::PARENT_QUOTE_ID
    ) {
        $this->storeMock = $this->getMockBuilder(Store::class)
            ->setMethods(['setCurrentCurrencyCode'])
            ->disableOriginalConstructor()
            ->getMock();

        $quoteItem = $this->getMockBuilder(\Magento\Quote\Model\Quote\Item::class)
            ->setMethods(['getSku', 'getQty', 'getCalculationPrice'])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteItem->method('getSku')
            ->willReturn('TestProduct');
        $quoteItem->method('getQty')
              ->willReturn(2);
        $quoteItem->method('getCalculationPrice')
              ->willReturn(299.995);


        $quoteMethods = [
            'getId', 'getBoltParentQuoteId', 'getSubtotal', 'getAllVisibleItems',
            'getAppliedRuleIds', 'isVirtual', 'getShippingAddress', 'collectTotals',
            'getQuoteCurrencyCode', 'getStoreId', 'setCouponCode', 'save', 'getCouponCode', 'getStore','getTotals'
        ];
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $quote->method('getId')
            ->willReturn($quoteId);
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
            ->willReturn('USD');
        $quote->method('collectTotals')
            ->willReturnSelf();
        $quote->method('save')
              ->willReturnSelf();
        $quote->method('getTotals')
            ->willReturnSelf();
        $quote->method('getStore')
              ->willReturn($this->storeMock);

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

    /**
     * @param array $methods
     * @param bool  $enableProxyingToOriginalMethods
     */
    private function initCurrentMock($methods = [], $enableProxyingToOriginalMethods = true)
    {
        $builder = $this->getMockBuilder(BoltShippingMethods::class)
            ->setConstructorArgs(
                [
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
                ]
            )
            ->setMethods($methods);

        if ($enableProxyingToOriginalMethods) {
            $builder->enableProxyingToOriginalMethods();
        }

        $this->currentMock = $builder->getMock();
    }

    /**
     * Invoke a private method of an object.
     *
     * @param  object      $object
     * @param  string      $method
     * @param  array       $args
     * @param  string|null $class
     * @return mixed
     * @throws \ReflectionException
     */
    private static function invokeInaccessibleMethod($object, $method, $args = [], $class = null)
    {
        if (is_null($class)) {
            $class = $object;
        }

        $method = new \ReflectionMethod($class, $method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    /**
     * @param  $object
     * @param  $property
     * @param  $value
     * @throws \ReflectionException
     */
    private static function setInaccessibleProperty($object, $property, $value)
    {
        $reflection = new \ReflectionClass(
            ($object instanceof MockObject) ? get_parent_class($object) : $object
        );
        $reflectionProperty = $reflection->getProperty($property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $value);
    }

    /**
     * @param $service
     * @param $reference
     * @param $cost
     * @param $taxAmount
     */
    private function setupShippingOptionFactory($service, $reference, $cost, $taxAmount)
    {
        $this->shippingOptionInterfaceFactory->method('create')
            ->willReturnSelf();
        $this->shippingOptionInterfaceFactory->method('setService')
            ->willReturnSelf();
        $this->shippingOptionInterfaceFactory->method('setCost')
            ->with($cost)
            ->willReturnSelf();
        $this->shippingOptionInterfaceFactory->method('setReference')
            ->with($reference)
            ->willReturnSelf();
        $this->shippingOptionInterfaceFactory->method('setTaxAmount')
            ->with($taxAmount)
            ->willReturnSelf();
    }

    /**
     * @param  float $amount
     * @param  float $discountAmount
     * @return MockObject
     */
    private function getShippingAddressMock($amount, $discountAmount)
    {
        $shippingAddress = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
            ->setMethods(
                [
                    'addData',
                    'setCollectShippingRates',
                    'setShippingMethod',
                    'getGroupedAllShippingRates',
                    'getShippingDiscountAmount',
                    'getShippingAmount',
                    'unsShippingAmountForDiscount',
                    'unsBaseShippingAmountForDiscount',
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
        $shippingAddress->method('setCollectShippingRates')
            ->with(true)
            ->willReturnSelf();
        $shippingAddress->method('getShippingDiscountAmount')
            ->willReturn($discountAmount);
        $shippingAddress->method('getShippingAmount')
            ->willReturn($amount);
        return $shippingAddress;
    }

    private function getShippingOptions()
    {
        $shippingOptionData = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $shippingOptionData
            ->setService('Flat Rate - Fixed')
            ->setCost(5600)
            ->setReference('flatrate_flatrate')
            ->setTaxAmount(0);

        $shippingOptionsData = new \Bolt\Boltpay\Model\Api\Data\ShippingOptions();
        $shippingOptionsData->setShippingOptions([$shippingOptionData]);
        return $shippingOptionsData;
    }
}
