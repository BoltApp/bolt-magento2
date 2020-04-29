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

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Model\Api\Data\ShippingOption;
use Bolt\Boltpay\Api\Data\ShippingDataInterface;
use Bolt\Boltpay\Model\Api\Data\ShippingData;

class ShippingDataTest extends TestCase
{
    /**
     * @var ShippingDataInterface
     */
    private $shippingData;

    /**
     * @var ShippingOption[]
     */
    private $shippingOptions;

    protected function setUp()
    {
        $this->shippingOptions = [new ShippingOption];
        $this->shippingData = new ShippingData;
        $this->shippingData->setShippingOptions($this->shippingOptions);
    }

    /**
     * @test
     */
    public function getShippingOptions()
    {
        $this->assertEquals($this->shippingOptions, $this->shippingData->getShippingOptions());
    }

    /**
     * @test
     */
    public function setShippingOptions()
    {
        $result = $this->shippingData->setShippingOptions($this->shippingOptions);
        $this->assertInstanceOf(ShippingData::class, $result);
    }

    /**
     * @test
     */
    public function jsonSerialize()
    {
        $result = $this->shippingData->jsonSerialize();
        $this->assertEquals([
            'shipping_options' => $this->shippingOptions
        ], $result);
    }
}
