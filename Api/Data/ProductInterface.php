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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Api\Data;

/**
 * Custom Product interface.
 *
 * @api
 */
interface ProductInterface extends \Magento\Catalog\Api\Data\ProductInterface
{
    /**
     * Get product final price
     *
     * @return float|null
     */
    public function getFinalPrice();

    /**
     * Set product final price
     *
     * @param float $price
     * @return $this
     */
    public function setFinalPrice($price);
}
