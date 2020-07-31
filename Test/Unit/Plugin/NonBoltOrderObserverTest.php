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

namespace Bolt\Boltpay\Test\Unit\Plugin;

use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Model\Response;
use Bolt\Boltpay\Plugin\NonBoltOrderPlugin;
use Exception;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\DataObject;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\DataObjectFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Quote\Api\CartRepositoryInterface as QuoteRepository;
use Magento\Sales\Model\Order\Payment;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Bolt\Boltpay\Plugin\NonBoltOrderPlugin as Plugin;
use \PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Model\Request;

/**
 * Class NonBoltOrderPluginTest
 * @coversDefaultClass \Bolt\Boltpay\Plugin\NonBoltOrderPlugin
 */
class NonBoltOrderObserverTest extends TestCase
{
    const IMMUTABLE_QUOTE_ID = 1001;
    const RESERVED_ORDER_ID = '100010001';
    const FIRST_NAME = 'IntegrationBolt';
    const LAST_NAME = 'BoltTest';
    const EMAIL = 'integration@bolt.com';
    const PHONE = '132 231 1234';
    const PAYMENT_METHOD = 'paypal_express';
    const EXPECTED_SHIPMENT = [
        'cost'             => 0,
        'tax_amount'       => 0,
        'shipping_address' => [
            'first_name'      => self::FIRST_NAME,
            'last_name'       => self::LAST_NAME,
            'phone'           => self::PHONE,
            'email'           => self::EMAIL,
        ],
        'service'          => null,
        'reference'        => null,
    ];
    const CART = [
        'order_reference' => self::IMMUTABLE_QUOTE_ID,
        'display_id'      => self::RESERVED_ORDER_ID . ' / ' . self::IMMUTABLE_QUOTE_ID,
        'shipments'       => [self::EXPECTED_SHIPMENT],
        'total_amount'    => 100,
        'tax_amount'      => 0,
    ];

    /**
     * @var ApiHelper|MockObject
     */
    private $apiHelper;

    /**
     * @var CartHelper|MockObject
     */
    private $cartHelper;

    /**
     * @var DataObject|MockObject
     */
    private $dataObject;

    /**
     * @var QuoteRepository|MockObject
     */
    private $quoteRepository;

    /**
     * @var Decider|MockObject
     */
    private $decider;

    /**
     * @var Plugin
     */
    protected $plugin;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagementInterface;

    protected function setUp()
    {
        $this->initRequiredMocks();
    }

    private function initRequiredMocks()
    {
        $this->decider = $this->createMock(Decider::class);
        $this->apiHelper = $this->createMock(ApiHelper::class);
        $bugsnag = $this->createMock(Bugsnag::class);
        $this->cartHelper = $this->getMockBuilder(CartHelper::class)
            ->setMethods(['buildCartFromQuote'])
            ->disableOriginalConstructor()
            ->getMock();
        $configHelper = $this->createMock(ConfigHelper::class);
        $this->dataObject = $this->getMockBuilder(DataObject::class)
            ->setMethods(['setApiData'])
            ->disableOriginalConstructor()
            ->getMock();
        $dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $dataObjectFactory->method('create')->willReturn($this->dataObject);
        $logHelper = $this->createMock(LogHelper::class);
        $metricsClient = $this->createMock(MetricsClient::class);
        $this->quoteRepository = $this->createMock(QuoteRepository::class);
        $this->orderManagementInterface = $this->createMock(OrderManagementInterface::class);
        $objectManager = new ObjectManager($this);
        $this->plugin = $objectManager->getObject(
            NonBoltOrderPlugin::class,
            [
                'apiHelper' => $this->apiHelper,
                'bugsnag' => $bugsnag,
                'cartHelper' => $this->cartHelper,
                'configHelper' => $configHelper,
                'dataObjectFactory' => $dataObjectFactory,
                'logHelper' => $logHelper,
                'metricsClient' => $metricsClient,
                'quoteRepository' => $this->quoteRepository,
                'decider' => $this->decider
            ]
        );
    }

