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

namespace Bolt\Boltpay\Plugin\Webkul\Odoomagentoconnect\Model\ResourceModel;

use Bolt\Boltpay\Model\Payment;
use Magento\Sales\Model\Order;

/**
 * Plugin class for {@see \Webkul\Odoomagentoconnect\Model\ResourceModel\Order}
 */
class OrderPlugin
{

    /**
     * Plugin for {@see \Webkul\Odoomagentoconnect\Model\ResourceModel\Order::exportOrder}
     * prevents pending Bolt order from being exported to Odoo
     *
     * @param \Webkul\Odoomagentoconnect\Model\ResourceModel\Order $subject
     * @param callable                                             $proceed
     * @param Order                                                $thisOrder
     * @param false                                                $quote
     *
     * @return int Odoo order id from the intercepted method or 0 if prevented
     */
    public function aroundExportOrder(
        \Webkul\Odoomagentoconnect\Model\ResourceModel\Order $subject,
        callable $proceed,
        $thisOrder,
        $quote = false
    ) {
        if ($thisOrder->getPayment() &&
            $thisOrder->getPayment()->getMethod() == Payment::METHOD_CODE &&
            $thisOrder->getState() == Order::STATE_PENDING_PAYMENT
        ) {
            // return 0 which is equivalent to an error occurring in the original method
            return 0;
        }
        return $proceed($thisOrder, $quote);
    }
}
