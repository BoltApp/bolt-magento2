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
 
namespace Bolt\Boltpay\Model\Service;

use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Sales\Api\Data\OrderInterface;

class InvoiceService extends \Magento\Sales\Model\Service\InvoiceService
{
    /**
     * Prepare order invoice without any items
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param                                        $amount
     *
     * @return \Magento\Sales\Model\Order\Invoice
     * @throws \Exception
     */
    public function prepareInvoiceWithoutItems(OrderInterface $order, $amount)
    {
        $invoice = $this->orderConverter->toInvoice($order);
        $invoice->setBaseGrandTotal($amount);
        $invoice->setSubtotal($amount);
        $invoice->setBaseSubtotal($amount);
        $invoice->setGrandTotal($amount);

        $order->getInvoiceCollection()->addItem($invoice);

        return $invoice;
    }
}
