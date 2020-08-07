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

namespace Bolt\Boltpay\Block;

use Bolt\Boltpay\Helper\Config;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Session\SessionManager as CheckoutSession;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * Js Block. The block class used in track.phtml block.
 *
 * @SuppressWarnings(PHPMD.DepthOfInheritance)
 */
class Js extends Template
{
    use BlockTrait;

    /** @var CheckoutSession */
    private $checkoutSession;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /** @var Bugsnag  Bug logging interface */
    private $bugsnag;

    /** @var array */
    private static $blockAlreadyShown;

    /**
     * @param Context $context
     * @param Config $configHelper
     * @param CheckoutSession $checkoutSession
     * @param CartHelper $cartHelper
     * @param Bugsnag $bugsnag
     * @param Decider $featureSwitches
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config $configHelper,
        CheckoutSession $checkoutSession,
        CartHelper $cartHelper,
        Bugsnag $bugsnag,
        Decider $featureSwitches,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configHelper = $configHelper;
        $this->checkoutSession = $checkoutSession;
        $this->cartHelper = $cartHelper;
        $this->bugsnag = $bugsnag;
        $this->featureSwitches = $featureSwitches;
    }

    /**
     * Get track js url
     *
     * @return  string
     */
    public function getTrackJsUrl()
    {
        $cdnUrl = $this->configHelper->getCdnUrl();

        return $cdnUrl . '/track.js';
    }

    /**
     * Get connect js url
     *
     * @return  string
     */
    public function getConnectJsUrl()
    {
        $cdnUrl = $this->configHelper->getCdnUrl();

        return $cdnUrl . '/connect.js';
    }

    /**
     * Get pay-by-link url
     *
     * @return  string
     */
    public function getPayByLinkUrl()
    {
        $cdnUrl = $this->configHelper->getCdnUrl();
        return $cdnUrl . '/checkout';
    }

    /**
     * Get M2 Plugin setting for always present checkout button
     * @return boolean
     */
    public function enableAlwaysPresentCheckoutButton()
    {
        $storeId = $this->getStoreId();
        return $this->configHelper->isAlwaysPresentCheckoutEnabled($storeId)
            && $this->featureSwitches->isAlwaysPresentCheckoutEnabled();
    }

    /**
     * Get account js url
     *
     * @return  string
     */
    public function getAccountJsUrl()
    {
        $cdnUrl = $this->configHelper->getCdnUrl();

        return $cdnUrl . '/account.js';
    }

    /**
     * Get Replace Button Selectors.
     *
     * @return string
     */
    public function getReplaceSelectors()
    {
        $isBoltUsedInCheckoutPage = $this->configHelper->isPaymentOnlyCheckoutEnabled()
            && $this->_request->getFullActionName() == Config::CHECKOUT_PAGE_ACTION;
        $subject = ($isBoltUsedInCheckoutPage) ? '' : trim($this->configHelper->getReplaceSelectors());

        return array_filter(explode(',', preg_replace('/\s+/', ' ', $subject)));
    }

    /**
     * Get Totals Change Selectors.
     *
     * @return string
     */
    public function getTotalsChangeSelectors()
    {
        $subject = trim($this->configHelper->getTotalsChangeSelectors());

        return array_filter(explode(',', preg_replace('/\s+/', ' ', $subject)));
    }

    /**
     * Get Additional button class.
     *
     * @return string
     */
    public function getAdditionalCheckoutButtonClass()
    {
        return trim($this->configHelper->getAdditionalCheckoutButtonClass());
    }

    /**
     * Get Additional button attributes
     *
     * @return object|array Button attributes object or empty array if not provided
     */
    public function getAdditionalCheckoutButtonAttributes()
    {
        return $this->configHelper->getAdditionalCheckoutButtonAttributes();
    }

    /**
     * Get the global CSS to be added to any page that displays the Bolt Checkout button.
     *
     * @return string global CSS
     */
    public function getGlobalCSS()
    {
        return $this->configHelper->getGlobalCSS();
    }

    /**
     * Get Javascript function call on success.
     *
     * @return string
     */
    public function getJavascriptSuccess()
    {
        return $this->configHelper->getJavascriptSuccess();
    }

    /**
     * Get Additional Javascript.
     *
     * @return string
     */
    public function getAdditionalJavascript()
    {
        return $this->configHelper->getAdditionalJS();
    }

