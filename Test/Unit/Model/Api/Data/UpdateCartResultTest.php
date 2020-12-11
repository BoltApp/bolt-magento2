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

use Bolt\Boltpay\Api\Data\UpdateCartResultInterface;
use Bolt\Boltpay\Model\Api\Data\CartData;
use Bolt\Boltpay\Model\Api\Data\UpdateCartResult;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Reflection\TypeProcessor;
use Zend\Code\Reflection\ClassReflection;

/**
 * Class CartDataTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\Data\UpdateCartResult
 */
class UpdateCartResultTest extends TestCase
{    
    const STATUS = 'STATUS';
    const ORDERREFERENCE = 'ORDERREFERENCE';
    
    const DISPLAYID = '10001';
    const CURRENCY = 'CURRENCY';
    const ITEMS = [ 'itemkey' => 'itemvalue' ];
    const DISCOUNTS = [ 'discountkey' => 'discountvalue' ];
    const TOTALAMOUNT = 1000;
    const TAXAMOUNT = 10;
    const SHIPMENTS = [ 'shipmentkey' => 'shipmentvalue' ];
    
    /**
     * @var \Bolt\Boltpay\Api\Data\CartDataInterface
     */
    private $orderCreate;
    
    /**
     * @var UpdateCartResultInterface
     */
    private $updateCartResult;

    /**
     * @var TypeProcessor
     */
    private $typeProcessor;

    public function setUp(): void
    {
        $this->updateCartResult = new UpdateCartResult;
        $this->orderCreate = new CartData;
        
        $this->orderCreate->setDisplayId(self::DISPLAYID);
        $this->orderCreate->setCurrency(self::CURRENCY);
        $this->orderCreate->setItems(self::ITEMS);
        $this->orderCreate->setDiscounts(self::DISCOUNTS);
        $this->orderCreate->setTotalAmount(self::TOTALAMOUNT);
        $this->orderCreate->setTaxAmount(self::TAXAMOUNT);
        $this->orderCreate->setOrderReference(self::ORDERREFERENCE);
        $this->orderCreate->setShipments(self::SHIPMENTS);
        
        $this->updateCartResult->setOrderCreate($this->orderCreate);
        $this->updateCartResult->setStatus(self::STATUS);
        $this->updateCartResult->setOrderReference(self::ORDERREFERENCE);
        $this->typeProcessor = new TypeProcessor();
    }

    /**
     * @test
     * that getOrderCreate would return cart data.
     *
     * @covers ::getOrderCreate
     */
    public function getOrderCreate()
    {
        $this->assertEquals($this->orderCreate, $this->updateCartResult->getOrderCreate());
    }

    /**
     * @test
     * that setOrderCreate would set cart data and return UpdateCartResult class instance
     *
     * @covers ::setOrderCreate
     */
    public function setOrderCreate()
    {
        $result = $this->updateCartResult->setOrderCreate($this->orderCreate);
        $this->assertInstanceOf(UpdateCartResult::class, $result);
    }
    
    /**
     * @test
     * that getStatus would return status.
     *
     * @covers ::getStatus
     */
    public function getStatus(): int
    {
        $this->assertEquals(self::STATUS, $this->updateCartResult->getStatus());
    }

    /**
     * @test
     * that setStatus would set status and return UpdateCartResult class instance
     *
     * @covers ::setStatus
     */
    public function setStatus()
    {
        $result = $this->updateCartResult->setStatus(self::STATUS);
        $this->assertInstanceOf(UpdateCartResult::class, $result);
    }
    
    /**
     * @test
     * that getOrderReference would return order reference.
     *
     * @covers ::getOrderReference
     */
    public function getOrderReference()
    {
        $this->assertEquals(self::ORDERREFERENCE, $this->updateCartResult->getOrderReference());
    }

    /**
     * @test
     * that setOrderReference would set order reference and return UpdateCartResult class instance
     *
     * @covers ::setOrderReference
     */
    public function setOrderReference()
    {
        $result = $this->updateCartResult->setOrderReference(self::ORDERREFERENCE);
        $this->assertInstanceOf(UpdateCartResult::class, $result);
    }

    /**
     * @test
     * that getCartResult would return array of result
     *
     * @covers ::getCartResult
     */
    public function getCartResult()
    {
        $result = $this->updateCartResult->getCartResult();
        $this->assertEquals([
            'status' => self::STATUS,
            'order_reference' => self::ORDERREFERENCE,
            'order_create' => [
                'cart' => [
                    'display_id' => self::DISPLAYID,
                    'currency' => self::CURRENCY,
                    'items' => self::ITEMS,
                    'discounts' => self::DISCOUNTS,
                    'shipments' => self::SHIPMENTS,
                    'total_amount' => self::TOTALAMOUNT,
                    'tax_amount' => self::TAXAMOUNT,
                    'order_reference' => self::ORDERREFERENCE,
                ]
            ]
        ], $result);
    }

    /**
     * @test
     * to ensure the return type annotation of method {getCartResult}
     * in class Bolt\Boltpay\Api\Data\UpdateCartResultInterface is correct
     */
    public function processTypeName(){
        $classReflection = new ClassReflection(UpdateCartResultInterface::class);
        $methodReflection = $classReflection->getMethod('getCartResult');

        self::assertEquals('mixed[]', $this->typeProcessor->register($this->typeProcessor->getGetterReturnType($methodReflection)['type']));
    }
}
