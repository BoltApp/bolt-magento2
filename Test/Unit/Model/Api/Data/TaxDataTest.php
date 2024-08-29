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
 * @copyright  Copyright (c) 2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api\Data;

use Bolt\Boltpay\Api\Data\TaxDataInterface;
use Bolt\Boltpay\Model\Api\Data\ShippingOption;
use Bolt\Boltpay\Model\Api\Data\ShipToStoreOption;
use Bolt\Boltpay\Model\Api\Data\TaxData;
use Bolt\Boltpay\Model\Api\Data\TaxResult;
use Bolt\Boltpay\Test\Unit\BoltTestCase;

/**
 * Class TaxDataTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\Data\TaxData
 */
class TaxDataTest extends BoltTestCase
{
    /**
     * @var TaxDataInterface
     */
    private $taxData;

    /**
     * @var TaxResult
     */
    private $taxResult;

    /**
     * @var ShippingOption
     */
    private $shippingOption;
    
    /**
     * @var ShipToStoreOption
     */
    private $shipToStoreOption;

    public function setUpInternal()
    {
        $this->taxResult = new TaxResult;
        $this->shippingOption = new ShippingOption;
        $this->shipToStoreOption = new ShipToStoreOption;
        $this->taxData = new TaxData;
        $this->taxData->setTaxResult($this->taxResult);
        $this->taxData->setShippingOption($this->shippingOption);
        $this->taxData->setShipToStoreOption($this->shipToStoreOption);
    }

    /**
     * @test
     * that getTaxResult would return tax result
     *
     * @covers ::getTaxResult
     */
    public function getTaxResult()
    {
        $this->assertEquals($this->taxResult, $this->taxData->getTaxResult());
    }

    /**
     * @test
     * that setTaxResult would set tax result and return tax data class instance
     *
     * @covers ::setTaxResult
     */
    public function setTaxResult()
    {
        $result = $this->taxData->setTaxResult($this->taxResult);
        $this->assertInstanceOf(TaxData::class, $result);
    }

    /**
     * @test
     * that getShippingOption would return shipping option
     *
     * @covers ::getShippingOption
     */
    public function getShippingOption()
    {
        $this->assertEquals($this->shippingOption, $this->taxData->getShippingOption());
    }

    /**
     * @test
     * that setShippingOption would set shipping option and return tax data instance
     *
     * @covers ::setShippingOption
     */
    public function setShippingOption()
    {
        $result = $this->taxData->setShippingOption($this->shippingOption);
        $this->assertInstanceOf(TaxData::class, $result);
    }
    
    /**
     * @test
     * that getShipToStoreOption would return ship to store option
     *
     * @covers ::getShipToStoreOption
     */
    public function getShipToStoreOption()
    {
        $this->assertEquals($this->shipToStoreOption, $this->taxData->getShipToStoreOption());
    }

    /**
     * @test
     * that setShipToStoreOption would set ship to store option and return tax data instance
     *
     * @covers ::setShipToStoreOption
     */
    public function setShipToStoreOption()
    {
        $result = $this->taxData->setShipToStoreOption($this->shipToStoreOption);
        $this->assertInstanceOf(TaxData::class, $result);
    }

    /**
     * @test
     * that jsonSerialize would return array of tax result and shipping option
     *
     * @covers ::jsonSerialize
     */
    public function jsonSerialize()
    {
        $result = $this->taxData->jsonSerialize();
        $this->assertEquals([
            'tax_result' => $this->taxResult,
            'shipping_option' => $this->shippingOption,
            'ship_to_store_option' => $this->shipToStoreOption
        ], $result);
    }
}
