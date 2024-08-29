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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api\Data\AutomatedTesting;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\CartItem;

/**
 * Class CartItemTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data\AutomatedTesting
 */
class CartItemTest extends BoltTestCase
{
    /**
     * @var CartItem
     */
    protected $cartItem;

    protected function setUpInternal()
    {
        $this->cartItem = new CartItem();
        $this->cartItem->setName('name');
        $this->cartItem->setPrice('10.000');
        $this->cartItem->setQuantity(1);
    }

    /**
     * @test
     */
    public function setAndGetName()
    {
        $this->assertEquals('name', $this->cartItem->getName());
    }

    /**
     * @test
     */
    public function setAndGetPrice()
    {
        $this->assertEquals('10.000', $this->cartItem->getPrice());
    }

    /**
     * @test
     */
    public function setAndGetQuantity()
    {
        $this->assertEquals(1, $this->cartItem->getQuantity());
    }

    /**
     * @test
     */
    public function jsonSerialize()
    {
        $result = $this->cartItem->jsonSerialize();
        $this->assertEquals([
            'name' => 'name',
            'price' => '10.000',
            'quantity' => 1,
        ], $result);
    }
}
