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

namespace Bolt\Boltpay\Test\Unit\ThirdPartyModules\Amasty;

use Bolt\Boltpay\ThirdPartyModules\Amasty\GiftCardAccount;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bolt\Boltpay\ThirdPartyModules\Amasty\GiftCardAccount
 */
class GiftCardAccountTest extends TestCase
{
    /** @var int Test order id */
    const ORDER_ID = 10001;

    /**
     * @var \Bolt\Boltpay\Helper\Bugsnag|MockObject mocked instance of the Bolt Bugsnag helper
     */
    private $bugsnagHelperMock;

    /**
     * @var \Amasty\GiftCardAccount\Model\GiftCardAccount\Repository|MockObject
     * mocked instance of the Amasty Giftcard repository
     */
    private $giftcardRepositoryMock;

    /**
     * @var \Amasty\GiftCardAccount\Model\GiftCardExtension\Order\Repository|MockObject
     */
    private $giftcardOrderRepositoryMock;

    /**
     * @var \Magento\Sales\Model\Order|MockObject mocked instance of the Magento Order model
     */
    private $orderMock;

    /**
     * @var \Amasty\GiftCardAccount\Api\Data\GiftCardOrderInterface|MockObject
     * mocked instance of the Order Extension Attribute added by Amasty Giftcard
     */
    private $giftcardOrderExtensionMock;

    /**
     * @var GiftCardAccount|MockObject mocked instance of the class tested
     */
    private $currentMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->bugsnagHelperMock = $this->createMock(\Bolt\Boltpay\Helper\Bugsnag::class);
        $this->giftcardRepositoryMock = $this->getMockBuilder(
            '\Amasty\GiftCardAccount\Model\GiftCardAccount\Repository'
        )->setMethods(['save', 'getById'])->disableOriginalConstructor()->getMock();
        $this->giftcardOrderRepositoryMock = $this->getMockBuilder(
            '\Amasty\GiftCardAccount\Model\GiftCardExtension\Order\Repository'
        )->setMethods(['getByOrderId'])->disableOriginalConstructor()->getMock();
        $this->orderMock = $this->createMock(\Magento\Sales\Model\Order::class);
        $this->orderMock->method('getId')->willReturn(self::ORDER_ID);
        $this->currentMock = $this->getMockBuilder(GiftCardAccount::class)
            ->setMethods(null)->setConstructorArgs([$this->bugsnagHelperMock])->getMock();
        $this->giftcardOrderExtensionMock = $this->getMockBuilder(
            '\Amasty\GiftCardAccount\Model\GiftCardExtension\Order\Order'
        )->setMethods(['getGiftCards'])->disableOriginalConstructor()->getMock();
    }

    /**
     * @test
     * that constructor sets provided arguments to properties
     *
     * @covers ::__construct
     */
    public function __construct_always_setsProperties()
    {
        $instance = new GiftCardAccount($this->bugsnagHelperMock);
        static::assertAttributeEquals($this->bugsnagHelperMock, 'bugsnagHelper', $instance);
    }

    /**
     * @test
     * that beforeDeleteOrder doesn't affect any giftcards if non are applied to the order
     *
     * @covers ::beforeDeleteOrder
     */
    public function beforeDeleteOrder_withNoGiftcardsOnOrder_doesNotRestoreBalance()
    {
        $this->giftcardOrderRepositoryMock->expects(static::once())->method('getByOrderId')
            ->with(self::ORDER_ID)->willThrowException(new NoSuchEntityException(__('Gift Card Order not found.')));
        $this->giftcardRepositoryMock->expects(static::never())->method('save');
        $this->currentMock->beforeDeleteOrder(
            $this->giftcardRepositoryMock,
            $this->giftcardOrderRepositoryMock,
            $this->orderMock
        );
    }

    /**
     * @test
     * that beforeDeleteOrder doesn't affect any giftcards if non are applied to the order
     *
     * @covers ::beforeDeleteOrder
     */
    public function beforeDeleteOrder_withGiftcardsAppliedToOrder_restoresGiftcardBalance()
    {
        $this->giftcardOrderRepositoryMock->expects(static::once())->method('getByOrderId')
            ->with(self::ORDER_ID)->willReturn($this->giftcardOrderExtensionMock);
        $this->giftcardOrderExtensionMock->expects(static::once())->method('getGiftCards')->willReturn(
            [
                ['id' => 3, 'b_amount' => 123.45],
                ['id' => 5, 'b_amount' => 456.78],
                ['id' => 15, 'b_amount' => 232.23],
            ]
        );
        $giftcard1 = $this->createGiftcardMock();
        $giftcard2 = $this->createGiftcardMock();
        $giftcard3 = $this->createGiftcardMock();
        $this->giftcardRepositoryMock->expects(static::exactly(3))->method('getById')
            ->withConsecutive([3], [5])->willReturnMap(
                [
                    [3, $giftcard1],
                    [5, $giftcard2],
                    [15, $giftcard3],
                ]
            );
        $giftcard1->expects(static::once())->method('setCurrentValue')
            ->with((float)(123.45 + 234.23));
        $giftcard1->expects(static::once())->method('getCurrentValue')->willReturn(234.23);
        $giftcard1->expects(static::once())->method('setStatus')->with(1);
        $giftcard2->expects(static::once())->method('setCurrentValue')
            ->with((float)(456.78 + 321.54));
        $giftcard2->expects(static::once())->method('getCurrentValue')->willReturn(321.54);
        $giftcard2->expects(static::once())->method('setStatus')->with(1);
        $exception = new \Magento\Framework\Exception\LocalizedException(__(''));
        $giftcard3->expects(static::once())->method('setCurrentValue')
            ->with((float)(232.23 + 521.23))->willThrowException($exception);
        $giftcard3->expects(static::once())->method('getCurrentValue')->willReturn(521.23);
        $this->giftcardRepositoryMock->expects(static::exactly(2))->method('save')
            ->withConsecutive([$giftcard1], [$giftcard3]);
        $this->bugsnagHelperMock->expects(static::once())->method('notifyException')->with($exception);
        $this->currentMock->beforeDeleteOrder(
            $this->giftcardRepositoryMock,
            $this->giftcardOrderRepositoryMock,
            $this->orderMock
        );
    }

    /**
     * Creates a mocked instance of the Amasty Giftcard Account
     * @return MockObject|\Amasty\GiftCardAccount\Model\GiftCardAccount\Account
     */
    private function createGiftcardMock()
    {
        return $this->getMockBuilder('\Amasty\GiftCardAccount\Model\GiftCardAccount\Account')
            ->setMethods(['setCurrentValue', 'getCurrentValue', 'setStatus'])
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();
    }
}
