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
 *
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\SSOHelper;
use Bolt\Boltpay\Model\Request;
use Bolt\Boltpay\Model\Response;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Exception;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass \Bolt\Boltpay\Helper\SSOHelper
 */
class SSOHelperTest extends BoltTestCase
{
    /**
     * @var Context|MockObject
     */
    private $context;

    /**
     * @var ConfigHelper|MockObject
     */
    private $configHelper;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var DataObjectFactory|MockObject
     */
    private $dataObjectFactory;

    /**
     * @var ApiHelper|MockObject
     */
    private $apiHelper;

    /**
     * @var SSOHelper|MockObject
     */
    private $currentMock;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        $this->context = $this->createMock(Context::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->apiHelper = $this->createMock(ApiHelper::class);
        $this->currentMock = $this->getMockBuilder(SSOHelper::class)
            ->setMethods(['getOAuthConfiguration', 'exchangeToken', 'parseAndValidateJWT', 'getPayload'])
            ->setConstructorArgs([
                $this->context,
                $this->configHelper,
                $this->storeManager,
                $this->dataObjectFactory,
                $this->apiHelper
            ])
            ->getMock();
    }

    /**
     * @test
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $instance = new SSOHelper(
            $this->context,
            $this->configHelper,
            $this->storeManager,
            $this->dataObjectFactory,
            $this->apiHelper
        );

        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
        static::assertAttributeEquals($this->storeManager, 'storeManager', $instance);
        static::assertAttributeEquals($this->dataObjectFactory, 'dataObjectFactory', $instance);
        static::assertAttributeEquals($this->apiHelper, 'apiHelper', $instance);
    }

    /**
     * @test
     *
     * @covers ::getOAuthConfiguration
     */
    public function getOAuthConfiguration_returnsCorrectValue()
    {
        $store = $this->createMock(Store::class);
        $store->expects(static::once())->method('getId')->willReturn(1);
        $this->storeManager->expects(static::once())->method('getStore')->willReturn($store);
        $this->configHelper->expects(static::once())->method('getPublishableKeyCheckout')->with(1)->willReturn('a.b.lastpart');
        $this->configHelper->expects(static::once())->method('getApiKey')->with(1)->willReturn('test api key');
        $this->configHelper->expects(static::once())->method('getPublicKey')->with(1)->willReturn('test public key');
        $this->assertEquals(
            ['lastpart', 'test api key', 'test public key'],
            TestHelper::invokeMethod($this->currentMock, 'getOAuthConfiguration')
        );
    }

    /**
     * @test
     *
     * @covers ::exchangeToken
     */
    public function exchangeToken_returnsExceptionMessage_ifExceptionIsThrown()
    {
        $this->storeManager->expects(static::once())->method('getStore')->willThrowException(new Exception('test exception'));
        $this->assertEquals('test exception', TestHelper::invokeMethod($this->currentMock, 'exchangeToken', ['', '', '', '']));
    }

    /**
     * @test
     *
     * @covers ::exchangeToken
     *
     * @dataProvider exchangeTokenProvider
     *
     * @param mixed        $responseBody
     * @param mixed|string $expected
     */
    public function exchangeToken_returnsCorrectValue_forAllCases($responseBody, $expected)
    {
        $store = $this->createMock(Store::class);
        $store->expects(static::once())->method('getId')->willReturn(1);
        $this->storeManager->expects(static::once())->method('getStore')->willReturn($store);
        $this->configHelper->expects(static::once())->method('getApiKey')->with(1)->willReturn('test api key');
        $dataObject = $this->createPartialMock(DataObject::class, ['setDynamicApiUrl', 'setApiKey', 'setApiData', 'setContentType']);
        $dataObject->expects(static::once())->method('setDynamicApiUrl')->with('oauth/token');
        $dataObject->expects(static::once())->method('setApiKey')->with('test api key');
        $dataObject->expects(static::once())->method('setApiData')->with('grant_type=authorization_code&code=abc&scope=openid&client_id=clientid&client_secret=clientsecret');
        $dataObject->expects(static::once())->method('setContentType')->with('application/x-www-form-urlencoded');
        $this->dataObjectFactory->expects(static::once())->method('create')->willReturn($dataObject);
        $request = $this->createMock(Request::class);
        $this->apiHelper->expects(static::once())->method('buildRequest')->with($dataObject)->willReturn($request);
        $response = $this->createPartialMock(Response::class, ['getResponse']);
        $response->expects(static::once())->method('getResponse')->willReturn($responseBody);
        $this->apiHelper->expects(static::once())->method('sendRequest')->with($request)->willReturn($response);
        $this->assertEquals($expected, TestHelper::invokeMethod($this->currentMock, 'exchangeToken', ['abc', 'openid', 'clientid', 'clientsecret']));
    }

