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

use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Model\Api\ShippingMethods as BoltShippingMethods;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Helper\MetricsClient;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Framework\Webapi\Rest\Request;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\TestFramework\ObjectManager;
use Bolt\Boltpay\Model\Api\ShippingMethods;
use Magento\TestFramework\Helper\Bootstrap;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Bolt\Boltpay\Model\ErrorResponse;

/**
 * Class ShippingMethodsTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\ShippingMethods
 */
class ShippingMethodsTest extends BoltTestCase
{
    const PARENT_QUOTE_ID = 1000;
    const IMMUTABLE_QUOTE_ID = 1001;
    const INCREMENT_ID = 100050001;
    const DISPLAY_ID = self::INCREMENT_ID;
    const STORE_ID = 1;
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
     * @var ShippingMethods
     */
    private $shippingMethod;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->shippingMethod = $this->objectManager->create(ShippingMethods::class);

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
     * @covers ::getShippingMethods
     * @test
     */
    public function getShippingMethods_emptyQuote()
    {
        $cart = [
            'display_id' => self::DISPLAY_ID,
            'metadata' => [
                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID
            ]
        ];
        $shippingAddress = [
            'street_address1' => 'test'
        ];
        $result = $this->shippingMethod->getShippingMethods($cart, $shippingAddress);
        // If another exception happens, the test will fail.
        $this->assertNull($result);
    }

    /**
     * @test
     * @covers ::getShippingMethods
     */
    public function getShippingMethods_fullAddressData()
    {
        $shippingMethodMock = $this->createPartialMock(
            ShippingMethods::class,
            ['getShippingAndTax']
        );
        $cart = [
            'display_id' => self::DISPLAY_ID,
            'order_reference' => self::PARENT_QUOTE_ID,
            'metadata' => [
                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
            ],
        ];
        $shippingAddress = [
            'company' => "",
            'country' => "United States",
            'country_code' => "US",
            'email' => "integration@bolt.com",
            'first_name' => "YevhenBolt",
            'last_name' => "BoltTest2",
            'locality' => "New York",
            'phone' => "2312311234",
            'postal_code' => "10001",
            'region' => "New York",
            'street_address1' => "228 5th Avenue",
            'street_address2' => "",
        ];
        $metricsClient = $this->createPartialMock(
            MetricsClient::class,
            ['getCurrentTime','processMetric']
        );
        TestHelper::setProperty($shippingMethodMock, 'metricsClient', $metricsClient);
        $metricsClient->method('getCurrentTime')->willReturnSelf();
        $metricsClient->method('processMetric')->withAnyParameters()->willReturnSelf();

        $shippingOptions = $this->getShippingOptions();
        $shippingMethodMock->method('getShippingAndTax')
            ->willReturn($shippingOptions);
        $result = $shippingMethodMock->getShippingMethods($cart, $shippingAddress);
        $this->assertEquals($shippingOptions, $result);
    }

    private function getShippingOptions()
    {
        $shippingOptionData = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $shippingOptionData
            ->setService('Flat Rate - Fixed')
            ->setCost(5600)
            ->setReference('flatrate_flatrate')
            ->setTaxAmount(0);

        $shippingOptionsData = new \Bolt\Boltpay\Model\Api\Data\ShippingOptions();
        $shippingOptionsData->setShippingOptions([$shippingOptionData]);
        return $shippingOptionsData;
    }

    /**
     * @test
     * @covers ::getShippingMethods
     */
    public function getShippingMethods_webApiException()
    {
        $quote = TestUtils::createQuote();
        $this->assertNull($this->shippingMethod->getShippingMethods(
            [
                'display_id' => self::DISPLAY_ID,
                'metadata' => [
                    'immutable_quote_id' => $quote->getId(),
                ]
            ],
            []
        ));
        $response = json_decode(TestHelper::getProperty($this->shippingMethod, 'response')->getBody(), true);
        $this->assertEquals(
            [
                'status' => 'failure',
                'error' => [
                    'code' => ErrorResponse::ERR_SERVICE, 'message' => 'Precondition Failed',]
            ],
            $response
        );
    }

