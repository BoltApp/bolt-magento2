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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Helper;

use Bolt\Boltpay\Model\Api\Data\BoltConfigSettingFactory;
use Exception;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Composer\ComposerFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ResourceInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

/**
 * Boltpay Configuration helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Config extends AbstractHelper
{
    const BOLT_TRACE_ID_HEADER = 'X-bolt-trace-id';
    const BOLT_COMPOSER_NAME = 'boltpay/bolt-magento2';

    /**
     * Path for title
     */
    const XML_PATH_TITLE = 'payment/boltpay/title';

    /**
     * Path for API Key
     */
    const XML_PATH_API_KEY = 'payment/boltpay/api_key';

    /**
     * Path for Signing Secret
     */
    const XML_PATH_SIGNING_SECRET = 'payment/boltpay/signing_secret';

    /**
     * Path for publishable key payment only
     */
    const XML_PATH_PUBLISHABLE_KEY_PAYMENT = 'payment/boltpay/publishable_key_payment';

    /**
     * Path for publishable key multi-step
     */
    const XML_PATH_PUBLISHABLE_KEY_CHECKOUT = 'payment/boltpay/publishable_key_checkout';

    /**
     * Path for publishable key back office
     */
    const XML_PATH_PUBLISHABLE_KEY_BACK_OFFICE = 'payment/boltpay/publishable_key_back_office';

    /**
     * Path for Replace Selectors
     */
    const XML_PATH_BUTTON_COLOR = 'payment/boltpay/button_color';

    /**
     * Path for Replace Selectors
     */
    const XML_PATH_REPLACE_SELECTORS = 'payment/boltpay/replace_selectors';

    /**
     * Path for Totals Change Selectors
     */
    const XML_PATH_TOTALS_CHANGE_SELECTORS = 'payment/boltpay/totals_change_selectors';

    /**
     * Path for Global CSS
     */
    const XML_PATH_GLOBAL_CSS = 'payment/boltpay/global_css';

    /**
     * Path for Global Javascript
     */
    const XML_PATH_GLOBAL_JS = 'payment/boltpay/global_js';

    /**
     * Path for show card type in the order grid
     */
    const XML_PATH_SHOW_CC_TYPE_IN_ORDER_GRID = 'payment/boltpay/show_cc_type_in_order_grid';

    /**
     * Path for new plugin version notification enabling
     */
    const XML_PATH_NEW_PLUGIN_VERSION_NOTIFICATION = 'payment/boltpay/new_plugin_version_notification';

    /**
     * Path for Additional Checkout Button Class
     */
    const XML_PATH_ADDITIONAL_CHECKOUT_BUTTON_CLASS = 'payment/boltpay/additional_checkout_button_class';

    /**
     * Path for Custom Prefetch Address Fields
     */
    const XML_PATH_PREFETCH_ADDRESS_FIELDS = 'payment/boltpay/prefetch_address_fields';

    /**
     * Path for Custom Javascript function call on success
     */
    const XML_PATH_JAVASCRIPT_SUCCESS = 'payment/boltpay/javascript_success';

    /**
     * Is pre-auth configuration path
     */
    const XML_PATH_IS_PRE_AUTH = 'payment/boltpay/is_pre_auth';

    /**
     * Enable product page checkout
     */
    const XML_PATH_PRODUCT_PAGE_CHECKOUT = 'payment/boltpay/product_page_checkout';

    /**
     * Enable product page checkout for select products.
     */
    const XML_PATH_SELECT_PRODUCT_PAGE_CHECKOUT = 'payment/boltpay/select_product_page_checkout';

    /**
     * Enable Bolt order management
     */
    const XML_PATH_PRODUCT_ORDER_MANAGEMENT = 'payment/boltpay/order_management';

    /**
     * Enable Bolt order management CSS selector
     */
    const XML_PATH_PRODUCT_ORDER_MANAGEMENT_SELECTOR = 'payment/boltpay/order_management_selector';

    /**
     * Prefetch shipping
     */
    const XML_PATH_PREFETCH_SHIPPING = 'payment/boltpay/prefetch_shipping';

    /**
     * Reset Shipping Calculation
     */
    const XML_PATH_RESET_SHIPPING_CALCULATION = 'payment/boltpay/reset_shipping_calculation';

    /**
     * Enabled
     */
    const XML_PATH_ACTIVE = 'payment/boltpay/active';

    /**
     * Debug
     */
    const XML_PATH_DEBUG = 'payment/boltpay/debug';

    /**
     * Create cart
     */
    const CREATE_ORDER_ACTION = 'boltpay/cart/data';

    /**
     * Get hints
     */
    const GET_HINTS_ACTION = 'boltpay/cart/hints';

    /**
     * Prefetch Shipping
     */
    const SHIPPING_PREFETCH_ACTION = 'boltpay/shipping/prefetch';

    /**
     * Save order
     */
    const SAVE_ORDER_ACTION = 'boltpay/order/save';

    /**
     * Save Email to Quote
     */
    const SAVE_EMAIL_ACTION = 'boltpay/cart/email';

    /**
     * Save order
     */
    const XML_PATH_SUCCESS_PAGE_REDIRECT = 'payment/boltpay/success_page';

    /**
     * Path for sandbox mode
     */
    const XML_PATH_SANDBOX_MODE = 'payment/boltpay/sandbox_mode';

    /**
     * Path for custom API server, used only for dev mode.
     */
    const XML_PATH_CUSTOM_API = 'payment/boltpay/custom_api';

    /**
     * Path for custom merchant dash, used only for dev mode.
     */
    const XML_PATH_CUSTOM_MERCHANT_DASH = 'payment/boltpay/custom_merchant_dash';

    /**
     * Path for custom cdn, used only for dev mode.
     */
    const XML_PATH_CUSTOM_CDN = 'payment/boltpay/custom_cdn';

    /**
     * Path for custom account url, used only for dev mode.
     */
    const XML_PATH_CUSTOM_ACCOUNT = 'payment/boltpay/custom_account';

    /**
     * Path for custom public key, used only for dev mode.
     */
    const XML_PATH_CUSTOM_PUBLIC_KEY = 'payment/boltpay/custom_public_key';

    /**
     * Bolt sandbox url
     */
    const API_URL_SANDBOX = 'https://api-sandbox.bolt.com/';

    /**
     * Bolt production url
     */
    const API_URL_PRODUCTION = 'https://api.bolt.com/';

    /**
     * Bolt sandbox cdn url
     */
    const CDN_URL_SANDBOX = 'https://connect-sandbox.bolt.com';

    /**
     * Bolt production cdn url
     */
    const ACCOUNT_URL_PRODUCTION = 'https://account.bolt.com';

    /**
     * Bolt sandbox cdn url
     */
    const ACCOUNT_URL_SANDBOX = 'https://account-sandbox.bolt.com';

    /**
     * Bolt production cdn url
     */
    const CDN_URL_PRODUCTION = 'https://connect.bolt.com';

    /**
     * Bolt merchant sandbox url
     */
    const MERCHANT_DASH_SANDBOX = 'https://merchant-sandbox.bolt.com';

    /**
     * Bolt merchant production url
     */
    const MERCHANT_DASH_PRODUCTION = 'https://merchant.bolt.com';

    /**
     * Path for API Key
     */
    const XML_PATH_GEOLOCATION_API_KEY = 'payment/boltpay/geolocation_api_key';

    /**
     * Path for catalog ingestion cron max items
     */
    const XML_PATH_CATALOG_INGESTION_CRON_MAX_ITEMS = 'payment/boltpay/catalog_ingestion_cron_max_items';

    /**
     * Path for catalog ingestion instant enabled
     */
    const XML_PATH_CATALOG_INGESTION_INSTANT_ENABLED = 'payment/boltpay/catalog_ingestion_instant_enabled';

    /**
     * Path for catalog ingestion instant async enabled
     */
    const XML_PATH_CATALOG_INGESTION_INSTANT_ASYNC_ENABLED = 'payment/boltpay/catalog_ingestion_instant_async_enabled';

    /**
     * Path for catalog ingestion instant available custom events
     */
    const XML_PATH_CATALOG_INGESTION_INSTANT_EVENT = 'payment/boltpay/catalog_ingestion_instant_events';

    /**
     * Path for Additional Javascript
     */
    const XML_PATH_ADDITIONAL_JS = 'payment/boltpay/additional_js';

    const XML_PATH_TRACK_CHECKOUT_START = 'payment/boltpay/track_on_checkout_start';

    const XML_PATH_TRACK_EMAIL_ENTER = 'payment/boltpay/track_on_email_enter';

    const XML_PATH_TRACK_SHIPPING_DETAILS_COMPLETE = 'payment/boltpay/track_on_shipping_details_complete';

    const XML_PATH_TRACK_SHIPPING_OPTIONS_COMPLETE = 'payment/boltpay/track_on_shipping_options_complete';

    const XML_PATH_TRACK_PAYMENT_SUBMIT = 'payment/boltpay/track_on_payment_submit';

    const XML_PATH_TRACK_SUCCESS = 'payment/boltpay/track_on_success';

    const XML_PATH_TRACK_CLOSE = 'payment/boltpay/track_on_close';

    /**
     * Additional configuration path
     */
    const XML_PATH_ADDITIONAL_CONFIG = 'payment/boltpay/additional_config';

    /**
     * MiniCart Support configuration path
     */
    const XML_PATH_MINICART_SUPPORT = 'payment/boltpay/minicart_support';

    /**
     * Display Bolt Checkout on the Cart Page configuration path
     */
    const XML_PATH_BOLT_ON_CART_PAGE = 'payment/boltpay/enable_bolt_on_cart_page';

    /**
     * Client IP Whitelist configuration path
     */
    const XML_PATH_IP_WHITELIST = 'payment/boltpay/ip_whitelist';

    /**
     * Use Store Credit on Shopping Cart configuration path
     */
    const XML_PATH_STORE_CREDIT = 'payment/boltpay/store_credit';
    const XML_DISABLE_CUSTOMER_GROUP = 'payment/boltpay/additional/disabled_customer_groups';

    /**
     * Use Store Credit on Shopping Cart configuration path
     */
    const XML_PATH_AMASTY_STORE_CREDIT = 'payment/boltpay/amasty_store_credit';

    /**
     * Use Aheadworks Reward Points on Shopping Cart configuration path
     */
    const XML_PATH_AHEADWORKS_REWARD_POINTS_ON_CART = 'payment/boltpay/aheadworks_reward_points_on_cart';

    /**
     * Use Aheadworks Store Credit on Shopping Cart configuration path
     */
    const XML_PATH_AHEADWORKS_STORE_CREDIT_ON_CART = 'payment/boltpay/aheadworks_store_credit_on_cart';

    /**
     * Use MageWorx Reward Points on Shopping Cart configuration path
     */
    const XML_PATH_MAGEWORX_REWARD_POINTS_ON_CART = 'payment/boltpay/mageworx_reward_points_on_cart';

    /**
     * Use Reward Points on Shopping Cart configuration path
     */
    const XML_PATH_REWARD_POINTS = 'payment/boltpay/reward_points';

    /**
     * Use Reward Points on Minicart configuration path
     */
    const XML_PATH_REWARD_POINTS_MINICART = 'payment/boltpay/reward_points_minicart';

    /**
     * Payment Only Checkout Enabled configuration path
     */
    const XML_PATH_PAYMENT_ONLY_CHECKOUT = 'payment/boltpay/enable_payment_only_checkout';

    /**
     * Bolt Order Caching configuration path
     */
    const XML_PATH_BOLT_ORDER_CACHING = 'payment/boltpay/bolt_order_caching';

    /**
     * Emulate Customer Session in API Calls configuration path
     */
    const XML_PATH_API_EMULATE_SESSION = 'payment/boltpay/api_emulate_session';

    /**
     * Enable Bolt Always Present Checkout button
     */
    const XML_PATH_ALWAYS_PRESENT_CHECKOUT = 'payment/boltpay/always_present_checkout';

    /**
     * Enable Bolt SSO button
     */
    const XML_PATH_BOLT_SSO = 'payment/boltpay/bolt_sso';

    /**
     * Enable Bolt SSO redirect referer
     */
    const XML_PATH_BOLT_SSO_REDIRECT_REFERER = 'payment/boltpay/bolt_sso_redirect_referer';

    /**
     * Enable Bolt Universal debug requests
     */
    const XML_PATH_DEBUG_UNIVERSAL = 'payment/boltpay/universal_debug';

    /**
     * Minify JavaScript configuration path
     */
    const XML_PATH_SHOULD_MINIFY_JAVASCRIPT = 'payment/boltpay/should_minify_javascript';

    const XML_PATH_CAPTURE_MERCHANT_METRICS = 'payment/boltpay/capture_merchant_metrics';

    const XML_PATH_TRACK_CHECKOUT_FUNNEL = 'payment/boltpay/track_checkout_funnel';

    const XML_PATH_MINIMUM_ORDER_AMOUNT = 'sales/minimum_order/amount';

    const XML_PATH_ENABLE_STORE_PICKUP_FEATURE = 'payment/boltpay/enable_store_pickup_feature';

    const XML_PATH_PICKUP_STREET = 'payment/boltpay/pickup_street';

    const XML_PATH_PICKUP_APARTMENT = 'payment/boltpay/pickup_apartment';

    const XML_PATH_PICKUP_CITY = 'payment/boltpay/pickup_city';

    const XML_PATH_PICKUP_ZIP_CODE = 'payment/boltpay/pickup_zipcode';

    const XML_PATH_PICKUP_COUNTRY_ID = 'payment/boltpay/pickup_country_id';

    const XML_PATH_PICKUP_REGION_ID = 'payment/boltpay/pickup_region_id';

    const XML_PATH_PICKUP_SHIPPING_METHOD_CODE = 'payment/boltpay/pickup_shipping_method_code';

    const XML_PATH_PRODUCT_ATTRIBUTES_LIST = 'payment/boltpay/product_attributes_list';

    /** @var string  */
    const XML_PATH_ORDER_COMMENT_FIELD = 'payment/boltpay/order_comment_field';

    /**
     * The mode of Magento integration associated with Bolt API
     */
    const XML_PATH_CONNECT_INTEGRATION_MODE = 'payment/boltpay/connect_magento_integration_mode';

    const XML_PATH_INSTANT_BUTTON_VARIANT = 'payment/boltpay/instant_button_variant';

    const XML_PATH_INSTANT_BUTTON_VARIANT_PPC = 'payment/boltpay/instant_button_variant_ppc';

    /**
     * Default whitelisted shopping cart and checkout pages "Full Action Name" identifiers, <router_controller_action>
     * Pages allowed to load Bolt javascript / show checkout button
     */
    const SHOPPING_CART_PAGE_ACTION = 'checkout_cart_index';
    const CHECKOUT_PAGE_ACTION = 'checkout_index_index';
    const SUCCESS_PAGE_ACTION = 'checkout_onepage_success';
    const HOME_PAGE_ACTION = 'cms_index_index';
    const LOGIN_PAGE_ACTION = 'customer_account_login';
    const CREATE_ACCOUNT_PAGE_ACTION = 'customer_account_create';

    /**
     * Map of human-readable config names to their XML paths
     */
    const CONFIG_SETTING_PATHS = [
        'active'                             => self::XML_PATH_ACTIVE,
        'title'                              => self::XML_PATH_TITLE,
        'api_key'                            => self::XML_PATH_API_KEY,
        'signing_secret'                     => self::XML_PATH_SIGNING_SECRET,
        'publishable_key_checkout'           => self::XML_PATH_PUBLISHABLE_KEY_CHECKOUT,
        'publishable_key_payment'            => self::XML_PATH_PUBLISHABLE_KEY_PAYMENT,
        'publishable_key_back_office'        => self::XML_PATH_PUBLISHABLE_KEY_BACK_OFFICE,
        'sandbox_mode'                       => self::XML_PATH_SANDBOX_MODE,
        'is_pre_auth'                        => self::XML_PATH_IS_PRE_AUTH,
        'product_page_checkout'              => self::XML_PATH_PRODUCT_PAGE_CHECKOUT,
        'select_product_page_checkout'       => self::XML_PATH_SELECT_PRODUCT_PAGE_CHECKOUT,
        'geolocation_api_key'                => self::XML_PATH_GEOLOCATION_API_KEY,
        'replace_selectors'                  => self::XML_PATH_REPLACE_SELECTORS,
        'totals_change_selectors'            => self::XML_PATH_TOTALS_CHANGE_SELECTORS,
        'global_css'                         => self::XML_PATH_GLOBAL_CSS,
        'global_js'                          => self::XML_PATH_GLOBAL_JS,
        'additional_checkout_button_class'   => self::XML_PATH_ADDITIONAL_CHECKOUT_BUTTON_CLASS,
        'success_page'                       => self::XML_PATH_SUCCESS_PAGE_REDIRECT,
        'prefetch_shipping'                  => self::XML_PATH_PREFETCH_SHIPPING,
        'prefetch_address_fields'            => self::XML_PATH_PREFETCH_ADDRESS_FIELDS,
        'reset_shipping_calculation'         => self::XML_PATH_RESET_SHIPPING_CALCULATION,
        'javascript_success'                 => self::XML_PATH_JAVASCRIPT_SUCCESS,
        'debug'                              => self::XML_PATH_DEBUG,
        'additional_js'                      => self::XML_PATH_ADDITIONAL_JS,
        'track_on_checkout_start'            => self::XML_PATH_TRACK_CHECKOUT_START,
        'track_on_email_enter'               => self::XML_PATH_TRACK_EMAIL_ENTER,
        'track_on_shipping_details_complete' => self::XML_PATH_TRACK_SHIPPING_DETAILS_COMPLETE,
        'track_on_shipping_options_complete' => self::XML_PATH_TRACK_SHIPPING_OPTIONS_COMPLETE,
        'track_on_payment_submit'            => self::XML_PATH_TRACK_PAYMENT_SUBMIT,
        'track_on_success'                   => self::XML_PATH_TRACK_SUCCESS,
        'track_on_close'                     => self::XML_PATH_TRACK_CLOSE,
        'additional_config'                  => self::XML_PATH_ADDITIONAL_CONFIG,
        'minicart_support'                   => self::XML_PATH_MINICART_SUPPORT,
        'enable_bolt_on_cart_page'           => self::XML_PATH_BOLT_ON_CART_PAGE,
        'ip_whitelist'                       => self::XML_PATH_IP_WHITELIST,
        'store_credit'                       => self::XML_PATH_STORE_CREDIT,
        'reward_points'                      => self::XML_PATH_REWARD_POINTS,
        'reward_points_minicart'             => self::XML_PATH_REWARD_POINTS_MINICART,
        'enable_payment_only_checkout'       => self::XML_PATH_PAYMENT_ONLY_CHECKOUT,
        'bolt_order_caching'                 => self::XML_PATH_BOLT_ORDER_CACHING,
        'api_emulate_session'                => self::XML_PATH_API_EMULATE_SESSION,
        'should_minify_javascript'           => self::XML_PATH_SHOULD_MINIFY_JAVASCRIPT,
        'capture_merchant_metrics'           => self::XML_PATH_CAPTURE_MERCHANT_METRICS,
        'track_checkout_funnel'              => self::XML_PATH_TRACK_CHECKOUT_FUNNEL,
        'connect_magento_integration_mode'   => self::XML_PATH_CONNECT_INTEGRATION_MODE,
        'instant_button_variant'             => self::XML_PATH_INSTANT_BUTTON_VARIANT,
        'instant_button_variant_ppc'         => self::XML_PATH_INSTANT_BUTTON_VARIANT_PPC
    ];

    /**
     *  Xml path to disable checkout
     *  From Magento 2.4.1, it makes Magento\Downloadable\Observer\IsAllowedGuestCheckoutObserver::XML_PATH_DISABLE_GUEST_CHECKOUT as private,
     *  so we need to define this const in class.
     */
    const XML_PATH_DISABLE_GUEST_CHECKOUT = 'catalog/downloadable/disable_guest_checkout';

    /**
     * Routes that will forbidden for users when SSO is enabled
     */
    public const PROHIBITED_CUSTOMER_ROUTES_WITH_SSO = [
        'customer_account_createPost',
        'customer_account_edit',
        'customer_account_editPost',
        'customer_account_resetPasswordPost',
        'customer_address_delete',
        'customer_address_edit',
        'customer_address_form',
        'customer_address_formPost',
        'customer_address_new',
    ];

    public static $defaultPageWhitelist = [
        self::SHOPPING_CART_PAGE_ACTION,
        self::CHECKOUT_PAGE_ACTION,
        self::SUCCESS_PAGE_ACTION
    ];

    public static $supportableProductTypesForProductPageCheckout = [
        \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE,
        \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL,
        \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE,
        \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE,
        \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE,
        'grouped'
    ];

    /**
     * @var BoltConfigSettingFactory
     */
    private $boltConfigSettingFactory;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var ResourceInterface
     */
    private $moduleResource;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var \Magento\Directory\Model\RegionFactory
     */
    private $regionFactory;

    /**
     * @var ComposerFactory
     */
    private $composerFactory;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @param Context                  $context
     * @param EncryptorInterface       $encryptor
     * @param ResourceInterface        $moduleResource
     * @param ProductMetadataInterface $productMetadata
     * @param BoltConfigSettingFactory $boltConfigSettingFactory
     * @param RegionFactory            $regionFactory
     * @param ComposerFactory          $composerFactory
     * @param WriterInterface          $configWriter
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        ResourceInterface $moduleResource,
        ProductMetadataInterface $productMetadata,
        BoltConfigSettingFactory $boltConfigSettingFactory,
        RegionFactory $regionFactory,
        ComposerFactory $composerFactory,
        WriterInterface $configWriter
    ) {
        parent::__construct($context);
        $this->encryptor = $encryptor;
        $this->moduleResource = $moduleResource;
        $this->productMetadata = $productMetadata;
        $this->boltConfigSettingFactory = $boltConfigSettingFactory;
        $this->regionFactory = $regionFactory;
        $this->composerFactory = $composerFactory;
        $this->configWriter = $configWriter;
    }

    /**
     * Get Bolt API base URL
     *
     * @param null|string $storeId
     *
     * @return string
     */
    public function getApiUrl($storeId = null)
    {
        //Check for sandbox mode
        if ($this->isSandboxModeSet($storeId)) {
            return $this->getApiUrlFromAdditionalConfig($storeId) ?: $this->getCustomURLValueOrDefault(self::XML_PATH_CUSTOM_API, self::API_URL_SANDBOX);
        }
        return self::API_URL_PRODUCTION;
    }

    /**
     * Get Bolt Merchant Dashboard URL
     *
     * @param null|string $storeId
     *
     * @return string
     */
    public function getMerchantDashboardUrl($storeId = null)
    {
        //Check for sandbox mode
        if ($this->isSandboxModeSet($storeId)) {
            return $this->getMerchantDashboardUrlFromAdditionalConfig($storeId) ?: $this->getCustomURLValueOrDefault(self::XML_PATH_CUSTOM_MERCHANT_DASH, self::MERCHANT_DASH_SANDBOX);
        }
        return self::MERCHANT_DASH_PRODUCTION ?: '';
    }

    /**
     * Get Bolt JavaScript base URL
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getCdnUrl($storeId = null)
    {
        //Check for sandbox mode
        if ($this->isSandboxModeSet($storeId)) {
            return $this->getCdnUrlFromAdditionalConfig($storeId) ?: $this->getCustomURLValueOrDefault(self::XML_PATH_CUSTOM_CDN, self::CDN_URL_SANDBOX);
        }
        return self::CDN_URL_PRODUCTION ?: '';
    }

    /**
     * Get Bolt PaybByLink URL
     *
     * @param bool $isAllowCustomURLForProduction
     * @param int|string $storeId
     *
     * @return string
     */
    public function getPayByLinkUrl($isAllowCustomURLForProduction = false, $storeId = null)
    {
        if ($this->isSandboxModeSet()) {
            $url = $this->getCdnUrlFromAdditionalConfig($storeId) ?: $this->getCustomURLValueOrDefault(self::XML_PATH_CUSTOM_CDN, self::CDN_URL_SANDBOX);
        } else if ($isAllowCustomURLForProduction) {
            $url = $this->getCdnUrlFromAdditionalConfig($storeId) ?: $this->getCustomURLValueOrDefault(self::XML_PATH_CUSTOM_CDN, self::CDN_URL_PRODUCTION);
        } else {
            $url = self::CDN_URL_PRODUCTION ?: '';
        }
        return $url . '/checkout';
    }

    /**
     * Get Bolt Account base URL
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getAccountUrl($storeId = null)
    {
        //Check for sandbox mode
        if ($this->isSandboxModeSet($storeId)) {
            return $this->getAccountUrlFromAdditionalConfig($storeId) ?: $this->getCustomURLValueOrDefault(self::XML_PATH_CUSTOM_ACCOUNT, self::ACCOUNT_URL_SANDBOX);
        }
        return self::ACCOUNT_URL_PRODUCTION ?: '';
    }

    /**
     * Get Bolt public key
     *
     * @param null|string $storeId
     *
     * @return string
     */
    public function getPublicKey($storeId = null)
    {
        // Sandbox public key is hardcoded, set the custom key for staging/development
        if ($this->isSandboxModeSet($storeId)) {
            return $this->getScopeConfig()->getValue(
                self::XML_PATH_CUSTOM_PUBLIC_KEY,
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?: 'MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAumrI98nQ0thJELhOa0AI4fQkEEuh9gHOFEQUjVZzSZO/O5x42mugJyMq3hDGwJBOH2FUgT5WnGt9tHJ9NbTwfZtljOyRkmoTUGFkQIcRZy/b0fD9/IfFXuAXJebflCIVFO/UnFRN4Z9RQqx+vffAE+qNnQV/V/455Qw0+/HW5n06Df0UVYXiZ1+2RXfGIinPcUgMS59r12kJDahELTWWcwa1gJE1UnSUiwTO7dDp1IjgGml6cpbynYcROyuz4wNumIj7w6tH+krmPguTYXPmKVSmZtqFCh1reXonSZBQ9XvuWhQbY3skf7X2AELHB6nkUNaUlVlSbG/DiHjxSAvSr3HSKLHiaYuB3VA/FWgfSWvg9kZVE9d1Qg+JhYL8kIxcWIgH37onIR5gh7lep0u73WlgFy97tjy9uiTmcjrzBBXtxl5PsLGaTJGPkZnAON4BH0Njuq23G/ZHXcJvX8uFs4VlfItq838SjJqzCrWS5eK4mKX669dYEXenjv8mqqkKSD3PNZl4ixwfMkhmVAeYA0qPnq5rt7XA5mVlr5BNkpal29fL/s6CcdfAylzvzS3C1a6z3ZpZSl2yGAfDgceC4+h+iLJmyeZM3Jz1jttE9BTUxwlhQvO/xIDkJXGgU9y8TMy/rNcPS/qOW1k4DDcTM/eCqsISa58WWiCO0WQUW6ECAwEAAQ==';
        }

        return 'MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAsfwvIFA3rstwsFizP9Yyjq8okQZFFZoxjORzdapQdf8UDQ/1hVTi26epvRKcKg0Zwxq6s/m1TWXsWJjIpAPWFVABcUufHnxgwXWyHsCedEmaJzomgoehMB/Ul4hNVfj0Dt7eNjsa2EboJ41B5Ir7MZ4LR1PRs2vlGQN73cEUQSrd0vtYPV69DXABk/o4fW+qU9iyWoCfJdhMWon32edMGnz6qxg0mFlOP0NTkkZPS3MbIoIxuhYT5/E35WoL60eSVjdPxYc4qeEK1+mOSKtjDku3mEsz5R3G20xUhK8lxSRqIIPbRyg9Y8Cg5LkXTsBDSGZX445bXsfeR9RigVblqLPFNQVcXyBV2CQe/bBwaLGxrNSXWUCWP+ITu+QVWAJ+HXMfyWDuPI0gVrlyC6BIEq6i4nC4UGZjs0MkU3mHTI2oojg6m0uDJvuwtxUq4mPdMhxXfnr8NJWvG2vzVQ+wds//9663VHbnCXi87PqtSaFbLFVUYNoJdDINQRtpnZNFxebH524k6dM9dZnA6rTdUwSoXbzE5HL64sUY6HCnNkqRS0HiwQBuz6WxDh7wuocxcXuAIwpYD3IYf1HKF1vyGtdHbLn7GhhDuelEzN3JgPdntrNp6tuLGJb47tO1KVz+/ps4e97colDjQAP7R8halVYUIGQ3rjwhZJXQP1suU0kCAwEAAQ==';
    }

    /**
     * Get module version
     *
     * @return string
     */
    public function getModuleVersion()
    {
        return $this->moduleResource->getDataVersion('Bolt_Boltpay') ?: '';
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    public function getComposerVersion()
    {
        try {
            $boltPackage = $this->composerFactory->create()
                ->getLocker()
                ->getLockedRepository()
                ->findPackage(self::BOLT_COMPOSER_NAME, '*');

            return ($boltPackage) ? $boltPackage->getVersion() : '';
        } catch (Exception $exception) {
            return '';
        }
    }

    /**
     * Get store version
     *
     * @return string
     */
    public function getStoreVersion()
    {
        return $this->productMetadata->getVersion() ?: '';
    }

    /**
     * Get one Publishable Key from config
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getAnyPublishableKey($storeId = null)
    {
        return $this->getPublishableKeyCheckout($storeId) ?: $this->getPublishableKeyPayment($storeId);
    }

    /**
     * Get API Key from config
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getTitle($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * Get API Key from config
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getApiKey($storeId = null)
    {
        return $this->getEncryptedKey(self::XML_PATH_API_KEY, $storeId) ?: '';
    }

    /**
     * Get Signing Secret Key from config
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getSigningSecret($storeId = null)
    {
        return $this->getEncryptedKey(self::XML_PATH_SIGNING_SECRET, $storeId) ?: '';
    }

    /**
     * Get Multi-step Publishable Key from config
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getPublishableKeyCheckout($storeId = null)
    {
        return $this->getEncryptedKey(self::XML_PATH_PUBLISHABLE_KEY_CHECKOUT, $storeId) ?: '';
    }

    /**
     * Get Payment Only Publishable Key from config
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getPublishableKeyPayment($storeId = null)
    {
        return $this->getEncryptedKey(self::XML_PATH_PUBLISHABLE_KEY_PAYMENT, $storeId) ?: '';
    }

    /**
     * Get Payment Only Publishable Key from config
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getPublishableKeyBackOffice($storeId = null)
    {
        return $this->getEncryptedKey(self::XML_PATH_PUBLISHABLE_KEY_BACK_OFFICE, $storeId) ?: '';
    }

    /**
     * Get Bolt color from config
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getButtonColor($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_BUTTON_COLOR,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * Get Replace Selectors from config
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getReplaceSelectors($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_REPLACE_SELECTORS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * Get Totals Change Selectors from config
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getTotalsChangeSelectors($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TOTALS_CHANGE_SELECTORS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * Get Replace Selectors from config
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getGlobalCSS($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_GLOBAL_CSS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * Get Global Javascript from config
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getGlobalJS($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_GLOBAL_JS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * Get show card type in the order grid
     *
     * @return string
     */
    public function getShowCcTypeInOrderGrid()
    {
        return $this->getScopeConfig()->getValue(self::XML_PATH_SHOW_CC_TYPE_IN_ORDER_GRID) ?: '';
    }

    /**
     * Get new plugin version notification enabled
     *
     * @return string
     */
    public function getNewPluginVersionNotificationEnabled()
    {
        return $this->getScopeConfig()->getValue(self::XML_PATH_NEW_PLUGIN_VERSION_NOTIFICATION) ?: '';
    }

    /**
     * Get additional checkout button class
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getAdditionalCheckoutButtonClass($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_ADDITIONAL_CHECKOUT_BUTTON_CLASS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * Get additional checkout button attributes configuration, stored in the following format in
     * the Additional Configuration store admin field:
     *
     * {
     *   "checkoutButtonAttributes": {
     *     "data-btn-txt": "Pay now",
     *     "data-btn-custom": "Some value"
     *   }
     * }
     *
     * @param int|string $storeId scope for which to retrieve additional checkout button attributes
     *
     * @return object containing additional checkout button attributes if available in Additional Configuration,
     *                otherwise an empty object
     */
    public function getAdditionalCheckoutButtonAttributes($storeId = null)
    {
        return $this->getAdditionalConfigProperty('checkoutButtonAttributes', $storeId) ?: (object) [];
    }

    /**
     * Get additional Custom SSO selectors configuration, stored in the following format in
     * the Additional Configuration store admin field:
     *
     * {
     *     "customSSOSelectors": {
     *         "[data-action=\"login\"]": {},
     *         "[data-action=\"logout\"]": {
     *             "logout": true
     *         },
     *         "[href*=\"wishlist\"]": {
     *             "redirect": "wishlist/index/index"
     *         },
     *         "[data-action=\"wishlist\"]": {
     *             "redirect": "http://localhost/wishlist/index/index"
     *         }
     *     }
     * }
     *
     * The default case where we want button to serve as login and to redirect to home page,
     * we have selector as key and empty object as value.
     * If we want a button to be used as logout, we again use selector as the key
     * but have logout set to true in the value object
     * If we want to redirect to an arbitrary page after login, we set the redirect key to desired URL or Magento route
     *
     *
     * @param int|string $storeId scope for which to retrieve additional checkout button attributes
     *
     * @return object
     */
    public function getAdditionalCustomSSOSelectors($storeId = null)
    {
        return $this->getAdditionalConfigProperty('customSSOSelectors', $storeId) ?: (object) [];
    }

    /**
     * Get Custom Prefetch Address Fields
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getPrefetchAddressFields($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_PREFETCH_ADDRESS_FIELDS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * Get Success Page Redirect from config
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getSuccessPageRedirect($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_SUCCESS_PAGE_REDIRECT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * Get Custom javascript function call on success
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getJavascriptSuccess($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_JAVASCRIPT_SUCCESS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * Get Product page checkout flag from config
     *
     * @param int|string|Store $store
     *
     * @return boolean
     */
    public function getProductPageCheckoutFlag($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_PRODUCT_PAGE_CHECKOUT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get select product page checkout flag from config.
     * Used to specify if product page is only enabled for specific products.
     *
     * @param int|string|Store $store
     *
     * @return boolean
     */
    public function getSelectProductPageCheckoutFlag($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_SELECT_PRODUCT_PAGE_CHECKOUT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Order management flag from config
     *
     * @param int|string|Store $store
     *
     * @return boolean
     */
    public function isOrderManagementEnabled($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_PRODUCT_ORDER_MANAGEMENT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Order management CSS selector from config
     *
     * @param int|string|Store $store
     *
     * @return string
     */
    public function getOrderManagementSelector($store = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_PRODUCT_ORDER_MANAGEMENT_SELECTOR,
            ScopeInterface::SCOPE_STORE,
            $store
        ) ?: '';
    }

    /**
     * Get Prefetch Shipping and Tax config
     *
     * @param int|string|Store $store
     *
     * @return boolean
     */
    public function getPrefetchShipping($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_PREFETCH_SHIPPING,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Reset Shipping Calculation config
     *
     * @param int|string|Store $store
     *
     * @return boolean
     */
    public function getResetShippingCalculation($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_RESET_SHIPPING_CALCULATION,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if module is enabled
     *
     * @param int|string|Store $store
     *
     * @return boolean
     */
    public function isActive($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_ACTIVE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if debug mode is on
     *
     * @param int|string|Store $store
     *
     * @return boolean
     */
    public function isDebugModeOn($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_DEBUG,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get sandbox mode config value
     *
     * @param int|string|Store $store
     *
     * @return bool
     */
    public function isSandboxModeSet($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_SANDBOX_MODE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param $path
     * @param $default
     *
     * @return mixed
     */
    public function getCustomURLValueOrDefault($path, $default)
    {
        $storedValue = $this->getScopeConfig()->getValue($path);

        return $this->validateCustomUrl($storedValue) ? $storedValue : $default;
    }

    /**
     * @return \Magento\Framework\App\Config\ScopeConfigInterface
     */
    public function getScopeConfig()
    {
        return $this->scopeConfig;
    }

    /**
     * Get Geolocation API Key from config
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getGeolocationApiKey($storeId = null)
    {
        return $this->getEncryptedKey(self::XML_PATH_GEOLOCATION_API_KEY, $storeId) ?: '';
    }

    /**
     * Get Additional Javascript from config
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getAdditionalJS($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_ADDITIONAL_JS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * @param int|string $storeId
     *
     * @return string
     */
    public function getOnCheckoutStart($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TRACK_CHECKOUT_START,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * @param int|string $storeId
     *
     * @return string
     */
    public function getOnEmailEnter($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TRACK_EMAIL_ENTER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * @param int|string $storeId
     *
     * @return string
     */
    public function getOnShippingDetailsComplete($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TRACK_SHIPPING_DETAILS_COMPLETE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * @param int|string $storeId
     *
     * @return string
     */
    public function getOnShippingOptionsComplete($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TRACK_SHIPPING_OPTIONS_COMPLETE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * @param int|string $storeId
     *
     * @return string
     */
    public function getOnPaymentSubmit($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TRACK_PAYMENT_SUBMIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * @param int|string $storeId
     *
     * @return string
     */
    public function getOnSuccess($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TRACK_SUCCESS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * @param int|string $storeId
     *
     * @return string
     */
    public function getOnClose($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TRACK_CLOSE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * Get Additional Config string
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getAdditionalConfigString($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_ADDITIONAL_CONFIG,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '{}';
    }

    /**
     * Get Is Pre-Auth flag from config
     *
     * @param int|string|Store $store
     *
     * @return boolean
     */
    public function getIsPreAuth($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_IS_PRE_AUTH,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get button variant from config
     *
     * @param int|string|Store $store
     *
     * @return string
     */
    public function getInstantButtonVariant($store = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_INSTANT_BUTTON_VARIANT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get PPC button variant from config
     *
     * @param int|string|Store $store
     *
     * @return string
     */
    public function getInstantPPCButtonVariant($store = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_INSTANT_BUTTON_VARIANT_PPC,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Toggle Checkout configuration, stored in the following format:
     *
     * {
     *   "toggleCheckout": {
     *     "active": true,
     *     "magentoButtons": [                          // Store "Proceed to Checkout" buttons
     *       "#top-cart-btn-checkout",
     *       "button[data-role=proceed-to-checkout]"
     *     ],
     *     "showElementsOnLoad": [                      // Dom nodes hidden with Global CSS until it is resolved
     *       ".checkout-methods-items",                 // which checkout to show
     *       ".block-minicart .block-content > .actions > .primary"
     *     ],
     *     "productRestrictionMethods": [               // Product model getters that can restrict Bolt checkout usage
     *       "getSubscriptionActive"                    // ParadoxLabs_Subscriptions
     *     ],
     *     "itemRestrictionMethods": [                  // Quote Item getters that can restrict Bolt checkout usage
     *       "getIsSubscription"                        // Magedelight_Subscribenow
     *     ]
     *   }
     * }
     *
     * Magento checkout buttons (links) are swapped with Bolt buttons and vice versa
     * according to Bolt checkout restriction state. Bolt checkout may be restricted if there are
     * restricted items in cart, e.g. subscription products.
     *
     * @param int|string $storeId
     *
     * @return mixed
     */
    public function getToggleCheckout($storeId = null)
    {
        return $this->getAdditionalConfigProperty('toggleCheckout', $storeId);
    }

    /**
     * Get Bolt additional configuration for Amasty Gift Card support, stored in the following format:
     *
     * {
     *   "amastyGiftCard": {
     *     "payForEverything": true|false   // true, the default,
     *   }                                  // if the gift cards can also be used to pay for the shipping and tax,
     * }                                    // false otherwise, only cart items can be payed with gift cards
     *
     * @param int|string $storeId
     *
     * @return mixed
     */
    public function getAmastyGiftCardConfig($storeId = null)
    {
        return $this->getAdditionalConfigProperty('amastyGiftCard', $storeId);
    }

    /**
     * Get MiniCart Support config
     *
     * @param int|string|Store $store
     *
     * @return boolean
     */
    public function getMinicartSupport($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_MINICART_SUPPORT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Display Bolt Checkout on the Cart Page config
     *
     * @param int|string|Store $store
     *
     * @return boolean
     */
    public function getBoltOnCartPage($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_BOLT_ON_CART_PAGE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get whitelisted pages, stored in "pageFilters.whitelist" additional configuration
     * as an array of "Full Action Name" identifiers, [<router_controller_action>]
     *
     * @param int|string $storeId
     *
     * @return array
     */
    public function getPageWhitelist($storeId = null)
    {
        return $this->getPageFilter('whitelist', $storeId);
    }

    /**
     * Get blacklisted pages, stored in "pageFilters.blacklist" additional configuration
     * as an array of "Full Action Name" identifiers, [<router_controller_action>]
     *
     * @param int|string $storeId
     *
     * @return array
     */
    public function getPageBlacklist($storeId = null)
    {
        return $this->getPageFilter('blacklist', $storeId);
    }

    /**
     * Get Bolt additional configuration for Tax Mismatch adjustment, stored in the following format:
     *
     * {
     *   "adjustTaxMismatch": true|false
     * }
     * defaults to false if not set
     *
     * @param int|string $storeId
     *
     * @return bool
     */
    public function shouldAdjustTaxMismatch($storeId = null)
    {
        return (bool) $this->getAdditionalConfigProperty('adjustTaxMismatch', $storeId);
    }

    /**
     * Get Bolt additional configuration for Price Mismatch adjustment, stored in the following format:
     *
     * {
     *    "priceFaultTolerance": "1"
     * }
     *
     * @param null $storeId
     *
     * @return int
     */
    public function getPriceFaultTolerance($storeId = null)
    {
        return (int) $this->getAdditionalConfigProperty('priceFaultTolerance', $storeId) ?: Order::MISMATCH_TOLERANCE;
    }

    /**
     * Get an array of whitelisted IPs
     *
     * @param int|string $storeId
     *
     * @return array
     */
    public function getIPWhitelistArray($storeId = null)
    {
        return array_filter(array_map('trim', explode(',', $this->getIPWhitelistConfig($storeId))));
    }

    /**
     * Gets the IP address of the requesting customer.
     * This is used instead of simply $_SERVER['REMOTE_ADDR'] to give more accurate IPs if a proxy is being used.
     *
     * @return string The IP address of the customer
     */
    public function getClientIp()
    {
        foreach ([
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ] as $key) {
            if ($ips = $this->_request->getServer($key, false)) {
                foreach (explode(',', $ips) as $ip) {
                    $ip = trim($ip); // just to be safe
                    if (filter_var(
                        $ip,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                    ) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return '';
    }

    /**
     * Check if the client IP is restricted -
     * there is an IP whitelist and the client IP is not on the list.
     *
     * @param null|mixed $storeId
     *
     * @return bool
     */
    public function isIPRestricted($storeId = null)
    {
        $clientIP = $this->getClientIp();
        $whitelist = $this->getIPWhitelistArray($storeId);
        return $whitelist && !in_array($clientIP, $whitelist);
    }

    /**
     * @return array
     */
    public function getDisabledCustomerGroups(): array
    {
        $multiselectValue = (string) $this->getScopeConfig()->getValue(self::XML_DISABLE_CUSTOMER_GROUP);
        return array_filter(
            explode(',', $multiselectValue),
            function ($elem) {
                return trim($elem) != '';
            }
        );
    }

    /**
     * Get Use Store Credit on Shopping Cart configuration
     *
     * @param int|string|Store $store
     *
     * @return bool
     */
    public function useStoreCreditConfig($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_STORE_CREDIT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Use Amasty Store Credit on Shopping Cart configuration
     *
     * @param int|string|Store $store
     *
     * @return bool
     */
    public function useAmastyStoreCreditConfig($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_AMASTY_STORE_CREDIT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Use Reward Points on Shopping Cart configuration
     *
     * @param int|string|Store $store
     *
     * @return bool
     */
    public function useRewardPointsConfig($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_REWARD_POINTS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Use Reward Points on Minicart configuration
     *
     * @param int|string|Store $store scope used for retrieving the configuration value
     *
     * @return bool Minicart Reward Points configuration flag
     */
    public function displayRewardPointsInMinicartConfig($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_REWARD_POINTS_MINICART,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Payment Only Checkout Enabled configuration
     *
     * @param int|string|Store $store
     *
     * @return bool
     */
    public function isPaymentOnlyCheckoutEnabled($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_PAYMENT_ONLY_CHECKOUT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get ignored shipping address coupons, stored in the following format:
     *
     * {
     *   "ignoredShippingAddressCoupons": ["coupon_code", "coupon_code_1", "coupon_code_2"]
     * }
     *
     * @param int|string $storeId
     *
     * @return array
     */
    public function getIgnoredShippingAddressCoupons($storeId)
    {
        $coupons = (array) $this->getAdditionalConfigProperty('ignoredShippingAddressCoupons', $storeId);

        $coupons = array_map(function ($coupon) {
            return strtolower($coupon);
        }, $coupons);

        return $coupons;
    }

    /**
     * Get Bolt Order Caching flag configuration
     *
     * @param int|string|Store $store
     *
     * @return boolean
     */
    public function isBoltOrderCachingEnabled($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_BOLT_ORDER_CACHING,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Emulate Customer Session in API Calls flag configuration
     *
     * @param int|string|Store $store
     *
     * @return boolean
     */
    public function isSessionEmulationEnabled($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_API_EMULATE_SESSION,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get minimize javascript flag configuration
     *
     * @param int|string|Store $store
     *
     * @return boolean
     */
    public function shouldMinifyJavascript($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_SHOULD_MINIFY_JAVASCRIPT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get minimize javascript flag configuration
     *
     * @param int|string|Store $store
     *
     * @return boolean
     */
    public function shouldCaptureMetrics($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_CAPTURE_MERCHANT_METRICS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get minimum order amount configuration
     *
     * @param int|string|Store $storeId
     *
     * @return float|int
     */
    public function getMinimumOrderAmount($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_MINIMUM_ORDER_AMOUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if plugin should track the funnel transition in magento's default checkout.
     *
     * @param int|string|Store $store
     *
     * @return boolean
     */
    public function shouldTrackCheckoutFunnel($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_TRACK_CHECKOUT_FUNNEL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if guest checkout is allowed
     *
     * @return boolean
     */
    public function isGuestCheckoutAllowed()
    {
        return $this->getScopeConfig()->isSetFlag(
            \Magento\Checkout\Helper\Data::XML_PATH_GUEST_CHECKOUT,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check if guest checkout for downloadable product is disabled
     *
     * @return bool
     */
    public function isGuestCheckoutForDownloadableProductDisabled()
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_DISABLE_GUEST_CHECKOUT,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get all bolt configuration settings
     *
     * @return BoltConfigSetting[]
     */
    public function getAllConfigSettings()
    {
        $boltSettings = [];

        // Active
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('active')
            ->setValue(var_export($this->isActive(), true));
        // Title
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('title')
            ->setValue($this->getTitle());
        // API Key (obscured)
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('api_key')
            ->setValue(SecretObscurer::obscure($this->getApiKey()));
        // Signing Secret (obscured)
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('signing_secret')
            ->setValue(SecretObscurer::obscure($this->getSigningSecret()));
        // Publishable Key for Checkout
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('publishable_key_checkout')
            ->setValue($this->getPublishableKeyCheckout());
        // Publishable Key for Payment
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('publishable_key_payment')
            ->setValue($this->getPublishableKeyPayment());
        // Publishable Key for Back Office
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('publishable_key_back_office')
            ->setValue($this->getPublishableKeyBackOffice());
        // Sandbox Mode
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('sandbox_mode')
            ->setValue(var_export($this->isSandboxModeSet(), true));
        // Pre-auth
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('is_pre_auth')
            ->setValue(var_export($this->getIsPreAuth(), true));
        // Product Page Checkout
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('product_page_checkout')
            ->setValue(var_export($this->getProductPageCheckoutFlag(), true));
        // Select Product Page Checkout
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('select_product_page_checkout')
            ->setValue(var_export($this->getSelectProductPageCheckoutFlag(), true));
        // Geolocation API Key (obscured)
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('geolocation_api_key')
            ->setValue(SecretObscurer::obscure($this->getGeolocationApiKey()));
        // Replace Button Selectors
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('replace_selectors')
            ->setValue($this->getReplaceSelectors());
        // Totals Monitor Selectors
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('totals_change_selectors')
            ->setValue($this->getTotalsChangeSelectors());
        // Global CSS
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('global_css')
            ->setValue($this->getGlobalCSS());
        // Global CSS
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('global_js')
            ->setValue($this->getGlobalJS());
        // Additional Checkout Button Class
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('additional_checkout_button_class')
            ->setValue($this->getAdditionalCheckoutButtonClass());
        // Success Page Redirect
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('success_page')
            ->setValue($this->getSuccessPageRedirect());
        // Prefetch Shipping
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('prefetch_shipping')
            ->setValue(var_export($this->getPrefetchShipping(), true));
        // Prefetch Address
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('prefetch_address_fields')
            ->setValue($this->getPrefetchAddressFields());
        // Reset Shipping Calculation
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('reset_shipping_calculation')
            ->setValue(var_export($this->getResetShippingCalculation(), true));
        // Javascript: success
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('javascript_success')
            ->setValue($this->getJavascriptSuccess());
        // Debug
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('debug')
            ->setValue(var_export($this->isDebugModeOn(), true));
        // Additional Javascript
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('additional_js')
            ->setValue($this->getAdditionalJS());
        // Tracking: onCheckoutStart
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('track_on_checkout_start')
            ->setValue($this->getOnCheckoutStart());
        // Tracking: onEmailEnter
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('track_on_email_enter')
            ->setValue($this->getOnEmailEnter());
        // Tracking: onShippingDetailsComplete
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('track_on_shipping_details_complete')
            ->setValue($this->getOnShippingDetailsComplete());
        // Tracking: onShippingOptionsComplete
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('track_on_shipping_options_complete')
            ->setValue($this->getOnShippingOptionsComplete());
        // Tracking: onPaymentSubmit
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('track_on_payment_submit')
            ->setValue($this->getOnPaymentSubmit());
        // Tracking: onSuccess
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('track_on_success')
            ->setValue($this->getOnSuccess());
        // Tracking: onClose
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('track_on_close')
            ->setValue($this->getOnClose());
        // Additional Configuration
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('additional_config')
            ->setValue($this->getAdditionalConfigString());
        // MiniCart Support
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('minicart_support')
            ->setValue(var_export($this->getMinicartSupport(), true));
        // Display Bolt Checkout on the Cart Page configuration path
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('enable_bolt_on_cart_page')
            ->setValue(var_export($this->getBoltOnCartPage(), true));
        // Client IP Restriction
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('ip_whitelist')
            ->setValue(implode(', ', $this->getIPWhitelistArray()));
        // Store Credit
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('store_credit')
            ->setValue(var_export($this->useStoreCreditConfig(), true));
        // Reward Points
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('reward_points')
            ->setValue(var_export($this->useRewardPointsConfig(), true));
        // Reward Points Minicart
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('reward_points_minicart')
            ->setValue(var_export($this->displayRewardPointsInMinicartConfig(), true));
        // Enable Payment Only Checkout
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('enable_payment_only_checkout')
            ->setValue(var_export($this->isPaymentOnlyCheckoutEnabled(), true));
        // Cache Bolt Order Token
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('bolt_order_caching')
            ->setValue(var_export($this->isBoltOrderCachingEnabled(), true));
        // Emulate Customer Session in API Calls
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('api_emulate_session')
            ->setValue(var_export($this->isSessionEmulationEnabled(), true));
        // Minify JavaScript
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('should_minify_javascript')
            ->setValue(var_export($this->shouldMinifyJavascript(), true));
        // Capture Internal Merchant Metrics
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('capture_merchant_metrics')
            ->setValue(var_export($this->shouldCaptureMetrics(), true));
        // Track checkout funnel
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('track_checkout_funnel')
            ->setValue(var_export($this->shouldTrackCheckoutFunnel(), true));
        // Show card type in the order grid
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('show_cc_type_in_order_grid')
            ->setValue(var_export($this->getShowCcTypeInOrderGrid(), true));
        // Enable Bolt SSO
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('bolt_sso')
            ->setValue(var_export($this->isBoltSSOEnabled(), true));
        // Enable Bolt Universal Debug
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('universal_debug')
            ->setValue(var_export($this->isBoltDebugUniversalEnabled(), true));
        // Order comment field
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('order_comment_field')
            ->setValue(var_export($this->getOrderCommentField(), true));
        // instant button variant field
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('instant_button_variant')
            ->setValue($this->getInstantButtonVariant(), true);
        // instant button variant field ppc
        $boltSettings[] = $this->boltConfigSettingFactory->create()
            ->setName('instant_button_variant_ppc')
            ->setValue($this->getInstantPPCButtonVariant());

        return $boltSettings;
    }

    /**
     * Set config setting name to the given value
     *
     * @param string      $settingName
     * @param mixed       $settingValue
     * @param null|string $storeId
     */
    public function setConfigSetting($settingName, $settingValue = null, $storeId = null)
    {
        $currentValue = $this->getScopeConfig()->getValue(
            self::CONFIG_SETTING_PATHS[$settingName],
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($currentValue != $settingValue) {
            $this->configWriter->save(
                self::CONFIG_SETTING_PATHS[$settingName],
                $settingValue,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
    }

    /**
     * @param null|string $storeId
     *
     * @return mixed
     */
    public function getPickupStreetConfiguration($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_PICKUP_STREET,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param null|string $storeId
     *
     * @return mixed
     */
    public function getPickupCityConfiguration($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_PICKUP_CITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param null|string $storeId
     *
     * @return mixed
     */
    public function getPickupZipCodeConfiguration($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_PICKUP_ZIP_CODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param null|string $storeId
     *
     * @return mixed
     */
    public function getPickupCountryIdConfiguration($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_PICKUP_COUNTRY_ID,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param null|string $storeId
     *
     * @return mixed
     */
    public function getPickupRegionIdConfiguration($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_PICKUP_REGION_ID,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param null|string $storeId
     *
     * @return mixed
     */
    public function getPickupShippingMethodCodeConfiguration($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_PICKUP_SHIPPING_METHOD_CODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param null|string $storeId
     *
     * @return mixed
     */
    public function getPickupApartmentConfiguration($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_PICKUP_APARTMENT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param null|string $storeId
     *
     * @return mixed
     */
    public function isStorePickupFeatureEnabled($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_ENABLE_STORE_PICKUP_FEATURE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get config value for whether or not always present checkout is enabled
     *
     * @param int|string|Store $storeId
     *
     * @return boolean
     */
    public function isAlwaysPresentCheckoutEnabled($storeId = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_ALWAYS_PRESENT_CHECKOUT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get config value for whether or not Bolt SSO is enabled
     *
     * @param int|string|Store $storeId
     *
     * @return boolean
     */
    public function isBoltSSOEnabled($storeId = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_BOLT_SSO,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get config value for whether or not Bolt SSO redirect referer is enabled
     *
     * @param int|string|Store $storeId
     *
     * @return boolean
     */
    public function isBoltSSORedirectRefererEnabled($storeId = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_BOLT_SSO_REDIRECT_REFERER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get config value for whether or not Bolt Debug V2 is enabled.
     *
     * @param int|string|Store $storeId
     *
     * @return boolean
     */
    public function isBoltDebugUniversalEnabled($storeId = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_DEBUG_UNIVERSAL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return array|null
     */
    public function getPickupAddressData()
    {
        $street = $this->getPickupStreetConfiguration();
        $city = $this->getPickupCityConfiguration();
        $postCode = $this->getPickupZipCodeConfiguration();
        $countryId = $this->getPickupCountryIdConfiguration();
        $regionId = $this->getPickupRegionIdConfiguration();
        $apartment = $this->getPickupApartmentConfiguration();

        if (empty($street) || empty($city) || empty($postCode) || empty($countryId) || empty($regionId)) {
            return null;
        }

        $regionCode = $this->regionFactory->create()->load($regionId)->getCode();

        if (empty($regionCode)) {
            return null;
        }

        if ($apartment) {
            $street .= "\n" . $apartment;
        }

        $addressData = [
            'street'      => trim($street),
            'city'        => $city,
            'postcode'    => $postCode,
            'country_id'  => $countryId,
            'region_id'   => $regionId,
            'region_code' => $regionCode,
        ];

        return $addressData;
    }

    /**
     * @param string $rateCode
     *
     * @return bool
     */
    public function isPickupInStoreShippingMethodCode($rateCode)
    {
        if (!$this->isStorePickupFeatureEnabled()) {
            return false;
        }
        $pickupInStoreShippingRateCode = $this->getPickupShippingMethodCodeConfiguration();
        return isset($pickupInStoreShippingRateCode) && $rateCode == $pickupInStoreShippingRateCode;
    }

    /**
     * @return bool
     */
    public function isTestEnvSet()
    {
        return isset($_SERVER['TEST_ENV']);
    }

    /**
     * @param null|string $storeId
     *
     * @return array
     */
    public function getProductAttributesList($storeId = null)
    {
        $commaSeparateList = $this->getScopeConfig()->getValue(
            self::XML_PATH_PRODUCT_ATTRIBUTES_LIST,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if (!$commaSeparateList) {
            return [];
        }
        return explode(',', $commaSeparateList);
    }

    /**
     * Gets order field configured to store comment, if not set defaults to customer_note
     *
     * @param null|string $storeId
     *
     * @return string either the configured field or 'customer_note'
     */
    public function getOrderCommentField($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_ORDER_COMMENT_FIELD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'customer_note';
    }

    /**
     * Gets the mode of Magento integration associated with Bolt API
     *
     * @return string
     */
    public function getConnectIntegrationMode()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_CONNECT_INTEGRATION_MODE
        ) ?: '';
    }

    /**
     * @param $data
     *
     * @return string
     */
    public function encrypt($data)
    {
        return $this->encryptor->encrypt($data);
    }

    /**
     * @param $data
     *
     * @return string
     */
    public function decrypt($data)
    {
        return $this->encryptor->decrypt($data);
    }

    /**
     * Get Use Aheadworks Reward Points on Shopping Cart configuration
     *
     * @param int|string|Store $store
     *
     * @return bool
     */
    public function getUseAheadworksRewardPointsConfig($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_AHEADWORKS_REWARD_POINTS_ON_CART,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Use Aheadworks Store Credit on Shopping Cart configuration
     *
     * @param int|string|Store $store
     *
     * @return bool
     */
    public function getUseAheadworksStoreCreditConfig($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_AHEADWORKS_STORE_CREDIT_ON_CART,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Use MageWorx Reward Points on Shopping Cart configuration
     *
     * @param int|string|Store $store
     *
     * @return bool
     */
    public function getUseMageWorxRewardPointsConfig($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_MAGEWORX_REWARD_POINTS_ON_CART,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param $url
     *
     * @return bool
     */
    protected function validateCustomUrl($url)
    {
        return $url && preg_match("/^https?:\/\/([a-zA-Z0-9-]+\.)+bolt.(me|com)\/?$/", $url);
    }

    /**
     * Helper method to Get Encrypted Key from config.
     * Return decrypted.
     *
     * @param string     $path
     * @param int|string $storeId
     *
     * @return string
     */
    private function getEncryptedKey($path, $storeId = null)
    {
        //Get management key
        $key = $this->getScopeConfig()->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';

        //Decrypt management key
        $key = $this->encryptor->decrypt($key);
        return $key;
    }

    /**
     * Get Additional Config object
     *
     * @param int|string $storeId scope for which to retrieve additional config property
     *
     * @return object containing Additional Config parsed from JSON
     */
    private function getAdditionalConfigObject($storeId = null)
    {
        return json_decode($this->getAdditionalConfigString($storeId));
    }

    /**
     * Get Additional Config property
     *
     * @param string     $name    name of the additional config property
     * @param int|string $storeId scope for which to retrieve additional config property
     *
     * @return mixed value of the requested property in the Additional COnfig
     */
    private function getAdditionalConfigProperty($name, $storeId = null)
    {
        $config = $this->getAdditionalConfigObject($storeId);
        return $config->$name ?? null;
    }

    /**
     * Get "pageFilters" additional configuration property, stored is the following format:
     *
     * {
     *   "pageFilters": {
     *     "whitelist": ['checkout_cart_index', 'checkout_index_index', 'checkout_onepage_success'],
     *     "blacklist": ['cms_index_index']
     *   }
     * }
     *
     * @param int|string $storeId
     *
     * @return mixed
     */
    private function getPageFilters($storeId = null)
    {
        return $this->getAdditionalConfigProperty('pageFilters', $storeId);
    }

    /**
     * Get filter specified by name from "pageFilters" additional configuration
     *
     * @param string     $filterName 'whitelist'|'blacklist'
     * @param int|string $storeId
     *
     * @return array
     */
    private function getPageFilter($filterName, $storeId = null)
    {
        $pageFilters = $this->getPageFilters($storeId);
        if ($pageFilters && isset($pageFilters->$filterName)) {
            return (array) $pageFilters->$filterName;
        }
        return [];
    }

    /**
     * Get Client IP Whitelist from config
     *
     * @param int|string $storeId
     *
     * @return string
     */
    private function getIPWhitelistConfig($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_IP_WHITELIST,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '';
    }

    /**
     * Get Bolt additional configuration for CDN URL, stored in the following format:
     *
     * {
     *   "cdnURL": "https://api-sandbox.bolt.com/"
     * }
     * defaults to empty string if not set
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getCdnUrlFromAdditionalConfig($storeId = null)
    {
        return $this->getAdditionalConfigProperty('cdnURL', $storeId) ?: '';
    }

    /**
     * Get Bolt additional configuration for account URL, stored in the following format:
     *
     * {
     *   "accountURL": "https://api-sandbox.bolt.com/"
     * }
     * defaults to empty string if not set
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getAccountUrlFromAdditionalConfig($storeId = null)
    {
        return $this->getAdditionalConfigProperty('accountURL', $storeId) ?: '';
    }

    /**
     * Get Bolt additional configuration for api URL, stored in the following format:
     *
     * {
     *   "apiURL": "https://api-sandbox.bolt.com/"
     * }
     * defaults to empty string if not set
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getApiUrlFromAdditionalConfig($storeId = null)
    {
        return $this->getAdditionalConfigProperty('apiURL', $storeId) ?: '';
    }

    /**
     * Get Bolt additional configuration for merchant dashboard URL, stored in the following format:
     *
     * {
     *   "merchantDashboardURL": "https://api-sandbox.bolt.com/"
     * }
     * defaults to empty string if not set
     *
     * @param int|string $storeId
     *
     * @return string
     */
    public function getMerchantDashboardUrlFromAdditionalConfig($storeId = null)
    {
        return $this->getAdditionalConfigProperty('merchantDashboardURL', $storeId) ?: '';
    }

    /**
     * Returns catalog ingestion maximum cron items count witch will be processed.
     *
     * @param int|string|null $websiteId
     *
     * @return string
     */
    public function getCatalogIngestionCronMaxItems($websiteId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_CATALOG_INGESTION_CRON_MAX_ITEMS,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Returns if catalog ingestion by instant job.
     *
     * @param int|string|null $websiteId
     *
     * @return bool
     */
    public function getIsCatalogIngestionInstantEnabled($websiteId = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_CATALOG_INGESTION_INSTANT_ENABLED,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Returns if catalog ingestion by async instant job.
     *
     * @param int|string|null $websiteId
     *
     * @return bool
     */
    public function getIsCatalogIngestionInstantAsyncEnabled($websiteId = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_CATALOG_INGESTION_INSTANT_ASYNC_ENABLED,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Returns available custom catalog ingestion instant job events.
     *
     * @param int|string|null $websiteId
     *
     * @return array
     */
    public function getCatalogIngestionEvents($websiteId = null)
    {
        $eventsConfigValue =  $this->getScopeConfig()->getValue(
            self::XML_PATH_CATALOG_INGESTION_INSTANT_EVENT,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
        return ($eventsConfigValue !== null) ?
            explode(',', $eventsConfigValue) : [];
    }

    /**
     * Get Integration base URL
     *
     * @param null|string $storeId
     *
     * @return string
     */
    public function getIntegrationBaseUrl($storeId = null)
    {
        //Check for sandbox mode
        if ($this->isSandboxModeSet($storeId)) {
            return $this->getMerchantDashboardUrlFromAdditionalConfig($storeId) ?: $this->getCustomURLValueOrDefault(self::XML_PATH_CUSTOM_API, self::API_URL_SANDBOX);
        }
        return self::API_URL_PRODUCTION;
    }
}
