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
use Bolt\Boltpay\Helper\Cart as CartHelper;



/**
 * Js Block. The block class used in replace.phtml and track.phtml blocks.
 *
 * @SuppressWarnings(PHPMD.DepthOfInheritance)
 */
class JsProductPage extends Js {

    /**
     * @var \Magento\Catalog\Model\Product
     */
    private $_product;

    public function __construct(
        Context $context,
        Config $configHelper,
        CheckoutSession $checkoutSession,
        CartHelper $cartHelper,
        array $data = [],
        \Magento\Catalog\Block\Product\View $view
    ) {
        $this->_product = $view->getProduct();

        parent::__construct($context,$configHelper,$checkoutSession,$cartHelper);
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

    public function getProductQty()
    {
        $stockItem = $this->getProduct()->getExtensionAttributes()->getStockItem();
        return $stockItem->getQty();
    }

    /**
     * Check if we support product page checkout for type of current product
     *
     * @return boolean
     */
    public function IsSupportableType()
    {
        if (in_array($this->_product->getTypeId(), Config::$supportableProductTypes)) {
            return true;
        }
        return false;
    }
}