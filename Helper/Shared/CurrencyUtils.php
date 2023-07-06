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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Helper\Shared;

class CurrencyUtils
{
    // Autogenerated with https://gist.github.com/daisy1754/07680be4ec298b1d3f20bd941bcda852
    const currencyToPrecisions = [
        "AED" => 2,
        "AFN" => 2,
        "ALL" => 2,
        "AMD" => 2,
        "ANG" => 2,
        "AOA" => 2,
        "ARS" => 2,
        "AUD" => 2,
        "AWG" => 2,
        "AZN" => 2,
        "BAM" => 2,
        "BBD" => 2,
        "BDT" => 2,
        "BGN" => 2,
        "BHD" => 3,
        "BIF" => 0,
        "BMD" => 2,
        "BND" => 2,
        "BOB" => 2,
        "BOV" => 2,
        "BRL" => 2,
        "BSD" => 2,
        "BTN" => 2,
        "BWP" => 2,
        "BYN" => 2,
        "BZD" => 2,
        "CAD" => 2,
        "CDF" => 2,
        "CHE" => 2,
        "CHF" => 2,
        "CHW" => 2,
        "CLF" => 4,
        "CLP" => 0,
        "CNY" => 2,
        "COP" => 2,
        "COU" => 2,
        "CRC" => 2,
        "CUC" => 2,
        "CUP" => 2,
        "CVE" => 2,
        "CZK" => 2,
        "DJF" => 0,
        "DKK" => 2,
        "DOP" => 2,
        "DZD" => 2,
        "EGP" => 2,
        "ERN" => 2,
        "ETB" => 2,
        "EUR" => 2,
        "FJD" => 2,
        "FKP" => 2,
        "GBP" => 2,
        "GEL" => 2,
        "GHS" => 2,
        "GIP" => 2,
        "GMD" => 2,
        "GNF" => 0,
        "GTQ" => 2,
        "GYD" => 2,
        "HKD" => 2,
        "HNL" => 2,
        "HRK" => 2,
        "HTG" => 2,
        "HUF" => 2,
        "IDR" => 2,
        "ILS" => 2,
        "INR" => 2,
        "IQD" => 3,
        "IRR" => 2,
        "ISK" => 0,
        "JMD" => 2,
        "JOD" => 3,
        "JPY" => 0,
        "KES" => 2,
        "KGS" => 2,
        "KHR" => 2,
        "KMF" => 0,
        "KPW" => 2,
        "KRW" => 0,
        "KWD" => 3,
        "KYD" => 2,
        "KZT" => 2,
        "LAK" => 2,
        "LBP" => 2,
        "LKR" => 2,
        "LRD" => 2,
        "LSL" => 2,
        "LYD" => 3,
        "MAD" => 2,
        "MDL" => 2,
        "MGA" => 2,
        "MKD" => 2,
        "MMK" => 2,
        "MNT" => 2,
        "MOP" => 2,
        "MRU" => 2,
        "MUR" => 2,
        "MVR" => 2,
        "MWK" => 2,
        "MXN" => 2,
        "MXV" => 2,
        "MYR" => 2,
        "MZN" => 2,
        "NAD" => 2,
        "NGN" => 2,
        "NIO" => 2,
        "NOK" => 2,
        "NPR" => 2,
        "NZD" => 2,
        "OMR" => 3,
        "PAB" => 2,
        "PEN" => 2,
        "PGK" => 2,
        "PHP" => 2,
        "PKR" => 2,
        "PLN" => 2,
        "PYG" => 0,
        "QAR" => 2,
        "RON" => 2,
        "RSD" => 2,
        "RUB" => 2,
        "RWF" => 0,
        "SAR" => 2,
        "SBD" => 2,
        "SCR" => 2,
        "SDG" => 2,
        "SEK" => 2,
        "SGD" => 2,
        "SHP" => 2,
        "SLL" => 2,
        "SOS" => 2,
        "SRD" => 2,
        "SSP" => 2,
        "STN" => 2,
        "SVC" => 2,
        "SYP" => 2,
        "SZL" => 2,
        "THB" => 2,
        "TJS" => 2,
        "TMT" => 2,
        "TND" => 3,
        "TOP" => 2,
        "TRY" => 2,
        "TTD" => 2,
        "TWD" => 2,
        "TZS" => 2,
        "UAH" => 2,
        "UGX" => 0,
        "USD" => 2,
        "USN" => 2,
        "UYI" => 0,
        "UYU" => 2,
        "UYW" => 4,
        "UZS" => 2,
        "VES" => 2,
        "VND" => 0,
        "VUV" => 0,
        "WST" => 2,
        "XAF" => 0,
        "XCD" => 2,
        "XOF" => 0,
        "XPF" => 0,
        "YER" => 2,
        "ZAR" => 2,
        "ZMW" => 2,
        "ZWL" => 2,
    ];

    /**
     * Returns "precision" of currency. For instance USD has precision of 2 because $1.23 is valid amount white $1.234 is not
     * (there is no such thing as 0.1 cent). Likewise precision of JPY is 0 because there is no 0.1 yen.
     *
     * @param string $code 3-digit ISO currency code
     *
     * @return int precision in integer
     * @throws \Exception when unknown currency code is passed
     */
    public static function getPrecisionForCurrencyCode($code)
    {
        if (!array_key_exists($code, CurrencyUtils::currencyToPrecisions)) {
            throw new \Exception("unknown currency code: " . $code);
        }
        return CurrencyUtils::currencyToPrecisions[$code];
    }

    /**
     * Convert major currency (eg dollar) to minor currency (eg cents). For currencies that don't have minor unit (eg JPY),
     * returns input as is. Result will be rounded to integer.
     * Example:
     *   toMinor(12.34, "USD") -> 1234
     *   toMinor(1234, "JPY") -> 1234
     *   toMinor(12.345, "USD") -> 1235
     *
     * @param float $amountInMajor
     * @param string $currencyCode 3-digit currency code
     *
     * @return integer amount in minor currency
     * @throws \Exception when unknown currency code is passed
     */
    public static function toMinor($amountInMajor, $currencyCode)
    {
        return (int) round(self::toMinorWithoutRounding($amountInMajor, $currencyCode));
    }

    /**
     * This function behaves same as toMinor, but without rounding result.
     * @see CurrencyUtils::toMinor()
     *
     * @param float $amountInMajor
     * @param string $currencyCode 3-digit currency code
     *
     * @return float amount in minor currency
     * @throws \Exception when unknown currency code is passed
     */
    public static function toMinorWithoutRounding($amountInMajor, $currencyCode)
    {
        $precision = self::getPrecisionForCurrencyCode($currencyCode);
        return (float)$amountInMajor * pow(10, $precision);
    }

    /**
     * Convert minor currency amount (eg cents) to major currency (eg dollar). For currencies that don't have minor unit
     * (eg JPY), returns input as is. Result is rounded with currency's precision.
     * Example:
     *   toMajor(1234, "USD") -> 12.34
     *   toMajor(1234, "JPY") -> 1234
     *   toMajor(1234.5, "USD") -> 12.35
     *
     * @param float $amountInMinor
     * @param string $currencyCode 3-digit currency code
     *
     * @return float amount in major currency
     * @throws \Exception when unknown currency code is passed
     */
    public static function toMajor($amountInMinor, $currencyCode)
    {
        $precision = self::getPrecisionForCurrencyCode($currencyCode);
        return round($amountInMinor / (float) pow(10, $precision), $precision);
    }
}
