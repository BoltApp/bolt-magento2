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

use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Store\Model\Store;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Model\Api\ShippingTaxContext;
use PHPUnit\Framework\MockObject\MockObject;
use Bolt\Boltpay\Model\Api\ShippingTax;
use Bolt\Boltpay\Test\Unit\TestHelper;

/**
 * Class ShippingTaxTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\ShippingTax
 */
class ShippingTaxTest extends TestCase
{
    const PARENT_QUOTE_ID = 1000;
    const IMMUTABLE_QUOTE_ID = 1001;
    const INCREMENT_ID = 100050001;
    const DISPLAY_ID = self::INCREMENT_ID . ' / ' . self::IMMUTABLE_QUOTE_ID;
    const STORE_ID = 1;
    const CURRENCY_CODE = 'USD';
    const EMAIL = 'integration@bolt.com';

    /**
     * @var HookHelper|MockObject
     */
    private $hookHelper;

    /**
     * @var CartHelper|MockObject
     */
    private $cartHelper;

    /**
     * @var LogHelper|MockObject
     */
    private $logHelper;

    /**
     * @var ConfigHelper|MockObject
     */
    private $configHelper;

    /**
     * @var SessionHelper|MockObject
     */
    private $sessionHelper;

    /**
     * @var DiscountHelper|MockObject
     */
    private $discountHelper;

    /**
     * @var Bugsnag|MockObject
     */
    private $bugsnag;

    /**
     * @var MetricsClient|MockObject
     */
    private $metricsClient;

    /**
     * @var BoltErrorResponse|MockObject
     */
    private $errorResponse;

    /**
     * @var RegionModel|MockObject
     */
    private $regionModel;

    /**
     * @var Response|MockObject
     */
    private $response;

    /**
     * @var ShippingTaxContext|MockObject
     */
    private $shippingTaxContext;

    /**
     * @var ShippingOptionInterfaceFactory|MockObject
     */
    private $shippingOptionFactory;

    /**
     * @var Store|MockObject
     */
    private $store;

    /**
     * @var ShippingTax|MockObject
     */
    private $currentMock;

    protected function setUp()
    {
        $this->hookHelper = $this->createMock(HookHelper::class);
        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->sessionHelper = $this->createMock(SessionHelper::class);
        $this->discountHelper = $this->createMock(DiscountHelper::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->metricsClient = $this->createMock(MetricsClient::class);
        $this->errorResponse = $this->createMock(BoltErrorResponse::class);
        $this->regionModel = $this->createMock(RegionModel::class);
        $this->response = $this->createMock(Response::class);
        $this->shippingOptionFactory = $this->createMock(ShippingOptionInterfaceFactory::class);

        $this->shippingTaxContext = $this->getMockBuilder(ShippingTaxContext::class)
            ->setConstructorArgs(
                [
                    $this->hookHelper,
                    $this->cartHelper,
                    $this->logHelper,
                    $this->configHelper,
                    $this->sessionHelper,
                    $this->discountHelper,
                    $this->bugsnag,
                    $this->metricsClient,
                    $this->errorResponse,
                    $this->regionModel,
                    $this->response,
                    $this->shippingOptionFactory
                ]
            )
            ->enableProxyingToOriginalMethods()
            ->getMock();

        $this->store = $this->createMock(Store::class);
    }

    private function initCurrentMock(
        $mockedMethods = [],
        $callOriginalConstructor = true,
        $callOriginalClone = true,
        $callAutoload = true,
        $cloneArguments = false
    ) {
        $this->currentMock = $this->getMockForAbstractClass(
            ShippingTax::class,
            [$this->shippingTaxContext],
            '',
            $callOriginalConstructor,
            $callOriginalClone,
            $callAutoload,
            $mockedMethods,
            $cloneArguments
        );
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
            HookHelper::class,
            'hookHelper',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            CartHelper::class,
            'cartHelper',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            LogHelper::class,
            'logHelper',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            ConfigHelper::class,
            'configHelper',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            SessionHelper::class,
            'sessionHelper',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            DiscountHelper::class,
            'discountHelper',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            Bugsnag::class,
            'bugsnag',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            MetricsClient::class,
            'metricsClient',
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
            Response::class,
            'response',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            ShippingOptionInterfaceFactory::class,
            'shippingOptionFactory',
            $this->currentMock
        );
    }

