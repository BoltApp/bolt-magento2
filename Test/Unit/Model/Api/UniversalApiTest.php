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

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Model\Api\UniversalApi;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Model\Api\CreateOrder;
use Bolt\Boltpay\Model\Api\OrderManagement;
use Bolt\Boltpay\Model\Api\Shipping;
use Bolt\Boltpay\Model\Api\ShippingMethods;
use Bolt\Boltpay\Model\Api\Tax;
use Bolt\Boltpay\Model\Api\UpdateCart;
use Bolt\Boltpay\Model\Api\Debug;
use Bolt\Boltpay\Model\Api\Data\UniversalApiResult;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class UniversalApiTest extends BoltTestCase
{
    const DATA = ['data1' => 'not important'];

    /** 
     * @var MockObject|Bugsnag 
     */
    private $bugsnag;

    /**
     * @var MockObject|LogHelper
     */
    private $logHelper;

    /**
     * @var MockObject|CreateOrder
     */
    private $createOrder;

    /**
     * @var MockObject|OrderManagement
     */
    private $orderManagement;

    /**
     * @var MockObject|Shipping
     */
    private $shipping;

    /**
     * @var MockObject|ShippingMethods
     */
    private $shippingMethods;

    /**
     * @var MockObject|Tax
     */
    private $tax;

    /**
     * @var MockObject|UpdateCart
     */
    private $updateCart;

    /**
     * @var MockObject|Debug
     */
    private $debug;

    /**
     * @var MockObject|UniversalApiResult
     */
    private $universalApiResult;

    /**
     * @var MockObject|Request
     */
    private $request;

    /**
     * @var MockObject|Response
     */
    private $response;

    /**
     * @var MockObject|UniversalApi
     */
    private $currentMock;

    /**
     * @inheritdoc
     */
    protected function setUpInternal()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    /**
     * @test
     */
    public function invalidRequestEvent_sendsErrorResponse()
    {
        $invalidType = "invalid_hook_type";

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_SERVICE,
            __('Invalid webhook type %1', $invalidType),
            422
        );

        $this->currentMock->execute($invalidType, self::DATA);
    }

    /**
     * @test
     */
    public function orderCreation_returnsTrue()
    {
        $event = "order.create";
        $data = ['order' => 'order', 'currency' => 'currency'];

        $this->createOrder->expects(self::once())
            ->method('execute')
            ->with(
                $event, 
                $data['order'], 
                $data['currency']
            )
            ->willReturn(true);
        
        $this->assertTrue($this->currentMock->execute($event, $data));
    }

    /**
     * @test
     */
    public function updateCart_returnsTrue()
    {
        $event = "cart.update";
        $data = [
            'cart' => 'cart',
            'add_items' => 'add_items',
            'remove_items' => 'remove_items',
            'discount_codes_to_add' => 'discount_codes_to_add',
            'discount_codes_to_remove' => 'discount_codes_to_remove',
        ];

        $this->updateCart->expects(self::once())
            ->method('execute')
            ->with(
                $data['cart'],
                $data['add_items'],
                $data['remove_items'],
                $data['discount_codes_to_add'],
                $data['discount_codes_to_remove']
            );
        
        $this->assertTrue($this->currentMock->execute($event, $data));
    }

    /**
     * @test
     */
    public function getShippingMethods_returnsTrue()
    {
        $event = "order.shipping_and_tax";
        $data = [
            'cart' => 'cart',
            'shipping_address' => 'shipping_address'
        ];

        $this->shippingMethods->expects(self::once())
            ->method('getShippingMethods')
            ->with(
                $data['cart'],
                $data['shipping_address']
            );
        
        $this->assertTrue($this->currentMock->execute($event, $data));
    }

    /**
     * @test
     */
    public function shippingOptions_returnsTrue()
    {
        $event = "order.shipping";
        $data = [
            'cart' => 'cart',
            'shipping_address' => 'shipping_address',
            'shipping_option' => 'shipping_option'
        ];

        $this->shipping->expects(self::once())
            ->method('execute')
            ->with(
                $data['cart'],
                $data['shipping_address'],
                $data['shipping_option']
            );
        
        $this->assertTrue($this->currentMock->execute($event, $data));
    }

    private function initRequiredMocks()
    {
        $this->createOrder = $this->createMock(CreateOrder::class);
        $this->orderManagement = $this->createMock(OrderManagement::class);
        $this->shipping = $this->createMock(Shipping::class);
        $this->shippingMethods = $this->createMock(ShippingMethods::class);
        $this->tax = $this->createMock(Tax::class);
        $this->updateCart = $this->createMock(UpdateCart::class);
        $this->universalApiResult = $this->createMock(UniversalApiResult::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->errorResponse = $this->createMock(BoltErrorResponse::class);
        $this->response = $this->createMock(Response::class);
        $this->debug = $this->createMock(Debug::class);
    }

    private function initCurrentMock($methods = null)
    {
        $mockBuilder = $this->getMockBuilder(UniversalApi::class)
            ->setConstructorArgs([
                $this->createOrder,
                $this->orderManagement,
                $this->shipping,
                $this->shippingMethods,
                $this->tax,
                $this->updateCart,
                $this->universalApiResult,
                $this->bugsnag,
                $this->logHelper,
                $this->errorResponse,
                $this->response,
                $this->debug,
            ]);
        if ($methods) {
            $mockBuilder->setMethods($methods);
        } else {
            $mockBuilder->enableProxyingToOriginalMethods();
        }

        $this->currentMock = $mockBuilder->getMock();
    }

    private function expectErrorResponse($errCode, $message, $httpStatusCode)
    {
        //TODO: Additional response stays here for now as it will be fleshed out in the handler
        $additionalErrorResponseData = [];
        $encodeErrorResult = '';

        $this->errorResponse->expects(self::once())
            ->method('prepareErrorMessage')
            ->with($errCode, $message, $additionalErrorResponseData)
            ->willReturn($encodeErrorResult);

        $this->bugsnag->expects(self::once())
            ->method('notifyException')
            ->with(new \Exception($message));

        $this->response->expects(self::once())
            ->method('setHttpResponseCode')
            ->with($httpStatusCode);
        $this->response->expects(self::once())
            ->method('setBody')
            ->with($encodeErrorResult);
        $this->response->expects(self::once())
            ->method('sendResponse');
    }
}