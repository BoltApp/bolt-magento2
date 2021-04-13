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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Api\Data\GetProductDataInterface;

/**
 * Class GetProductData. Represents a product info object containing products info and stock.
 *
 * @package Bolt\Boltpay\Model\Api\Data
 */
class GetProductData implements GetProductDataInterface, \JsonSerializable
{
    /**
     * @var \Magento\Catalog\Api\Data\ProductInterface
     */
    private $product;

    /**
     * @var \Magento\CatalogInventory\Api\Data\StockItemInterface
     */
    private $stockItem;

    /**
     * @var \Magento\Catalog\Api\Data\ProductInterface[]
     */
    private $children;


    /**
     * Get product info.
     *
     * @api
     * @return \Magento\Catalog\Api\Data\ProductInterface
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * Set product info.
     *
     * @api
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     *
     * @return $this
     */
    public function setProduct($product)
    {
        $this->product = $product;
        return $this;
    }

    /**
     * Get stock info.
     *
     * @api
     * @return \Magento\CatalogInventory\Api\Data\StockItemInterface
     */
    public function getStock()
    {
        return $this->stockItem;
    }

    /**
     * Get stock info.
     *
     * @api
     * @param \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem
     * @return $this
     */
    public function setStock($stockItem)
    {
        $this->stockItem = $stockItem;
        return $this;
    }


    /**
     * Get children info.
     *
     * @api
     * @return \Magento\Catalog\Api\Data\ProductInterface[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Set children info.
     *
     * @api
     * @param \Magento\Catalog\Api\Data\ProductInterface[] $children
     *
     * @return $this
     */
    public function setChildren($children)
    {
        $this->children = $children;
        return $this;
    }


    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'product' => $this->product,
            'children' => $this->children,
            'stock' => $this->stockItem
        ];
    }
}