    /**
     * @test
     * that throwUnknownQuoteIdException would return localized exception
     *
     * @covers ::throwUnknownQuoteIdException
     */
    public function throwUnknownQuoteIdException()
    {
        $this->initCurrentMock();
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(__('Unknown quote id: %1.', self::IMMUTABLE_QUOTE_ID)->render());
        TestHelper::invokeMethod(
            $this->currentMock,
            'throwUnknownQuoteIdException',
            [self::IMMUTABLE_QUOTE_ID]
        );
    }

    /**
     * @test
     * that catchExceptionAndSendError would notify bugsnag and send error response
     *
     * @covers ::catchExceptionAndSendError
     */
    public function catchExceptionAndSendError()
    {
        $this->initCurrentMock(['sendErrorResponse']);
        $e = new \Exception;
        $this->bugsnag->expects(self::once())->method('notifyException')->with($e);
        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(6009, '', 422);
        TestHelper::invokeMethod(
            $this->currentMock,
            'catchExceptionAndSendError',
            [$e]
        );
    }

    /**
     * @test
     * that preprocessHook would forward to hook helper preProcessWebhook
     *
     * @covers ::preprocessHook
     */
    public function preprocessHook()
    {
        $this->initCurrentMock();
        $this->hookHelper->expects(self::once())->method('preProcessWebhook')
            ->with(self::STORE_ID);
        TestHelper::invokeMethod(
            $this->currentMock,
            'preprocessHook',
            [self::STORE_ID]
        );
    }

    /**
     * @test
     * that sendErrorResponse would prepare error messages and set response code, body and send response
     *
     * @covers ::sendErrorResponse
     */
    public function sendErrorResponse()
    {
        $errCode = 123;
        $message = 'test message';
        $httpStatusCode = 401;

        $this->initCurrentMock();

        $errResponse = [
            'status' => 'failure',
            'error' => [
                'code' => $errCode,
                'message' => $message,
            ],
        ];

        $this->errorResponse->expects(self::once())->method('prepareErrorMessage')
            ->with($errCode, $message)->willReturn($errResponse);
        $this->response->expects(self::once())->method('setHttpResponseCode')->with($httpStatusCode);
        $this->response->expects(self::once())->method('setBody')
            ->with($errResponse);
        $this->response->expects(self::once())->method('sendResponse');

        TestHelper::invokeMethod(
            $this->currentMock,
            'sendErrorResponse',
            [$errCode, $message, $httpStatusCode]
        );
    }

