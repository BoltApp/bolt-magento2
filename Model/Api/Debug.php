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

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\DebugInterface;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Model\Api\Data\BoltConfigSettingFactory;
use Bolt\Boltpay\Model\Api\Data\DebugInfoFactory;
use Bolt\Boltpay\Model\Api\Data\PluginVersionFactory;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Store\Model\StoreManagerInterface;

class Debug implements DebugInterface
{
	/**
	 * @var DebugInfoFactory
	 */
	private $debugInfoFactory;

	/**
	 * @var BoltConfigSettingFactory
	 */
	private $boltConfigSettingFactory;

	/**
	 * @var PluginVersionFactory
	 */
	private $pluginVersionFactory;

	/**
	 * @var HookHelper
	 */
	private $hookHelper;

	/**
	 * @var StoreManagerInterface
	 */
	private $storeManager;

	/**
	 * @var ProductMetadataInterface
	 */
	private $productMetadata;

	/**
	 * @var ConfigHelper
	 */
	private $configHelper;

	/**
	 * @param DebugInfoFactory $debugInfoFactory
	 * @param BoltConfigSettingFactory $boltConfigSettingFactory
	 * @param PluginVersionFactory $pluginVersionFactory
	 * @param StoreManagerInterface $storeManager
	 * @param HookHelper $hookHelper
	 * @param ProductMetadataInterface $productMetadata
	 * @param ConfigHelper $configHelper
	 */
	public function __construct(
		DebugInfoFactory $debugInfoFactory,
		BoltConfigSettingFactory $boltConfigSettingFactory,
		PluginVersionFactory $pluginVersionFactory,
		StoreManagerInterface $storeManager,
		HookHelper $hookHelper,
		ProductMetadataInterface $productMetadata,
		ConfigHelper $configHelper
	) {
		$this->debugInfoFactory = $debugInfoFactory;
		$this->boltConfigSettingFactory = $boltConfigSettingFactory;
		$this->pluginVersionFactory = $pluginVersionFactory;
		$this->storeManager = $storeManager;
		$this->hookHelper = $hookHelper;
		$this->productMetadata = $productMetadata;
		$this->configHelper = $configHelper;
	}

	/**
	 * This request handler will return relevant information for Bolt for debugging purpose.
	 *
	 * @return DebugInfo
	 * @api
	 */
	public function debug()
	{
		# verify request
		$this->hookHelper->preProcessWebhook($this->storeManager->getStore()->getId());

		$result = $this->debugInfoFactory->create();

		# populate php version
		$result->setPhpVersion(PHP_VERSION);

		# populate platform version
		$result->setPlatformVersion($this->productMetadata->getVersion());

		# populate bolt config settings
		$result->setBoltConfigSettings($this->getBoltSettings());

		$otherPluginVersions = [];
		$otherPluginVersions[] = $this->pluginVersionFactory->create();
		$result->setOtherPluginVersions($otherPluginVersions);

		return $result;
	}

