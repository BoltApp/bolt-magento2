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
use Bolt\Boltpay\Model\Api\Debug;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\Webapi\Rest\Response;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Webapi\Rest\Request;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Model\ResponseFactory as BoltResponseFactory;
use Bolt\Boltpay\Model\RequestFactory as BoltRequestFactory;

/**
 * Class CreateOrderTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\Debug
 */
class DebugTest extends BoltTestCase
{
    const TRACE_ID_HEADER = 'KdekiEGku3j1mU21Mnsx5g==';
    const SECRET = '42425f51e0614482e17b6e913d74788eedb082e2e6f8067330b98ffa99adc809';
    const APIKEY = '3c2d5104e7f9d99b66e1c9c550f6566677bf81de0d6f25e121fdb57e47c2eafc';

    /**
     * @var Debug
     */
    private $debug;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var
     */
    private $websiteId;

    /**
     * @var ScopeInterface
     */
    private $storeId;

    /** @var Request */
    private $request;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->debug = $this->objectManager->create(Debug::class);
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

    protected function tearDownInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }

        $this->resetRequest();
        $this->resetResponse();
    }

    public function resetRequest()
    {
        if (!$this->request) {
            $this->request = $this->objectManager->get(Request::class);
        }

        $this->objectManager->removeSharedInstance(Request::class);
        $this->request = null;

    }

    public function resetResponse()
    {
        if (!$this->response) {
            $this->response = $this->objectManager->get(Response::class);
        }

        $this->objectManager->removeSharedInstance(Response::class);
        $this->response = null;
    }

    /**
     * @test
     * @covers ::debug
     */
    public function debug_successful()
    {
        $this->skipTestInUnitTestsFlow();
        $this->createRequest([]);
        $hookHelper = $this->objectManager->create(Hook::class);

        $stubApiHelper = new stubBoltApiHelper();
        $apiHelperProperty = new \ReflectionProperty(
            Hook::class,
            'apiHelper'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($hookHelper, $stubApiHelper);

        $orderHelperProperty = new \ReflectionProperty(
            Debug::class,
            'hookHelper'
        );
        $orderHelperProperty->setAccessible(true);
        $orderHelperProperty->setValue($this->debug, $hookHelper);
        $this->debug->debug();
        $response = json_decode(TestHelper::getProperty($this->debug, 'response')->getBody(), true);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('integration.debug', $response['event']);
        $this->assertNotNull($response['data']);
    }
}