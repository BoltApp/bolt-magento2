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

use Bolt\Boltpay\Api\Data\TaxResultInterface;
use Bolt\Boltpay\Model\Api\Data\TaxResult;
use PHPUnit\Framework\TestCase;

/**
 * Class TaxResultTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data
 * @coversDefaultClass TaxResult
 */
class TaxResultTest extends TestCase
{
    const SUBTOTAL_AMOUNT = 5;

    /**
     * @var TaxResultInterface
     */
    private $taxResult;

    protected function setUp()
    {
        $this->taxResult = new TaxResult;
        $this->taxResult->setSubtotalAmount(self::SUBTOTAL_AMOUNT);
    }

    /**
     * @test
     */
    public function getSubtotalAmount()
    {
        $this->assertEquals(self::SUBTOTAL_AMOUNT, $this->taxResult->getSubtotalAmount());
    }

    /**
     * @test
     */
    public function setSubtotalAmount()
    {
        $result = $this->taxResult->setSubtotalAmount(self::SUBTOTAL_AMOUNT);
        $this->assertInstanceOf(TaxResult::class, $result);
    }

    /**
     * @test
     */
    public function jsonSerialize()
    {
        $result = $this->taxResult->jsonSerialize();
        $this->assertEquals([
            'subtotal_amount' => self::SUBTOTAL_AMOUNT,
        ], $result);
    }
}