    /**
     * Gets the auto-open Bolt checkout session flag, and then unsets it so that it is only used once.
     *
     * @return bool
     */
    public function getInitiateCheckout()
    {
        $flag = $this->checkoutSession->getBoltInitiateCheckout();
        $this->checkoutSession->unsBoltInitiateCheckout();
        return (bool)$flag;
    }

    /**
     * Return if the instant checkout is enabled
     *
     * @return bool
     */
    public function getIsInstantCheckoutButton()
    {
        return $this->featureSwitches->isInstantCheckoutButton();
    }

    /**
     * Get Javascript page settings.
     * @return string
     */
    public function getSettings()
    {
        return json_encode([
            'connect_url' => $this->getConnectJsUrl(),
            'publishable_key_payment' => $this->configHelper->getPublishableKeyPayment(),
            'publishable_key_checkout' => $this->configHelper->getPublishableKeyCheckout(),
            'publishable_key_back_office' => $this->configHelper->getPublishableKeyBackOffice(),
            'create_order_url' => $this->getUrl(Config::CREATE_ORDER_ACTION),
            'save_order_url' => $this->getUrl(Config::SAVE_ORDER_ACTION),
            'get_hints_url' => $this->getUrl(Config::GET_HINTS_ACTION),
            'selectors' => $this->getReplaceSelectors(),
            'shipping_prefetch_url' => $this->getUrl(Config::SHIPPING_PREFETCH_ACTION),
            'prefetch_shipping' => $this->configHelper->getPrefetchShipping(),
            'save_email_url' => $this->getUrl(Config::SAVE_EMAIL_ACTION),
            'pay_by_link_url' => $this->featureSwitches->isPayByLinkEnabled() ? $this->getPayByLinkUrl() : null,
            'quote_is_virtual' => $this->getQuoteIsVirtual(),
            'totals_change_selectors' => $this->getTotalsChangeSelectors(),
            'additional_checkout_button_class' => $this->getAdditionalCheckoutButtonClass(),
            'additional_checkout_button_attributes' => $this->getAdditionalCheckoutButtonAttributes(),
            'initiate_checkout' => $this->getInitiateCheckout(),
            'toggle_checkout' => $this->getToggleCheckout(),
            'is_pre_auth' => $this->getIsPreAuth(),
            'default_error_message' => $this->getBoltPopupErrorMessage(),
            'button_css_styles' => $this->getButtonCssStyles(),
            'is_instant_checkout_button' => $this->getIsInstantCheckoutButton(),
            'cdn_url' => $this->configHelper->getCdnUrl(),
            'always_present_checkout' => $this->enableAlwaysPresentCheckoutButton(),
            'account_url' => $this->getAccountJsUrl(),
            'order_management_selector' => $this->getOrderManagementSelector(),
        ]);
    }

    /**
     * Get quote is virtual flag, false if no existing quote
     * @return bool
     */
    private function getQuoteIsVirtual()
    {
        $quote = $this->getQuoteFromCheckoutSession();
        return $quote ? $quote->isVirtual() : false;
    }

    /**
     * @return string
     */
    public function getBoltPopupErrorMessage()
    {
        $contact_email = $this->_scopeConfig->getValue('trans_email/ident_support/email') ?:
            $this->_scopeConfig->getValue('trans_email/ident_general/email') ?: '';
        return __('Your payment was successful and we\'re now processing your order.' .
            'If you don\'t receive order confirmation email in next 30 minutes, please contact us at ' .
            $contact_email . '.');
    }

    /**
     * @return array
     */
    public function getTrackCallbacks()
    {
        return [
            'checkout_start' => $this->getOnCheckoutStart(),
            'email_enter' => $this->getOnEmailEnter(),
            'shipping_details_complete' => $this->getOnShippingDetailsComplete(),
            'shipping_options_complete' => $this->getOnShippingOptionsComplete(),
            'payment_submit' => $this->getOnPaymentSubmit(),
            'success' => $this->getOnSuccess(),
            'close' => $this->getOnClose(),
        ];
    }

    /**
     * @return string
     */
    protected function getOnCheckoutStart()
    {
        return $this->configHelper->getOnCheckoutStart();
    }

    /**
     * @return string
     */
    protected function getOnEmailEnter()
    {
        return $this->configHelper->getOnEmailEnter();
    }

