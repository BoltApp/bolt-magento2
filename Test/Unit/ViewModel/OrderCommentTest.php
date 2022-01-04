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

namespace Bolt\Boltpay\Test\Unit\ViewModel;

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Model\FeatureSwitch;
use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\ViewModel\OrderComment;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Store\Model\StoreManagerInterface;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Store\Model\ScopeInterface;

/**
 * Class OrderCommentTest
 *
 * @coversDefaultClass \Bolt\Boltpay\ViewModel\OrderComment
 */
class OrderCommentTest extends BoltTestCase
{
    /**
     * @var Config
     */
    protected $configHelper;

    /**
     * @var Decider
     */
    protected $featureSwitches;

    /**
     * @var OrderComment
     */
    protected $orderComment;

    /**
     * @var Order
     */
    private $order;

    private $objectManager;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->orderComment = $this->objectManager->create(OrderComment::class);
        $this->order = $this->objectManager->create(Order::class);
        $this->featureSwitches = $this->objectManager->create(Decider::class);
        $this->configHelper = $this->objectManager->create(ConfigHelper::class);
    }

    /**
     * @test
     * that constructor will populate the expected properties with the provided arguments
     *
     * @return void
     */
    public function __construct_always_populatesInternalProperties()
    {
        $instance = new OrderComment($this->configHelper, $this->featureSwitches);
        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
        static::assertAttributeEquals($this->featureSwitches, 'featureSwitches', $instance);
    }

    /**
     * @test
     * that getCommentForOrder returns order data stored under the field configured to be used for comment storage
     * @see \Bolt\Boltpay\Helper\Config::getOrderCommentField
     */
    public function getCommentForOrder_withCommentFieldSet_returnsOrderCommentFromTheConfiguredField()
    {
        $storeId = $this->objectManager->get(StoreManagerInterface::class)->getStore()->getId();
        $this->order->setStoreId($storeId);
        $this->order->setData('user_note', 'test note');
        $configData = [
            [
                'path' => ConfigHelper::XML_PATH_ORDER_COMMENT_FIELD,
                'value' => 'user_note',
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeId' => $storeId
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        static::assertEquals('test note', $this->orderComment->getCommentForOrder($this->order));
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
        TestUtils::saveFeatureSwitch(
            \Bolt\Boltpay\Helper\FeatureSwitch\Definitions::M2_SHOW_ORDER_COMMENT_IN_ADMIN,
            $isShowOrderCommentInAdmin
        );
        $storeId = $this->objectManager->get(StoreManagerInterface::class)->getStore()->getId();
        $this->order->setStoreId($storeId);
        $this->order->setData('user_note', $orderComment);
        $configData = [
            [
                'path' => ConfigHelper::XML_PATH_ORDER_COMMENT_FIELD,
                'value' => 'user_note',
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeId' => $storeId
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $payment = $this->objectManager->create(Order\Payment::class);
        $payment->setMethod($paymentMethod);

        $this->order->setPayment($payment);
        static::assertEquals($shouldDisplay, $this->orderComment->shouldDisplayForOrder($this->order));
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
}
