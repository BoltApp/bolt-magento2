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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Plugin;

use Bolt\Boltpay\Model\Payment;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

/**
 * Class OrderSenderPlugin
 *
 * @package Bolt\Boltpay\Plugin
 */
class OrderSenderPlugin
{
    /**
     * Override OrderSender send method.
     * Skip sending order confirmation email until the order payment is approved - order in STATE_PROCESSING or beyond.
     *
     * @param Order $order
     * @param bool $forceSyncMode
     * @return bool
     */
    public function aroundSend(
        OrderSender $subject,
        callable $proceed,
        Order $order,
        $forceSyncMode = false
    ) {
        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethod();

        if ($paymentMethod == Payment::METHOD_CODE &&
            in_array(
                $order->getState(),
                [
                    Order::STATE_PENDING_PAYMENT,
                    Order::STATE_NEW,
                    Order::STATE_CANCELED,
                    Order::STATE_PAYMENT_REVIEW,
                    Order::STATE_HOLDED
                ]
            )
        ) {
            return false;
        }

        return $proceed($order, $forceSyncMode);
    }
}
