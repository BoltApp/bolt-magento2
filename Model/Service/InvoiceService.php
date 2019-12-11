<?php
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
