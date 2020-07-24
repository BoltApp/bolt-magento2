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
 * Class ShippingTaxTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data
 */
class ShippingTaxTest extends TestCase
{
    const TAX_AMOUNT = '111';

    /**
     * @var \Bolt\Boltpay\Model\Api\Data\ShippingTax
     */
    protected $shippingTax;

    protected function setUp()
    {
        $this->shippingTax = new \Bolt\Boltpay\Model\Api\Data\ShippingTax;
    }

    /**
     * @test
     */
    public function setAndGetAmount()
    {
        $this->shippingTax->setAmount(self::TAX_AMOUNT);
        $this->assertEquals(self::TAX_AMOUNT, $this->shippingTax->getAmount());
    }
}
