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
namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Bolt\Boltpay\Model\Api\OrderTransactions;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Repository;

/**
 * Class OrderTransactionsTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\OrderTransactions
 */
class OrderTransactionsTest extends BoltTestCase
{
    const TXN_ID = 'test_txt_id';

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var OrderTransactions
     */
    private $orderTransactions;

    /**
     * @inheritdoc
     */
    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->orderTransactions = $this->objectManager->create(OrderTransactions::class);
    }


    /**
     * @test
     */
    public function testOrderTransactionSuccessCreation()
    {
        $order = TestUtils::createDumpyOrder();
        $transactionObj = $this->objectManager->create(Transaction::class);
        $transactionObj->setData(
            [
                'order_id' => $order->getId(),
                'payment_id' => $order->getPayment()->getId(),
                'txn_id' => self::TXN_ID,
                'parent_txn_id' => 'TA9EsNAYR6Zrf-auth',
                'txn_type' => 'capture',
                'is_closed' => 1
            ]
        );
        $transactionId = $this->orderTransactions->execute(
            $transactionObj,
            [
                'testKey' => 'testData'
            ]
        );
        $orderTransactionRepository = $this->objectManager->get(Repository::class);
        $transaction = $orderTransactionRepository->get($transactionId);
        $this->assertEquals(self::TXN_ID, $transaction->getTxnId());
    }
}
