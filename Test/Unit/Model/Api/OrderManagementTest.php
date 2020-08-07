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

use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Model\Api\CreateOrder;
use Bolt\Boltpay\Model\Api\OrderManagement;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use ReflectionException;

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
    const HOOK_PAYLOAD = ['checkboxes' => ['text'=>'Subscribe for our newsletter','category'=>'NEWSLETTER','value'=>true] ];

    /** @var MockObject|HookHelper mocked instance of the Bolt hook helper */
    private $hookHelper;

    /** @var MockObject|OrderHelper mocked instance of the Bolt order helper */
    private $orderHelperMock;

    /** @var MockObject|LogHelper mocked instance of the Bolt log helper */
    private $logHelper;

    /** @var MockObject|Request mocked instance of the Webapi Rest Request */
    private $request;

    /** @var MockObject|Bugsnag  mocked instance of the Bugsnag class */
    private $bugsnag;

    /** @var MockObject|MetricsClient mocked instance of the Bolt metrics client helper */
    private $metricsClient;

    /** @var MockObject|Response mocked instance of the Webapi Response */
    private $response;

    /** @var MockObject|ConfigHelper mocked instance of the Bolt configuration helper */
    private $configHelper;

    /** @var MockObject|OrderManagement  mocked instance of the tested class */
    private $currentMock;

    /** @var MockObject|CartHelper mocked instance of the Bolt cart helper */
    private $cartHelper;
    
    /** @var string stubbed json string response for {@see \Zend\Http\PhpEnvironment\Request::getContent} */
    private $requestContent;

    /** @var MockObject|Order mocked instance of the Order Model */
    private $order;

    /** @var MockObject|Decider mocked instance of the Bolt feature switch helper */
    private $decider;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock([]);

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
        $this->orderHelperMock = $this->createPartialMock(
            OrderHelper::class,
            [
                'saveUpdateOrder',
                'getStoreIdByQuoteId',
                'tryDeclinedPaymentCancelation',
                'deleteOrderByIncrementId',
                'saveCustomerCreditCard'
            ]
        );
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->request = $this->createMock(Request::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->metricsClient = $this->createMock(MetricsClient::class);
        $this->response = $this->createMock(Response::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->order = $this->createPartialMock(Order::class, ['getData']);

        $this->quoteMock = $this->createMock(Quote::class);
        $this->decider = $this->createPartialMock(
            Decider::class,
            ['isIgnoreHookForInvoiceCreationEnabled','isIgnoreHookForCreditMemoCreationEnabled']
        );

        $this->order->expects(self::any())->method('getData')
            ->willReturn([
                'id' => '1111',
                'increment_id'=> 'XXXXX',
                'grand_total' => '$11.00'
            ]);

        $this->orderHelperMock->expects(self::any())->method('saveUpdateOrder')
            ->willReturn([$this->quoteMock, $this->order]);

        $this->orderHelperMock->expects(self::any())->method('getStoreIdByQuoteId')
            ->will(self::returnValueMap([
                [self::ORDER_ID,self::STORE_ID],
                [null,null]
            ]));

        $this->cartHelper = $this->createMock(CartHelper::class);
    }

    private function initCurrentMock($methods)
    {
        $mockBuilder = $this->getMockBuilder(OrderManagement::class)
            ->setConstructorArgs([
                $this->hookHelper,
                $this->orderHelperMock,
                $this->logHelper,
                $this->request,
                $this->bugsnag,
                $this->metricsClient,
                $this->response,
                $this->configHelper,
                $this->cartHelper,
                $this->decider
            ]);
        if ($methods) {
            $mockBuilder->setMethods($methods);
        } else {
            $mockBuilder->enableProxyingToOriginalMethods();
        }
        $this->currentMock = $mockBuilder->getMock();
    }

    /**
     * @test
     * @covers ::manage
     * @covers ::saveUpdateOrder
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
     * @covers ::saveUpdateOrder
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
            null,
            self::DISPLAY_ID
        );
    }

    /**
     * @test
     * @depends manage_common
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function manage_rejectedIrreversible_fail()
    {
        $type = "rejected_irreversible";

        $this->orderHelperMock->expects(self::once())->method('tryDeclinedPaymentCancelation')
            ->willReturn(false);
        $this->request->expects(self::once())->method('getHeader')->with(ConfigHelper::BOLT_TRACE_ID_HEADER)
            ->willReturn(self::REQUEST_HEADER_TRACE_ID);
        $this->orderHelperMock->expects(self::once())->method('saveUpdateOrder')
            ->with(self::REFERENCE, self::STORE_ID, self::REQUEST_HEADER_TRACE_ID, $type)
            ->willReturn([null, $this->order]);

        $this->response->expects(self::once())->method('setHttpResponseCode')->with(200);
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'success',
            'message' => 'Order creation / update was successful. Order Data: {"id":"1111","increment_id":"XXXXX","grand_total":"$11.00"}',
        ]));

        $this->currentMock->manage(
            self::ID,
            self::REFERENCE,
            self::ORDER_ID,
            $type,
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
     * @covers ::saveUpdateOrder
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
            'status' => 'failure',
            'error' => [
                'code' => 2001001,
                'message' => $exception->getMessage(),
            ]
        ]));

        $this->currentMock->manage(
            self::ID,
            self::REFERENCE,
            self::ORDER_ID,
            $type,
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
     * @covers ::saveUpdateOrder
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
            null,
            self::DISPLAY_ID
        );
    }

    /**
     * @test
     * @depends manage_common
     * @covers ::manage
     * @covers ::saveUpdateOrder
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
            'status' => 'failure',
            'error' => [
                'code' => 2001001,
                'message' => $exception->getMessage(),
            ]
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
            null,
            self::DISPLAY_ID
        );
    }

    /**
     * @test
     * @depends manage_common
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function manage_failed_success()
    {
        $type = "failed";

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
            null,
            self::DISPLAY_ID
        );
    }

    /**
     * @test
     * @depends manage_common
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function manage_failed_exception()
    {
        $type = "failed";
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
            'status' => 'failure',
            'error' => [
                'code' => 2001001,
                'message' => $exception->getMessage(),
            ]
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
            null,
            self::DISPLAY_ID
        );
    }



    /**
     * @test
     * @depends manage_common
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function manage_webApiException()
    {
        $exception = new WebapiException(__('Precondition Failed'), 6001, 412);
        $this->hookHelper->expects(self::once())->method('preProcessWebhook')->with(self::STORE_ID)
            ->willThrowException($exception);
        $this->response->expects(self::once())->method('setHttpResponseCode')->with($exception->getHttpCode());
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'error',
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
        ]));
        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with('webhooks.failure', 1, "webhooks.latency", self::anything());
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
     * @covers ::saveUpdateOrder
     */
    public function manage_emptyReference()
    {
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(422);
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'error',
            'code' => '6009',
            'message' => 'Unprocessable Entity: Missing required parameters.',
        ]));
        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with('webhooks.failure', 1, "webhooks.latency", self::anything());
        $this->currentMock->manage(
            self::ID,
            null,
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
     * @covers ::saveUpdateOrder
     */
    public function manage_pending()
    {
        $type = "pending";

        $this->orderHelperMock->expects(self::never())->method('tryDeclinedPaymentCancelation');
        $this->orderHelperMock->expects(self::never())->method('deleteOrderByIncrementId');
        $this->request->expects(self::once())->method('getHeader')->with(ConfigHelper::BOLT_TRACE_ID_HEADER)
            ->willReturn(self::REQUEST_HEADER_TRACE_ID);
        $this->request->expects(self::once())->method('getBodyParams')
            ->willReturn(self::HOOK_PAYLOAD);
        $this->orderHelperMock->expects(self::once())->method('saveUpdateOrder')->with(
            self::REFERENCE,
            self::STORE_ID,
            self::REQUEST_HEADER_TRACE_ID,
            $type,
            self::HOOK_PAYLOAD
        );
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(200);
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'success',
            'message' => 'Order creation / update was successful. Order Data: {"id":"1111","increment_id":"XXXXX","grand_total":"$11.00"}',
        ]));

        $this->currentMock->manage(
            self::ID,
            self::REFERENCE,
            self::ORDER_ID,
            $type,
            self::AMOUNT,
            self::CURRENCY,
            null,
            self::DISPLAY_ID
        );
    }

    /**
     * @test
     * that __construct sets internal properties
     * 
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperties()
    {
        $instance = new OrderManagement(
            $this->hookHelper,
            $this->orderHelperMock,
            $this->logHelper,
            $this->request,
            $this->bugsnag,
            $this->metricsClient,
            $this->response,
            $this->configHelper,
            $this->cartHelper,
            $this->decider
        );

        static::assertAttributeEquals($this->hookHelper, 'hookHelper', $instance);
        static::assertAttributeEquals($this->orderHelperMock, 'orderHelper', $instance);
        static::assertAttributeEquals($this->logHelper, 'logHelper', $instance);
        static::assertAttributeEquals($this->request, 'request', $instance);
        static::assertAttributeEquals($this->bugsnag, 'bugsnag', $instance);
        static::assertAttributeEquals($this->metricsClient, 'metricsClient', $instance);
        static::assertAttributeEquals($this->response, 'response', $instance);
        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
        static::assertAttributeEquals($this->cartHelper, 'cartHelper', $instance);
        static::assertAttributeEquals($this->decider, 'decider', $instance);
    }

    private function manage_cartCreate_basicAssertion()
    {
        $this->startTime = microtime(true) * 1000;
        $this->metricsClient->expects(self::once())->method('getCurrentTime')->willReturn($this->startTime);

        $this->requestArray = [
            'type' => 'cart.create',
            'items' =>
                [
                    [
                        'reference' => '20102',
                        'name' => 'Product name',
                        'description' => null,
                        'options' => null,
                        'total_amount' => 100,
                        'unit_price' => 100,
                        'tax_amount' => 0,
                        'quantity' => 1,
                        'uom' => null,
                        'upc' => null,
                        'sku' => null,
                        'isbn' => null,
                        'brand' => null,
                        'manufacturer' => null,
                        'category' => null,
                        'tags' => null,
                        'properties' => null,
                        'color' => null,
                        'size' => null,
                        'weight' => null,
                        'weight_unit' => null,
                        'image_url' => null,
                        'details_url' => null,
                        'tax_code' => null,
                        'type' => 'unknown'
                    ]
                ],
            'currency' => 'USD',
            'metadata' => null,
        ];

        $this->hookHelper->expects(self::once())->method('preProcessWebhook')->with(null);
        $this->request->expects(self::once())->method('getBodyParams')->willReturn($this->requestArray);
    }

    /**
     * @test
     * @covers ::manage
     * @covers ::handleCartCreateApiCall
     */
    public function manage_cartCreate()
    {
        $this->manage_cartCreate_basicAssertion();
        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with('webhooks.success', 1, 'webhooks.latency', $this->startTime);
        $cart = [
            'order_reference' => '1001',
            'display_id' => '100010001 / 1001',
            'currency' => 'USD',
            'items' => [ [
                'reference' => '20102',
                'name' => 'Product name',
                'total_amount' => 100,
                'unit_price' => 100,
                'quantity' => 100,
                'sku' => 'TestProduct',
                'type' => 'physical',
                'description' => ''
            ] ],
            'discounts' => [],
            'total_amount' => 100,
            'tax_amount' => 0,
        ];
        $this->cartHelper->expects(self::once())->method('createCartByRequest')->with($this->requestArray)->willReturn($cart);
        $this->response->expects(self::once())->method('sendResponse');
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(200);
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'success',
            'cart' => $cart,
        ]));

        $this->currentMock->manage(
            null,
            null,
            null,
            'cart.create',
            null,
            null,
            null,
            null
        );
    }

    /**
     * @test
     * @dataProvider manage_cartCreate_error_dataProvider
     * @covers ::manage
     * @covers ::handleCartCreateApiCall
     */
    public function manage_cartCreate_error($exception, $error_code, $error_message)
    {
        $this->manage_cartCreate_basicAssertion();
        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with('webhooks.failure', 1, 'webhooks.latency', $this->startTime);

        $this->cartHelper->expects(self::once())->method('createCartByRequest')->with($this->requestArray)->willThrowException($exception);
        $this->response->expects(self::once())->method('sendResponse');
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(422);
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'failure',
            'error' => ['code' => $error_code, 'message' => $error_message],
        ]));

        $this->currentMock->manage(
            null,
            null,
            null,
            'cart.create',
            null,
            null,
            null,
            null
        );
    }

    public function manage_cartCreate_error_dataProvider()
    {
        return [
            [new BoltException(__('The requested qty is not available'), null, 6303), 6303, 'The requested qty is not available'],
            [new BoltException(__('Product that you are trying to add is not available.'), null, 6301), 6301, 'Product that you are trying to add is not available.'],
        ];
    }

    /**
     * @test
     * @depends manage_common
     * @covers ::manage
     * @covers ::saveUpdateOrder
     */
    public function manage_capture_IgnoreHookForInvoiceCreationIsEnabled()
    {
        $type = "capture";
        $this->decider->expects(self::once())->method('isIgnoreHookForInvoiceCreationEnabled')->willReturn(true);
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(200);
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'success',
            'message' => 'Ignore the capture hook for the invoice creation',
        ]));


        $this->currentMock->manage(
            self::ID,
            self::REFERENCE,
            self::ORDER_ID,
            $type,
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
     * @covers ::saveUpdateOrder
     */
    public function manage_credit_IgnoreHookForCreditMemoCreationIsEnabled()
    {
        $type = "credit";
        $this->decider->expects(self::once())->method('isIgnoreHookForCreditMemoCreationEnabled')->willReturn(true);
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(200);
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'success',
            'message' => 'Ignore the credit hook for the credit memo creation',
        ]));


        $this->currentMock->manage(
            self::ID,
            self::REFERENCE,
            self::ORDER_ID,
            $type,
            self::AMOUNT,
            self::CURRENCY,
            null,
            self::DISPLAY_ID
        );
    }

    /**
     * @test
     */
    public function testSaveCustomerCreditCardWhenPendingHookIsSentToMagento()
    {
        $type = "pending";
        $this->orderHelperMock->expects(self::once())->method('saveCustomerCreditCard')
            ->with(self::DISPLAY_ID, self::REFERENCE, self::STORE_ID)->willReturnSelf();

        $this->currentMock->manage(
            self::ID,
            self::REFERENCE,
            self::ORDER_ID,
            $type,
            self::AMOUNT,
            self::CURRENCY,
            null,
            self::DISPLAY_ID
        );
    }

    /**
     * @test
     * that setSuccessResponse sets response code to 200 and body to JSON containing success status and provided message
     *
     * @covers ::setSuccessResponse
     *
     * @throws ReflectionException if setSuccessResponse method doesn't exist
     */
    public function setSuccessResponse_always_setsSuccessResponse()
    {
        $message = 'Test message';
        
        $bodyData = json_encode([
            'status' => 'success',
            'message' => $message,
        ]);
        $this->response->expects(static::once())->method('setHttpResponseCode')->with(200)->willReturnSelf();
        $this->response->expects(static::once())->method('setBody')->with($bodyData)->willReturnSelf();
        
        TestHelper::invokeMethod($this->currentMock, 'setSuccessResponse', [$message]);
    }

    /**
     * @test
     * that handleCartCreateApiCall throws an exception if the first request item is not found
     *
     * @covers ::handleCartCreateApiCall
     *
     * @throws ReflectionException if handleCartCreateApiCall method doesn't exist
     */
    public function handleCartCreateApiCall_ifRequestItemsNotFound_throwsLocalizedException()
    {
        $this->request->expects(static::once())->method('getBodyParams')->willReturn(null);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Missing required parameters.');
        TestHelper::invokeMethod($this->currentMock, 'handleCartCreateApiCall');
    }
}
