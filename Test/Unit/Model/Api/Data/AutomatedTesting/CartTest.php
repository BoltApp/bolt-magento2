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

namespace Bolt\Boltpay\Test\Unit\Model\Api\Data\AutomatedTesting;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\Cart;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\PriceProperty;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\CartItem;

/**
 * Class CartItemTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data\AutomatedTesting
 */
class CartTest extends BoltTestCase
{
    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var PriceProperty
     */
    protected $priceProperty;

    /**
     * @var CartItem
     */
    protected $cartItem;

    protected function setUpInternal()
    {
        $this->cart = new Cart();
        $this->cart->setExpectedShippingMethods(['bolt_shipping']);
        $this->cart->setTax('4.00');
        $this->cart->setSubTotal('100');

        $this->priceProperty = new PriceProperty();
        $this->priceProperty->setName('name');
        $this->priceProperty->setPrice('10.000');
        $this->cart->setShipping($this->priceProperty);

        $this->cartItem = new CartItem();
        $this->cartItem->setName('name');
        $this->cartItem->setPrice('10.000');
        $this->cartItem->setQuantity(1);
        $this->cart->setItems([$this->cartItem]);
    }

    /**
     * @test
     */
    public function setAndGetExpectedShippingMethods()
    {
        $this->assertEquals(['bolt_shipping'], $this->cart->getExpectedShippingMethods());
    }

    /**
     * @test
     */
    public function setAndGetTax()
    {
        $this->assertEquals('4.00', $this->cart->getTax());
    }

    /**
     * @test
     */
    public function setAndGetShipping()
    {
        $this->assertEquals($this->priceProperty, $this->cart->getShipping());
    }

    /**
     * @test
     */
    public function setAndGetItems()
    {
        $this->assertEquals([$this->cartItem], $this->cart->getItems());
    }

    /**
     * @test
     */
    public function setAndGetSubTotal()
    {
        $this->assertEquals('100', $this->cart->getSubTotal());
    }

    /**
     * @test
     */
    public function jsonSerialize()
    {
        $result = $this->cart->jsonSerialize();
        $this->assertEquals([
            'items'                   => [$this->cartItem],
            'shipping'                => $this->priceProperty,
            'expectedShippingMethods' => ['bolt_shipping'],
            'tax'                     => '4.00',
            'subTotal'                => '100'
        ], $result);
    }
}
