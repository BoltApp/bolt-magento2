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

use Bolt\Boltpay\Api\ExternalCustomerEntityRepositoryInterface as ExternalCustomerEntityRepository;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider as DeciderHelper;
use Bolt\Boltpay\Helper\SSOHelper;
use Bolt\Boltpay\Model\Api\OAuthRedirect;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Response;
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
        $this->externalCustomerEntityRepository = $this->createMock(ExternalCustomerEntityRepository::class);
        $this->customerRepository = $this->createMock(CustomerRepository::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->customerInterfaceFactory = $this->createMock(CustomerInterfaceFactory::class);
        $this->customerFactory = $this->createMock(CustomerFactory::class);
        $this->url = $this->createMock(Url::class);
        $this->currentMock = $this->getMockBuilder(OAuthRedirect::class)
            ->setMethods()
            ->setConstructorArgs([
                $this->response,
                $this->deciderHelper,
                $this->ssoHelper,
                $this->externalCustomerEntityRepository,
                $this->customerRepository,
                $this->storeManager,
                $this->customerSession,
                $this->customerInterfaceFactory,
                $this->customerFactory,
                $this->url
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
        $this->currentMock->login('', 'scope', 'state');
    }

    /**
     * @test
     */
    public function login_throwsWebapiException_ifScopeIsEmpty()
    {
        $this->deciderHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Bad Request');
        $this->currentMock->login('code', '', 'state');
    }

    /**
     * @test
     */
    public function login_throwsWebapiException_ifStateIsEmpty()
    {
        $this->deciderHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Bad Request');
        $this->currentMock->login('code', 'scope', '');
    }

    /**
     * @test
     */
    public function login_throwsWebapiException_ifTokenExchangeReturnsNull()
    {
        $this->deciderHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $this->ssoHelper->expects(static::once())->method('getOAuthConfiguration')->willReturn([
            'clientID'      => 'clientid',
            'clientSecret'  => 'clientsecret',
            'boltPublicKey' => 'boltpublickey'
        ]);
        $this->ssoHelper->expects(static::once())->method('exchangeToken')->willReturn(null);
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Internal Server Error');
        $this->currentMock->login('code', 'scope', 'state');
    }

    /**
     * @test
     */
    public function login_throwsWebapiException_ifParseAndValidateTokenFailed()
    {
        $this->deciderHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $this->ssoHelper->expects(static::once())->method('getOAuthConfiguration')->willReturn([
            'clientID'      => 'clientid',
            'clientSecret'  => 'clientsecret',
            'boltPublicKey' => 'boltpublickey'
        ]);
        $this->ssoHelper->expects(static::once())->method('exchangeToken')->willReturn('token');
        $this->ssoHelper->expects(static::once())->method('parseAndValidateJWT')->willReturn(null);
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Internal Server Error');
        $this->currentMock->login('code', 'scope', 'state');
    }
}
