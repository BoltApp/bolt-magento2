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

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\CreateOrderInterface;
use Bolt\Boltpay\Api\DiscountCodeValidationInterface;
use Bolt\Boltpay\Api\LegacyApiInterface;
use Bolt\Boltpay\Api\OrderManagementInterface;
use Bolt\Boltpay\Api\ShippingInterface;
use Bolt\Boltpay\Api\ShippingMethodsInterface;
use Bolt\Boltpay\Api\TaxInterface;
use Bolt\Boltpay\Api\UpdateCartInterface;

class LegacyApi implements LegacyApiInterface
{
    /**
     * @var CreateOrderInterface
     */
    protected $createOrder;

    /**
     * @var DiscountCodeValidationInterface
     */
    protected $discountCodeValidation;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @var ShippingInterface
     */
    protected $shipping;
    
    /**
     * @var ShippingMethodsInterface
     */
    protected $shippingMethods;

    /**
     * @var TaxInterface
     */
    protected $tax;

    /**
     * @var UpdateCartInterface
     */
    protected $updateCart;

    /**
     * @var CreateOrderInterface $createOrder
     * @var DiscountCodeValidationInterface $discountCodeValidation
     * @var OrderManagementInterface $orderManagement
     * @var ShippingInterface $shipping
     * @var ShippingMethodsInterface $shippingMethods
     * @var TaxInterface $tax
     * @var UpdateCartInterface $updateCart
     */
    public function __construct(
        CreateOrderInterface $createOrder,
        DiscountCodeValidationInterface $discountCodeValidation,
        OrderManagementInterface $orderManagement,
        ShippingInterface $shipping,
        ShippingMethodsInterface $shippingMethods,
        TaxInterface $tax,
        UpdateCartInterface $updateCart
    ) {
        $this->createOrder = $createOrder;
        $this->discountCodeValidation = $discountCodeValidation;
        $this->orderManagement = $orderManagement;
        $this->shipping = $shipping;
        $this->shippingMethods = $shippingMethods;
        $this->tax = $tax;
        $this->updateCart = $updateCart;
    }

    /**
     * @api
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
        $order = null, // <parent quote ID>
        $type = null,
        $amount = null,
        $currency = null,
        $status = null,
        $display_id = null, // <order increment ID>
        $source_transaction_id = null,
        $source_transaction_reference = null
    ) {
        return $this->orderManagement->manage(
            $id,
            $reference,
            $order,
            $type,
            $amount,
            $currency,
            $status,
            $display_id,
            $source_transaction_id,
            $source_transaction_reference
        );
    }

    /**
     * @api
     * @param mixed $cart
     * @param mixed $add_items
     * @param mixed $remove_items
     * @param mixed $discount_codes_to_add
     * @param mixed $discount_codes_to_remove
     * @return void
     */
    public function updateCart(
        $cart,
        $add_items = null,
        $remove_items = null,
        $discount_codes_to_add = null,
        $discount_codes_to_remove = null
    ) {
        return $this->updateCart->execute(
            $cart,
            $add_items,
            $remove_items,
            $discount_codes_to_add,
            $discount_codes_to_remove
        );
    }

    /**
     * @api
     * @param mixed $cart cart details
     * @param mixed $shipping_address shipping address
     * @return \Bolt\Boltpay\Api\Data\ShippingOptionsInterface
     */
    public function getShippingMethods($cart, $shipping_address)
    {
        return $this->shippingMethods->getShippingMethods(
            $cart,
            $shipping_address
        );
    }

    /**
     * @api
     *
     * Shipping hook
     * @param mixed $cart cart details
     * @param mixed $shipping_address shipping address
     * @param mixed $cart_shipment_type selected shipping option
     * @return \Bolt\Boltpay\Api\Data\ShippingDataInterface
     */
    public function getShippingOptions($cart, $shipping_address, $cart_shipment_type = null)
    {
        return $this->shipping->execute(
            $cart,
            $shipping_address,
            $cart_shipment_type
        );
    }

    /**
     * @api
     *
     * Tax hook
     * Get tax for a given shipping option.
     * @param mixed $cart cart details
     * @param mixed $shipping_address shipping address
     * @param mixed $cart_shipment_type selected shipping option
     * @param mixed $shipping_option selected shipping option
     * @param mixed $ship_to_store_option selected ship to store option
     * @return \Bolt\Boltpay\Api\Data\TaxDataInterface
     */
    public function getTax($cart, $shipping_address, $cart_shipment_type = null, $shipping_option = null, $ship_to_store_option = null)
    {
        return $this->tax->execute(
            $cart,
            $shipping_address,
            $cart_shipment_type,
            $shipping_option,
            $ship_to_store_option
        );
    }

    /**
     * @api
     *
     * Discount validation
     * @return bool
     */
    public function validateDiscount()
    {
        return $this->discountCodeValidation->validate();
    }

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
    public function createOrder($type = null, $order = null, $currency = null)
    {
        return $this->createOrder->execute(
            $type,
            $order,
            $currency
        );
    }
}
