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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Model\Api\Data\BoltConfigSetting;
use Bolt\Boltpay\Model\Api\Data\BoltConfigSettingFactory;
use Bolt\Boltpay\Model\Api\Data\DebugInfo;
use Bolt\Boltpay\Model\Api\Data\DebugInfoFactory;
use Bolt\Boltpay\Model\Api\Debug;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\StoreManager;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Config as ConfigHelper;

/**
 * Class CreateOrderTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\Debug
 */
class DebugTest extends TestCase
{
	/**
	 * @var Debug
	 */
	private $debug;

	/**
	 * @var DebugInfoFactory
	 */
	private $debugInfoFactoryMock;

	/**
	 * @var BoltConfigSettingFactory
	 */
	private $boltConfigSettingFactoryMock;

	/**
	 * @var StoreManagerInterface
	 */
	private $storeManagerInterfaceMock;

	/**
	 * @var HookHelper
	 */
	private $hookHelperMock;

	/**
	 * @var ProductMetadataInterface
	 */
	private $productMetadataInterfaceMock;

	/**
	 * @var ConfigHelper
	 */
	private $configHelperMock;

	/**
	 * @inheritdoc
	 */
	public function setUp()
	{
		// prepare debug info factory
		$this->debugInfoFactoryMock = $this->createMock(DebugInfoFactory::class);
		$this->debugInfoFactoryMock->method('create')->willReturn(new DebugInfo());

		// prepare bolt config setting factory
		$this->boltConfigSettingFactoryMock = $this->createMock(BoltConfigSettingFactory::class);
		$this->boltConfigSettingFactoryMock->method('create')->willReturnCallback(function () {
			return new BoltConfigSetting();
		});

		// prepare store manager
		$storeInterfaceMock = $this->createMock(StoreInterface::class);
		$storeInterfaceMock->method('getId')->willReturn(0);
		$this->storeManagerInterfaceMock = $this->createMock(StoreManagerInterface::class);
		$this->storeManagerInterfaceMock->method('getStore')->willReturn($storeInterfaceMock);

		// prepare product meta data
		$this->productMetadataInterfaceMock = $this->createMock(ProductMetadataInterface::class);
		$this->productMetadataInterfaceMock->method('getVersion')->willReturn('2.3.0');

		// prepare hook helper
		$this->hookHelperMock = $this->createMock(HookHelper::class);
		$this->hookHelperMock->method('preProcessWebhook');
		$this->hookHelperMock->method('preProcessWebhook');


		// prepare config helper
		$this->configHelperMock = $this->createMock(ConfigHelper::class);
		$this->prepareConfigHelperMock();

		// initialize test object
		$objectManager = new ObjectManager($this);
		$this->debug = $objectManager->getObject(
			Debug::class,
			[
				'debugInfoFactory' => $this->debugInfoFactoryMock,
				'boltConfigSettingFactory' => $this->boltConfigSettingFactoryMock,
				'storeManager' => $this->storeManagerInterfaceMock,
				'hookHelper' => $this->hookHelperMock,
				'productMetadata' => $this->productMetadataInterfaceMock,
				'configHelper' => $this->configHelperMock
			]
		);
	}

