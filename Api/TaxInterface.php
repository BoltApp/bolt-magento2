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
 * Tax interface. Tax endpoint.
 *
 * Get tax data using shipping address, cart details and selected shipping option.
 * @api
 */
interface TaxInterface
{
    /**
     * Get tax for a given shipping option.
     *
     * @api
     * @param mixed $cart cart details
     * @param mixed $shipping_address shipping address
     * @param mixed $shipping_option selected shipping option
     * @return \Bolt\Boltpay\Api\Data\TaxDataInterface
     */
    public function execute($cart, $shipping_address, $shipping_option = null);
}