    /**
     * @covers ::getShippingMethods
     * @test
     */
    public function getShippingMethods_mismatch()
    {
        $this->createRequest([]);
        $quote = TestUtils::createQuote(['store_id' => $this->storeId]);
        $product = TestUtils::getSimpleProduct();
        $productSku = $product->getSku();
        $quote->addProduct($product, 1);
        $quote->save();
        $quoteId = $quote->getId();
        $cart = [
            'display_id' => self::DISPLAY_ID,
            'items' => [
                [
                    'sku' => $productSku,
                    'quantity' => 1,
                    'total_amount' => 222
                ]
            ],
            'order_reference' => $quoteId,
            'metadata' => [
                'immutable_quote_id' => $quoteId,
            ],
        ];
        $shipping_address = [
            'email' => 'johnmc+testing@bolt.com',
            'country_code' => 'US'
        ];
        $shipping_option = null;
        $hookHelper = $this->objectManager->create(HookHelper::class);
        $stubApiHelper = new stubBoltApiHelper();
        $apiHelperProperty = new \ReflectionProperty(
            HookHelper::class,
            'apiHelper'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($hookHelper, $stubApiHelper);

        $hookHelperProperty = new \ReflectionProperty(
            ShippingMethods::class,
            'hookHelper'
        );
        $hookHelperProperty->setAccessible(true);
        $hookHelperProperty->setValue($this->shippingMethod, $hookHelper);
        $this->assertNull($this->shippingMethod->getShippingMethods($cart, $shipping_address));
        $response = json_decode(TestHelper::getProperty($this->shippingMethod, 'response')->getBody(), true);
        $this->assertEquals(
            [
                'status' => 'failure',
                'error' => [
                    'code' => 6103,
                    'message' => 'Your cart total has changed and needs to be revised. Please reload the page and checkout again.']
            ],
            $response
        );
    }

    /**
     * @test
     * @covers ::throwUnknownQuoteIdException
     */
    public function throwUnknownQuoteIdException()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(__('Unknown quote id: %1.', self::IMMUTABLE_QUOTE_ID)->render());
        self::invokeInaccessibleMethod(
            $this->shippingMethod,
            'throwUnknownQuoteIdException',
            [self::IMMUTABLE_QUOTE_ID]
        );
    }

    /**
     * @test
     * @covers ::applyExternalQuoteData
     */
    public function applyExternalQuoteData_thirdPartyRewards()
    {
        $amRewardsPoint = 100;
        $quote = TestUtils::createQuote();
        $quote->setAmrewardsPoint($amRewardsPoint);
        self::assertEquals(
            $amRewardsPoint,
            $this->shippingMethod->applyExternalQuoteData($quote)
        );
    }

