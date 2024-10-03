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
 *
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Observer\Adminhtml\Sales;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Service\InvoiceService;
use Bolt\Boltpay\Helper\Order as OrderHelper;

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
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param Bugsnag $bugsnag
     * @param OrderHelper $orderHelper
     */
    public function __construct(InvoiceService $invoiceService, InvoiceSender $invoiceSender, Bugsnag $bugsnag, OrderHelper $orderHelper)
    {
        $this->bugsnag = $bugsnag;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->orderHelper = $orderHelper;
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

                $msg = __('BOLTPAY INFO :: Invoice created: #%1', $invoice->getIncrementId());
                $order->addStatusHistoryComment($msg);
                if ($transaction = $order->getRechargedTransaction()) {
                    $amount  = $order->formatPrice(CurrencyUtils::toMajor($transaction->amount->amount, $transaction->amount->currency));
                    $msg = __('BOLTPAY INFO :: Payment status: %1 Amount %2 <br /> Bolt transaction: %3', strtoupper($transaction->status), $amount, $this->orderHelper->formatReferenceUrl($transaction->reference));
                    $order->addStatusHistoryComment($msg);
                }
                //Add notification comment to order
                $order->setIsCustomerNotified(true);
                $order->save();
            }
            return $this;
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }
}
