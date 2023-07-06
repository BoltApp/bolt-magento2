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
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\Config;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\Cart;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\Order;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\StoreItem;

/**
 * Class CartItemTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data\AutomatedTesting
 */
class ConfigTest extends BoltTestCase
{
    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var StoreItem
     */
    protected $storeItem;

    /**
     * @var Order
     */
    protected $order;

    /**
     * @var Config
     */
    protected $config;

    protected function setUpInternal()
    {
        $this->config = new Config();

        $this->cart = new Cart();
        $this->cart->setExpectedShippingMethods(['bolt_shipping']);
        $this->cart->setTax('4.00');
        $this->cart->setSubTotal('100');
        $this->config->setCart($this->cart);

        $this->storeItem = new StoreItem();
        $this->storeItem->setName('name');
        $this->storeItem->setPrice(100);
        $this->config->setStoreItems([$this->storeItem]);

        $this->order = new Order();
        $this->config->setPastOrder($this->order);
    }

    /**
     * @test
     */
    public function setAndGetStoreItems()
    {
        $this->assertEquals([$this->storeItem], $this->config->getStoreItems());
    }

    /**
     * @test
     */
    public function setAndGetCart()
    {
        $this->assertEquals($this->cart, $this->config->getCart());
    }

    /**
     * @test
     */
    public function setAndGetOrder()
    {
        $this->assertEquals($this->order, $this->config->getPastOrder());
    }

    /**
     * @test
     */
    public function jsonSerialize()
    {
        $result = $this->config->jsonSerialize();
        $this->assertEquals([
            'storeItems' => [$this->storeItem],
            'cart' => $this->cart,
            'pastOrder' => $this->order
        ], $result);
    }
}
