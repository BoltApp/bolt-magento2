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

use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Bolt\Boltpay\Api\Data\TaxDataInterface;
use Bolt\Boltpay\Model\Api\Data\ShippingOption;
use Bolt\Boltpay\Model\Api\Data\TaxData;
use Bolt\Boltpay\Api\Data\TaxResultInterface;
use Bolt\Boltpay\Model\Api\Data\TaxResult;
use PHPUnit\Framework\TestCase;

/**
 * Class TaxDataTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data
 * @coversDefaultClass TaxData
 */
class TaxDataTest extends TestCase
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

    public function setUp()
    {
        $this->taxResult = new TaxResult;
        $this->shippingOption = new ShippingOption;
        $this->taxData = new TaxData;
        $this->taxData->setTaxResult($this->taxResult);
        $this->taxData->setShippingOption($this->shippingOption);
    }

    /**
     * @test
     */
    public function getTaxResult()
    {
        $this->assertEquals($this->taxResult, $this->taxData->getTaxResult());
    }

    /**
     * @test
     */
    public function setTaxResult()
    {
        $result = $this->taxData->setTaxResult($this->taxResult);
        $this->assertInstanceOf(TaxData::class, $result);
    }

    /**
     * @test
     */
    public function getShippingOption()
    {
        $this->assertEquals($this->shippingOption, $this->taxData->getShippingOption());
    }

    /**
     * @test
     */
    public function seShippingOption()
    {
        $result = $this->taxData->setShippingOption($this->shippingOption);
        $this->assertInstanceOf(TaxData::class, $result);
    }

    /**
     * @test
     */
    public function jsonSerialize()
    {
        $result = $this->taxData->jsonSerialize();
        $this->assertEquals([
            'tax_result' => $this->taxResult,
            'shipping_option' => $this->shippingOption
        ], $result);
    }
}