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

namespace Bolt\Boltpay\Plugin\ImaginationMedia\TmwGiftCard;

use Bolt\Boltpay\Helper\Hook;

/**
 * Class ForceInvoicePlugin
 * @package Bolt\Boltpay\Plugin\ImaginationMedia\TmwGiftCard
 */
class ForceInvoicePlugin
{
    /**
     * When the pending hook is sent to Magento, the $subject observer would try to create an online invoice for the order
     * if it has a virtual gift card and because the Bolt transaction is not yet authorized, an error of
     * “Transaction is not in authorized state.”  would be thrown.
     *
     * We don't need the logic in the $subject observer as our payment/capture hook will generate an offline invoice later.
     * This would bypass the unneeded logic from the $subject observer.
     *
     * @param \ImaginationMedia\TmwGiftCard\Observer\ForceInvoice $subject
     * @param callable $proceed
     * @param $observer
     */
    public function aroundExecute(
        \ImaginationMedia\TmwGiftCard\Observer\ForceInvoice $subject,
        callable $proceed,
        $observer
    ) {
        $payment = $observer->getEvent()->getPayment();
        $order = $payment->getOrder();
        if ($order && Hook::$fromBolt && $payment && $payment->getMethod() === \Bolt\Boltpay\Model\Payment::METHOD_CODE) {
            return;
        }

        $proceed($observer);
    }
}
