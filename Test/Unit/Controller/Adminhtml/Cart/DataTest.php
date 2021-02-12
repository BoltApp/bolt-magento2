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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
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
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Catalog\Helper\Product;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObjectFactory;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Magento\Framework\Escaper;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class DataTest
 *
 * @package Bolt\Boltpay\Test\Unit\Controller\Adminhtml\Cart
 * @coversDefaultClass \Bolt\Boltpay\Controller\Adminhtml\Cart\Data
 */
class DataTest extends BoltTestCase
{
    const PLACE_ORDER_PAYLOAD = 'payload';
    const STORE_ID = '42';
    const PUBLISHABLE_KEY = 'publishable_key';
    const IS_PREAUTH = true;
    const HINT = 'hint!';
    const RESPONSE_TOKEN = 'response_token';
    const CUSTOMER_EMAIL = 'test@bolt.com';

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
    private $cartHelperMock;

    /**
     * @var MockObject|Config
     */
    private $configHelperMock;

    /**
     * @var Bugsnag
     */
    private $bugsnagHelperMock;

    /**
     * @var MetricsClient
     */
    private $metricsClientMock;

    private $happyCartArray;

    private $noTokenCartArray;

    private $dataObjectFactoryMock;

    private $happyCartData;

    private $noTokenCartData;

    private $map;

    /**
     * @var MockObject|RequestInterface
     */
    private $request;

    /**
     * @var MockObject|\Magento\Backend\Model\Session\Quote
     */
    private $sessionMock;

    /**
     * @var \Magento\Sales\Model\AdminOrder\Create|\PHPUnit\Framework\MockObject\MockObject
     */
    private $orderCreateModel;

    /**
     * @var \Magento\Quote\Model\Quote|\PHPUnit\Framework\MockObject\MockObject
     */
    private $quoteMock;

    /**
     * @var \Magento\Quote\Model\Quote\Address|\PHPUnit\Framework\MockObject\MockObject
     */
    private $quoteBillingAddressMock;

    /**
     * @var Product|\PHPUnit\Framework\MockObject\MockObject
     */
    private $productHelperMock;

    /**
     * @var Escaper|\PHPUnit\Framework\MockObject\MockObject
     */
    private $escaperMock;

    /**
     * @var PageFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $resultPageFactoryMock;

    /**
     * @var StoreManagerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $storeManagerMock;

    /**
     * @var ForwardFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $resultForwardFactoryMock;

    /**
     * @var Data|\PHPUnit\Framework\MockObject\MockObject
     */
    private $currentMock;

    /**
     * @var Json|\PHPUnit\Framework\MockObject\MockObject
     */
    private $resultJsonMock;

    protected function setUpInternal()
    {
        $this->initData();
        $this->initRequiredMocks();
    }

    private function initData()
    {
        $this->happyCartArray = ['orderToken' => self::RESPONSE_TOKEN];
        $this->happyCartData = [
            'cart'           => [
                'orderToken' => self::RESPONSE_TOKEN
            ],
            'hints'          => self::HINT,
            'storeId'        => self::STORE_ID,
            'isPreAuth'      => self::IS_PREAUTH,
            'backOfficeKey'  => self::PUBLISHABLE_KEY,
            'paymentOnlyKey' => null
        ];

        $this->noTokenCartArray = ['orderToken' => ''];
        $this->noTokenCartData = [
            'cart'           => [
                'errorMessage' => 'Bolt order was not created successfully'
            ],
            'hints'          => self::HINT,
            'storeId'        => self::STORE_ID,
            'isPreAuth'      => self::IS_PREAUTH,
            'backOfficeKey'  => self::PUBLISHABLE_KEY,
            'paymentOnlyKey' => '',
        ];

        $this->map = [
            ['place_order_payload', null, self::PLACE_ORDER_PAYLOAD]
        ];
    }

