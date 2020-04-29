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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api\Data;

use PHPUnit\Framework\TestCase;

/**
 * Class ShippingOptionTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 */
class ShippingOptionTest extends TestCase
{
    const REFERENCE = 'REFERENCE';
    const SERVICE = 'SERVICE';
    const COST = 'COST';
    const TAX_AMOUNT = '111';

    /**
     * @var \Bolt\Boltpay\Model\Api\Data\ShippingOption
     */
    protected $shippingOption;

    protected function setUp()
    {
        $this->shippingOption = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $this->shippingOption->setReference(self::REFERENCE);
        $this->shippingOption->setService(self::SERVICE);
        $this->shippingOption->setCost(self::COST);
        $this->shippingOption->setTaxAmount(self::TAX_AMOUNT);
    }

    /**
     * @test
     */
    public function setAndGetReference()
    {
        $this->assertEquals(self::REFERENCE, $this->shippingOption->getReference());
    }

    /**
     * @test
     */
    public function testAndGetService()
    {
        $this->assertEquals(self::SERVICE, $this->shippingOption->getService());
    }

    /**
     * @test
     */
    public function setAndGetCost()
    {
        $this->assertEquals(self::COST, $this->shippingOption->getCost());
    }

    /**
     * @test
     */
    public function setAndGetTaxAmount()
    {
        $this->assertEquals(self::TAX_AMOUNT, $this->shippingOption->getTaxAmount());
    }

    /**
     * @test
     */
    public function jsonSerialize()
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
