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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api\Data;

use Bolt\Boltpay\Api\Data\CartDataInterface;
use Bolt\Boltpay\Model\Api\Data\CartData;
use PHPUnit\Framework\TestCase;

/**
 * Class CartDataTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\Data\CartData
 */
class CartDataTest extends TestCase
{
    const DISPLAYID = '10001';
    const CURRENCY = 'CURRENCY';
    const ITEMS = [ 'itemkey' => 'itemvalue' ];
    const DISCOUNTS = [ 'discountkey' => 'discountvalue' ];
    const TOTALAMOUNT = 1000;
    const TAXAMOUNT = 10;
    const ORDERREFERENCE = 'ORDERREFERENCE';
    const SHIPMENTS = [ 'shipmentkey' => 'shipmentvalue' ];
    
    /**
     * @var CartDataInterface
     */
    private $cartData;

    public function setUp()
    {
        $this->cartData = new CartData;
        $this->cartData->setDisplayId(self::DISPLAYID);
        $this->cartData->setCurrency(self::CURRENCY);
        $this->cartData->setItems(self::ITEMS);
        $this->cartData->setDiscounts(self::DISCOUNTS);
        $this->cartData->setTotalAmount(self::TOTALAMOUNT);
        $this->cartData->setTaxAmount(self::TAXAMOUNT);
        $this->cartData->setOrderReference(self::ORDERREFERENCE);
        $this->cartData->setShipments(self::SHIPMENTS);
    }

    /**
     * @test
     * that getDisplayId would return display id.
     *
     * @covers ::getDisplayId
     */
    public function getDisplayId()
    {
        $this->assertEquals(self::DISPLAYID, $this->cartData->getDisplayId());
    }

    /**
     * @test
     * that setDisplayId would set display id and return cart data class instance
     *
     * @covers ::setDisplayId
     */
    public function setDisplayId()
    {
        $result = $this->cartData->setDisplayId(self::DISPLAYID);
        $this->assertInstanceOf(CartData::class, $result);
    }
    
    /**
     * @test
     * that getCurrency would return currency.
     *
     * @covers ::getCurrency
     */
    public function getCurrency()
    {
        $this->assertEquals(self::CURRENCY, $this->cartData->getCurrency());
    }

    /**
     * @test
     * that setCurrency would set currency and return cart data class instance
     *
     * @covers ::setCurrency
     */
    public function setCurrency()
    {
        $result = $this->cartData->setCurrency(self::CURRENCY);
        $this->assertInstanceOf(CartData::class, $result);
    }
    
    /**
     * @test
     * that getItems would return items.
     *
     * @covers ::getItems
     */
    public function getItems()
    {
        $this->assertEquals(self::ITEMS, $this->cartData->getItems());
    }

    /**
     * @test
     * that setItems would set items and return cart data class instance
     *
     * @covers ::setItems
     */
    public function setItems()
    {
        $result = $this->cartData->setItems(self::ITEMS);
        $this->assertInstanceOf(CartData::class, $result);
    }
    
    /**
     * @test
     * that getDiscounts would return discounts.
     *
     * @covers ::getDiscounts
     */
    public function getDiscounts()
    {
        $this->assertEquals(self::DISCOUNTS, $this->cartData->getDiscounts());
    }

    /**
     * @test
     * that setDiscounts would set discounts and return cart data class instance
     *
     * @covers ::setDiscounts
     */
    public function setDiscounts()
    {
        $result = $this->cartData->setDiscounts(self::DISCOUNTS);
        $this->assertInstanceOf(CartData::class, $result);
    }
    
    /**
     * @test
     * that getTotalAmount would return total amount.
     *
     * @covers ::getTotalAmount
     */
    public function getTotalAmount()
    {
        $this->assertEquals(self::TOTALAMOUNT, $this->cartData->getTotalAmount());
    }

    /**
     * @test
     * that setTotalAmount would set total amount and return cart data class instance
     *
     * @covers ::setTotalAmount
     */
    public function setTotalAmount()
    {
        $result = $this->cartData->setTotalAmount(self::TOTALAMOUNT);
        $this->assertInstanceOf(CartData::class, $result);
    }
    
    /**
     * @test
     * that getTaxAmount would return tax amount.
     *
     * @covers ::getTaxAmount
     */
    public function getTaxAmount()
    {
        $this->assertEquals(self::TAXAMOUNT, $this->cartData->getTaxAmount());
    }

    /**
     * @test
     * that setTaxAmount would set tax amount and return cart data class instance
     *
     * @covers ::setTaxAmount
     */
    public function setTaxAmount()
    {
        $result = $this->cartData->setTaxAmount(self::TAXAMOUNT);
        $this->assertInstanceOf(CartData::class, $result);
    }
    
    /**
     * @test
     * that getOrderReference would return order reference.
     *
     * @covers ::getOrderReference
     */
    public function getOrderReference()
    {
        $this->assertEquals(self::ORDERREFERENCE, $this->cartData->getOrderReference());
    }

    /**
     * @test
     * that setOrderReference would set order reference and return cart data class instance
     *
     * @covers ::setOrderReference
     */
    public function setOrderReference()
    {
        $result = $this->cartData->setOrderReference(self::ORDERREFERENCE);
        $this->assertInstanceOf(CartData::class, $result);
    }
    
    /**
     * @test
     * that getShipments would return shipments.
     *
     * @covers ::getShipments
     */
    public function getShipments()
    {
        $this->assertEquals(self::SHIPMENTS, $this->cartData->getShipments());
    }

    /**
     * @test
     * that setShipments would set shipments and return cart data class instance
     *
     * @covers ::setShipments
     */
    public function setShipments()
    {
        $result = $this->cartData->setShipments(self::SHIPMENTS);
        $this->assertInstanceOf(CartData::class, $result);
    }

    /**
     * @test
     * that getCartData would return array of cart data
     *
     * @covers ::getCartData
     */
    public function getCartData()
    {
        $result = $this->cartData->getCartData();
        $this->assertEquals([
            'display_id' => self::DISPLAYID,
            'currency' => self::CURRENCY,
            'items' => self::ITEMS,
            'discounts' => self::DISCOUNTS,
            'shipments' => self::SHIPMENTS,
            'total_amount' => self::TOTALAMOUNT,
            'tax_amount' => self::TAXAMOUNT,
            'order_reference' => self::ORDERREFERENCE,
        ], $result);
    }
}