    /**
     * @test
     * @covers ::doesDiscountApplyToShipping
     * @throws \ReflectionException
     */
    public function doesDiscountApplyToShipping()
    {
        $quote = TestUtils::createQuote();
        $quote->setAppliedRuleIds('1');
        $ruleFactory = $this->getMockBuilder(\Magento\SalesRule\Model\RuleFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create','load','getApplyToShipping'])
            ->getMock();
        $ruleFactory->method('create')->willReturnSelf();
        $ruleFactory->method('load')->willReturnSelf();
        $ruleFactory->method('getApplyToShipping')->willReturn(true);
        $shippingMethod = new \ReflectionProperty(
            ShippingMethods::class,
            'ruleFactory'
        );
        $shippingMethod->setAccessible(true);
        $shippingMethod->setValue($this->shippingMethod, $ruleFactory);
        $this->assertTrue(
            self::invokeInaccessibleMethod(
                $this->shippingMethod,
                'doesDiscountApplyToShipping',
                [$quote]
            )
        );
    }

    /**
     * @test
     * @covers ::getShippingOptions
     */
    public function getShippingOptions_error()
    {
        $quote = TestUtils::createQuote();
        $shippingAddress = [
            'company' => "",
            'country' => "United States",
            'country_code' => "US",
            'email' => "integration@bolt.com",
            'first_name' => "YevhenBolt",
            'last_name' => "BoltTest2",
            'locality' => "New York",
            'phone' => "2312311234",
            'postal_code' => "10001",
            'region' => "New York",
            'street_address1' => "228 5th Avenue",
            'street_address2' => "",
        ];

        $this->expectException(BoltException::class);
        $this->expectExceptionMessage('No Shipping Methods retrieved');
        $this->expectExceptionCode(BoltErrorResponse::ERR_SERVICE);

        $this->shippingMethod->getShippingOptions($quote, $shippingAddress);
    }

    /**
     * @test
     */
    public function getShippingOptions_virtual()
    {
        $quote = TestUtils::createQuote();
        $product = TestUtils::createVirtualProduct();
        $quote->addProduct($product, 1);
        $quote->setIsVirtual(true);
        $quote->save();
        $addressData = [
            'country_id' => 'US',
            'postcode' => '10001',
            'region' => 'New York',
            'city' => 'New York',
        ];
        $shippingOptionData = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $shippingOptionData
            ->setService(BoltShippingMethods::NO_SHIPPING_SERVICE)
            ->setCost(0)
            ->setReference(BoltShippingMethods::NO_SHIPPING_REFERENCE)
            ->setTaxAmount(0);

        $this->assertEquals([$shippingOptionData], $this->shippingMethod->getShippingOptions($quote, $addressData));
    }

    /**
     * @test
     * @covers ::checkCartItems
     */
    public function checkCartItems_noQuoteItems()
    {
        $quote = TestUtils::createQuote();
        TestHelper::setInaccessibleProperty($this->shippingMethod, 'quote', $quote);

        $this->expectException(BoltException::class);
        $this->expectExceptionCode(6103);
        $this->expectExceptionMessage('The cart is empty. Please reload the page and checkout again.');

        self::invokeInaccessibleMethod(
            $this->shippingMethod,
            'checkCartItems',
            [
                [
                    'items' => []
                ]
            ]
        );
    }

    /**
     * @test
     * @covers ::checkCartItems
     */
    public function checkCartItems_totalsMismatch()
    {
        $quote = TestUtils::createQuote();
        $product = TestUtils::getSimpleProduct();
        $quote->addProduct($product, 1);
        $quote->save();

        $cart = [
            'items' => [
                [
                    'sku' => $product->getSku(),
                    'quantity' => 1,
                    'total_amount' => 100
                ]
            ]
        ];
        TestHelper::setInaccessibleProperty($this->shippingMethod, 'quote', $quote);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionCode(6103);
        $this->expectExceptionMessage('Your cart total has changed and needs to be revised. Please reload the page and checkout again.');
        self::invokeInaccessibleMethod(
            $this->shippingMethod,
            'checkCartItems',
            [
                $cart
            ]
        );
    }

    /**
     * @test
     * @covers ::checkCartItems
     */
    public function checkCartItems_quantityMismatch()
    {
        $quote = TestUtils::createQuote();
        $product = TestUtils::getSimpleProduct();
        $quote->addProduct($product, 1);
        $quote->save();

        $cart = [
            'items' => [
                [
                    'sku' => $product->getSku(),
                    'quantity' => 2,
                    'total_amount' => 100
                ]
            ]
        ];
        TestHelper::setInaccessibleProperty($this->shippingMethod, 'quote', $quote);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionCode(6103);
        $this->expectExceptionMessage('The quantity of items in your cart has changed and needs to be revised. Please reload the page and checkout again.');
        self::invokeInaccessibleMethod(
            $this->shippingMethod,
            'checkCartItems',
            [
                $cart
            ]
        );
    }

    /**
     * Invoke a private method of an object.
     *
     * @param  object $object
     * @param  string $method
     * @param  array $args
     * @param  string|null $class
     * @return mixed
     * @throws \ReflectionException
     */
    private static function invokeInaccessibleMethod($object, $method, $args = [], $class = null)
    {
        if (is_null($class)) {
            $class = $object;
        }

        $method = new \ReflectionMethod($class, $method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}
