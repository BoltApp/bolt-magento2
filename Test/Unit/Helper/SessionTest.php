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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\Session;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Backend\Model\Session\Quote as AdminCheckoutSession;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\App\Cache;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;

/**
 * Class SessionTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper
 * @coversDefaultClass \Bolt\Boltpay\Helper\Session
 */
class SessionTest extends BoltTestCase
{
    const SESSION_ID = '1111';
    const QUOTE_ID = '1';
    const STORE_ID = '1';
    const CUSTOMER_ID = '1111';
    const BOLT_PARENT_QUOTE_ID = '1111';
    const FORM_KEY = '2222';

    /**
     * @var \Magento\Framework\TestFramework\Unit\Helper\ObjectManager
     */
    protected $objectManager;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->session = $this->objectManager->create(Session::class);
        $this->checkoutSession = $this->objectManager->create(\Magento\Checkout\Model\Session::class);
    }

    /**
     * @test
     * @dataProvider getCheckoutSession_withAreaIsNotAdminProvider
     */
    public function getCheckoutSession_withAreaIsNotAdmin($data)
    {
        $appState = $this->objectManager->create(State::class);
        $appState->setAreaCode($data['area']);
        TestHelper::setInaccessibleProperty($this->session, 'appState', $appState);
        $result = $this->session->getCheckoutSession();
        self::assertEquals($this->checkoutSession, $result);
    }

    public function getCheckoutSession_withAreaIsNotAdminProvider()
    {
        return [
            ['data' => ['area' => Area::AREA_GLOBAL]],
            ['data' => ['area' => Area::AREA_FRONTEND]],
            ['data' => ['area' => Area::AREA_DOC]],
            ['data' => ['area' => Area::AREA_CRONTAB]],
            ['data' => ['area' => Area::AREA_WEBAPI_SOAP]],
            ['data' => ['area' => Area::AREA_WEBAPI_REST]]
        ];
    }

    /**
     * @test
     */
    public function saveSession()
    {
        $this->session->saveSession(self::QUOTE_ID, $this->checkoutSession);
        self::assertEquals(
            [
                'sessionType' => 'frontend',
                'sessionID' => $this->checkoutSession->getSessionId()
            ],
            json_decode(
                TestHelper::getProperty($this->session, 'cache')->load(Session::BOLT_SESSION_PREFIX . self::QUOTE_ID),
                true
            )
        );
    }

    /**
     * @test
     */
    public function setSession_withSessionEmulationIsDisabled()
    {
        $result = TestHelper::invokeMethod($this->session, 'setSession', [$this->checkoutSession, self::SESSION_ID, self::STORE_ID]);
        $this->assertNull($result);
    }

    /**
     * @covers ::setSession
     * @test
     */
    public function setSession_withSessionEmulationIsEnabled()
    {
        $store = $this->objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();
        $configData = [
            [
                'path' => ConfigHelper::XML_PATH_API_EMULATE_SESSION,
                'value' => true,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $storeId
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        TestHelper::invokeMethod($this->session, 'setSession', [$this->checkoutSession, self::SESSION_ID, self::STORE_ID]);
        $this->assertEquals(self::SESSION_ID, $this->checkoutSession->getSessionId());
    }

    /**
     * @test
     * @covers ::replaceQuote
     */
    public function replaceQuote()
    {
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        TestHelper::invokeMethod($this->session, 'replaceQuote', [$quote]);
        self::assertEquals($quoteId, TestHelper::getProperty($this->session, 'checkoutSession')->getQuoteId());
    }

    /**
     * @test
     * @covers ::loadSession
     */
    public function loadSession_withAreaCodeIsNotWebApiRest()
    {
        $quote = TestUtils::createQuote();
        $appState = $this->objectManager->create(State::class);
        $appState->setAreaCode(Area::AREA_WEBAPI_SOAP);
        $quoteId = $quote->getId();
        TestHelper::setProperty($this->session, 'appState', $appState);
        $this->session->loadSession($quote);
        self::assertEquals($quoteId, TestHelper::getProperty($this->session, 'checkoutSession')->getQuoteId());
    }

    /**
     * @test
     * @covers ::loadSession
     */
    public function loadSession_withCacheIdentifierIsLoaded()
    {
        $quote = TestUtils::createQuote();
        $appState = $this->objectManager->create(State::class);
        $appState->setAreaCode(Area::AREA_WEBAPI_REST);
        $cache = $this->createPartialMock(
            Cache::class,
            ['save', 'load']
        );

        $cache->expects(self::once())->method('load')->willReturn('{"sessionType":"frontend","sessionID":"1111"}');
        TestHelper::setProperty($this->session, 'appState', $appState);
        TestHelper::setInaccessibleProperty($this->session, 'cache', $cache);
        $result = $this->session->loadSession($quote);

        $this->assertEquals('1111', $this->checkoutSession->getSessionId());
        $this->assertNull($result);
    }

    /**
     * @test
     * @covers ::loadSession
     */
    public function loadSession_withCacheIdentifierIsNotLoaded()
    {
        $quote = TestUtils::createQuote();
        $sesionId = $this->checkoutSession->getSessionId();
        $appState = $this->objectManager->create(State::class);
        $appState->setAreaCode(Area::AREA_WEBAPI_REST);

        $cache = $this->createPartialMock(
            Cache::class,
            ['save', 'load']
        );
        $cache->expects(self::once())->method('load')->willReturn(false);
        TestHelper::setProperty($this->session, 'appState', $appState);
        TestHelper::setInaccessibleProperty($this->session, 'cache', $cache);
        $result = $this->session->loadSession($quote);

        $this->assertEquals($sesionId, $this->checkoutSession->getSessionId());
        $this->assertNull($result);
    }

    /**
     * @test
     * that loadSession dispatches the restoreSessionData third party event when session data is present in the metadata
     *
     * @covers ::loadSession
     */
    public function loadSession_withEncryptedSessionDataInMetadata_dispatchesRestoreSessionDataThirdPartyEvent()
    {
        $quote = TestUtils::createQuote();
        $appState = $this->objectManager->create(State::class);
        $appState->setAreaCode(Area::AREA_WEBAPI_REST);
        $sessionData = [
            'idme_group' => 'nurse'
        ];
        $eventsForThirdPartyModules = $this->createMock(
            EventsForThirdPartyModules::class
        );
        $eventsForThirdPartyModules->method('runFilter')->will($this->returnArgument(1));

        $configHelper = $this->createPartialMock(
            ConfigHelper::class,
            ['isSessionEmulationEnabled', 'encrypt', 'decrypt']
        );
        $configHelper->expects(static::once())->method('decrypt')
            ->willReturn(json_encode($sessionData));
        $eventsForThirdPartyModules->expects(static::exactly(2))
            ->method('dispatchEvent')
            ->withConsecutive(
                ['restoreSessionData', $sessionData, $quote]
            );

        TestHelper::setInaccessibleProperty($this->session, 'eventsForThirdPartyModules', $eventsForThirdPartyModules);
        TestHelper::setInaccessibleProperty($this->session, 'appState', $appState);
        TestHelper::setInaccessibleProperty($this->session, 'configHelper', $configHelper);

        $this->session->loadSession(
            $quote,
            [
                Session::ENCRYPTED_SESSION_DATA_KEY => base64_encode(json_encode($sessionData))
            ]
        );
    }

    /**
     * @test
     * @covers ::setFormKey
     */
    public function setFormKey()
    {
        $expected = 'Formkey';
        $quote = TestUtils::createQuote();
        $cache = TestHelper::getProperty($this->session, 'cache');
        $cache->save($expected, Session::BOLT_SESSION_PREFIX_FORM_KEY . $quote->getId(), [], 86400);
        $this->session->setFormKey($quote);
        $result = TestHelper::getProperty($this->session, 'formKey')->getFormKey();
        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function cacheFormKey()
    {
        $quote = TestUtils::createQuote();
        $this->session->cacheFormKey($quote);
        self::assertEquals(
            TestHelper::getProperty($this->session, 'formKey')->getFormKey(),
            TestHelper::getProperty($this->session, 'cache')->load(Session::BOLT_SESSION_PREFIX_FORM_KEY . $quote->getId())
        );
    }

    /**
     * @test
     */
    public function getCheckoutSession_withAreaIsAdmin()
    {
        $appState = $this->objectManager->create(State::class);
        $adminCheckoutSession = $this->objectManager->create(AdminCheckoutSession::class);
        $appState->setAreaCode(Area::AREA_ADMINHTML);
        TestHelper::setInaccessibleProperty($this->session, 'appState', $appState);
        $this->assertEquals($adminCheckoutSession, $this->session->getCheckoutSession());
    }
}
