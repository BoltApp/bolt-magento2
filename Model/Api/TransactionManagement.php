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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api;

use \Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use Magento\Sales\Model\Order\Payment\Transaction\Repository as TransactionRepository;
use Magento\Sales\Model\Order\Payment\Repository as PaymentRepository;
use \Magento\Sales\Model\OrderRepository;

class TransactionManagement implements \Bolt\Boltpay\Api\TransactionManagementInterface
{
    /**
     * @var \Bolt\Boltpay\Helper\Cart
     */
    private $cartHelper;

    /**
     * @var TransactionBuilder
     */
    private $builder;

    /**
     * @var TransactionRepository
     */
    private $transactionRepository;

    /**
     * @var PaymentRepository
     */
    private $paymentRepository;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    public function __construct(
        CartHelper $cartHelper,
        TransactionBuilder $builder,
        TransactionRepository $transactionRepository,
        PaymentRepository $paymentRepository,
        OrderRepository $orderRepository
    ) {
        $this->cartHelper = $cartHelper;
        $this->builder = $builder;
        $this->transactionRepository = $transactionRepository;
        $this->paymentRepository = $paymentRepository;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param string $incrementId
     * @param string $transactionState
     * @param string $transactionId
     * @param string $parentTransactionId
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\InputException
     */
    public function execute($incrementId, $transactionState, $transactionId, $parentTransactionId)
    {
        $order = $this->cartHelper->getOrderByIncrementId($incrementId);
        if (!$order) {
            throw new NoSuchEntityException(
                __('Could not find the order data.')
            );
        }

        $orderPayment = $order->getPayment();

        $paymentAuthorized = (bool)$orderPayment->getAdditionalInformation('authorized');

        $paymentData = [
            'transaction_state' => $transactionState,
            'authorized' => $paymentAuthorized || in_array($transactionState, [Transaction::TYPE_AUTH, Transaction::TYPE_CAPTURE]),
        ];

        $orderPayment->setParentTransactionId($parentTransactionId);
        $orderPayment->setTransactionId($transactionId);
        $orderPayment->setLastTransId($transactionId);
        $orderPayment->setIsTransactionClosed($transactionState != Transaction::TYPE_AUTH);
        $orderPayment->setAdditionalInformation(array_merge((array)$orderPayment->getAdditionalInformation(), $paymentData));


        $paymentTransaction = $this->builder->setPayment($orderPayment)
            ->setOrder($order)
            ->setTransactionId($transactionId)
            ->setFailSafe(true)
            ->build($transactionState);

        $this->transactionRepository->save($paymentTransaction);
        $this->paymentRepository->save($orderPayment);
        $this->orderRepository->save($order);
    }
}
