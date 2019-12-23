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

namespace Bolt\Boltpay\Test\Unit\Helper\GraphQL;


use Bolt\Boltpay\Helper\Config as BoltConfig;
use Bolt\Boltpay\Helper\GraphQL\Client;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\HTTP\ZendClient;
use Bolt\Boltpay\Model\ResponseFactory;
use Bolt\Boltpay\Model\Response as BoltResponse;
use Bolt\Boltpay\Model\RequestFactory;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use PHPUnit\Framework\TestCase;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Zend\Http\Response;

/**
 * Class ClientTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper\GraphQL
 */
class ClientTest extends TestCase
{

    /**
     * @var Context
     */
    private $context;

    /**
     * @var ZendClientFactory\
     */
    private $zendClientFactory;

    /**
     * @var BoltConfig
     */
    private $boltConfig;

    /**
     * @var ResponseFactory\
     */
    private $responseFactory;

    /**
     * @var RequestFactory\
     */
    private $requestFactory;

    /**
     * @var Log\
     */
    private $logger;

    /**
     * @var Bugsnag\
     */
    private $bugsnag;

    /**
     * @var Client\
     */
    private $graphQLClient;

    /**
     * @inheritdoc
     */
    public function setUp()
    {

        $this->context = $this->createMock(Context::class);
        $this->zendClientFactory = $this->createMock(ZendClientFactory::class);
        $this->boltConfig = $this->createMock(BoltConfig::class);
        $this->responseFactory = $this->createMock(ResponseFactory::class);
        $this->requestFactory = $this->createMock(RequestFactory::class);
        $this->logger = $this->createMock(LogHelper::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);


        $this->graphQLClient = (new ObjectManager($this))->getObject(
            Client::class,
            [
                'context' => $this->context,
                'httpClientFactory' => $this->zendClientFactory,
                'configHelper' => $this->boltConfig,
                'responseFactory' => $this->responseFactory,
                'requestFactory' => $this->requestFactory,
                'logHelper' => $this->logger,
                'bugsnag' => $this->bugsnag,
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function testGetFeatureSwitches_success() {
        $httpClient = $this->createMock(ZendClient::class);
        $response = $this->createMock(Response::class);

        $this->zendClientFactory
            ->method('create')
            ->willReturn($httpClient);

        $httpClient->method('setRawData')
            ->willReturn($httpClient);

        $httpClient->method('request')
            ->willReturn($response);

        $response->method('getBody')
            ->willReturn('{"data": {"features": {"name": "OK", "value": true, "default_value": false, "rollout_percentage": 100}}}');

        $this->responseFactory->method('create')->willReturn(new BoltResponse());

        $response = $this->graphQLClient->getFeatureSwitches();
        $this->assertEquals($response->getData()["response"]->data->features->name, "OK");
    }

    /**
     * @inheritdoc
     */
    public function testGetFeatureSwitches_fail() {
        $httpClient = $this->createMock(ZendClient::class);
        $response = $this->createMock(Response::class);

        $this->zendClientFactory
            ->method('create')
            ->willReturn($httpClient);

        $httpClient->method('setRawData')
            ->willReturn($httpClient);

        $httpClient->method('request')
            ->willReturn($response);

        $response->method('getBody')
            ->willReturn('{"errors": [{"message": "no_dice"}]}');


        $this->expectExceptionMessage("Something went wrong when talking to Bolt.");

        $this->graphQLClient->getFeatureSwitches();
    }

    public function testSendLogs_success() {
        $httpClient = $this->createMock(ZendClient::class);
        $response = $this->createMock(Response::class);

        $this->zendClientFactory
            ->method('create')
            ->willReturn($httpClient);

        $httpClient->method('setRawData')
            ->willReturn($httpClient);

        $httpClient->method('request')
            ->willReturn($response);

        $response->method('getBody')
            ->willReturn('{"data": {"logMerchantLogs": {"isSuccessful": true}}}');

        $this->responseFactory->method('create')->willReturn(new BoltResponse());

        $response = $this->graphQLClient->sendLogs("[{}]");
        $this->assertEquals($response->getData()["response"]->data->logMerchantLogs->isSuccessful, true);
    }

    /**
     * @inheritdoc
     */
    public function testSendLogs_fail() {
        $httpClient = $this->createMock(ZendClient::class);
        $response = $this->createMock(Response::class);

        $this->zendClientFactory
            ->method('create')
            ->willReturn($httpClient);

        $httpClient->method('setRawData')
            ->willReturn($httpClient);

        $httpClient->method('request')
            ->willReturn($response);

        $response->method('getBody')
            ->willReturn('{"errors": [{"message": "no_dice"}]}');


        $this->expectExceptionMessage("Something went wrong when talking to Bolt.");

        $this->graphQLClient->sendLogs("[{}]");
    }
}

