<?php

namespace Bolt\Boltpay\Test\Unit\Controller\Order;

use Bolt\Boltpay\Controller\Order\Save;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config;
use Bolt\BoltPay\Helper\Order as OrderHelper;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Test\Unit\ObjectManagerFactoryTest;
use Magento\Framework\Controller\Result\JsonFactory as ResultJsonFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

class SaveTest extends TestCase
{
    const ORDER_ID = 1234;
    const QUOTE_ID = 5678;
    const INCREMENT_ID = 1235;
    const STATUS = "Ready";
    const REFERENCE = "referenceValue";
    const URL = "http://url.return.value/";

    /**
     * @var Save currentMock
     */
    private $currentMock;

    /**
     * @var ObjectManager objectManager
     */
    private $objectManager;

    /**
     * @var Order orderMock
     */
    private $orderMock;

    /**
     * @var Quote quoteMock
     */
    private $quoteMock;

    /**
     * @var Bugsnag bugsnagMock
     */
    private $bugsnagMock;

    /**
     * @var OrderHelper orderHelper
     */
    private $orderHelper;

    /**
     * @var Session checkoutSession
     */
    private $checkoutSession;

    /**
     * @var Config configHelper
     */
    private $configHelper;

    /**
     * @var DataObjectFactory dataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var Context context
     */
    private $context;

    /**
     * @var ResultJsonFactory jsonFactory
     */
    private $resultJsonFactory;

    protected function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    public function testExecute()
    {
        //Verify certain methods are run
//        $this->currentMock->expects($this->once())->method('replaceQuote');
//        $this->currentMock->expects($this->once())->method('clearQuoteSession');
//        $this->currentMock->expects($this->once())->method('clearOrderSession');

        $expected = '{"status": "success", "success_url": "' . self::URL . '"}';

        $this->assertEquals($expected, $this->currentMock->execute());

        //This method is just missing the resultJsonFactory to be able to work;

    }

    public function testExecuteException()
    {
        //Bugsnag reporting
        $this->currentMock->expects($this->once())->method('notifyException');


    }


    protected function initRequiredMocks()
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
        $this->resultJsonFactory = $this->getMockBuilder(ResultJsonFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        //Set up method returns
        $this->orderMock->method('getId')->willReturn(self::ORDER_ID);
        $this->orderMock->method('getIncrementId')->willReturn(self::INCREMENT_ID);
        $this->orderMock->method('getStatus')->willReturn(self::STATUS);

        $this->quoteMock->method('getId')->willReturn(self::QUOTE_ID);

        $this->orderHelper->method('saveUpdateOrder')->willReturn([self::QUOTE_ID, self::ORDER_ID]);

        $this->configHelper->method('getSuccessPageRedirect')->willReturn(self::URL);

    }

    protected function initCurrentMock()
    {
        $this->currentMock = $this->getMockBuilder(Save::class)
            ->setConstructorArgs([
                $this->context,
                $this->resultJsonFactory,
                $this->checkoutSession,
                $this->orderHelper,
                $this->configHelper,
                $this->bugsnagMock,
                $this->dataObjectFactory
            ])
            ->enableProxyingToOriginalMethods()
            ->getMock();
        $this->currentMock->method('getRequest')->willReturn(self::REFERENCE);
        return $this->currentMock;
    }

}
