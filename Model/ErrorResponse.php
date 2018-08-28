<?php

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