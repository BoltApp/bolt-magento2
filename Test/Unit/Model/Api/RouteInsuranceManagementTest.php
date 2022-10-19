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

use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use \Bolt\Boltpay\Model\Api\RouteInsuranceManagement;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Webapi\Exception as WebapiException;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Module\Manager;
use Magento\Framework\Serialize\SerializerInterface as Serialize;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Quote\Model\QuoteRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\RouteInsuranceManagement
 */
class RouteInsuranceManagementTest extends BoltTestCase
{
    const TRACE_ID_HEADER = 'KdekiEGku3j1mU21Mnsx5g==';
    const SECRET = '42425f51e0614482e17b6e913d74788eedb082e2e6f8067330b98ffa99adc809';
    const APIKEY = '3c2d5104e7f9d99b66e1c9c550f6566677bf81de0d6f25e121fdb57e47c2eafc';

    /**
     * @var Bugsnag|MockObject
     */
    private $bugsnag;

    /**
     * @var Manager|MockObject
     */
    private $moduleManager;

    /**
     * @var Serialize|MockObject
     */
    private $serializer;

    /**
     * @var CartHelper|MockObject
     */
    private $cartHelper;

    /**
     * @var QuoteRepository|MockObject
     */
    private $quoteRepository;

    /**
     * @var Response|MockObject
     */
    private $response;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var ScopeInterface
     */
    private $storeId;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var RouteInsuranceManagement
     */
    private $routeInsuranceManagement;

    /**
     * @var RouteInsuranceManagement|MockObject
     */
    private $currentMock;

    /**
     * @inheritdoc
     */
    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->routeInsuranceManagement = $this->objectManager->create(RouteInsuranceManagement::class);
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

    protected function initCurrentMock()
    {
        $this->currentMock = $this->getMockBuilder(RouteInsuranceManagement::class)
            ->setConstructorArgs(
                [
                    $this->createMock(Response::class),
                    $this->createMock(Bugsnag::class),
                    $this->createMock(Manager::class),
                    $this->createMock(Serialize::class),
                    $this->createMock(CartHelper::class),
                    $this->createMock(QuoteRepository::class),
                    $this->createMock(CheckoutSession::class),
                ]
            )
            ->enableOriginalConstructor()
            ->disableProxyingToOriginalMethods()
            ->setMethods(['isModuleEnabled', 'setRouteIsInsuredToQuote', 'responseBuilder']);
        $this->currentMock = $this->currentMock->getMock();

        TestHelper::setProperty($this->currentMock, 'response', $this->response);
        TestHelper::setProperty($this->currentMock, 'bugsnag', $this->bugsnag);
        TestHelper::setProperty($this->currentMock, 'moduleManager', $this->moduleManager);
        TestHelper::setProperty($this->currentMock, 'serializer', $this->serializer);
        TestHelper::setProperty($this->currentMock, 'cartHelper', $this->cartHelper);
        TestHelper::setProperty($this->currentMock, 'quoteRepository', $this->quoteRepository);
        TestHelper::setProperty($this->currentMock, 'checkoutSession', $this->checkoutSession);
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
     * @throws WebapiException
     */
    public function execute_willReturnHttpNotFoundCode_ifRouteModuleIsNotInstalled()
    {
        $this->createRequest([]);
        $quote = TestUtils::createQuote();
        $this->initCurrentMock();
        $this->currentMock->method('isModuleEnabled')->willReturn(false);
        $this->routeInsuranceManagement->execute($quote->getID(), true);
        $response = json_decode(TestHelper::getProperty($this->routeInsuranceManagement, 'response')->getBody(), true);
        $this->assertEquals(
            [
                'message' => sprintf("%s is not installed on merchant's site", RouteInsuranceManagement::ROUTE_MODULE_NAME)
            ],
            $response
        );
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_willThroughException_ifQuoteIsNotFound()
    {
        $this->createRequest([]);
        $this->initCurrentMock();
        $this->currentMock->method('isModuleEnabled')->willReturn(true);
        $this->expectException(WebApiException::class);
        $this->routeInsuranceManagement->execute('11111', true);
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_returnsSuccessMessageWithGrandTotal_ifEverythingSucceeds()
    {
        $quote = TestUtils::createQuote(
            [
                'grand_total' => 100
            ]
        );
        $this->createRequest([]);
        $this->initCurrentMock();
        $this->currentMock->method('isModuleEnabled')->willReturn(true);
        $this->routeInsuranceManagement->execute($quote->getID(), true);
        $response = json_decode(TestHelper::getProperty($this->routeInsuranceManagement, 'response')->getBody(), true);
        $this->assertEquals(
            [
                'message' => 'Route insurance is enabled for quote',
                'grand_total' => 100
            ],
            $response
        );
    }
}
