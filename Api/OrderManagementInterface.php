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
    public function manage(
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

    /**
     * Deletes a specified order by ID.
     *
     * @param int $id The order ID.
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     * @throws NoSuchEntityException
     * @throws WebapiException
     */
    public function deleteById($id);

    /**
     * Subscribe an user / email to newsletter
     *
     * @param int $id The order ID.
     * @return bool
     * @throws NoSuchEntityException
     * @throws WebapiException
     */
    public function subscribeToNewsletter($id);

    /**
     * Creates invoice by order ID.
     * We need this endpoing because magento API is not able to create
     * partial invoice without settings specific items
     *
     * @param int $id The order ID.
     * @param float $amount
     * @param bool $notify
     * @return int
     * @throws NoSuchEntityException
     * @throws WebapiException
     * @throws \Exception
     */
    public function createInvoice($id, $amount, $notify = false);

    /**
     * Places an order by order ID.
     * We need this endpoint because we skip order place during order creation
     * as transaction might be failed on bolt side
     * & we don't want to trigger some after events if transaction failed.
     *
     * @param int $id The order ID.
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws NoSuchEntityException
     * @throws WebapiException
     */
    public function placeOrder($id);
}
