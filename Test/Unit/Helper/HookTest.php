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

use Bolt\Boltpay\Helper\Hook as BoltHook;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Inspection\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Webapi\Rest\Request;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\Webapi\Exception as WebapiException;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\DataObject;
use Bolt\Boltpay\Model\Request as BoltRequest;
use ReflectionException;

/**
 * Class HookTest
 *
 * @coversDefaultClass \Bolt\Boltpay\Helper\Hook
 * @package Bolt\Boltpay\Test\Unit\Helper
 */
class HookTest extends TestCase
{
    const PAYLOAD = 'payload';
    const CORRECT_HMAC = 'correct HMAC';
    const INCORRECT_HMAC = 'incorrect HMAC';
    const STORE_ID = 1;
    const BOLT_TRACE_ID = 'BOLT_TRACE_ID';

    /** @var MockObject|Context mocked instance of the Context helper */
    private $context;

    /** @var MockObject|Request mocked instance of the Webapi Rest Request */
    private $request;

    /** @var MockObject|ConfigHelper mocked instance of the Config helper */
    private $configHelper;

    /** @var MockObject|LogHelper mocked instance of the Log helper */
    private $logHelper;

    /** @var MockObject|ApiHelper mocked instance of the Api helper */
    private $apiHelper;

    /** @var MockObject|DataObjectFactory mocked instance of the Data Object Factory class */
    private $dataObjectFactory;

    /** @var MockObject|Bugsnag mocked instance of the Bugsnag helper */
    private $bugsnag;

    /** @var MockObject|Response mocked instance of the Response WebApi */
    private $response;
    
    /** @var MockObject|BoltHook mocked instance of the class tested */
    private $currentMock;
    
    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->context = $this->createMock(Context::class);
        $this->request = $this->createMock(Request::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->apiHelper = $this->createMock(ApiHelper::class);
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->response = $this->createMock(Response::class);
    }

