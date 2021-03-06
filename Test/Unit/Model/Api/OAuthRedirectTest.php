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
 *
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Api\Data\ExternalCustomerEntityInterface;
use Bolt\Boltpay\Api\ExternalCustomerEntityRepositoryInterface as ExternalCustomerEntityRepository;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider as DeciderHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\SSOHelper;
use Bolt\Boltpay\Model\Api\OAuthRedirect;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Sales\Api\OrderRepositoryInterface as OrderRepository;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\OAuthRedirect
 */
class OAuthRedirectTest extends BoltTestCase
{
    /**
     * @var Response|MockObject
     */
    private $response;

    /**
     * @var DeciderHelper|MockObject
     */
    private $deciderHelper;

    /**
     * @var SSOHelper|MockObject
     */
    private $ssoHelper;

    /**
     * @var LogHelper|MockObject
     */
    private $logHelper;

    /**
     * @var CartHelper|MockObject
     */
    private $cartHelper;

    /**
     * @var ExternalCustomerEntityRepository|MockObject
     */
    private $externalCustomerEntityRepository;

    /**
     * @var CustomerRepository|MockObject
     */
    private $customerRepository;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var CustomerSession|MockObject
     */
    private $customerSession;

    /**
     * @var CustomerInterfaceFactory|MockObject
     */
    private $customerInterfaceFactory;

    /**
     * @var CustomerFactory|MockObject
     */
    private $customerFactory;

    /**
     * @var Url|MockObject
     */
    private $url;

    /**
     * @var Bugsnag|MockObject
     */
    private $bugsnag;

    /**
     * @var OrderRepository|MockObject
     */
    private $orderRepository;

