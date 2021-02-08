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

namespace Bolt\Boltpay\Model\Api\Data\AutomatedTesting;

class Cart implements \JsonSerializable
{
    /**
     * @var CartItem[]
     */
    private $items;

    /**
     * @var PriceProperty
     */
    private $shipping;

    /**
     * @var PriceProperty[]
     */
    private $expectedShippingMethods;

    /**
     * @var PriceProperty
     */
    private $tax;

    /**
     * @var string
     */
    private $subTotal;

    /**
     * @return CartItem[]
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @param CartItem[] $items
     *
     * @return $this
     */
    public function setItems($items)
    {
        $this->items = $items;
        return $this;
    }

    /**
     * @return PriceProperty
     */
    public function getShipping()
    {
        return $this->shipping;
    }

    /**
     * @param PriceProperty $shipping
     *
     * @return $this
     */
    public function setShipping($shipping)
    {
        $this->shipping = $shipping;
        return $this;
    }

    /**
     * @return PriceProperty[]
     */
    public function getExpectedShippingMethods()
    {
        return $this->expectedShippingMethods;
    }

    /**
     * @param PriceProperty[] $expectedShippingMethods
     *
     * @return $this
     */
    public function setExpectedShippingMethods($expectedShippingMethods)
    {
        $this->expectedShippingMethods = $expectedShippingMethods;
        return $this;
    }

    /**
     * @return PriceProperty
     */
    public function getTax()
    {
        return $this->tax;
    }

    /**
     * @param PriceProperty $tax
     *
     * @return $this
     */
    public function setTax($tax)
    {
        $this->tax = $tax;
        return $this;
    }

    /**
     * @return string
     */
    public function getSubTotal()
    {
        return $this->subTotal;
    }

    /**
     * @param string $subTotal
     *
     * @return $this
     */
    public function setSubTotal($subTotal)
    {
        $this->subTotal = $subTotal;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'items'                   => $this->items,
            'shipping'                => $this->shipping,
            'expectedShippingMethods' => $this->expectedShippingMethods,
            'tax'                     => $this->tax,
            'subTotal'                => $this->subTotal
        ];
    }
}
