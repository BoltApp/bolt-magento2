<?php
namespace Bolt\Boltpay\Helper;

/**
 * Class ArrayHelper
 *
 * @package Bolt\Boltpay\Helper
 */
class ArrayHelper
{
    /**
     * @param array  $array
     * @param string $key
     * @param null   $default
     * @return mixed|null
     */
    public static function getValueFromArray($array, $key, $default = null)
    {
        if ($key instanceof \Closure) {
            return $key($array, $default);
        }

        if (is_array($key)) {
            $lastKey = array_pop($key);
            foreach ($key as $keyPart) {
                $array = static::getValueFromArray($array, $keyPart);
            }
            $key = $lastKey;
        }

        if (is_array($array) && (isset($array[$key]) || array_key_exists($key, $array))) {
            return $array[$key];
        }

        if (($pos = strrpos($key, '.')) !== false) {
            $array = static::getValueFromArray($array, substr($key, 0, $pos), $default);
            $key = substr($key, $pos + 1);
        }

        if (is_object($array)) {
            // this is expected to fail if the property does not exist, or __get() is not implemented
            // it is not reliably possible to check whether a property is accessible beforehand
            return $array->$key;
        } elseif (is_array($array)) {
            return (isset($array[$key]) || array_key_exists($key, $array)) ? $array[$key] : $default;
        }

        return $default;
    }

    /**
     * Get incrementId and quoteId from display_id.
     *
     * @param string $displayId
     * @return array - [$incrementId, $quoteId]
     */
    public static function extractDataFromDisplayId($displayId)
    {
        list($incrementId, $quoteId) = array_pad(
            explode(' / ', $displayId),
            2,
            null
        );

        if ($incrementId || $quoteId) {
            return [$incrementId, $quoteId];
        }

        return [];
    }
}
