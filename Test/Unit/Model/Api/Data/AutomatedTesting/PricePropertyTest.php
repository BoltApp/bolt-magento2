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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api\Data\AutomatedTesting;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\PriceProperty;

/**
 * Class PricePropertyTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data\AutomatedTesting
 */
class PricePropertyTest extends BoltTestCase
{
    /**
     * @var PriceProperty
     */
    protected $priceProperty;

    protected function setUpInternal()
    {
        $this->priceProperty = new PriceProperty();
        $this->priceProperty->setName('name');
        $this->priceProperty->setPrice('10.000');
    }

    /**
     * @test
     */
    public function setAndGetName()
    {
        $this->assertEquals('name', $this->priceProperty->getName());
    }

    /**
     * @test
     */
    public function setAndGetPrice()
    {
        $this->assertEquals('10.000', $this->priceProperty->getPrice());
    }

    /**
     * @test
     */
    public function jsonSerialize()
    {
        $result = $this->priceProperty->jsonSerialize();
        $this->assertEquals([
            'name' => 'name',
            'price' => '10.000',
        ], $result);
    }
}
