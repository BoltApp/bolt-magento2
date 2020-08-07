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

namespace Bolt\Boltpay\Helper;

/**
 * Class SecretObscurer
 *
 * @package Bolt\Boltpay\Helper
 */
class SecretObscurer
{
    /**
     * Obscures the input string.
     *
     * @param string $input
     * @return string
     */
    public static function obscure($input)
    {
        if (strlen($input) == 0) {
            return '';
        }
        if (strlen($input) < 6) {
            return '***';
        }
        return substr($input, 0, 3) . '***' . substr($input, -3);
    }
}
