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

namespace Bolt\Boltpay\Plugin\Magento\Sales\Controller\Adminhtml\Invoice;

use Magento\Sales\Controller\Adminhtml\Order\Invoice\Capture;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Bolt\Boltpay\Model\Payment;
use Magento\Sales\Api\InvoiceRepositoryInterface;

class CapturePlugin
{
    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    public function __construct(
        TransactionRepositoryInterface $transactionRepository,
        InvoiceRepositoryInterface $invoiceRepository
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * Closing auth transaction after invoice capture to sync bolt with default magento logic
     *
     * @param Capture $subject
     * @param $result
     * @return \Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\InputException
     */
    public function afterExecute(Capture $subject, $result)
    {
        $invoice = $this->invoiceRepository->get($subject->getRequest()->getParam('invoice_id'));
        $order = $invoice->getOrder();

        $payment = $order->getPayment();
        $transaction = $this->transactionRepository->getByTransactionType(
            \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH,
            $payment->getId()
        );

        if ($payment->getMethod() == Payment::METHOD_CODE && ($transaction && !$transaction->getIsClosed())) {
            $transaction->setIsClosed(1);
            $this->transactionRepository->save($transaction);
        }

        return $result;
    }
}
