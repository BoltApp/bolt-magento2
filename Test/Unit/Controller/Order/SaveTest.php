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

namespace Bolt\Boltpay\Test\Unit\Controller\Order;

use Bolt\Boltpay\Controller\Order\Save;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Test\Unit\ObjectManagerFactoryTest;
use Magento\Framework\Controller\Result\JsonFactory as ResultJsonFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Class HintsTest
 * @package Bolt\Boltpay\Test\Unit\Controller\Order
 * @coversDefaultClass \Bolt\Boltpay\Controller\Order\Save
 */
class SaveTest extends TestCase
{
    const ORDER_ID = 1234;
    const QUOTE_ID = 5678;
    const PPC_QUOTE_ID = 5679;
    const INCREMENT_ID = 1235;
    const STATUS = "Ready";
    const REFERENCE = "referenceValue";
    const SUCCESS_URL = "http://url.return.value/";
    const EXCEPTION_MESSAGE = "Exception Message";

    /**
     * @var Order | MockObject
     */
    private $orderMock;

    /**
     * @var Quote | MockObject
     */
    private $quoteMock;

    /**
     * @var Bugsnag | MockObject
     */
    private $bugsnagMock;

    /**
     * @var OrderHelper | MockObject
     */
    private $orderHelper;

    /**
     * @var Session | MockObject
     */
    private $checkoutSession;

    /**
     * @var Config | MockObject
     */
    private $configHelper;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var Context
     */
    private $context;
    
    /**
     * @var MockObject|ResultJsonFactory mocked instance of JsonFactory
     */
    private $resultJsonFactory;

    protected function setUp()
    {
        $this->initRequiredMocks();
    }

    /**
     * @test
     * that constructor sets internal properties
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $instance = new Save(
            $this->context,
            $this->resultJsonFactory,
            $this->checkoutSession,
            $this->orderHelper,
            $this->configHelper,
            $this->bugsnagMock,
            $this->dataObjectFactory
        );

        $this->assertAttributeEquals($this->resultJsonFactory, 'resultJsonFactory', $instance);
        $this->assertAttributeEquals($this->checkoutSession, 'checkoutSession', $instance);
        $this->assertAttributeEquals($this->orderHelper, 'orderHelper', $instance);
        $this->assertAttributeEquals($this->configHelper, 'configHelper', $instance);
        $this->assertAttributeEquals($this->bugsnagMock, 'bugsnag', $instance);
        $this->assertAttributeEquals($this->dataObjectFactory, 'dataObjectFactory', $instance);
    }

    /**
     * @test
     * @param $isPPCCase true for PPC case, when we need to save checkout quote
     *
     * @dataProvider executeProvider
     */
    public function execute_HappyPath($isPPCCase)
    {
        $result = [
            'status' => 'success',
            'success_url' => self::SUCCESS_URL
        ];

        $json = $this->getMockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->getMock();
        $json->expects($this->once())
            ->method('setData')
            ->with($result);
        $jsonFactory = $this->getMockBuilder(ResultJsonFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $jsonFactory->method('create')->willReturn($json);

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturn(self::SUCCESS_URL);
        $this->context->method('getUrl')->willReturn($url);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturn(self::REFERENCE);

        $this->orderHelper->method('saveUpdateOrder')->willReturn([$this->quoteMock, $this->orderMock]);

        $checkoutSession = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'setLastQuoteId',
                'setLastSuccessQuoteId',
                'clearHelperData',
                'replaceQuote',
                'setQuoteId',
                'setLastOrderId',
                'setLastRealOrderId',
                'setLastOrderStatus',
                'getQuote'
            ])
            ->getMock();

        //clearQuoteSession
        $checkoutSession->expects($this->once())->method('setLastQuoteId')->with(self::QUOTE_ID)->willReturnSelf();
        $checkoutSession->expects($this->once())->method('setLastSuccessQuoteId')->with(self::QUOTE_ID)->willReturnSelf();
        $checkoutSession->expects($this->once())->method('clearHelperData')->willReturnSelf();

