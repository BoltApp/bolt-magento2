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
 * Create Order interface.
 * @api
 */
interface CreateOrderInterface
{
    /**
     * Manage order.
     * Hook formats:
     * {"quote_id":"350","reference":"Q3QR-R2JW-D9BC","transaction_id":"TAmdZdMTSkyYG","notification_type":"auth"}
     * {"id":"TAgRDWjyT88sJ","reference":"D6JZ-TQ63-RFHJ","order":"353","type":"payment","amount":21485,
     * "currency":"USD","status":"completed","display_id":"000000318"}
     *
     * @api
     *
     * @param mixed $id
     * @param mixed $reference
     * @param mixed $order
     * @param mixed $type
     * @param mixed $amount
     * @param mixed $currency
     * @param mixed $status
     * @param mixed $display_id
     * @param mixed $source_transaction_id
     * @param mixed $source_transaction_reference
     *
     * @return void
     */
    public function execute(
        $id = null,
        $reference = null,
        $order = null,
        $type = null,
        $amount = null,
        $currency = null,
        $status = null,
        $display_id = null,
        $source_transaction_id = null,
        $source_transaction_reference = null
    );
}
