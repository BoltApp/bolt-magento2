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

namespace Bolt\Boltpay\Test\Unit\Model\Api\Data\AutomatedTesting;

use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\OrderItem;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\Order;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\Address;

/**
 * Class OrderItemTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data\AutomatedTesting
 */
class OrderTest extends BoltTestCase
{
    const SHIPPING_METHOD = 'shipping_method';
    const SUBTOTAL = '1000';
    const TAX = '100';
    const DISCOUNT = '100';
    const GRAND_TOTAL = '1000';
    const SHIPPING_AMOUNT = '1000';

    /**
     * @var Order
     */
    protected $order;
    protected $billingAddress;
    protected $shippingAddress;

    protected $orderItem;

    protected function setUpInternal()
    {
        $this->order = new Order();
        $this->billingAddress = new Address();
        $this->shippingAddress = new Address();
        $this->orderItem = new OrderItem();
        $this->order->setShippingMethod(self::SHIPPING_METHOD);
        $this->order->setShippingAddress($this->shippingAddress);
        $this->order->setBillingAddress($this->billingAddress);
        $this->order->setShippingAmount(self::SHIPPING_AMOUNT);
        $this->order->setOrderItems([$this->orderItem]);
        $this->order->setSubtotal(self::SUBTOTAL);
        $this->order->setTax(self::TAX);
        $this->order->setDiscount(self::DISCOUNT);
        $this->order->setGrandTotal(self::GRAND_TOTAL);
    }

    /**
     * @test
     */
    public function setAndGetBillingAddress()
    {
        $this->assertEquals($this->billingAddress, $this->order->getBillingAddress());
    }

    /**
     * @test
     */
    public function setAndGetShippingAddress()
    {
        $this->assertEquals($this->shippingAddress, $this->order->getShippingAddress());
    }

    /**
     * @test
     */
    public function setAndGetShippingMethod()
    {
        $this->assertEquals(self::SHIPPING_METHOD, $this->order->getShippingMethod());
    }

    /**
     * @test
     */
    public function setAndGetShippingAmount()
    {
        $this->assertEquals(self::SHIPPING_AMOUNT, $this->order->getShippingAmount());
    }

    /**
     * @test
     */
    public function setAndGetOrderItems()
    {
        $this->assertEquals([$this->orderItem], $this->order->getOrderItems());
    }

    /**
     * @test
     */
    public function setAndGetSubtotal()
    {
        $this->assertEquals(self::SUBTOTAL, $this->order->getSubtotal());
    }

    /**
     * @test
     */
    public function setAndGetTax()
    {
        $this->assertEquals(self::TAX, $this->order->getTax());
    }

    /**
     * @test
     */
    public function setAndGetDiscount()
    {
        $this->assertEquals(self::DISCOUNT, $this->order->getDiscount());
    }

    /**
     * @test
     */
    public function setAndGetGrandTotal()
    {
        $this->assertEquals(self::GRAND_TOTAL, $this->order->getGrandTotal());
    }

    /**
     * @test
     */
    public function jsonSerialize()
    {
        $result = $this->order->jsonSerialize();
        $this->assertEquals([
            'billingAddress' => $this->billingAddress,
            'shippingAddress' => $this->shippingAddress,
            'shippingMethod' => self::SHIPPING_METHOD,
            'shippingAmount' => self::SHIPPING_AMOUNT,
            'orderItems' => [$this->orderItem],
            'subtotal' => self::SUBTOTAL,
            'tax' => self::TAX,
            'discount' => self::DISCOUNT,
            'grandTotal' => self::GRAND_TOTAL
        ], $result);
    }
}
