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

namespace Bolt\Boltpay\Block;

use Bolt\Boltpay\Helper\Config;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Js Block. The block class used in replace.phtml and track.phtml blocks.
 *
 * @SuppressWarnings(PHPMD.DepthOfInheritance)
 */
class Js extends Template
{
    /**
     * @var Config
     */
    private $configHelper;

    /** @var CheckoutSession */
    private $checkoutSession;


    /**
     * @param Context $context
     * @param Config $configHelper
     * @param CheckoutSession $checkoutSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config $configHelper,
        CheckoutSession $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configHelper = $configHelper;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Get track js url
     *
     * @return  string
     */
    public function getTrackJsUrl()
    {
        //Get cdn url
        $cdnUrl = $this->configHelper->getCdnUrl();
        return $cdnUrl.'/track.js';
    }

    /**
     * Get connect js url
     *
     * @return  string
     */
    public function getConnectJsUrl()
    {
        //Get cdn url
        $cdnUrl = $this->configHelper->getCdnUrl();
        return $cdnUrl.'/connect.js';
    }

    /**
     * Get checkout key. Any of the defined publishable keys for use with track.js.
     *
     * @return  string
     */
    public function getCheckoutKey()
    {
        return $this->configHelper->getAnyPublishableKey();
    }

    /**
     * Get Replace Button Selectors.
     *
     * @return string
     */
    public function getReplaceSelectors()
    {
        return array_filter(explode(',', preg_replace('/\s+/', ' ', trim($this->configHelper->getReplaceSelectors()))));
    }

    /**
     * Get Totals Change Selectors.
     *
     * @return string
     */
    public function getTotalsChangeSelectors()
    {
        return array_filter(explode(',', preg_replace('/\s+/', ' ', trim($this->configHelper->getTotalsChangeSelectors()))));
    }

    /**
     * Get Replace Button Selectors.
     *
     * @return string
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
     * Get Javascript page settings.
     * @return string
     */
    public function getSettings()
    {
        return json_encode([
            'connect_url'              => $this->getConnectJsUrl(),
            'publishable_key_payment'  => $this->configHelper->getPublishableKeyPayment(),
            'publishable_key_checkout' => $this->configHelper->getPublishableKeyCheckout(),
            'publishable_key_back_office' => $this->configHelper->getPublishableKeyBackOffice(),
            'create_order_url'         => $this->getUrl(Config::CREATE_ORDER_ACTION),
            'save_order_url'           => $this->getUrl(Config::SAVE_ORDER_ACTION),
            'selectors'                => $this->getReplaceSelectors(),
            'shipping_prefetch_url'    => $this->getUrl(Config::SHIPPING_PREFETCH_ACTION),
            'prefetch_shipping'        => $this->configHelper->getPrefetchShipping(),
            'save_email_url'           => $this->getUrl(Config::SAVE_EMAIL_ACTION),
            'quote_is_virtual'         => $this->getQuoteIsVirtual(),
            'totals_change_selectors'  => $this->getTotalsChangeSelectors(),
        ]);
    }

    /**
     * Get Bolt Payment module active state.
     * @return bool
     */
    public function isEnabled()
    {
        return $this->configHelper->isActive();
    }

    /**
     * Get quote is virtual flag, false if no existing quote
     * @return bool
     */
    private function getQuoteIsVirtual() {
        $quote = $this->checkoutSession->getQuote();
        return $quote ? $quote->isVirtual() : false;
    }

    /**
     * @return string
     */
    public function getBoltPopupErrorMessage()
    {
        return __('Error') . ': ' . __('Your payment was successful and we\'re now processing your order.' .
        'If you don\'t receive order confirmation email in next 30 minutes, please contact us at support@bolt.com)');
    }
}
