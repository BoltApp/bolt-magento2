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
     * @var RequestInterface request
     */
    private $request;

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

        $this->request->method('getParam')->willReturn('placeholder');
        $this->context->method('getRequest')->willReturn($this->request);

        $this->cartHelper = $this->createMock(Cart::class);
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

    /**
     * @test
     */
    public function execute_happyPath()
    {
        $expected = array(
            'status' => 'success',
            'cart' => $this->expectedCart,
            'hints' => self::HINT,
            'backUrl' => ''
        );
        $this->cartHelper->method('calculateCartAndHints')->willReturn($expected);
        $jsonFactoryMock = $this->buildJsonMock($expected);

        $data = new Data(
            $this->context,
            $jsonFactoryMock,
            $this->cartHelper
        );
        $data->execute();
    }
}
