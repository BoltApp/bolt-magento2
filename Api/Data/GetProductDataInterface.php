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
     * @return \Bolt\Boltpay\Api\Data\ProductInventoryInfoInterface;
     */
    public function getProductInventory();

    /**
     * Set product info.
     *
     * @api
     * @param \Bolt\Boltpay\Api\Data\ProductInventoryInfoInterface $product
     * @return $this
     */
    public function setProductInventory($product);

    /**
     * Get parent info.
     *
     * @api
     * @return \Bolt\Boltpay\Api\Data\ProductInventoryInfoInterface
     */
    public function getParent();

    /**
     * Set parent info.
     *
     * @api
     * @param \Bolt\Boltpay\Api\Data\ProductInventoryInfoInterface $product
     * @return $this
     */
    public function setParent($product);

    /**
     * Get product info.
     *
     * @api
     * @return \Bolt\Boltpay\Api\Data\ProductInventoryInfoInterface[]
     */
    public function getChildren();

    /**
     * Set product info.
     *
     * @api
     * @param \Bolt\Boltpay\Api\Data\ProductInventoryInfoInterface[] $children
     * @return $this
     */
    public function setChildren($children);
}
