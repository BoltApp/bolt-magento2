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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api\Data\AutomatedTesting;

class Order implements \JsonSerializable
{
    /**
     * @var Address
     */
    private $billingAddress;

    /**
     * @var Address
     */
    private $shippingAddress;

    /**
     * @var string
     */
    private $shippingMethod;

    /**
     * @var string
     */
    private $shippingAmount;

    /**
     * @var OrderItem[]
     */
    private $orderItems;

    /**
     * @var string
     */
    private $subtotal;

    /**
     * @var string
     */
    private $tax;

    /**
     * @var string
     */
    private $discount;

    /**
     * @var string
     */
    private $grandTotal;

    /**
     * @return Address
     */
    public function getBillingAddress()
    {
        return $this->billingAddress;
    }

    /**
     * @return Address
     */
    public function getShippingAddress()
    {
        return $this->shippingAddress;
    }

    /**
     * @return string
     */
    public function getShippingMethod() {
        return $this->shippingMethod;
    }

    /**
     * @return string
     */
    public function getShippingAmount()
    {
        return $this->shippingAmount;
    }

    /**
     * @return OrderItem[]
     */
    public function getOrderItems()
    {
        return $this->orderItems;
    }

    /**
     * @return string
     */
    public function getSubtotal()
    {
        return $this->subtotal;
    }

    /**
     * @return string
     */
    public function getTax()
    {
        return $this->tax;
    }

    /**
     * @return string
     */
    public function getDiscount()
    {
        return $this->discount;
    }

    /**
     * @return string
     */
    public function getGrandTotal()
    {
        return $this->grandTotal;
    }

    /**
     * @param Address $billingAddress
     * @return $this
     */
    public function setBillingAddress($billingAddress) {
        $this->billingAddress = $billingAddress;
        return $this;
    }

    /**
     * @param Address $shippingAddress
     * @return $this
     */
    public function setShippingAddress($shippingAddress) {
        $this->shippingAddress = $shippingAddress;
        return $this;
    }

    /**
     * @param string $shippingMethod
     * @return mixed
     */
    public function setShippingMethod($shippingMethod)
    {
        $this->shippingMethod = $shippingMethod;
        return $this;
    }

    /**
     * @param string $shippingAmount
     * @return $this
     */
    public function setShippingAmount($shippingAmount)
    {
        $this->shippingAmount = $shippingAmount;
        return $this;
    }

    /**
     * @param OrderItem[] $orderItems
     * @return $this
     */
    public function setOrderItems($orderItems)
    {
        $this->orderItems = $orderItems;
        return $this;
    }

    /**
     * @param string $subtotal
     * @return $this
     */
    public function setSubtotal($subtotal)
    {
        $this->subtotal = $subtotal;
        return $this;
    }

    /**
     * @param string $tax
     * @return $this
     */
    public function setTax($tax)
    {
        $this->tax = $tax;
        return $this;
    }

    /**
     * @param string $discount
     * @return $this
     */
    public function setDiscount($discount)
    {
        $this->discount = $discount;
        return $this;
    }

    /**
     * @param string $grandTotal
     * @return $this
     */
    public function setGrandTotal($grandTotal)
    {
        $this->grandTotal = $grandTotal;
        return $this;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'billingAddress' => $this->billingAddress,
            'shippingAddress' => $this->shippingAddress,
            'shippingMethod' => $this->shippingMethod,
            'shippingAmount' => $this->shippingAmount,
            'orderItems' => $this->orderItems,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'discount' => $this->discount,
            'grandTotal' => $this->grandTotal
        ];
    }
}