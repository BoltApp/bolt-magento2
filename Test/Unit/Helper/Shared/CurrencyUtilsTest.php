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

namespace Bolt\Boltpay\Test\Unit\Helper\Shared;

use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

/**
 * Class CurrencyUtilsTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper\Shared
 */
class CurrencyUtilsTest extends TestCase {
    /**
     * @test
     * @param code currency code
     * @param precision expected precision
     * @dataProvider currencyAndPrecisions
     * @throws \Exception
     */
    public function precisionCurrenciesForKnownCurrency( $code, $precision ) {
        $this->assertEquals( $precision, CurrencyUtils::getPrecisionForCurrencyCode( $code ) );
    }

    /**
     * @test
     * @throws \Exception
     */
    public function precisionForUnknown() {
        $this->expectException( \Exception::class );

        CurrencyUtils::getPrecisionForCurrencyCode( "XXX" );
    }


    public function currencyAndPrecisions() {
        return [
            [ "USD", 2 ],
            [ "JPY", 0 ],
            [ "EUR", 2 ],
            [ "CAD", 2 ]
        ];
    }
}
