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

namespace Bolt\Boltpay\Test\Unit\Block;

use Bolt\Boltpay\Block\Js;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as HelperConfig;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Model\Api\Data\BoltConfigSettingFactory;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Composer\ComposerFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ResourceInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\View\Element\Template\Context as BlockContext;
use Magento\Quote\Model\Quote;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionException;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;

/**
 * Class JsTest
 *
 * @coversDefaultClass \Bolt\Boltpay\Block\Js
 *
 * @package Bolt\Boltpay\Test\Unit\Block
 */
class JsTest extends BoltTestCase
{
    /**
     * @var int expected number of settings returned by {@see \Bolt\Boltpay\Block\Js::getSettings}
     */
    const SETTINGS_NUMBER = 27;

    /**
     * @var int expeced number of tracking callback returned by {@see \Bolt\Boltpay\Block\Js::getTrackCallbacks}
     */
    const TRACK_CALLBACK_NUMBER = 7;

    /**
     * @var int test store id
     */
    const STORE_ID = 1;

    /**
     * @var string test API key
     */
    const CONFIG_API_KEY = 'test_api_key';

    /**
     * @var string test API signing secret
     */
    const CONFIG_SIGNING_SECRET = 'test_signing_secret';

    /**
     * @var string test API publishable key
     */
    const CONFIG_PUBLISHABLE_KEY = 'test_publishable_key';

    /**
     * @var HelperConfig|MockObject mocked instance of the Bolt configuration helper
     */
    protected $configHelper;

    /**
     * @var Context|MockObject mocked instance of the helper context
     */
    protected $helperContextMock;

    /**
     * @var BlockContext|MockObject mocked instance of the block context
     */
    protected $contextMock;

    /**
     * @var Session|MockObject mocked instance of the Checkout session model
     */
    protected $checkoutSessionMock;

    /**
     * @var Js|MockObject Mocked instance of tested class
     */
    protected $currentMock;

    /**
     * @var Decider|MockObject mocked instance of the feature switch decider
     */
    protected $deciderMock;

    /**
     * @var HttpContext|MockObject mocked instance of the customer session
     */
    protected $httpContextMock;

    /**
     * @var Http|MockObject mocked instance of the Magento request object
     */
    private $requestMock;

    /**
     * @var CartHelper|MockObject mocked instance of the Cart helper
     */
    private $cartHelperMock;

    /**
     * @var Bugsnag|MockObject mocked instance of the Bugsnag helper
     */
    private $bugsnagHelperMock;

    /**
     * @var ObjectManager|MockObject unit test object manager
     */
    private $objectManager;

    /**
     * @var MockObject|EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;
    
    /** @var State */
    private $_appState;

    /**
     * @test
     * that __construct sets internal properties
     *
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperties()
    {
        $instance = new Js(
            $this->contextMock,
            $this->configHelper,
            $this->checkoutSessionMock,
            $this->cartHelperMock,
            $this->bugsnagHelperMock,
            $this->deciderMock,
            $this->eventsForThirdPartyModules,
            $this->httpContextMock
        );
        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
        static::assertAttributeEquals($this->checkoutSessionMock, 'checkoutSession', $instance);
        static::assertAttributeEquals($this->cartHelperMock, 'cartHelper', $instance);
        static::assertAttributeEquals($this->bugsnagHelperMock, 'bugsnag', $instance);
        static::assertAttributeEquals($this->deciderMock, 'featureSwitches', $instance);
        static::assertAttributeEquals($this->eventsForThirdPartyModules, 'eventsForThirdPartyModules', $instance);
    }

    /**
     * @test
     * that getTrackJsUrl returns track JS URL according to the sandbox configuration mode
     *
     * @covers ::getTrackJsUrl
     *
     * @dataProvider getTrackJsUrl_withVariousConfigurationModesProvider
     *
     * @param bool $sandboxMode    configuration flag
     * @param bool $expectedResult of the tested method call
     */
    public function getTrackJsUrl_withVariousConfigurationModes_returnsCheckoutUrl($sandboxMode, $expectedResult)
    {
        $this->setSandboxMode($sandboxMode);
        static::assertEquals($expectedResult, $this->currentMock->getTrackJsUrl());
    }

    /**
     * Data provider for {@see getTrackJsUrl_withVariousConfigurationModes_returnsCheckoutUrl}
     *
     * @return array[] containing sandbox configuration mode and expected result of the tested method call
     */
    public function getTrackJsUrl_withVariousConfigurationModesProvider()
    {
        return [
            [
                'sandboxMode'    => true,
                'expectedResult' => HelperConfig::CDN_URL_SANDBOX . '/track.js',
            ],
            [
                'sandboxMode'    => false,
                'expectedResult' => HelperConfig::CDN_URL_PRODUCTION . '/track.js',
            ],
        ];
    }

    /**
     * @test
     * that getConnectJsUrl returns connect JS URL according to the sandbox configuration mode
     *
     * @covers ::getConnectJsUrl
     *
     * @dataProvider getConnectJsUrl_withVariousConfigurationModesProvider
     *
     * @param bool $sandboxMode    configuration flag
     * @param bool $expectedResult of the tested method call
     */
    public function getConnectJsUrl_withVariousConfigurationModes_returnsCheckoutUrl($sandboxMode, $expectedResult)
    {
        $this->setSandboxMode($sandboxMode);
        static::assertEquals($expectedResult, $this->currentMock->getConnectJsUrl());
    }

    /**
     * Data provider for {@see getConnectJsUrl_withVariousConfigurationModes_returnsCheckoutUrl}
     *
     * @return array[] containing sandbox configuration mode and expected result of tested method
     */
    public function getConnectJsUrl_withVariousConfigurationModesProvider()
    {
        return [
            [
                'sandboxMode'    => true,
                'expectedResult' => HelperConfig::CDN_URL_SANDBOX . '/connect.js',
            ],
            [
                'sandboxMode'    => false,
                'expectedResult' => HelperConfig::CDN_URL_PRODUCTION . '/connect.js',
            ],
        ];
    }

    /**
     * @test
     * that getPayByLinkUrl returns checkout URL according to the sandbox configuration mode
     *
     * @covers ::getPayByLinkUrl
     *
     * @dataProvider getPayByLinkUrl_withVariousConfigurationModesProvider
     *
     * @param bool $sandboxMode    configuration flag
     * @param bool $expectedResult of the tested method call
     */
    public function getPayByLinkUrl_withVariousConfigurationModes_returnsCheckoutUrl($sandboxMode, $expectedResult)
    {
        $this->setSandboxMode($sandboxMode);
        static::assertEquals($expectedResult, $this->currentMock->getPayByLinkUrl());
    }

    /**
     * Data provider for {@see getPayByLinkUrl_withVariousConfigurationModes_returnsCheckoutUrl}
     *
     * @return array[] containing sandbox configuration mode and expected result of tested method
     */
    public function getPayByLinkUrl_withVariousConfigurationModesProvider()
    {
        return [
            [
                'sandboxMode'    => true,
                'expectedResult' => HelperConfig::CDN_URL_SANDBOX . '/checkout',
            ],
            [
                'sandboxMode'    => false,
                'expectedResult' => HelperConfig::CDN_URL_PRODUCTION . '/checkout',
            ],
        ];
    }

    /**
     * @test
     * that getAccountJsUrl returns the account JS URL according to the sandbox configuration
     *
     * @covers ::getAccountJsUrl
     *
     * @dataProvider getAccountJsUrl_withVariousConfigurationModesProvider
     *
     * @param bool $sandboxMode    configuration flag
     * @param bool $expectedResult of the tested method call
     */
    public function getAccountJsUrl_withVariousSandboxConfigurationModes_returnsAccountUrl($sandboxMode, $expectedResult)
    {
        $this->setSandboxMode($sandboxMode);
        static::assertEquals($expectedResult, $this->currentMock->getAccountJsUrl());
    }

    /**
     * Data provider for {@see getAccountJsUrl_withVariousSandboxConfigurationModes_returnsAccountUrl}
     *
     * @return array[] containing sandbox configuration mode and expected result of the tested method call
     */
    public function getAccountJsUrl_withVariousConfigurationModesProvider()
    {
        return [
            [
                'sandboxMode'    => true,
                'expectedResult' => HelperConfig::ACCOUNT_URL_SANDBOX . '/account.js',
            ],
            [
                'sandboxMode'    => false,
                'expectedResult' => HelperConfig::ACCOUNT_URL_PRODUCTION . '/account.js',
            ],
        ];
    }

    /**
     * @test
     * that getCheckoutKey returns defined checkout publishable keys if:
     * 1. checkout Payment is Enabled
     * 2. requested page is checkout index
     *
     * @covers \Bolt\Boltpay\Block\BlockTrait::getCheckoutKey
     *
     * @dataProvider getCheckoutKey_withVariousStatesProvider
     *
     * @param bool   $isPaymentOnlyCheckoutEnabled
     * @param string $publishableKeyPayment
     * @param string $publishableKeyCheckout
     * @param string $requestAction                current request name, stubbed result of {@see \Magento\Framework\App\Request\Http::getFullActionName}
     * @param string $expectedResult               of the tested method call
     */
    public function getCheckoutKey_withVariousStates_returnsCheckoutKey(
        $isPaymentOnlyCheckoutEnabled,
        $publishableKeyPayment,
        $publishableKeyCheckout,
        $requestAction,
        $expectedResult
    ) {
        $this->configHelper->method('isPaymentOnlyCheckoutEnabled')
            ->willReturn($isPaymentOnlyCheckoutEnabled);
        $this->configHelper->method('getPublishableKeyPayment')
            ->willReturn($publishableKeyPayment);
        $this->configHelper->method('getPublishableKeyCheckout')
            ->willReturn($publishableKeyCheckout);

        $this->requestMock->method('getFullActionName')->willReturn($requestAction);
        $result = $this->currentMock->getCheckoutKey();
        static::assertStringStartsWith('pKv_', $result, 'Publishable Key doesn\'t work properly');
        static::assertEquals(strlen($expectedResult), strlen($result), 'Publishable Key has an invalid length');
        static::assertEquals($expectedResult, $result, 'Publishable Key has an invalid length');
    }

    /**
     * Data provider for {@see getCheckoutKey_withVariousStates_returnsCheckoutKey}
     *
     * @return array[]
     */
    public function getCheckoutKey_withVariousStatesProvider()
    {
        return [
            [
                'is_payment_only_checkout_enabled' => true,
                'publishable_key_payment'          => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTCHECKOUTPAGE',
                'publishable_key_checkout'         => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTCARTPAGE',
                'requestAction'                    => HelperConfig::CHECKOUT_PAGE_ACTION,
                'expectedResult'                   => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTCHECKOUTPAGE'
            ],
            [
                'is_payment_only_checkout_enabled' => true,
                'publishable_key_payment'          => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTCHECKOUTPAGE',
                'publishable_key_checkout'         => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTCARTPAGE',
                'requestAction'                    => HelperConfig::SHOPPING_CART_PAGE_ACTION,
                'expectedResult'                   => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTCARTPAGE'
            ],
            [
                'is_payment_only_checkout_enabled' => true,
                'publishable_key_payment'          => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTCHECKOUTPAGE',
                'publishable_key_checkout'         => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTOTHERPAGES',
                'requestAction'                    => 'other_requests',
                'expectedResult'                   => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTOTHERPAGES'
            ],
            [
                'is_payment_only_checkout_enabled' => false,
                'publishable_key_payment'          => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTCHECKOUTPAGE',
                'publishable_key_checkout'         => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTMINICART',
                'requestAction'                    => HelperConfig::CHECKOUT_PAGE_ACTION,
                'expectedResult'                   => 'pKv_pOzRTEST.TESTkEIjTEST.TEST01f0d15501cd7548c1953f6666b2689f2e5a20198c5d7f886c004913TESTMINICART'
            ],
        ];
    }

