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

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Helper\Data;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Model\Api\CreateOrder;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Class CreateOrderTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\CreateOrder
 */
class CreateOrderTest extends TestCase
{
    const STORE_ID = 1;
    const MINIMUM_ORDER_AMOUNT = 50;
    const ORDER_ID = 123;
    const QUOTE_ID = 457;
    const IMMUTABLE_QUOTE_ID = 456;
    const DISPLAY_ID = "000000123 / 456";
    const CURRENCY = "USD";
    const SUBTOTAL = 70;
    const SUBTOTAL_WITH_DISCOUNT = 70;
    const GRAND_TOTAL = 70;
    const PRODUCT_SKU = "24-UB02";

    /**
     * @var MockObject|HookHelper
     */
    private $hookHelper;

    /**
     * @var MockObject|OrderHelper
     */
    private $orderHelper;

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
     * @var MockObject|CartHelper
     */
    private $cartHelper;

    /**
     * @var MockObject|UrlInterface
     */
    private $url;

    /**
     * @var MockObject|BackendUrl
     */
    private $backendUrl;

    /**
     * @var MockObject|StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var MockObject|SessionHelper
     */
    private $sessionHelper;

    /**
     * @var MockObject|CreateOrder
     */
    private $currentMock;

    /**
     * @var MockObject|Quote
     */
    private $quoteMock;

    /**
     * @var MockObject|Quote
     */
    private $immutableQuoteMock;

