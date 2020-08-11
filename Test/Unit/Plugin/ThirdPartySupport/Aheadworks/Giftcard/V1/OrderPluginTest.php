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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Plugin\ThirdPartySupport\Aheadworks\Giftcard\V1;

use Bolt\Boltpay\Plugin\ThirdPartySupport\Aheadworks\Giftcard\V1\OrderPlugin;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\ThirdPartySupport\Aheadworks\Giftcard\V1\OrderPlugin
 */
class OrderPluginTest extends TestCase
{
    /** @var int Test order ID */
    const ORDER_ID = 10001;

    /**
     * @var \Bolt\Boltpay\Plugin\ThirdPartySupport\CommonModuleContext|MockObject
     */
    private $contextMock;

    /**
     * @var \Bolt\Boltpay\Model\ThirdPartyModuleFactory|MockObject
     */
    private $aheadworksGiftcardOrderServicePluginFactoryMock;

    /**
     * @var \Aheadworks\Giftcard\Api\GiftcardCartManagementInterface|MockObject
     */
    private $aheadworksGiftcardOrderServicePluginMock;

    /**
     * @var \Bolt\Boltpay\Helper\Order|MockObject
     */
    private $subjectMock;

    /**
     * @var \Magento\Sales\Model\Service\OrderService|MockObject
     */
    private $orderServiceMock;

    /**
     * @var OrderPlugin|MockObject
     */
    private $currentMock;

    /**
     * @var \Magento\Sales\Model\Order|MockObject
     */
    private $orderMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->contextMock = $this->createMock(\Bolt\Boltpay\Plugin\ThirdPartySupport\CommonModuleContext::class);
        $this->aheadworksGiftcardOrderServicePluginFactoryMock = $this->createMock(
            \Bolt\Boltpay\Model\ThirdPartyModuleFactory::class
        );
        $this->aheadworksGiftcardOrderServicePluginMock = $this->getMockBuilder(
            '\Aheadworks\Giftcard\Plugin\Model\Service\OrderServicePlugin'
        )
            ->disableOriginalClone()
            ->disableOriginalConstructor()
            ->disableProxyingToOriginalMethods()
            ->setMethods(['afterPlace', 'aroundCancel'])
            ->getMock();
        $this->subjectMock = $this->createMock(\Bolt\Boltpay\Helper\Order::class);
        $this->orderServiceMock = $this->createMock(\Magento\Sales\Model\Service\OrderService::class);
        $this->currentMock = $this->getMockBuilder(OrderPlugin::class)
            ->setMethods(['shouldRun'])
            ->setConstructorArgs(
                [$this->contextMock, $this->aheadworksGiftcardOrderServicePluginFactoryMock, $this->orderServiceMock]
            )
            ->getMock();
        $this->orderMock = $this->createMock(\Magento\Sales\Model\Order::class);
        $this->orderMock->method('getId')->willReturn(self::ORDER_ID);
    }

    /**
     * @test
     * that __construct sets internal properties
     *
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperties()
    {
        $instance = new OrderPlugin(
            $this->contextMock,
            $this->aheadworksGiftcardOrderServicePluginFactoryMock,
            $this->orderServiceMock
        );
        static::assertAttributeEquals(
            $this->aheadworksGiftcardOrderServicePluginFactoryMock,
            'aheadworksGiftcardOrderServicePluginFactory',
            $instance
        );
        static::assertAttributeEquals($this->orderServiceMock, 'orderService', $instance);
    }

    /**
     * @test
     * that beforeDeleteOrder will not execute its logic and call proceed if any of the preconditions are not met
     *
     * @covers ::beforeDeleteOrder
     *
     * @dataProvider beforeDeleteOrder_withVariousPreconditionUnmetStatesProvider
     *
     * @param bool $shouldRun stubbed result of {@see \Bolt\Boltpay\Plugin\ThirdPartySupport\AbstractPlugin::shouldRun}
     * @param bool $isAvailable stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isAvailable}
     * @param bool $isExists stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isExists}
     *
     * @throws \Exception from the tested method
     */
    public function beforeDeleteOrder_withVariousPreconditionStates_runsPluginLogicIfPreconditionsAreMet(
        $shouldRun,
        $isAvailable,
        $isExists
    ) {
        $this->currentMock->method('shouldRun')->willReturn($shouldRun);
        $this->aheadworksGiftcardOrderServicePluginFactoryMock->method('isAvailable')->willReturn($isAvailable);
        $this->aheadworksGiftcardOrderServicePluginFactoryMock->method('isExists')->willReturn($isExists);

        $preconditionsMet = $shouldRun && $isAvailable && $isExists;
        $this->aheadworksGiftcardOrderServicePluginFactoryMock->expects(
            $preconditionsMet
                ? static::once()
                : static::never()
        )->method('getInstance')->willReturn($this->aheadworksGiftcardOrderServicePluginMock);
        $this->aheadworksGiftcardOrderServicePluginMock->expects(
            $preconditionsMet
                ? static::once()
                : static::never()
        )->method('aroundCancel')->with(
            $this->orderServiceMock,
            static::callback(
                function ($callback) {
                    return $callback(self::ORDER_ID);
                }
            ),
            self::ORDER_ID
        );

        static::assertNull($this->currentMock->beforeDeleteOrder($this->subjectMock, $this->orderMock));
    }

    /**
     * Data provider for {@see beforeDeleteOrder_withVariousPreconditionStates_doesNotRunPluginLogic}
     *
     * @return array containing
     * stubbed result of {@see \Bolt\Boltpay\Plugin\ThirdPartySupport\AbstractPlugin::shouldRun}
     * stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isAvailable} for giftcard cart management
     * stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isExists} for giftcard cart management
     */
    public function beforeDeleteOrder_withVariousPreconditionUnmetStatesProvider()
    {
        return \Bolt\Boltpay\Test\Unit\TestHelper::getAllBooleanCombinations(3);
    }
}
