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

namespace Bolt\Boltpay\Test\Unit\Controller\Cart;

use Bolt\Boltpay\Controller\Cart\Data;
use Bolt\Boltpay\Helper\Cart;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Class DataTest
 * @package Bolt\Boltpay\Test\Unit\Controller\Cart
 * @coversDefaultClass \Bolt\Boltpay\Controller\Cart\Data
 */
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
     * @var MockObject|JsonFactory mocked instance of JsonFactory
     */
    private $resultJsonFactory;

    protected function setUp()
    {
        $this->initResponseData();
        $this->initRequiredMocks();
    }

    private function initResponseData()
    {
        $requestShippingAddress = 'String';

        $this->response = (object) ( [
            'cart' =>
                (object) ( [
                    'order_reference' => self::ORDER_REFERENCE,
                    'display_id'      => '100050001 / 1234',
                    'shipments'       =>
                        [
                            0 =>
                                (object) ( [
                                    'shipping_address' => $requestShippingAddress,
                                    'shipping_method' => 'unknown',
                                    'service'         => 'Flat Rate - Fixed',
                                    'cost'            =>
                                        (object) ( [
                                            'amount'          => 500,
                                            'currency'        => 'USD',
                                            'currency_symbol' => '$',
                                        ] ),
                                    'tax_amount'      =>
                                        (object) ( [
                                            'amount'          => 0,
                                            'currency'        => 'USD',
                                            'currency_symbol' => '$',
                                        ] ),
                                    'reference'       => 'flatrate_flatrate'
                                ] ),
                        ],
                ] ),
            'token' => self::TOKEN
        ] );

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
        $this->resultJsonFactory = $this->createMock(JsonFactory::class);

        $this->request->method('getParam')->willReturn('placeholder');
        $this->context->method('getRequest')->willReturn($this->request);

        $this->cartHelper = $this->createMock(Cart::class);
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
            $this->cartHelper
        );
        
        $this->assertAttributeEquals($this->resultJsonFactory, 'resultJsonFactory', $instance);
        $this->assertAttributeEquals($this->cartHelper, 'cartHelper', $instance);
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
        $expected = [
            'status' => 'success',
            'cart' => $this->expectedCart,
            'hints' => self::HINT,
            'backUrl' => ''
        ];
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
