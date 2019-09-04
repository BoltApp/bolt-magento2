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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Exception\BoltException;

/**
 * Class OrderTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper
 */
class OrderTest extends TestCase
{
    const INCREMENT_ID = 1234;
    const QUOTE_ID = 5678;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $currentMock;

    /**
     * @var Order
     */
    private $orderMock;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    private function initCurrentMock()
    {
        $this->currentMock = $this->createPartialMock(
            OrderHelper::class,
            [
                'getExistingOrder',
                'deleteOrder'
            ]);
    }

    private function initRequiredMocks()
    {
        $this->orderMock = $this->createPartialMock(
            Order::class,
            [
                'getState'
            ]);
    }

    /**
     * @test
     */
    public function deleteOrderByIncrementId_noOrder()
    {
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn(null);
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(\Bolt\Boltpay\Model\Api\CreateOrder::E_BOLT_GENERAL_ERROR);
        $this->expectExceptionMessage(
            sprintf(
                'Order Delete Error. Order does not exist. Order #: %s Immutable Quote ID: %s',
                self::INCREMENT_ID,
                self::QUOTE_ID
            )
        );
        $this->currentMock->expects(static::never())->method('deleteOrder');
        $this->currentMock->deleteOrderByIncrementId(self::INCREMENT_ID." / ".self::QUOTE_ID);
    }

    /**
     * @test
     */
    public function deleteOrderByIncrementId_invalidState()
    {
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn($this->orderMock);
        $state = Order::STATE_NEW;
        $this->orderMock->expects(static::once())->method('getState')->willReturn($state);
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(\Bolt\Boltpay\Model\Api\CreateOrder::E_BOLT_GENERAL_ERROR);
        $this->expectExceptionMessage(
            sprintf(
                'Order Delete Error. Order is in invalid state. Order #: %s State: %s Immutable Quote ID: %s',
                self::INCREMENT_ID,
                $state,
                self::QUOTE_ID
            )
        );
        $this->currentMock->expects(static::never())->method('deleteOrder');
        $this->currentMock->deleteOrderByIncrementId(self::INCREMENT_ID." / ".self::QUOTE_ID);
    }

    /**
     * @test
     */
    public function deleteOrderByIncrementId_noError()
    {
        $this->currentMock->expects(static::once())->method('getExistingOrder')->with(self::INCREMENT_ID)
            ->willReturn($this->orderMock);
        $state = Order::STATE_PENDING_PAYMENT;
        $this->orderMock->expects(static::once())->method('getState')->willReturn($state);
        $this->currentMock->expects(static::once())->method('deleteOrder')->with($this->orderMock);
        $this->currentMock->deleteOrderByIncrementId(self::INCREMENT_ID." / ".self::QUOTE_ID);
    }
}