    /**
     * @var MockObject|Order
     */
    private $orderMock;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    private function initRequiredMocks()
    {
        $this->hookHelper = $this->createMock(HookHelper::class);
        $this->orderHelper = $this->createMock(OrderHelper::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->request = $this->createMock(Request::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->metricsClient = $this->createMock(MetricsClient::class);
        $this->response = $this->createMock(Response::class);
        $this->url = $this->createMock(UrlInterface::class);
        $this->backendUrl = $this->createMock(BackendUrl::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->stockRegistry = $this->createMock(StockRegistryInterface::class);
        $this->sessionHelper = $this->createMock(SessionHelper::class);

        $this->quoteMock = $this->createPartialMock(
            Quote::class,
            [
                'validateMinimumAmount',
                'getGrandTotal',
                'getSubtotal',
                'getSubtotalWithDiscount',
                'getStoreId',
                'getId',
                'getAllVisibleItems',
                'isVirtual',
                'getShippingAddress',
                'getBoltIsBackendOrder',
            ]);
        $this->quoteMock->method('getStoreId')->willReturn(self::STORE_ID);

        $quoteItem = $this->getMockBuilder(\Magento\Quote\Model\Quote\Item::class)
            ->setMethods([
                'getSku', 'getQty', 'getCalculationPrice', 'getName', 'getIsVirtual',
                'getProductId', 'getProduct', 'getPrice', 'getErrorInfos'
            ])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteItem->method('getSku')->willReturn(self::PRODUCT_SKU);
        $quoteItem->method('getProductId')->willReturn('7');
        $quoteItem->method('getPrice')->willReturn(74);
        $quoteItem->method('getErrorInfos')->willReturn([]);
        $this->quoteMock->method('getAllVisibleItems')
            ->willReturn([
                $quoteItem
            ]);

        $quoteShippingAddress = $this->createPartialMock(
            \Magento\Quote\Model\Quote\Address::class,
            ['getTaxAmount', 'getShippingAmount']
        );
        $quoteShippingAddress->method('getTaxAmount')->willReturn(0);
        $quoteShippingAddress->method('getShippingAmount')->willReturn(5);
        $this->quoteMock->method('getShippingAddress')->willReturn($quoteShippingAddress);


        $this->immutableQuoteMock = $this->createPartialMock(
            Quote::class,
            [
                'validateMinimumAmount',
                'getGrandTotal',
                'getStoreId',
            ]);
        $this->immutableQuoteMock->method('getStoreId')->willReturn(self::STORE_ID);

        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->cartHelper->method('getRoundAmount')->willReturnCallback(
            function ($amount) {
                return (int)round($amount * 100);
            }
        );
        $this->cartHelper->method('getQuoteById')->willReturnMap([
            [self::QUOTE_ID, $this->quoteMock],
            [self::IMMUTABLE_QUOTE_ID, $this->immutableQuoteMock],
        ]);

        $this->orderMock = $this->createMock(Order::class);

        $this->request->expects(self::any())->method('getContent')->willReturn($this->getRequestContent());
    }

    private function initCurrentMock()
    {
        $this->currentMock = $this->getMockBuilder(CreateOrder::class)
            ->setConstructorArgs([
                $this->hookHelper,
                $this->orderHelper,
                $this->cartHelper,
                $this->logHelper,
                $this->request,
                $this->bugsnag,
                $this->metricsClient,
                $this->response,
                $this->url,
                $this->backendUrl,
                $this->configHelper,
                $this->stockRegistry,
                $this->sessionHelper
            ])
            ->setMethods([
                'getQuoteIdFromPayloadOrder',
                'loadQuoteData',
                'preProcessWebhook',
            ])
            ->enableProxyingToOriginalMethods()
            ->getMock();
        $this->currentMock->method('getQuoteIdFromPayloadOrder')
            ->withAnyParameters()
            ->willReturn(self::QUOTE_ID);
    }

    /**
     * @test
     */
    public function validateMinimumAmount_valid()
    {
        $this->quoteMock->expects(static::once())->method('validateMinimumAmount')->willReturn(true);
        $this->currentMock->validateMinimumAmount($this->quoteMock);
    }

    /**
     * @test
     * @covers ::validateMinimumAmount
     */
    public function validateMinimumAmount_invalid()
    {
        $this->quoteMock->expects(static::once())->method('validateMinimumAmount')->willReturn(false);
        $this->quoteMock->expects(static::once())->method('getStoreId')->willReturn(static::STORE_ID);
        $this->configHelper->expects(static::once())->method('getMinimumOrderAmount')->with(static::STORE_ID)
            ->willReturn(static::MINIMUM_ORDER_AMOUNT);
        $this->quoteMock->expects(self::once())->method('getSubtotal')->willReturn(self::SUBTOTAL);
        $this->quoteMock->expects(self::once())->method('getSubtotalWithDiscount')->willReturn(
            self::SUBTOTAL_WITH_DISCOUNT
        );
        $this->quoteMock->expects(self::once())->method('getGrandTotal')->willReturn(self::GRAND_TOTAL);
        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function (callable $fn) {
                $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                $reportMock->expects(self::once())->method('setMetaData')->with([
                    'Pre Auth' => [
                        'Minimum order amount' => static::MINIMUM_ORDER_AMOUNT,
                        'Subtotal' => self::SUBTOTAL,
                        'Subtotal with discount' => self::SUBTOTAL_WITH_DISCOUNT,
                        'Total' => self::GRAND_TOTAL,
                    ]
                ]);
                $fn($reportMock);
            });
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(\Bolt\Boltpay\Model\Api\CreateOrder::E_BOLT_MINIMUM_PRICE_NOT_MET);
        $this->expectExceptionMessage(
            sprintf(
                'The minimum order amount: %s has not being met.', static::MINIMUM_ORDER_AMOUNT
            )
        );
        $this->currentMock->validateMinimumAmount($this->quoteMock);
    }

    /**
     * @test
     */
    public function validateTotalAmount_valid()
    {
        $this->quoteMock->expects(static::once())->method('getGrandTotal')->willReturn(74);
        $this->currentMock->validateTotalAmount($this->quoteMock, $this->getTransaction());
    }

    /**
     * @test
     */
    public function validateTotalAmount_invalid()
    {
        $this->quoteMock->expects(static::once())->method('getGrandTotal')->willReturn(74.01);
        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function (callable $fn) {
                $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                $reportMock->expects(self::once())->method('setMetaData')->with([
                    'Pre Auth' => [
                        'quote.total_amount' => 7401,
                        'transaction.total_amount' => 7400,
                    ]
                ]);
                $fn($reportMock);
            });
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(\Bolt\Boltpay\Model\Api\CreateOrder::E_BOLT_GENERAL_ERROR);
        $this->expectExceptionMessage('Total amount does not match.');
        $this->currentMock->validateTotalAmount($this->quoteMock, $this->getTransaction());
    }

    /**
     * @test
     * @covers ::execute
     * @covers ::preProcessWebhook
     * @covers ::getQuoteIdFromPayloadOrder
     * @covers ::getDisplayId
     * @covers ::getOrderReference
     * @covers ::loadQuoteData
     */
    public function execute_common()
    {
        $startTime = microtime(true) * 1000;
        $type = 'order.create';

        $this->metricsClient->expects(self::once())->method('getCurrentTime')->willReturn($startTime);
        $this->logHelper->expects(self::exactly(4))->method('addInfoLog')
            ->withConsecutive(
                ['[-= Pre-Auth CreateOrder =-]'], [$this->getRequestContent()], ['[-= getReceivedUrl =-]'], ['---> ']
            );
        $this->hookHelper->expects(self::once())->method('preProcessWebhook')->with(self::STORE_ID);
        $this->orderHelper->expects(self::once())->method('prepareQuote')
            ->with($this->immutableQuoteMock, $this->getTransaction())
            ->willReturn($this->quoteMock);
        $this->orderHelper->expects(self::once())->method('processExistingOrder')
            ->with($this->quoteMock, $this->getTransaction())->willReturn($this->orderMock);
        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with(self::anything(), 1, 'order_creation.latency', $startTime);
        $this->response->expects(self::once())->method('sendResponse');

        $this->currentMock->execute(
            $type,
            $this->getOrderTransaction(),
            self::CURRENCY
        );
    }


    /**
     * @test
     * @covers ::execute
     */
    public function execute_invalidHookType()
    {
        $exception = new BoltException(
            __('Invalid hook type!'),
            null,
            CreateOrder::E_BOLT_GENERAL_ERROR
        );
        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with("order_creation.failure", 1, "order_creation.latency", self::anything());
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(422);
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'failure',
            'error' => [[
                'code' => $exception->getCode(),
                'data' => [[
                    'reason' => $exception->getMessage(),
                ]]
            ]]
        ]));
        $this->currentMock->execute(
            null,
            $this->getOrderTransaction(),
            self::CURRENCY
        );
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_emptyOrder()
    {
        $exception = new BoltException(
            __('Missing order data.'),
            null,
            CreateOrder::E_BOLT_GENERAL_ERROR
        );
        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with("order_creation.failure", 1, "order_creation.latency", self::anything());
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(422);
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'failure',
            'error' => [[
                'code' => $exception->getCode(),
                'data' => [[
                    'reason' => $exception->getMessage(),
                ]]
            ]]
        ]));
        $this->currentMock->execute(
            'order.create',
            null,
            self::CURRENCY
        );
    }

    /**
     * @test
     * @covers ::execute
     * @covers ::validateQuoteData
     * @covers ::validateMinimumAmount
     * @covers ::validateCartItems
     * @covers ::hasItemErrors
     * @covers ::getQtyFromTransaction
     * @covers ::validateItemPrice
     * @covers ::validateTax
     * @covers ::validateShippingCost
     * @covers ::validateTotalAmount
     */
    public function execute_processNewOrder()
    {
        $this->orderHelper->expects(self::once())->method('prepareQuote')
            ->with($this->immutableQuoteMock, $this->getTransaction())
            ->willReturn($this->quoteMock);
        $this->orderHelper->expects(self::once())->method('processExistingOrder')
            ->with($this->quoteMock, $this->getTransaction())->willReturn(false);
        $this->quoteMock->expects(self::once())->method('validateMinimumAmount')
            ->willReturn(true);
        $this->quoteMock->expects(self::once())->method('getGrandTotal')->willReturn(74);
        $this->orderHelper->expects(self::once())->method('processNewOrder')
            ->with($this->quoteMock, $this->getTransaction())->willReturn($this->orderMock);

        $this->currentMock->execute(
            'order.create',
            $this->getOrderTransaction(),
            self::CURRENCY
        );
    }

    /**
     * @test
     * @covers ::execute
     * @covers ::preProcessWebhook
     */
    public function execute_webApiException()
    {
        $exception = new WebapiException(__('Precondition Failed'), 6001, 412);
        $this->hookHelper->expects(self::once())->method('preProcessWebhook')->with(self::STORE_ID)
            ->willThrowException($exception);
        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->response->expects(self::once())->method('setHttpResponseCode')->with($exception->getHttpCode());
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'failure',
            'error' => [[
                'code' => CreateOrder::E_BOLT_GENERAL_ERROR,
                'data' => [[
                    'reason' => $exception->getCode() . ': ' . $exception->getMessage(),
                ]]
            ]]
        ]));
        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with('order_creation.failure', 1, "order_creation.latency", self::anything());

        $this->currentMock->execute(
            'order.create',
            $this->getOrderTransaction(),
            self::CURRENCY
        );
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_localizedException()
    {
        $exception = new \Magento\Framework\Exception\LocalizedException(
            __('The requested Payment Method is not available.')
        );
        $this->hookHelper->expects(self::once())->method('preProcessWebhook')->with(self::STORE_ID);

        $this->orderHelper->expects(self::once())
            ->method('prepareQuote')
            ->with($this->immutableQuoteMock, $this->getTransaction())
            ->willThrowException($exception);

        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(422);
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'failure',
            'error' => [[
                'code' => 6009,
                'data' => [[
                    'reason' => 'Unprocessable Entity: ' . $exception->getMessage(),
                ]]
            ]]
        ]));
        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with('order_creation.failure', 1, "order_creation.latency", self::anything());

        $this->currentMock->execute(
            'order.create',
            $this->getOrderTransaction(),
            self::CURRENCY
        );
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_otherException()
    {
        $exception = new \Exception(
            __('Other exception.')
        );
        $this->hookHelper->expects(self::once())->method('preProcessWebhook')->with(self::STORE_ID);

        $this->orderHelper->expects(self::once())
            ->method('prepareQuote')
            ->with($this->immutableQuoteMock, $this->getTransaction())
            ->willThrowException($exception);

        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(422);
        $this->response->expects(self::once())->method('setBody')->with(json_encode([
            'status' => 'failure',
            'error' => [[
                'code' => CreateOrder::E_BOLT_GENERAL_ERROR,
                'data' => [[
                    'reason' => $exception->getMessage(),
                ]]
            ]]
        ]));
        $this->metricsClient->expects(self::once())->method('processMetric')
            ->with('order_creation.failure', 1, "order_creation.latency", self::anything());

        $this->currentMock->execute(
            'order.create',
            $this->getOrderTransaction(),
            self::CURRENCY
        );
    }

    /**
     * @test
     * @covers ::getQuoteIdFromPayloadOrder
     */
    public function getQuoteIdFromPayloadOrder_noOrder()
    {
        self::assertEquals(
            $this->currentMock->getQuoteIdFromPayloadOrder([
                'cart' => [
                    'order_reference' => self::ORDER_ID,
                    'display_id' => false,
                ]
            ]),
            self::ORDER_ID
        );
    }

    /**
     * @test
     * @covers ::getOrderReference
     */
    public function getOrderReference_exception()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('cart->order_reference does not exist');
        $this->currentMock->getOrderReference([]);
    }

    /**
     * @test
     * @covers ::getDisplayId
     */
    public function getDisplayId_exception()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('cart->display_id does not exist');
        $this->currentMock->getDisplayId([]);
    }

    /**
     * @test
     * @covers ::getReceivedUrl
     */
    public function getReceivedUrl_frontend()
    {
        $url = 'baseurl/admin/boltpay/order/receivedurl';
        $this->sessionHelper->expects(self::once())->method('setFormKey')->with($this->quoteMock);
        $this->logHelper->expects(self::exactly(2))->method('addInfoLog')
            ->withConsecutive(['[-= getReceivedUrl =-]'], ['---> ' . $url]);
        $this->quoteMock->expects(self::once())->method('getBoltIsBackendOrder')->willReturn(false);
        $this->url->expects(self::once())->method('setScope')->with(self::STORE_ID);
        $this->url->expects(self::once())->method('getUrl')->with('boltpay/order/receivedurl', [
            '_secure' => true,
            'store_id' => self::STORE_ID
        ])->willReturn($url);
        $this->currentMock->getReceivedUrl($this->quoteMock);
    }

    /**
     * @test
     * @covers ::getReceivedUrl
     */
    public function getReceivedUrl_backend()
    {
        $url = 'baseurl/boltpay/order/receivedurl';
        $this->sessionHelper->expects(self::once())->method('setFormKey')->with($this->quoteMock);
        $this->logHelper->expects(self::exactly(2))->method('addInfoLog')
            ->withConsecutive(['[-= getReceivedUrl =-]'], ['---> ' . $url]);
        $this->quoteMock->expects(self::once())->method('getBoltIsBackendOrder')->willReturn(true);
        $this->backendUrl->expects(self::once())->method('setScope')->with(self::STORE_ID);
        $this->backendUrl->expects(self::once())->method('getUrl')->with('boltpay/order/receivedurl', [
            '_secure' => true,
            'store_id' => self::STORE_ID
        ])->willReturn($url);
        $this->currentMock->getReceivedUrl($this->quoteMock);
    }

    /**
     * @test
     * @covers ::isBackOfficeOrder
     */
    public function isBackOfficeOrder_true()
    {
        $this->quoteMock->expects(self::once())->method('getBoltIsBackendOrder')->willReturn(true);
        self::assertTrue($this->currentMock->isBackOfficeOrder($this->quoteMock));
    }

    /**
     * @test
     * @covers ::isBackOfficeOrder
     */
    public function isBackOfficeOrder_false()
    {
        $this->quoteMock->expects(self::once())->method('getBoltIsBackendOrder')->willReturn(false);
        self::assertFalse($this->currentMock->isBackOfficeOrder($this->quoteMock));
    }

    /**
     * @test
     * @covers ::loadQuoteData
     */
    public function loadQuoteData_exception()
    {
        $quoteId = self::QUOTE_ID;
        $this->cartHelper->expects(self::once())->method('getQuoteById')->with(self::QUOTE_ID)
            ->willThrowException(new NoSuchEntityException());
        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function (callable $fn) use ($quoteId) {
                $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                $reportMock->expects(self::once())->method('setMetaData')->with([
                    'ORDER' => [
                        'pre-auth' => true,
                        'quoteId' => $quoteId,
                    ]
                ]);
                $fn($reportMock);
            });
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(CreateOrder::E_BOLT_GENERAL_ERROR);
        $this->expectExceptionMessage(sprintf('There is no quote with ID: %s', $quoteId));

        $this->currentMock->loadQuoteData($quoteId);
    }

    /**
     * @test
     * @covers ::validateCartItems
     * @covers ::getCartItemsFromTransaction
     * @covers ::arrayDiff
     */
    public function validateCartItems_exception()
    {
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(CreateOrder::E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED);
        $this->expectExceptionMessage('Cart data has changed. SKU: ["24-UB02"]');
        $this->currentMock->validateCartItems($this->quoteMock, json_decode('{"order":{"cart":{"items":{}}}}'));
    }

    /**
     * @test
     * @covers ::hasItemErrors
     */
    public function hasItemErrors_qty()
    {
        /** @var MockObject|Quote\Item $quoteItem */
        $quoteItem = $this->getMockBuilder(Quote\Item::class)
            ->setMethods([
                'getSku', 'getQty', 'getCalculationPrice', 'getName', 'getIsVirtual',
                'getProductId', 'getProduct', 'getPrice', 'getErrorInfos'
            ])
            ->disableOriginalConstructor()
            ->getMock();
        $message = 'This product is out of stock.';
        $errorInfo = [
            'origin' => 'cataloginventory',
            'code' => Data::ERROR_QTY,
            'message' => __($message),
        ];
        $quoteItem->method('getErrorInfos')->willReturn([$errorInfo]);

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function (callable $fn) use ($errorInfo) {
                $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                $reportMock->expects(self::once())->method('setMetaData')->with([
                    'Pre Auth' => [
                        'quoteItem errors' => '(' . $errorInfo['origin'] . '): ' . $errorInfo['message']->render() . PHP_EOL,
                        'Error Code' => Data::ERROR_QTY
                    ]
                ]);
                $fn($reportMock);
            });
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(CreateOrder::E_BOLT_ITEM_OUT_OF_INVENTORY);
        $this->expectExceptionMessage($message);
        $this->currentMock->hasItemErrors($quoteItem);
    }

    /**
     * @test
     * @covers ::validateItemPrice
     * @covers ::getUnitPriceFromTransaction
     * @covers ::getSkuFromTransaction
     */
    public function validateItemPrice_exception()
    {
        $itemSku = self::PRODUCT_SKU;
        $transactionItems = json_decode(json_encode($this->getOrderTransactionItems()));

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function (callable $fn) {
                $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                $reportMock->expects(self::once())->method('setMetaData')->with([
                    'Pre Auth' => [
                        'item.price' => 7402,
                        'transaction.unit_price' => 7400,
                    ]
                ]);
                $fn($reportMock);
            });
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(CreateOrder::E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED);
        $this->expectExceptionMessage('Price does not match. Item sku: ' . $itemSku);

        $this->currentMock->validateItemPrice(
            $itemSku,
            7402,
            $transactionItems
        );
    }

    /**
     * @test
     * @covers ::validateTax
     * @covers ::getTaxAmountFromTransaction
     */
    public function validateTax_exception()
    {
        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function (callable $fn) {
                $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                $reportMock->expects(self::once())->method('setMetaData')->with([
                    'Pre Auth' => [
                        'shipping.tax_amount' => 0,
                        'transaction.tax_amount' => OrderHelper::MISMATCH_TOLERANCE + 1,
                    ]
                ]);
                $fn($reportMock);
            });
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(CreateOrder::E_BOLT_GENERAL_ERROR);
        $this->expectExceptionMessage('Cart Tax mismatched.');

        $this->currentMock->validateTax(
            $this->quoteMock,
            json_decode('{"order":{"cart":{"tax_amount":{"amount":2}}}}')
        );
    }

    /**
     * @test
     * @covers ::validateShippingCost
     * @covers ::getShippingAmountFromTransaction
     */
    public function validateShippingCost_exception()
    {
        $storeCost = 500;
        $boltCost = 0;
        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function (callable $fn) use ($boltCost, $storeCost) {
                $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                $reportMock->expects(self::once())->method('setMetaData')->with([
                    'Pre Auth' => [
                        'shipping.shipping_amount' => $storeCost,
                        'transaction.shipping_amount' => $boltCost,
                        ]
                ]);
                $fn($reportMock);
            });
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(CreateOrder::E_BOLT_SHIPPING_EXPIRED);
        $this->expectExceptionMessage(
            'Shipping total has changed. Old value: ' . $boltCost . ', new value: ' . $storeCost
        );

        $this->currentMock->validateShippingCost(
            $this->quoteMock,
            json_decode('{}')
        );
    }

    /**
     * @test
     * @covers ::validateShippingCost
     * @covers ::getShippingAmountFromTransaction
     */
    public function validateShippingCost_virtual()
    {
        $this->quoteMock->expects(self::once())->method('isVirtual')->willReturn(true);

        $transaction = $this->getTransaction();
        $transaction->order->cart->shipping_amount->amount = 0;

        $this->currentMock->validateShippingCost(
            $this->quoteMock,
            $transaction
        );
    }

    /**
     * @test
     * @covers ::validateShippingCost
     * @covers ::getShippingAmountFromTransaction
     */
    public function validateShippingCost_virtual_exception()
    {
        $storeCost = 0;
        $boltCost = 500;

        $this->quoteMock->expects(self::once())->method('isVirtual')->willReturn(true);

        $this->bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function (callable $fn) use ($boltCost, $storeCost) {
                $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                $reportMock->expects(self::once())->method('setMetaData')->with([
                    'Pre Auth' => [
                        'shipping.shipping_amount' => $storeCost,
                        'transaction.shipping_amount' => $boltCost,
                        ]
                ]);
                $fn($reportMock);
            });
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(CreateOrder::E_BOLT_SHIPPING_EXPIRED);
        $this->expectExceptionMessage(
            'Shipping total has changed. Old value: ' . $boltCost . ', new value: ' . $storeCost
        );

        $this->currentMock->validateShippingCost(
            $this->quoteMock,
            $this->getTransaction()
        );
    }

    /**
     * @test
     * @covers ::sendResponse
     */
    public function sendResponse()
    {
        $code = 200;
        $body = ['test' => 'test'];

        $this->response->expects(self::once())->method('setHttpResponseCode')->with($code);
        $this->response->expects(self::once())->method('setBody')->with(json_encode($body));
        $this->currentMock->sendResponse(
            $code,
            $body
        );
    }

    /**
     * @return \stdClass
     */
    private function getTransaction()
    {
        return json_decode($this->getRequestContent());
    }

    /**
     * @return string
     */
    private function getRequestContent(): string
    {
        return json_encode($this->getRequestArray());
    }

    /**
     * @return array
     */
    private function getOrderTransaction()
    {
        return [
            "token" => "adae3381970f8a96ddfea87b6cdbee5aa7c5dc49679f5105171ea22ce0c6766e",
            "cart" => [
                "order_reference" => self::ORDER_ID,
                "display_id" => self::DISPLAY_ID,
                "currency" => [
                    "currency" => self::CURRENCY,
                    "currency_symbol" => "$"
                ],
                "subtotal_amount" => null,
                "total_amount" => [
                    "amount" => 7400,
                    "currency" => "USD",
                    "currency_symbol" => "$"
                ],
                "tax_amount" => [
                    "amount" => 0,
                    "currency" => "USD",
                    "currency_symbol" => "$"
                ],
                "shipping_amount" => [
                    "amount" => 500,
                    "currency" => "USD",
                    "currency_symbol" => "$"
                ],
                "discount_amount" => [
                    "amount" => 0,
                    "currency" => "USD",
                    "currency_symbol" => "$"
                ],
                "billing_address" => [
                    "id" => "AA3xYFWjogfah",
                    "street_address1" => "DO NOT SHIP",
                    "locality" => "Beverly Hills",
                    "region" => "California",
                    "postal_code" => "90210",
                    "country_code" => "US",
                    "country" => "United States",
                    "name" => "DO_NOT_SHIP DO_NOT_SHIP",
                    "first_name" => "DO_NOT_SHIP",
                    "last_name" => "DO_NOT_SHIP",
                    "phone_number" => "5551234567",
                    "email_address" => "test@guaranteed.network"
                ],
                "items" => $this->getOrderTransactionItems(),
                "shipments" => [
                    [
                        "shipping_address" => [
                            "id" => "AA2HtQVSbQBtf",
                            "street_address1" => "DO NOT SHIP",
                            "locality" => "Beverly Hills",
                            "region" => "California",
                            "postal_code" => "90210",
                            "country_code" => "US",
                            "country" => "United States",
                            "name" => "DO_NOT_SHIP DO_NOT_SHIP",
                            "first_name" => "DO_NOT_SHIP",
                            "last_name" => "DO_NOT_SHIP",
                            "phone_number" => "5551234567",
                            "email_address" => "bolttest@guaranteed.network"
                        ],
                        "shipping_method" => "unknown",
                        "service" => "Best Way - Table Rate",
                        "cost" => [
                            "amount" => 0,
                            "currency" => "USD",
                            "currency_symbol" => "$"
                        ],
                        "reference" => "tablerate_bestway"
                    ]
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    private function getRequestArray(): array
    {
        return [
            "id" => "TAj57ALHgDNXZ",
            "type" => "cc_payment",
            "date" => 1566923343111,
            "reference" => "3JRC-BVGG-CNBD",
            "status" => "completed",
            "from_consumer" => [
                "id" => "CAiC6LhLAUMq7",
                "first_name" => "Bolt",
                "last_name" => "Team",
                "avatar" => [
                    "domain" => "img-sandbox.bolt.com",
                    "resource" => "default.png"
                ],
                "phones" => [
                    [
                        "id" => "",
                        "number" => "+1 7894566548",
                        "country_code" => "1",
                        "status" => "",
                        "priority" => ""
                    ],
                    [
                        "id" => "PAfnA3HiQRZpZ",
                        "number" => "+1 789 456 6548",
                        "country_code" => "1",
                        "status" => "pending",
                        "priority" => "primary"
                    ]
                ],
                "emails" => [
                    [
                        "id" => "",
                        "address" => "daniel.dragic@bolt.com",
                        "status" => "",
                        "priority" => ""
                    ],
                    [
                        "id" => "EA8hRQHRpHx6Z",
                        "address" => "daniel.dragic@bolt.com",
                        "status" => "pending",
                        "priority" => "primary"
                    ]
                ]
            ],
            "to_consumer" => [
                "id" => "CAfR8NYVXrrLb",
                "first_name" => "Leon",
                "last_name" => "McCottry",
                "avatar" => [
                    "domain" => "img-sandbox.bolt.com",
                    "resource" => "default.png"
                ],
                "phones" => [
                    [
                        "id" => "PAgDzbZW8iwZ7",
                        "number" => "5555559647",
                        "country_code" => "1",
                        "status" => "active",
                        "priority" => "primary"
                    ]
                ],
                "emails" => [
                    [
                        "id" => "EA4iyW8c7Mues",
                        "address" => "leon+magento2@bolt.com",
                        "status" => "active",
                        "priority" => "primary"
                    ]
                ]
            ],
            "from_credit_card" => [
                "id" => "CA8E8FedBJNfM",
                "description" => "default card",
                "last4" => "1111",
                "bin" => "411111",
                "expiration" => 1575158400000,
                "network" => "visa",
                "token_type" => "vantiv",
                "priority" => "listed",
                "display_network" => "Visa",
                "icon_asset_path" => "img/issuer-logos/visa.png",
                "status" => "transient",
                "billing_address" => [
                    "id" => "AA2L6bxABJBn4",
                    "street_address1" => "1235D Howard Street",
                    "locality" => "San Francisco",
                    "region" => "California",
                    "postal_code" => "94103",
                    "country_code" => "US",
                    "country" => "United States",
                    "name" => "Bolt Team",
                    "first_name" => "Bolt",
                    "last_name" => "Team",
                    "company" => "Bolt",
                    "phone_number" => "7894566548",
                    "email_address" => "daniel.dragic@bolt.com"
                ]
            ],
            "amount" => [
                "amount" => 2882,
                "currency" => "USD",
                "currency_symbol" => "$"
            ],
            "authorization" => [
                "status" => "succeeded",
                "reason" => "none"
            ],
            "capture" => [
                "id" => "CAfi8PprxApDF",
                "status" => "succeeded",
                "amount" => [
                    "amount" => 2882,
                    "currency" => "USD",
                    "currency_symbol" => "$"
                ],
                "splits" => [
                    [
                        "amount" => [
                            "amount" => 2739,
                            "currency" => "USD",
                            "currency_symbol" => "$"
                        ],
                        "type" => "net"
                    ],
                    [
                        "amount" => [
                            "amount" => 114,
                            "currency" => "USD",
                            "currency_symbol" => "$"
                        ],
                        "type" => "processing_fee"
                    ],
                    [
                        "amount" => [
                            "amount" => 29,
                            "currency" => "USD",
                            "currency_symbol" => "$"
                        ],
                        "type" => "bolt_fee"
                    ]
                ]
            ],
            "captures" => [
                [
                    "id" => "CAfi8PprxApDF",
                    "status" => "succeeded",
                    "amount" => [
                        "amount" => 2882,
                        "currency" => "USD",
                        "currency_symbol" => "$"
                    ],
                    "splits" => [
                        [
                            "amount" => [
                                "amount" => 2739,
                                "currency" => "USD",
                                "currency_symbol" => "$"
                            ],
                            "type" => "net"
                        ],
                        [
                            "amount" => [
                                "amount" => 114,
                                "currency" => "USD",
                                "currency_symbol" => "$"
                            ],
                            "type" => "processing_fee"
                        ],
                        [
                            "amount" => [
                                "amount" => 29,
                                "currency" => "USD",
                                "currency_symbol" => "$"
                            ],
                            "type" => "bolt_fee"
                        ]
                    ]
                ]
            ],
            "merchant_division" => [
                "id" => "MAd7pWDqT9JzX",
                "merchant_id" => "MAe3Hc1YXENzq",
                "public_id" => "NwQxY8yKNDiL",
                "description" => "bolt-magento2 - full",
                "logo" => [
                    "domain" => "img-sandbox.bolt.com",
                    "resource" => "bolt-magento2_-_full_logo_1559750957154518171.png"
                ],
                "platform" => "magento",
                "hook_url" => "https://bane-magento2.guaranteed.site/rest/V1/bolt/boltpay/order/manage",
                "hook_type" => "bolt",
                "shipping_and_tax_url" => "https://bane-magento2.guaranteed.site/rest/V1/bolt/boltpay/shipping/methods",
                "create_order_url" => "https://bane-magento2.guaranteed.site/rest/V1/bolt/boltpay/order/create"
            ],
            "merchant" => [
                "description" => "Guaranteed Site - Magento2 Sandbox",
                "time_zone" => "America/Los_Angeles",
                "public_id" => "aksPFmo1MoeQ",
                "processor" => "vantiv",
                "processor_linked" => true
            ],
            "indemnification_decision" => "indemnified",
            "indemnification_reason" => "risk_engine_approved",
            "last_viewed_utc" => 0,
            "splits" => [
                [
                    "amount" => [
                        "amount" => 2739,
                        "currency" => "USD",
                        "currency_symbol" => "$"
                    ],
                    "type" => "net"
                ],
                [
                    "amount" => [
                        "amount" => 114,
                        "currency" => "USD",
                        "currency_symbol" => "$"
                    ],
                    "type" => "processing_fee"
                ],
                [
                    "amount" => [
                        "amount" => 29,
                        "currency" => "USD",
                        "currency_symbol" => "$"
                    ],
                    "type" => "bolt_fee"
                ]
            ],
            "auth_verification_status" => "",
            "order" => $this->getOrderTransaction(),
            "timeline" => [
                [
                    "date" => 1567011727217,
                    "type" => "note",
                    "note" => "Bolt Settled Order",
                    "visibility" => "merchant"
                ],
                [
                    "date" => 1566923457810,
                    "type" => "note",
                    "note" => "Guaranteed Site - Magento2 Sandbox Captured Order",
                    "visibility" => "merchant"
                ],
                [
                    "date" => 1566923431003,
                    "type" => "note",
                    "note" => "Bolt Approved Order",
                    "visibility" => "merchant"
                ],
                [
                    "date" => 1566923344843,
                    "type" => "note",
                    "note" => "Authorized Order",
                    "consumer" => [
                        "id" => "CAi8cQ5u5vL5P",
                        "first_name" => "Bolt",
                        "last_name" => "Team",
                        "avatar" => [
                            "domain" => "img-sandbox.bolt.com",
                            "resource" => "default.png"
                        ]
                    ],
                    "visibility" => "merchant"
                ],
                [
                    "date" => 1566923343433,
                    "type" => "note",
                    "note" => "Created Order",
                    "consumer" => [
                        "id" => "CAi8cQ5u5vL5P",
                        "first_name" => "Bolt",
                        "last_name" => "Team",
                        "avatar" => [
                            "domain" => "img-sandbox.bolt.com",
                            "resource" => "default.png"
                        ]
                    ],
                    "visibility" => "merchant"
                ]
            ],
            "refunded_amount" => [
                "amount" => 0,
                "currency" => "USD",
                "currency_symbol" => "$"
            ],
            "refund_transaction_ids" => [
            ],
            "refund_transactions" => [
            ],
            "source_transaction" => null,
            "adjust_transactions" => [
            ]
        ];
    }

    /**
     * @return array
     */
    private function getOrderTransactionItems(): array
    {
        return [
            $this->getOrderTransactionItem()
        ];
    }

    /**
     * @return array
     */
    private function getOrderTransactionItem(): array
    {
        return [
            "reference" => "7",
            "name" => "Impulse Duffle",
            "total_amount" => [
                "amount" => 7400,
                "currency" => "USD",
                "currency_symbol" => "$"
            ],
            "unit_price" => [
                "amount" => 7400,
                "currency" => "USD",
                "currency_symbol" => "$"
            ],
            "quantity" => 1,
            "sku" => self::PRODUCT_SKU,
            "image_url" => "",
            "type" => "physical",
            "taxable" => true,
            "properties" => [
            ]
        ];
    }
}
