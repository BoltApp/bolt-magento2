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

namespace Bolt\Boltpay\Model;

/**
 * Class ErrorResponse
 *
 * @package Bolt\Boltpay\Model
 */
class ErrorResponse
{
    const ERR_INSUFFICIENT_INFORMATION     = 6200;
    const ERR_CODE_INVALID                 = 6201;
    const ERR_CODE_EXPIRED                 = 6202;
    const ERR_CODE_NOT_AVAILABLE           = 6203;
    const ERR_CODE_LIMIT_REACHED           = 6204;
    const ERR_MINIMUM_CART_AMOUNT_REQUIRED = 6205;
    const ERR_UNIQUE_EMAIL_REQUIRED        = 6206;
    const ERR_ITEMS_NOT_ELIGIBLE           = 6207;
    const ERR_SERVICE                      = 6001;

    const ERR_PPC_OUT_OF_STOCK = 6301;
    const ERR_PPC_INVALID_QUANTITY = 6303;

    /**
     * @param       $errCode
     * @param       $message
     * @param array $additionalData
     * @return string
     */
    public function prepareErrorMessage($errCode, $message, $additionalData = [])
    {
        $errResponse = [
            'status' => 'failure',
            'error' => [
                'code' => $errCode,
                'message' => $message,
            ],
        ];

        if (count($additionalData)) {
            $errResponse += $additionalData;
        }

        return json_encode($errResponse, JSON_FORCE_OBJECT);
    }
}
