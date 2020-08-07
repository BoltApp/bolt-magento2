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

/**
 * Class ShippingOptionsTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 */
class ShippingOptionsTest extends TestCase
{
    const TAX_RESULT = '111';

    /**
     * @var \Bolt\Boltpay\Model\Api\Data\ShippingOption
     */
    protected $shippingOption;

    /**
     * @var \Bolt\Boltpay\Model\Api\Data\ShippingOptions
     */
    protected $shippingOptions;

    protected function setUp()
    {
        $this->shippingOptions = new \Bolt\Boltpay\Model\Api\Data\ShippingOptions();
    }

    /**
     * @test
     */
    public function setAndGetShippingOptions()
    {
        $shippingOption = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $this->shippingOptions->setShippingOptions([$shippingOption]);
        $this->assertEquals([$shippingOption], $this->shippingOptions->getShippingOptions());
    }

    /**
     * @test
     */
    public function setAndGetTaxResult()
    {
        $this->shippingOptions->setTaxResult(self::TAX_RESULT);
        $this->assertEquals(self::TAX_RESULT, $this->shippingOptions->getTaxResult());
    }

    /**
     * @test
     */
    public function addAmountToShippingOptions()
    {
        $shippingOption = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $shippingOption->setCost(10);

        $this->shippingOptions->setShippingOptions([$shippingOption]);
        $this->shippingOptions->addAmountToShippingOptions(5);

        $this->assertEquals(15, $shippingOption->getCost());
    }
}
