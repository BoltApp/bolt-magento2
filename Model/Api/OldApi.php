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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\OldApiInterface;
use Bolt\Boltpay\Api\OrderManagementInterface;
use Bolt\Boltpay\Api\UpdateCartInterface;

class OldApi implements OldApiInterface
{
    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @var UpdateCartInterface
     */
    protected $updateCart;

    /**
     * @var OrderManagementInterface $orderManagement
     * @var UpdateCartInterface $updateCart
     */
    public function __construct(
        OrderManagementInterface $orderManagement,
        UpdateCartInterface $updateCart
    )
    {
        $this->orderManagement = $orderManagement;
        $this->updateCart = $updateCart;
    }

    /**
     * @api
     * 
     * @return void
     */
    public function manage(
        $id = null,
        $reference = null,
        $order = null, // <parent quote ID>
        $type = null,
        $amount = null,
        $currency = null,
        $status = null,
        $display_id = null, // <order increment ID>
        $source_transaction_id = null,
        $source_transaction_reference = null
    )
    {
        $this->orderManagement->manage(
            $id,
            $reference,
            $order,
            $type,
            $amount,
            $currency,
            $status,
            $display_id,
            $source_transaction_id,
            $source_transaction_reference
        );
    }

    /**
     * @api
     * 
     * @return \Bolt\Boltpay\Api\Data\UpdateCartResultInterface
     */
    public function updateCart(
        $cart,
        $add_items = null,
        $remove_items = null,
        $discount_codes_to_add = null,
        $discount_codes_to_remove = null
    )
    {
        return $this->updateCart->execute(
            $cart,
            $add_items,
            $remove_items,
            $discount_codes_to_add,
            $discount_codes_to_remove
        );
    }
}