    /**
     * @test
     * that getReplaceSelectors returns empty array if payment only checkout is enabled and current action is checkout
     * otherwise returns button replace selectors from configuration after splitting them by comma
     * and trimming unnecessary whitespace characters
     *
     * @covers ::getReplaceSelectors
     *
     * @dataProvider getReplaceSelectors_withVariousConfigPropertiesProvider
     *
     * @param bool   $isPaymentOnlyCheckoutEnabled configuration flag
     * @param string $requestActionName            current request name, stubbed result of {@see \Magento\Framework\App\Request\Http::getFullActionName}
     * @param string $replaceSelectors             value in configuration
     * @param mixed  $expectedResult               of the tested method call
     */
    public function getReplaceSelectors_withVariousConfigProperties_returnsReplaceSelectorsArray(
        $isPaymentOnlyCheckoutEnabled,
        $requestActionName,
        $replaceSelectors,
        $expectedResult
    ) {
        $this->configHelper->expects(static::once())
            ->method('isPaymentOnlyCheckoutEnabled')
            ->willReturn($isPaymentOnlyCheckoutEnabled);
        $this->requestMock->method('getFullActionName')->willReturn($requestActionName);
        $this->configHelper->expects(static::any())->method('getReplaceSelectors')->willReturn($replaceSelectors);

        static::assertEquals($expectedResult, $this->currentMock->getReplaceSelectors());
    }

    /**
     * Data provider for {@see getReplaceSelectors_withVariousConfigProperties_returnsReplaceSelectorsArray}
     *
     * @return array[] containing
     *                 isPaymentOnlyCheckoutEnabled configuration flag
     *                 action page name value
     *                 config selector value
     *                 expected result of the tested method call
     */
    public function getReplaceSelectors_withVariousConfigPropertiesProvider()
    {
        return [
            [
                'isPaymentOnlyCheckoutEnabled' => true,
                'requestActionName'            => HelperConfig::CHECKOUT_PAGE_ACTION,
                'replaceSelectors'             => '.replaceable-example-selector1|append .replaceable-example-selector2|prepend,.replaceable-example-selector3',
                'expectedResult'               => [],
            ],
            [
                'isPaymentOnlyCheckoutEnabled' => true,
                'requestActionName'            => 'other_actions',
                'replaceSelectors'             => '.replaceable-example-selector1|append .replaceable-example-selector2|prepend,.replaceable-example-selector3',
                'expectedResult'               => [
                    '.replaceable-example-selector1|append .replaceable-example-selector2|prepend',
                    '.replaceable-example-selector3',
                ],
            ],
            [
                'isPaymentOnlyCheckoutEnabled' => false,
                'requestActionName'            => HelperConfig::CHECKOUT_PAGE_ACTION,
                'replaceSelectors'             => '.replaceable-example-selector1|append .replaceable-example-selector2|prepend,.replaceable-example-selector3',
                'expectedResult'               => [
                    '.replaceable-example-selector1|append .replaceable-example-selector2|prepend',
                    '.replaceable-example-selector3',
                ],
            ],
        ];
    }

    /**
     * @test
     * that getTotalsChangeSelectors returns array of filtered selectors after:
     * 1. config value is trimmed
     * 2. selector additional whitespaces are removed
     * 3. empty children are removed
     *
     * @covers ::getTotalsChangeSelectors
     *
     * @dataProvider getTotalsChangeSelectors_withVariousSelectorsProvider
     *
     * @param string $selectors      from the config helper
     * @param string $expectedResult of the tested method call
     */
    public function getTotalsChangeSelectors_withVariousSelectors_returnsFilteredSelectors($selectors, $expectedResult)
    {
        $this->configHelper->expects(static::once())->method('getTotalsChangeSelectors')->willReturn($selectors);
        static::assertEquals($expectedResult, $this->currentMock->getTotalsChangeSelectors());
    }

    /**
     * Data provider for {@see getTotalsChangeSelectors_withVariousSelectors_returnsFilteredSelectors}
     *
     * @return array[] containing totals change selectors config value   and expected result of tested method
     */
    public function getTotalsChangeSelectors_withVariousSelectorsProvider()
    {
        return [
            [
                'selectors'      => '  .button,    .checkout     ,       .payout    ',
                'expectedResult' => ['.button', ' .checkout ', ' .payout'],
            ],
            [
                'selectors'      => '    .checkout,       .payout    ',
                'expectedResult' => ['.checkout', ' .payout'],
            ],
            [
                'selectors'      => '  .value  , , , ,    ',
                'expectedResult' => ['.value ', ' ', ' ', ' '],
            ],
        ];
    }

    /**
     * @test
     * that getAdditionalCheckoutButtonAttributes returns additional checkout button attributes from
     *
     * @see \Bolt\Boltpay\Helper\Config::getAdditionalCheckoutButtonAttributes
     *
     * @covers ::getAdditionalCheckoutButtonAttributes
     */
    public function getAdditionalCheckoutButtonAttributes_always_returnsAdditionalCheckoutButtonAttributesFromConfig()
    {
        $additionalCheckoutButtonAttributes = (object) ['data-btn-txt' => 'Pay now'];
        $this->configHelper->expects(static::once())
            ->method('getAdditionalCheckoutButtonAttributes')
            ->willReturn($additionalCheckoutButtonAttributes);

        static::assertEquals(
            $additionalCheckoutButtonAttributes,
            $this->currentMock->getAdditionalCheckoutButtonAttributes()
        );
    }

    /**
     * @test
     * that getAdditionalCheckoutButtonClass returns trimmed additional checkout button class from the config helper
     *
     * @see \Bolt\Boltpay\Helper\Config::getAdditionalCheckoutButtonClass
     *
     * @covers ::getAdditionalCheckoutButtonClass
     */
    public function getAdditionalCheckoutButtonClass_always_returnsTrimmedButtonClass()
    {
        $configValue = ' .btn-green ';
        $this->configHelper->expects(static::once())
            ->method('getAdditionalCheckoutButtonClass')
            ->willReturn($configValue);

        static::assertEquals(trim($configValue), $this->currentMock->getAdditionalCheckoutButtonClass());
    }

    /**
     * @test
     * that getGlobalCSS returns global CSS from config to be added to any page that displays bolt checkout button
     *
     * @see \Bolt\Boltpay\Helper\Config::getGlobalCSS
     *
     * @covers ::getGlobalCSS
     */
    public function getGlobalCSS_always_returnsCssCode()
    {
        $value = '.replaceable-example-selector1 {
            color: red;
        }';
        
        $this->currentMock->expects(static::once())
                          ->method('getRequest')
                          ->willReturnSelf();
                          
        $this->currentMock->expects(static::once())
                          ->method('getFullActionName')
                          ->willReturn('catalog_product_view');
            
        $this->configHelper->expects(static::once())
            ->method('getGlobalCSS')
            ->willReturn($value);

        $result = $this->currentMock->getGlobalCSS();

