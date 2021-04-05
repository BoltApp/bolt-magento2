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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\ViewModel;

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\ViewModel\OrderComment;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class OrderCommentTest
 *
 * @package Bolt\Boltpay\Test\Unit\ViewModel
 */
class OrderCommentTest extends BoltTestCase
{
    const STORE_ID = 1;

    /**
     * @var MockObject|Config
     */
    protected $configHelperMock;

    /**
     * @var MockObject|OrderComment
     */
    protected $currentMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        $this->configHelperMock = $this->createMock(Config::class);
        $this->currentMock = $this->getMockBuilder(OrderComment::class)
            ->setMethods(null)
            ->setConstructorArgs([$this->configHelperMock])
            ->getMock();
    }

    /**
     * @test
     * that constructor will populate the expected properties with the provided arguments
     */
    public function __construct_always_populatesInternalProperties()
    {
        $instance = new OrderComment($this->configHelperMock);
        static::assertAttributeEquals($this->configHelperMock, 'configHelper', $instance);
    }

    /**
     * @test
     * that getCommentForOrder returns order data stored under the field configured to be used for comment storage
     * @see \Bolt\Boltpay\Helper\Config::getOrderCommentField
     */
    public function getCommentForOrder_withCommentFieldSet_returnsOrderCommentFromTheConfiguredField()
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->expects(static::once())->method('getStoreId')->willReturn(self::STORE_ID);
        $orderMock->expects(static::once())->method('getData')->with('user_note')->willReturn('test note');
        $this->configHelperMock->expects(static::once())->method('getOrderCommentField')->with(self::STORE_ID)
            ->willReturn('user_note');
        static::assertEquals('test note', $this->currentMock->getCommentForOrder($orderMock));
    }
}
