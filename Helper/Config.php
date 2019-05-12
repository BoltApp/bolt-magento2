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
use Magento\Framework\App\Request\Http as Request;

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
     * Automatic capture mode
     */
    const XML_PATH_AUTOMATIC_CAPTURE_MODE = 'payment/boltpay/automatic_capture_mode';

    /**
     * Enable product page checkout
     */
    const XML_PATH_PRODUCT_PAGE_CHECKOUT = 'payment/boltpay/product_page_checkout';


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

    public static $supportableProductTypes = [
        \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE,
        \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL,
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
     * @var Request
     */
    private $request;

    /**
     * @param Context $context
     * @param EncryptorInterface $encryptor
     * @param ResourceInterface $moduleResource
     * @param ProductMetadataInterface $productMetadata
     * @param Request $request
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        ResourceInterface $moduleResource,
        ProductMetadataInterface $productMetadata,
        Request $request
    ) {
        parent::__construct($context);
        $this->encryptor = $encryptor;
        $this->moduleResource = $moduleResource;
        $this->productMetadata = $productMetadata;
        $this->request = $request;
    }

    /**
     * Get Bolt API base URL
     *
     * @return  string
     */
    public function getApiUrl()
    {
        //Check for sandbox mode
        if ($this->isSandboxModeSet()) {
            return self::API_URL_SANDBOX;
        } else {
            return self::API_URL_PRODUCTION;
        }
    }

    /**
     * Get Bolt Merchant Dashboard URL
     *
     * @return  string
     */
    public function getMerchantDashboardUrl()
    {
        //Check for sandbox mode
        if ($this->isSandboxModeSet()) {
            return self::MERCHANT_DASH_SANDBOX;
        } else {
            return self::MERCHANT_DASH_PRODUCTION;
        }
    }

    /**
     * Get Bolt JavaScript base URL
     *
     * @return  string
     */
    public function getCdnUrl()
    {
        //Check for sandbox mode
        if ($this->isSandboxModeSet()) {
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
     *
     * @return  string
     */
    private function getEncryptedKey($path)
    {
        //Get management key
        $key =  $this->getScopeConfig()->getValue(
            $path,
            ScopeInterface::SCOPE_STORE
        );

        //Decrypt management key
        $key = $this->encryptor->decrypt($key);
        return $key;
    }

    /**
     * Get one Publishable Key from config
     *
     * @return  string
     */
    public function getAnyPublishableKey()
    {
        return $this->getPublishableKeyCheckout() ?: $this->getPublishableKeyPayment();
    }

    /**
     * Get API Key from config
     *
     * @return  string
     */
    public function getApiKey()
    {
        return $this->getEncryptedKey(self::XML_PATH_API_KEY);
    }

    /**
     * Get Signing Secret Key from config
     *
     * @return  string
     */
    public function getSigningSecret()
    {
        return $this->getEncryptedKey(self::XML_PATH_SIGNING_SECRET);
    }

    /**
     * Get Multi-step Publishable Key from config
     *
     * @return  string
     */
    public function getPublishableKeyCheckout()
    {
        return $this->getEncryptedKey(self::XML_PATH_PUBLISHABLE_KEY_CHECKOUT);
    }

    /**
     * Get Payment Only Publishable Key from config
     *
     * @return  string
     */
    public function getPublishableKeyPayment()
    {
        return $this->getEncryptedKey(self::XML_PATH_PUBLISHABLE_KEY_PAYMENT);
    }

    /**
     * Get Payment Only Publishable Key from config
     *
     * @return  string
     */
    public function getPublishableKeyBackOffice()
    {
        return $this->getEncryptedKey(self::XML_PATH_PUBLISHABLE_KEY_BACK_OFFICE);
    }

    /**
     * Get Replace Selectors from config
     *
     * @return  string
     */
    public function getReplaceSelectors()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_REPLACE_SELECTORS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Totals Change Selectors from config
     *
     * @return  string
     */
    public function getTotalsChangeSelectors()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TOTALS_CHANGE_SELECTORS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Replace Selectors from config
     *
     * @return  string
     */
    public function getGlobalCSS()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_GLOBAL_CSS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get additional checkout button class
     *
     * @return string
     */
    public function getAdditionalCheckoutButtonClass()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_ADDITIONAL_CHECKOUT_BUTTON_CLASS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Custom Prefetch Address Fields
     *
     * @return  string
     */
    public function getPrefetchAddressFields()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_PREFETCH_ADDRESS_FIELDS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Success Page Redirect from config
     *
     * @return  string
     */
    public function getSuccessPageRedirect()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_SUCCESS_PAGE_REDIRECT,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Custom javascript function call on success
     *
     * @return  string
     */
    public function getjavascriptSuccess()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_JAVASCRIPT_SUCCESS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Automatic Capture mode from config
     *
     * @param int|string|Store $store
     *
     * @return  boolean
     */
    public function getAutomaticCaptureMode($store = null)
    {
        return $this->getScopeConfig()->isSetFlag(
            self::XML_PATH_AUTOMATIC_CAPTURE_MODE,
            ScopeInterface::SCOPE_STORE,
            $store
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
     * @return  string
     */
    public function getGeolocationApiKey()
    {
        return $this->getEncryptedKey(self::XML_PATH_GEOLOCATION_API_KEY);
    }

    /**
     * Get Additional Javascript from config
     *
     * @return  string
     */
    public function getAdditionalJS()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_ADDITIONAL_JS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getOnCheckoutStart()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TRACK_CHECKOUT_START,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getOnEmailEnter()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TRACK_EMAIL_ENTER,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getOnShippingDetailsComplete()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TRACK_SHIPPING_DETAILS_COMPLETE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getOnShippingOptionsComplete()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TRACK_SHIPPING_OPTIONS_COMPLETE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getOnPaymentSubmit()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TRACK_PAYMENT_SUBMIT,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getOnSuccess()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TRACK_SUCCESS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getOnClose()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_TRACK_CLOSE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Additional Config string
     *
     * @return  string
     */
    protected function getAdditionalConfigString()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_ADDITIONAL_CONFIG,
            ScopeInterface::SCOPE_STORE
        ) ?: '{}';
    }

    /**
     * Get Additional Config object
     *
     * @return  \stdClass
     */
    private function getAdditionalConfigObject()
    {
        return json_decode($this->getAdditionalConfigString());
    }

    /**
     * Get Additional Config property
     *
     * @param $name
     * @return mixed
     */
    private function getAdditionalConfigProperty($name)
    {
        $config = $this->getAdditionalConfigObject();
        return @$config->$name;
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
     * @return mixed
     */
    public function getToggleCheckout()
    {
        return $this->getAdditionalConfigProperty('toggleCheckout');
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
     * @return mixed
     */
    public function getAmastyGiftCardConfig()
    {
        return $this->getAdditionalConfigProperty('amastyGiftCard');
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
     * @return mixed
     */
    private function getPageFilters()
    {
        return $this->getAdditionalConfigProperty('pageFilters');
    }

    /**
     * Get filter specified by name from "pageFilters" additional configuration
     *
     * @param string $filterName   'whitelist'|'blacklist'
     * @return array
     */
    private function getPageFilter($filterName)
    {
        $pageFilters = $this->getPageFilters();
        if ($pageFilters && @$pageFilters->$filterName) {
            return (array)$pageFilters->$filterName;
        }
        return [];
    }

    /**
     * Get whitelisted pages, stored in "pageFilters.whitelist" additional configuration
     * as an array of "Full Action Name" identifiers, [<router_controller_action>]
     *
     * @return array
     */
    public function getPageWhitelist()
    {
        return $this->getPageFilter('whitelist');
    }

    /**
     * Get blacklisted pages, stored in "pageFilters.blacklist" additional configuration
     * as an array of "Full Action Name" identifiers, [<router_controller_action>]
     *
     * @return array
     */
    public function getPageBlacklist()
    {
        return $this->getPageFilter('blacklist');
    }

    /**
     * Get Bolt additional configuration for Tax Mismatch adjustment, stored in the following format:
     *
     * {
     *   "adjustTaxMismatch": true|false
     * }
     * defaults to false if not set
     *
     * @return bool
     */
    public function shouldAdjustTaxMismatch()
    {
        return (bool)$this->getAdditionalConfigProperty('adjustTaxMismatch');
    }

    /**
     * Get Client IP Whitelist from config
     *
     * @return  string
     */
    private function getIPWhitelistConfig()
    {
        return $this->getScopeConfig()->getValue(
            self::XML_PATH_IP_WHITELIST,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get an array of wtitelisted IPs
     *
     * @return array
     */
    public function getIPWhitelistArray()
    {
        return array_filter(array_map('trim', explode(',', $this->getIPWhitelistConfig())));
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
            if ($ips = $this->request->getServer($key, false)) {
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
    public function isIPRestricted()
    {
        $clientIP = $this->getClientIp();
        $whitelist = $this->getIPWhitelistArray();
        return $whitelist && ! in_array($clientIP, $whitelist);
    }

    /**
     * Get Use Store Credit on Shopping Cart configuration
     *
     * @param int|string|Store $store
     * @return bool
     */
    public function getStoreCreditConfig($store = null)
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
}