	private function prepareConfigHelperMock()
	{
		$this->configHelperMock->method('isActive')->willReturn(true);
		$this->configHelperMock->method('getTitle')->willReturn('bolt test title');
		$this->configHelperMock->method('getApiKey')->willReturn('bolt test api key');
		$this->configHelperMock->method('getSigningSecret')->willReturn('bolt test signing secret');
		$this->configHelperMock->method('getPublishableKeyCheckout')->willReturn('bolt test publishable key - checkout');
		$this->configHelperMock->method('getPublishableKeyPayment')->willReturn('bolt test publishable key - payment');
		$this->configHelperMock->method('getPublishableKeyBackOffice')->willReturn('bolt test publishable key - back office');
		$this->configHelperMock->method('isSandboxModeSet')->willReturn(true);
		$this->configHelperMock->method('getIsPreAuth')->willReturn(true);
		$this->configHelperMock->method('getProductPageCheckoutFlag')->willReturn(true);
		$this->configHelperMock->method('getGeolocationApiKey')->willReturn('geolocation api key');
		$this->configHelperMock->method('getReplaceSelectors')->willReturn('#replace');
		$this->configHelperMock->method('getTotalsChangeSelectors')->willReturn('.totals');
		$this->configHelperMock->method('getGlobalCSS')->willReturn('#customerbalance-placer {width: 210px;}');
		$this->configHelperMock->method('getAdditionalCheckoutButtonClass')->willReturn('with-cards');
		$this->configHelperMock->method('getSuccessPageRedirect')->willReturn('checkout/onepage/success');
		$this->configHelperMock->method('getPrefetchShipping')->willReturn(true);
		$this->configHelperMock->method('getPrefetchAddressFields')->willReturn('77 Geary Street');
		$this->configHelperMock->method('getResetShippingCalculation')->willReturn(false);
		$this->configHelperMock->method('getJavascriptSuccess')->willReturn('// do nothing');
		$this->configHelperMock->method('isDebugModeOn')->willReturn(false);
		$this->configHelperMock->method('getAdditionalJS')->willReturn('// none');
		$this->configHelperMock->method('getOnCheckoutStart')->willReturn('// on checkout start');
		$this->configHelperMock->method('getOnEmailEnter')->willReturn('// on email enter');
		$this->configHelperMock->method('getOnShippingDetailsComplete')->willReturn('// on shipping details complete');
		$this->configHelperMock->method('getOnShippingOptionsComplete')->willReturn('// on shipping options complete');
		$this->configHelperMock->method('getOnPaymentSubmit')->willReturn('// on payment submit');
		$this->configHelperMock->method('getOnSuccess')->willReturn('// on success');
		$this->configHelperMock->method('getOnClose')->willReturn('// on close');
		$this->configHelperMock->method('getAdditionalConfigString')->willReturn('bolt additional config');
		$this->configHelperMock->method('getMinicartSupport')->willReturn(true);
		$this->configHelperMock->method('getIPWhitelistArray')->willReturn(['127.0.0.1', '0.0.0.0']);
		$this->configHelperMock->method('useStoreCreditConfig')->willReturn(false);
		$this->configHelperMock->method('useRewardPointsConfig')->willReturn(false);
		$this->configHelperMock->method('isPaymentOnlyCheckoutEnabled')->willReturn(false);
		$this->configHelperMock->method('isBoltOrderCachingEnabled')->willReturn(true);
		$this->configHelperMock->method('isSessionEmulationEnabled')->willReturn(true);
		$this->configHelperMock->method('shouldMinifyJavascript')->willReturn(true);
		$this->configHelperMock->method('shouldCaptureMetrics')->willReturn(false);
		$this->configHelperMock->method('shouldTrackCheckoutFunnel')->willReturn(false);
	}

	/**
	 * @test
	 * @covers ::debug
	 */
	public function debug_successful()
	{
		$this->hookHelperMock->expects($this->once())->method('preProcessWebhook');

		$debugInfo = $this->debug->debug();
		$this->assertNotNull($debugInfo);
		$this->assertNotNull($debugInfo->getPhpVersion());
		$this->assertEquals('2.3.0', $debugInfo->getPlatformVersion());

		// check bolt settings
		$expected_settings = [
			['active', 'true'],
			['title', 'bolt test title'],
			['api_key', 'bol***key'],
			['signing_secret', 'bol***ret'],
			['publishable_key_checkout', 'bol***out'],
			['publishable_key_payment', 'bol***ent'],
			['publishable_key_back_office', 'bol***ice'],
			['sandbox_mode', 'true'],
			['is_pre_auth', 'true'],
			['product_page_checkout', 'true'],
			['geolocation_api_key', 'geo***key'],
			['replace_selectors', '#replace'],
			['totals_change_selectors', '.totals'],
			['global_css', '#customerbalance-placer {width: 210px;}'],
			['additional_checkout_button_class', 'with-cards'],
			['success_page', 'checkout/onepage/success'],
			['prefetch_shipping', 'true'],
			['prefetch_address_fields', '77 Geary Street'],
			['reset_shipping_calculation', 'false'],
			['javascript_success', '// do nothing'],
			['debug', 'false'],
			['additional_js', '// none'],
			['track_on_checkout_start', '// on checkout start'],
			['track_on_email_enter', '// on email enter'],
			['track_on_shipping_details_complete', '// on shipping details complete'],
			['track_on_shipping_options_complete', '// on shipping options complete'],
			['track_on_payment_submit', '// on payment submit'],
			['track_on_success', '// on success'],
			['track_on_close', '// on close'],
			['additional_config', 'bolt additional config'],
			['minicart_support', 'true'],
			['ip_whitelist', '127.0.0.1, 0.0.0.0'],
			['store_credit', 'false'],
			['reward_points', 'false'],
			['enable_payment_only_checkout', 'false'],
			['bolt_order_caching', 'true'],
			['api_emulate_session', 'true'],
			['should_minify_javascript', 'true'],
			['capture_merchant_metrics', 'false'],
			['track_checkout_funnel', 'false'],
		];
		$this->assertEquals(40, count($debugInfo->getBoltConfigSettings()));
		for ($i = 0; $i < 40; $i ++) {
			$this->assertEquals($expected_settings[$i][0], $debugInfo->getBoltConfigSettings()[$i]->getName());
			$this->assertEquals($expected_settings[$i][1], $debugInfo->getBoltConfigSettings()[$i]->getValue(), 'actual value for ' . $expected_settings[$i][0] . ' is not equals to expected');
		}
	}
}