    /**
     * Sets mocked instance of the tested class
     *
     * @param array $methods to be mocked
     */
    public function initCurrentMock($methods = [])
    {
        $this->currentMock = $this->getMockBuilder(BoltHook::class)
            ->setMethods($methods)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->request,
                    $this->configHelper,
                    $this->logHelper,
                    $this->apiHelper,
                    $this->dataObjectFactory,
                    $this->bugsnag,
                    $this->response
                ]
            )
            ->getMock();
    }
    
    /**
     * @test
     * that __construct sets internal properties
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $instance = new BoltHook(
            $this->context,
            $this->request,
            $this->configHelper,
            $this->logHelper,
            $this->apiHelper,
            $this->dataObjectFactory,
            $this->bugsnag,
            $this->response
        );
        
        $this->assertAttributeEquals($this->request, 'request', $instance);
        $this->assertAttributeEquals($this->configHelper, 'configHelper', $instance);
        $this->assertAttributeEquals($this->logHelper, 'logHelper', $instance);
        $this->assertAttributeEquals($this->apiHelper, 'apiHelper', $instance);
        $this->assertAttributeEquals($this->dataObjectFactory, 'dataObjectFactory', $instance);
        $this->assertAttributeEquals($this->bugsnag, 'bugsnag', $instance);
        $this->assertAttributeEquals($this->response, 'response', $instance);
    }

    /**
     * @test
     * that verifyWebhookApi verifies provided payload signature using merchant/verify_signature API endpoint
     *
     * @covers ::verifyWebhookApi
     *
     * @dataProvider verifyWebhookApi_withVariousResponsesProvider
     *
     * @param string $hmacHeader parameter used to verify payload
     * @param int|\Exception $response of the sent api request
     * @param bool $expectException flag to determine is exception thrown
     * @param bool $expectedResult of the tested method
     *
     * @throws ReflectionException if verifyWebhookApi method doesn't exist
     */
    public function verifyWebhookApi_withVariousResponses_returnsVerifiedState(
        $hmacHeader, 
        $response, 
        $expectException,
        $expectedResult
    ) {
        $this->initCurrentMock(['getStoreId']);
        $requestData = $this->getMockBuilder(DataObject::class)
            ->setMethods(['setApiData','setDynamicApiUrl','setApiKey','setHeaders','setStatusOnly'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->dataObjectFactory->method('create')->willReturn($requestData);
        $requestData->expects(static::once())->method('setApiData')->with(json_decode(self::PAYLOAD));
        $requestData->expects(static::once())->method('setDynamicApiUrl')->with('merchant/verify_signature');
        $this->currentMock->expects(static::once())->method('getStoreId')->willReturn(self::STORE_ID);
        $this->configHelper->expects(static::once())->method('getApiKey')->with(self::STORE_ID)->willReturn('ApiKey');
        $requestData->expects(static::once())->method('setApiKey')->with('ApiKey');
        $requestData->expects(static::once())->method('setHeaders')->with([BoltHook::HMAC_HEADER => $hmacHeader]);
        $requestData->expects(static::once())->method('setStatusOnly')->with(true);
        $request =  $this->createMock(BoltRequest::class);
        $this->apiHelper->expects(static::once())->method('buildRequest')->with($requestData)->willReturn($request);
        $this->apiHelper->expects(static::once())->method('sendRequest')->with($request)->will(
            $expectException ? $this->throwException($response) : $this->returnValue($response)
        );
        
        $this->assertEquals(
            $expectedResult,
            TestHelper::invokeMethod($this->currentMock, 'verifyWebhookApi', [self::PAYLOAD, $hmacHeader])
        );
    }

    /**
     * Data provider for {@see verifyWebhookApi_withVariousResponses_returnsVerifiedState}
     *
     * @return array[] containing
     * hmac header value
     * response of sent request
     * except exception flag
     * expected result of the tested method
     *
     * @throws ReflectionException from {@see \PHPUnit\Framework\TestCase::createMock} method
     */
    public function verifyWebhookApi_withVariousResponsesProvider()
    {
        return [
            [
                'hmacHeader' => self::CORRECT_HMAC,
                'response' => 200,
                'expectException' => false,
                'expectedResult' => true,
            ],
            [
                'hmacHeader' => self::INCORRECT_HMAC,
                'response' => $this->createMock(Exception::class),
                'expectException' => true,
                'expectedResult' => false,
            ],
        ];
    }

    /**
     * @test
     * that verifySignature determines if provided signature matches the one computed from the provided payload
     *
     * @covers ::verifySignature
     *
     * @dataProvider verifySignature_withVariousHmacHeadersProvider
     *
     * @param string $hmacHeader value
     * @param bool $expectedResult of the tested method
     */
    public function verifySignature_withVariousHmacHeaders_returnsSignatureState($hmacHeader, $expectedResult)
    {
        $this->initCurrentMock(['computeSignature']);
        $this->currentMock->method('computeSignature')->with(self::PAYLOAD)->willReturn(self::CORRECT_HMAC);
        $this->assertEquals($expectedResult, $this->currentMock->verifySignature(self::PAYLOAD, $hmacHeader));
    }

    /**
     * Data provider for {@see verifySignature_withVariousHmacHeaders_returnsSignatureState}
     * 
     * @return array[] containing HMAC header value and expected result of the tested method
     */
    public function verifySignature_withVariousHmacHeadersProvider()
    {
        return [
            ['hmacHeader' => self::CORRECT_HMAC, 'expectedResult' => true],
            ['hmacHeader' => self::INCORRECT_HMAC, 'expectedResult' => false],
        ];
    }

    /**
     * @test
     * that computeSignature returns signature using payment secret key
     *
     * @covers ::computeSignature
     */
    public function computeSignature_always_returnsSignature()
    {
        $this->initCurrentMock(['getStoreId']);
        $this->currentMock->method('getStoreId')->willReturn(self::STORE_ID);
        $this->configHelper->method('getSigningSecret')->with(self::STORE_ID)->willReturn('signing_secret');

        $expected_signature = base64_encode(hash_hmac('sha256', self::PAYLOAD, 'signing_secret', true));
        $this->assertEquals($expected_signature, $this->currentMock->computeSignature(self::PAYLOAD));
    }

    private function verifyWebhook_adjustRequestMock()
    {
        $this->request->expects($this->once())->method('getContent')->willReturn(self::PAYLOAD);
        $this->request->expects($this->once())->method('getHeader')->with('X-Bolt-Hmac-Sha256')->willReturn(self::CORRECT_HMAC);
    }

    /**
     * @test
     * that verifyWebhook proceeds request when signature is verified and exception is not thrown
     *
     * @covers ::verifyWebhook
     */
    public function verifyWebhook_whenSignatureIsVerified_proceedsRequest()
    {
        $this->initCurrentMock(['verifySignature']);
        $this->currentMock->method('verifySignature')->with(self::PAYLOAD, self::CORRECT_HMAC)->willReturn(true);
        $this->verifyWebhook_adjustRequestMock();
        $this->currentMock->verifyWebhook();
        // no exception
    }

    /**
     * @test
     * that verifyWebhook
     *
     * @covers ::verifyWebhook
     *
     * @dataProvider verifyWebhook_withVariousResponseCodesProvider
     *
     * @param int $responseCode of the verify request
     *
     * @throws LocalizedException from verifyWebhookApi method
     * @throws ReflectionException from {@see \PHPUnit\Framework\TestCase::createMock} method
     */
    public function verifyWebhook_withVariousResponseCodes_returnsHookRequest($responseCode)
    {
        $this->initCurrentMock(['verifySignature', 'getStoreId']);
        $this->currentMock->method('verifySignature')->with(self::PAYLOAD, self::CORRECT_HMAC)->willReturn(false);
        $this->currentMock->method('getStoreId')->willReturn(self::STORE_ID);

        $this->verifyWebhook_adjustRequestMock();

        //private function verifyWebhookApi
        $requestData = $this->getMockBuilder(DataObject::class)
            ->setMethods(['setApiData', 'setDynamicApiUrl', 'setApiKey', 'setHeaders', 'setStatusOnly'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->dataObjectFactory->method('create')->willReturn($requestData);
        $requestData->expects($this->once())->method('setApiData')->with(json_decode(self::PAYLOAD));
        $requestData->expects($this->once())->method('setDynamicApiUrl')->with('merchant/verify_signature');
        $this->configHelper->method('getApiKey')->with(self::STORE_ID)->willReturn('ApiKey');
        $requestData->expects($this->once())->method('setApiKey')->with('ApiKey');
        $requestData->expects($this->once())->method('setHeaders')->with(['X-Bolt-Hmac-Sha256' => self::CORRECT_HMAC]);
        $requestData->expects($this->once())->method('setStatusOnly')->with(true);

        $request = $this->createMock(BoltRequest::class);
        $this->apiHelper->expects($this->once())->method('buildRequest')->with($requestData)->willReturn($request);
        $this->apiHelper->expects($this->once())->method('sendRequest')->with($request)->willReturn($responseCode);

        $this->verifyWebhook_adjustRequestMock();
        // should return exception for any answerCode except 200
        if ($responseCode !== 200) {
            $this->expectException(WebapiException::class);
            $this->expectExceptionCode(6001);
            $this->expectExceptionMessage("Precondition Failed");
        }
        $this->currentMock->verifyWebhook();
    }

    /**
     * Data provider for {@see verifyWebhook_withVariousResponseCodes_returnsHookRequest}
     *
     * @return array[] containing HTTP response code
     */
    public function verifyWebhook_withVariousResponseCodesProvider()
    {
        return [
            ['responseCode' => 200],
            ['responseCode' => 422],
        ];
    }

    /**
     * @test
     * that setCommonMetaData adds Bolt trace ID to Bugsnag metadata if it is present in the request headers
     * 
     * @covers ::setCommonMetaData
     */
    public function setCommonMetaData_requestHasBoltTraceIdHeader_registersBugsnagCallback()
    {
        $this->initCurrentMock(null);
        $this->request->method('getHeader')->with('X-bolt-trace-id')->willReturn(self::BOLT_TRACE_ID);
        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function (callable $fn) {
                $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                $reportMock->expects(self::once())->method('setMetaData')->with([
                    'META DATA' => [
                        'bolt_trace_id' => self::BOLT_TRACE_ID
                    ]
                ]);
                $fn($reportMock);
            }
        );
        $this->currentMock->setCommonMetaData();
    }

    /**
     * @test
     * that setHeaders sets User-Agent header to contain store version and
     * X-Bolt-Plugin-Version to contain Bolt module version
     *
     * @covers ::setHeaders
     *
     * @throws ReflectionException from {@see \PHPUnit\Framework\TestCase::createMock} method
     */
    public function setHeaders_always_setsAdditionalResponseHeaders()
    {
        $this->initCurrentMock(['addHeaders']);
        $this->configHelper->method('getStoreVersion')->willReturn('storeVersion');
        $this->configHelper->method('getModuleVersion')->willReturn('moduleVersion');

        $headers = $this->createMock(\Zend\Http\Headers::class);
        $this->response->method('getHeaders')->willReturn($headers);
        $headers->expects(static::once())->method('addHeaders')->with([
            'User-Agent' => 'BoltPay/Magento-storeVersion/moduleVersion',
            'X-Bolt-Plugin-Version' => 'moduleVersion',
        ]);

        $this->currentMock->setHeaders();
    }

    /**
     * @test
     * that getStoreId returns store id from {@see \Bolt\Boltpay\Helper\Hook::$storeId} property
     *
     * @covers ::getStoreId
     *
     * @throws ReflectionException if {@see \Bolt\Boltpay\Helper\Hook::$storeId} property doesn't exist
     */
    public function getStoreId_always_returnsStoreId()
    {
        $this->initCurrentMock(null);
        TestHelper::setProperty($this->currentMock, 'storeId', self::STORE_ID);
        static::assertEquals(self::STORE_ID, $this->currentMock->getStoreId());
    }

    /**
     * @test
     * that setStoreId sets provided store id to {@see \Bolt\Boltpay\Helper\Hook::$storeId} property
     *
     * @covers ::setStoreId
     */
    public function setStoreId_always_setsStoreId()
    {
        $this->initCurrentMock(null);
        $this->currentMock->setStoreId(self::STORE_ID);
        static::assertAttributeEquals(self::STORE_ID , 'storeId', $this->currentMock);
    }

    /**
     * @test
     * that preProcessWebhook
     * 1. Sets store id using {@see \Bolt\Boltpay\Helper\Hook::setStoreId}
     * 2. Adds bolt_trace_id to bugsnag metadata using {@see \Bolt\Boltpay\Helper\Hook::setCommonMetaData}
     * 3. Sets additional response headers using {@see \Bolt\Boltpay\Helper\Hook::setHeaders}
     * 4. Verifies webhook request using {@see \Bolt\Boltpay\Helper\Hook::verifyWebhook}
     *
     * @covers ::preProcessWebhook
     *
     * @throws LocalizedException from the tested method
     */
    public function preProcessWebhook_always_verifiesHook()
    {
        $this->initCurrentMock(['setStoreId','setCommonMetaData','setHeaders','verifyWebhook']);

        $this->currentMock->expects(static::once())->method('setStoreId')->with(self::STORE_ID);
        $this->currentMock->expects(static::once())->method('setCommonMetaData');
        $this->currentMock->expects(static::once())->method('setHeaders');
        $this->currentMock->expects(static::once())->method('verifyWebhook');

        $this->currentMock->preProcessWebhook(self::STORE_ID);
    }
}
