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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.boltapp.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api\Data\AutomatedTesting;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\OrderItem;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class OrderItemTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data\AutomatedTesting
 */
class OrderItemTest extends BoltTestCase
{
    const PRODUCT_NAME = 'product_name';
    const PRODUCT_SKU = 'product_sku';
    const PRODUCT_URL = 'product_url';
    const PRODUCT_PRICE = '10.000';
    const PRODUCT_QUANTITY_ORDERED = 1;
    const PRODUCT_SUBTOTAL = '20.000';
    const PRODUCT_TAX_AMOUNT = '5.000';
    const PRODUCT_TAX_PERCENT = '5';
    const PRODUCT_TOTAL = '5';
    const PRODUCT_DISCOUNT_AMOUNT = '5';
    /**
     * @var OrderItem
     */
    protected $orderItem;

    protected function setUpInternal()
    {
        $this->orderItem = new OrderItem();
        $this->orderItem->setProductName(self::PRODUCT_NAME);
        $this->orderItem->setProductSku(self::PRODUCT_SKU);
        $this->orderItem->setProductUrl(self::PRODUCT_URL);
        $this->orderItem->setPrice(self::PRODUCT_PRICE);
        $this->orderItem->setQuantityOrdered(self::PRODUCT_QUANTITY_ORDERED);
        $this->orderItem->setSubtotal(self::PRODUCT_SUBTOTAL);
        $this->orderItem->setTaxAmount(self::PRODUCT_TAX_AMOUNT);
        $this->orderItem->setTaxPercent(self::PRODUCT_TAX_PERCENT);
        $this->orderItem->setTotal(self::PRODUCT_TOTAL);
        $this->orderItem->setDiscountAmount(self::PRODUCT_DISCOUNT_AMOUNT);
    }

    /**
     * @test
     */
    #[Test]
    public function setAndGetProductName()
    {
        $this->assertEquals(self::PRODUCT_NAME, $this->orderItem->getProductName());
    }

    /**
     * @test
     */
    #[Test]
    public function setAndGetProductSku()
    {
        $this->assertEquals(self::PRODUCT_SKU, $this->orderItem->getProductSku());
    }

    /**
     * @test
     */
    #[Test]
    public function setAndGetProductUrl()
    {
        $this->assertEquals(self::PRODUCT_URL, $this->orderItem->getProductUrl());
    }

    /**
     * @test
     */
    #[Test]
    public function setAndGetPrice()
    {
        $this->assertEquals(self::PRODUCT_PRICE, $this->orderItem->getPrice());
    }

    /**
     * @test
     */
    #[Test]
    public function setAndGetQuantityOrdered()
    {
        $this->assertEquals(self::PRODUCT_QUANTITY_ORDERED, $this->orderItem->getQuantityOrdered());
    }

    /**
     * @test
     */
    #[Test]
    public function setAndGetSubtotal()
    {
        $this->assertEquals(self::PRODUCT_SUBTOTAL, $this->orderItem->getSubtotal());
    }

    /**
     * @test
     */
    #[Test]
    public function setAndGetTaxAmount()
    {
        $this->assertEquals(self::PRODUCT_TAX_AMOUNT, $this->orderItem->getTaxAmount());
    }

    /**
     * @test
     */
    #[Test]
    public function setAndGetTaxPercent()
    {
        $this->assertEquals(self::PRODUCT_TAX_PERCENT, $this->orderItem->getTaxPercent());
    }

    /**
     * @test
     */
    #[Test]
    public function setAndGetTotal()
    {
        $this->assertEquals(self::PRODUCT_TOTAL, $this->orderItem->getTotal());
    }

    /**
     * @test
     */
    #[Test]
    public function jsonSerialize()
    {
        $this->orderItem->setProductName(self::PRODUCT_NAME);
        $this->orderItem->setProductSku(self::PRODUCT_SKU);
        $this->orderItem->setProductUrl(self::PRODUCT_URL);
        $this->orderItem->setPrice(self::PRODUCT_PRICE);
        $this->orderItem->setQuantityOrdered(self::PRODUCT_QUANTITY_ORDERED);
        $this->orderItem->setSubtotal(self::PRODUCT_SUBTOTAL);
        $this->orderItem->setTaxAmount(self::PRODUCT_TAX_AMOUNT);
        $this->orderItem->setTaxPercent(self::PRODUCT_TAX_PERCENT);
        $this->orderItem->setTotal(self::PRODUCT_TOTAL);
        $result = $this->orderItem->jsonSerialize();
        $this->assertEquals([
            'productName' => self::PRODUCT_NAME,
            'productSku' => self::PRODUCT_SKU,
            'productUrl' => self::PRODUCT_URL,
            'price' => self::PRODUCT_PRICE,
            'quantityOrdered' => self::PRODUCT_QUANTITY_ORDERED,
            'subtotal' => self::PRODUCT_SUBTOTAL,
            'taxAmount' => self::PRODUCT_TAX_AMOUNT,
            'taxPercent' => self::PRODUCT_TAX_PERCENT,
            'total' => self::PRODUCT_TOTAL,
            'discountAmount' => self::PRODUCT_DISCOUNT_AMOUNT,
        ], $result);
    }
}