    /**
     * @return string
     */
    protected function getOnShippingDetailsComplete()
    {
        return $this->configHelper->getOnShippingDetailsComplete();
    }

    /**
     * @return string
     */
    protected function getOnShippingOptionsComplete()
    {
        return $this->configHelper->getOnShippingOptionsComplete();
    }

    /**
     * @return string
     */
    protected function getOnPaymentSubmit()
    {
        return $this->configHelper->getOnPaymentSubmit();
    }

    /**
     * @return string
     */
    protected function getOnSuccess()
    {
        return $this->configHelper->getOnSuccess();
    }

    /**
     * @return string
     */
    protected function getOnClose()
    {
        return $this->configHelper->getOnClose();
    }

    /**
     * Get Toggle Checkout configuration
     *
     * @return mixed
     */
    private function getToggleCheckout()
    {
        $toggleCheckout = $this->configHelper->getToggleCheckout();
        return $toggleCheckout && $toggleCheckout->active ? $toggleCheckout : null;
    }

    /**
     * Get Is Pre-Auth configuration
     *
     * @return bool
     */
    private function getIsPreAuth()
    {
        $storeId = $this->getStoreId();
        return $this->configHelper->getIsPreAuth($storeId);
    }

    /**
     * @return Quote
     */
    protected function getQuoteFromCheckoutSession()
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * Get plugin version
     *
     * @return string|false Plugin version string or false if the module is missing or there is a DB connection problem
     */
    public function getModuleVersion()
    {
        return $this->configHelper->getModuleVersion();
    }

    /**
     * Takes a string containing javascript and removes unneeded characters in
     * order to shrink the code without altering it's functionality.
     *
     * @param string $js
     * @return string
     * @throws \Exception
     */
    public function minifyJs($js)
    {
        if ($this->configHelper->shouldMinifyJavascript()) {
            try {
                return \JShrink\Minifier::minify($js);
            } catch (\Exception $e) {
                $this->bugsnag->notifyException($e);
                return $js;
            }
        } else {
            return $js;
        }
    }

    /**
     * Return true if we are on cart page or checkout page
     */
    public function isOnPageFromWhiteList()
    {
        $currentPage = $this->getRequest()->getFullActionName();
        return in_array($currentPage, $this->getPageWhitelist());
    }

    /**
     * Return true if bolt on minicart is enabled
     */
    public function isMinicartEnabled()
    {
        return $this->configHelper->getMinicartSupport();
    }

    /**
     * Return true if we are on product page, and bolt on product page is enabled
     */
    public function isBoltProductPage()
    {
        if (!$this->configHelper->getProductPageCheckoutFlag()) {
            return false;
        }
        $currentPage = $this->getRequest()->getFullActionName();
        return $currentPage == "catalog_product_view";
    }

    /**
     * Return CSS styles for bolt button
     * @return string
     */
    public function getButtonCssStyles()
    {
        $buttonColor = $this->configHelper->getButtonColor();
        if (!$buttonColor) {
            return "";
        }
        return '--bolt-primary-action-color:' . $buttonColor;
    }

    /**
     * Return true if Order Management is enabled
     * @return bool
     */
    public function isOrderManagementEnabled()
    {
        return $this->configHelper->isOrderManagementEnabled() &&
            $this->featureSwitches->isOrderManagementEnabled();
    }

    /**
     * Return true if Order Management is enabled
     * @return bool
     */
    public function getOrderManagementSelector()
    {
        if (!$this->configHelper->isOrderManagementEnabled() || !$this->featureSwitches->isOrderManagementEnabled()) {
            return '';
        }
        return $this->configHelper->getOrderManagementSelector();
    }

    /**
     * Return false if block wasn't shown yet
     * Need to provide using block only once
     *
     * @return bool
     */
    public function isBlockAlreadyShown($blockType)
    {
        if (isset(static::$blockAlreadyShown[$blockType])) {
            return true;
        }
        static::$blockAlreadyShown[$blockType] = true;
        return false;
    }

    /**
     * Return if we should use JS code that uses bolt cart from magento sections
     */
    public function isSaveCartInSections()
    {
        return $this->featureSwitches->isSaveCartInSections();
    }

    /**
     * @param $jsCode
     * @param string $argName
     * @return string
     */
    public function wrapWithCatch ($jsCode, $argName = '')
    {
        return "
function($argName) {
    try {
        $jsCode
    } catch (error) {
        console.error(error);
    }
}";
    }
}
