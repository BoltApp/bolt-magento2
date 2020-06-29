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

use Magento\Sales\Model\Order;

/**
 * Class OrderSenderPlugin
 *
 * @package Bolt\Boltpay\Plugin
 */
class OrderPlugin
{
    /**
     * @var string|null
     */
    private $oldState;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;

    /**
     * OrderPlugin constructor.
     * @param \Magento\Framework\App\RequestInterface $request
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request
    ){
        $this->request = $request;
    }

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
        // Store the initial order state.
        // Used in conditionally calling Order::place just once
        // in the transition from Order::STATE_PENDING_PAYMENT to Order::STATE_NEW.
        $this->oldState = $subject->getState();

        if (!$subject->getPayment() || $subject->getPayment()->getMethod() != \Bolt\Boltpay\Model\Payment::METHOD_CODE) {
            return [$state];
        }

        if ($subject->getIsRechargedOrder()){
            // Check and set the recharged order state to processing
            return [Order::STATE_PROCESSING];
        }

        if ($state === \Bolt\Boltpay\Helper\Order::BOLT_ORDER_STATE_NEW) {
            $state = Order::STATE_NEW;
        } elseif (!$subject->getState() || $state === Order::STATE_NEW) {
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

        if ($subject->getIsRechargedOrder()) {
            // Check and set the recharged order state to processing
            return [Order::STATE_PROCESSING];
        }

        if ($status === \Bolt\Boltpay\Helper\Order::BOLT_ORDER_STATUS_PENDING) {
            $status = $subject->getConfig()->getStateDefaultStatus(Order::STATE_NEW);
        } elseif ((
            !  $subject->getStatus()
            || $subject->getStatus() == Order::STATE_PENDING_PAYMENT
            || $subject->getStatus() == Order::STATE_NEW
            ) && $status === \Bolt\Boltpay\Helper\Order::MAGENTO_ORDER_STATUS_PENDING
        ) {
            $status = $subject->getConfig()->getStateDefaultStatus(Order::STATE_PENDING_PAYMENT);
        }
        return [$status];
    }

    /**
     * Override Order place method.
     * Skip execution when we just created the order.
     * We call it manually later when bolt transaction is created.
     *
     * @param Order $subject
     * @param callable $proceed
     * @return Order
     */
    public function aroundPlace(Order $subject, callable $proceed)
    {
        $isRechargedOrder = $this->request->getParam('bolt-credit-cards');
        // Move on if the order payment method is not Bolt
        if (!$subject->getPayment() || $subject->getPayment()->getMethod() != \Bolt\Boltpay\Model\Payment::METHOD_CODE || $isRechargedOrder) {
            return $proceed();
        }
        // Skip if the order did not reach Order::STATE_NEW state
        if (!$subject->getState() || $subject->getState() == Order::STATE_PENDING_PAYMENT) {
            return $subject;
        }
        return $proceed();
    }

    /**
     * Prevent creating an order with an empty state / status.
     * Since we rip Order::place out of the context by going around it
     * first time it was called from QuoteManagement::submitQuote and
     * call it manually later, the order would be created with no
     * state / status set. They are ment to be set in the place call. As bypassing it
     * with the around plugin does not prevent the after plugin execution we
     * set the initial state here. We set the state to Order::STATE_NEW, as it
     * was supposed to be set in the original method. Our beforeSetState plugin
     * above then overrides it to Order::STATE_PENDING_PAYMENT if needed.
     *
     * @param Order $subject
     * @param Order $result
     * @return mixed
     */
    public function afterPlace($subject, $result)
    {
        // Skip if the order payment method is not Bolt
        if (!$subject->getPayment() || $subject->getPayment()->getMethod() != \Bolt\Boltpay\Model\Payment::METHOD_CODE) {
            return $result;
        }
        if (!$subject->getState()) {
            $subject->setState(Order::STATE_NEW);
            $subject->setStatus($subject->getConfig()->getStateDefaultStatus(Order::STATE_NEW));
        }
        return $result;
    }

    /**
     * Manually call Order::place after the transition is made to Order::STATE_NEW state.
     * Other parts of the Magento system expect this method to be called
     * at the beginning of the order lifecycle. However, as we create the order before the
     * authorization takes place, and may potentially cancel or delete it early on,
     * we suppress the internal Order::place call and call it ourselves after
     * the order is approved by Bolt.
     *
     * @param Order $subject
     * @param Order $result
     * @return array
     */
    public function afterSetState($subject, $result)
    {
        // Skip if the order payment method is not Bolt
        if (!$subject->getPayment() || $subject->getPayment()->getMethod() != \Bolt\Boltpay\Model\Payment::METHOD_CODE) {
            return $result;
        }
        // Place the order if it had just been moved to the Order::STATE_NEW state
        if ($result->getState() == Order::STATE_NEW && $this->oldState == Order::STATE_PENDING_PAYMENT) {
            $subject->place();
        }
        return $result;
    }
}
