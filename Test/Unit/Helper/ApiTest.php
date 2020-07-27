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

use Bolt\Boltpay\Helper\Api as ApiHelper;

use Bolt\Boltpay\Test\Unit\TestHelper;
use Bugsnag\Report;
use Exception;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\ZendClientFactory;
use Bolt\Boltpay\Model\ResponseFactory;
use Bolt\Boltpay\Model\RequestFactory;
use Bolt\Boltpay\Model\Request;
use Bolt\Boltpay\Model\Response;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use PHPUnit\Framework\Constraint\ArraySubset;
use ReflectionException;
use Bolt\Boltpay\Helper\Bugsnag;
use \PHPUnit\Framework\TestCase;
use Magento\Framework\HTTP\ZendClient;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Zend_Http_Response;

/**
 * @coversDefaultClass \Bolt\Boltpay\Helper\Api
 */
class ApiTest extends TestCase
{

    /** @var string Dummy API url */
    const API_URL = "https://api.bolt.com/";
    /** @var string Dummy API key */
    const API_KEY = '60c47bdb25b0b133840808ce5fd2879d6295c53d0265c70e311552fb2028b00b';
    /** @var string Dummy dynamic part of the API url */
    const DYNAMIC_API_URL = "/test/api/dynamic";

    /** @var MockObject|LogHelper */
    private $logHelper;

    /** @var MockObject|Bugsnag */
    private $bugsnag;

    /** @var MockObject|ConfigHelper */
    private $configHelper;

    /** @var MockObject|ZendClientFactory */
    private $zendClientFactory;

    /** @var MockObject|Request */
    private $request;

    /** @var MockObject|ResponseFactory */
    private $responseFactory;

    /** @var MockObject|RequestFactory */
    private $requestFactory;

    /** @var MockObject|Context */
    private $context;

