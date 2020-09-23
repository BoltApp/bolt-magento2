<?php

namespace Bolt\Custom\Helper\Boltpay;

/**
 * Plugin for {@see \Bolt\Boltpay\Helper\Order}
 */
class Order extends \Bolt\Boltpay\Helper\Order
{
    /**
     * Cancel and delete the order
     * Overriden because {@see \Aheadworks\Giftcard\Plugin\Model\Service\OrderServicePlugin::aroundCancel}
     * is plugged into {@see \Magento\Sales\Api\OrderManagementInterface::cancel} instead of
     * {@see \Magento\Sales\Model\Order::cancel} which we normally call
     *
     * @param \Magento\Sales\Model\Order $order to be deleted
     *
     * @throws \Exception if unable to delete order
     */
    protected function deleteOrder($order)
    {
        try {
            // use object manager just in case the underlying class constructor changes in the future
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            /** @var \Magento\Sales\Model\Service\OrderService $orderService */
            $orderService = $objectManager->create(\Magento\Sales\Model\Service\OrderService::class);
            $bugsnag = $objectManager->get(\Bolt\Boltpay\Helper\Bugsnag::class);
            $orderService->cancel($order->getId());
            $order->delete();
        } catch (\Exception $e) {
            $bugsnag->registerCallback(
                function ($report) use ($order) {
                    $report->setMetaData(
                        [
                            'DELETE ORDER' => [
                                'order increment ID' => $order->getIncrementId(),
                                'order entity ID'    => $order->getId(),
                            ]
                        ]
                    );
                }
            );
            $bugsnag->notifyException($e);
            $order->delete();
        }
    }

}