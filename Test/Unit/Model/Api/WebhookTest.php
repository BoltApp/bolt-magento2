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

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Model\Api\Webhook;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Model\Api\CreateOrder;
use Bolt\Boltpay\Model\Api\DiscountCodeValidation;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use PHPUnit\Framework\TestCase;

class WebhookTest extends TestCase
{

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
     * @var MockObject|DiscountCodeValidation
     */
    private $discountCodeValidation;

    /**
     * @var MockObject|Request
     */
    private $request;

    /**
     * @var MockObject|Response
     */
    private $response;

    /**
     * @var MockObject|Webhook
     */
    private $currentMock;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    /**
     * @test
     */
    public function invalidWebhookType_sendsErrorResponse()
    {
        $invalidType = "invalid_hook_type";
        $data = ['data1' => 'not important'];

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_SERVICE,
            __('Invalid webhook type %1', $invalidType),
            422
        );

        $this->currentMock->execute($invalidType, $data);
    }

    /**
     * @test
     */
    public function discountValidation_returnsTrue()
    {
        $type = "validate_discount";
        $data = ['data1' => 'not important'];
        
        $this->discountCodeValidation->expects(self::once())
            ->method('validate')
            ->willReturn(true);

        $this->assertTrue($this->currentMock->execute($type, $data));
    }

    private function initRequiredMocks()
    {
        $this->createOrder = $this->createMock(CreateOrder::class);
        $this->discountCodeValidation = $this->createMock(DiscountCodeValidation::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->errorResponse = $this->createMock(BoltErrorResponse::class);
        $this->response = $this->createMock(Response::class);
    }

    private function initCurrentMock($methods = null)
    {
        $mockBuilder = $this->getMockBuilder(Webhook::class)
            ->setConstructorArgs([
                $this->createOrder,
                $this->discountCodeValidation,
                $this->bugsnag,
                $this->logHelper,
                $this->errorResponse,
                $this->response
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