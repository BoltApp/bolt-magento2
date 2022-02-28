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

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction\Repository;

/**
 * Class OrderTransactionsTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\OrderTransactions
 */
class OrderTransactionsTest extends WebapiAbstract
{
    const RESOURCE_PATH = '/V1/bolt/boltpay/transactions';

    const SERVICE_READ_NAME = 'boltpayOrderTransactionsV1';

    const SERVICE_VERSION = 'V1';

    const TXN_ID = 'test_txt_id';

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @magentoApiDataFixture Magento/Sales/_files/order.php
     */
    public function testOrderTransactionSuccessCreation()
    {
        $order = $this->objectManager->create(Order::class)
            ->loadByIncrementId('100000001');

        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH,
                'httpMethod' => RestRequest::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'execute',
            ],
        ];

        $requestData = [
            'transaction' => [
                'order_id' => $order->getId(),
                'payment_id' => $order->getPayment()->getId(),
                'txn_id' => self::TXN_ID,
                'parent_txn_id' => 'TA9EsNAYR6Zrf-auth',
                'txn_type' => 'capture',
                'is_closed' => 1
            ],
            'additional_information' => [
                'testKey' => 'testData'
            ]
        ];

        $transactionId = $this->_webApiCall($serviceInfo, $requestData);
        /**
         * @var Repository $orderTransactionRepository
         */
        $orderTransactionRepository = $this->objectManager->get(Repository::class);
        $transaction = $orderTransactionRepository->get($transactionId);
        $this->assertEquals(self::TXN_ID, $transaction->getTxnId());
    }
}
