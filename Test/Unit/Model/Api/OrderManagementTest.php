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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Model\Api\CreateOrder;
use Bolt\Boltpay\Model\Api\OrderManagement;
use Magento\Framework\Phrase;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class OrderManagementTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\OrderManagement
 */
class OrderManagementTest extends TestCase
{
    const ORDER_ID = 123;
    const QUOTE_ID = 456;
    const STORE_ID = 1;
    const ID = "AAAABBBBCCCCD";
    const REFERENCE = "AAAA-BBBB-CCCC";
    const AMOUNT = 11800;
    const CURRENCY = "USD";
    const DISPLAY_ID = "000000123 / 456";
    const REQUEST_HEADER_TRACE_ID = 'aaaabbbbcccc';
    const TYPE = 'pending';
    const STATUS = 'pending';

    /**
     * @var MockObject|HookHelper
     */
    private $hookHelper;

    /**
     * @var MockObject|OrderHelper
     */
    private $orderHelperMock;

    /**
     * @var MockObject|LogHelper
     */
    private $logHelper;

    /**
     * @var MockObject|Request
     */
    private $request;

    /**
     * @var MockObject|Bugsnag
     */
    private $bugsnag;

    /**
     * @var MockObject|MetricsClient
     */
    private $metricsClient;

    /**
     * @var MockObject|Response
     */
    private $response;

    /**
     * @var MockObject|ConfigHelper
     */
    private $configHelper;

    /**
     * @var MockObject|OrderManagement
     */
    private $currentMock;

    /**
     * @var MockObject|Quote
     */
    private $quoteMock;
    /**
     * @var string
     */
    private $requestContent;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();

