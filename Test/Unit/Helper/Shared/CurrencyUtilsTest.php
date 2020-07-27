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

namespace Bolt\Boltpay\Test\Unit\Helper\Shared;

use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

/**
 * Class CurrencyUtilsTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper\Shared
 */
class CurrencyUtilsTest extends TestCase
{
    /**
     * @test
     * @param code currency code
     * @param precision expected precision
     * @dataProvider currencyAndPrecisions
     * @throws \Exception
     */
    public function precisionCurrenciesForKnownCurrency($code, $precision)
    {
        $this->assertEquals($precision, CurrencyUtils::getPrecisionForCurrencyCode($code));
    }

    /**
     * @test
     * @throws \Exception
     */
    public function precisionForUnknown()
    {
        $this->expectException(\Exception::class);

        CurrencyUtils::getPrecisionForCurrencyCode("XXX");
    }

    /**
     * @test
     * @dataProvider toMinorData
     */
    public function toMinor($code, $amount, $expected, $unused)
    {
        $this->assertEquals($expected, CurrencyUtils::toMinor($amount, $code));
    }

    /**
     * @test
     * @dataProvider toMinorData
     */
    public function toMinorWithoutRounding($code, $amount, $unused, $expected)
    {
        $this->assertEquals($expected, CurrencyUtils::toMinorWithoutRounding($amount, $code));
    }

    /**
     * @test
     * @dataProvider toMajorData
     */
    public function toMajor($code, $amount, $expected)
    {
        $this->assertEquals($expected, CurrencyUtils::toMajor($amount, $code));
    }

    public function currencyAndPrecisions()
    {
        return [
            [ "USD", 2 ],
            [ "JPY", 0 ],
            [ "EUR", 2 ],
            [ "CAD", 2 ]
        ];
    }

    public function toMinorData()
    {
        return [
            // code, amount, toMinor, toMinorWithoutRounding
            [ "USD", 12.34, 1234, 1234 ],
            [ "JPY", 1234, 1234, 1234 ],
            [ "USD", 12.345, 1235, 1234.5 ],
            [ "JPY", 1234.5, 1235, 1234.5 ],
            [ "USD", 0, 0, 0 ],
            [ "USD", "", 0, 0 ]
        ];
    }

    public function toMajorData()
    {
        return [
            [ "USD", 1234, 12.34 ],
            [ "JPY", 1234, 1234 ],
            [ "USD", 1234.5, 12.35 ],
        ];
    }
}
