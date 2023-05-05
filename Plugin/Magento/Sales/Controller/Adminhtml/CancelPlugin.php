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

namespace Bolt\Boltpay\Plugin\Magento\Sales\Controller\Adminhtml;

use Magento\Sales\Controller\Adminhtml\Order\Cancel;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Bolt\Boltpay\Model\Payment;

class CancelPlugin
{
    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;

    public function __construct(
        OrderHelper $orderHelper,
        TransactionRepositoryInterface $transactionRepository
    ) {
        $this->orderHelper = $orderHelper;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * Closing auth transaction before cancel of order to sync bolt with default magento logic
     *
     * @param Cancel $subject
     * @return void
     * @throws \Magento\Framework\Exception\InputException
     */
    public function beforeExecute(Cancel $subject)
    {
        $orderId = $subject->getRequest()->getParam('order_id');
        $order = $this->orderHelper->getOrderById($orderId);

        $payment = $order->getPayment();
        $transaction = $this->transactionRepository->getByTransactionType(
            \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH,
            $payment->getId()
        );

        if ($payment->getMethod() == Payment::METHOD_CODE && ($transaction && !$transaction->getIsClosed())) {
            $transaction->setIsClosed(1);
            $this->transactionRepository->save($transaction);
        }
    }
}
