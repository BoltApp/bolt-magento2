<?php
/**
 * Copyright © 2013-2017 Bold, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */


namespace Bolt\Boltpay\Api;

/**
 * Discount Code Validation interface
 * @api
 */
interface DiscountCodeValidationInterface
{
    /**
     * @api
     * @return void
     */
    public function validate();
}