	/**
	 * Get all bolt configuration settings
	 *
	 * @return BoltConfigSetting[]
	 */
	private function getBoltSettings()
	{
		$boltSettings = [];

		// Active
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('active')
		                                                 ->setValue(var_export($this->configHelper->isActive(), true));
		// Title
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('title')
		                                                 ->setValue($this->configHelper->getTitle());
//		// TODO：API Key (obscure)
//		$boltSettings[] = $this->boltConfigSettingFactory->create()
//		                                                 ->setName('api_key')
//		                                                 ->setValue($this->configHelper->getApiKey());
//		// TODO：Signing Secret (obscure)
//		$boltSettings[] = $this->boltConfigSettingFactory->create()
//		                                                 ->setName('signing_secret')
//		                                                 ->setValue($this->configHelper->getSigningSecret());
//		// TODO: Publishable Key for Checkout (obscure)
//		$boltSettings[] = $this->boltConfigSettingFactory->create()
//		                                                 ->setName('publishable_key_checkout')
//		                                                 ->setValue($this->configHelper->getPublishableKeyCheckout());
//		// TODO: Publishable Key for Payment (obscure)
//		$boltSettings[] = $this->boltConfigSettingFactory->create()
//		                                                 ->setName('publishable_key_payment')
//		                                                 ->setValue($this->configHelper->getPublishableKeyPayment());
//		// TODO: Publishable Key for Back Office (obscure)
//		$boltSettings[] = $this->boltConfigSettingFactory->create()
//		                                                 ->setName('publishable_key_back_office')
//		                                                 ->setValue($this->configHelper->getPublishableKeyBackOffice());
		// Sandbox Mode
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('sandbox_mode')
		                                                 ->setValue(var_export($this->configHelper->isSandboxModeSet(), true));
		// Pre-auth
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('is_pre_auth')
		                                                 ->setValue(var_export($this->configHelper->getIsPreAuth(), true));
		// Product Page Checkout
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('product_page_checkout')
		                                                 ->setValue(var_export($this->configHelper->getProductPageCheckoutFlag(), true));
//		// TODO: Geolocation API Key (obscure)
//		$boltSettings[] = $this->boltConfigSettingFactory->create()
//		                                                 ->setName('product_page_checkout')
//		                                                 ->setValue($this->configHelper->getGeolocationApiKey());
		// Replace Button Selectors
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('replace_selectors')
		                                                 ->setValue($this->configHelper->getReplaceSelectors());
		// Totals Monitor Selectors
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('totals_change_selectors')
		                                                 ->setValue($this->configHelper->getTotalsChangeSelectors());
		// Global CSS
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('global_css')
		                                                 ->setValue($this->configHelper->getGlobalCSS());
		// Additional Checkout Button Class
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('additional_checkout_button_class')
		                                                 ->setValue($this->configHelper->getAdditionalCheckoutButtonClass());
		// Success Page Redirect
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('success_page')
		                                                 ->setValue($this->configHelper->getSuccessPageRedirect());
		// Prefetch Shipping
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('prefetch_shipping')
		                                                 ->setValue(var_export($this->configHelper->getPrefetchShipping(), true));
		// Prefetch Address
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('prefetch_address_fields')
		                                                 ->setValue($this->configHelper->getPrefetchAddressFields());
		// Reset Shipping Calculation
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('reset_shipping_calculation')
		                                                 ->setValue(var_export($this->configHelper->getResetShippingCalculation(), true));
		// Javascript: success
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('javascript_success')
		                                                 ->setValue($this->configHelper->getJavascriptSuccess());
		// Debug
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('debug')
		                                                 ->setValue(var_export($this->configHelper->isDebugModeOn(), true));
		// Additional Javascript
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('additional_js')
		                                                 ->setValue($this->configHelper->getAdditionalJS());
		// Tracking: onCheckoutStart
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('track_on_checkout_start')
		                                                 ->setValue($this->configHelper->getOnCheckoutStart());
		// Tracking: onEmailEnter
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('track_on_email_enter')
		                                                 ->setValue($this->configHelper->getOnEmailEnter());
		// Tracking: onShippingDetailsComplete
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('track_on_shipping_details_complete')
		                                                 ->setValue($this->configHelper->getOnShippingDetailsComplete());
		// Tracking: onShippingOptionsComplete
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('track_on_shipping_options_complete')
		                                                 ->setValue($this->configHelper->getOnShippingOptionsComplete());
		// Tracking: onPaymentSubmit
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('track_on_payment_submit')
		                                                 ->setValue($this->configHelper->getOnPaymentSubmit());
		// Tracking: onSuccess
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('track_on_success')
		                                                 ->setValue($this->configHelper->getOnSuccess());
		// Tracking: onClose
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('track_on_close')
		                                                 ->setValue($this->configHelper->getOnClose());
		// Additional Configuration
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('additional_config')
		                                                 ->setValue($this->configHelper->getAdditionalConfigString());
		// MiniCart Support
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('minicart_support')
		                                                 ->setValue(var_export($this->configHelper->getMinicartSupport(), true));
		// Client IP Restriction
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('ip_whitelist')
		                                                 ->setValue($this->configHelper->getIPWhitelistArray());
		// Store Credit
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('store_credit')
		                                                 ->setValue(var_export($this->configHelper->useStoreCreditConfig(), true));
		// Reward Points
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('reward_points')
		                                                 ->setValue(var_export($this->configHelper->useRewardPointsConfig(), true));
		// Enable Payment Only Checkout
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('enable_payment_only_checkout')
		                                                 ->setValue(var_export($this->configHelper->isPaymentOnlyCheckoutEnabled(), true));
		// Cache Bolt Order Token
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('bolt_order_caching')
		                                                 ->setValue(var_export($this->configHelper->isBoltOrderCachingEnabled(), true));
		// Emulate Customer Session in API Calls
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('api_emulate_session')
		                                                 ->setValue(var_export($this->configHelper->isSessionEmulationEnabled(), true));
		// Minify JavaScript
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('should_minify_javascript')
		                                                 ->setValue(var_export($this->configHelper->shouldMinifyJavascript(), true));
		// Capture Internal Merchant Metrics
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('capture_merchant_metrics')
		                                                 ->setValue(var_export($this->configHelper->shouldCaptureMetrics(), true));
		// Track checkout funnel
		$boltSettings[] = $this->boltConfigSettingFactory->create()
		                                                 ->setName('track_checkout_funnel')
		                                                 ->setValue(var_export($this->configHelper->shouldTrackCheckoutFunnel(), true));

		return $boltSettings;
	}

}