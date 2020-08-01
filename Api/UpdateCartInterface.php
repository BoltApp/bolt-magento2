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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Api;

/**
 * @api
 */
interface UpdateCartInterface
{
    /**
     * Update cart with items and discounts.
     *
     * @api
     * @param mixed $cart
     * @param mixed $add_items
     * @param mixed $remove_items
     * @param mixed $discount_codes_to_add
     * @param mixed $discount_codes_to_remove
     * @return \Bolt\Boltpay\Api\Data\UpdateCartResultInterface
     */
    public function execute($cart, $add_items = null, $remove_items = null, $discount_codes_to_add = null, $discount_codes_to_remove = null);
}
