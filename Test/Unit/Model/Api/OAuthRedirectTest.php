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

use Bolt\Boltpay\Helper\FeatureSwitch\Definitions;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\SSOHelper;
use Bolt\Boltpay\Model\Api\OAuthRedirect;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Quote\Model\Quote;

/**
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\OAuthRedirect
 */
class OAuthRedirectTest extends BoltTestCase
{
    /**
     * @var OAuthRedirect
     */
    private $oAuthRedirect;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->oAuthRedirect = $this->objectManager->create(OAuthRedirect::class);
    }

    /**
     * @test
     */
    public function login_throwsNoSuchEntityException_ifSSONotEnabled()
    {
        TestUtils::saveFeatureSwitch(
            Definitions::M2_ENABLE_BOLT_SSO,
            false
        );
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Request does not match any route.');
        $this->oAuthRedirect->login();
    }

    /**
     * @test
     */
    public function login_throwsWebapiException_ifCodeIsEmpty()
    {
        TestUtils::saveFeatureSwitch(
            Definitions::M2_ENABLE_BOLT_SSO,
            true
        );
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Bad Request');
        $this->oAuthRedirect->login('', 'scope', 'state', '');
    }

    /**
     * @test
     */
    public function login_throwsWebapiException_ifScopeIsEmpty()
    {
        TestUtils::saveFeatureSwitch(
            Definitions::M2_ENABLE_BOLT_SSO,
            true
        );
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Bad Request');
        $this->oAuthRedirect->login('code', '', 'state', '');
    }

    /**
     * @test
     */
    public function login_throwsWebapiException_ifStateIsEmpty()
    {
        TestUtils::saveFeatureSwitch(
            Definitions::M2_ENABLE_BOLT_SSO,
            true
        );
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Bad Request');
        $this->oAuthRedirect->login('code', 'scope', '', '');
    }

    /**
     * @test
     */
    public function login_throwsWebapiException_ifTokenExchangeReturnsString()
    {
        TestUtils::saveFeatureSwitch(
            Definitions::M2_ENABLE_BOLT_SSO,
            true
        );
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Internal Server Error');
        $this->oAuthRedirect->login('code', 'scope', 'state', '');
    }

    /**
     * @test
     */
    public function login_throwsWebapiException_ifParseAndValidateTokenFailed()
    {
        TestUtils::saveFeatureSwitch(
            Definitions::M2_ENABLE_BOLT_SSO,
            true
        );
        $ssoHelper = $this->createMock(SSOHelper::class);
        $ssoHelper->method('getOAuthConfiguration')->willReturn(['clientid', 'clientsecret', 'boltpublickey']);
        $ssoHelper->method('exchangeToken')->willReturn((object)['access_token' => 'test access token', 'id_token' => 'test id token']);
        $ssoHelper->method('parseAndValidateJWT')->willReturn('test string');

        TestHelper::setProperty($this->oAuthRedirect, 'ssoHelper', $ssoHelper);
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Internal Server Error');
        $this->oAuthRedirect->login('code', 'scope', 'state', '');
    }

    /**
     * @test
     */
    public function login_isSuccessful()
    {
        TestUtils::saveFeatureSwitch(
            Definitions::M2_ENABLE_BOLT_SSO,
            true
        );
        $ssoHelper = $this->createMock(SSOHelper::class);
        $ssoHelper->expects(static::once())->method('getOAuthConfiguration')->willReturn(['clientid', 'clientsecret', 'boltpublickey']);
        $ssoHelper->expects(static::once())->method('exchangeToken')->willReturn((object)['access_token' => 'test access token', 'id_token' => 'test id token']);
        $ssoHelper->expects(static::once())->method('parseAndValidateJWT')->willReturn([
            'sub' => 'abc',
            'first_name' => 'first',
            'last_name' => 'last',
            'email' => 't@t.com',
            'email_verified' => true
        ]);
        TestHelper::setProperty($this->oAuthRedirect, 'ssoHelper', $ssoHelper);
        $this->oAuthRedirect->login('code', 'scope', 'state', '');
        $this->assertEquals('t@t.com', TestHelper::getProperty($this->oAuthRedirect, 'customerSession')->getCustomer()->getEmail());
    }


    /**
     * @test
     */
    public function login_associatesCustomerWithQuote()
    {
        TestUtils::saveFeatureSwitch(
            Definitions::M2_ENABLE_BOLT_SSO,
            true
        );
        $ssoHelper = $this->createMock(SSOHelper::class);
        $ssoHelper->expects(static::once())->method('getOAuthConfiguration')->willReturn(['clientid', 'clientsecret', 'boltpublickey']);
        $ssoHelper->expects(static::once())->method('exchangeToken')->willReturn((object)['access_token' => 'test access token', 'id_token' => 'test id token']);
        $ssoHelper->expects(static::once())->method('parseAndValidateJWT')->willReturn([
            'sub' => 'abc',
            'first_name' => 'first',
            'last_name' => 'last',
            'email' => 't@t.com',
            'email_verified' => true
        ]);

        $cartHelper = $this->createMock(CartHelper::class);
        $cartHelper->expects(static::once())->method('getOrderByQuoteId')->willReturn(false);
        $testQuote = $this->objectManager->create(Quote::class);
        $cartHelper->expects(static::once())->method('getQuoteById')->willReturn($testQuote);
        $cartHelper->expects(static::once())->method('saveQuote');

        TestHelper::setProperty($this->oAuthRedirect, 'ssoHelper', $ssoHelper);
        $this->oAuthRedirect->login('code', 'scope', 'state', '222');
        $this->assertEquals('t@t.com', TestHelper::getProperty($this->oAuthRedirect, 'customerSession')->getCustomer()->getEmail());
    }
}