    private function initRequiredMocks()
    {
        $this->context = $this->createMock(Context::class);

        $this->metricsClientMock = $this->createMock(MetricsClient::class);

        $this->bugsnagHelperMock = $this->createMock(Bugsnag::class);

        $this->request = $this->createMock(RequestInterface::class);
        $this->request->method('getParam')->willReturnMap($this->map);

        $this->cartHelperMock = $this->createMock(Cart::class);
        $this->cartHelperMock->method('getSessionQuoteStoreId')
            ->willReturn(self::STORE_ID);
        $this->cartHelperMock->method('getHints')
            ->willReturn(self::HINT);

        $this->configHelperMock = $this->createMock(Config::class);
        $this->configHelperMock->method('getPublishableKeyBackOffice')
            ->willReturn(self::PUBLISHABLE_KEY);
        $this->configHelperMock->method('getIsPreAuth')
            ->willReturn(self::IS_PREAUTH);

        $this->quoteMock = $this->createPartialMock(
            \Magento\Quote\Model\Quote::class,
            [
                'getStoreId',
                'getCustomerEmail',
                'getCustomerId',
                'setCustomerEmail',
            ]
        );

        $this->resultJsonMock = $this->createMock(\Magento\Framework\Controller\Result\Json::class);
        $this->resultJsonFactory = $this->createMock(JsonFactory::class);
        $this->resultJsonFactory->method('create')->willReturn($this->resultJsonMock);

        $this->dataObjectFactoryMock = $this->createMock(DataObjectFactory::class);
        $this->sessionMock = $this->createPartialMock(
            \Magento\Backend\Model\Session\Quote::class,
            ['getStoreId']
        );
        $this->orderCreateModel = $this->getMockBuilder(
            \Magento\Sales\Model\AdminOrder\Create::class
        )->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();
        $this->quoteBillingAddressMock = $this->createMock(\Magento\Quote\Model\Quote\Address::class);
        $this->productHelperMock = $this->createMock(Product::class);
        $this->escaperMock = $this->createMock(Escaper::class);
        $this->resultPageFactoryMock = $this->createMock(PageFactory::class);
        $this->resultForwardFactoryMock = $this->createMock(ForwardFactory::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);

        $this->currentMock = $this->getMockBuilder(Data::class)
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->resultJsonFactory,
                    $this->cartHelperMock,
                    $this->configHelperMock,
                    $this->bugsnagHelperMock,
                    $this->metricsClientMock,
                    $this->dataObjectFactoryMock,
                ]
            )
            ->setMethods(['_getSession', '_initSession', '_getOrderCreateModel'])
            ->getMock();
        $this->currentMock->method('_getSession')->willReturn($this->sessionMock);
        $this->currentMock->method('_getOrderCreateModel')->willReturn($this->orderCreateModel);
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
            $this->cartHelperMock,
            $this->configHelperMock,
            $this->bugsnagHelperMock,
            $this->metricsClientMock,
            $this->dataObjectFactoryMock
        );

        static::assertAttributeEquals($this->resultJsonFactory, 'resultJsonFactory', $instance);
        static::assertAttributeEquals($this->cartHelperMock, 'cartHelper', $instance);
        static::assertAttributeEquals($this->configHelperMock, 'configHelper', $instance);
        static::assertAttributeEquals($this->bugsnagHelperMock, 'bugsnag', $instance);
        static::assertAttributeEquals($this->metricsClientMock, 'metricsClient', $instance);
        static::assertAttributeEquals($this->dataObjectFactoryMock, 'dataObjectFactory', $instance);
    }

    /**
     * @test
     *
     * @covers ::execute
     */
    public function execute_NoBoltpayOrder()
    {
        $this->sessionMock->method('getStoreId')->willReturn(self::STORE_ID);
        $this->cartHelperMock->method('getBoltpayOrder')->willReturn(null);

        $resultJsonFactory = $this->buildJsonMocks($this->noTokenCartData);

        $currentMock = $this->getMockBuilder(Data::class)
            ->setMethods(['getRequest', '_getSession', '_initSession', '_getOrderCreateModel'])
            ->setConstructorArgs(
                [
                    $this->context,
                    $resultJsonFactory,
                    $this->cartHelperMock,
                    $this->configHelperMock,
                    $this->bugsnagHelperMock,
                    $this->metricsClientMock,
                    $this->dataObjectFactoryMock
                ]
            )
            ->getMock();

        $currentMock->method('getRequest')->willReturn($this->request);
        $currentMock->method('_getSession')->willReturn($this->sessionMock);
        $currentMock->method('_getOrderCreateModel')->willReturn($this->orderCreateModel);
        $this->orderCreateModel->method('getQuote')->willReturn($this->quoteMock);
        $this->orderCreateModel->expects(static::once())->method('getBillingAddress')
            ->willReturn($this->quoteBillingAddressMock);
        $currentMock->execute();
    }

    /**
     * @test
     */
    public function execute_HappyPath()
    {
        $this->sessionMock->method('getStoreId')->willReturn(self::STORE_ID);

        $boltpayOrder = $this->getMockBuilder(Response::class)
            ->setMethods(['getResponse'])
            ->disableOriginalConstructor()
            ->getMock();
        $boltpayOrder->method('getResponse')
            ->willReturn((object)['token' => self::RESPONSE_TOKEN]);

        $this->cartHelperMock->method('getBoltpayOrder')
            ->willReturn($boltpayOrder);

        $resultJsonFactory = $this->buildJsonMocks($this->happyCartData);

        $currentMock = $this->getMockBuilder(Data::class)
            ->setMethods(['getRequest', '_getSession', '_initSession', '_getOrderCreateModel'])
            ->setConstructorArgs(
                [
                    $this->context,
                    $resultJsonFactory,
                    $this->cartHelperMock,
                    $this->configHelperMock,
                    $this->bugsnagHelperMock,
                    $this->metricsClientMock,
                    $this->dataObjectFactoryMock
                ]
            )
            ->getMock();
        $currentMock->method('getRequest')->willReturn($this->request);
        $currentMock->method('_getSession')->willReturn($this->sessionMock);
        $currentMock->method('_getOrderCreateModel')->willReturn($this->orderCreateModel);
        $this->orderCreateModel->method('getQuote')->willReturn($this->quoteMock);
        $this->orderCreateModel->expects(static::once())->method('getBillingAddress')
            ->willReturn($this->quoteBillingAddressMock);
        $currentMock->execute();
    }

    /**
     * @test
     */
    public function execute_ThrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This is an exception');
        $exception = new \Exception('This is an exception');

        $bugsnag = $this->createMock(Bugsnag::class);
        $bugsnag->expects(static::once())->method('notifyException')->with($exception);

        $currentMock = $this->getMockBuilder(Data::class)
            ->setMethods(['getRequest', '_getSession', '_initSession', '_getOrderCreateModel'])
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->resultJsonFactory,
                    $this->cartHelperMock,
                    $this->configHelperMock,
                    $bugsnag,
                    $this->metricsClientMock,
                    $this->dataObjectFactoryMock
                ]
            )
            ->getMock();
        $this->cartHelperMock->method('getBoltpayOrder')->willThrowException($exception);
        $currentMock->method('getRequest')->willReturn($this->request);
        $currentMock->method('_getSession')->willReturn($this->sessionMock);
        $currentMock->method('_getOrderCreateModel')->willReturn($this->orderCreateModel);
        $this->orderCreateModel->method('getQuote')->willReturn($this->quoteMock);
        $this->sessionMock->expects(static::once())->method('getStoreId')->willReturn(self::STORE_ID);
        $this->orderCreateModel->expects(static::once())->method('getBillingAddress')
            ->willReturn($this->quoteBillingAddressMock);
        $currentMock->execute();
    }

    /**
     * @test
     * that execute will return an error response and not attempt Bolt order creation if the session store id is not set
     *
     * @covers ::execute
     */
    public function execute_withSessionStoreIdNotSet_returnsErrorResponse()
    {
        $this->sessionMock->expects(static::once())->method('getStoreId')->willReturn(null);
        $this->currentMock->expects(static::never())->method('_initSession');
        $this->cartHelperMock->expects(static::never())->method('getBoltpayOrder');

        $this->resultJsonMock->expects(static::once())->method('setData')->with(
            [
                'cart'           => ['errorMessage' => 'Order creation not initialized'],
                'hints'          => [],
                'backOfficeKey'  => '',
                'paymentOnlyKey' => '',
                'storeId'        => '',
                'isPreAuth'      => '',
            ]
        );

        $this->currentMock->execute();
    }

    /**
     * @test
     * that execute will return an error response and not attempt Bolt order creation if the session store id is not set
     *
     * @covers ::execute
     */
    public function execute_withAlreadyUsedCustomerEmail_returnsErrorResponse()
    {
        $this->sessionMock->expects(static::once())->method('getStoreId')->willReturn(self::STORE_ID);
        $this->currentMock->expects(static::once())->method('_initSession');
        $this->cartHelperMock->expects(static::never())->method('getBoltpayOrder');

        $this->orderCreateModel->expects(static::once())->method('getQuote')->willReturn($this->quoteMock);
        $this->orderCreateModel->expects(static::never())->method('getBillingAddress')
            ->willReturn($this->quoteBillingAddressMock);

        $this->quoteMock->expects(static::once())->method('getCustomerId')->willReturn(null);
        $this->quoteMock->expects(static::once())->method('getCustomerEmail')->willReturn(self::CUSTOMER_EMAIL);
        $this->cartHelperMock->expects(static::once())->method('getCustomerByEmail')->with(self::CUSTOMER_EMAIL)
            ->willReturn(true);

        $this->resultJsonMock->expects(static::once())->method('setData')->with(
            [
                'cart'           => [
                    'errorMessage' => 'A customer with the same email address already exists in an associated website.'
                ],
                'hints'          => [],
                'backOfficeKey'  => self::PUBLISHABLE_KEY,
                'paymentOnlyKey' => '',
                'storeId'        => self::STORE_ID,
                'isPreAuth'      => self::IS_PREAUTH,
            ]
        );

        $this->currentMock->execute();
    }
}
