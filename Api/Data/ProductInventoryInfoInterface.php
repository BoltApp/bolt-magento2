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
interface ProductInventoryInfoInterface
{

    /**
     * Get product info.
     *
     * @api
     * @return Bolt\Boltpay\Api\Data\ProductInterface
     */
    public function getProduct();

    /**
     * Set product info.
     *
     * @api
     * @param Bolt\Boltpay\Api\Data\ProductInterface $product
     * @return $this
     */
    public function setProduct($product);

    /**
     * Get stock info.
     *
     * @api
     * @return \Magento\CatalogInventory\Api\Data\StockStatusInterface
     */
    public function getStock();

    /**
     * Get stock info.
     *
     * @api
     * @param \Magento\CatalogInventory\Api\Data\StockStatusInterface $stockStatus
     * @return $this
     */
    public function setStock($stockStatus);
}
