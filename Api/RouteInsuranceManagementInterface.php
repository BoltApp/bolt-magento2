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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Api;

use Magento\Framework\Webapi\Exception as WebapiException;

/**
 * Route insurance interface. Route endpoint.
 *
 * Enable or disable route insurance.
 * @api
 */
interface RouteInsuranceManagementInterface
{
    const ROUTE_MODULE_NAME = 'Route_Route';
    const RESPONSE_SUCCESS_STATUS = 200;
    const RESPONSE_FAIL_STATUS = 404;

    /**
     * Enable or disable route insurance.
     *
     * @api
     * @param string $cartId quote id
     * @param bool $routeIsInsured route enabled status
     * @return void
     *
     * @throws WebapiException
     */
    public function execute($cartId, $routeIsInsured);
}
