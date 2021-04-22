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
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\ViewModel\OrderComment;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class OrderCommentTest
 *
 * @coversDefaultClass \Bolt\Boltpay\ViewModel\OrderComment
 */
class OrderCommentTest extends BoltTestCase
{
    /**
     * Test store id
     */
    const STORE_ID = 1;

    /**
     * @var MockObject|Config
     */
    protected $configHelperMock;

    /**
     * @var MockObject|Decider
     */
    protected $featureSwitchesMock;

    /**
     * @var MockObject|OrderComment
     */
    protected $currentMock;

    /**
     * @var MockObject|Order
     */
    private $orderMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        $this->configHelperMock = $this->createMock(Config::class);
        $this->featureSwitchesMock = $this->createMock(Decider::class);
        $this->paymentMock = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $this->orderMock = $this->createMock(Order::class);
        $this->initCurrentMock(null);
    }

    /**
     * @test
     * that constructor will populate the expected properties with the provided arguments
     *
     * @return void
     */
    public function __construct_always_populatesInternalProperties()
    {
        $instance = new OrderComment($this->configHelperMock, $this->featureSwitchesMock);
        static::assertAttributeEquals($this->configHelperMock, 'configHelper', $instance);
        static::assertAttributeEquals($this->featureSwitchesMock, 'featureSwitches', $instance);
    }

    /**
     * @test
     * that getCommentForOrder returns order data stored under the field configured to be used for comment storage
     * @see \Bolt\Boltpay\Helper\Config::getOrderCommentField
     */
    public function getCommentForOrder_withCommentFieldSet_returnsOrderCommentFromTheConfiguredField()
    {
        $this->orderMock = $this->createMock(Order::class);
        $this->orderMock->expects(static::once())->method('getStoreId')->willReturn(self::STORE_ID);
        $this->orderMock->expects(static::once())->method('getData')->with('user_note')->willReturn('test note');
        $this->configHelperMock->expects(static::once())->method('getOrderCommentField')->with(self::STORE_ID)
            ->willReturn('user_note');
        static::assertEquals('test note', $this->currentMock->getCommentForOrder($this->orderMock));
    }

    /**
     * @test
     * that shouldDisplayForOrder determines whether the order comment block should be displayed
     * only returns true if :
     * 1. M2_SHOW_ORDER_COMMENT_IN_ADMIN feature switch is enabled
     * 2. Order comment field is not empty
     * 3. Order payment method is Bolt
     * @dataProvider shouldDisplayForOrder_withVariousStatesProvider
     * @covers ::shouldDisplayForOrder
     *
     * @return void
     */
    public function shouldDisplayForOrder_withVariousStates_determinesIfCommentBlockShouldBeDisplayed(
        $isShowOrderCommentInAdmin,
        $orderComment,
        $paymentMethod,
        $shouldDisplay
    ) {
        $this->initCurrentMock(['getCommentForOrder']);
        $this->featureSwitchesMock->method('isShowOrderCommentInAdmin')->willReturn($isShowOrderCommentInAdmin);
        $this->currentMock->method('getCommentForOrder')->with($this->orderMock)->willReturn($orderComment);
        $this->orderMock->method('getPayment')->willReturn($paymentMethod ? $this->paymentMock : null);
        $this->paymentMock->method('getMethod')->willReturn($paymentMethod);
        static::assertEquals($shouldDisplay, $this->currentMock->shouldDisplayForOrder($this->orderMock));
    }

    /**
     * Data provider for {@see shouldDisplayForOrder_withVariousStates_determinesIfCommentBlockShouldBeDisplayed}
     *
     * @return array
     */
    public function shouldDisplayForOrder_withVariousStatesProvider()
    {
        return [
            [
                'isShowOrderCommentInAdmin' => true,
                'orderComment'              => 'Test comment',
                'paymentMethod'             => Payment::METHOD_CODE,
                'shouldDisplay'             => true
            ],
            [
                'isShowOrderCommentInAdmin' => false,
                'orderComment'              => 'Test comment',
                'paymentMethod'             => Payment::METHOD_CODE,
                'shouldDisplay'             => false
            ],
            [
                'isShowOrderCommentInAdmin' => true,
                'orderComment'              => null,
                'paymentMethod'             => Payment::METHOD_CODE,
                'shouldDisplay'             => false
            ],
            [
                'isShowOrderCommentInAdmin' => true,
                'orderComment'              => 'Test comment',
                'paymentMethod'             => null,
                'shouldDisplay'             => false
            ],
            [
                'isShowOrderCommentInAdmin' => true,
                'orderComment'              => 'Test comment',
                'paymentMethod'             => 'checkmo',
                'shouldDisplay'             => false
            ],
        ];
    }

    /**
     * Initializes {@see $currentMock} with stubbing the specified methods
     *
     * @param array|null $methods to be stubbed
     *
     * @return void
     */
    private function initCurrentMock($methods): void
    {
        $this->currentMock = $this->getMockBuilder(OrderComment::class)
            ->setMethods($methods)
            ->setConstructorArgs([$this->configHelperMock, $this->featureSwitchesMock])
            ->getMock();
    }
}