        //clearOrderSession
        $checkoutSession->expects($this->once())->method('setLastOrderId')->with(self::ORDER_ID)->willReturnSelf();
        $checkoutSession->expects($this->once())->method('setLastRealOrderId')->with(self::INCREMENT_ID)->willReturnSelf();
        $checkoutSession->expects($this->once())->method('setLastOrderStatus')->with(self::STATUS)->willReturnSelf();

        if ($isPPCCase) {
            $checkoutQuote = $this->createMock(Order::class);
            $checkoutQuote->method('getId')->willReturn(self::PPC_QUOTE_ID);
            $checkoutSession->expects($this->once())->method('getQuote')->willReturn($checkoutQuote);
            //replaceQuote
            $checkoutSession->method('replaceQuote')->with($checkoutQuote);
        } else {
            $checkoutSession->expects($this->once())->method('getQuote')->willReturn(null);
            //replaceQuote
            $checkoutSession->method('replaceQuote')->with($this->quoteMock);
        }

        $save = $this->getMockBuilder(Save::class)
            ->setMethods([
                'getRequest',
                'replaceQuote',
                'clearQuoteSession',
                'clearOrderSession'
            ])
            ->setConstructorArgs([
                $this->context,
                $jsonFactory,
                $checkoutSession,
                $this->orderHelper,
                $this->configHelper,
                $this->bugsnagMock,
                $this->dataObjectFactory
            ])
            ->getMock();

        $save->method('getRequest')->willReturn($request);

        $save->execute();
    }

    public function executeProvider()
    {
        return [[true], [false]];
    }

    /**
     * @test
     */
    public function execute_Exception()
    {
        $expected = [
            'status' => 'error',
            'code' => 6009,
            'message' => self::EXCEPTION_MESSAGE,
            'reference' => null
        ];
        $exception = new \Exception(self::EXCEPTION_MESSAGE);

        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getParam')->with('reference');

        $this->bugsnagMock->expects($this->once())->method('notifyException')->with($exception);

        $this->orderHelper->method('saveUpdateOrder')->willThrowException($exception);

        $json = $this->getMockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->getMock();
        $json->expects($this->once())->method('setHttpResponseCode')->with(422);
        $json->expects($this->once())->method('setData')->with($expected);
        $jsonFactory = $this->getMockBuilder(ResultJsonFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $jsonFactory->method('create')->willReturn($json);

        $save = $this->getMockBuilder(Save::class)
            ->setMethods(['getRequest'])
            ->setConstructorArgs([
                $this->context,
                $jsonFactory,
                $this->checkoutSession,
                $this->orderHelper,
                $this->configHelper,
                $this->bugsnagMock,
                $this->dataObjectFactory
            ])
            ->getMock();

        $save->method('getRequest')->willReturn($request);

        $save->execute();
    }

    protected function initRequiredMocks()
    {
        $this->orderHelper = $this->createMock(OrderHelper::class);
        $this->orderMock = $this->createMock(Order::class);
        $this->quoteMock = $this->createMock(Quote::class);
        $this->context = $this->createMock(Context::class);
        $this->checkoutSession = $this->createMock(Session::class);
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->resultJsonFactory = $this->createMock(ResultJsonFactory::class);
        $this->configHelper = $this->createMock(Config::class);
        $this->bugsnagMock = $this->createMock(Bugsnag::class);

        //Set up method returns
        $this->orderMock->method('getId')->willReturn(self::ORDER_ID);
        $this->orderMock->method('getIncrementId')->willReturn(self::INCREMENT_ID);
        $this->orderMock->method('getStatus')->willReturn(self::STATUS);

        $this->quoteMock->method('getId')->willReturn(self::QUOTE_ID);

        $this->configHelper->method('getSuccessPageRedirect')->willReturn(self::SUCCESS_URL);
    }
}
