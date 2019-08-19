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
     * @param Order $subject
     * @param string $state
     * @return array
     */
    public function beforeSetState(Order $subject, $state)
    {
        if ($state === Order::STATE_NEW) {
            $state = Order::STATE_PENDING_PAYMENT;
        }
        return [$state];
    }

    /**
     * @param Order $subject
     * @param string $status
     * @return array
     */
    public function beforeSetStatus(Order $subject, $status)
    {
        if (!$subject->getState() || $subject->getState() === Order::STATE_PENDING_PAYMENT) {
            $status = $subject->getConfig()->getStateDefaultStatus(Order::STATE_PENDING_PAYMENT);
        }
        return [$status];
    }
}
