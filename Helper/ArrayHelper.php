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
     * Converts giver array to an object, recursively
     *
     * @param array $array to be converted
     *
     * @return \stdClass object
     */
    public static function arrayToObject($array)
    {
        $obj = new \stdClass;
        foreach ($array as $k => $v) {
            if (strlen($k)) {
                if (is_array($v)) {
                    $obj->{$k} = self::arrayToObject($v); //RECURSION
                } else {
                    $obj->{$k} = $v;
                }
            }
        }
        return $obj;
    }
    
    /**
     * Save the structure of mixed array&object, so the serialized data can be restored properly.
     *
     * @param mixed $data
     * @param bool  $child
     * 
     * @return array
     */
    public static function saveStructureMixedArrayObject($data, $child = false)
    {
        $result = [];
        foreach ($data as $k => $v) {
            if (is_object($v)) {
                $result[$k]['type'] = 'object';
            } else {
                $result[$k]['type'] = 'normal';
            }
            if (is_array($v) || is_object($v)) {
                $result[$k]['content'] = self::saveStructureMixedArrayObject($v, true);
            } else {
                $result[$k]['content'] = 'normal';
            }
        }
        if (!$child) {
            $finalResult = [];
            if (is_object($data)) {
                $finalResult['type'] = 'object';
            } else {
                $finalResult['type'] = 'normal';
            }
            $finalResult['content'] = $result;
            $result = $finalResult;
        }
        return $result;
    }
    
    /**
     * Restore the serialized data to mixed array&objects properly.
     *
     * @param array $data
     * @param array $mixedStructure
     * @param bool  $child
     * 
     * @return mixed
     */
    public static function restoreMixedArrayObject($data, $mixedStructure, $child = false)
    {
        if (!empty($data) && !empty($mixedStructure) && is_array($mixedStructure)) {
            $mixedChildStructure = $child ? $mixedStructure : $mixedStructure['content'];
            foreach ($data as $k => &$v) {
                if (array_key_exists($k, $mixedChildStructure)) {
                    if (is_array($v) || is_object($v)) {
                        $v = self::restoreMixedArrayObject($v, $mixedChildStructure[$k]['content'], true);
                    }
                    if ($mixedChildStructure[$k]['type'] == 'object') {
                        $v = (object)$v;
                    }
                }
            }
        }
        if (!$child && $mixedStructure['type'] == 'object') {
            $data = (object)$data;
        }
        return $data;
    }

}
