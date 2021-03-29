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

namespace Bolt\Boltpay\Api;

/**
 * @api
 */
interface LegacyApiInterface
{
    /**
     * @api
     *
     * Order management 
     * @param mixed $id
     * @param mixed $reference
     * @param mixed $order
     * @param mixed $type
     * @param mixed $amount
     * @param mixed $currency
     * @param mixed $status
     * @param mixed $display_id
     * @param mixed $immutable_quote_id
     * @param mixed $source_transaction_id
     * @param mixed $source_transaction_reference
     * @return void
     */

    public function manage(
        $id = null,
        $reference = null,
        $order = null,
        $type = null,
        $amount = null,
        $currency = null,
        $status = null,
        $display_id = null,
        $source_transaction_id = null,
        $source_transaction_reference = null
    );

    /**
     * @api
     * 
     * Update cart
     * @param mixed $cart
     * @param mixed $add_items
     * @param mixed $remove_items
     * @param mixed $discount_codes_to_add
     * @param mixed $discount_codes_to_remove
     * @return Bolt\Boltpay\Api\Data\UpdateCartResultInterface
     */
    public function updateCart(
        $cart,
        $add_items = null,
        $remove_items = null,
        $discount_codes_to_add = null,
        $discount_codes_to_remove = null
    );

    /**
     * @api
     * 
     * Shipping and tax hook
     * @param mixed $cart cart details
     * @param mixed $shipping_address shipping address
     * @return \Bolt\Boltpay\Api\Data\ShippingOptionsInterface
     */
    public function getShippingMethods($cart, $shipping_address);

    /**
     * @api
     * 
     * Shipping hook
     * @param mixed $cart cart details
     * @param mixed $shipping_address shipping address
     * @return \Bolt\Boltpay\Api\Data\ShippingDataInterface
     */
    public function getShippingOptions($cart, $shipping_address);

    /**
     * @api
     *
     * Tax hook
     * Get tax for a given shipping option.
     * @param mixed $cart cart details
     * @param mixed $shipping_address shipping address
     * @param mixed $shipping_option selected shipping option
     * @param mixed $ship_to_store_option selected ship to store option
     * @return \Bolt\Boltpay\Api\Data\TaxDataInterface
     */
    public function getTax($cart, $shipping_address, $shipping_option = null, $ship_to_store_option = null);

    /**
     * @api
     * 
     * Discount validation
     * @return bool
     */
    public function validateDiscount();

    /**
     * @api
     * 
     * Create order.
     * Hook formats:
     * [{"type":"order.create","order":{},"currency":"USD"}]
     *
     * @param string $type
     * @param mixed  $order - which contain token and cart nodes.
     * @param string $currency
     *
     * @return void
     */
    public function createOrder($type = null, $order = null, $currency = null);
}