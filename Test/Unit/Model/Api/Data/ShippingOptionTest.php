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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api\Data;

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Model\Api\Data\ShippingOption;

/**
 * Class ShippingOptionTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\Data\ShippingOption
 */
class ShippingOptionTest extends TestCase
{
    const REFERENCE = 'REFERENCE';
    const SERVICE = 'SERVICE';
    const COST = 'COST';
    const TAX_AMOUNT = '111';

    /**
     * @var ShippingOption
     */
    protected $shippingOption;

    protected function setUp()
    {
        $this->shippingOption = new ShippingOption();
        $this->shippingOption->setReference(self::REFERENCE);
        $this->shippingOption->setService(self::SERVICE);
        $this->shippingOption->setCost(self::COST);
        $this->shippingOption->setTaxAmount(self::TAX_AMOUNT);
    }

    /**
     * @test
     * that getReference would return reference
     * @covers ::getReference
     */
    public function getReference_always_returnsReference()
    {
        $this->assertEquals(self::REFERENCE, $this->shippingOption->getReference());
    }

    /**
     * @test
     * that getService would return service
     * @covers ::getService
     */
    public function getService_always_returnsService()
    {
        $this->assertEquals(self::SERVICE, $this->shippingOption->getService());
    }

    /**
     * @test
     * that getCost would return cost
     * @covers ::getCost
     */
    public function getCost_always_returnsCost()
    {
        $this->assertEquals(self::COST, $this->shippingOption->getCost());
    }

    /**
     * @test
     * that getTaxAmount would return tax amount
     * @covers ::getTaxAmount
     */
    public function getTaxAmount_always_returnsTaxAmount()
    {
        $this->assertEquals(self::TAX_AMOUNT, $this->shippingOption->getTaxAmount());
    }

    /**
     * @test
     * that jsonSerialize would return array containing service, cost, reference and tax amount
     * @covers ::jsonSerialize
     */
    public function jsonSerialize_always_returnsServiceCostReferenceAndTaxAmount()
    {
        $result = $this->shippingOption->jsonSerialize();
        $this->assertEquals([
            'service' => self::SERVICE,
            'cost' => self::COST,
            'reference' => self::REFERENCE,
            'tax_amount' => self::TAX_AMOUNT
        ], $result);
    }
}