    /**
     * @test
     */
    public function testExecute()
    {
        $order = $this->getMockBuilder("BoltOrder")
            ->setMethods(['getPayment', 'getQuoteId', 'getStoreId', 'setBoltTransactionReference', 'save'])
            ->getMock();
        $payment = $this->createMock(Payment::class);
        $item = $this->createMock(Item::class);
        $quote = $this->createMock(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([$item]);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn(self::EMAIL);
        $customer->method('getFirstname')->willReturn(self::FIRST_NAME);
        $customer->method('getLastname')->willReturn(self::LAST_NAME);
        $quote->method('getCustomer')->willReturn($customer);
        $address = $this->createMock(Address::class);
        $address->method('getFirstname')->willReturn(self::FIRST_NAME);
        $address->method('getLastname')->willReturn(self::LAST_NAME);
        $quote->method('getShippingAddress')->willReturn($address);

        $this->decider->expects($this->once())->method('isNonBoltTrackingEnabled')->willReturn(true);
        $this->quoteRepository->expects($this->once())
            ->method('get')
            ->willReturn($quote);
        $order->expects($this->once())
            ->method('getPayment')
            ->willReturn($payment);
        $order->expects($this->once())
            ->method('setBoltTransactionReference');
        $order->expects($this->once())
            ->method('save');
        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn(self::PAYMENT_METHOD);

        $this->cartHelper->method('buildCartFromQuote')->willReturn(self::CART);
        $expectedOrderData = [
            'cart' => self::CART,
            'user_identifier' => [
                'email' => self::EMAIL,
                'phone' => self::PHONE,
            ],
            'user_identity' => [
                'first_name' => self::FIRST_NAME,
                'last_name' => self::LAST_NAME,
            ],
            'payment_method' => self::PAYMENT_METHOD,
        ];

        $this->dataObject->expects($this->once())
            ->method("setApiData")
            ->with($expectedOrderData);

        $this->apiHelper->expects($this->once())->method('buildRequest')->willReturn(new Request());
        $response = new Response();
        $response->setResponse(json_decode('{"reference": "ABCD-EFGH-1234"}'));
        $this->apiHelper->expects($this->once())->method('sendRequest')->willReturn($response);
        $this->plugin->afterPlace($this->orderManagementInterface, $order);
    }

    /**
     * @test
     */
    public function testExecuteNoQuote()
    {
        $this->decider->expects($this->once())->method('isNonBoltTrackingEnabled')->willReturn(true);
        $order = $this->createMock(Order::class);
        $this->quoteRepository->expects($this->once())
            ->method('get')
            ->willReturn(null);
        $this->apiHelper->expects($this->never())->method('sendRequest');
        $this->plugin->afterPlace($this->orderManagementInterface, $order);
    }


    /**
     * @test
     */
    public function testFalseDecider()
    {
        $this->decider->expects($this->once())->method('isNonBoltTrackingEnabled')->willReturn(false);
        $this->quoteRepository->expects($this->never())->method('get');
        $this->apiHelper->expects($this->never())->method('sendRequest');
        $order = $this->createMock(Order::class);
        $this->plugin->afterPlace($this->orderManagementInterface, $order);
    }

    /**
     * @test
     */
    public function testExecuteBoltOrder()
    {
        $this->decider->expects($this->once())->method('isNonBoltTrackingEnabled')->willReturn(true);
        $payment = $this->createMock(Payment::class);
        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn(\Bolt\Boltpay\Model\Payment::METHOD_CODE);
        $order = $this->getMockBuilder(Order::class)
            ->setMethods(['getPayment'])
            ->disableOriginalConstructor()
            ->getMock();
        $order->expects($this->once())
            ->method('getPayment')
            ->willReturn($payment);
        $this->apiHelper->expects($this->never())->method('sendRequest');
        $this->plugin->afterPlace($this->orderManagementInterface, $order);
    }

    /**
     * @test
     */
    public function testExecuteBuildCartError()
    {
        $this->decider->expects($this->once())->method('isNonBoltTrackingEnabled')->willReturn(true);
        $order = $this->createMock(Order::class);
        $item = $this->createMock(Item::class);
        $quote = $this->createMock(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([$item]);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn(self::EMAIL);
        $customer->method('getFirstname')->willReturn(self::FIRST_NAME);
        $customer->method('getLastname')->willReturn(self::LAST_NAME);
        $quote->method('getCustomer')->willReturn($customer);
        $address = $this->createMock(Address::class);
        $address->method('getFirstname')->willReturn(self::FIRST_NAME);
        $address->method('getLastname')->willReturn(self::LAST_NAME);
        $quote->method('getShippingAddress')->willReturn($address);
        $this->quoteRepository->expects($this->once())
            ->method('get')
            ->willReturn($quote);
        $this->cartHelper->method('buildCartFromQuote')->willReturnCallback(function () {
            trigger_error("Error", E_ERROR);
        });
        $this->dataObject->expects($this->never())->method("setApiData");
        $this->apiHelper->expects($this->never())->method('buildRequest');
        $this->apiHelper->expects($this->never())->method('sendRequest');

        $this->plugin->afterPlace($this->orderManagementInterface, $order);

        // assert that an error was not thrown by the observer
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function testExecuteBuildCartException()
    {
        $this->decider->expects($this->once())->method('isNonBoltTrackingEnabled')->willReturn(true);
        $order = $this->createMock(Order::class);
        $item = $this->createMock(Item::class);
        $quote = $this->createMock(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([$item]);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn(self::EMAIL);
        $customer->method('getFirstname')->willReturn(self::FIRST_NAME);
        $customer->method('getLastname')->willReturn(self::LAST_NAME);
        $quote->method('getCustomer')->willReturn($customer);
        $address = $this->createMock(Address::class);
        $address->method('getFirstname')->willReturn(self::FIRST_NAME);
        $address->method('getLastname')->willReturn(self::LAST_NAME);
        $quote->method('getShippingAddress')->willReturn($address);
        $this->quoteRepository->expects($this->once())
            ->method('get')
            ->willReturn($quote);
        $this->cartHelper->method('buildCartFromQuote')->willThrowException(new Exception());
        $this->dataObject->expects($this->never())->method("setApiData");
        $this->apiHelper->expects($this->never())->method('buildRequest');
        $this->apiHelper->expects($this->never())->method('sendRequest');

        $this->plugin->afterPlace($this->orderManagementInterface, $order);

        // assert that an error was not thrown by the observer
        $this->assertTrue(true);
    }

    public function testExecuteShipmentsNotSet()
    {
        $this->decider->expects($this->once())->method('isNonBoltTrackingEnabled')->willReturn(true);
        $order = $this->getMockBuilder("BoltOrder")
            ->setMethods(['getPayment', 'getQuoteId', 'getStoreId', 'setBoltTransactionReference', 'save'])
            ->getMock();
        $payment = $this->createMock(Payment::class);
        $item = $this->createMock(Item::class);
        $quote = $this->createMock(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([$item]);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn(self::EMAIL);
        $customer->method('getFirstname')->willReturn(self::FIRST_NAME);
        $customer->method('getLastname')->willReturn(self::LAST_NAME);
        $quote->method('getCustomer')->willReturn($customer);
        $address = $this->createMock(Address::class);
        $address->method('getFirstname')->willReturn(self::FIRST_NAME);
        $address->method('getLastname')->willReturn(self::LAST_NAME);
        $quote->method('getShippingAddress')->willReturn($address);

        $this->quoteRepository->expects($this->once())
            ->method('get')
            ->willReturn($quote);
        $order->expects($this->once())
            ->method('getPayment')
            ->willReturn($payment);
        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn(self::PAYMENT_METHOD);

        $cartMissingPhone = self::CART;
        unset($cartMissingPhone['shipments']);
        $this->cartHelper->method('buildCartFromQuote')->willReturn($cartMissingPhone);
        $expectedOrderData = [
            'cart' => $cartMissingPhone,
            'user_identifier' => [
                'email' => self::EMAIL,
                'phone' => null,
            ],
            'user_identity' => [
                'first_name' => self::FIRST_NAME,
                'last_name' => self::LAST_NAME,
            ],
            'payment_method' => self::PAYMENT_METHOD,
        ];

        $this->dataObject->expects($this->once())
            ->method("setApiData")
            ->with($expectedOrderData);

        $this->apiHelper->expects($this->once())->method('buildRequest')->willReturn(new Request());
        $response = new Response();
        $response->setResponse(json_decode('{"reference": "ABCD-EFGH-1234"}'));
        $this->apiHelper->expects($this->once())->method('sendRequest')->willReturn($response);

        $this->plugin->afterPlace($this->orderManagementInterface, $order);
    }
}
