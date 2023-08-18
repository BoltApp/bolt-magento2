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

namespace Bolt\Boltpay\Api;

use Magento\Framework\Webapi\Exception as WebapiException;
use Bolt\Boltpay\Api\Data\ExtendWarrantyPlanInterface;

/**
 * Extend_Warranty module management
 *
 * @api
 */
interface ExtendWarrantyManagementInterface
{
    const MODULE_NAME = 'Extend_Warranty';
    const WARRANTY_PRODUCT_TYPE = 'warranty';
    const RESPONSE_SUCCESS_STATUS = 200;
    const RESPONSE_FAIL_STATUS = 404;

    /**
     * Add extend warranty product to the cart based on extend warranty data
     *
     * @api
     * @param string $cartId
     * @param ExtendWarrantyPlanInterface $plan
     * @return void
     *
     * @throws WebapiException
     */
    public function addWarrantyPlan(string $cartId, ExtendWarrantyPlanInterface $plan);
}
