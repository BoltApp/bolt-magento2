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
 * @copyright  Copyright (c) 2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Plugin\Magento\CustomerBalance\Observer;

use Bolt\Boltpay\Model\Payment;
use Magento\CustomerBalance\Observer\RevertStoreCreditForOrder;
use Magento\Sales\Model\Order;
use Bolt\Boltpay\Helper\Config;

/**
 * Fix multiple reverts of magento store credit for order
 *
 * It is caused because magento Customer_Balance module subscribes to two events:
 * - sales_model_service_quote_submit_failure
 * - order_cancel_after
 *
 * It doesn't expect that this two events can be dispatched for the same order.
 * Because in default flow when sales_model_service_quote_submit_failure is dispatched order is not created yet and cancel event won't be called.
 * In bolt flow we create order first and then make payment authorization.
 *
 * Therefore, in failed payment authorization case we need call both events in <route url="/V1/bolt/boltpay/orders/:id" method="DELETE">.
 * - sales_model_service_quote_submit_failure - to revert quote inventory, coupons, some 3-th party logic.
 * - order_cancel_after - to revert inventory, coupons, store credit, etc.
 * Which leads to multiple reverts of store credit for order. In this plugin we prevent multiple reverts of store credit for order.
 */
class RevertStoreCreditForOrderPlugin
{
    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @param Config $configHelper
     */
    public function __construct(Config $configHelper)
    {
        $this->configHelper = $configHelper;
    }

    /**
     * Prevents multiple reverts of store credit for order
     *
     * @param RevertStoreCreditForOrder $subject
     * @param callable $proceed
     * @param Order $order
     * @return RevertStoreCreditForOrder
     */
    public function aroundExecute($subject, callable $proceed, Order $order)
    {
        if ($order->getPayment()->getMethod() !== Payment::METHOD_CODE || !$this->configHelper->isActive()) {
            return $proceed($order);
        }

        if (!$order->getIsStoreCreditReverted()) {
            $order->setIsStoreCreditReverted(true);
            return $proceed($order);
        }

        return $subject;
    }
}