    /**
     * @var OAuthRedirect|MockObject
     */
    private $currentMock;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        $this->response = $this->createMock(Response::class);
        $this->deciderHelper = $this->createMock(DeciderHelper::class);
        $this->ssoHelper = $this->createMock(SSOHelper::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->externalCustomerEntityRepository = $this->createMock(ExternalCustomerEntityRepository::class);
        $this->customerRepository = $this->createMock(CustomerRepository::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->customerInterfaceFactory = $this->createMock(CustomerInterfaceFactory::class);
        $this->customerFactory = $this->createMock(CustomerFactory::class);
        $this->url = $this->createMock(Url::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->currentMock = $this->getMockBuilder(OAuthRedirect::class)
            ->setMethods()
            ->setConstructorArgs([
                $this->response,
                $this->deciderHelper,
                $this->ssoHelper,
                $this->logHelper,
                $this->cartHelper,
                $this->externalCustomerEntityRepository,
                $this->customerRepository,
                $this->storeManager,
                $this->customerSession,
                $this->customerInterfaceFactory,
                $this->customerFactory,
                $this->url,
                $this->bugsnag,
                $this->orderRepository
            ])
            ->getMock();
    }

    /**
     * @test
     */
    public function login_throwsNoSuchEntityException_ifSSONotEnabled()
    {
        $this->deciderHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(false);
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Request does not match any route.');
        $this->currentMock->login();
    }

    /**
     * @test
     */
    public function login_throwsWebapiException_ifCodeIsEmpty()
    {
        $this->deciderHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Bad Request');
        $this->currentMock->login('', 'scope', 'state', '');
    }

    /**
     * @test
     */
    public function login_throwsWebapiException_ifScopeIsEmpty()
    {
        $this->deciderHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Bad Request');
        $this->currentMock->login('code', '', 'state', '');
    }

    /**
     * @test
     */
    public function login_throwsWebapiException_ifStateIsEmpty()
    {
        $this->deciderHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Bad Request');
        $this->currentMock->login('code', 'scope', '', '');
    }

    /**
     * @test
     */
    public function login_throwsWebapiException_ifTokenExchangeReturnsString()
    {
        $this->deciderHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $this->ssoHelper->expects(static::once())->method('getOAuthConfiguration')->willReturn(['clientid', 'clientsecret', 'boltpublickey']);
        $this->ssoHelper->expects(static::once())->method('exchangeToken')->willReturn('empty response');
        $this->bugsnag->expects(static::once())->method('notifyError')->with('OAuthRedirect', 'empty response');
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Internal Server Error');
        $this->currentMock->login('code', 'scope', 'state', '');
    }

    /**
     * @test
     */
    public function login_throwsWebapiException_ifParseAndValidateTokenFailed()
    {
        $this->deciderHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $this->ssoHelper->expects(static::once())->method('getOAuthConfiguration')->willReturn(['clientid', 'clientsecret', 'boltpublickey']);
        $this->ssoHelper->expects(static::once())->method('exchangeToken')->willReturn((object) ['access_token' => 'test access token', 'id_token' => 'test id token']);
        $this->ssoHelper->expects(static::once())->method('parseAndValidateJWT')->willReturn('test string');
        $this->bugsnag->expects(static::once())->method('notifyError')->with('OAuthRedirect', 'test string');
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Internal Server Error');
        $this->currentMock->login('code', 'scope', 'state', '');
    }

    /**
     * @test
     */
    public function login_notifiesBugsnag_ifExternalCustomerEntityIsFoundButCustomerIsNot()
    {
        $this->deciderHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $this->ssoHelper->expects(static::once())->method('getOAuthConfiguration')->willReturn(['clientid', 'clientsecret', 'boltpublickey']);
        $this->ssoHelper->expects(static::once())->method('exchangeToken')->willReturn((object) ['access_token' => 'test access token', 'id_token' => 'test id token']);
        $this->ssoHelper->expects(static::once())->method('parseAndValidateJWT')->willReturn([
            'sub'            => 'abc',
            'first_name'     => 'first',
            'last_name'      => 'last',
            'email'          => 't@t.com',
            'email_verified' => true
        ]);
        $store = $this->createMock(StoreInterface::class);
        $this->storeManager->expects(static::exactly(2))->method('getStore')->willReturn($store);
        $store->expects(static::once())->method('getWebsiteId')->willReturn(1);
        $store->expects(static::once())->method('getId')->willReturn(1);
        $externalCustomerEntity = $this->createMock(ExternalCustomerEntityInterface::class);
        $this->externalCustomerEntityRepository->expects(static::once())->method('getByExternalID')->with('abc')->willReturn($externalCustomerEntity);
        $externalCustomerEntity->expects(static::exactly(2))->method('getCustomerID')->willReturn(2);
        $this->customerRepository->expects(static::once())->method('getById')->with(2)->willReturn(null);
        $this->bugsnag->expects(static::once())->method('notifyError')->with('OAuthRedirect', 'external customer entity abc linked to nonexistent customer 2');
        $this->setUpPrivateMethods();
        $this->currentMock->login('code', 'scope', 'state', '');
    }

    /**
     * @test
     */
    public function login_throwsWebapiException_ifSpecificConditionsAreMet()
    {
        $this->deciderHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $this->ssoHelper->expects(static::once())->method('getOAuthConfiguration')->willReturn(['clientid', 'clientsecret', 'boltpublickey']);
        $this->ssoHelper->expects(static::once())->method('exchangeToken')->willReturn((object) ['access_token' => 'test access token', 'id_token' => 'test id token']);
        $this->ssoHelper->expects(static::once())->method('parseAndValidateJWT')->willReturn(['sub' => 'abc', 'email' => 't@t.com', 'email_verified' => false]);
        $store = $this->createMock(StoreInterface::class);
        $this->storeManager->expects(static::exactly(2))->method('getStore')->willReturn($store);
        $store->expects(static::once())->method('getWebsiteId')->willReturn(1);
        $store->expects(static::once())->method('getId')->willReturn(1);
        $this->externalCustomerEntityRepository->expects(static::once())->method('getByExternalID')->with('abc')->willReturn(null);
        $customerInterface = $this->createMock(CustomerInterface::class);
        $this->customerRepository->expects(static::once())->method('get')->with('t@t.com', 1)->willReturn($customerInterface);
        $this->bugsnag->expects(static::once())->method('notifyError')->with('OAuthRedirect', 'customer with email t@t.com found but email is not verified');
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Internal Server Error');
        $this->currentMock->login('code', 'scope', 'state', '');
    }

    /**
     * @test
     */
    public function login_throwsWebapiException_ifExceptionIsThrownWhenTryingToLogin()
    {
        $this->deciderHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $this->ssoHelper->expects(static::once())->method('getOAuthConfiguration')->willReturn(['clientid', 'clientsecret', 'boltpublickey']);
        $this->ssoHelper->expects(static::once())->method('exchangeToken')->willReturn((object) ['access_token' => 'test access token', 'id_token' => 'test id token']);
        $this->ssoHelper->expects(static::once())->method('parseAndValidateJWT')->willReturn(['sub' => 'abc', 'email' => 't@t.com', 'email_verified' => true]);
        $store = $this->createMock(StoreInterface::class);
        $this->storeManager->expects(static::exactly(2))->method('getStore')->willReturn($store);
        $store->expects(static::once())->method('getWebsiteId')->willReturn(1);
        $store->expects(static::once())->method('getId')->willReturn(1);
        $this->externalCustomerEntityRepository->expects(static::once())->method('getByExternalID')->with('abc')->willReturn(null);
        $customerInterface = $this->createMock(CustomerInterface::class);
        $this->customerRepository->expects(static::once())->method('get')->with('t@t.com', 1)->willReturn($customerInterface);
        $customerInterface->expects(static::once())->method('getId')->willReturn(1);
        $this->externalCustomerEntityRepository->expects(static::once())->method('upsert')->with('abc', 1)->willThrowException(new Exception('test exception'));
        $this->bugsnag->expects(static::once())->method('notifyError')->with('OAuthRedirect', 'test exception');
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Internal Server Error');
        $this->currentMock->login('code', 'scope', 'state', '');
    }

    /**
     * @test
     */
    public function login_isSuccessful()
    {
        $this->deciderHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $this->ssoHelper->expects(static::once())->method('getOAuthConfiguration')->willReturn(['clientid', 'clientsecret', 'boltpublickey']);
        $this->ssoHelper->expects(static::once())->method('exchangeToken')->willReturn((object) ['access_token' => 'test access token', 'id_token' => 'test id token']);
        $this->ssoHelper->expects(static::once())->method('parseAndValidateJWT')->willReturn([
            'sub'            => 'abc',
            'first_name'     => 'first',
            'last_name'      => 'last',
            'email'          => 't@t.com',
            'email_verified' => true
        ]);
        $store = $this->createMock(StoreInterface::class);
        $this->storeManager->expects(static::exactly(2))->method('getStore')->willReturn($store);
        $store->expects(static::once())->method('getWebsiteId')->willReturn(1);
        $store->expects(static::once())->method('getId')->willReturn(1);
        $this->externalCustomerEntityRepository->expects(static::once())->method('getByExternalID')->with('abc')->willThrowException(new NoSuchEntityException());
        $this->customerRepository->expects(static::once())->method('get')->with('t@t.com', 1)->willThrowException(new NoSuchEntityException());
        $this->setUpPrivateMethods();
        $this->currentMock->login('code', 'scope', 'state', '');
    }

    private function setUpPrivateMethods()
    {
        $customerInterface = $this->createMock(CustomerInterface::class);
        $this->customerInterfaceFactory->expects(static::once())->method('create')->willReturn($customerInterface);
        $customerInterface->expects(static::once())->method('setWebsiteId')->with(1);
        $customerInterface->expects(static::once())->method('setStoreId')->with(1);
        $customerInterface->expects(static::once())->method('setFirstName')->with('first');
        $customerInterface->expects(static::once())->method('setLastName')->with('last');
        $customerInterface->expects(static::once())->method('setEmail')->with('t@t.com');
        $customerInterface->expects(static::once())->method('setConfirmation')->with(null);
        $this->customerRepository->expects(static::once())->method('save')->with($customerInterface)->willReturn($customerInterface);
        $customerInterface->expects(static::once())->method('getId')->willReturn(3);
        $this->externalCustomerEntityRepository->expects(static::once())->method('upsert')->with('abc', 3);
        $customer = $this->createMock(Customer::class);
        $this->customerFactory->expects(static::once())->method('create')->willReturn($customer);
        $customer->expects(static::once())->method('load')->with(3)->willReturnSelf();
        $this->customerSession->expects(static::once())->method('setCustomerAsLoggedIn')->with($customer);
        $this->url->expects(static::once())->method('getAccountUrl')->willReturn('http://account.url');
        $this->response->expects(static::once())->method('setRedirect')->with('http://account.url')->willReturnSelf();
        $this->response->expects(static::once())->method('sendResponse');
    }
}
