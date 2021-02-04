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

use Bolt\Boltpay\Model\Api\UniversalWebhook;

use Bolt\Boltpay\Model\Api\OrderManagement;
use Bolt\Boltpay\Model\Api\Data\UniversalWebhookResult as Result;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Framework\Webapi\Rest\Response;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class UniversalWebhookTest extends BoltTestCase
{
    /**
     * @var MockObject|OrderManagement
     */
    private $orderManagement;

    /**
     * @var Result
     */
    private $result;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var BoltErrorResponse
     */
    private $errorResponse;

    /**
     * @var Response
     */
    private $response;

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
    public function exceptionHandled_sendsErrorResponse()
    {
        $exception = new BoltException(
            __("Test error message"),
            null,
            BoltErrorResponse::ERR_SERVICE
        );

        $this->orderManagement->expects(self::once())
            ->method('manage')
            ->willThrowException($exception);

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_SERVICE,
            __("Test error message"),
            422
        );

        $this->currentMock->execute();
    }

    /**
     * @test
     */
    public function updateOrder_returnsTrue()
    {
        $type = 'testType';
        $object = 'transaction';
        $data = [
            'id' => '1234',
            'reference' => 'XXXX-XXXX-XXXX',
            'order' => [
                'cart' => [
                    'order_reference' => '5678',
                    'display_id' => '000001234'
                ]
            ],
            'amount' => [
                'amount' => '2233',
                'currency' => 'USD'
            ],
            'status' => 'completed',
            'source_transaction' => [
                'id' => 'string',
                'reference' => 'otherstring'
            ]
        ];

        $expectedResult = json_encode(['status' => 'success']);
        $this->expectSuccessResponse($expectedResult, 200);

        $this->assertTrue($this->currentMock->execute($type, $object, $data));
    }

    private function expectSuccessResponse($result, $httpResponseCode)
    {
        $this->response->expects(self::once())
            ->method('setHttpResponseCode')
            ->with($httpResponseCode);

        $this->response->expects(self::once())
            ->method('setBody')
            ->with($result);

        $this->response->expects(self::once())
            ->method('sendResponse');
    }

    private function expectErrorResponse($errCode, $message, $httpStatusCode)
    {
        $encodeErrorResult = '';

        $this->errorResponse->expects(self::once())
            ->method('prepareErrorMessage')
            ->with($errCode, $message)
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

    private function initRequiredMocks()
    {
        $this->orderManagement = $this->createMock(OrderManagement::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->errorResponse = $this->createMock(BoltErrorResponse::class);
        $this->response = $this->createMock(Response::class);

        $this->result = $this->getMockBuilder(Result::class)
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    private function initCurrentMock($methods = null)
    {
        $mockBuilder = $this->getMockBuilder(UniversalWebhook::class)
            ->setConstructorArgs([
                $this->orderManagement,
                $this->result,
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
}