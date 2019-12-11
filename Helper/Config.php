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

namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ResourceInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Encryption\EncryptorInterface;
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

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

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
     * Minify JavaScript configuration path
     */
    const XML_PATH_SHOULD_MINIFY_JAVASCRIPT = 'payment/boltpay/should_minify_javascript';

    const XML_PATH_CAPTURE_MERCHANT_METRICS = 'payment/boltpay/capture_merchant_metrics';

    const XML_PATH_TRACK_CHECKOUT_FUNNEL = 'payment/boltpay/track_checkout_funnel';

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

    /**
     * @var ResourceInterface
     */
    private $moduleResource;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @param Context                  $context
     * @param EncryptorInterface       $encryptor
     * @param ResourceInterface        $moduleResource
     * @param ProductMetadataInterface $productMetadata
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        ResourceInterface $moduleResource,
        ProductMetadataInterface $productMetadata
    ) {
        parent::__construct($context);
        $this->encryptor = $encryptor;
        $this->moduleResource = $moduleResource;
        $this->productMetadata = $productMetadata;
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
            return self::API_URL_SANDBOX;
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
            return self::MERCHANT_DASH_SANDBOX;
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
            return self::CDN_URL_SANDBOX;
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
     * @codeCoverageIgnore
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
    protected function getAdditionalConfigString($storeId = null)
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
     * @param int|string $storeId
     *
     * @return  \stdClass
     */
    private function getAdditionalConfigObject($storeId = null)
    {
        return json_decode($this->getAdditionalConfigString($storeId));
    }

    /**
     * Get Additional Config property
     *
     * @param $name
     * @param int|string $storeId
     *
     * @return string
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
     * @param string $filterName   'whitelist'|'blacklist'
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
            'sales/minimum_order/amount',
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
            self::XML_PATH_ACTIVE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
