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

namespace Bolt\Boltpay\Test\Unit\Controller\Adminhtml\Cart;

use Bolt\Boltpay\Controller\Adminhtml\Cart\Data;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Model\Response;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Zend\Log\Filter\Mock;

/**
 * Class DataTest
 * @package Bolt\Boltpay\Test\Unit\Controller\Adminhtml\Cart
 * @coversDefaultClass \Bolt\Boltpay\Controller\Adminhtml\Cart\Data
 */
class DataTest extends TestCase
{
    const PLACE_ORDER_PAYLOAD = 'payload';
    const STORE_ID = '42';
    const PUBLISHABLE_KEY = 'publishable_key';
    const IS_PREAUTH = true;
    const HINT = 'hint!';
    const RESPONSE_TOKEN = 'response_token';

    /**
     * @var Context
     */
    private $context;

    /**
     * @var MockObject|JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var MockObject|Cart
     */
    private $cartHelper;

    /**
     * @var MockObject|Config
     */
    private $configHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var MetricsClient
     */
    private $metricsClient;

    private $happyCartArray;

    private $noTokenCartArray;

    private $dataObjectFactory;

    private $happyCartData;

    private $noTokenCartData;

    private $map;

    /**
     * @var MockObject|RequestInterface
     */
    private $request;

    protected function setUp()
    {
        $this->initData();
        $this->initRequiredMocks();
    }

    private function initData()
    {
        $this->happyCartArray = ['orderToken' => self::RESPONSE_TOKEN];
        $this->happyCartData = [
            'cart' => [
                'orderToken' => self::RESPONSE_TOKEN
                ],
            'hints' => self::HINT,
            'publishableKey' => self::PUBLISHABLE_KEY,
            'storeId' => self::STORE_ID,
            'isPreAuth' => self::IS_PREAUTH
        ];

        $this->noTokenCartArray = ['orderToken' => ''];
        $this->noTokenCartData = [
            'cart' => [
                'orderToken' => ''
            ],
            'hints' => self::HINT,
            'publishableKey' => self::PUBLISHABLE_KEY,
            'storeId' => self::STORE_ID,
            'isPreAuth' => self::IS_PREAUTH
        ];

        $this->map = [
            ['place_order_payload', null, self::PLACE_ORDER_PAYLOAD]
        ];
    }

    private function initRequiredMocks()
    {
        $this->context = $this->createMock(Context::class);

        $this->metricsClient = $this->createMock(MetricsClient::class);

        $this->bugsnag = $this->createMock(Bugsnag::class);

        $this->request = $this->createMock(RequestInterface::class);
        $this->request->method('getParam')
            ->will($this->returnValueMap($this->map));

        $this->cartHelper = $this->createMock(Cart::class);
        $this->cartHelper->method('getSessionQuoteStoreId')
            ->willReturn(self::STORE_ID);
        $this->cartHelper->method('getHints')
            ->willReturn(self::HINT);

        $this->configHelper = $this->createMock(Config::class);
        $this->configHelper->method('getPublishableKeyBackOffice')
            ->willReturn(self::PUBLISHABLE_KEY)
            ->with(self::STORE_ID);
        $this->configHelper->method('getIsPreAuth')
            ->willReturn(self::IS_PREAUTH)
            ->with(self::STORE_ID);

        $this->resultJsonFactory = $this->createMock(JsonFactory::class);

        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
    }

    private function buildJsonMocks($expected)
    {
        $json = $this->getMockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->getMock();
        $json->expects($this->at(0))
            ->method('setData')
            ->with($expected);
        $resultJsonFactory = $this->getMockBuilder(JsonFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $resultJsonFactory->method('create')
            ->willReturn($json);
        return $resultJsonFactory;
    }
    
    /**
     * @test
     * that constructor sets internal properties
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $instance = new Data(
            $this->context,
            $this->resultJsonFactory,
            $this->cartHelper,
            $this->configHelper,
            $this->bugsnag,
            $this->metricsClient,
            $this->dataObjectFactory
        );
        
        $this->assertAttributeEquals($this->resultJsonFactory, 'resultJsonFactory', $instance);
        $this->assertAttributeEquals($this->cartHelper, 'cartHelper', $instance);
        $this->assertAttributeEquals($this->configHelper, 'configHelper', $instance);
        $this->assertAttributeEquals($this->bugsnag, 'bugsnag', $instance);
        $this->assertAttributeEquals($this->metricsClient, 'metricsClient', $instance);
        $this->assertAttributeEquals($this->dataObjectFactory, 'dataObjectFactory', $instance);
    }

    /**
     * @test
     */
    public function execute_NoBoltpayOrder()
    {
        $this->cartHelper->method('getBoltpayOrder')
            ->willReturn(null);

        $dataObject = $this->createMock(DataObject::class);
        $dataObject->method('getData')
            ->willReturn($this->noTokenCartData);
        $dataObject->expects($this->at(0))
            ->method('setData')
            ->with($this->equalTo('cart'), $this->equalTo($this->noTokenCartArray));

        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->dataObjectFactory->method('create')
            ->willReturn($dataObject);

        $resultJsonFactory = $this->buildJsonMocks($this->noTokenCartData);

        $data = $this->getMockBuilder(Data::class)
            ->setMethods(['getRequest'])
            ->setConstructorArgs([
                $this->context,
                $resultJsonFactory,
                $this->cartHelper,
                $this->configHelper,
                $this->bugsnag,
                $this->metricsClient,
                $this->dataObjectFactory
            ])
            ->getMock();

        $data->method('getRequest')->willReturn($this->request);
        $data->execute();
    }

    /**
     * @test
     */
    public function execute_HappyPath()
    {
        $boltpayOrder = $this->getMockBuilder(Response::class)
            ->setMethods(['getResponse'])
            ->disableOriginalConstructor()
            ->getMock();
        $boltpayOrder->method('getResponse')
            ->willReturn(['token' => self::RESPONSE_TOKEN]);

        $this->cartHelper->method('getBoltpayOrder')
            ->willReturn($boltpayOrder);

        $dataObject = $this->createMock(DataObject::class);
        $dataObject->method('getData')
            ->willReturn($this->happyCartData);
        $dataObject->expects($this->at(0))
            ->method('setData')
            ->with($this->equalTo('cart'), $this->equalTo($this->happyCartArray));

        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->dataObjectFactory->method('create')
            ->willReturn($dataObject);

        $resultJsonFactory = $this->buildJsonMocks($this->happyCartData);

        $data = $this->getMockBuilder(Data::class)
            ->setMethods(['getRequest'])
            ->setConstructorArgs([
                $this->context,
                $resultJsonFactory,
                $this->cartHelper,
                $this->configHelper,
                $this->bugsnag,
                $this->metricsClient,
                $this->dataObjectFactory
            ])
            ->getMock();

        $data->method('getRequest')->willReturn($this->request);
        $data->execute();
    }

    /**
     * @test
     * @expectedException \Exception
     */
    public function execute_ThrowsException()
    {
        $exception = new \Exception("This is an exception");

        $bugsnag = $this->createMock(Bugsnag::class);
        $bugsnag->expects($this->at(0))
            ->method('notifyException')
            ->with($exception);

        $data = $this->getMockBuilder(Data::class)
            ->setMethods(['getRequest'])
            ->setConstructorArgs([
                $this->context,
                $this->resultJsonFactory,
                $this->cartHelper,
                $this->configHelper,
                $bugsnag,
                $this->metricsClient,
                $this->dataObjectFactory
            ])
            ->getMock();
        $this->cartHelper->method('getBoltpayOrder')->willThrowException($exception);
        $data->execute();
    }
}
