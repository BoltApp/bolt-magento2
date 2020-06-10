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
use Bolt\Boltpay\Helper\Order as OrderHelper;

/**
 * Class OrderSenderPlugin
 *
 * @package Bolt\Boltpay\Plugin
 */
class OrderPlugin
{
    /**
     * Override Order place method.
     * Skip execution when we just created order
     * We call it manually later when bolt transaction it created
     *
     * @param Order $subject
     * @param callable $proceed
     * @return Order
     */
    public function aroundPlace(Order $subject, callable $proceed)
    {
        if (!$subject->getPayment() || $subject->getPayment()->getMethod() != \Bolt\Boltpay\Model\Payment::METHOD_CODE) {
            return $proceed();
        }
        if (is_null($subject->getState())) {
            $subject->setState(OrderHelper::BOLT_ORDER_STATUS_PENDING);
            $subject->setStatus(OrderHelper::BOLT_ORDER_STATUS_PENDING);
            return $subject;
        }
        return $proceed();
    }
}
