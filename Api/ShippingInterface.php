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

namespace Bolt\Boltpay\Api;

/**
 * Shipping methods interface. Shipping endpoint.
 *
 * Get shipping methods using shipping address and cart details.
 * @api
 */
interface ShippingInterface
{
    /**
     * Get all available shipping methods.
     *
     * @api
     * @param mixed $cart cart details
     * @param mixed $shipping_address shipping address
     * @return \Bolt\Boltpay\Api\Data\ShippingDataInterface
     */
    public function execute($cart, $shipping_address);
}
