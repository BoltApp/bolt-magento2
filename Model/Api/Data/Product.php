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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Api\Data\ProductInterface;

/**
 * Class Product. Represents a product info object containing products info.
 *
 * @package Bolt\Boltpay\Model\Api\Data
 */
class Product extends \Magento\Catalog\Model\Product implements ProductInterface
{
    /**
     * @var float
     */
    private $finalPrice;


    /**
     * Sets final price of product
     *
     * This func is equal to magic 'setFinalPrice()', but added as a separate func, because in cart with bundle
     * products it's called very often in Item->getProduct(). So removing chain of magic with more cpu consuming
     * algorithms gives nice optimization boost.
     *
     * @param float $price Price amount
     * @return \Magento\Catalog\Model\Product
     */
    public function setFinalPrice($price)
    {
        $this->_data['final_price'] = $price;
        return $this;
    }

    /**
     * Get product final price
     *
     * @param float $qty
     * @return float
     */
    public function getFinalPrice($qty = null)
    {
        if ($this->_calculatePrice || $this->_getData('final_price') === null) {
            return $this->getPriceModel()->getFinalPrice($qty, $this);
        } else {
            return $this->_getData('final_price');
        }
    }
}