    /**
     * Data provider for {@see exchangeToken_returnsCorrectValue_forAllCases}
     *
     * @return array
     */
    public function exchangeTokenProvider()
    {
        return [
            ['responseBody' => [], 'expected' => 'empty response'],
            [
                'responseBody' => ['access_token' => 'test access token', 'id_token' => 'test id token'],
                'expected'     => ['access_token' => 'test access token', 'id_token' => 'test id token']
            ],
        ];
    }

    /**
     * @test
     *
     * @covers ::parseAndValidateJWT
     */
    public function parseAndValidateJWT_returnsCorrectValue_ifJWTParseThrowsException()
    {
        $this->currentMock->expects(static::once())->method('getPayload')->willThrowException(new Exception('test exception'));
        $this->assertEquals(
            'test exception',
            TestHelper::invokeMethod($this->currentMock, 'parseAndValidateJWT', ['', '', ''])
        );
    }

    /**
     * @test
     *
     * @covers ::parseAndValidateJWT
     *
     * @dataProvider parseAndValidateJWTProvider
     *
     * @param mixed        $payload
     * @param mixed|string $expected
     */
    public function parseAndValidateJWT_returnsCorrectValue_forAllCases($payload, $expected)
    {
        $this->currentMock->expects(static::once())->method('getPayload')->willReturn($payload);
        $this->assertEquals(
            $expected,
            TestHelper::invokeMethod($this->currentMock, 'parseAndValidateJWT', ['', 'test audience', ''])
        );
    }

    /**
     * Data provider for {@see parseAndValidateJWT_returnsCorrectValue_forAllCases}
     *
     * @return array
     */
    public function parseAndValidateJWTProvider()
    {
        return [
            [
                'payload'  => [],
                'expected' => 'iss must be set'
            ],
            [
                'payload'  => ['iss' => 'not bolt'],
                'expected' => 'incorrect iss not bolt'
            ],
            [
                'payload'  => ['iss' => 'https://bolt.com'],
                'expected' => 'aud must be set'
            ],
            [
                'payload'  => ['iss' => 'https://bolt.com', 'aud' => ['blah', 'blah2']],
                'expected' => 'aud blah,blah2 does not contain audience test audience'
            ],
            [
                'payload'  => ['iss' => 'https://bolt.com', 'aud' => ['blah', 'test audience']],
                'expected' => 'sub must be set'
            ],
            [
                'payload'  => ['iss' => 'https://bolt.com', 'aud' => ['blah', 'test audience'], 'sub' => 'test sub'],
                'expected' => 'first_name must be set'
            ],
            [
                'payload'  => ['iss' => 'https://bolt.com', 'aud' => ['blah', 'test audience'], 'sub' => 'test sub', 'first_name' => 'first name'],
                'expected' => 'last_name must be set'
            ],
            [
                'payload'  => ['iss' => 'https://bolt.com', 'aud' => ['blah', 'test audience'], 'sub' => 'test sub', 'first_name' => 'first name', 'last_name' => 'last name'],
                'expected' => 'email must be set'
            ],
            [
                'payload'  => ['iss' => 'https://bolt.com', 'aud' => ['blah', 'test audience'], 'sub' => 'test sub', 'first_name' => 'first name', 'last_name' => 'last name', 'email' => 't@t.com'],
                'expected' => 'email_verified must be set'
            ],
            [
                'payload'  => ['iss' => 'https://bolt.com', 'aud' => ['blah', 'test audience'], 'sub' => 'test sub', 'first_name' => 'first name', 'last_name' => 'last name', 'email' => 't@t.com', 'email_verified' => true],
                'expected' => [
                    'iss'            => 'https://bolt.com',
                    'aud'            => ['blah', 'test audience'],
                    'sub'            => 'test sub',
                    'first_name'     => 'first name',
                    'last_name'      => 'last name',
                    'email'          => 't@t.com',
                    'email_verified' => true
                ]
            ]
        ];
    }
}