        $this->requestContent = json_encode(
            [
                "id" => self::ID, "reference" => self::REFERENCE, "order" => self::ORDER_ID, "type" => self::TYPE,
                "amount" => self::AMOUNT, "currency" => self::CURRENCY, "status" => self::STATUS, "display_id" => self::DISPLAY_ID
            ]
        );
    }

    private function initRequiredMocks()
    {
        $this->hookHelper = $this->createMock(HookHelper::class);
        $this->orderHelperMock = $this->createMock(OrderHelper::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->request = $this->createMock(Request::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->metricsClient = $this->createMock(MetricsClient::class);
        $this->response = $this->createMock(Response::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);

        $this->quoteMock = $this->createMock(Quote::class);

        $this->orderHelperMock->expects(self::once())->method('getStoreIdByQuoteId')
            ->with(self::ORDER_ID)->willReturn(self::STORE_ID);
    }

    private function initCurrentMock()
    {
        $this->currentMock = $this->getMockBuilder(OrderManagement::class)
            ->setConstructorArgs([
                $this->hookHelper,
                $this->orderHelperMock,
                $this->logHelper,
                $this->request,
                $this->bugsnag,
                $this->metricsClient,
                $this->response,
                $this->configHelper
            ])
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     * @test
     * @covers ::manage
     */
    public function manage_common()
    {
        $startTime = microtime(true) * 1000;

        $this->metricsClient->expects(self::once())->method('getCurrentTime')->willReturn($startTime);
        $this->request->expects(self::once())->method('getContent')->willReturn($this->requestContent);
        $this->logHelper->expects(self::exactly(2))->method('addInfoLog')
            ->withConsecutive([$this->requestContent], ['StoreId: ' . self::STORE_ID]);
        $this->hookHelper->expects(self::once())->method('preProcessWebhook')->with(self::STORE_ID);
        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with(self::anything(), 1, 'webhooks.latency', $startTime);
        $this->response->expects(self::once())->method('sendResponse');

        $this->currentMock->manage(
            self::ID,
            self::REFERENCE,
            self::ORDER_ID,
            null,
            self::AMOUNT,
            self::CURRENCY,
            null,
            self::DISPLAY_ID
        );
    }

    /**
     * @test
     * @depends manage_common
     * @covers ::manage
     */
    public function manage_rejectedIrreversible_success()
    {
        $type = "rejected_irreversible";

        $this->orderHelperMock->expects(self::once())->method('tryDeclinedPaymentCancelation')
            ->willReturn(true);
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(200);
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'success',
            'message' => 'Order was canceled due to declined payment: ' . self::DISPLAY_ID,
        ]));

        $this->currentMock->manage(
            self::ID,
            self::REFERENCE,
            self::ORDER_ID,
            $type,
            self::AMOUNT,
            self::CURRENCY,
            $type,
            self::DISPLAY_ID
        );
    }

    /**
     * @test
     * @depends manage_common
     * @covers ::manage
     */
    public function manage_rejectedIrreversible_fail()
    {
        $type = "rejected_irreversible";

        $this->orderHelperMock->expects(self::once())->method('tryDeclinedPaymentCancelation')
            ->willReturn(false);
        $this->request->expects(self::once())->method('getHeader')->with(ConfigHelper::BOLT_TRACE_ID_HEADER)
            ->willReturn(self::REQUEST_HEADER_TRACE_ID);
        $this->orderHelperMock->expects(self::once())->method('saveUpdateOrder')
            ->with(self::REFERENCE, self::STORE_ID, self::REQUEST_HEADER_TRACE_ID, $type);
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(200);
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'success',
            'message' => 'Order creation / update was successful',
        ]));

        $this->currentMock->manage(
            self::ID,
            self::REFERENCE,
            self::ORDER_ID,
            $type,
            self::AMOUNT,
            self::CURRENCY,
            $type,
            self::DISPLAY_ID
        );
    }

    /**
     * @test
     * @depends manage_common
     * @covers ::manage
     */
    public function manage_rejectedIrreversible_exception()
    {
        $type = "rejected_irreversible";
        $exception = new BoltException(
            new Phrase(
                'Order Cancelation Error. Order does not exist. Order #: %1 Immutable Quote ID: %2',
                [
                    self::ORDER_ID,
                    self::QUOTE_ID
                ]
            ),
            null,
            CreateOrder::E_BOLT_GENERAL_ERROR
        );

        $this->orderHelperMock->expects(self::once())->method('tryDeclinedPaymentCancelation')
            ->with(self::DISPLAY_ID)->willThrowException(
                $exception
            );
        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with('webhooks.failure', 1, "webhooks.latency", self::anything());
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(422);
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'error',
            'code' => '6009',
            'message' => 'Unprocessable Entity: ' . $exception->getMessage(),
        ]));

        $this->currentMock->manage(
            self::ID,
            self::REFERENCE,
            self::ORDER_ID,
            $type,
            self::AMOUNT,
            self::CURRENCY,
            $type,
            self::DISPLAY_ID
        );
    }

    /**
     * @test
     * @depends manage_common
     * @covers ::manage
     */
    public function manage_failedPayment_success()
    {
        $type = "failed_payment";

        $this->orderHelperMock->expects(self::once())->method('deleteOrderByIncrementId')
            ->with(self::DISPLAY_ID);
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(200);
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'success',
            'message' => 'Order was deleted: ' . self::DISPLAY_ID,
        ]));
        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with('webhooks.success', 1, "webhooks.latency", self::anything());

        $this->currentMock->manage(
            self::ID,
            self::REFERENCE,
            self::ORDER_ID,
            $type,
            self::AMOUNT,
            self::CURRENCY,
            $type,
            self::DISPLAY_ID
        );
    }

    /**
     * @test
     * @depends manage_common
     * @covers ::manage
     */
    public function manage_failedPayment_exception()
    {
        $type = "failed_payment";
        $exception = new BoltException(
            new Phrase(
                'Order Delete Error. Order is in invalid state. Order #: %1 State: %2 Immutable Quote ID: %3',
                [
                    self::ORDER_ID,
                    Order::STATE_PROCESSING,
                    self::QUOTE_ID
                ]
            ),
            null,
            CreateOrder::E_BOLT_GENERAL_ERROR
        );

        $this->orderHelperMock->expects(self::once())->method('deleteOrderByIncrementId')
            ->with(self::DISPLAY_ID)->willThrowException($exception);
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(422);
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'error',
            'code' => '6009',
            'message' => 'Unprocessable Entity: ' . $exception->getMessage(),
        ]));
        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with('webhooks.failure', 1, "webhooks.latency", self::anything());

        $this->currentMock->manage(
            self::ID,
            self::REFERENCE,
            self::ORDER_ID,
            $type,
            self::AMOUNT,
            self::CURRENCY,
            $type,
            self::DISPLAY_ID
        );
    }
}
