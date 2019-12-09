<?php

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

class SaveTest extends TestCase
{
    const ORDER_ID = 1234;
    const QUOTE_ID = 5678;
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

    protected function setUp()
    {
        $this->initRequiredMocks();
    }

    public function testExecute_HappyPath()
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

        $this->configHelper->method('getSuccessPageRedirect');

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
                'setLastOrderStatus'
            ])
            ->getMock();

        //clearQuoteSession
        $checkoutSession->method('setLastQuoteId')->with(self::QUOTE_ID)->willReturnSelf();
        $checkoutSession->method('setLastSuccessQuoteId')->with(self::QUOTE_ID)->willReturnSelf();
        $checkoutSession->method('clearHelperData')->willReturnSelf();

        //clearOrderSession
        $checkoutSession->method('setLastOrderId')->with(self::ORDER_ID)->willReturnSelf();
        $checkoutSession->method('setLastRealOrderId')->with(self::INCREMENT_ID)->willReturnSelf();
        $checkoutSession->method('setLastOrderStatus')->with(self::STATUS)->willReturnSelf();

        //replaceQuote
        $checkoutSession->method('replaceQuote')->with($this->quoteMock);

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

        $save->expects($this->once())->method('replaceQuote')->with($this->quoteMock);
        $save->expects($this->once())->method('clearQuoteSession')->with($this->quoteMock);
        $save->expects($this->once())->method('clearOrderSession')->with($this->orderMock);

        $save->method('getRequest')->willReturn($request);

        $save->execute();
    }

    public function testExecute_Exception()
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