    /**
     * @test
     * that getQuoteById would return quote from cart helper
     *
     * @covers ::getQuoteById
     */
    public function getQuoteById()
    {
        $this->initCurrentMock();
        $quote = $this->createMock(Quote::class);
        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)->willReturn($quote);
        $this->assertEquals($quote, $this->currentMock->getQuoteById(self::IMMUTABLE_QUOTE_ID));
    }

    /**
     * @test
     * that applyExternalQuoteData would call apply external quote data in discount helper for the quote
     *
     * @covers ::applyExternalQuoteData
     */
    public function applyExternalQuoteData()
    {
        $this->initCurrentMock();
        $quote = $this->createMock(Quote::class);
        TestHelper::setProperty($this->currentMock, 'quote', $quote);
        $this->discountHelper->expects(self::once())->method('applyExternalDiscountData')->with($quote);
        $this->currentMock->applyExternalQuoteData($quote);
    }

    /**
     * @test
     * that reformatAddressData would return address data includes region and unset empty values
     *
     * @covers ::reformatAddressData
     */
    public function reformatAddressData()
    {
        $addressData = [
            'region' => 'California',
            'country_code' => 'US',
            'postal_code' => '90210',
            'locality' => 'San Franciso',
            'street_address1' => '123 Sesame St.',
            'street_address2' => '',
            'email' => self::EMAIL,
            'company' => ''
        ];
        $expected = [
            'country_id' => 'US',
            'postcode' => '90210',
            'region' => 'California',
            'region_id' => 12,
            'city' => 'San Franciso',
            'street' => '123 Sesame St.',
            'email' => self::EMAIL
        ];
        $this->initCurrentMock();
        $region = $this->createMock(RegionModel::class);
        $region->expects(self::once())->method('getId')
            ->willReturn(12);
        $this->regionModel->expects(self::once())->method('loadByName')->with('California', 'US')
            ->willReturn($region);
        $this->cartHelper->expects(self::once())->method('validateEmail')
            ->with(self::EMAIL)->willReturn(true);

        $this->assertEquals($expected, $this->currentMock->reformatAddressData($addressData));
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
            'getId', 'getBoltParentQuoteId', 'getStoreId', 'getStore', 'getQuoteCurrencyCode'
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
        $quote->method('getStore')
            ->willReturn($this->store);
        $quote->method('getStoreId')
            ->willReturn(self::STORE_ID);
        return $quote;
    }

    /**
     * @test
     * that validateAddressData would validate email
     *
     * @covers ::validateAddressData
     */
    public function validateAddressData()
    {
        $addressData = [
            'email' => self::EMAIL
        ];
        $this->initCurrentMock();
        $this->cartHelper->expects(self::once())->method('validateEmail')
            ->with(self::EMAIL)->willReturn(true);
        $this->currentMock->validateAddressData($addressData);
    }

    /**
     * @test
     * that validateEmail would call validateEmail from cart helper and return validated
     *
     * @covers ::validateEmail
     */
    public function validateEmail_valid()
    {
        $this->initCurrentMock();
        $this->cartHelper->expects(self::once())->method('validateEmail')
            ->with(self::EMAIL)->willReturn(true);
        $this->currentMock->validateEmail(self::EMAIL);
    }

    /**
     * @test
     * that validateEmail throws BoltException with Invalid email message for invalid email input
     *
     * @covers ::validateEmail
     */
    public function validateEmail_invalid()
    {
        $invalidEmail = 'invalid email';
        $this->initCurrentMock();

        $this->cartHelper->expects(self::once())->method('validateEmail')
            ->with($invalidEmail)->willReturn(false);
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(BoltErrorResponse::ERR_UNIQUE_EMAIL_REQUIRED);
        $this->expectExceptionMessage(__('Invalid email: %1', $invalidEmail)->render());

        $this->currentMock->validateEmail($invalidEmail);
    }

    /**
     * @param $errCode
     * @param $message
     * @param $httpStatusCode
     */
    private function expectErrorResponse($errCode, $message, $httpStatusCode = 422)
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
     * @test
     * that loadQuote return quote
     *
     * @covers ::loadQuote
     */
    public function loadQuote_happyPath()
    {
        $this->initCurrentMock(['getQuoteById']);
        $quote = $this->createMock(Quote::class);
        $this->currentMock->expects(self::exactly(1))->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn($quote);

        $this->assertEquals($quote, $this->currentMock->loadQuote(self::IMMUTABLE_QUOTE_ID));
    }

    /**
     * @test
     * that loadQuote throw unknown quote id exception regarding get quote id return false
     *
     * @covers ::loadQuote
     */
    public function loadQuote_throwUnknownQuoteIdException()
    {
        $this->initCurrentMock(['getQuoteById']);
        $this->currentMock->expects(self::exactly(1))->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(__('Unknown quote id: %1.', self::IMMUTABLE_QUOTE_ID)->render());

        $this->assertNull($this->currentMock->loadQuote(self::IMMUTABLE_QUOTE_ID));
    }

    /**
     * @test
     * that execute throws web api exception
     *
     * @covers ::execute
     */
    public function execute_WebApiException()
    {
        $cart = [
            'display_id' => self::DISPLAY_ID
        ];
        $this->initCurrentMock(['getQuoteById', 'preprocessHook']);

        $startTime = microtime(true) * 1000;
        $this->metricsClient->expects(self::once())->method('getCurrentTime')->willReturn($startTime);

        $immutableQuote = $this->createMock(Quote::class);
        $this->currentMock->expects(self::exactly(1))->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn($immutableQuote);

        $e = new WebapiException(__('Precondition Failed'), 6001, 422);
        $this->currentMock->method('preprocessHook')->willThrowException($e);
        $this->currentMock->expects(self::once())->method('preprocessHook')
            ->willThrowException($e);

        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with(ShippingTax::METRICS_FAILURE_KEY, 1, ShippingTax::METRICS_LATENCY_KEY, $startTime);

        $this->expectErrorResponse($e->getCode(), $e->getMessage(), $e->getHttpCode());
        $this->assertNull($this->currentMock->execute($cart, []));
    }

    /**
     * @test
     * that execute would throw bolt exception
     *
     * @covers ::execute
     */
    public function execute_BoltException()
    {
        $cart = [
            'display_id' => self::DISPLAY_ID,
            'order_reference' => self::PARENT_QUOTE_ID
        ];
        $shipping_address = [
            'email' => 'invalid email'
        ];
        $shipping_option = null;

        $this->initCurrentMock(['loadQuote', 'preprocessHook', 'validateEmail']);

        $startTime = microtime(true) * 1000;
        $this->metricsClient->expects(self::once())->method('getCurrentTime')->willReturn($startTime);

        $immutableQuote = $this->getQuoteMock();
        $parentQuote = $this->getQuoteMock(self::PARENT_QUOTE_ID, self::PARENT_QUOTE_ID);

        $this->currentMock->expects(self::once())->method('preprocessHook')
            ->with(self::STORE_ID);
        $this->currentMock->expects(self::exactly(2))->method('loadQuote')
            ->withConsecutive([self::IMMUTABLE_QUOTE_ID], [self::PARENT_QUOTE_ID])
            ->willReturnOnConsecutiveCalls($immutableQuote, $parentQuote);
        $this->cartHelper->expects(self::once())->method('replicateQuoteData')
            ->with($immutableQuote, $parentQuote);

        TestHelper::setProperty($this->currentMock, 'quote', $parentQuote);

        $this->store->expects(self::once())->method('setCurrentCurrencyCode')
            ->with(self::CURRENCY_CODE);
        $this->sessionHelper->expects(self::once())->method('loadSession')
            ->with(TestHelper::getProperty($this->currentMock, 'quote'));
        $this->cartHelper->expects(self::once())->method('handleSpecialAddressCases')
            ->with($shipping_address)->willReturn($shipping_address);

        $e = new BoltException(
            __('Invalid email: %1', 'invalid email'),
            null,
            BoltErrorResponse::ERR_UNIQUE_EMAIL_REQUIRED
        );

        $this->currentMock->method('validateEmail')->willThrowException($e);

        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with(ShippingTax::METRICS_FAILURE_KEY, 1, ShippingTax::METRICS_LATENCY_KEY, $startTime);

        $this->expectErrorResponse($e->getCode(), $e->getMessage());
        $this->assertNull($this->currentMock->execute($cart, $shipping_address));
    }

    /**
     * @test
     * that execute throw exception and return null
     *
     * @covers ::execute
     */
    public function execute_Exception()
    {
        $cart = [
            'display_id' => self::DISPLAY_ID
        ];
        $this->initCurrentMock(['loadQuote']);

        $message = 'test message';
        $e = new \Exception($message);
        $this->currentMock->expects(self::once())->method('loadQuote')->willThrowException($e);
        $msg = __('Unprocessable Entity') . ': ' . $e->getMessage();

        $this->expectErrorResponse(6009, $msg);
        $this->assertNull($this->currentMock->execute($cart, []));
    }

    /**
     * @test
     * that execute would return result regarding address data and shipping option
     *
     * @covers ::execute
     */
    public function execute_happyPath()
    {
        $cart = [
            'display_id' => self::DISPLAY_ID,
            'order_reference' => self::PARENT_QUOTE_ID
        ];
        $shipping_address = [
            'email' => self::EMAIL
        ];
        $shipping_option = null;

        $this->initCurrentMock(['loadQuote', 'preprocessHook', 'getResult']);

        $startTime = microtime(true) * 1000;
        $this->metricsClient->expects(self::once())->method('getCurrentTime')->willReturn($startTime);

        $immutableQuote = $this->getQuoteMock();
        $parentQuote = $this->getQuoteMock(self::PARENT_QUOTE_ID, self::PARENT_QUOTE_ID);

        $this->currentMock->expects(self::once())->method('preprocessHook')
            ->with(self::STORE_ID);
        $this->currentMock->expects(self::exactly(2))->method('loadQuote')
            ->withConsecutive([self::IMMUTABLE_QUOTE_ID], [self::PARENT_QUOTE_ID])
            ->willReturnOnConsecutiveCalls($immutableQuote, $parentQuote);
        $this->cartHelper->expects(self::once())->method('replicateQuoteData')
            ->with($immutableQuote, $parentQuote);

        TestHelper::setProperty($this->currentMock, 'quote', $parentQuote);

        $this->store->expects(self::once())->method('setCurrentCurrencyCode')
            ->with(self::CURRENCY_CODE);
        $this->sessionHelper->expects(self::once())->method('loadSession')
            ->with(TestHelper::getProperty($this->currentMock, 'quote'));
        $this->cartHelper->expects(self::once())->method('handleSpecialAddressCases')
            ->with($shipping_address)->willReturn($shipping_address);
        $this->cartHelper->expects(self::once())->method('validateEmail')
            ->with(self::EMAIL)->willReturn(true);
        $this->currentMock->expects(self::once())->method('getResult')
            ->with($shipping_address, $shipping_option);
        $this->logHelper->expects(self::exactly(4))->method('addInfoLog');
        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with(ShippingTax::METRICS_SUCCESS_KEY, 1, ShippingTax::METRICS_LATENCY_KEY, $startTime);

        $this->currentMock->execute($cart, $shipping_address);
    }

    /**
     * @test
     * that getResult would return generate result
     *
     * @covers ::getResult
     */
    public function getResult()
    {
        $addressData = [
            'region' => 'California',
            'country_code' => 'US',
            'postal_code' => '90210',
            'locality' => 'San Franciso',
            'street_address1' => '123 Sesame St.',
            'email' => self::EMAIL,
            'company' => 'Bolt'
        ];

        $result = [
            'some' => 'result'
        ];

        $this->initCurrentMock(['applyExternalQuoteData', 'generateResult']);
        $this->currentMock->expects(self::once())->method('applyExternalQuoteData');
        $this->currentMock->expects(self::once())->method('generateResult')
            ->with($addressData, null)->willReturn($result);

        $this->assertEquals($result, $this->currentMock->getResult($addressData, null));
    }

    /**
     * @test
     * that populateAddress would return reformatted address
     *
     * @covers ::populateAddress
     */
    public function populateAddress()
    {
        $addressData = [
            'region' => 'California',
            'country_code' => 'US',
            'postal_code' => '90210',
            'locality' => 'San Franciso',
            'street_address1' => '123 Sesame St.',
            'email' => self::EMAIL,
            'company' => 'Bolt'
        ];
        $addressDataReformatted = [
            'country_id' => 'US',
            'postcode' => '90210',
            'region' => 'California',
            'region_id' => 12,
            'city' => 'San Franciso',
            'street' => '123 Sesame St.',
            'email' => self::EMAIL,
            'company' => 'Bolt'
        ];

        $this->initCurrentMock(['reformatAddressData']);

        $address = $this->createMock(\Magento\Quote\Model\Quote\Address::class);
        $quote = $this->createMock(Quote::class);
        $quote->expects(self::once())->method('isVirtual')->willReturn(false);
        $quote->expects(self::once())->method('getShippingAddress')->willReturn($address);

        TestHelper::setProperty($this->currentMock, 'quote', $quote);

        $this->currentMock->expects(self::once())->method('reformatAddressData')
            ->with($addressData)->willReturn($addressDataReformatted);

        $address->expects(self::once())->method('addData')->willReturnSelf();

        $this->assertEquals($address, $this->currentMock->populateAddress($addressData));
    }
}
