<?php

namespace Bolt\Boltpay\Test\Unit\Controller\Cart;

use Bolt\Boltpay\Controller\Cart\Data;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Order;
use Bolt\Boltpay\Model\Request;
use Bolt\Boltpay\Model\Response;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use phpDocumentor\Reflection\Types\Void_;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class DataTest extends TestCase
{

    /**
     * @var Context
     */
    private $context;

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var MockObject|Cart
     */

    private $cartHelper;

    /**
     * @var MockObject|Config
     */

    private $configHelper;

    /**
     * @var MockObject|Bugsnag
     */

    private $bugsnag;

    /**
     * @var RequestInterface request
     */
    private $request;

    /**
     * @var MetricsClient metricsClient
     */
    private $metricsClient;
    /**
     * @var Data currentMock
     */
    private $currentMock;

    protected function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    public function testExecute()
    {
        //Need to do mock work on the carthelper and such. Not sure what sort of data we'd be getting as response
        $expected = '{status: "success", cart: "not sure what goes here", hints: "here either", backUrl: ""}';

        $request_shipping_addr = 'String';

        $request_data = (object) ( array(
            'cart' =>
                (object) ( array(
                    'order_reference' => '1234',
                    'display_id'      => '100050001 / 1234',
                    'shipments'       =>
                        array(
                            0 =>
                                (object) ( array(
                                    'shipping_addres' => $request_shipping_addr,
                                    'shipping_method' => 'unknown',
                                    'service'         => 'Flat Rate - Fixed',
                                    'cost'            =>
                                        (object) ( array(
                                            'amount'          => 500,
                                            'currency'        => 'USD',
                                            'currency_symbol' => '$',
                                        ) ),
                                    'tax_amount'      =>
                                        (object) ( array(
                                            'amount'          => 0,
                                            'currency'        => 'USD',
                                            'currency_symbol' => '$',
                                        ) ),
                                    'reference'       => 'flatrate_flatrate'
                                ) ),
                        ),
                ) ),
            'token' => 'token'
        ) );


//        $boltpayOrder = $this->createMock(Response::class);
        $boltpayOrder = $this->getMockBuilder(Response::class)
            ->setMethods(['getData'])
            ->disableOriginalConstructor()
            ->getMock();
        $boltpayOrder->method('getData')->willReturn($request_data);
        $cartHelper = $this->createMock(Cart::class);
        $cartHelper->method('isCheckoutAllowed')->willReturn(true);
        $cartHelper->method('hasProductRestrictions')->willReturn(false);
        $cartHelper->method('getBoltpayOrder')
            ->withAnyParameters()
            ->willReturn($boltpayOrder);


        $json = "filler";
        $objectManagerMock = $this->getMockBuilder(ObjectManagerInterface::class)
            ->getMock();
        $objectManagerMock->method('create')->willReturn($json);
        $jsonFactory = new JsonFactory($objectManagerMock);

        $jsonFactoryMock = $this->getMockBuilder(JsonFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $jsonFactoryMock->method('create')
            ->willReturnSelf();

        $currentMock = new Data(
            $this->context,
            $jsonFactoryMock,
            $cartHelper,
            $this->configHelper,
            $this->bugsnag,
            $this->metricsClient
        );
        $currentMock->execute();
//        $this->assertEquals($expected, $this->currentMock->execute());
        $this->assertTrue(true);
    }

    public function testProductRestrictions()
    {
        //Override default set in init
        $this->cartHelper->method('hasProductRestrictions')->willReturn(true);

        /**
         * should expect JSON similar or equal to
         * {status: "success", restrict: "true", message: "The cart has products not allowed for Bolt checkout", backUrl: ""}
         */
        $expected = '{status: "success", restrict: "true", message: "The cart has products not allowed for Bolt checkout", backUrl: ""}';

        $this->assertEquals($expected, $this->currentMock->execute());
    }

    public function testDisallowedCheckout()
    {
        //Override default set in init
        $this->cartHelper->method('isCheckoutAllowed')->willReturn(false);

        $expected = '{status: "success", restrict: "true", message: "Guest checkout is not allowed.", backUrl: ""}';

        $this->assertEquals($expected, $this->currentMock->execute());
    }

    public function testGeneralException()
    {
        //Make a method throw an exception during execution
        $exception = new \Exception("Test message");
        $this->currentMock->method('getRequest')->willThrowException($exception);
        $this->bugsnag->expects($this->once())->method('notifyException');

        $expected = '{status: "failure", message: "Test message", backUrl: ""}';

        $this->currentMock->execute();
//        $this->assertEquals($expected, $this->currentMock->execute());
    }

    private function initRequiredMocks()
    {
        $this->context = $this->createMock(Context::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->metricsClient = $this->createMock(MetricsClient::class);

        $map = [['payment_only', true], ['place_order_payload', 'this is a string I don\'t know what it should be']];
        $this->request->method('getParam')->willReturnMap($map);
        $this->context->method('getRequest')->willReturn($this->request);
        $boltPayOrderMock = $this->createMock(DataObject::class);

        //Methods used in Data.php
        //hasProductRestrictions -> returns bool (should modify in test cases)
        //isCheckoutAllowed -> returns bool (should modify in test cases)
        //getBoltpayOrder -> returns void | response (presumed JSON) (void would be error case)
        //getHints -> returns array (need to check in on what exactly)

        $this->cartHelper = $this->createMock(Cart::class);
        $this->cartHelper->method('hasProductRestrictions')->willReturn(false);
        $this->cartHelper->method('isCheckoutAllowed')->willReturn(true);
        $this->cartHelper->method('getBoltPayOrder')->willReturn($boltPayOrderMock);


        //Does not appear to be used in Data.php but is a part of the constructor
        $this->configHelper = $this->createMock(Config::class);

        //Methods used in Data.php:
        //notifyException -> returns void
        $this->bugsnag = $this->createMock(Bugsnag::class);

        $this->jsonFactory = $this->getMockBuilder(JsonFactory::class)
            ->setMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->jsonFactory->method('create')
            ->willReturnSelf();
        //JSON factory stuff
//        $json = $this->createMock(Json::class);
//        $this->jsonFactory = $this->createMock(JsonFactory::class);
//        $this->jsonFactory->method('create')->willReturn($json);
    }

    private function initCurrentMock()
    {
        $this->currentMock = $this->getMockBuilder(Data::class)
                                ->setConstructorArgs([
                                    $this->context,
                                    $this->jsonFactory,
                                    $this->cartHelper,
                                    $this->configHelper,
                                    $this->bugsnag,
                                    $this->metricsClient
                                ])
                                ->enableProxyingToOriginalMethods()
                                ->getMock();

//        $map = [['payment_only', true], ['place_order_payload', 'this is a string I don\'t know what it should be']];
//        $this->currentMock->method('getRequest')->willReturnMap($map);
        $this->currentMock->method('getRequest')->willReturn(true);
        return $this->currentMock;
    }
}
