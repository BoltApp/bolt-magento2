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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Plugin;

use Magento\Sales\Model\Order;

/**
 * Class OrderSenderPlugin
 *
 * @package Bolt\Boltpay\Plugin
 */
class OrderPlugin
{
    /**
     * Override the default "new" order state with the "pending_payment"
     * unless the special "bolt_new" state is received in which case state "new" is used.
     *
     * @param Order $subject
     * @param string $state
     * @return array
     */
    public function beforeSetState(Order $subject, $state)
    {
        if (!$subject->getPayment() || $subject->getPayment()->getMethod() != \Bolt\Boltpay\Model\Payment::METHOD_CODE) {
            return [$state];
        }
        if ($state === \Bolt\Boltpay\Helper\Order::BOLT_ORDER_STATE_NEW) {
            $state = Order::STATE_NEW;
        }
        elseif (!$subject->getState() || $state === Order::STATE_NEW) {
            $state = Order::STATE_PENDING_PAYMENT;
        }
        return [$state];
    }

    /**
     * Override the default "pending" order status with the "pending_payment"
     * unless the special "bolt_pending" status is received in which case status "pending" is used.
     *
     * @param Order $subject
     * @param string $status
     * @return array
     */
    public function beforeSetStatus(Order $subject, $status)
    {
        if (!$subject->getPayment() || $subject->getPayment()->getMethod() != \Bolt\Boltpay\Model\Payment::METHOD_CODE) {
            return [$status];
        }
        if ($status === \Bolt\Boltpay\Helper\Order::BOLT_ORDER_STATUS_PENDING) {
            $status = $subject->getConfig()->getStateDefaultStatus(Order::STATE_NEW);
        }
        elseif ((
            !  $subject->getStatus()
            || $subject->getStatus() == Order::STATE_PENDING_PAYMENT
            || $subject->getStatus() == Order::STATE_NEW
            ) && $status === \Bolt\Boltpay\Helper\Order::MAGENTO_ORDER_STATUS_PENDING
        ) {
            $status = $subject->getConfig()->getStateDefaultStatus(Order::STATE_PENDING_PAYMENT);
        }
        return [$status];
    }
}