    /** @var MockObject|ApiHelper */
    private $currentMock;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->initRequiredMocks();
        $this->currentMock = $this->getMockBuilder(ApiHelper::class)
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->zendClientFactory,
                    $this->configHelper,
                    $this->responseFactory,
                    $this->requestFactory,
                    $this->logHelper,
                    $this->bugsnag
                ]
            )
            ->setMethods(
                [
                    'getFullApiUrl'
                ]
            )
            ->getMock();
    }

    /**
     * Initializes mock objects required for all tests
     */
    private function initRequiredMocks()
    {
        $this->context = $this->createMock(Context::class);
        $this->zendClientFactory = $this->createPartialMock(
            ZendClientFactory::class,
            ['create', 'setUri']
        );
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->request = $this->createPartialMock(
            Request::class,
            [
                'getApiData',
                'getApiUrl',
                'getApiKey',
                'getDynamicApiUrl',
                'getRequestMethod',
                'getHeaders',
                'getStatusOnly',
                'setApiData',
                'setApiUrl',
                'setApiKey',
                'setRequestMethod',
                'setHeaders',
                'setStatusOnly',
            ]
        );
        $this->responseFactory = $this->createPartialMock(ResponseFactory::class, ['create']);
        $this->requestFactory = $this->createMock(RequestFactory::class);
        $this->requestFactory->method('create')->willReturn($this->request);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
    }
    
    /**
     * @test
     * that constructor sets internal properties
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $instance = new ApiHelper(
            $this->context,
            $this->zendClientFactory,
            $this->configHelper,
            $this->responseFactory,
            $this->requestFactory,
            $this->logHelper,
            $this->bugsnag
        );
        
        $this->assertAttributeEquals($this->zendClientFactory, 'httpClientFactory', $instance);
        $this->assertAttributeEquals($this->configHelper, 'configHelper', $instance);
        $this->assertAttributeEquals($this->responseFactory, 'responseFactory', $instance);
        $this->assertAttributeEquals($this->requestFactory, 'requestFactory', $instance);
        $this->assertAttributeEquals($this->logHelper, 'logHelper', $instance);
        $this->assertAttributeEquals($this->bugsnag, 'bugsnag', $instance);
    }

    /**
     * @test
     * that getFullApiUrl returns full api url from provided dynamic by prepending base api url and current version
     *
     * @covers ::getFullApiUrl
     *
     * @throws ReflectionException if getFullApiUrl method doesn't exist
     */
    public function getFullApiUrl_always_returnsFullApiUrl()
    {
        $this->configHelper->expects($this->once())->method('getApiUrl')->willReturn(self::API_URL);
        $fullApiUrl = TestHelper::invokeMethod($this->currentMock, 'getFullApiUrl', [self::DYNAMIC_API_URL]);
        static::assertEquals(self::API_URL . ApiHelper::API_CURRENT_VERSION . self::DYNAMIC_API_URL, $fullApiUrl);
    }

    /**
     * @test
     * that isResponseError returns true for responses containing errors or error_code keys, otherwise false
     *
     * @covers ::isResponseError
     *
     * @dataProvider isResponseError_withVariousResponsesProvider
     *
     * @param mixed $response to be evaluated
     * @param bool  $expectedResult of the method call
     *
     * @throws ReflectionException if isResponseError method is not defined
     */
    public function isResponseError_withVariousResponses_returnsIsResponseError($response, $expectedResult)
    {
        static::assertEquals(
            $expectedResult,
            TestHelper::invokeMethod($this->currentMock, 'isResponseError', [$response])
        );
    }

    /**
     * Data provider for {@see isResponseError_withVariousResponses_returnsIsResponseError}
     *
     * @return array containing dummy response and expected result of the method call
     */
    public function isResponseError_withVariousResponsesProvider()
    {
        return [
            ['response' => ['errors' => []], 'expectedResult' => true],
            ['response' => ['error_code' => 123], 'expectedResult' => true],
            ['response' => ['test'], 'expectedResult' => false],
        ];
    }

    /**
     * @test
     * that sendRequest returns request response body
     *
     * @covers ::sendRequest
     *
     * @throws LocalizedException from tested method
     * @throws \Zend_Http_Client_Exception from tested method
     */
    public function sendRequest_withRegularRequest_returnsResultResponse()
    {
        $request = new Request(
            [
                'api_data'       => new \stdClass(),
                'api_url'        => self::API_URL,
                'api_key'        => self::API_KEY,
                'request_method' => 'POST'
            ]
        );
        $client = $this->createMock(ZendClient::class);

        $resultMock = $this->createPartialMock(Response::class, ['setResponse']);
        $responseMock = $this->createPartialMock(Zend_Http_Response::class, ['getBody', 'getHeaders', 'getStatus']);

        $this->zendClientFactory->expects(static::once())->method('create')->willReturn($client);
        $this->responseFactory->expects(static::once())->method('create')->willReturn($resultMock);

        $client->expects(static::once())->method('setUri')->with(self::API_URL);
        $client->expects(static::once())->method('setConfig')->with(['maxredirects' => 0, 'timeout' => 30]);

        $reportMock = $this->createPartialMock(Report::class, ['setMetaData']);
        $dummyResponseBody = '{"data": {"logMerchantLogs": {"isSuccessful": true}}}';
        $reportMock->expects(self::exactly(3))->method('setMetaData')->withConsecutive(
            [
                new ArraySubset(
                    [
                        'BOLT API REQUEST' => $request->getData()
                    ]
                )
            ],
            [
                [
                    'BOLT API RESPONSE' => [
                        'headers' => [],
                        'body'    => $dummyResponseBody,
                    ]
                ]
            ],
            [
                [
                    'META DATA' => [
                        'bolt_trace_id' => null,
                    ]
                ]
            ]
        );
        $this->bugsnag->expects(self::exactly(3))->method('registerCallback')->willReturnCallback(
            function (callable $callback) use ($reportMock) {
                $callback($reportMock);
            }
        );

        $client->expects(static::once())->method('setRawData')->willReturnSelf();

        $client->expects(static::once())->method('request')->willReturn($responseMock);

        $responseMock->expects(static::exactly(2))->method('getHeaders')->willReturn([]);
        $responseMock->expects(static::exactly(2))->method('getBody')
            ->willReturn($dummyResponseBody);

        $resultMock->expects(static::once())->method('setResponse')->with(json_decode($dummyResponseBody));

        static::assertSame($resultMock, $this->currentMock->sendRequest($request));
    }

    /**
     * @test
     * that sendRequest returns response status when request has status_only flag
     *
     * @covers ::sendRequest
     *
     * @throws LocalizedException from tested method
     * @throws \Zend_Http_Client_Exception from tested method
     */
    public function sendRequest_withStatusOnlyRequest_returnsResponseStatus()
    {
        $request = new Request(
            [
                'api_data'       => new \stdClass(),
                'api_url'        => self::API_URL,
                'api_key'        => self::API_KEY,
                'request_method' => 'POST',
                'status_only'    => true
            ]
        );
        $client = $this->createMock(ZendClient::class);

        $responseMock = $this->createPartialMock(Response::class, ['getStatus', 'getBody']);

        $this->zendClientFactory->expects(static::once())->method('create')->willReturn($client);
        $this->responseFactory->expects(static::once())->method('create')->willReturn($responseMock);

        $client->expects(static::once())->method('setUri')->with(self::API_URL);
        $client->expects(static::once())->method('setConfig')->with(['maxredirects' => 0, 'timeout' => 30]);

        $client->expects(static::once())->method('setRawData')->willReturnSelf();
        $client->expects(static::once())->method('request')->willReturn($responseMock);

        $responseMock->expects(static::once())->method('getStatus')->willReturn(200);

        static::assertSame(200, $this->currentMock->sendRequest($request));
    }

    /**
     * @test
     * that sendRequest will wrap exception message and re-throw it as localized exception
     * if an exception is thrown while sending the request
     *
     * @covers ::sendRequest
     * @covers ::wrapGatewayError
     *
     * @expectedException \Magento\Framework\Exception\LocalizedException
     * @expectedExceptionMessage Gateway error: Expected exception message
     *
     * @throws LocalizedException from tested method
     * @throws \Zend_Http_Client_Exception from tested method
     */
    public function sendRequest_exceptionDuringRequest_rethrowsAsLocalizedException()
    {
        $request = new Request(
            [
                'api_data'       => new \stdClass(),
                'api_url'        => self::API_URL,
                'api_key'        => self::API_KEY,
                'request_method' => 'POST'
            ]
        );
        $client = $this->createMock(ZendClient::class);
        $this->zendClientFactory->expects(static::once())->method('create')->willReturn($client);
        $client->expects(static::once())->method('setRawData')->willReturnSelf();
        $client->expects(static::once())->method('request')
            ->willThrowException(new Exception('Expected exception message'));
        $this->currentMock->sendRequest($request);
    }
    
    /**
     * @test
     * that sendRequest throws localized exception if the response body is empty
     *
     * @covers ::sendRequest
     *
     * @throws \Zend_Http_Client_Exception from tested method
     */
    public function sendRequest_withRegularRequest_returnsLocalizedException()
    {
        $request = new Request(
            [
                'api_data'       => new \stdClass(),
                'api_url'        => self::API_URL,
                'api_key'        => self::API_KEY,
                'request_method' => 'POST'
            ]
        );
        $client = $this->createMock(ZendClient::class);

        $resultMock = $this->createPartialMock(Response::class, ['setResponse']);
        $responseMock = $this->createPartialMock(Zend_Http_Response::class, ['getBody', 'getHeaders', 'getStatus']);

        $this->zendClientFactory->expects(static::once())->method('create')->willReturn($client);
        $this->responseFactory->expects(static::once())->method('create')->willReturn($resultMock);

        $client->expects(static::once())->method('setUri')->with(self::API_URL);
        $client->expects(static::once())->method('setConfig')->with(['maxredirects' => 0, 'timeout' => 30]);

        $reportMock = $this->createPartialMock(Report::class, ['setMetaData']);
        $emptyResponseBody = '';
        $reportMock->expects(self::exactly(3))->method('setMetaData')->withConsecutive(
            [
                new ArraySubset(
                    [
                        'BOLT API REQUEST' => $request->getData()
                    ]
                )
            ],
            [
                [
                    'BOLT API RESPONSE' => [
                        'headers' => [],
                        'body'    => $emptyResponseBody,
                    ]
                ]
            ],
            [
                [
                    'META DATA' => [
                        'bolt_trace_id' => null,
                    ]
                ]
            ]
        );
        $this->bugsnag->expects(self::exactly(3))->method('registerCallback')->willReturnCallback(
            function (callable $callback) use ($reportMock) {
                $callback($reportMock);
            }
        );

        $client->expects(static::once())->method('setRawData')->willReturnSelf();

        $client->expects(static::once())->method('request')->willReturn($responseMock);

        $responseMock->expects(static::exactly(2))->method('getHeaders')->willReturn([]);
        $responseMock->expects(static::exactly(2))->method('getBody')
            ->willReturn($emptyResponseBody);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Something went wrong in the payment gateway.');
        
        $this->currentMock->sendRequest($request);
    }

    /**
     * @test
     * that wrapGatewayError adds prefix to provided text and runs it through translator
     * @see \Magento\Framework\Phrase
     *
     * @covers ::wrapGatewayError
     *
     * @throws ReflectionException if wrapGatewayError method doesn't exist
     */
    public function wrapGatewayError_always_addsPrefixToProvidedErrorText()
    {
        $text = 'Dummy error message';
        static::assertEquals(
            __('Gateway error: %1', $text),
            TestHelper::invokeMethod($this->currentMock, 'wrapGatewayError', [$text])
        );
    }

    /**
     * @test
     * that buildRequest returns {@see \Bolt\Boltpay\Model\Request} object populated with provided data
     *
     * @covers ::buildRequest
     */
    public function buildRequest_always_buildsRequestFromProvidedData()
    {
        $apiData = [
            'transaction_id'         => OrderTest::TRANSACTION_ID,
            'amount'                 => 123,
            'currency'               => 'USD',
            'skip_hook_notification' => true
        ];
        $requestMethod = 'POST';
        $headers = [];
        $statusOnly = false;
        $requestData = new DataObject(
            [
                'api_data'        => $apiData,
                'api_key'         => self::API_KEY,
                'dynamic_api_url' => ApiHelper::API_CREATE_ORDER
            ]
        );

        $this->configHelper->method('getApiUrl')->willReturn(self::API_URL);

        $this->request->expects(self::once())->method('setApiData')->with($apiData);
        $this->request->expects(self::once())->method('setApiUrl')->with('https://api.bolt.com/v1/merchant/orders');
        $this->request->expects(self::once())->method('setApiKey')->with(self::API_KEY);
        $this->request->expects(self::once())->method('setRequestMethod')->with($requestMethod);
        $this->request->expects(self::once())->method('setHeaders')->with($headers);
        $this->request->expects(self::once())->method('setStatusOnly')->with($statusOnly);

        static::assertSame($this->request, $this->currentMock->buildRequest($requestData));
    }
}
