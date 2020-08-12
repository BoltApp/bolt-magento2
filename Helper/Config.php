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

namespace Bolt\Boltpay\Helper;

use Bolt\Boltpay\Model\Api\Data\BoltConfigSettingFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ResourceInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Composer\ComposerFactory;

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
     * @var BoltConfigSettingFactory
     */
    private $boltConfigSettingFactory;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

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
     * Path for custom merchant dash, used only for dev mode.
     */
    const XML_PATH_CUSTOM_CDN = 'payment/boltpay/custom_cdn';

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
     * Client IP Whitelist configuration path
     */
    const XML_PATH_IP_WHITELIST = 'payment/boltpay/ip_whitelist';

    /**
     * Use Store Credit on Shopping Cart configuration path
     */
    const XML_PATH_STORE_CREDIT = 'payment/boltpay/store_credit';

    /**
     * Use Store Credit on Shopping Cart configuration path
     */
    const XML_PATH_AMASTY_STORE_CREDIT = 'payment/boltpay/amasty_store_credit';

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
     * Minify JavaScript configuration path
     */
    const XML_PATH_SHOULD_MINIFY_JAVASCRIPT = 'payment/boltpay/should_minify_javascript';

    const XML_PATH_CAPTURE_MERCHANT_METRICS = 'payment/boltpay/capture_merchant_metrics';

    const XML_PATH_TRACK_CHECKOUT_FUNNEL = 'payment/boltpay/track_checkout_funnel';

    const XML_PATH_MINIMUM_ORDER_AMOUNT = 'sales/minimum_order/amount';

    const XML_PATH_ENABLE_STORE_PICKUP_FEATURE = 'payment/boltpay/enable_store_pickup_feature';

    const XML_PATH_PICKUP_STREET = 'payment/boltpay/pickup_street';

    const XML_PATH_PICKUP_APARTMENT  = 'payment/boltpay/pickup_apartment';

    const XML_PATH_PICKUP_CITY = 'payment/boltpay/pickup_city';

    const XML_PATH_PICKUP_ZIP_CODE = 'payment/boltpay/pickup_zipcode';

    const XML_PATH_PICKUP_COUNTRY_ID = 'payment/boltpay/pickup_country_id';

    const XML_PATH_PICKUP_REGION_ID = 'payment/boltpay/pickup_region_id';

    const XML_PATH_PICKUP_SHIPPING_METHOD_CODE = 'payment/boltpay/pickup_shipping_method_code';

    const XML_PATH_PRODUCT_ATTRIBUTES_LIST = 'payment/boltpay/product_attributes_list';

    /**
     * Default whitelisted shopping cart and checkout pages "Full Action Name" identifiers, <router_controller_action>
     * Pages allowed to load Bolt javascript / show checkout button
     */
    const SHOPPING_CART_PAGE_ACTION = 'checkout_cart_index';
    const CHECKOUT_PAGE_ACTION = 'checkout_index_index';
    const SUCCESS_PAGE_ACTION = 'checkout_onepage_success';

    public static $defaultPageWhitelist = [
        self::SHOPPING_CART_PAGE_ACTION,
        self::CHECKOUT_PAGE_ACTION,
        self::SUCCESS_PAGE_ACTION
    ];

    public static $supportableProductTypesForProductPageCheckout = [
        \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE,
        \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL,
        \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE,
    ];
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
     * @param Context $context
     * @param EncryptorInterface $encryptor
     * @param ResourceInterface $moduleResource
     * @param ProductMetadataInterface $productMetadata
     * @param BoltConfigSettingFactory $boltConfigSettingFactory
     * @param RegionFactory $regionFactory
     * @param ComposerFactory $composerFactory
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        ResourceInterface $moduleResource,
        ProductMetadataInterface $productMetadata,
        BoltConfigSettingFactory $boltConfigSettingFactory,
        RegionFactory $regionFactory,
        ComposerFactory $composerFactory
    ) {
        parent::__construct($context);
        $this->encryptor = $encryptor;
        $this->moduleResource = $moduleResource;
        $this->productMetadata = $productMetadata;
        $this->boltConfigSettingFactory = $boltConfigSettingFactory;
        $this->regionFactory = $regionFactory;
        $this->composerFactory = $composerFactory;
    }

    /**
     * Get Bolt API base URL
     *
     * @param null|string $storeId
     *
     * @return  string
     */
    public function getApiUrl($storeId = null)
    {
        //Check for sandbox mode
        if ($this->isSandboxModeSet($storeId)) {
            return $this->getCustomURLValueOrDefault(self::XML_PATH_CUSTOM_API, self::API_URL_SANDBOX);
        } else {
            return self::API_URL_PRODUCTION;
        }
    }

    /**
     * Get Bolt Merchant Dashboard URL
     *
     * @param null|string $storeId
     *
     * @return  string
     */
    public function getMerchantDashboardUrl($storeId = null)
    {
        //Check for sandbox mode
        if ($this->isSandboxModeSet($storeId)) {
            return $this->getCustomURLValueOrDefault(self::XML_PATH_CUSTOM_MERCHANT_DASH, self::MERCHANT_DASH_SANDBOX);
        } else {
            return self::MERCHANT_DASH_PRODUCTION;
        }
    }

    /**
     * Get Bolt JavaScript base URL
     *
     * @param int|string $storeId
     *
     * @return  string
     */
    public function getCdnUrl($storeId = null)
    {
        //Check for sandbox mode
        if ($this->isSandboxModeSet($storeId)) {
            return $this->getCustomURLValueOrDefault(self::XML_PATH_CUSTOM_CDN, self::CDN_URL_SANDBOX);
        } else {
            return self::CDN_URL_PRODUCTION;
        }
    }

    /**
     * Get module version
     * @return false|string
     */
    public function getModuleVersion()
    {
        return $this->moduleResource->getDataVersion('Bolt_Boltpay');
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getComposerVersion()
    {
        try {
            $boltPackage = $this->composerFactory->create()
                ->getLocker()
                ->getLockedRepository()
                ->findPackage(self::BOLT_COMPOSER_NAME, '*');

            return ($boltPackage) ? $boltPackage->getVersion() : null;
        }catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * Get store version
     * @return false|string
     */
    public function getStoreVersion()
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * Helper method to Get Encrypted Key from config.
     * Return decrypted.
     *
     * @param string $path
     * @param int|string $storeId
     *
     * @return  string
     */
    private function getEncryptedKey($path, $storeId = null)
    {
        //Get management key
        $key =  $this->getScopeConfig()->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        //Decrypt management key
        $key = $this->encryptor->decrypt($key);
        return $key;
    }

    /**
     * Get one Publishable Key from config
     *
     * @param int|string $storeId
     *
     * @return  string
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
     * @return  string
     */
    public function getTitle($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get API Key from config
     *
     * @param int|string $storeId
     *
     * @return  string
     */
    public function getApiKey($storeId = null)
    {
        return $this->getEncryptedKey(self::XML_PATH_API_KEY, $storeId);
    }

    /**
     * Get Signing Secret Key from config
     *
     * @param int|string $storeId
     *
     * @return  string
     */
    public function getSigningSecret($storeId = null)
    {
        return $this->getEncryptedKey(self::XML_PATH_SIGNING_SECRET, $storeId);
    }

    /**
     * Get Multi-step Publishable Key from config
     *
     * @param int|string $storeId
     *
     * @return  string
     */
    public function getPublishableKeyCheckout($storeId = null)
    {
        return $this->getEncryptedKey(self::XML_PATH_PUBLISHABLE_KEY_CHECKOUT, $storeId);
    }

    /**
     * Get Payment Only Publishable Key from config
     *
     * @param int|string $storeId
     *
     * @return  string
     */
    public function getPublishableKeyPayment($storeId = null)
    {
        return $this->getEncryptedKey(self::XML_PATH_PUBLISHABLE_KEY_PAYMENT, $storeId);
    }

    /**
     * Get Payment Only Publishable Key from config
     *
     * @param int|string $storeId
     *
     * @return  string
     */
    public function getPublishableKeyBackOffice($storeId = null)
    {
        return $this->getEncryptedKey(self::XML_PATH_PUBLISHABLE_KEY_BACK_OFFICE, $storeId);
    }

    /**
     * Get Bolt color from config
     *
     * @param int|string $storeId
     *
     * @return  string
     */
    public function getButtonColor($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_BUTTON_COLOR,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Replace Selectors from config
     *
     * @param int|string $storeId
     *
     * @return  string
     */
    public function getReplaceSelectors($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_REPLACE_SELECTORS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Totals Change Selectors from config
     *
     * @param int|string $storeId
     *
     * @return  string
     */
    public function getTotalsChangeSelectors($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TOTALS_CHANGE_SELECTORS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Replace Selectors from config
     *
     * @param int|string $storeId
     *
     * @return  string
     */
    public function getGlobalCSS($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_GLOBAL_CSS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
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
        );
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
     * otherwise an empty object
     */
    public function getAdditionalCheckoutButtonAttributes($storeId = null)
    {
        return $this->getAdditionalConfigProperty('checkoutButtonAttributes', $storeId) ?: (object)[];
    }

    /**
     * Get Custom Prefetch Address Fields
     *
     * @param int|string $storeId
     *
     * @return  string
     */
    public function getPrefetchAddressFields($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_PREFETCH_ADDRESS_FIELDS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Success Page Redirect from config
     *
     * @param int|string $storeId
     *
     * @return  string
     */
    public function getSuccessPageRedirect($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_SUCCESS_PAGE_REDIRECT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Custom javascript function call on success
     *
     * @param int|string $storeId
     *
     * @return  string
     */
    public function getJavascriptSuccess($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_JAVASCRIPT_SUCCESS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Product page checkout flag from config
     *
     * @param int|string|Store $store
     *
     * @return  boolean
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
     * Get Order management flag from config
     *
     * @param int|string|Store $store
     *
     * @return  boolean
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
     * @return  string
     */
    public function getOrderManagementSelector($store = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_PRODUCT_ORDER_MANAGEMENT_SELECTOR,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Prefetch Shipping and Tax config
     *
     * @param int|string|Store $store
     *
     * @return  boolean
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
     * @return  boolean
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
     * @return  boolean
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
     * @return  boolean
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
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
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
     * @return mixed
     */
    public function getCustomURLValueOrDefault($path, $default)
    {
        $storedValue = $this->getScopeConfig()->getValue($path);

        return $this->validateCustomUrl($storedValue) ? $storedValue : $default;
    }

    /**
     * @param $url
     * @return bool
     */
    protected function validateCustomUrl($url)
    {
        return (
            $url
            && preg_match("/^https?:\/\/([a-zA-Z0-9]+\.)+bolt.me\/?$/", $url)
        );
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
     * @return  string
     */
    public function getGeolocationApiKey($storeId = null)
    {
        return $this->getEncryptedKey(self::XML_PATH_GEOLOCATION_API_KEY, $storeId);
    }

    /**
     * Get Additional Javascript from config
     *
     * @param int|string $storeId
     *
     * @return  string
     */
    public function getAdditionalJS($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_ADDITIONAL_JS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
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
        );
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
        );
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
        );
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
        );
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
        );
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
        );
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
        );
    }

    /**
     * Get Additional Config string
     *
     * @param int|string $storeId
     *
     * @return  string
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
     * @param string $name of the additional config property
     * @param int|string $storeId scope for which to retrieve additional config property
     *
     * @return mixed value of the requested property in the Additional COnfig
     */
    private function getAdditionalConfigProperty($name, $storeId = null)
    {
        $config = $this->getAdditionalConfigObject($storeId);
        return @$config->$name;
    }

    /**
     * Get Is Pre-Auth flag from config
     *
     * @param int|string|Store $store
     *
     * @return  boolean
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
     * @return  boolean
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
     * @param string $filterName 'whitelist'|'blacklist'
     * @param int|string $storeId
     * @return array
     */
    private function getPageFilter($filterName, $storeId = null)
    {
        $pageFilters = $this->getPageFilters($storeId);
        if ($pageFilters && @$pageFilters->$filterName) {
            return (array)$pageFilters->$filterName;
        }
        return [];
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
        return (bool)$this->getAdditionalConfigProperty('adjustTaxMismatch', $storeId);
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
     * @return string
     */
    public function getPriceFaultTolerance($storeId = null)
    {
        return (int) $this->getAdditionalConfigProperty('priceFaultTolerance', $storeId) ?: Order::MISMATCH_TOLERANCE;
    }

    /**
     * Get Client IP Whitelist from config
     *
     * @param int|string $storeId
     *
     * @return  string
     */
    private function getIPWhitelistConfig($storeId = null)
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_IP_WHITELIST,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
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
     * @return string  The IP address of the customer
     */
    public function getClientIp()
    {
        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP',
                     'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR',] as $key) {
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
    }

    /**
     * Check if the client IP is restricted -
     * there is an IP whitelist and the client IP is not on the list.
     *
     * @return bool
     */
    public function isIPRestricted($storeId = null)
    {
        $clientIP = $this->getClientIp();
        $whitelist = $this->getIPWhitelistArray($storeId);
        return $whitelist && ! in_array($clientIP, $whitelist);
    }

    /**
     * Get Use Store Credit on Shopping Cart configuration
     *
     * @param int|string|Store $store
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
     * @param  int|string|Store $store
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
     * @return array
     */
    public function getIgnoredShippingAddressCoupons($storeId)
    {
        $coupons = (array)$this->getAdditionalConfigProperty('ignoredShippingAddressCoupons', $storeId);

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
     * @return  boolean
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
     * @return  boolean
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
     * @return  boolean
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
     * @return  boolean
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
     * @return  boolean
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
        // Client IP Restriction
        $boltSettings[] = $this->boltConfigSettingFactory->create()
                                                         ->setName('ip_whitelist')
                                                         ->setValue(implode(", ", $this->getIPWhitelistArray()));
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

        return $boltSettings;
    }

    /**
     * @param null $storeId
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
     * @param null $storeId
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
     * @param null $storeId
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
     * @param null $storeId
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
     * @param null $storeId
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
     * @param null $storeId
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
     * @param null $storeId
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
     * @param null $storeId
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
     * @param int|string|Store $storeId
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
            $street .= "\n".$apartment;
        }

        $addressData = [
            'street' => trim($street),
            'city' => $city,
            'postcode' => $postCode,
            'country_id' => $countryId,
            'region_id' => $regionId,
            'region_code' => $regionCode,
        ];

        return $addressData;
    }

    /**
     * @param $rateCode
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
     * @param null $storeId
     * @return array
     */
    public function getProductAttributesList($storeId = null)
    {
        $commaSeparateList =  $this->getScopeConfig()->getValue(
            self::XML_PATH_PRODUCT_ATTRIBUTES_LIST,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if (!$commaSeparateList) {
            return [];
        }
        return explode(",", $commaSeparateList);
    }
}
