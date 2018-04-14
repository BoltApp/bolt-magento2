<?php
/**
 * Copyright © 2013-2017 Bold, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */


namespace Bolt\Boltpay\Api;

/**
 * Order management interface. Saves the order and updates the payment / transaction info.
 *
 * An order is a document that a web store issues to a customer. Magento generates a sales order that lists the product
 * items, billing and shipping addresses, and shipping and payment methods. A corresponding external document, known as
 * a purchase order, is emailed to the customer.
 * @api
 */
interface OrderManagementInterface
{
    /**
     * Manage order.
     * Hook formats:
     * {"quote_id":"350","reference":"Q3QR-R2JW-D9BC","transaction_id":"TAmdZdMTSkyYG","notification_type":"auth"}
     * {"id":"TAgRDWjyT88sJ","reference":"D6JZ-TQ63-RFHJ","order":"353","type":"payment","amount":21485,"currency":"USD","status":"completed","display_id":"000000318"}
     *
     * @api
     *
     * @param mixed $quote_id
     * @param mixed $reference
     * @param mixed $transaction_id
     * @param mixed $notification_type
     * @param mixed $amount
     * @param mixed $currency
     * @param mixed $status
     * @param mixed $display_id
     * @param mixed $source_transaction_id
     * @param mixed $source_transaction_reference
     *
     * @return void
     */
    public function manage($quote_id = null, $reference, $transaction_id = null, $notification_type = null, $amount = null, $currency = null, $status = null, $display_id = null, $source_transaction_id = null, $source_transaction_reference = null);
}
