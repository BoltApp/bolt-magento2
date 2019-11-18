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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Block;

use Bolt\Boltpay\Block\Js as BlockJs;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config as HelperConfig;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Framework\App\Request\Http;
/**
 * Class JsTest
 *
 * @package Bolt\Boltpay\Test\Unit\Block
 */
class JsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var HelperConfig
     */
    protected $configHelper;
    /**
     * @var \Magento\Framework\App\Helper\Context
     */
    protected $helperContextMock;
    /**
     * @var \Magento\Framework\View\Element\Template\Context
     */
    protected $contextMock;

    /**
     * @var Http
     */
    private $requestMock;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSessionMock;

    /**
     * @var BlockJs
     */
    protected $block;

    /**
     * @var CartHelper
     */
    private $cartHelperMock;

    /**
     * @var Bugsnag
     */
    private $bugsnagHelperMock;

    private $magentoQuote;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->helperContextMock = $this->createMock(\Magento\Framework\App\Helper\Context::class);
        $this->contextMock = $this->createMock(\Magento\Framework\View\Element\Template\Context::class);

        $this->checkoutSessionMock = $this->getMockBuilder(\Magento\Checkout\Model\Session::class)
            ->disableOriginalConstructor()
            ->setMethods(['getQuote', 'getBoltInitiateCheckout','unsBoltInitiateCheckout'])
            ->getMock();

        $this->magentoQuote = $this->getMockBuilder(\Magento\Checkout\Model\Session::class)
            ->disableOriginalConstructor()
            ->setMethods(['getQuote'])
            ->getMock();

        $methods = [
            'isSandboxModeSet', 'isActive', 'getAnyPublishableKey',
            'getPublishableKeyPayment', 'getPublishableKeyCheckout', 'getPublishableKeyBackOffice',
            'getReplaceSelectors', 'getGlobalCSS', 'getPrefetchShipping', 'getQuoteIsVirtual',
            'getTotalsChangeSelectors', 'getAdditionalCheckoutButtonClass', 'getAdditionalConfigString', 'getIsPreAuth',
            'shouldTrackCheckoutFunnel','isPaymentOnlyCheckoutEnabled'
        ];

        $this->configHelper = $this->getMockBuilder(HelperConfig::class)
            ->setMethods($methods)
            ->setConstructorArgs(
                [
                    $this->helperContextMock,
                    $this->createMock(\Magento\Framework\Encryption\EncryptorInterface::class),
                    $this->createMock(\Magento\Framework\Module\ResourceInterface::class),
                    $this->createMock(\Magento\Framework\App\ProductMetadataInterface::class),
                    $this->createMock(\Magento\Framework\App\Request\Http::class)
                ]
            )
            ->getMock();

        $this->cartHelperMock = $this->createMock(CartHelper::class);
        $this->bugsnagHelperMock = $this->createMock(Bugsnag::class);
        $this->requestMock = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()
            ->setMethods(['getFullActionName'])
            ->getMock();

        $this->contextMock->method('getRequest')->willReturn($this->requestMock);
        $this->block = $this->getMockBuilder(BlockJs::class)
            ->setMethods(['configHelper', 'getUrl'])
            ->setConstructorArgs(
                [
                    $this->contextMock,
                    $this->configHelper,
                    $this->checkoutSessionMock,
                    $this->cartHelperMock,
                    $this->bugsnagHelperMock
                ]
            )
            ->getMock();
    }

    /**
     * @inheritdoc
     */
    public function testGetTrackJsUrl()
    {
        // For CDN URL in sandbox mode
        $this->setSandboxMode();
        $result = $this->block->getTrackJsUrl();
        $expectedUrl = HelperConfig::CDN_URL_SANDBOX . DIRECTORY_SEPARATOR . 'track.js';

        $this->assertEquals($expectedUrl, $result, 'Not equal CDN Url in Sandbox mode');
    }

    /**
     * @inheritdoc
     */
    public function testGetTrackJsUrlForProductionMode()
    {
        // For CDN URL in production mode.
        $this->setSandboxMode(false);
        $result = $this->block->getTrackJsUrl();
        $expectedUrl = HelperConfig::CDN_URL_PRODUCTION . DIRECTORY_SEPARATOR . 'track.js';

        $this->assertEquals($result, $expectedUrl, 'Not equal CDN Url in Production mode');
    }

    /**
     * @inheritdoc
     */
    public function testGetConnectJsUrl()
    {
        // For CDN URL in sandbox mode
        $this->setSandboxMode();
        $result = $this->block->getConnectJsUrl();
        $expectedUrl = HelperConfig::CDN_URL_SANDBOX . DIRECTORY_SEPARATOR . 'connect.js';
        $this->assertEquals($result, $expectedUrl, 'Not equal CDN Url in Sandbox mode');
    }

    public function testGetConnectJsUrlForProductionMode()
    {
        // For CDN URL in production mode.
        $this->setSandboxMode(false);
        $result = $this->block->getConnectJsUrl();
        $expectedUrl = HelperConfig::CDN_URL_PRODUCTION . DIRECTORY_SEPARATOR . 'connect.js';
        $this->assertEquals($result, $expectedUrl, 'Not equal CDN Url in Production mode');
    }

    /**
     * @test
     * @param $data
     * @dataProvider providerTestGetCheckoutKey
     */
    public function testGetCheckoutKey($data)
    {
        $this->configHelper->expects($this->any())
            ->method('isPaymentOnlyCheckoutEnabled')
            ->will($this->returnValue($data['is_payment_only_checkout_enabled']));
        $this->configHelper->expects($this->any())
            ->method('getPublishableKeyPayment')
            ->will($this->returnValue($data['publishable_key_payment']));
        $this->configHelper->expects($this->any())
            ->method('getPublishableKeyCheckout')
            ->will($this->returnValue($data['publishable_key_checkout']));

        $this->requestMock->method('getFullActionName')->willReturn($data['action']);
        $result = $this->block->getCheckoutKey();
        $this->assertStringStartsWith('pKv_', $result, 'Publishable Key doesn\'t work properly');
        $this->assertEquals(strlen($data['expected']), strlen($result), 'Publishable Key has an invalid length');

    }

    public function providerTestGetCheckoutKey()
    {
        return [
            ['data' => [
                'is_payment_only_checkout_enabled' => true,
                'publishable_key_payment' => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTCHECKOUTPAGE',
                'publishable_key_checkout' => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTCARTPAGE',
                'action' => HelperConfig::CHECKOUT_PAGE_ACTION,
                'expected' => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTCHECKOUTPAGE'
                ]
            ],
            ['data' => [
                'is_payment_only_checkout_enabled' => true,
                'publishable_key_payment' => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTCHECKOUTPAGE',
                'publishable_key_checkout' => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTCARTPAGE',
                'action' => HelperConfig::SHOPPING_CART_PAGE_ACTION,
                'expected' => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTCARTPAGE'
                ]
            ],
            ['data' => [
                'is_payment_only_checkout_enabled' => true,
                'publishable_key_payment' => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTCHECKOUTPAGE',
                'publishable_key_checkout' => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTOTHERPAGES',
                'action' => 'other_actions',
                'expected' => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTOTHERPAGES'
                ]
            ],
            ['data' => [
                'is_payment_only_checkout_enabled' => false,
                'publishable_key_payment' => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTCHECKOUTPAGE',
                'publishable_key_checkout' => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTMINICART',
                'action' => HelperConfig::CHECKOUT_PAGE_ACTION,
                'expected' => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTMINICART'
                ]
            ],
        ];
    }

    /**
     * @test
     * @param $data
     * @dataProvider providerGetReplaceSelectors
     */
    public function testGetReplaceSelectors($data)
    {
        $this->configHelper->expects($this->any())
            ->method('getReplaceSelectors')
            ->will($this->returnValue($data['value']));

        $this->configHelper->expects($this->any())
            ->method('isPaymentOnlyCheckoutEnabled')
            ->will($this->returnValue($data['is_payment_only_checkout_enabled']));

        $this->requestMock->method('getFullActionName')->willReturn($data['action']);

        $result = $this->block->getReplaceSelectors();

        $this->assertEquals($data['expected'], $result, 'getReplaceSelectors() method: not working properly');
    }

    public function providerGetReplaceSelectors(){
        return [
            ['data' => [
                'value' => '.replaceable-example-selector1|append .replaceable-example-selector2|prepend,.replaceable-example-selector3',
                'is_payment_only_checkout_enabled' => true,
                'action' => HelperConfig::CHECKOUT_PAGE_ACTION,
                'expected' => []
                ]
            ],
            ['data' => [
                'value' => '.replaceable-example-selector1|append .replaceable-example-selector2|prepend,.replaceable-example-selector3',
                'is_payment_only_checkout_enabled' => true,
                'action' => 'other_actions',
                'expected' => ['.replaceable-example-selector1|append .replaceable-example-selector2|prepend', '.replaceable-example-selector3']
                ]
            ],
            ['data' => [
                'value' => '.replaceable-example-selector1|append .replaceable-example-selector2|prepend,.replaceable-example-selector3',
                'is_payment_only_checkout_enabled' => false,
                'action' => HelperConfig::CHECKOUT_PAGE_ACTION,
                'expected' => ['.replaceable-example-selector1|append .replaceable-example-selector2|prepend', '.replaceable-example-selector3']
                ]
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function testGetGlobalCSS()
    {
        $value = '.replaceable-example-selector1 {
            color: red;
        }';

        $this->configHelper->expects($this->once())
            ->method('getGlobalCSS')
            ->will($this->returnValue($value));

        $result = $this->block->getGlobalCSS();

        $this->assertEquals($value, $result, 'getGlobalCSS() method: not working properly');
    }

    /**
     * @inheritdoc
     */
    public function testGetSettings()
    {
        $result = $this->block->getSettings();

        $this->assertJson($result, 'The Settings config do not have a proper JSON format.');

        $array = json_decode($result, true);
        $this->assertCount(16, $array, 'The number of keys in the settings is not correct');

        $message = 'Cannot find in the Settings the key: ';
        $this->assertArrayHasKey('connect_url', $array, $message . 'connect_url');
        $this->assertArrayHasKey('publishable_key_payment', $array, $message . 'publishable_key_payment');
        $this->assertArrayHasKey('publishable_key_checkout', $array, $message . 'publishable_key_checkout');
        $this->assertArrayHasKey('publishable_key_back_office', $array, $message . 'publishable_key_back_office');
        $this->assertArrayHasKey('create_order_url', $array, $message . 'create_order_url');
        $this->assertArrayHasKey('save_order_url', $array, $message . 'save_order_url');
        $this->assertArrayHasKey('selectors', $array, $message . 'selectors');
        $this->assertArrayHasKey('shipping_prefetch_url', $array, $message . 'shipping_prefetch_url');
        $this->assertArrayHasKey('prefetch_shipping', $array, $message . 'prefetch_shipping');
        $this->assertArrayHasKey('save_email_url', $array, $message . 'save_email_url');
        $this->assertArrayHasKey('quote_is_virtual', $array, $message . 'quote_is_virtual');
        $this->assertArrayHasKey('totals_change_selectors', $array, $message . 'totals_change_selectors');
        $this->assertArrayHasKey('additional_checkout_button_class', $array, $message . 'additional_checkout_button_class');
        $this->assertArrayHasKey('initiate_checkout', $array, $message . 'initiate_checkout');
        $this->assertArrayHasKey('toggle_checkout', $array, $message . 'toggle_checkout');
    }

    /**
     * @inheritdoc
     */
    public function testIsEnabled()
    {
        $storeId = 0;
        $this->configHelper->expects($this->any())
            ->method('isActive')
            ->with($storeId)
            ->will($this->returnValue(true));

        $result = $this->block->isEnabled();

        $this->assertTrue($result, 'IsEnabled() method: not working properly');
    }

    /**
     * Get CDN url mode.
     *
     * @param bool $value
     */
    public function setSandboxMode($value = true)
    {
        $this->configHelper->expects($this->any())
            ->method('isSandboxModeSet')
            ->will($this->returnValue($value));
    }

    public function setBoltInitiateCheckout($value = true)
    {
        $this->checkoutSessionMock
            ->expects($this->once())
            ->method('getBoltInitiateCheckout')
            ->willReturn($value);
    }

    public function testGetInitiateCheckoutFalse()
    {
        $this->assertFalse($this->block->getInitiateCheckout(), 'getInitiateCheckout() method: not working properly');
    }

    public function testGetInitiateCheckoutTrue()
    {
        $this->setBoltInitiateCheckout();
        $this->assertTrue($this->block->getInitiateCheckout(), 'getInitiateCheckout() method: not working properly');
    }

    public function testShouldTrackCheckoutFunnel()
    {
        $this->configHelper->expects($this->any())
                           ->method('shouldTrackCheckoutFunnel')
                           ->will($this->returnValue(true));

        $result = $this->block->shouldTrackCheckoutFunnel();

        $this->assertTrue($result, 'shouldTrackCheckoutFunnel() returns true when config is set to true');
    }
}
