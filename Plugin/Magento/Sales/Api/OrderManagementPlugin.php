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
namespace Bolt\Boltpay\Plugin\Magento\Sales\Api;

use Magento\Sales\Api\OrderManagementInterface;
use \Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

class OrderManagementPlugin
{
    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;

    /**
     * OrderManagement constructor.
     * @param \Magento\Framework\App\RequestInterface $request
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->request = $request;
    }

    /**
     * Override Order place method.
     * Skip execution when we just created the order.
     * We call it manually later when bolt transaction is created.
     *
     * @param OrderManagementInterface $subject
     * @param callable $proceed
     * @return OrderInterface
     */
    public function aroundPlace(
        OrderManagementInterface $subject,
        callable $proceed,
        OrderInterface $order
    ): OrderInterface {
        $isRechargedOrder = $this->request->getParam('bolt-credit-cards');
        // Move on if the order payment method is not Bolt
        if (!$order->getPayment()
            || $order->getPayment()->getMethod() != \Bolt\Boltpay\Model\Payment::METHOD_CODE
            || $isRechargedOrder
        ) {
            return $proceed();
        }
        // Skip if the order did not reach Order::STATE_NEW state
        if (!$order->getState() || $order->getState() == Order::STATE_PENDING_PAYMENT) {
            return $order;
        }
        return $proceed();
    }
}
