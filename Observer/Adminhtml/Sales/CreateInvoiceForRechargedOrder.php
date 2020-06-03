<?php

namespace Bolt\Boltpay\Observer\Adminhtml\Sales;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * Class CreateInvoiceForRechargedOrder
 *
 * @package Bolt\Boltpay\Observer\Adminhtml\Sales
 */
class CreateInvoiceForRechargedOrder implements ObserverInterface
{

    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var InvoiceSender
     */
    private $invoiceSender;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * CreateInvoiceForRechargedOrder constructor.
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        Bugsnag $bugsnag
    )
    {
        $this->bugsnag = $bugsnag;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
    }

    public function execute(Observer $observer)
    {
        try {
            $order = $observer->getEvent()->getOrder();
            $isRechargedOrder = $order->getIsRechargedOrder();
            if ($isRechargedOrder && $order->canInvoice()) {
                $invoice = $this->invoiceService
                    ->prepareInvoice($order)
                    ->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE)
                    ->register()
                    ->save();
                $order->addRelatedObject($invoice);
                if (!$invoice->getEmailSent()) {
                    $this->invoiceSender->send($invoice);
                }
                //Add notification comment to order
                $order->addStatusHistoryComment(
                    __('Invoice #%1 is created. Notification email is sent to customer.', $invoice->getId())
                )->setIsCustomerNotified(true);

            }
            return $this;
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }
}
