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

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\Hook as BoltHook;
use PHPUnit\Framework\TestCase;
use Magento\Framework\App\Helper\AbstractHelper;
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

/**
 * Class ConfigTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper
 */
class HookTest extends TestCase
{
    const PAYLOAD = 'payload';
    const CORRECT_HMAC = 'correct HMAC';
    const INCORRECT_HMAC = 'incorrect HMAC';
    const STORE_ID = 1;
    const BOLT_TRACE_ID = 'BOLT_TRACE_ID';

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var BoltHook
     */
    private $currentMock;

    /**
     * @var Response
     */
    private $response;

    public function initCurrentMock($methods = null)
    {
        $this->context = $this->createMock(Context::class);
        $this->request = $this->createMock(Request::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->apiHelper = $this->createMock(ApiHelper::class);
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->response = $this->createMock(Response::class);

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
     */
    public function verifySignature_correctSignature()
    {
        $hmac_header = self::CORRECT_HMAC;
        $this->initCurrentMock(['computeSignature']);
        $this->currentMock->method('computeSignature')->with(self::PAYLOAD)->willReturn(self::CORRECT_HMAC);
        $this->assertTrue($this->currentMock->verifySignature(self::PAYLOAD, $hmac_header));
    }

    /**
     * @test
     */
    public function verifySignature_incorrectSignature()
    {
        $hmac_header = self::INCORRECT_HMAC;
        $this->initCurrentMock(['computeSignature']);
        $this->currentMock->method('computeSignature')->with(self::PAYLOAD)->willReturn(self::CORRECT_HMAC);
        $this->assertFalse($this->currentMock->verifySignature(self::PAYLOAD, $hmac_header));
    }

    /**
     * @test
     */
    public function computeSignature()
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
     */
    public function verifyWebhook_whenVerifySignatureIsTrue()
    {
        $this->initCurrentMock(['verifySignature']);
        $this->currentMock->method('verifySignature')->with(self::PAYLOAD, self::CORRECT_HMAC)->willReturn(true);
        $this->verifyWebhook_adjustRequestMock();
        $this->currentMock->verifyWebhook();
        // no exception
    }

    public function verifyWebhook_dataProvider()
    {
        return [
            [200],
            [422]
        ];
    }

    /**
     * @test
     * @dataProvider verifyWebhook_dataProvider
     */
    public function verifyWebhook_whenVerifySignatureIsFalse($answerCode)
    {
        $this->initCurrentMock(['verifySignature','getStoreId']);
        $this->currentMock->method('verifySignature')->with(self::PAYLOAD, self::CORRECT_HMAC)->willReturn(false);
        $this->currentMock->method('getStoreId')->willReturn(self::STORE_ID);

        $this->verifyWebhook_adjustRequestMock();

        //private function verifyWebhookApi
        $requestData = $this->getMockBuilder(DataObject::class)
            ->setMethods(['setApiData','setDynamicApiUrl','setApiKey','setHeaders','setStatusOnly'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->dataObjectFactory->method('create')->willReturn($requestData);
        $requestData->expects($this->once())->method('setApiData')->with(json_decode(self::PAYLOAD));
        $requestData->expects($this->once())->method('setDynamicApiUrl')->with('merchant/verify_signature');
        $this->configHelper->method('getApiKey')->with(self::STORE_ID)->willReturn('ApiKey');
        $requestData->expects($this->once())->method('setApiKey')->with('ApiKey');
        $requestData->expects($this->once())->method('setHeaders')->with(['X-Bolt-Hmac-Sha256'=>self::CORRECT_HMAC]);
        $requestData->expects($this->once())->method('setStatusOnly')->with(true);

        $request =  $this->createMock(BoltRequest::class);
        $this->apiHelper->expects($this->once())->method('buildRequest')->with($requestData)->willReturn($request);
        $this->apiHelper->expects($this->once())->method('sendRequest')->with($request)->willReturn($answerCode);

        $this->verifyWebhook_adjustRequestMock();
        // should return exception for any answerCode except 200
        try {
            $this->currentMock->verifyWebhook();
            if ($answerCode<>200) {
                $this->fail("Expected exception not thrown");
            }
        } catch (WebapiException $e) {
            if ($answerCode==200) {
                $this->fail("Unexpected exception");
            }
            $this->assertEquals(6001, $e->getCode());
            $this->assertEquals("Precondition Failed", $e->getMessage());
        }
    }

    /**
     * @test
     */
    public function setCommonMetaData()
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
     */
    public function setHeaders()
    {
        $this->initCurrentMock(null);
        $this->configHelper->method('getStoreVersion')->willReturn('storeVersion');
        $this->configHelper->method('getModuleVersion')->willReturn('moduleVersion');

        $headers = $this->createMock(\Zend\Http\Headers::class);
        $this->response->method('getHeaders')->willReturn($headers);
        $headers->expects($this->once())->method('addHeaders')->with([
            'User-Agent' => 'BoltPay/Magento-storeVersion/moduleVersion',
            'X-Bolt-Plugin-Version' => 'moduleVersion',
        ]);

        $this->currentMock->setHeaders();
    }

    /**
     * @test
     */
    public function setAndGetStoreId()
    {
        $this->initCurrentMock(null);
        $this->assertNull($this->currentMock->getStoreId());
        $this->currentMock->setStoreId(self::STORE_ID);
        $this->assertEquals(self::STORE_ID, $this->currentMock->getStoreId());
    }

    /**
     * @test
     */
    public function preProcessWebhook()
    {
        $this->initCurrentMock(['setStoreId','setCommonMetaData','setHeaders','verifyWebhook']);

        $this->currentMock->expects($this->once())->method('setStoreId')->with(self::STORE_ID);
        $this->currentMock->expects($this->once())->method('setCommonMetaData');
        $this->currentMock->expects($this->once())->method('setHeaders');
        $this->currentMock->expects($this->once())->method('verifyWebhook');

        $this->currentMock->preProcessWebhook(self::STORE_ID);
    }
}
