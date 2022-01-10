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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Hook;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Model\Api\CreateOrder;
use Magento\TestFramework\Helper\Bootstrap;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Bolt\Boltpay\Model\ResponseFactory as BoltResponseFactory;
use Bolt\Boltpay\Model\RequestFactory as BoltRequestFactory;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class CreateOrderTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\CreateOrder
 */
class CreateOrderTest extends BoltTestCase
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
    const FLAT_SHIPPING_CODE = 'flatrate_flatrate';
    const FLAT_SHIPPING_COST = 5.00;

    const TRACE_ID_HEADER = 'KdekiEGku3j1mU21Mnsx5g==';
    const SECRET = '42425f51e0614482e17b6e913d74788eedb082e2e6f8067330b98ffa99adc809';
    const APIKEY = '3c2d5104e7f9d99b66e1c9c550f6566677bf81de0d6f25e121fdb57e47c2eafc';

    /**
     * @var Response
     */
    private $response;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    private $websiteId;

    /**
     * @var ScopeInterface
     */
    private $storeId;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CreateOrder
     */
    private $createOrder;

    /**
     * @inheritdoc
     */
    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->createOrder = $this->objectManager->create(CreateOrder::class);
        $this->resetRequest();
        $this->resetResponse();

        $store = $this->objectManager->get(StoreManagerInterface::class);
        $this->storeId = $store->getStore()->getId();

        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $this->websiteId = $websiteRepository->get('base')->getId();

        $encryptor = $this->objectManager->get(EncryptorInterface::class);
        $secret = $encryptor->encrypt(self::SECRET);
        $apikey = $encryptor->encrypt(self::APIKEY);

        $configData = [
            [
                'path' => 'payment/boltpay/signing_secret',
                'value' => $secret,
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => 'payment/boltpay/api_key',
                'value' => $apikey,
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path' => 'payment/boltpay/active',
                'value' => 1,
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
        ];
        TestUtils::setupBoltConfig($configData);
    }

    protected function tearDownInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }

        $this->resetRequest();
        $this->resetResponse();
    }

    private function resetRequest()
    {
        if (!$this->request) {
            $this->request = $this->objectManager->get(Request::class);
        }

        $this->objectManager->removeSharedInstance(Request::class);
        $this->request = null;
    }

    private function resetResponse()
    {
        if (!$this->response) {
            $this->response = $this->objectManager->get(Response::class);
        }

        $this->objectManager->removeSharedInstance(Response::class);
        $this->response = null;
    }

    /**
     * @test
     */
    public function validateMinimumAmount_valid()
    {
        $quote = TestUtils::createQuote();
        $this->createOrder->validateMinimumAmount($quote);
    }

    /**
     * @test
     * that validateMinimumAmount skips validation if quote checkout type is backoffice
     *
     * @covers ::validateMinimumAmount
     *
     * @throws BoltException from tested method
     */
    public function validateMinimumAmount_withBackofficeCheckoutType_skipsValidation()
    {
        $quote = TestUtils::createQuote();
        $quote->setBoltCheckoutType(CartHelper::BOLT_CHECKOUT_TYPE_BACKOFFICE);
        $this->createOrder->validateMinimumAmount($quote);
    }

    /**
     * @test
     */
    public function validateTotalAmount_valid()
    {
        $quote = TestUtils::createQuote(['grand_total' => 74]);
        $this->createOrder->validateTotalAmount($quote, $this->getTransaction());
    }

    /**
     * @test
     */
    public function validateTotalAmount_invalid()
    {
        $quote = TestUtils::createQuote(['grand_total' => 7402]);
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(\Bolt\Boltpay\Model\Api\CreateOrder::E_BOLT_CART_HAS_EXPIRED);
        $this->expectExceptionMessage('Total amount does not match.');
        $this->createOrder->validateTotalAmount($quote, $this->getTransaction());
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_invalidHookType()
    {
        $this->createOrder->execute(
            null,
            $this->getOrderTransaction(),
            self::CURRENCY
        );
        $response = json_decode(TestHelper::getProperty($this->createOrder, 'response')->getBody(), true);
        $this->assertEquals(
            [
                'status' => 'failure',
                'error' => [
                    [
                        'code' => CreateOrder::E_BOLT_GENERAL_ERROR,
                        'data' => [
                            ['reason' => 'Invalid hook type!']
                        ]
                    ]
                ]
            ],
            $response
        );
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_emptyOrder()
    {
        $this->createOrder->execute(
            'order.create',
            null,
            self::CURRENCY
        );
        $response = json_decode(TestHelper::getProperty($this->createOrder, 'response')->getBody(), true);
        $this->assertEquals(
            [
                'status' => 'failure',
                'error' => [
                    [
                        'code' => CreateOrder::E_BOLT_GENERAL_ERROR,
                        'data' => [
                            ['reason' => 'Missing order data.']
                        ]
                    ]
                ]
            ],
            $response
        );
    }

    /**
     * Request getter
     *
     * @param array $bodyParams
     *
     * @return \Magento\Framework\App\RequestInterface
     */
    public function createRequest($bodyParams)
    {
        if (!$this->request) {
            $this->request = $this->objectManager->get(Request::class);
        }

        $requestContent = json_encode($bodyParams);

        $computed_signature = base64_encode(hash_hmac('sha256', $requestContent, self::SECRET, true));

        $this->request->getHeaders()->clearHeaders();
        $this->request->getHeaders()->addHeaderLine('X-Bolt-Hmac-Sha256', $computed_signature);
        $this->request->getHeaders()->addHeaderLine('X-bolt-trace-id', self::TRACE_ID_HEADER);
        $this->request->getHeaders()->addHeaderLine('Content-Type', 'application/json');

        $this->request->setParams($bodyParams);
        $this->request->setContent($requestContent);
        $this->request->setMethod("POST");

        return $this->request;
    }

    /**
     * @test
     * @covers ::execute
     * @covers ::createOrder
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
        $this->createRequest([]);
        $quote = TestUtils::createQuote(['store_id' => $this->storeId]);
        $quoteId = $quote->getId();
        $order = TestUtils::createDumpyOrder([
            'quote_id'=> $quoteId,
            'increment_id' => '100000002'
        ]);
        $orderIncrementId = $order->getIncrementId();

        $hookHelper = $this->objectManager->create(Hook::class);
        $orderHelper = $this->getMockBuilder(OrderHelper::class)
            ->disableOriginalConstructor()
            ->setMethods(['prepareQuote','processExistingOrder'])
            ->getMock();
        $orderHelper->expects($this->any())->method('prepareQuote')->willReturn($quote);
        $orderHelper->expects($this->any())->method('processExistingOrder')->willReturn($order);
        $stubApiHelper = new stubBoltApiHelper();
        $apiHelperProperty = new \ReflectionProperty(
            Hook::class,
            'apiHelper'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($hookHelper, $stubApiHelper);

        $hookHelperProperty = new \ReflectionProperty(
            CreateOrder::class,
            'hookHelper'
        );
        $hookHelperProperty->setAccessible(true);
        $hookHelperProperty->setValue($this->createOrder, $hookHelper);

        $orderHelperProperty = new \ReflectionProperty(
            CreateOrder::class,
            'orderHelper'
        );
        $orderHelperProperty->setAccessible(true);
        $orderHelperProperty->setValue($this->createOrder, $orderHelper);

        $this->createOrder->execute(
            'order.create',
            [
                "token" => "adae3381970f8a96ddfea87b6cdbee5aa7c5dc49679f5105171ea22ce0c6766e",
                "cart" => [
                    "order_reference" => $orderIncrementId,
                    "display_id" => $orderIncrementId.' / '. $quoteId,
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
            ],
            self::CURRENCY
        );
        $response = json_decode(TestHelper::getProperty($this->createOrder, 'response')->getBody(), true);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals($order->getIncrementId(), $response['display_id']);
        TestUtils::cleanupSharedFixtures([$order]);
    }

    /**
     * @test
     * @covers ::execute
     * @covers ::createOrder
     */
    public function execute_webApiException()
    {
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();

        $this->createOrder->execute(
            'order.create',
            [
                "token" => "adae3381970f8a96ddfea87b6cdbee5aa7c5dc49679f5105171ea22ce0c6766e",
                "cart" => [
                    "order_reference" => self::ORDER_ID,
                    "display_id" => '000000123'.' / '. $quoteId,
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
            ],
            self::CURRENCY
        );
        $response = json_decode(TestHelper::getProperty($this->createOrder, 'response')->getBody(), true);
        $this->assertEquals(
            [
                'status' => 'failure',
                'error' => [
                    [
                        'code' => CreateOrder::E_BOLT_GENERAL_ERROR,
                        'data' => [
                            ['reason' => '6001: Precondition Failed']
                        ]
                    ]
                ]
            ],
            $response
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
        $this->createOrder->getOrderReference([]);
    }

    /**
     * @test
     * @covers ::getDisplayId
     */
    public function getDisplayId_exception()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('cart->display_id does not exist');
        $this->createOrder->getDisplayId([]);
    }

    /**
     * @test
     * @covers ::getReceivedUrl
     */
    public function getReceivedUrl()
    {
        $urlInterface = Bootstrap::getObjectManager()->create(UrlInterface::class);
        $quote = TestUtils::createQuote();
        $url = $this->createOrder->getReceivedUrl($quote);
        self::assertEquals($urlInterface->getUrl('boltpay/order/receivedurl', [
            '_secure' => true,
            'store_id' => $quote->getStoreId()
        ]), $url);
    }

    /**
     * @test
     * @covers ::isBackOfficeOrder
     */
    public function isBackOfficeOrder_true()
    {
        $quote = TestUtils::createQuote();
        $quote->setBoltCheckoutType(CartHelper::BOLT_CHECKOUT_TYPE_BACKOFFICE);
        self::assertTrue($this->createOrder->isBackOfficeOrder($quote));
    }

    /**
     * @test
     * @covers ::isBackOfficeOrder
     */
    public function isBackOfficeOrder_false()
    {
        $quote = TestUtils::createQuote();
        $quote->setBoltCheckoutType(CartHelper::BOLT_CHECKOUT_TYPE_MULTISTEP);
        self::assertFalse($this->createOrder->isBackOfficeOrder($quote));
    }

    /**
     * @test
     * @covers ::loadQuoteData
     */
    public function loadQuoteData()
    {
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        self::assertEquals($quoteId, $this->createOrder->loadQuoteData($quoteId)->getId());
    }

    /**
     * @test
     * @covers ::validateCartItems
     * @covers ::getCartItemsFromTransaction
     * @covers ::arrayDiff
     */
    public function validateCartItems_exception()
    {
        $quote = TestUtils::createQuote();
        $product = TestUtils::getSimpleProduct();
        $productSku = $product->getSku();
        $quote->addProduct($product, 1);
        $quote->save();
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(CreateOrder::E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED);
        $this->expectExceptionMessage('Cart data has changed. SKU: ["'.$productSku.'"]');
        $this->createOrder->validateCartItems($quote, json_decode('{"order":{"cart":{"items":{}}}}'));
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
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(CreateOrder::E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED);
        $this->expectExceptionMessage('Price does not match. Item sku: ' . $itemSku);

        $this->createOrder->validateItemPrice(
            $itemSku,
            740200,
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
        $quote = TestUtils::createQuote();
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(CreateOrder::E_BOLT_CART_HAS_EXPIRED);
        $this->expectExceptionMessage('Cart Tax mismatched.');
        $this->createOrder->validateTax(
            $quote,
            json_decode('{"order":{"cart":{"tax_amount":{"amount":20000000000}}}}')
        );
    }

    /**
     * @test
     * @covers ::validateShippingCost
     * @covers ::getShippingAmountFromTransaction
     */
    public function validateShippingCost_exception()
    {
        $storeCost = 0;
        $boltCost = 500;
        $quote = TestUtils::createQuote();
        $transaction = $this->getTransaction();
        $transaction->order->cart->shipping_amount->amount = 500;

        $this->expectException(BoltException::class);
        $this->expectExceptionCode(CreateOrder::E_BOLT_SHIPPING_EXPIRED);
        $this->expectExceptionMessage(
            'Shipping total has changed. Old value: ' . $boltCost . ', new value: ' . $storeCost
        );
        $this->createOrder->validateShippingCost($quote, $transaction);
    }

    /**
     * @test
     * @covers ::validateShippingCost
     * @covers ::getShippingAmountFromTransaction
     */
    public function validateShippingCost_virtual()
    {
        $quote = TestUtils::createQuote();
        $product = TestUtils::getVirtualProduct();
        $quote->addProduct($product, 1);
        $quote->save();
        $transaction = $this->getTransaction();
        $transaction->order->cart->shipping_amount->amount = 0;

        $this->createOrder->validateShippingCost(
            $quote,
            $transaction
        );
        TestUtils::cleanupSharedFixtures([$product]);
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

        $quote = TestUtils::createQuote();
        $product = TestUtils::getVirtualProduct();
        $quote->addProduct($product, 1);
        $quote->save();
        $transaction = $this->getTransaction();
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(CreateOrder::E_BOLT_SHIPPING_EXPIRED);
        $this->expectExceptionMessage(
            'Shipping total has changed. Old value: ' . $boltCost . ', new value: ' . $storeCost
        );
        $this->createOrder->validateShippingCost(
            $quote,
            $transaction
        );
        TestUtils::cleanupSharedFixtures([$product]);
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
