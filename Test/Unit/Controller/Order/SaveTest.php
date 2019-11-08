<?php

namespace Bolt\Boltpay\Test\Unit\Controller\Order;

use Bolt\Boltpay\Controller\Order\Save;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config;
use Bolt\BoltPay\Helper\Order as OrderHelper;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

class SaveTest extends TestCase
{
    const ORDER_ID = 1234;
    const QUOTE_ID = 5678;
    const INCREMENT_ID = 1235;
    const STATUS = 'Ready';
    const REFERENCE = 'referenceValue';
    const URL = 'http://url.return.value/';
    const ERROR_MESSAGE = 'Error message';

    /**
     * @var Save currentMock
     */
    private $currentMock;

    /**
     * @var ObjectManager objectManager
     */
    private $objectManager;

    /**
     * @var MockObject|Order orderMock
     */
    private $orderMock;

    /**
     * @var MockObject|Quote quoteMock
     */
    private $quoteMock;

    /**
     * @var Bugsnag bugsnagMock
     */
    private $bugsnagMock;

    /**
     * @var MockObject|OrderHelper orderHelper
     */
    private $orderHelper;

    /**
     * @var MockObject|Session checkoutSession
     */
    private $checkoutSession;

    /**
     * @var MockObject|Config configHelper
     */
    private $configHelper;

    /**
     * @var MockObject|DataObjectFactory dataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var MockObject|Context context
     */
    private $context;

    protected function setUp()
    {
        $this->initRequiredMocks();
    }

    private function initRequiredMocks()
    {
        $this->objectManager =

        $this->orderMock = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $this->quoteMock = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->getMock();
        $this->bugsnagMock = $this->createMock(Bugsnag::class);
        $this->orderHelper = $this->createMock(\Bolt\Boltpay\Helper\Order::class);
        $this->configHelper = $this->createMock(Config::class);
        $this->context = $this->createMock(Context::class);
        $this->checkoutSession = $this->createMock(Session::class);
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);

        //Set up method returns
        $this->orderMock->method('getId')->willReturn(self::ORDER_ID);
        $this->orderMock->method('getIncrementId')->willReturn(self::INCREMENT_ID);
        $this->orderMock->method('getStatus')->willReturn(self::STATUS);

        $this->quoteMock->method('getId')->willReturn(self::QUOTE_ID);

        $this->orderHelper->method('saveUpdateOrder')->willReturn([$this->quoteMock, $this->orderMock]);

        $this->configHelper->method('getSuccessPageRedirect')->willReturn(self::URL);

//        $this->checkoutSession->method('setLastQuoteId')->willReturnSelf();

    }

    private function buildJsonMock($expected)
    {
        $json = $this->getmockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->getMock();
        $json->expects($this->at(0))
            ->method('setData')
            ->with($expected);
        $jsonFactoryMock = $this->getMockBuilder(JsonFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $jsonFactoryMock->method('create')
            ->willReturn($json);
        return $jsonFactoryMock;
    }

    public function testExecute_happyPath()
    {
        //Verify certain methods are run
//        $this->currentMock->expects($this->once())->method('replaceQuote');
//        $this->currentMock->expects($this->once())->method('clearQuoteSession');
//        $this->currentMock->expects($this->once())->method('clearOrderSession');

        $expected = array(
            'status' => 'success',
            'success_url' => self::URL
        );

        $jsonFactoryMock = $this->buildJsonMock($expected);

        $save = $this->getMockBuilder(Save::class)
            ->setMethods([
                'replaceQuote',
                'clearQuoteSession',
                'clearOrderSession',
                'getRequest'
            ])
            ->setConstructorArgs([
                $this->context,
                $jsonFactoryMock,
                $this->checkoutSession,
                $this->orderHelper,
                $this->configHelper,
                $this->bugsnagMock,
                $this->dataObjectFactory
            ])
//            ->enableProxyingToOriginalMethods()
            ->getMock();

        $request = $this->createMock(RequestInterface::class);

        $save->method('getRequest')->willReturn($request);

        $save->expects($this->once())->method('replaceQuote');
        $save->expects($this->once())->method('clearQuoteSession');
        $save->expects($this->once())->method('clearOrderSession');

        $save->execute();
    }

    public function testExecuteException()
    {
        $expected = array(
            'status' => 'error',
            'code' => 6009,
            'message' => self::ERROR_MESSAGE,
            'reference' => self::REFERENCE
        );

        $jsonFactoryMock = $this->buildJsonMock($expected);

        $bugsnag = $this->createMock(Bugsnag::class);
        $bugsnag->expects($this->any())->method('notifyException');

        $request = $this->createMock(RequestInterface::class);

        $save = $this->getMockBuilder(Save::class)
            ->setMethods(['getRequest'])
            ->enableProxyingToOriginalMethods()
            ->setConstructorArgs([
                $this->context,
                $jsonFactoryMock,
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




}
