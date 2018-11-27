<?php
namespace Bolt\Boltpay\Model\Service;

use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Sales\Api\Data\OrderInterface;

class InvoiceService extends \Magento\Sales\Model\Service\InvoiceService
{
    /**
     * @var \Bolt\Boltpay\Helper\Bugsnag
     */
    protected $bugsnag;

    /**
     * Constructor
     *
     * @param \Magento\Sales\Api\InvoiceRepositoryInterface        $repository
     * @param \Magento\Sales\Api\InvoiceCommentRepositoryInterface $commentRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder         $criteriaBuilder
     * @param \Magento\Framework\Api\FilterBuilder                 $filterBuilder
     * @param \Magento\Sales\Model\Order\InvoiceNotifier           $notifier
     * @param \Magento\Sales\Api\OrderRepositoryInterface          $orderRepository
     * @param \Magento\Sales\Model\Convert\Order                   $orderConverter
     * @param \Bolt\Boltpay\Helper\Bugsnag                         $bugsnag
     */
    public function __construct(
        \Magento\Sales\Api\InvoiceRepositoryInterface $repository,
        \Magento\Sales\Api\InvoiceCommentRepositoryInterface $commentRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $criteriaBuilder,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Sales\Model\Order\InvoiceNotifier $notifier,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Convert\Order $orderConverter,
        Bugsnag $bugsnag
    ) {
        parent::__construct($repository, $commentRepository, $criteriaBuilder, $filterBuilder, $notifier, $orderRepository, $orderConverter);
        $this->bugsnag = $bugsnag;
    }

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
        try {
            $invoice = $this->orderConverter->toInvoice($order);
            $invoice->setBaseGrandTotal($amount);
            $invoice->setSubtotal($amount);
            $invoice->setBaseSubtotal($amount);
            $invoice->setGrandTotal($amount);

            $order->getInvoiceCollection()->addItem($invoice);

            return $invoice;
        } catch(\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }
    }
}
