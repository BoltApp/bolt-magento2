<?php
/**
 * Copyright © 2013-2017 Bold, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */


namespace Bolt\Boltpay\Api;

/**
 * Shipping methods interface. Shipping and Tax endpoint.
 *
 * Get shipping methods using shipping address and cart details.
 * @api
 */
interface ShippingMethodsInterface
{
    /**
     * Get all available shipping methods.
     *
     * @api
     * @param mixed $cart cart details
     * @param mixed $shipping_address shipping address
     * @return \Bolt\Boltpay\Api\Data\ShippingOptionsInterface
     */
    public function getShippingMethods($cart, $shipping_address);
}
