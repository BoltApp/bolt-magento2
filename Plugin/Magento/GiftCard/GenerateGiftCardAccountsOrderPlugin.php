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
namespace Bolt\Boltpay\Plugin\Magento\GiftCard;

use Bolt\Boltpay\Model\Payment as BoltPayment;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;

class GenerateGiftCardAccountsOrderPlugin
{
    public function aroundExecute(
        $subject,
        callable $proceed,
        Observer $observer
    ) {
        $event = $observer->getEvent();
        /** @var Order $order */
        $order =  $event->getOrder();
        if ($order->getPayment()->getMethod() === BoltPayment::METHOD_CODE &&
            in_array($order->getStatus(), [Order::STATE_PENDING_PAYMENT, Order::STATE_CANCELED])) {
            return;
        }
        return $proceed($observer);
    }
}
