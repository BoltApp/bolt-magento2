<?php

namespace Bolt\Boltpay\Test\Unit\Controller\Adminhtml\Order;

use Bolt\Boltpay\Controller\Adminhtml\Order\Save as Save;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Order;
use Magento\Backend\App\Action\Context;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class SaveTest extends TestCase
{
    const ORDER_ID = '1234';
    const ORDER_STATUS = 'currentStatus';
    const INCREMENT_ID = '1235';
    const REFERENCE = 'reference';
    const STORE_ID = '4321';
    const QUOTE_ID = '1357';
    const URL = 'https://www.mockUrl.com';
    const EXCEPTIONMESSAGE = 'ExceptionMessage';

    /**
     * @var Context|MockObject
     */
    private $context;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Order | MockObject
     */
    private $orderHelper;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    protected function setUp()
    {
        $this->initRequiredMocks();
    }

    public function testExecute_HappyPath()
    {
        $map = [
            ['reference', self::REFERENCE],
            ['store_id', self::STORE_ID]
        ];

        $expected= ['success_url' => self::URL];

        $order = $this->getMockBuilder(OrderInterface::class)
            ->setMethods([
                'getId',
                'getIncrementId',
                'getStatus'
            ])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $order->method('getId')->willReturn(self::ORDER_ID);
        $order->method('getIncrementId')->willReturn(self::INCREMENT_ID);
        $order->method('getStatus')->willreturn(self::ORDER_STATUS);

        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(self::QUOTE_ID);

        $url = $this->createMock(UrlInterface::class);
        $url->expects($this->once())
            ->method('getUrl')
            ->with($this->equalTo('sales/order/view/'), $this->equalTo(['order_id' => self::ORDER_ID]))
            ->willReturn(self::URL);

        $this->context->method('getUrl')->willReturn($url);

        $this->orderHelper->method('saveUpdateOrder')->willReturn([$quote, $order]);

        $json = $this->createMock(Json::class);
        $json->method('setData');
        $this->resultJsonFactory->method('create')->willReturn($json);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')
            ->will($this->returnValueMap($map));

        $checkout = $this->getMockBuilder(Session::class)
            ->setMethods([
                'setLastQuoteId',
                'setLastSuccessQuoteId',
                'clearHelperData',
                'setLastOrderId',
                'setLastRealOrderId',
                'setLastOrderStatus'])
            ->disableOriginalConstructor()
            ->getMock();

        //clearQuoteSession
        $checkout->method('setLastQuoteId')->with(self::QUOTE_ID)->willReturnSelf();
        $checkout->method('setLastSuccessQuoteId')->with(self::QUOTE_ID)->willReturnSelf();
        $checkout->method('clearHelperData')->willReturnSelf();

        //clearOrderSession
        $checkout->method('setLastOrderId')->with(self::ORDER_ID)->willReturnSelf();
        $checkout->method('setLastRealOrderId')->with(self::INCREMENT_ID)->willReturnSelf();
        $checkout->method('setLastOrderStatus')->with(self::ORDER_STATUS)->willReturnSelf();

        $save = $this->getMockBuilder(Save::class)
            ->setMethods([
                'getRequest',
                'clearQuoteSession',
                'clearOrderSession'
            ])
            ->setConstructorArgs([
                $this->context,
                $this->resultJsonFactory,
                $checkout,
                $this->orderHelper,
                $this->configHelper,
                $this->bugsnag,
                $this->dataObjectFactory
            ])
            ->getMock();

        $save->expects($this->exactly(2))->method('getRequest')->willReturn($request);
        $save->expects($this->once())->method('clearQuoteSession')->with($quote);
        $save->expects($this->once())->method('clearOrderSession')->with($order);

        $save->execute();
    }

    public function testExecute_Exception()
    {
        $expectedData = [
            'status' => 'error',
            'code' => '1000',
            'message' => self::EXCEPTIONMESSAGE
        ];

        $exception = new \Exception(self::EXCEPTIONMESSAGE);

        $json = $this->createMock(Json::class);
        $json->expects($this->once())->method('setHttpResponseCode')->with(422);
        $json->expects($this->once())->method('setData')->with($expectedData);
        $this->resultJsonFactory->method('create')->willReturn($json);

        $this->bugsnag->expects($this->once())->method('notifyException')->with($exception);

        $save = $this->getMockBuilder(Save::class)
            ->setMethods(['getRequest'])
            ->setConstructorArgs([
                $this->context,
                $this->resultJsonFactory,
                $this->checkoutSession,
                $this->orderHelper,
                $this->configHelper,
                $this->bugsnag,
                $this->dataObjectFactory
            ])
            ->getMock();

        $save->method('getRequest')->willThrowException($exception);
        $save->execute();
    }

    private function initRequiredMocks()
    {
        $this->context = $this->createMock(Context::class);
        $this->checkoutSession = $this->createMock(Session::class);
        $this->orderHelper = $this->createMock(Order::class);
        $this->configHelper = $this->createMock(Config::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->resultJsonFactory = $this->createMock(JsonFactory::class);
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
    }
}
