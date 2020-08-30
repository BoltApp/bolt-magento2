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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\Session;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use PHPUnit\Framework\TestCase;
use Magento\Framework\App\Helper\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Backend\Model\Session\Quote as AdminCheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Cache;
use Magento\Quote\Model\Quote;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Framework\Data\Form\FormKey;
use Bolt\Boltpay\Test\Unit\TestHelper;

/**
 * Class SessionTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper
 * @coversDefaultClass \Bolt\Boltpay\Helper\Session
 */
class SessionTest extends TestCase
{
    const SESSION_ID = '1111';
    const QUOTE_ID = '1';
    const STORE_ID = '1';
    const CUSTOMER_ID = '1111';
    const BOLT_PARENT_QUOTE_ID = '1111';
    const FORM_KEY = '2222';

    /**
     * @var Session
     */
    private $currentMock;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var AdminCheckoutSession
     */
    private $adminCheckoutSession;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /** @var CacheInterface */
    private $cache;

    /** @var State */
    private $appState;

    /** @var FormKey */
    private $formKey;

    /** @var ConfigHelper */
    private $configHelper;

    /**
     * @var \Magento\Framework\TestFramework\Unit\Helper\ObjectManager
     */
    protected $objectManager;

    protected $quote;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    private function initRequiredMocks()
    {
        $this->context = $this->createMock(Context::class);

        $this->checkoutSession = $this->createPartialMock(
            CheckoutSession::class,
            ['getSessionId', 'replaceQuote', 'writeClose', 'setSessionId', 'start']
        );

        $this->adminCheckoutSession = $this->createPartialMock(
            AdminCheckoutSession::class,
            ['isDebugModeOn']
        );

        $this->customerSession = $this->createPartialMock(
            CustomerSession::class,
            ['loginById']
        );

        $this->logHelper = $this->createPartialMock(
            LogHelper::class,
            ['isDebugModeOn']
        );

        $this->cache = $this->createPartialMock(
            Cache::class,
            ['save', 'load']
        );

        $this->appState = $this->createPartialMock(
            State::class,
            ['getAreaCode']
        );

        $this->formKey = $this->createPartialMock(
            FormKey::class,
            ['set', 'getFormKey']
        );
        $this->quote = $this->createPartialMock(
            Quote::class,
            ['getCustomerId', 'getBoltParentQuoteId', 'getStoreId', 'getID']
        );

        $this->configHelper = $this->createPartialMock(
            ConfigHelper::class,
            ['isSessionEmulationEnabled']
        );
    }