        static::assertEquals($value, $result, 'getGlobalCSS() method: not working properly');
    }
    
    /**
     * @test
     * that if Bolt is disabled on the cart page, getGlobalCSS returns global CSS from config as well as extra css to show native M2 checkout button.
     *
     * @see \Bolt\Boltpay\Helper\Config::getGlobalCSS
     *
     * @covers ::getGlobalCSS
     */
    public function getGlobalCSS_BoltOnCartDisabled_returnsCssCode()
    {
        $value = '.replaceable-example-selector1 {
            color: red;
        }';

        $this->currentMock->expects(static::once())
                          ->method('getRequest')
                          ->willReturnSelf();
                          
        $this->currentMock->expects(static::once())
                          ->method('getFullActionName')
                          ->willReturn('checkout_cart_index');
        
        $this->configHelper->expects(static::once())
            ->method('getBoltOnCartPage')
            ->willReturn(false);
            
        $this->configHelper->expects(static::once())
            ->method('getGlobalCSS')
            ->willReturn($value);

        $result = $this->currentMock->getGlobalCSS();
        
        $expectedResult = '.replaceable-example-selector1 {
            color: red;
        }button[data-role=proceed-to-checkout]{display:block!important;}';

        static::assertEquals($expectedResult, $result, 'getGlobalCSS() method: not working properly');
    }
    
    /**
     * @test
     * that getGlobalJS returns global javascript from config to be added to any page
     *
     * @see \Bolt\Boltpay\Helper\Config::getGlobalJS
     *
     * @covers ::getGlobalJS
     */
    public function getGlobalJS_always_returnsJSCode()
    {
        $value = 'require([
            "jquery"
        ], function ($) {       
        });';

        $this->configHelper->expects(static::once())
            ->method('getGlobalJS')
            ->willReturn($value);

        $result = $this->currentMock->getGlobalJS();

        static::assertEquals($value, $result, 'getGlobalJS() method: not working properly');
    }

    /**
     * @test
     * that getJavascriptSuccess returns javascript onsuccess callback from the config helper
     *
     * @covers ::getJavascriptSuccess
     */
    public function getJavascriptSuccess_always_returnsCallback()
    {
        $this->configHelper->expects(static::once())
            ->method('getJavascriptSuccess')
            ->willReturn(HelperConfig::XML_PATH_JAVASCRIPT_SUCCESS);

        static::assertEquals(
            HelperConfig::XML_PATH_JAVASCRIPT_SUCCESS,
            $this->currentMock->getJavascriptSuccess()
        );
    }

    /**
     * @test
     * that getAdditionalJavascript returns additional javascript callback from the config helper
     *
     * @see \Bolt\Boltpay\Helper\Config::getAdditionalJS
     *
     * @covers ::getAdditionalJavascript
     */
    public function getAdditionalJavascript_always_returnsAdditionalJSCallback()
    {
        $this->configHelper->expects(static::once())
            ->method('getAdditionalJS')
            ->willReturn(HelperConfig::XML_PATH_ADDITIONAL_JS);
        $this->eventsForThirdPartyModules->expects(static::once())
            ->method('runFilter')
            ->with('getAdditionalJS', HelperConfig::XML_PATH_ADDITIONAL_JS)
            ->willReturn('test additional js');
        static::assertEquals(
            'test additional js',
            $this->currentMock->getAdditionalJavascript()
        );
    }

    /**
     * @test
     */
    public function getAdditionalHtml_returnsCorrectResult()
    {
        $this->eventsForThirdPartyModules->expects(static::once())->method('runFilter')->with('getAdditionalHtml', null)->willReturn('<html></html>');
        static::assertEquals('<html></html>', $this->currentMock->getAdditionalHtml());
    }

    /**
     * @test
     * that getSettings returns valid JSON containing javascript page settings
     *
     * @covers ::getSettings
     */
    public function getSettings_always_returnsJsonSettings()
    {
        $this->deciderMock->method('isPayByLinkEnabled')->willReturn(true);
        $result = $this->currentMock->getSettings();

        static::assertJson($result, 'The Settings config do not have a proper JSON format.');

        $array = json_decode($result, true);
        static::assertCount(static::SETTINGS_NUMBER, $array, 'The number of keys in the settings is not correct');

        $message = 'Cannot find following key in the Settings: ';
        static::assertArrayHasKey('connect_url', $array, $message . 'connect_url');
        static::assertArrayHasKey('track_url', $array, $message . 'track_url');
        static::assertArrayHasKey('publishable_key_payment', $array, $message . 'publishable_key_payment');
        static::assertArrayHasKey('publishable_key_checkout', $array, $message . 'publishable_key_checkout');
        static::assertArrayHasKey('publishable_key_back_office', $array, $message . 'publishable_key_back_office');
        static::assertArrayHasKey('create_order_url', $array, $message . 'create_order_url');
        static::assertArrayHasKey('save_order_url', $array, $message . 'save_order_url');
        static::assertArrayHasKey('get_hints_url', $array, $message . 'get_hints_url');
        static::assertArrayHasKey('selectors', $array, $message . 'selectors');
        static::assertArrayHasKey('shipping_prefetch_url', $array, $message . 'shipping_prefetch_url');
        static::assertArrayHasKey('prefetch_shipping', $array, $message . 'prefetch_shipping');
        static::assertArrayHasKey('save_email_url', $array, $message . 'save_email_url');
        static::assertArrayHasKey('pay_by_link_url', $array, $message . 'pay_by_link_url');
        static::assertArrayHasKey('quote_is_virtual', $array, $message . 'quote_is_virtual');
        static::assertArrayHasKey('totals_change_selectors', $array, $message . 'totals_change_selectors');
        static::assertArrayHasKey(
            'additional_checkout_button_class',
            $array,
            $message . 'additional_checkout_button_class'
        );
        static::assertArrayHasKey('initiate_checkout', $array, $message . 'initiate_checkout');
        static::assertArrayHasKey('toggle_checkout', $array, $message . 'toggle_checkout');
        static::assertArrayHasKey('is_pre_auth', $array, $message . 'is_pre_auth');
        static::assertArrayHasKey('default_error_message', $array, $message . 'default_error_message');
        static::assertArrayHasKey('button_css_styles', $array, $message . 'button_css_styles');
        static::assertArrayHasKey('is_instant_checkout_button', $array, $message . 'is_instant_checkout_button');
        static::assertArrayHasKey('cdn_url', $array, $message . 'cdn_url');
        static::assertArrayHasKey('always_present_checkout', $array, $message . 'always_present_checkout');
        $this->assertArrayHasKey('account_url', $array, $message . 'account_url');
        $this->assertArrayHasKey('order_management_selector', $array, $message . 'order_management_selector');
    }

    /**
     * @test
     * that getSettings returns JSON settings in which Pay By Link URL is null when that feature is disabled
     *
     * @covers ::getSettings
     */
    public function getSettings_ifPayByLinkFeatureIsDisabled_returnsSettingWithPayByLinkSetToNull()
    {
        $this->deciderMock->method('isPayByLinkEnabled')->willReturn(false);
        $result = $this->currentMock->getSettings();

        static::assertNull(json_decode($result, true)['pay_by_link_url']);
    }

    /**
     * @test
     * that getSettings returns JSON settings containing Pay By Link url when that feature is enabled
     *
     * @covers ::getSettings
     */
    public function getSettings_ifPayByLinkUrlIsEnabled_returnsSettingsWithPayByLink()
    {
        $this->deciderMock->method('isPayByLinkEnabled')->willReturn(true);
        $result = $this->currentMock->getSettings();

        static::assertEquals(
            HelperConfig::CDN_URL_PRODUCTION . '/checkout',
            json_decode($result, true)['pay_by_link_url']
        );
    }

    /**
     * @test
     * that getQuoteIsVirtual determines if checkout session quote is virtual
     *
     * @covers ::getQuoteIsVirtual
     *
     * @dataProvider getQuoteIsVirtual_withVariousQuotesProvider
     *
     * @param bool $isQuoteVirtual whether the current checkout session quote is virtual
     * @param bool $expectedResult of the tested method call
     *
     * @throws ReflectionException from isVirtual method
     */
    public function getQuoteIsVirtual_withVariousQuotes_returnsIsQuoteVirtual($isQuoteVirtual, $expectedResult)
    {
        $quoteMock = $this->createPartialMock(Quote::class, ['isVirtual']);
        $this->currentMock->expects(static::once())->method('getQuoteFromCheckoutSession')->willReturn($quoteMock);
        $quoteMock->expects(static::once())->method('isVirtual')->willReturn($isQuoteVirtual);

        static::assertEquals($expectedResult, TestHelper::invokeMethod($this->currentMock, 'getQuoteIsVirtual'));
    }

    /**
     * Data provider for {@see getQuoteIsVirtual_withVariousQuotes_returnsIsQuoteVirtual}
     *
     * @return array[] containing flags for virtual quote and expected result
     */
    public function getQuoteIsVirtual_withVariousQuotesProvider()
    {
        return [
            ['isQuoteVirtual' => true, 'expectedResult' => true],
            ['isQuoteVirtual' => false, 'expectedResult' => false],
        ];
    }

    /**
     * @test
     * that getBoltPopupErrorMessage returns Bolt popup error message
     * containing either store support or general contact email
     *
     * @covers ::getBoltPopupErrorMessage
     *
     * @dataProvider getBoltPopupErrorMessage_withVariousConfigurationsProvider
     *
     * @param array  $configMap            used to stub the return values of {@see \Magento\Framework\App\Config\ScopeConfigInterface::getValue}
     * @param string $expectedContactEmail to be contained in the error message
     *
     * @throws ReflectionException if _scopeConfig property doesn't exist
     */
    public function getBoltPopupErrorMessage_withVariousConfigurations_returnsBoltPopupErrorMessage(
        $configMap,
        $expectedContactEmail
    ) {
        /** add {@see \Magento\Framework\App\Config\ScopeConfigInterface::getValue} default values to map */
        $configMap = array_map(
            function ($config) {
                array_splice($config, 1, 0, [ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null]);
                return $config;
            },
            $configMap
        );

        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();

        $currentMock = $this->createPartialMock(Js::class, []);
        TestHelper::setProperty($currentMock, '_scopeConfig', $scopeConfigMock);
        $scopeConfigMock->method('getValue')->willReturnMap($configMap);

        static::assertEquals(
            "Your payment was successful and we're now processing your order. " .
            "If you don't receive order confirmation email in next 30 minutes, please contact us at $expectedContactEmail.",
            $currentMock->getBoltPopupErrorMessage()
        );
    }

    /**
     * Data provider for {@see getBoltPopupErrorMessage_withVariousConfigurations_returnsBoltPopupErrorMessage}
     *
     * @return array[] containing configMap used to stub the return values of
     *
     * @see \Magento\Framework\App\Config\ScopeConfigInterface::getValue
     * and expected contact email to be contained in the error message
     */
    public function getBoltPopupErrorMessage_withVariousConfigurationsProvider()
    {
        return [
            [
                'configMap' => [
                    ['trans_email/ident_support/email', 'support@bolt.com'],
                    ['trans_email/ident_general/email', 'info@bolt.com'],
                ],
                'expectedContactEmail' => 'support@bolt.com'
            ],
            [
                'configMap' => [
                    ['trans_email/ident_support/email', null],
                    ['trans_email/ident_general/email', 'info@bolt.com'],
                ],
                'expectedContactEmail' => 'info@bolt.com'
            ],
            [
                'configMap' => [
                    ['trans_email/ident_support/email', null],
                    ['trans_email/ident_general/email', null],
                ],
                'expectedContactEmail' => ''
            ],
        ];
    }

    /**
     * @test
     * that getTrackCallbacks returns array containing callbacks from configuration using the following methods:
     *
     * @see Js::getOnCheckoutStart as 'checkout_start'
     * @see Js::getOnEmailEnter as 'email_enter'
     * @see Js::getOnShippingDetailsComplete as 'shipping_details_complete'
     * @see Js::getOnShippingOptionsComplete as 'shipping_options_complete'
     * @see Js::getOnPaymentSubmit as 'payment_submit'
     * @see Js::getOnSuccess as 'success'
     * @see Js::getOnClose as 'close'
     *
     * @covers ::getTrackCallbacks
     */
    public function getTrackCallbacks_always_returnsTrackCallbacksValue()
    {
        $currentMock = $this->createPartialMock(
            Js::class,
            [
                'getOnCheckoutStart',
                'getOnEmailEnter',
                'getOnShippingDetailsComplete',
                'getOnShippingOptionsComplete',
                'getOnPaymentSubmit',
                'getOnSuccess',
                'getOnClose',
            ]
        );
        $onCheckoutStart = "dataLayer.push({'event': 'checkout_start'});";
        $onEmailEnter = "dataLayer.push({'event': 'email_enter'});";
        $onPaymentSubmit = "dataLayer.push({'event': 'on_payment_submit'});";
        $onSuccess = "dataLayer.push({'event': 'on_success'});";
        $onClose = "dataLayer.push({'event': 'on_close'});";
        $onShippingDetailsComplete = "dataLayer.push({'event': 'on_shipping_details_complete'});";
        $onShippingOptionsComplete = "dataLayer.push({'event': 'on_shipping_options_complete'});";

        $currentMock->expects(static::once())->method('getOnCheckoutStart')->willReturn($onCheckoutStart);
        $currentMock->expects(static::once())->method('getOnEmailEnter')->willReturn($onEmailEnter);
        $currentMock->expects(static::once())->method('getOnShippingDetailsComplete')->willReturn(
            $onShippingDetailsComplete
        );
        $currentMock->expects(static::once())->method('getOnShippingOptionsComplete')->willReturn(
            $onShippingOptionsComplete
        );
        $currentMock->expects(static::once())->method('getOnPaymentSubmit')->willReturn($onPaymentSubmit);
        $currentMock->expects(static::once())->method('getOnSuccess')->willReturn($onSuccess);
        $currentMock->expects(static::once())->method('getOnClose')->willReturn($onClose);

        $result = $currentMock->getTrackCallbacks();

        static::assertCount(static::TRACK_CALLBACK_NUMBER, $result);
        static::assertEquals(
            [
                'checkout_start'            => $onCheckoutStart,
                'email_enter'               => $onEmailEnter,
                'shipping_details_complete' => $onShippingDetailsComplete,
                'shipping_options_complete' => $onShippingOptionsComplete,
                'payment_submit'            => $onPaymentSubmit,
                'success'                   => $onSuccess,
                'close'                     => $onClose,
            ],
            $result
        );
    }

    /**
     * @test
     * that getOnCheckoutStart returns on checkout start callback from the config helper
     *
     * @covers ::getOnCheckoutStart
     *
     * @throws ReflectionException if getOnCheckoutStart method is not defined
     */
    public function getOnCheckoutStart_always_returnsCheckoutStart()
    {
        $checkoutStartCallback = "dataLayer.push({'event': 'checkout_start'});";
        $this->configHelper->expects(static::once())->method('getOnCheckoutStart')->willReturn($checkoutStartCallback);

        static::assertEquals(
            $checkoutStartCallback,
            TestHelper::invokeMethod($this->currentMock, 'getOnCheckoutStart')
        );
    }

    /**
     * @test
     * that getOnEmailEnter returns javascript code when a user enters or change mail
     *
     * @covers ::getOnEmailEnter
     *
     * @throws ReflectionException if getOnEmailEnter method is not defined
     */
    public function getOnEmailEnter_always_returnsEmailEntered()
    {
        $emailEntered = "dataLayer.push({'event': 'email_enter'});";
        $this->configHelper->expects(static::once())->method('getOnEmailEnter')->willReturn($emailEntered);
        $this->eventsForThirdPartyModules->expects(static::once())->method('runFilter')->with('getOnEmailEnter', $emailEntered)->willReturn('test onEmailEnter');
        static::assertEquals('test onEmailEnter', TestHelper::invokeMethod($this->currentMock, 'getOnEmailEnter'));
    }

    /**
     * @test
     * that getOnShippingDetailsComplete returns javascript callback to be executed when the user proceed
     * to the shipping options page from configuration using {@see \Bolt\Boltpay\Helper\Config::getOnShippingDetailsComplete}
     *
     * @covers ::getOnShippingDetailsComplete
     *
     * @throws ReflectionException if getOnShippingDetailsComplete method is not defined
     */
    public function getOnShippingDetailsComplete_always_returnsShippingDetailsCallback()
    {
        $shippingDetails = "dataLayer.push({'event': 'on_shipping_details_complete'});";

        $this->configHelper->expects(static::once())->method('getOnShippingDetailsComplete')->willReturn(
            $shippingDetails
        );

        static::assertEquals(
            $shippingDetails,
            TestHelper::invokeMethod($this->currentMock, 'getOnShippingDetailsComplete')
        );
    }

    /**
     * @test
     * that getOnShippingOptionsComplete returns javascript callback to be executed when the user proceeds to the
     * payment details page from configuration using {@see \Bolt\Boltpay\Helper\Config::getOnShippingOptionsComplete}
     *
     * @covers ::getOnShippingOptionsComplete
     *
     * @throws ReflectionException if getOnShippingOptionsComplete method is not defined
     */
    public function getOnShippingOptionsComplete_always_returnsShippingOptionsCompleteCallback()
    {
        $shippingOptions = "dataLayer.push({'event': 'on_shipping_options_complete'});";
        $this->configHelper->expects(static::once())->method('getOnShippingOptionsComplete')->willReturn(
            $shippingOptions
        );

        static::assertEquals(
            $shippingOptions,
            TestHelper::invokeMethod($this->currentMock, 'getOnShippingOptionsComplete')
        );
    }

    /**
     * @test
     * that getOnPaymentSubmit returns javascript callback to be executed when the user clicks the pay button
     * from configuration using {@see \Bolt\Boltpay\Helper\Config::getOnPaymentSubmit}
     *
     * @covers ::getOnPaymentSubmit
     *
     * @throws ReflectionException if getOnPaymentSubmit method is not defined
     */
    public function getOnPaymentSubmit_always_returnsOnPaymentSubmitCallback()
    {
        $paymentSubmit = "dataLayer.push({'event': 'on_payment_submit'});";
        $this->configHelper->expects(static::once())->method('getOnPaymentSubmit')->willReturn($paymentSubmit);

        static::assertEquals($paymentSubmit, TestHelper::invokeMethod($this->currentMock, 'getOnPaymentSubmit'));
    }

    /**
     * @test
     * that getOnSuccess returns javascript callback to be executed when the Bolt checkout transaction is successful
     * from configuration using {@see \Bolt\Boltpay\Helper\Config::getOnSuccess}
     *
     * @covers ::getOnSuccess
     *
     * @throws ReflectionException if getOnSuccess method is not defined
     */
    public function getOnSuccess_always_returnsOnSuccessCallback()
    {
        $success = "dataLayer.push({'event': 'on_success'});";
        $this->configHelper->expects(static::once())->method('getOnSuccess')->willReturn($success);

        static::assertEquals($success, TestHelper::invokeMethod($this->currentMock, 'getOnSuccess'));
    }

    /**
     * @test
     * that getOnClose returns javascript callback to be executed when the Bolt checkout modal is closed
     * from configuration using {@see \Bolt\Boltpay\Helper\Config::getOnClose}
     *
     * @covers ::getOnClose
     *
     * @throws ReflectionException if getOnClose method is not defined
     */
    public function getOnClose_always_returnsOnClosedCallback()
    {
        $closed = "dataLayer.push({'event': 'on_close'});";
        $this->configHelper->expects(static::once())->method('getOnClose')->willReturn($closed);

        static::assertEquals($closed, TestHelper::invokeMethod($this->currentMock, 'getOnClose'));
    }

    /**
     * @test
     * that getToggleCheckout returns toggle checkout config using {@see \Bolt\Boltpay\Helper\Config::getToggleCheckout}
     * only if its active property is set to true
     *
     * @covers ::getToggleCheckout
     *
     * @dataProvider getToggleCheckout_withVariousToggleCheckoutStatesProvider
     *
     * @param mixed $toggleCheckoutConfig value from the config helper
     * @param mixed $expectedResult       of the tested method call
     *
     * @throws ReflectionException if getToggleCheckout method is not defined
     */
    public function getToggleCheckout_withVariousToggleCheckoutStates_returnsToggleCheckoutConfiguration(
        $toggleCheckoutConfig,
        $expectedResult
    ) {
        $this->configHelper->expects(static::once())->method('getToggleCheckout')->willReturn($toggleCheckoutConfig);
        static::assertEquals($expectedResult, TestHelper::invokeMethod($this->currentMock, 'getToggleCheckout'));
    }

    /**
     * Data provider for {@see getToggleCheckout_withVariousToggleCheckoutStates_returnsToggleCheckoutConfiguration}
     *
     * @return array[] containing toggle checkout config and expected result of the tested method call
     */
    public function getToggleCheckout_withVariousToggleCheckoutStatesProvider()
    {
        return [
            ['toggleCheckoutConfig' => (object) ['active' => true], 'expectedResult' => (object) ['active' => true]],
            ['toggleCheckoutConfig' => (object) ['active' => false], 'expectedResult' => null],
            ['toggleCheckoutConfig' => null, 'expectedResult' => null],
        ];
    }

    /**
     * @test
     * that getIsPreAuth returns preauth configuration value for the current store using
     *
     * @see \Bolt\Boltpay\Helper\Config::getIsPreAuth
     *
     * @covers ::getIsPreAuth
     *
     * @dataProvider getIsPreAuth_withVariousPreAuthConfigurationsProvider
     *
     * @param int  $storeId        of the current store, stubbed result of {@see \Bolt\Boltpay\Block\BlockTrait::getStoreId}
     * @param bool $isPreAuth      configuration for the provided current store
     * @param bool $expectedResult of the tested method call
     *
     * @throws ReflectionException if getIsPreAuth method is not defined
     */
    public function getIsPreAuth_withVariousPreAuthConfigurations_returnsPreAuthConfiguration(
        $storeId,
        $isPreAuth,
        $expectedResult
    ) {
        $this->currentMock->expects(static::once())->method('getStoreId')->willReturn($storeId);
        $this->configHelper->expects(static::once())->method('getIsPreAuth')->with($storeId)->willReturn($isPreAuth);
        static::assertEquals($expectedResult, TestHelper::invokeMethod($this->currentMock, 'getIsPreAuth'));
    }

    /**
     * Data provider for {@see getIsPreAuth_withVariousPreAuthConfigurations_returnsPreAuthConfiguration}
     *
     * @return array[] containing current store id, preauth configuration and expected result of the tested method call
     */
    public function getIsPreAuth_withVariousPreAuthConfigurationsProvider()
    {
        return [
            ['storeId' => 2, 'isPreAuth' => true, 'expectedResult' => true],
            ['storeId' => 0, 'isPreAuth' => true, 'expectedResult' => true],
            ['storeId' => 1, 'isPreAuth' => false, 'expectedResult' => false],
        ];
    }

    /**
     * @test
     * that getQuoteFromCheckoutSession returns quote from checkout session
     *
     * @see \Magento\Checkout\Model\Session::getQuote
     * @see \Magento\Backend\Model\Session\Quote::getQuote
     *
     * @covers ::getQuoteFromCheckoutSession
     *
     * @throws ReflectionException if getQuoteFromCheckoutSession method is undefined
     */
    public function getQuoteFromCheckoutSession_always_returnsQuote()
    {
        $quoteMock = $this->createMock(Quote::class);
        $this->checkoutSessionMock->expects(static::once())->method('getQuote')->willReturn($quoteMock);

        static::assertEquals(
            $quoteMock,
            TestHelper::invokeMethod($this->currentMock, 'getQuoteFromCheckoutSession')
        );
    }

    /**
     * @test
     * that getModuleVersion returns module version from the config helper
     *
     * @see \Bolt\Boltpay\Helper\Config::getModuleVersion
     *
     * @covers ::getModuleVersion
     */
    public function getModuleVersion_always_returnsModuleVersion()
    {
        $moduleVersion = '2.2.3';
        $this->configHelper->expects(static::once())->method('getModuleVersion')->willReturn($moduleVersion);

        static::assertEquals($moduleVersion, $this->currentMock->getModuleVersion());
    }

    /**
     * @test
     * that minifyJs returns unaltered javascript if minification is not active in configuration
     *
     * @see \Bolt\Boltpay\Helper\Config::shouldMinifyJavascript
     *
     * @covers ::minifyJs
     *
     * @throws Exception from Minifier::minify method
     */
    public function minifyJs_whenMinifyJavascriptIsUnset_returnsUnalteredJs()
    {
        $jsCode = 'var helpers = [];';
        $this->configHelper->expects(static::once())->method('shouldMinifyJavascript')->willReturn(false);
        static::assertEquals($jsCode, $this->currentMock->minifyJs($jsCode));
    }

    /**
     * @test
     * that minifyJs returns minified version of the provided javascript if the javascript minification is enabled in config
     *
     * @see \JShrink\Minifier::minify
     *
     * @covers ::minifyJs
     *
     * @throws Exception from Minifier::minify method
     */
    public function minifyJs_whenMinifyJavascriptIsSet_returnsMinifiedJs()
    {
        $jsCode = <<<'JS'
var isObject = function (item) {
    return (item && typeof item === 'object' && !Array.isArray(item));
};
JS;
        $this->configHelper->expects(static::once())->method('shouldMinifyJavascript')->willReturn(true);
        static::assertEquals(
            "var isObject=function(item){return(item&&typeof item==='object'&&!Array.isArray(item));};",
            $this->currentMock->minifyJs($jsCode)
        );
    }

    /**
     * @test
     * that minifyJs returns the provided javascript unchanged if an exception occurs during the minification process
     *
     * @covers ::minifyJs
     *
     * @throws Exception from Minifier::minify method
     */
    public function minifyJs_whenAnExceptionOccursDuringMinification_returnsUnchangedJavascript()
    {
        $badFormatJs = []; // will throw exception because it is not a string as expected
        $this->configHelper->expects(static::once())->method('shouldMinifyJavascript')->willReturn(true);

        $this->bugsnagHelperMock->expects(static::once())
            ->method('notifyException')
            ->with(static::isInstanceOf(Exception::class));

        /** @noinspection PhpParamsInspection */
        static::assertEquals($badFormatJs, $this->currentMock->minifyJs($badFormatJs));
    }

    /**
     * @test
     * that isOnPageFromWhiteList returns whether current action is in whitelisted pages returned from
     *
     * @see \Bolt\Boltpay\Block\BlockTrait::getPageWhitelist
     *
     * @covers ::isOnPageFromWhiteList
     *
     * @dataProvider isOnPageFromWhiteList_withVariousActionsProvider
     *
     * @param string $currentAction  identifier, stubbed result of {@see \Magento\Framework\App\Request\Http::getFullActionName}
     * @param bool   $expectedResult of the tested method call
     *
     * @throws ReflectionException if isOnPageFromWhiteList method is not defined
     */
    public function isOnPageFromWhiteList_withVariousActions_returnsWhitelistedState($currentAction, $expectedResult)
    {
        $this->currentMock->expects(static::once())->method('getRequest')->willReturnSelf();
        $this->currentMock->expects(static::once())->method('getFullActionName')->willReturn($currentAction);
        $this->currentMock->expects(static::once())->method('getPageWhitelist')
            ->willReturn(HelperConfig::$defaultPageWhitelist);

        static::assertEquals($expectedResult, TestHelper::invokeMethod($this->currentMock, 'isOnPageFromWhiteList'));
    }

    /**
     * Data provider for {@see isOnPageFromWhiteList_withVariousActions_returnsWhitelistedState}
     *
     * @return array[] containing current currentAction and expected result of the tested method call
     */
    public function isOnPageFromWhiteList_withVariousActionsProvider()
    {
        return [
            ['currentAction' => HelperConfig::SHOPPING_CART_PAGE_ACTION, 'expectedResult' => true],
            ['currentAction' => HelperConfig::CHECKOUT_PAGE_ACTION, 'expectedResult' => true],
            ['currentAction' => HelperConfig::SUCCESS_PAGE_ACTION, 'expectedResult' => true],
            ['currentAction' => 'catalog_product_view', 'expectedResult' => false],
            ['currentAction' => 'customer_account_login', 'expectedResult' => false],
            ['currentAction' => 'customer_account_index', 'expectedResult' => false],
            ['currentAction' => 'wishlist_index_index', 'expectedResult' => false],
        ];
    }

    /**
     * @test
     * that isMinicartEnabled returns minicart support configuration from
     *
     * @see \Bolt\Boltpay\Helper\Config::getMinicartSupport
     *
     * @covers ::isMinicartEnabled
     *
     * @dataProvider isMinicartEnabled_withVariousMinicartSupportStatesProvider
     *
     * @param bool $minicartSupport configuration flag, stubbed result of {@see \Bolt\Boltpay\Helper\Config::getMinicartSupport}
     * @param bool $expectedResult  of the tested method call
     */
    public function isMinicartEnabled_withVariousMinicartSupportStates_returnsMinicartState(
        $minicartSupport,
        $expectedResult
    ) {
        $this->configHelper->expects(static::once())->method('getMinicartSupport')->willReturn($minicartSupport);

        static::assertEquals($expectedResult, $this->currentMock->isMinicartEnabled());
    }

    /**
     * Data provider for {@see isMinicartEnabled_withVariousMinicartSupportStates_returnsMinicartState}
     *
     * @return array[] containing flags for minicart support config and expected result of the tested method call
     */
    public function isMinicartEnabled_withVariousMinicartSupportStatesProvider()
    {
        return [
            ['minicartSupport' => true, 'expectedResult' => true],
            ['minicartSupport' => false, 'expectedResult' => false],
        ];
    }

    /**
     * @test
     *
     * @dataProvider isLoggedIn_withVariousConfigsProvider
     *
     * @param int  $isLoggedIn
     * @param bool $expectedResult
     */
    public function isLoggedIn_withVariousConfigs_returnsCorrectResult(
        $isLoggedIn,
        $expectedResult
    ) {
        $this->httpContextMock->expects(static::once())->method('getValue')->with(\Magento\Customer\Model\Context::CONTEXT_AUTH)->willReturn($isLoggedIn);
        static::assertEquals($expectedResult, $this->currentMock->isLoggedIn());
    }

    /**
     * Data provider for {@see isLoggedIn_withVariousConfigs_returnsCorrectResult}
     *
     * @return array
     */
    public function isLoggedIn_withVariousConfigsProvider()
    {
        return [
            ['isLoggedIn' => 1, 'expectedResult' => true],
            ['isLoggedIn' => 0, 'expectedResult' => false],
        ];
    }

    /**
     * @test
     * that isBoltProductPage returns true only if product page checkout is enabled and the current page is product page
     * otherwise false
     *
     * @covers ::isBoltProductPage
     *
     * @dataProvider isBoltProductPage_withVariousProductCheckoutStatesAndVariousPagesProvider
     *
     * @param bool   $productPageCheckoutFlag from the config helper
     * @param string $fullActionName          of the current request
     * @param bool   $expectedResult          of the tested method call
     */
    public function isBoltProductPage_withVariousProductCheckoutStatesAndVariousPages_returnsProductPageState(
        $productPageCheckoutFlag,
        $fullActionName,
        $expectedResult
    ) {
        $this->currentMock->expects(static::once())
            ->method('getRequest')
            ->willReturnSelf();
        $this->currentMock->expects(static::once())
            ->method('getFullActionName')
            ->willReturn($fullActionName);
            
        $this->configHelper->expects(($fullActionName == 'catalog_product_view') ? static::once() : static::never())
            ->method('getProductPageCheckoutFlag')
            ->willReturn($productPageCheckoutFlag);

        static::assertEquals($expectedResult, $this->currentMock->isBoltProductPage());
    }

    /**
     * Data provider for
     *
     * @see isBoltProductPage_withVariousProductCheckoutStatesAndVariousPages_returnsProductPageState
     *
     * @return array[] containing current page names and flag for product page checkout and
     *                 expected result of the tested method call
     */
    public function isBoltProductPage_withVariousProductCheckoutStatesAndVariousPagesProvider()
    {
        return [
            [
                'productPageCheckoutFlag' => true,
                'fullActionName'          => 'catalog_product_view',
                'expectedResult'          => true,
            ],
            [
                'productPageCheckoutFlag' => false,
                'fullActionName'          => 'catalog_product_view',
                'expectedResult'          => false,
            ],
            [
                'productPageCheckoutFlag' => true,
                'fullActionName'          => HelperConfig::SHOPPING_CART_PAGE_ACTION,
                'expectedResult'          => false,
            ],
            [
                'productPageCheckoutFlag' => true,
                'fullActionName'          => HelperConfig::CHECKOUT_PAGE_ACTION,
                'expectedResult'          => false,
            ],
            [
                'productPageCheckoutFlag' => true,
                'fullActionName'          => HelperConfig::SUCCESS_PAGE_ACTION,
                'expectedResult'          => false,
            ],
        ];
    }

    /**
     * @test
     * that isEnabled returns whether Bolt module is enabled for the current store from
     *
     * @see \Bolt\Boltpay\Helper\Config::isActive
     *
     * @covers \Bolt\Boltpay\Block\BlockTrait::isEnabled
     *
     * @dataProvider isEnabled_withVariousConfigActiveStatesProvider
     *
     * @param bool $isActive       configuration flag for the current store, stubbed result of {@see \Bolt\Boltpay\Helper\Config::isActive}
     * @param bool $expectedResult of the tested method call
     */
    public function isEnabled_withVariousConfigActiveStates_returnsBoltPaymentModuleIsActive(
        $isActive,
        $areaCode,
        $expectedResult
    ) {
        $this->_appState = $this->createPartialMock(
            State::class,
            ['getAreaCode']
        );
        TestHelper::setProperty($this->currentMock, '_appState', $this->_appState);
        $this->_appState->expects(self::once())->method('getAreaCode')->willReturn($areaCode);
        $this->currentMock->expects(static::once())->method('getStoreId')->willReturn(static::STORE_ID);
        $this->configHelper->expects($areaCode == Area::AREA_ADMINHTML ? static::never() : static::once())
            ->method('isActive')
            ->with(static::STORE_ID)
            ->willReturn($isActive);

        static::assertEquals($expectedResult, $this->currentMock->isEnabled());
    }

    /**
     * Data provider for {@see isEnabled_withVariousConfigActiveStates_returnsBoltPaymentModuleIsActive}
     *
     * @return array[] containing flag for config active state and expected result of the tested method call
     */
    public function isEnabled_withVariousConfigActiveStatesProvider()
    {
        return [
            ['isActive' => false, 'areaCode' => Area::AREA_WEBAPI_SOAP, 'expectedResult' => false],
            ['isActive' => true, 'areaCode' => Area::AREA_WEBAPI_SOAP, 'expectedResult' => true],
            ['isActive' => false, 'areaCode' => Area::AREA_ADMINHTML, 'expectedResult' => true],
            ['isActive' => true, 'areaCode' => Area::AREA_ADMINHTML, 'expectedResult' => true],
        ];
    }

    /**
     * @test
     * that getInitiateCheckout retrieves 'bolt_initiate_checkout' property from the session and unsets it before returning it
     *
     * @covers ::getInitiateCheckout
     *
     * @dataProvider getInitiateCheckout_withVariousInitiateStatesProvider
     *
     * @param bool $boltInitiateCheckout session property flag
     * @param bool $expectedResult       of the tested method call
     */
    public function getInitiateCheckout_withVariousSessionStates_determinesIfCheckoutShouldBeInitiatedAutomatically(
        $boltInitiateCheckout,
        $expectedResult
    ) {
        $this->checkoutSessionMock->expects(static::once())
            ->method('getBoltInitiateCheckout')
            ->willReturn($boltInitiateCheckout);
        $this->checkoutSessionMock->expects(static::once())->method('unsBoltInitiateCheckout');

        static::assertEquals($expectedResult, $this->currentMock->getInitiateCheckout());
    }

    /**
     * Data provider for {@see getInitiateCheckout_withVariousSessionStates_determinesIfCheckoutShouldBeInitiatedAutomatically}
     *
     * @return array[] containing boltInitiateCheckout session property flag and expected result of the tested method call
     */
    public function getInitiateCheckout_withVariousInitiateStatesProvider()
    {
        return [
            ['boltInitiateCheckout' => true, 'expectedResult' => true],
            ['boltInitiateCheckout' => false, 'expectedResult' => false],
        ];
    }

    /**
     * @test
     * that getIsInstantCheckoutButton returns if the instant checkout feature is enabled
     * {@see \Bolt\Boltpay\Helper\FeatureSwitch\Decider::isInstantCheckoutButton}
     *
     * @covers ::getIsInstantCheckoutButton
     *
     * @dataProvider getIsInstantCheckoutButton_withVariousInstantCheckoutButtonStateProvider
     *
     * @param bool $instantCheckoutButton feature switcher flag
     * @param bool $expectedResult        of the tested method call
     */
    public function getIsInstantCheckoutButton_withVariousInstantCheckoutFeatureStates_returnsButtonState(
        $instantCheckoutButton,
        $expectedResult
    ) {
        $this->deciderMock->expects(static::once())->method('isInstantCheckoutButton')->willReturn($instantCheckoutButton);
        static::assertEquals($expectedResult, $this->currentMock->getIsInstantCheckoutButton());
    }

    /**
     * Data provider for {@see getIsInstantCheckoutButton_withVariousInstantCheckouFeatureStates_returnsButtonState}
     *
     * @return array[] containing configuration flag for instant checkout button and
     *                 expected result of the tested method call
     */
    public function getIsInstantCheckoutButton_withVariousInstantCheckoutButtonStateProvider()
    {
        return [
            ['instantCheckoutButton' => true, 'expectedResult' => true],
            ['instantCheckoutButton' => false, 'expectedResult' => false],
        ];
    }

    /**
     * @test
     * that shouldDisableBoltCheckout returns true if one of the following statements is correct:
     * 1. M2_BOLT_ENABLED feature switch is disabled
     * {@see \Bolt\Boltpay\Helper\FeatureSwitch\Decider::isBoltEnabled returns false}
     * 2. Bolt is disabled in configuration
     * {@see \Bolt\Boltpay\Block\BlockTrait::isEnabled returns false}
     * 3. Current request action is restricted
     * {@see \Bolt\Boltpay\Block\BlockTrait::isPageRestricted returns true}
     * 4. IP address is restricted
     * {@see \Bolt\Boltpay\Block\BlockTrait::isIPRestricted returns true}
     * 5. One of the required keys is missing
     * {@see \Bolt\Boltpay\Block\BlockTrait::isKeyMissing returns true}
     *
     * @covers \Bolt\Boltpay\Block\BlockTrait::shouldDisableBoltCheckout
     *
     * @dataProvider shouldDisableBoltCheckout_withVariousConfigurationsProvider
     *
     * @param bool $isBoltFeatureEnabled whether M2_BOLT_ENABLED feature is enabled
     * @param bool $isEnabled            whether Bolt is active (enabled) for the current store in configuration
     * @param bool $isPageRestricted     whether Bolt checkout is restricted on the current loading page
     * @param bool $isIPRestricted       whether the client IP is restricted
     * @param bool $isKeyMissing         whether one of the required keys is missing in configuration
     *
     * @throws ReflectionException
     */
    public function shouldDisableBoltCheckout_withVariousConfigurations_determinesIfBoltShouldBeDisabled(
        $isBoltFeatureEnabled,
        $isEnabled,
        $isPageRestricted,
        $isIPRestricted,
        $isKeyMissing
    ) {
        $currentMock = $this->createPartialMock(
            Js::class,
            [
                'isEnabled',
                'isIPRestricted',
                'isKeyMissing',
                'getPageBlacklist',
                'getIPWhitelistArray',
                'getRequest',
                'getFullActionName'
            ]
        );
        $this->deciderMock->method('isBoltEnabled')->willReturn($isBoltFeatureEnabled);
        TestHelper::setProperty($currentMock, 'featureSwitches', $this->deciderMock);
        TestHelper::setProperty($currentMock, 'configHelper', $this->configHelper);
        $currentMock->method('isEnabled')->willReturn($isEnabled);

        // stub \Bolt\Boltpay\Block\BlockTrait::isPageRestricted start
        $currentMock->method('getRequest')->willReturnSelf();
        $currentMock->method('getPageBlacklist')->willReturn($isPageRestricted ? [null] : []);
        // stub \Bolt\Boltpay\Block\BlockTrait::isPageRestricted end

        $currentMock->method('isIPRestricted')->willReturn($isIPRestricted);
        $currentMock->method('isKeyMissing')->willReturn($isKeyMissing);
        $expected_value = !$isBoltFeatureEnabled || !$isEnabled || $isPageRestricted || $isIPRestricted || $isKeyMissing;
        $this->eventsForThirdPartyModules->expects(static::once())->method('runFilter')->with('filterShouldDisableBoltCheckout', $expected_value, $currentMock)->willReturn($expected_value);

        static::assertEquals(
            $expected_value,
            $currentMock->shouldDisableBoltCheckout()
        );
    }

    /**
     * Data provider for {@see shouldDisableBoltCheckout_withVariousConfigurations_determinesIfBoltShouldBeDisabled}
     *
     * @return bool[][] containing
     *                  isBoltFeatureEnabled - whether M2_BOLT_ENABLED feature is enabled
     *                  isEnabled - whether Bolt is active (enabled) for the current store in configuration
     *                  isPageRestricted - whether Bolt checkout is restricted on the current loading page
     *                  isIPRestricted - whether the client IP is restricted
     *                  isKeyMissing - whether one of the required keys is missing in configuration
     */
    public function shouldDisableBoltCheckout_withVariousConfigurationsProvider()
    {
        return TestHelper::getAllBooleanCombinations(5);
    }

    /**
     * @test
     * that shouldTrackCheckoutFunnel returns default checkout funnel transition tracking configuration from
     *
     * @see \Bolt\Boltpay\Helper\Config::shouldTrackCheckoutFunnel
     *
     * @covers \Bolt\Boltpay\Block\BlockTrait::shouldTrackCheckoutFunnel
     *
     * @dataProvider shouldTrackCheckoutFunnel_withVariousConfigStatesProvider
     *
     * @param bool $shouldTrackCheckout flag
     * @param bool $expectedResult      of the tested method call
     */
    public function shouldTrackCheckoutFunnel_withVariousConfigStates_returnsTrackCheckoutState(
        $shouldTrackCheckout,
        $expectedResult
    ) {
        $this->configHelper->expects(static::once())
            ->method('shouldTrackCheckoutFunnel')
            ->willReturn($shouldTrackCheckout);

        static::assertEquals($expectedResult, $this->currentMock->shouldTrackCheckoutFunnel());
    }

    /**
     * Data provider for {@see shouldTrackCheckoutFunnel_withVariousConfigStates_returnsTrackCheckoutState}
     *
     * @return array[] containing should track checkout flag and excepted result of the tested method call
     */
    public function shouldTrackCheckoutFunnel_withVariousConfigStatesProvider()
    {
        return [
            ['shouldTrackCheckout' => false, 'expectedResult' => false],
            ['shouldTrackCheckout' => true, 'expectedResult' => true],
        ];
    }

    /**
     * @test
     * that enableAlwaysPresentCheckoutButton returns true only if both:
     * 1. always present checkout is enabled for the current store in the configuration
     * {@see \Bolt\Boltpay\Helper\Config::isAlwaysPresentCheckoutEnabled returns true}
     * 2. feature switch M2_ALWAYS_PRESENT_CHECKOUT is enabled
     * {@see \Bolt\Boltpay\Helper\FeatureSwitch\Decider::isAlwaysPresentCheckoutEnabled returns true}
     *
     * @covers ::enableAlwaysPresentCheckoutButton
     *
     * @dataProvider enableAlwaysPresentCheckoutButton_withVariousConfigAndDeciderStatesProvider
     *
     * @param bool $isAlwaysPresentCheckoutConfigurationEnabled flag
     * @param bool $isAlwaysPresentCheckoutFeatureSwitchEnabled flag
     * @param bool $expectedResult                              of the tested method call
     */
    public function enableAlwaysPresentCheckoutButton_withVariousConfigAndDeciderStates_returnsPluginSettings(
        $isAlwaysPresentCheckoutConfigurationEnabled,
        $isAlwaysPresentCheckoutFeatureSwitchEnabled,
        $expectedResult
    ) {
        $this->currentMock->expects(static::once())->method('getStoreId')->willReturn(static::STORE_ID);
        $this->configHelper->expects(static::once())
            ->method('isAlwaysPresentCheckoutEnabled')
            ->with(static::STORE_ID)
            ->willReturn($isAlwaysPresentCheckoutConfigurationEnabled);

        $this->deciderMock->expects($isAlwaysPresentCheckoutConfigurationEnabled ? static::once() : static::never())
            ->method('isAlwaysPresentCheckoutEnabled')
            ->willReturn($isAlwaysPresentCheckoutFeatureSwitchEnabled);

        static::assertEquals($expectedResult, $this->currentMock->enableAlwaysPresentCheckoutButton());
    }

    /**
     * Data provider for {@see enableAlwaysPresentCheckoutButton_withVariousConfigAndDeciderStates_returnsPluginSettings}
     *
     * @return array[] containing flags is config checkout enabled, is decider switch enabled and
     *                 expected result of the tested method call
     */
    public function enableAlwaysPresentCheckoutButton_withVariousConfigAndDeciderStatesProvider()
    {
        return [
            [
                'isAlwaysPresentCheckoutConfigurationEnabled' => true,
                'isAlwaysPresentCheckoutFeatureSwitchEnabled' => true,
                'expectedResult'                              => true
            ],
            [
                'isAlwaysPresentCheckoutConfigurationEnabled' => false,
                'isAlwaysPresentCheckoutFeatureSwitchEnabled' => true,
                'expectedResult'                              => false
            ],
            [
                'isAlwaysPresentCheckoutConfigurationEnabled' => true,
                'isAlwaysPresentCheckoutFeatureSwitchEnabled' => false,
                'expectedResult'                              => false
            ],
        ];
    }

    /**
     * @test
     * that getPrefetchShipping returns true only if both:
     * 1. prefetch shipping is enabled for the current store in the configuration
     * {@see \Bolt\Boltpay\Helper\Config::getPrefetchShipping returns true}
     * 2. feature switch M2_PREFETCH_SHIPPING is enabled
     * {@see \Bolt\Boltpay\Helper\FeatureSwitch\Decider::isPrefetchShippingEnabled returns true}
     *
     * @covers ::getPrefetchShipping
     *
     * @dataProvider getPrefetchShipping_withVariousConfigAndDeciderStatesProvider
     *
     * @param bool $isPrefetchShippingConfigurationEnabled flag
     * @param bool $isPrefetchShippingFeatureSwitchEnabled flag
     * @param bool $expectedResult                         of the tested method call
     */
    public function getPrefetchShipping_withVariousConfigAndDeciderStates_returnsPluginSettings(
        $isPrefetchShippingConfigurationEnabled,
        $isPrefetchShippingFeatureSwitchEnabled,
        $expectedResult
    ) {
        $this->currentMock->expects(static::once())->method('getStoreId')->willReturn(static::STORE_ID);
        $this->configHelper->expects(static::once())
            ->method('getPrefetchShipping')
            ->with(static::STORE_ID)
            ->willReturn($isPrefetchShippingConfigurationEnabled);

        $this->deciderMock->expects($isPrefetchShippingConfigurationEnabled ? static::once() : static::never())
            ->method('isPrefetchShippingEnabled')
            ->willReturn($isPrefetchShippingFeatureSwitchEnabled);

        static::assertEquals($expectedResult, $this->currentMock->getPrefetchShipping());
    }

    /**
     * Data provider for {@see getPrefetchShipping_withVariousConfigAndDeciderStates_returnsPluginSettings}
     *
     * @return array[] containing flags is config checkout enabled, is decider switch enabled and
     *                 expected result of the tested method call
     */
    public function getPrefetchShipping_withVariousConfigAndDeciderStatesProvider()
    {
        return [
            [
                'isPrefetchShippingConfigurationEnabled' => true,
                'isPrefetchShippingFeatureSwitchEnabled' => true,
                'expectedResult'                         => true
            ],
            [
                'isPrefetchShippingConfigurationEnabled' => false,
                'isPrefetchShippingFeatureSwitchEnabled' => true,
                'expectedResult'                         => false
            ],
            [
                'isPrefetchShippingConfigurationEnabled' => true,
                'isPrefetchShippingFeatureSwitchEnabled' => false,
                'expectedResult'                         => false
            ],
        ];
    }

    /**
     * @test
     * that getButtonCssStyles returns CSS style for Bolt button according to config button color
     *
     * @covers ::getButtonCssStyles
     *
     * @dataProvider getButtonCssStyles_withVariousConfigButtonColorsProvider
     *
     * @param string $configButtonColor from the config helper
     * @param string $expectedResult    of the tested method call
     */
    public function getButtonCssStyles_withVariousConfigButtonColors_returnsBoltButtonStyle(
        $configButtonColor,
        $expectedResult
    ) {
        $this->configHelper->expects(static::once())->method('getButtonColor')->willReturn($configButtonColor);
        static::assertEquals($expectedResult, $this->currentMock->getButtonCssStyles());
    }

    /**
     * Data provider for {@see getButtonCssStyles_withVariousConfigButtonColors_returnsBoltButtonStyle}
     *
     * @return array[] containing config button css style and expected result of the tested method call
     */
    public function getButtonCssStyles_withVariousConfigButtonColorsProvider()
    {
        return [
            ['configButtonColor' => '', 'expectedResult' => ''],
            ['configButtonColor' => null, 'expectedResult' => ''],
            ['configButtonColor' => false, 'expectedResult' => ''],
            ['configButtonColor' => '#AA00AA', 'expectedResult' => '--bolt-primary-action-color:#AA00AA;'],
            ['configButtonColor' => 'not validated', 'expectedResult' => '--bolt-primary-action-color:not validated;'],
        ];
    }

    /**
     * @test
     */
    public function getButtonCssStyles_prependsDisplayNone()
    {
        $this->configHelper->expects(static::once())->method('getToggleCheckout')->willReturn((object) ['active' => true]);
        $this->configHelper->expects(static::once())->method('getButtonColor')->willReturn('#EEEEEE');
        static::assertEquals('display:none;--bolt-primary-action-color:#EEEEEE;', $this->currentMock->getButtonCssStyles());
    }

    /**
     * @test
     * that isOrderManagementEnabled returns true only if it is enabled both in feature switch and config
     *
     * @covers ::isOrderManagementEnabled
     *
     * @dataProvider isOrderManagementEnabled_withVariousOrderManagementAvailabilityProvider
     *
     * @param bool $isOrderManagementEnabledInConfiguration flag
     * @param bool $isOrderManagementFeatureSwitchEnabled   flag
     * @param bool $expectedResult                          of the tested method call
     */
    public function isOrderManagementEnabled_withVariousOrderManagementAvailability_returnsOrderManagementAvailability(
        $isOrderManagementEnabledInConfiguration,
        $isOrderManagementFeatureSwitchEnabled,
        $expectedResult
    ) {
        $this->configHelper->expects(static::once())->method('isOrderManagementEnabled')
            ->willReturn($isOrderManagementEnabledInConfiguration);
        $this->deciderMock->expects($isOrderManagementEnabledInConfiguration ? static::once() : static::never())
            ->method('isOrderManagementEnabled')
            ->willReturn($isOrderManagementFeatureSwitchEnabled);

        static::assertEquals($expectedResult, $this->currentMock->isOrderManagementEnabled());
    }

    /**
     * Data provider for
     *
     * @see isOrderManagementEnabled_withVariousOrderManagementAvailability_returnsOrderManagementAvailability
     *
     * @return array[] containing order management status in configuration and feature switcher
     *                 and expected result of the method call
     */
    public function isOrderManagementEnabled_withVariousOrderManagementAvailabilityProvider()
    {
        return [
            [
                'isOrderManagementEnabledInConfiguration' => true,
                'isOrderManagementFeatureSwitchEnabled'   => true,
                'expectedResult'                          => true
            ],
            [
                'isOrderManagementEnabledInConfiguration' => false,
                'isOrderManagementFeatureSwitchEnabled'   => true,
                'expectedResult'                          => false
            ],
            [
                'isOrderManagementEnabledInConfiguration' => true,
                'isOrderManagementFeatureSwitchEnabled'   => false,
                'expectedResult'                          => false
            ],
            [
                'isOrderManagementEnabledInConfiguration' => false,
                'isOrderManagementFeatureSwitchEnabled'   => false,
                'expectedResult'                          => false
            ],
        ];
    }

    /**
     * @test
     *
     * @dataProvider isBoltSSOEnabled_withVariousConfigsProvider
     *
     * @param bool $isBoltSSOInConfig
     * @param bool $isBoltSSOFeatureSwitchEnabled
     * @param bool $expectedResult
     */
    public function isBoltSSOEnabled_withVariousConfigs_returnsCorrectResult(
        $isBoltSSOInConfig,
        $isBoltSSOFeatureSwitchEnabled,
        $expectedResult
    ) {
        $this->configHelper->expects(static::once())->method('isBoltSSOEnabled')
            ->willReturn($isBoltSSOInConfig);
        $this->deciderMock->expects($isBoltSSOInConfig ? static::once() : static::never())
            ->method('isBoltSSOEnabled')
            ->willReturn($isBoltSSOFeatureSwitchEnabled);

        static::assertEquals($expectedResult, $this->currentMock->isBoltSSOEnabled());
    }

    /**
     * Data provider for
     *
     * @see isBoltSSOEnabled_withVariousConfigs_returnsCorrectResult
     *
     * @return array
     */
    public function isBoltSSOEnabled_withVariousConfigsProvider()
    {
        return [
            [
                'isBoltSSOInConfig'             => true,
                'isBoltSSOFeatureSwitchEnabled' => true,
                'expectedResult'                => true
            ],
            [
                'isBoltSSOInConfig'             => false,
                'isBoltSSOFeatureSwitchEnabled' => true,
                'expectedResult'                => false
            ],
            [
                'isBoltSSOInConfig'             => true,
                'isBoltSSOFeatureSwitchEnabled' => false,
                'expectedResult'                => false
            ],
            [
                'isBoltSSOInConfig'             => false,
                'isBoltSSOFeatureSwitchEnabled' => false,
                'expectedResult'                => false
            ],
        ];
    }

    /**
     * @test
     * that getOrderManagementSelector returns order management css selector if:
     * 1. config order management is enabled
     * 2. feature switch order management is enabled
     *
     * @covers ::getOrderManagementSelector
     *
     * @dataProvider getOrderManagementSelector_withVariousOrderManagementAvailabilitiesProvider
     *
     * @param bool   $configOrderManagementEnabled flag from {@see \Bolt\Boltpay\Helper\Config::isOrderManagementEnabled}
     * @param bool   $fsOrderManagementEnabled     flag from {@see \Bolt\Boltpay\Helper\FeatureSwitch\Decider::isOrderManagementEnabled}
     * @param string $orderManagementSelector      value from {@see \Bolt\Boltpay\Helper\Config::getOrderManagementSelector}
     * @param mixed  $expectedResult               of the tested method call
     */
    public function getOrderManagementSelector_withVariousOrderManagementAvailabilities_returnsOrderManagementSelector(
        $configOrderManagementEnabled,
        $fsOrderManagementEnabled,
        $orderManagementSelector,
        $expectedResult
    ) {
        $this->configHelper->expects(static::once())
            ->method('isOrderManagementEnabled')
            ->willReturn($configOrderManagementEnabled);
        $this->deciderMock->expects($configOrderManagementEnabled ? static::once() : static::never())
            ->method('isOrderManagementEnabled')
            ->willReturn($fsOrderManagementEnabled);
        $this->configHelper
            ->expects($configOrderManagementEnabled && $fsOrderManagementEnabled ? static::once() : static::never())
            ->method('getOrderManagementSelector')
            ->willReturn($orderManagementSelector);

        $this->assertEquals($expectedResult, $this->currentMock->getOrderManagementSelector());
    }

    /**
     * Data provider for
     * {@see getOrderManagementSelector_withVariousOrderManagementAvailabilities_returnsOrderManagementSelector}
     *
     * @return array[] containing
     *                 1. config order management enabled flag
     *                 2. feature switch order management enabled flag
     *                 3. expected result of the tested method call
     */
    public function getOrderManagementSelector_withVariousOrderManagementAvailabilitiesProvider()
    {
        return [
            [
                'configOrderManagementEnabled' => false,
                'fsOrderManagementEnabled'     => false,
                'orderManagementSelector'      => '#order-example-selector',
                'expectedResult'               => '',
            ],
            [
                'configOrderManagementEnabled' => false,
                'fsOrderManagementEnabled'     => true,
                'orderManagementSelector'      => '#order-example-selector',
                'expectedResult'               => '',
            ],
            [
                'configOrderManagementEnabled' => true,
                'fsOrderManagementEnabled'     => false,
                'orderManagementSelector'      => '#order-example-selector',
                'expectedResult'               => '',
            ],
            [
                'configOrderManagementEnabled' => true,
                'fsOrderManagementEnabled'     => true,
                'orderManagementSelector'      => '#order-example-selector',
                'expectedResult'               => '#order-example-selector',
            ],
        ];
    }

    /**
     * @test
     * that isBlockAlreadyShown returns true only once per provided block type
     *
     * @covers ::isBlockAlreadyShown
     *
     * @dataProvider isBlockAlreadyShown_withVariousBlockTypeStatesProvider
     *
     * @param string $blockType                 name value
     * @param bool   $blockAlreadyShownProperty configuration flag
     * @param bool   $expectedResult            of the tested method call
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function isBlockAlreadyShown_withVariousBlockTypeStates_returnsBlockTypeShownState(
        $blockType,
        $blockAlreadyShownProperty,
        $expectedResult
    ) {
        $instance = $this->objectManager->getObject(Js::class);
        TestHelper::setProperty($instance, 'blockAlreadyShown', $blockAlreadyShownProperty);
        static::assertEquals($expectedResult, $instance->isBlockAlreadyShown($blockType));
        static::assertTrue(static::readAttribute($instance, 'blockAlreadyShown')[$blockType]);
    }

    /**
     * Data provider for {@see isBlockAlreadyShown_withVariousBlockTypeStates_returnsBlockTypeShownState}
     *
     * @return array[] containing block type, block type shown flag and expected result of the tested method call
     */
    public function isBlockAlreadyShown_withVariousBlockTypeStatesProvider()
    {
        return [
            ['blockType' => 'account', 'blockAlreadyShownProperty' => [], 'expectedResult' => false],
            ['blockType' => 'account', 'blockAlreadyShownProperty' => ['account' => true], 'expectedResult' => true],
        ];
    }

    /**
     * Stubs {@see \Bolt\Boltpay\Helper\Config::isSandboxModeSet} to return provided value and
     *
     * @see \Bolt\Boltpay\Helper\Config::getCustomURLValueOrDefault} to always return the second argumen
     *
     * @param bool $flag whether sandbox mode should be set to true or false
     */
    public function setSandboxMode($flag = true)
    {
        $this->configHelper->expects(static::any())
            ->method('isSandboxModeSet')
            ->willReturn($flag);

        $this->configHelper
            ->method('getCustomURLValueOrDefault')
            ->willReturnArgument(1);
    }

    /**
     * @test
     * that isBoltOrderCachingEnabled returns Bolt order caching configuration status form config helper
     *
     * @see \Bolt\Boltpay\Block\Js::isBoltOrderCachingEnabled
     *
     * @covers ::isBoltOrderCachingEnabled
     */
    public function isBoltOrderCachingEnabled()
    {
        $this->configHelper->expects(static::once())->method('isBoltOrderCachingEnabled')->with(self::STORE_ID)->willReturn(true);
        static::assertTrue($this->currentMock->isBoltOrderCachingEnabled(self::STORE_ID));
    }

    /**
     * @test
     */
    public function wrapWithCatch_returnsCorrectResult()
    {
        $expectedResult = '
function(arg) {
    try {
        console.log(arg);
    } catch (error) {
        console.error(error);
    }
}';
        $this->assertEquals($expectedResult, $this->currentMock->wrapWithCatch('console.log(arg);', 'arg'));
    }

    /**
     * @test
     */
    public function getAdditionalInvalidateBoltCartJavascript_returnsCorrectResult()
    {
        $this->eventsForThirdPartyModules->expects(static::once())->method('runFilter')->with('getAdditionalInvalidateBoltCartJavascript', null)->willReturn('test js');
        $this->assertEquals('test js', $this->currentMock->getAdditionalInvalidateBoltCartJavascript());
    }
    
    /**
     * @test
     */
    public function getAdditionalQuoteTotalsConditions_returnsCorrectResult()
    {
        $this->eventsForThirdPartyModules->expects(static::once())->method('runFilter')->with('getAdditionalQuoteTotalsConditions', null)->willReturn('test js');
        $this->assertEquals('test js', $this->currentMock->getAdditionalQuoteTotalsConditions());
    }

    /**
     * Setup test dependencies, called before each test
     *
     * @throws ReflectionException if unable to create one of the required mocks
     */
    protected function setUpInternal()
    {
        $this->objectManager = new ObjectManager($this);
        $this->helperContextMock = $this->createMock(Context::class);
        $this->contextMock = $this->createMock(BlockContext::class);

        $this->checkoutSessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->setMethods(['getQuote', 'getBoltInitiateCheckout', 'unsBoltInitiateCheckout'])
            ->getMock();

        $methods = [
            'isSandboxModeSet',
            'isActive',
            'getAnyPublishableKey',
            'getCustomURLValueOrDefault',
            'getPublishableKeyPayment',
            'getPublishableKeyCheckout',
            'getPublishableKeyBackOffice',
            'getReplaceSelectors',
            'getGlobalCSS',
            'getGlobalJS',
            'getPrefetchShipping',
            'getQuoteIsVirtual',
            'getTotalsChangeSelectors',
            'getAdditionalCheckoutButtonClass',
            'getAdditionalConfigString',
            'getIsPreAuth',
            'shouldTrackCheckoutFunnel',
            'isPaymentOnlyCheckoutEnabled',
            'isIPRestricted',
            'getPageBlacklist',
            'getMinicartSupport',
            'getIPWhitelistArray',
            'getApiKey',
            'getSigningSecret',
            'getButtonColor',
            'getJavascriptSuccess',
            'getAdditionalJS',
            'getOnCheckoutStart',
            'getOnEmailEnter',
            'getOnShippingDetailsComplete',
            'getOnShippingOptionsComplete',
            'getOnPaymentSubmit',
            'getOnSuccess',
            'getOnClose',
            'getToggleCheckout',
            'getModuleVersion',
            'shouldMinifyJavascript',
            'getProductPageCheckoutFlag',
            'getSelectProductPageCheckoutFlag',
            'isOrderManagementEnabled',
            'isAlwaysPresentCheckoutEnabled',
            'isShowTermsPaymentButton',
            'getOrderManagementSelector',
            'getAdditionalCheckoutButtonAttributes',
            'isBoltOrderCachingEnabled',
            'isBoltSSOEnabled',
            'getBoltOnCartPage',
        ];

        $this->configHelper = $this->getMockBuilder(HelperConfig::class)
            ->setMethods($methods)
            ->setConstructorArgs(
                [
                    $this->helperContextMock,
                    $this->createMock(EncryptorInterface::class),
                    $this->createMock(ResourceInterface::class),
                    $this->createMock(ProductMetadataInterface::class),
                    $this->createMock(BoltConfigSettingFactory::class),
                    $this->createMock(RegionFactory::class),
                    $this->createMock(ComposerFactory::class),
                    $this->createMock(WriterInterface::class)
                ]
            )
            ->getMock();

        $this->cartHelperMock = $this->createMock(CartHelper::class);
        $this->bugsnagHelperMock = $this->createMock(Bugsnag::class);
        $this->deciderMock = $this->createMock(Decider::class);
        $this->httpContextMock = $this->createMock(HttpContext::class);

        $this->requestMock = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()
            ->setMethods(['getFullActionName'])
            ->getMock();
        $this->contextMock->method('getRequest')->willReturn($this->requestMock);

        $store = $this->getMockForAbstractClass(StoreInterface::class);
        $store->method('getId')->willReturn(static::STORE_ID);
        $storeManager = $this->getMockForAbstractClass(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $this->contextMock->method('getStoreManager')->willReturn($storeManager);
        $this->eventsForThirdPartyModules = $this->createPartialMock(EventsForThirdPartyModules::class, ['runFilter']);

        $this->currentMock = $this->getMockBuilder(Js::class)
            ->setMethods(
                [
                    'getUrl',
                    'getBoltPopupErrorMessage',
                    'getQuoteFromCheckoutSession',
                    'getStoreId',
                    'getRequest',
                    'getFullActionName',
                    'getPageWhitelist',
                ]
            )
            ->setConstructorArgs(
                [
                    $this->contextMock,
                    $this->configHelper,
                    $this->checkoutSessionMock,
                    $this->cartHelperMock,
                    $this->bugsnagHelperMock,
                    $this->deciderMock,
                    $this->eventsForThirdPartyModules,
                    $this->httpContextMock,
                ]
            )
            ->getMock();
    }
    
    /**
     * @test
     *
     * @dataProvider isLoadConnectJs_withVariousConfigsProvider
     *
     * @param string $fullActionName
     * @param bool $productPageCheckoutFlag
     * @param bool $isLoadConnectJsOnSpecificPageFeatureSwitchEnabled
     * @param bool $expectedResult
     */
    public function isLoadConnectJs_withVariousConfigs_returnsCorrectResult(
        $fullActionName,
        $productPageCheckoutFlag,
        $isLoadConnectJsOnSpecificPageFeatureSwitchEnabled,
        $expectedResult
    ) {
        $this->deciderMock->expects(static::once())
            ->method('isLoadConnectJsOnSpecificPage')
            ->willReturn($isLoadConnectJsOnSpecificPageFeatureSwitchEnabled);

        $this->currentMock->expects((!$isLoadConnectJsOnSpecificPageFeatureSwitchEnabled)
                                    ? static::never()
                                    : (($fullActionName == 'checkout_cart_index') ? static::once() : static::exactly(2)))
                          ->method('getRequest')->willReturnSelf();
        $this->currentMock->expects((!$isLoadConnectJsOnSpecificPageFeatureSwitchEnabled)
                                    ? static::never()
                                    : (($fullActionName == 'checkout_cart_index') ? static::once() : static::exactly(2)))
                          ->method('getFullActionName')->willReturn($fullActionName);
        $this->configHelper->expects((!$isLoadConnectJsOnSpecificPageFeatureSwitchEnabled || ($fullActionName != 'catalog_product_view'))
                                    ? static::never() : static::once())
                           ->method('getProductPageCheckoutFlag')
                           ->willReturn($productPageCheckoutFlag);

        static::assertEquals($expectedResult, $this->currentMock->isLoadConnectJs());
    }

    /**
     * Data provider for
     *
     * @see isLoadConnectJs_withVariousConfigs_returnsCorrectResult
     *
     * @return array
     */
    public function isLoadConnectJs_withVariousConfigsProvider()
    {
        return [
            [
                'fullActionName'                                    => 'checkout_cart_index',
                'productPageCheckoutFlag'                           => false,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => true,
                'expectedResult'                                    => true
            ],
            [
                'fullActionName'                                    => 'checkout_cart_index',
                'productPageCheckoutFlag'                           => false,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => false,
                'expectedResult'                                    => true
            ],
            [
                'fullActionName'                                    => 'checkout_cart_index',
                'productPageCheckoutFlag'                           => true,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => false,
                'expectedResult'                                    => true
            ],
            [
                'fullActionName'                                    => 'checkout_cart_index',
                'productPageCheckoutFlag'                           => true,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => true,
                'expectedResult'                                    => true
            ],
            [
                'fullActionName'                                    => 'catalog_product_view',
                'productPageCheckoutFlag'                           => true,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => false,
                'expectedResult'                                    => true
            ],
            [
                'fullActionName'                                    => 'catalog_product_view',
                'productPageCheckoutFlag'                           => false,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => false,
                'expectedResult'                                    => true
            ],
            [
                'fullActionName'                                    => 'catalog_product_view',
                'productPageCheckoutFlag'                           => false,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => true,
                'expectedResult'                                    => false
            ],
            [
                'fullActionName'                                    => 'catalog_product_view',
                'productPageCheckoutFlag'                           => true,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => true,
                'expectedResult'                                    => true
            ],
            [
                'fullActionName'                                    => HelperConfig::CHECKOUT_PAGE_ACTION,
                'productPageCheckoutFlag'                           => true,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => true,
                'expectedResult'                                    => false
            ],
            [
                'fullActionName'                                    => HelperConfig::CHECKOUT_PAGE_ACTION,
                'productPageCheckoutFlag'                           => true,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => false,
                'expectedResult'                                    => true
            ],
        ];
    }
    
    /**
     * @test
     *
     * @dataProvider isLoadConnectJsDynamic_withVariousConfigsProvider
     *
     * @param string $fullActionName
     * @param bool $minicartSupport
     * @param bool $productPageCheckoutFlag
     * @param bool $isLoadConnectJsOnSpecificPageFeatureSwitchEnabled
     * @param bool $expectedResult
     */
    public function isLoadConnectJsDynamic_withVariousConfigs_returnsCorrectResult(
        $fullActionName,
        $minicartSupport,
        $productPageCheckoutFlag,
        $isLoadConnectJsOnSpecificPageFeatureSwitchEnabled,
        $expectedResult
    ) {
        $this->deciderMock->expects(static::once())
            ->method('isLoadConnectJsOnSpecificPage')
            ->willReturn($isLoadConnectJsOnSpecificPageFeatureSwitchEnabled);

        $this->currentMock->expects(($isLoadConnectJsOnSpecificPageFeatureSwitchEnabled && $minicartSupport && !$productPageCheckoutFlag)
                                    ? static::once()
                                    : static::never())
                          ->method('getRequest')->willReturnSelf();
        $this->currentMock->expects(($isLoadConnectJsOnSpecificPageFeatureSwitchEnabled && $minicartSupport && !$productPageCheckoutFlag)
                                    ? static::once()
                                    : static::never())
                          ->method('getFullActionName')->willReturn($fullActionName);
        $this->configHelper->expects($isLoadConnectJsOnSpecificPageFeatureSwitchEnabled ? static::once() : static::never())
                           ->method('getMinicartSupport')
                           ->willReturn($minicartSupport);
                           
        $this->configHelper->expects(($isLoadConnectJsOnSpecificPageFeatureSwitchEnabled && $minicartSupport)
                                    ? static::once() : static::never())
                           ->method('getProductPageCheckoutFlag')
                           ->willReturn($productPageCheckoutFlag);

        static::assertEquals($expectedResult, $this->currentMock->isLoadConnectJsDynamic());
    }
    
    /**
     * Data provider for
     *
     * @see isLoadConnectJsDynamic_withVariousConfigs_returnsCorrectResult
     *
     * @return array
     */
    public function isLoadConnectJsDynamic_withVariousConfigsProvider()
    {
        return [
            [
                'fullActionName'                                    => 'catalog_product_view',
                'minicartSupport'                                   => true,
                'productPageCheckoutFlag'                           => true,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => true,
                'expectedResult'                                    => false
            ],
            [
                'fullActionName'                                    => 'catalog_product_view',
                'minicartSupport'                                   => false,
                'productPageCheckoutFlag'                           => true,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => true,
                'expectedResult'                                    => false
            ],
            [
                'fullActionName'                                    => 'catalog_product_view',
                'minicartSupport'                                   => true,
                'productPageCheckoutFlag'                           => false,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => true,
                'expectedResult'                                    => true
            ],
            [
                'fullActionName'                                    => 'catalog_product_view',
                'minicartSupport'                                   => false,
                'productPageCheckoutFlag'                           => false,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => true,
                'expectedResult'                                    => false
            ],
            [
                'fullActionName'                                    => 'catalog_product_view',
                'minicartSupport'                                   => true,
                'productPageCheckoutFlag'                           => true,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => false,
                'expectedResult'                                    => false
            ],
            [
                'fullActionName'                                    => 'catalog_product_view',
                'minicartSupport'                                   => false,
                'productPageCheckoutFlag'                           => true,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => false,
                'expectedResult'                                    => false
            ],
            [
                'fullActionName'                                    => 'catalog_product_view',
                'minicartSupport'                                   => true,
                'productPageCheckoutFlag'                           => false,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => false,
                'expectedResult'                                    => false
            ],
            [
                'fullActionName'                                    => 'catalog_product_view',
                'minicartSupport'                                   => false,
                'productPageCheckoutFlag'                           => false,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => false,
                'expectedResult'                                    => false
            ],
            [
                'fullActionName'                                    => HelperConfig::CHECKOUT_PAGE_ACTION,
                'minicartSupport'                                   => true,
                'productPageCheckoutFlag'                           => true,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => true,
                'expectedResult'                                    => false
            ],
            [
                'fullActionName'                                    => HelperConfig::CHECKOUT_PAGE_ACTION,
                'minicartSupport'                                   => false,
                'productPageCheckoutFlag'                           => true,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => true,
                'expectedResult'                                    => false
            ],
            [
                'fullActionName'                                    => HelperConfig::CHECKOUT_PAGE_ACTION,
                'minicartSupport'                                   => true,
                'productPageCheckoutFlag'                           => false,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => true,
                'expectedResult'                                    => false
            ],
            [
                'fullActionName'                                    => HelperConfig::CHECKOUT_PAGE_ACTION,
                'minicartSupport'                                   => false,
                'productPageCheckoutFlag'                           => false,
                'isLoadConnectJsOnSpecificPageFeatureSwitchEnabled' => true,
                'expectedResult'                                    => false
            ],
        ];
    }
    
    /**
     * @test
     *
     * @dataProvider isDisableTrackJsOnHomePage_withVariousConfigsProvider
     *
     * @param bool $isDisableTrackJsOnHomePage
     * @param bool $expectedResult
     */
    public function isDisableTrackJsOnHomePage_withVariousConfigs_returnsCorrectResult($isDisableTrackJsOnHomePage, $expectedResult)
    {
        $this->deciderMock->expects(static::once())
            ->method('isDisableTrackJsOnHomePage')
            ->willReturn($isDisableTrackJsOnHomePage);
        static::assertEquals($expectedResult, $this->currentMock->isDisableTrackJsOnHomePage());
    }
    
    /**
     * Data provider for
     *
     * @see isDisableTrackJsOnHomePage_withVariousConfigs_returnsCorrectResult
     *
     * @return array
     */
    public function isDisableTrackJsOnHomePage_withVariousConfigsProvider()
    {
        return [
            [
                'isDisableTrackJsOnHomePage' => true,
                'expectedResult'               => true
            ],
            [
                'isDisableTrackJsOnHomePage' => false,
                'expectedResult'               => false
            ],
        ];
    }
    
    /**
     * @test
     *
     * @dataProvider isDisableTrackJsOnNonBoltPages_withVariousConfigsProvider
     *
     * @param bool $isDisableTrackJsOnNonBoltPages
     * @param bool $expectedResult
     */
    public function isDisableTrackJsOnNonBoltPages_withVariousConfigs_returnsCorrectResult($isDisableTrackJsOnNonBoltPages, $expectedResult)
    {
        $this->deciderMock->expects(static::once())
            ->method('isDisableTrackJsOnNonBoltPages')
            ->willReturn($isDisableTrackJsOnNonBoltPages);
        static::assertEquals($expectedResult, $this->currentMock->isDisableTrackJsOnNonBoltPages());
    }
    
    /**
     * Data provider for
     *
     * @see isDisableTrackJsOnNonBoltPages_withVariousConfigs_returnsCorrectResult
     *
     * @return array
     */
    public function isDisableTrackJsOnNonBoltPages_withVariousConfigsProvider()
    {
        return [
            [
                'isDisableTrackJsOnNonBoltPages' => true,
                'expectedResult'                  => true
            ],
            [
                'isDisableTrackJsOnNonBoltPages' => false,
                'expectedResult'                  => false
            ],
        ];
    }
    
    /**
     * @test
     * 
     * @see \Bolt\Boltpay\Block\Js::isBoltOnCartDisabled
     *
     * @dataProvider isBoltOnCartDisabled_withVariousConfigsProvider
     *
     * @covers ::isBoltOnCartDisabled
     *
     * @param bool $getBoltOnCartPage
     * @param bool $fullActionName
     * @param bool $expectedResult
     */
    public function isBoltOnCartDisabled_withVariousConfigs_returnsCorrectResult($getBoltOnCartPage, $fullActionName, $expectedResult)
    {
        $this->currentMock->expects(static::once())->method('getStoreId')->willReturn(self::STORE_ID);
        $this->currentMock->expects(static::once())
                          ->method('getRequest')
                          ->willReturnSelf();
        $this->currentMock->expects(static::once())
                          ->method('getFullActionName')
                          ->willReturn($fullActionName);
        $this->configHelper->expects(($fullActionName == 'checkout_cart_index')
                                    ? static::once() : static::never())
                           ->method('getBoltOnCartPage')
                           ->with(self::STORE_ID)
                           ->willReturn($getBoltOnCartPage);
        static::assertEquals($expectedResult, $this->currentMock->isBoltOnCartDisabled());
    }
    
    /**
     * Data provider for
     *
     * @see isBoltOnCartDisabled_withVariousConfigs_returnsCorrectResult
     *
     * @return array
     */
    public function isBoltOnCartDisabled_withVariousConfigsProvider()
    {
        return [
            [
                'getBoltOnCartPage' => true,
                'fullActionName'    => 'checkout_cart_index',
                'expectedResult'    => false
            ],
            [
                'getBoltOnCartPage' => true,
                'fullActionName'    => 'catalog_product_view',
                'expectedResult'    => false
            ],
            [
                'getBoltOnCartPage' => false,
                'fullActionName'    => 'checkout_cart_index',
                'expectedResult'    => true
            ],
            [
                'getBoltOnCartPage' => false,
                'fullActionName'    => 'catalog_product_view',
                'expectedResult'    => false
            ],
        ];
    }
}
