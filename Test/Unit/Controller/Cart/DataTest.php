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
    const HINT = 'hint!';
    const ORDER_REFERENCE = 1234;
    const TOKEN = 'token';
    const EXCEPTION_MESSAGE = 'Test exception message';

    /**
     * @var Context
     */
    private $context;

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
     * @var Object response
     */
    private $response;

    /**
     * @var array responseData
     */
    private $responseData;

    /**
     * @var array $expectedCart
     */
    private $expectedCart;

    /**
     * @var Object requestShippingAddress
     */
    private $requestShippingAddress;

    /**
     * @var MockObject | Response boltpayOrder
     */
    private $boltpayOrder;

    protected function setUp()
    {
        $this->initResponseData();
        $this->initRequiredMocks();
    }

    private function initResponseData()
    {
        $this->requestShippingAddress = 'String';

        $this->response = (object) ( array(
            'cart' =>
                (object) ( array(
                    'order_reference' => self::ORDER_REFERENCE,
                    'display_id'      => '100050001 / 1234',
                    'shipments'       =>
                        array(
                            0 =>
                                (object) ( array(
                                    'shipping_address' => $this->requestShippingAddress,
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
            'token' => self::TOKEN
        ) );

        //weird bit of stuff here, copied from the code under test
        $this->responseData = json_decode(json_encode($this->response), true);

        $this->expectedCart = array_merge($this->responseData['cart'], [
            'orderToken' => self::TOKEN,
            'cartReference' => self::ORDER_REFERENCE
        ]);
    }

    private function initRequiredMocks()
    {
        $this->context = $this->createMock(Context::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->metricsClient = $this->createMock(MetricsClient::class);

        $this->request->method('getParam')->willReturn('placeholder');
        $this->context->method('getRequest')->willReturn($this->request);

        $this->boltpayOrder = $this->getMockBuilder(Response::class)
            ->setMethods(['getResponse'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->boltpayOrder->method('getResponse')->willReturn($this->response);

        $this->cartHelper = $this->createMock(Cart::class);
        $this->cartHelper->method('isCheckoutAllowed')->willReturn(true);
        $this->cartHelper->method('hasProductRestrictions')->willReturn(false);
        $this->cartHelper->method('getBoltpayOrder')
            ->withAnyParameters()
            ->willReturn($this->boltpayOrder);
        $this->cartHelper->method('getHints')->willReturn(self::HINT);

        //Does not appear to be used in Data.php but is a part of the constructor
        $this->configHelper = $this->createMock(Config::class);

        $this->bugsnag = $this->createMock(Bugsnag::class);
    }

    private function buildJsonMock($expected)
    {
        $json = $this->getMockBuilder(Json::class)
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
        $expected = array(
            'status' => 'success',
            'cart' => $this->expectedCart,
            'hints' => self::HINT,
            'backUrl' => ''
        );

        $this->boltpayOrder = $this->getMockBuilder(Response::class)
            ->setMethods(['getResponse'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->boltpayOrder->method('getResponse')->willReturn($this->response);
        $cartHelper = $this->createMock(Cart::class);
        $cartHelper->method('isCheckoutAllowed')->willReturn(true);
        $cartHelper->method('hasProductRestrictions')->willReturn(false);
        $cartHelper->method('getBoltpayOrder')
            ->withAnyParameters()
            ->willReturn($this->boltpayOrder);

        $jsonFactoryMock = $this->buildJsonMock($expected);

        $data = new Data(
            $this->context,
            $jsonFactoryMock,
            $this->cartHelper,
            $this->configHelper,
            $this->bugsnag,
            $this->metricsClient
        );
        $data->execute();
    }

    public function testExecute_HasProductRestrictions()
    {
        //replace default set in init
        $cartHelper = $this->createMock(Cart::class);
        $cartHelper->method('hasProductRestrictions')->willReturn(true);

        $expected = array(
            'status' => 'success',
            'restrict' => true,
            'message' => 'The cart has products not allowed for Bolt checkout',
            'backUrl' => ''
        );

        $jsonFactoryMock = $this->buildJsonMock($expected);

        $data = new Data(
            $this->context,
            $jsonFactoryMock,
            $cartHelper,
            $this->configHelper,
            $this->bugsnag,
            $this->metricsClient
        );
        $data->execute();
    }

    public function testExecute_DisallowedCheckout()
    {
        //replace default set in init
        $cartHelper = $this->createMock(Cart::class);
        $cartHelper->method('isCheckoutAllowed')->willReturn(false);

        $expected = array(
            'status' => 'success',
            'restrict' => true,
            'message' => 'Guest checkout is not allowed.',
            'backUrl' => ''
        );

        $jsonFactoryMock = $this->buildJsonMock($expected);

        $data = new Data(
            $this->context,
            $jsonFactoryMock,
            $cartHelper,
            $this->configHelper,
            $this->bugsnag,
            $this->metricsClient
        );
        $data->execute();
    }

    public function testExecute_GeneralException()
    {
        //Make a method throw an exception during execution
        $exception = new \Exception(self::EXCEPTION_MESSAGE);
        $cartHelper = $this->createMock(Cart::class);
        $cartHelper->method('hasProductRestrictions')->willReturn(false);
        $cartHelper->method('isCheckoutAllowed')->willReturn(true);
        $cartHelper->method('getBoltpayOrder')->willThrowException($exception);
        $this->bugsnag->expects($this->once())->method('notifyException');

        $expected = array(
            'status' => 'failure',
            'message' => self::EXCEPTION_MESSAGE,
            'backUrl' => '',
        );

        $jsonFactoryMock = $this->buildJsonMock($expected);

        $data = new Data(
            $this->context,
            $jsonFactoryMock,
            $cartHelper,
            $this->configHelper,
            $this->bugsnag,
            $this->metricsClient
        );

        $data->execute();
    }

    public function testExecute_NullResponse()
    {
        $expected = array(
            'status' => 'success',
            'cart' => array(
                'orderToken' => '',
                'cartReference' => ''
            ),
            'hints' => null,
            'backUrl' => ''
        );

        $cartHelper = $this->createMock(Cart::class);
        $cartHelper->method('hasProductRestrictions')->willReturn(false);
        $cartHelper->method('isCheckoutAllowed')->willReturn(true);
        $cartHelper->method('getBoltpayOrder')->willReturn(null);

        $jsonFactoryMock = $this->buildJsonMock($expected);

        $data = new Data(
            $this->context,
            $jsonFactoryMock,
            $cartHelper,
            $this->configHelper,
            $this->bugsnag,
            $this->metricsClient
        );

        $data->execute();
    }
}
