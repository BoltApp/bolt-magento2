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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Helper\Shared;


use Magento\Framework\Exception\LocalizedException;


class ApiUtils {
    /**
     * A helper method for checking errors in JSON object.
     *
     * @return null|string
     */
    private static function handleJsonParseError()
    {
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return null;

            case JSON_ERROR_DEPTH:
                return 'Maximum stack depth exceeded';

            case JSON_ERROR_STATE_MISMATCH:
                return 'Underflow or the modes mismatch';

            case JSON_ERROR_CTRL_CHAR:
                return 'Unexpected control character found';

            case JSON_ERROR_SYNTAX:
                return 'Syntax error, malformed JSON';

            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';

            default:
                return 'Unknown error';
        }
    }

    private static function isApiError($response) {
        $arr = (array)$response;
        return array_key_exists('errors', $arr) ||
            array_key_exists('error_code', $arr) ||
            array_key_exists('error', $arr);
    }

    public static function getJSONFromResponseBody($responseBody) {
        $resultFromJSON = json_decode($responseBody);
        $jsonError  = self::handleJsonParseError();
        if ($jsonError != null) {
            $message = __("JSON Parse Error: " . $jsonError);
            throw new LocalizedException($message);
        }

        if (self::isApiError($resultFromJSON)) {
            $message = isset($resultFromJSON->errors[0]->message) ? __($resultFromJSON->errors[0]->message) :  __("Bolt API Error Response");
            throw new LocalizedException($message);
        }
        return $resultFromJSON;
    }

    public static function constructRequestHeaders(
        $storeVersion,
        $moduleVersion,
        $requestData,
        $apiKey,
        $additionalHeaders = array()) {

        return [
                'User-Agent'            => 'BoltPay/Magento-'.$storeVersion . '/' . $moduleVersion,
                'X-Bolt-Plugin-Version' => $moduleVersion,
                'Content-Type'          => 'application/json',
                'Content-Length'        => $requestData ? strlen($requestData) : null,
                'X-Api-Key'             => $apiKey,
                'X-Nonce'               => rand(100000000000, 999999999999)
            ] + $additionalHeaders;
    }
}