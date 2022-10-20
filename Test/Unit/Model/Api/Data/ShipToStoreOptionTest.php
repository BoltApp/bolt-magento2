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

namespace Bolt\Boltpay\Test\Unit\Model\Api\Data;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\Api\Data\ShipToStoreOption;
use Bolt\Boltpay\Model\Api\Data\StoreAddress;

/**
 * Class ShipToStoreOptionTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\Data\ShipToStoreOption
 */
class ShipToStoreOptionTest extends BoltTestCase
{
    const REFERENCE = 'INSTOREPICKUPREFERENCE';
    const COST = 10;
    const TAX_AMOUNT = 123;
    const STORE_NAME = 'INSTOREPICKUP_STORE';
    const DISTANCE = 6.6;
    const DISTANCE_UNIT = 'km';
    const DESCRIPTION = '3 to 5 BUSINESS DAYS';

    /**
     * @var ShipToStoreOption
     */
    protected $shipToStoreOption;
    
    /**
     * @var StoreAddress
     */
    protected $storeAddress;

    protected function setUpInternal()
    {
        $this->storeAddress =  new StoreAddress();
        $this->shipToStoreOption = new ShipToStoreOption();
        $this->shipToStoreOption->setReference(self::REFERENCE);
        $this->shipToStoreOption->setCost(self::COST);
        $this->shipToStoreOption->setStoreName(self::STORE_NAME);
        $this->shipToStoreOption->setAddress($this->storeAddress);
        $this->shipToStoreOption->setDistance(self::DISTANCE);
        $this->shipToStoreOption->setDistanceUnit(self::DISTANCE_UNIT);
        $this->shipToStoreOption->setTaxAmount(self::TAX_AMOUNT);
        $this->shipToStoreOption->setDescription(self::DESCRIPTION);
    }

    /**
     * @test
     * that getReference would return reference
     * @covers ::getReference
     */
    public function getReference_always_returnsReference()
    {
        $this->assertEquals(self::REFERENCE, $this->shipToStoreOption->getReference());
    }

    /**
     * @test
     * that getCost would return cost
     * @covers ::getCost
     */
    public function getCost_always_returnsCost()
    {
        $this->assertEquals(self::COST, $this->shipToStoreOption->getCost());
    }
    
    /**
     * @test
     * that getStoreName would return store name
     * @covers ::getStoreName
     */
    public function getStoreName_always_returnsStoreName()
    {
        $this->assertEquals(self::STORE_NAME, $this->shipToStoreOption->getStoreName());
    }
    
    /**
     * @test
     * that getAddress would return store address
     * @covers ::getAddress
     */
    public function getAddress_always_returnsAddress()
    {
        $this->assertEquals($this->storeAddress, $this->shipToStoreOption->getAddress());
    }
    
    /**
     * @test
     * that getDistance would return distance
     * @covers ::getDistance
     */
    public function getDistance_always_returnsDistance()
    {
        $this->assertEquals(self::DISTANCE, $this->shipToStoreOption->getDistance());
    }
    
    /**
     * @test
     * that getDistanceUnit would return distance unit
     * @covers ::getDistanceUnit
     */
    public function getDistanceUnit_always_returnsDistanceUnit()
    {
        $this->assertEquals(self::DISTANCE_UNIT, $this->shipToStoreOption->getDistanceUnit());
    }

    /**
     * @test
     * that getTaxAmount would return tax amount
     * @covers ::getTaxAmount
     */
    public function getTaxAmount_always_returnsTaxAmount()
    {
        $this->assertEquals(self::TAX_AMOUNT, $this->shipToStoreOption->getTaxAmount());
    }

    /**
     * @test
     * that getTaxAmount would return tax amount
     * @covers ::getDescription
     */
    public function getDescription_always_returnsDescription()
    {
        $this->assertEquals(self::DESCRIPTION, $this->shipToStoreOption->getDescription());
    }

    /**
     * @test
     * that jsonSerialize would return an associative array of values
     * @covers ::jsonSerialize
     */
    public function jsonSerialize_always_returnsValues()
    {
        $result = $this->shipToStoreOption->jsonSerialize();
        $this->assertEquals([
            'reference' => self::REFERENCE,
            'cost' => self::COST,
            'store_name' => self::STORE_NAME,
            'address' => $this->storeAddress,
            'distance' => self::DISTANCE,
            'distance_unit' => self::DISTANCE_UNIT,
            'tax_amount' =>self::TAX_AMOUNT,
            'description' =>self::DESCRIPTION
        ], $result);
    }
}
