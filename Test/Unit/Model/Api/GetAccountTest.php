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

use Bolt\Boltpay\Helper\Hook;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\Api\GetAccount;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Exception;
use Bolt\Boltpay\Test\Unit\TestHelper;

/**
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\GetAccount
 */
class GetAccountTest extends BoltTestCase
{
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
     * @var GetAccount
     */
    private $getAccount;

    /**
     * @inheritdoc
     */
    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->getAccount = $this->objectManager->create(GetAccount::class);
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

        return $this->request;
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_throwsException_ifVerifySignatureFails()
    {
        $this->expectException(WebApiException::class);
        $this->expectExceptionMessage('Request is not authenticated.');
        $this->getAccount->execute('test@bolt.com');
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_throwsException_ifEmailIsEmpty()
    {
        $this->createRequest([]);
        $hookHelper = $this->objectManager->create(Hook::class);
        $stubApiHelper = new stubBoltApiHelper();
        $apiHelperProperty = new \ReflectionProperty(
            Hook::class,
            'apiHelper'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($hookHelper, $stubApiHelper);
        $hookHelperProperty = new \ReflectionProperty(
            GetAccount::class,
            'hookHelper'
        );
        $hookHelperProperty->setAccessible(true);
        $hookHelperProperty->setValue($this->getAccount, $hookHelper);
        $this->expectException(WebApiException::class);
        $this->expectExceptionMessage('Missing email in the request body.');
        $this->getAccount->execute('');
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_throwsException_ifEmailNotFound()
    {
        $this->createRequest([]);
        $hookHelper = $this->objectManager->create(Hook::class);
        $stubApiHelper = new stubBoltApiHelper();
        $apiHelperProperty = new \ReflectionProperty(
            Hook::class,
            'apiHelper'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($hookHelper, $stubApiHelper);
        $hookHelperProperty = new \ReflectionProperty(
            GetAccount::class,
            'hookHelper'
        );
        $hookHelperProperty->setAccessible(true);
        $hookHelperProperty->setValue($this->getAccount, $hookHelper);
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Customer not found with given email.');
        $this->getAccount->execute('emailnotfound@bolt.com');
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_throwsException_ifExceptionIsThrown()
    {
        $exception = new Exception();
        $this->createRequest([]);
        $hookHelper = $this->objectManager->create(Hook::class);
        $stubApiHelper = new stubBoltApiHelper();
        $apiHelperProperty = new \ReflectionProperty(
            Hook::class,
            'apiHelper'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($hookHelper, $stubApiHelper);
        $hookHelperProperty = new \ReflectionProperty(
            GetAccount::class,
            'hookHelper'
        );
        $hookHelperProperty->setAccessible(true);
        $hookHelperProperty->setValue($this->getAccount, $hookHelper);

        $customerRepository = $this->createMock(CustomerRepository::class);
        $customerRepository->expects(static::once())->method('get')->with('test@bolt.com', 1)->willThrowException($exception);
        $customerRepositoryProperty = new \ReflectionProperty(
            GetAccount::class,
            'customerRepository'
        );
        $customerRepositoryProperty->setAccessible(true);
        $customerRepositoryProperty->setValue($this->getAccount, $customerRepository);

        $this->expectException(WebApiException::class);
        $this->expectExceptionMessage('Internal Server Error');
        $this->getAccount->execute('test@bolt.com');
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_returnsCustomerId_ifEverythingSucceeds()
    {
        $customer = TestUtils::createCustomer($this->websiteId, $this->storeId, array(
            "street_address1" => "street",
            "street_address2" => "",
            "locality"        => "Los Angeles",
            "region"          => "California",
            'region_code'     => 'CA',
            'region_id'       => '12',
            "postal_code"     => "11111",
            "country_code"    => "US",
            "country"         => "United States",
            "name"            => "lastname firstname",
            "first_name"      => "firstname",
            "last_name"       => "lastname",
            "phone_number"    => "11111111",
            "email_address"   => "john@bolt.com",
        ));
        $this->createRequest([]);
        $hookHelper = $this->objectManager->create(Hook::class);
        $stubApiHelper = new stubBoltApiHelper();
        $apiHelperProperty = new \ReflectionProperty(
            Hook::class,
            'apiHelper'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($hookHelper, $stubApiHelper);
        $hookHelperProperty = new \ReflectionProperty(
            GetAccount::class,
            'hookHelper'
        );
        $hookHelperProperty->setAccessible(true);
        $hookHelperProperty->setValue($this->getAccount, $hookHelper);
        $this->getAccount->execute('john@bolt.com');
        $response = json_decode(TestHelper::getProperty($this->getAccount, 'response')->getBody(), true);
        $this->assertEquals(['id' => $customer->getId()], $response);
    }
}