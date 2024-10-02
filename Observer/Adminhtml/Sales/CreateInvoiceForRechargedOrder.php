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
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Service\InvoiceService;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;

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
     * @var Decider
     */
    private $featureSwitches;

    /**
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param Bugsnag $bugsnag
     * @param Decider $featureSwitches
     */
    public function __construct(InvoiceService $invoiceService, InvoiceSender $invoiceSender, Bugsnag $bugsnag, Decider $featureSwitches)
    {
        $this->bugsnag = $bugsnag;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->featureSwitches = $featureSwitches;
    }

    public function execute(Observer $observer)
    {
        try {
            if ($this->featureSwitches->isAPIDrivenIntegrationEnabled()) {
                return $this;
            }

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
