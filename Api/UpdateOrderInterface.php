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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Api;

/**
 * Update Order interface.
 * @api
 */
interface UpdateOrderInterface
{
    /**
     * Update order.
     * Hook formats:
     * [{"type":"order.update","transaction":{},"order_reference":"BLT5c753abfce9cf", "display_id": "134"}]
     *
     * @api
     *
     * @param mixed $type
     * @param mixed $order
     * @param mixed $currency
     *
     * @return void
     */
    public function execute(
        $type = null,
        $transaction = null,
        $order_reference = null,
        $display_id = null
    );
}
