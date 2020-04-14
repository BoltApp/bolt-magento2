<?php


namespace Bolt\Boltpay\Plugin;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as ResourceOrder;

class OrderStatusLogging
{

    /**
     * @var \Bolt\Boltpay\Helper\Bugsnag
     */
    private $bugsnag;

    public function __construct(
        \Bolt\Boltpay\Helper\Bugsnag $bugsnag
    ) {
        $this->bugsnag = $bugsnag;
    }

    /**
     * @param ResourceOrder                          $subject
     * @param                                        $result
     * @param \Magento\Framework\Model\AbstractModel $order
     *
     * @return mixed
     */
    public function afterSave(ResourceOrder $subject, $result, \Magento\Framework\Model\AbstractModel $order)
    {
        try {
            if (!$order->getPayment() || $order->getPayment()->getMethod() != \Bolt\Boltpay\Model\Payment::METHOD_CODE){
                return $result;
            }

            if ($order->getStatus() === Order::STATE_CANCELED || $order->getState() === Order::STATE_CANCELED){
                $this->bugsnag->notifyError(
                    "{$order->getIncrementId()} - Order status update",
                    "\nStatus: {$order->getStatus()}, State: {$order->getState()}"
                );
            }

            if ($order->getStatus() === Order::STATE_CLOSED || $order->getState() === Order::STATE_CLOSED){
                $this->bugsnag->notifyError(
                    "{$order->getIncrementId()} - Order status update",
                    "\nStatus: {$order->getStatus()}, State: {$order->getState()}"
                );
            }
            
        } catch (\Throwable $e) {
            $this->bugsnag->notifyError(
                "{$order->getIncrementId()} - Order status update - Error - {$e->getMessage()}",
                "\nStatus: {$order->getStatus()}, State: {$order->getState()} \n Exception Trace: {$e->getTraceAsString()}"
            );
        }

        return $result;
    }
}