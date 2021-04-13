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

namespace Bolt\Boltpay\Api\Data;

/**
 * Get Product interface.
 *
 * @api
 */
interface GetProductDataInterface
{

    /**
     * Get product info.
     *
     * @api
     * @return \Magento\Catalog\Api\Data\ProductInterface
     */
    public function getProduct();

    /**
     * Set product info.
     *
     * @api
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return $this
     */
    public function setProduct($product);

    /**
     * Get product info.
     *
     * @api
     * @return \Magento\Catalog\Api\Data\ProductInterface[]
     */
    public function getChildren();

    /**
     * Set product info.
     *
     * @api
     * @param \Magento\Catalog\Api\Data\ProductInterface[] $children
     * @return $this
     */
    public function setChildren($children);

    /**
     * Get stock info.
     *
     * @api
     * @return \Magento\CatalogInventory\Api\Data\StockItemInterface
     */
    public function getStock();

    /**
     * Get stock info.
     *
     * @api
     * @param \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem
     * @return $this
     */
    public function setStock($stockItem);
}
