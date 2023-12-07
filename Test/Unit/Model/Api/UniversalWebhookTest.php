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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Model\Api\UniversalWebhook;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\TestFramework\Helper\Bootstrap;
use Bolt\Boltpay\Api\OrderManagementInterface;
use Bolt\Boltpay\Exception\BoltException;

class UniversalWebhookTest extends BoltTestCase
{
    /**
     * @var UniversalWebhook
     */
    private $universalWebhook;

    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    private $objectManager;

    /**
     * @inheritdoc
     */
    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->universalWebhook = $this->objectManager->create(UniversalWebhook::class);
        $this->orderManagement = $this->objectManager->create(OrderManagementInterface::class);
    }

    /**
     * @test
     */
    public function updateOrder_returnsTrue()
    {
        $type = 'testType';
        $object = 'transaction';
        $data = [
            'id' => '1234',
            'reference' => 'XXXX-XXXX-XXXX',
            'order' => [
                'cart' => [
                    'order_reference' => '5678',
                    'display_id' => '000001234'
                ]
            ],
            'amount' => [
                'amount' => '2233',
                'currency' => 'USD'
            ],
            'status' => 'completed',
            'source_transaction' => [
                'id' => 'string',
                'reference' => 'otherstring'
            ]
        ];
        $orderManagement = $this->createMock(OrderManagementInterface::class);
        $orderManagement->method('manage');
        TestHelper::setProperty($this->universalWebhook, 'orderManagement', $orderManagement);
        $this->assertTrue($this->universalWebhook->execute($type, $object, $data));
        $response = json_decode(TestHelper::getProperty($this->universalWebhook, 'response')->getBody(), true);
        $this->assertEquals(['status' => 'success'], $response);
    }

    /**
     * @test
     */
    public function updateOrder_returnsFalse()
    {
        $type = 'testType';
        $object = 'transaction';
        $data = [
            'id' => '1234',
            'reference' => 'XXXX-XXXX-XXXX',
            'order' => [
                'cart' => [
                    'order_reference' => '5678',
                    'display_id' => '000001234'
                ]
            ],
            'amount' => [
                'amount' => '2233',
                'currency' => 'USD'
            ],
            'status' => 'completed',
            'source_transaction' => [
                'id' => 'string',
                'reference' => 'otherstring'
            ]
        ];
        $orderManagement = $this->createMock(OrderManagementInterface::class);
        $boltException =  new BoltException(__('The cart has products not allowed for Bolt checkout'));
        $orderManagement->method('manage')->willThrowException($boltException);
        TestHelper::setProperty($this->universalWebhook, 'orderManagement', $orderManagement);
        $this->assertFalse($this->universalWebhook->execute($type, $object, $data));
        $response = json_decode(TestHelper::getProperty($this->universalWebhook, 'response')->getBody(), true);
        $errResponse = [
            'status' => 'failure',
            'error' => [
                'code' => $boltException->getCode(),
                'message' => $boltException->getMessage(),
            ],
        ];
        $this->assertEquals($errResponse, $response);
    }
}
