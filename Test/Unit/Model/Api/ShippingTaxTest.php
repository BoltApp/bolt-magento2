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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Model\Api\Shipping;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Store\Model\Store;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Framework\Webapi\Rest\Request;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;

use Bolt\Boltpay\Exception\BoltException;
use Magento\TestFramework\ObjectManager;
use Bolt\Boltpay\Model\Api\ShippingTax;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\TestFramework\Helper\Bootstrap;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Bolt\Boltpay\Model\ResponseFactory as BoltResponseFactory;
use Bolt\Boltpay\Model\RequestFactory as BoltRequestFactory;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Bolt\Boltpay\Model\ErrorResponse;

/**
 * Class ShippingTaxTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\ShippingTax
 */
class ShippingTaxTest extends BoltTestCase
{
    const PARENT_QUOTE_ID = 1000;
    const IMMUTABLE_QUOTE_ID = 1001;
    const INCREMENT_ID = 100050001;
    const DISPLAY_ID = self::INCREMENT_ID;
    const STORE_ID = 1;
    const CURRENCY_CODE = 'USD';
    const EMAIL = 'integration@bolt.com';
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
     * @var ShippingTax
     */
    private $shippingTax;

    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->shippingTax = $this->objectManager->create(Shipping::class);

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
     * @test
     * that throwUnknownQuoteIdException would return localized exception
     *
     * @covers ::throwUnknownQuoteIdException
     */
    public function throwUnknownQuoteIdException()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(__('Unknown quote id: %1.', self::IMMUTABLE_QUOTE_ID)->render());
        TestHelper::invokeMethod(
            $this->shippingTax,
            'throwUnknownQuoteIdException',
            [self::IMMUTABLE_QUOTE_ID]
        );
    }

    /**
     * @test
     * that getQuoteById would return quote from cart helper
     *
     * @covers ::getQuoteById
     */
    public function getQuoteById()
    {
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        static::assertEquals($quoteId, $this->shippingTax->getQuoteById($quoteId)->getId());
    }

    /**
     * @test
     * that reformatAddressData would return address data includes region and unset empty values
     *
     * @covers ::reformatAddressData
     */
    public function reformatAddressData()
    {
        $addressData = [
            'region' => 'California',
            'country_code' => 'US',
            'postal_code' => '90210',
            'locality' => 'San Franciso',
            'street_address1' => '123 Sesame St.',
            'street_address2' => '',
            'email' => self::EMAIL,
            'company' => ''
        ];
        $expected = [
            'country_id' => 'US',
            'postcode' => '90210',
            'region' => 'California',
            'region_id' => 12,
            'city' => 'San Franciso',
            'street' => '123 Sesame St.',
            'email' => self::EMAIL
        ];
        $this->assertEquals($expected, $this->shippingTax->reformatAddressData($addressData));
    }


    /**
     * @test
     * that validateAddressData would validate email
     *
     * @covers ::validateAddressData
     */
    public function validateAddressData()
    {
        $addressData = [
            'email' => self::EMAIL
        ];
        $this->shippingTax->validateAddressData($addressData);
    }

    /**
     * @test
     * that validateEmail would call validateEmail from cart helper and return validated
     *
     * @covers ::validateEmail
     */
    public function validateEmail_valid()
    {
        $this->shippingTax->validateEmail(self::EMAIL);
    }

    /**
     * @test
     * that validateEmail throws BoltException with Invalid email message for invalid email input
     *
     * @covers ::validateEmail
     */
    public function validateEmail_invalid()
    {
        $invalidEmail = 'invalid email';
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(BoltErrorResponse::ERR_UNIQUE_EMAIL_REQUIRED);
        $this->expectExceptionMessage(__('Invalid email: %1', $invalidEmail)->render());

        $this->shippingTax->validateEmail($invalidEmail);
    }

    /**
     * @test
     * that loadQuote return quote
     *
     * @covers ::loadQuote
     */
    public function loadQuote_happyPath()
    {
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        $this->assertEquals($quote->getId(), $this->shippingTax->loadQuote($quoteId)->getId());
    }

    /**
     * @test
     * that loadQuote throw unknown quote id exception regarding get quote id return false
     *
     * @covers ::loadQuote
     */
    public function loadQuote_throwUnknownQuoteIdException()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(__('Unknown quote id: %1.', self::IMMUTABLE_QUOTE_ID)->render());
        $this->assertNull($this->shippingTax->loadQuote(self::IMMUTABLE_QUOTE_ID));
    }

    /**
     * @test
     * that execute throws web api exception
     *
     * @covers ::execute
     */
    public function execute_WebApiException()
    {
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        $cart = [
            'display_id' => self::DISPLAY_ID,
            'metadata' => [
                'immutable_quote_id' => $quoteId,
            ],
        ];

        $this->assertNull($this->shippingTax->execute($cart, []));
        $response = json_decode(TestHelper::getProperty($this->shippingTax, 'response')->getBody(), true);
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
     * @test
     * that handleRequest throws web api exception
     *
     * @covers ::handleRequest
     */
    public function handleRequest_WebApiException()
    {
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        $cart = [
            'display_id' => self::DISPLAY_ID,
            'metadata' => [
                'immutable_quote_id' => $quoteId,
            ],
        ];

        $this->expectException(WebapiException::class);

        $this->expectExceptionMessage('Precondition Failed');
        $this->shippingTax->handleRequest($cart, []);
    }

    /**
     * @test
     * that execute would throw bolt exception
     *
     * @covers ::execute
     */
    public function execute_BoltException()
    {
        $this->createRequest([]);
        $quote = TestUtils::createQuote(['store_id' => $this->storeId]);
        $quoteId = $quote->getId();
        $cart = [
            'display_id' => self::DISPLAY_ID,
            'order_reference' => $quoteId,
            'metadata' => [
                'immutable_quote_id' => $quoteId,
            ],
        ];
        $shipping_address = [
            'email' => 'invalid email',
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
            ShippingTax::class,
            'hookHelper'
        );
        $hookHelperProperty->setAccessible(true);
        $hookHelperProperty->setValue($this->shippingTax, $hookHelper);
        $this->assertNull($this->shippingTax->execute($cart, $shipping_address));
        $response = json_decode(TestHelper::getProperty($this->shippingTax, 'response')->getBody(), true);
        $this->assertEquals(
            [
                'status' => 'failure',
                'error' => [
                    'code' => ErrorResponse::ERR_UNIQUE_EMAIL_REQUIRED,
                    'message' => 'Invalid email: invalid email']
            ],
            $response
        );
    }

    /**
     * @test
     * that handleRequest throws web api exception
     *
     * @covers ::handleRequest
     */
    public function handleRequest_BoltException()
    {
        $quote = TestUtils::createQuote(['store_id' => $this->storeId]);
        $quoteId = $quote->getId();
        $cart = [
            'display_id' => self::DISPLAY_ID,
            'order_reference' => $quoteId,
            'metadata' => [
                'immutable_quote_id' => $quoteId,
            ],
        ];
        $shipping_address = [
            'email' => 'invalid email',
            'country_code' => 'US'
        ];

        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Precondition Failed');
        $this->shippingTax->handleRequest($cart, $shipping_address);
    }

    /**
     * @test
     * that execute return null
     *
     * @covers ::execute
     */
    public function execute_Exception()
    {
        $cart = [
            'display_id' => self::DISPLAY_ID,
            'metadata' => [
                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
            ],
        ];
        $this->assertNull($this->shippingTax->execute($cart, []));
    }

    /**
     * @test
     * that handleRequest throws exception
     *
     * @covers ::handleRequest
     */
    public function handleRequest_Exception()
    {
        $cart = [
            'display_id' => self::DISPLAY_ID,
            'metadata' => [
                'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
            ],
        ];

        $message = 'Unknown quote id: 1001.';
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($message);

        $this->shippingTax->handleRequest($cart, []);
    }

    /**
     * @test
     * that execute would return result regarding address data and shipping option
     *
     * @covers ::execute
     */
    public function execute_happyPath()
    {
        $this->createRequest([]);
        $quote = TestUtils::createQuote(['store_id' => $this->storeId]);
        $quoteId = $quote->getId();
        $cart = [
            'display_id' => self::DISPLAY_ID,
            'order_reference' => $quoteId,
            'metadata' => [
                'immutable_quote_id' => $quoteId,
            ],
        ];

        $shipping_address = [
            'region' => 'California',
            'country_code' => 'US',
            'postal_code' => '90210',
            'locality' => 'San Franciso',
            'street_address1' => '123 Sesame St.',
            'email' => self::EMAIL,
            'company' => 'Bolt'
        ];

        $hookHelper = $this->objectManager->create(HookHelper::class);
        $stubApiHelper = new stubBoltApiHelper();
        $apiHelperProperty = new \ReflectionProperty(
            HookHelper::class,
            'apiHelper'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($hookHelper, $stubApiHelper);

        $hookHelperProperty = new \ReflectionProperty(
            Shipping::class,
            'hookHelper'
        );
        $hookHelperProperty->setAccessible(true);
        $hookHelperProperty->setValue($this->shippingTax, $hookHelper);

        $shippingMethodManagement = $this->getMockBuilder(\Magento\Quote\Api\Data\ShippingMethodInterface::class)
                ->disableOriginalConstructor()
                ->setMethods([
                    'getCarrierTitle',
                    'setCarrierTitle',
                    'getCarrierCode',
                    'setCarrierCode',
                    'setMethodCode',
                    'getMethodCode',
                    'setMethodTitle',
                    'getMethodTitle',
                    'getAmount',
                    'setAmount',
                    'getBaseAmount',
                    'setBaseAmount',
                    'getAvailable',
                    'setAvailable',
                    'getExtensionAttributes',
                    'setExtensionAttributes',
                    'setErrorMessage',
                    'getErrorMessage',
                    'getPriceExclTax',
                    'setPriceExclTax',
                    'getPriceInclTax',
                    'setPriceInclTax'
                ])
                ->getMock();
        $shippingMethodManagement->method('getCarrierTitle')->willReturn('Carrier Title');
        $shippingMethodManagement->method('getMethodTitle')->willReturn('Method Title');
        $shippingMethodManagement->method('getCarrierCode')->willReturn('carrier_code');
        $shippingMethodManagement->method('getMethodCode')->willReturn('method_code');
        $shippingMethodManagement->method('getAmount')->willReturn(100);
        $shipmentEstimationInterface = $this->getMockBuilder(\Magento\Quote\Api\ShipmentEstimationInterface::class)
                ->disableOriginalConstructor()
                ->setMethods(['estimateByExtendedAddress'])
                ->getMock();
        $shipmentEstimationInterface->method('estimateByExtendedAddress')->willReturn([$shippingMethodManagement]);
        $shippingMethodManagement = new \ReflectionProperty(
            Shipping::class,
            'shippingMethodManagement'
        );
        $shippingMethodManagement->setAccessible(true);
        $shippingMethodManagement->setValue($this->shippingTax, $shipmentEstimationInterface);

        $result = $this->shippingTax->execute($cart, $shipping_address, ['some' => 'result']);

        $shippingOptionData = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $shippingOptionData
            ->setService('Carrier Title - Method Title')
            ->setCost(10000)
            ->setReference('carrier_code_method_code')
            ->setTaxAmount(0);
        $this->assertEquals(
            [$shippingOptionData],
            $result->getShippingOptions()
        );
    }

    /**
     * @test
     * @throws \ReflectionException
     */
    public function handleRequest_happyPath()
    {
        $this->createRequest([]);
        $quote = TestUtils::createQuote(['store_id' => $this->storeId]);
        $quoteId = $quote->getId();
        $cart = [
            'display_id' => self::DISPLAY_ID,
            'order_reference' => $quoteId,
            'metadata' => [
                'immutable_quote_id' => $quoteId,
            ],
        ];

        $shipping_address = [
            'region' => 'California',
            'country_code' => 'US',
            'postal_code' => '90210',
            'locality' => 'San Franciso',
            'street_address1' => '123 Sesame St.',
            'email' => self::EMAIL,
            'company' => 'Bolt'
        ];

        $hookHelper = $this->objectManager->create(HookHelper::class);
        $stubApiHelper = new stubBoltApiHelper();
        $apiHelperProperty = new \ReflectionProperty(
            HookHelper::class,
            'apiHelper'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($hookHelper, $stubApiHelper);

        $hookHelperProperty = new \ReflectionProperty(
            Shipping::class,
            'hookHelper'
        );
        $hookHelperProperty->setAccessible(true);
        $hookHelperProperty->setValue($this->shippingTax, $hookHelper);

        $shippingMethodManagement = $this->getMockBuilder(\Magento\Quote\Api\Data\ShippingMethodInterface::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getCarrierTitle',
                'setCarrierTitle',
                'getCarrierCode',
                'setCarrierCode',
                'setMethodCode',
                'getMethodCode',
                'setMethodTitle',
                'getMethodTitle',
                'getAmount',
                'setAmount',
                'getBaseAmount',
                'setBaseAmount',
                'getAvailable',
                'setAvailable',
                'getExtensionAttributes',
                'setExtensionAttributes',
                'setErrorMessage',
                'getErrorMessage',
                'getPriceExclTax',
                'setPriceExclTax',
                'getPriceInclTax',
                'setPriceInclTax'
            ])
            ->getMock();
        $shippingMethodManagement->method('getCarrierTitle')->willReturn('Carrier Title');
        $shippingMethodManagement->method('getMethodTitle')->willReturn('Method Title');
        $shippingMethodManagement->method('getCarrierCode')->willReturn('carrier_code');
        $shippingMethodManagement->method('getMethodCode')->willReturn('method_code');
        $shippingMethodManagement->method('getAmount')->willReturn(100);
        $shipmentEstimationInterface = $this->getMockBuilder(\Magento\Quote\Api\ShipmentEstimationInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['estimateByExtendedAddress'])
            ->getMock();
        $shipmentEstimationInterface->method('estimateByExtendedAddress')->willReturn([$shippingMethodManagement]);
        $shippingMethodManagement = new \ReflectionProperty(
            Shipping::class,
            'shippingMethodManagement'
        );
        $shippingMethodManagement->setAccessible(true);
        $shippingMethodManagement->setValue($this->shippingTax, $shipmentEstimationInterface);

        $result = $this->shippingTax->execute($cart, $shipping_address, ['some' => 'result']);

        $shippingOptionData = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $shippingOptionData
            ->setService('Carrier Title - Method Title')
            ->setCost(10000)
            ->setReference('carrier_code_method_code')
            ->setTaxAmount(0);
        $this->assertEquals(
            [$shippingOptionData],
            $result->getShippingOptions()
        );
    }

    /**
     * @test
     * that populateAddress would return reformatted address
     *
     * @covers ::populateAddress
     */
    public function populateAddress()
    {
        $addressData = [
            'region' => 'California',
            'country_code' => 'US',
            'postal_code' => '90210',
            'locality' => 'San Franciso',
            'street_address1' => '123 Sesame St.',
            'email' => self::EMAIL,
            'company' => 'Bolt'
        ];
        $addressDataReformatted = [
            'country_id' => 'US',
            'postcode' => '90210',
            'region' => 'California',
            'region_id' => 12,
            'city' => 'San Franciso',
            'street' => '123 Sesame St.',
            'email' => self::EMAIL,
            'company' => 'Bolt'
        ];

        $testAddressData = [
            'company'         => "",
            'country'         => "United States",
            'country_code'    => "US",
            'email'           => "na@bolt.com",
            'first_name'      => "IntegrationBolt",
            'last_name'       => "BoltTest",
            'locality'        => "New York",
            'phone'           => "8005550111",
            'postal_code'     => "10011",
            'region'          => "New York",
            'street_address1' => "228 7th Avenue",
            'street_address2' => "228 7th Avenue 2",
        ];
        $quote = TestUtils::createQuote();
        TestUtils::setAddressToQuote($testAddressData, $quote, 'shipping');
        TestUtils::setAddressToQuote($testAddressData, $quote, 'billing');
        $quote->save();
        TestHelper::setProperty($this->shippingTax, 'quote', $quote);

        $this->assertEquals([$quote->getShippingAddress(), $addressDataReformatted], $this->shippingTax->populateAddress($addressData));
    }
}
