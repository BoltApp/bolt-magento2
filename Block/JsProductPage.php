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
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Magento\Checkout\Helper\Data;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Catalog\Block\Product\View as ProductView;

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

    public function __construct(
        Context $context,
        Config $configHelper,
        CheckoutSession $checkoutSession,
        CartHelper $cartHelper,
        Bugsnag $bugsnag,
        ProductView $productView,
        Decider $featureSwitches,
        array $data = []
    ) {
        $this->_product = $productView->getProduct();

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

    public function isSaveHintsInSections()
    {
        return $this->featureSwitches->isSaveHintsInSections();
    }
}