    private function initCurrentMock()
    {
        $this->currentMock = $this->getMockBuilder(Session::class)
            ->setMethods(['replaceQuote', 'setSession'])
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->checkoutSession,
                    $this->adminCheckoutSession,
                    $this->customerSession,
                    $this->logHelper,
                    $this->cache,
                    $this->appState,
                    $this->formKey,
                    $this->configHelper
                ]
            )
            ->getMock();
    }

     /**
     * @test
     * that constructor sets internal properties
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $instance = new Session(
            $this->context,
            $this->checkoutSession,
            $this->adminCheckoutSession,
            $this->customerSession,
            $this->logHelper,
            $this->cache,
            $this->appState,
            $this->formKey,
            $this->configHelper
        );
        
        $this->assertAttributeEquals($this->checkoutSession, 'checkoutSession', $instance);
        $this->assertAttributeEquals($this->adminCheckoutSession, 'adminCheckoutSession', $instance);
        $this->assertAttributeEquals($this->customerSession, 'customerSession', $instance);
        $this->assertAttributeEquals($this->logHelper, 'logHelper', $instance);
        $this->assertAttributeEquals($this->cache, 'cache', $instance);
        $this->assertAttributeEquals($this->appState, 'appState', $instance);
        $this->assertAttributeEquals($this->formKey, 'formKey', $instance);
        $this->assertAttributeEquals($this->configHelper, 'configHelper', $instance);
    }

    /**
     * @test
     */
    public function saveSession()
    {
        $this->checkoutSession->expects(self::once())->method('getSessionId')->willReturn(self::SESSION_ID);

        $this->cache->expects(self::once())->method('save')->will($this->returnCallback(
            function ($data) {
                $this->assertEquals('a:2:{s:11:"sessionType";s:8:"frontend";s:9:"sessionID";s:4:"1111";}', $data);
            }
        ));

        $this->currentMock->saveSession(self::QUOTE_ID, $this->checkoutSession);
    }

    /**
     * @test
     */
    public function setSession_withSessionEmulationIsDisabled()
    {
        $this->configHelper->expects(self::once())->method('isSessionEmulationEnabled')->with(self::STORE_ID)->willReturn(false);
        $result = TestHelper::invokeMethod($this->currentMock, 'setSession', [$this->checkoutSession, self::SESSION_ID, self::STORE_ID]);
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function setSession_withSessionEmulationIsEnabled()
    {
        $this->configHelper->expects(self::once())->method('isSessionEmulationEnabled')->with(self::STORE_ID)->willReturn(true);
        $this->checkoutSession->expects(self::once())->method('writeClose')->willReturnSelf();
        $this->checkoutSession->expects(self::once())->method('setSessionId')->willReturnSelf();
        $this->checkoutSession->expects(self::once())->method('start')->willReturnSelf();

        $result = TestHelper::invokeMethod($this->currentMock, 'setSession', [$this->checkoutSession, self::SESSION_ID, self::STORE_ID]);
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function replaceQuote()
    {
        $this->checkoutSession->expects(self::once())->method('replaceQuote')->with($this->quote)->willReturnSelf();
        $result = TestHelper::invokeMethod($this->currentMock, 'replaceQuote', [$this->quote]);
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function loadSession_withAreaCodeIsNotWebApiRest()
    {
        $this->appState->expects(self::once())->method('getAreaCode')->willReturn(Area::AREA_WEBAPI_SOAP);
        $this->checkoutSession->expects(self::once())->method('replaceQuote')->with($this->quote)->willReturnSelf();
        $this->assertNull($this->currentMock->loadSession($this->quote));
    }

    /**
     * @test
     */
    public function loadSession_withCacheIdentifierIsLoaded()
    {
        $this->appState->expects(self::once())->method('getAreaCode')->willReturn(Area::AREA_WEBAPI_REST);

        $this->quote->expects(self::once())->method('getCustomerId')->willReturn(self::CUSTOMER_ID);
        $this->quote->expects(self::once())->method('getBoltParentQuoteId')->willReturn(self::BOLT_PARENT_QUOTE_ID);
        $this->quote->expects(self::once())->method('getStoreId')->willReturn(self::STORE_ID);
        $this->cache->expects(self::once())->method('load')->willReturn('a:2:{s:11:"sessionType";s:8:"frontend";s:9:"sessionID";s:4:"1111";}');

        $this->currentMock->expects(self::any())->method('setSession')->withAnyParameters()->willReturnSelf();
        $this->customerSession->expects(self::once())->method('loginById')->with(self::CUSTOMER_ID)->willReturnSelf();

        $this->checkoutSession->expects(self::once())->method('replaceQuote')->with($this->quote)->willReturnSelf();

        $result = $this->currentMock->loadSession($this->quote);
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function loadSession_withCacheIdentifierIsNotLoaded()
    {
        $this->appState->expects(self::once())->method('getAreaCode')->willReturn(Area::AREA_WEBAPI_REST);

        $this->quote->expects(self::once())->method('getCustomerId')->willReturn(self::CUSTOMER_ID);
        $this->quote->expects(self::once())->method('getBoltParentQuoteId')->willReturn(self::BOLT_PARENT_QUOTE_ID);
        $this->cache->expects(self::once())->method('load')->willReturn(false);

        $this->customerSession->expects(self::once())->method('loginById')->with(self::CUSTOMER_ID)->willReturnSelf();

        $this->checkoutSession->expects(self::once())->method('replaceQuote')->with($this->quote)->willReturnSelf();
        $result = $this->currentMock->loadSession($this->quote);

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function setFormKey()
    {
        $this->quote->expects(self::once())->method('getId')->willReturn(self::CUSTOMER_ID);
        $this->cache->expects(self::once())->method('load')->will($this->returnCallback(
            function ($data) {
                $this->assertEquals('BOLT_SESSION_FORM_KEY_1111', $data);
            }
        ));

        $this->formKey->expects(self::once())->method('set')->withAnyParameters()->willReturnSelf();
        $this->currentMock->setFormKey($this->quote);
    }

    /**
     * @test
     */
    public function cacheFormKey()
    {
        $this->quote->expects(self::once())->method('getId')->willReturn(self::CUSTOMER_ID);
        $this->formKey->expects(self::once())->method('getFormKey')->willReturn(self::FORM_KEY);
        $this->cache->expects(self::once())->method('save')->with(
            self::FORM_KEY,
            'BOLT_SESSION_FORM_KEY_1111',
            [],
            14400
        )->will($this->returnCallback(
            function ($data) {
                $this->assertEquals(self::FORM_KEY, $data);
            }
        ));

        $this->currentMock->cacheFormKey($this->quote);
    }

    /**
     * @test
     */
    public function getCheckoutSession_withAreaIsAdmin()
    {
        $this->appState->expects(self::once())->method('getAreaCode')->willReturn(Area::AREA_ADMINHTML);
        $this->assertSame($this->adminCheckoutSession, $this->currentMock->getCheckoutSession());
    }

    /**
     * @test
     * @dataProvider getCheckoutSession_withAreaIsNotAdminProvider
     */
    public function getCheckoutSession_withAreaIsNotAdmin($data)
    {
        $this->appState->expects(self::once())->method('getAreaCode')->willReturn($data['area']);
        $this->assertSame($this->checkoutSession, $this->currentMock->getCheckoutSession());
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
}
