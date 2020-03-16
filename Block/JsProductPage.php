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
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Magento\Checkout\Helper\Data;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Catalog\Block\Product\View as ProductView;
use Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;

/**
 * Js Block. The block class used in replace.phtml and track.phtml blocks.
 *
 * @SuppressWarnings(PHPMD.DepthOfInheritance)
 */
class JsProductPage extends Js
{

    /**
     * @var \Magento\Catalog\Model\Product
     */
    private $_product;

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    public function __construct(
        Context $context,
        Config $configHelper,
        CheckoutSession $checkoutSession,
        CartHelper $cartHelper,
        Bugsnag $bugsnag,
        ProductView $productView,
        Decider $featureSwitches,
        ScopeConfig $scopeConfig,
        array $data = []
    ) {
        $this->_product     = $productView->getProduct();
        $this->_scopeConfig = $scopeConfig;

        parent::__construct($context, $configHelper, $checkoutSession, $cartHelper, $bugsnag, $featureSwitches, $data);
    }

    /**
     * Get product
     *
     * @return  \Magento\Catalog\Model\Product
     */
    public function getProduct()
    {
        return $this->_product;
    }

    /**
     * Check if we support product page checkout for type of current product
     *
     * @return boolean
     */
    public function isSupportableType()
    {
        if (in_array($this->_product->getTypeId(), Config::$supportableProductTypesForProductPageCheckout)) {
            return true;
        }

        return false;
    }

    /**
     * Check if current product has type configurable
     *
     * @return boolean
     */
    public function isConfigurable()
    {
        return $this->_product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE;
    }

    /**
     * Check if guest checkout is allowed
     *
     * @return int 1 if guest checkout is allowed, 0 if not
     */
    public function isGuestCheckoutAllowed()
    {
        return (int)$this->configHelper->isGuestCheckoutAllowed();
    }

    public function getStoreCurrencyCode()
    {
        return $this->_storeManager->getStore()->getCurrentCurrencyCode();
    }

    /**
     * Get the Order Minimum Amount
     *
     * @return boolean|float return minimum amount if `Order Minimum Amount` is enabled, otherwise false.
     */
    public function getOrderMinimumAmount()
    {
        $storeId        = $this->getStoreId();
        $minOrderActive = $this->_scopeConfig->isSetFlag(
            'sales/minimum_order/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ( ! $minOrderActive) {
            return false;
        }

        $minAmount = $this->_scopeConfig->getValue(
            'sales/minimum_order/amount',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $minAmount;
    }
}