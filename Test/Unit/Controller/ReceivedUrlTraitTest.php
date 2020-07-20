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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Controller;

use Bolt\Boltpay\Helper\Cart;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Controller\ReceivedUrlTrait;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class ReceivedUrlTraitTest
 * @coversDefaultClass \Bolt\Boltpay\Controller\ReceivedUrlTrait
 */
class ReceivedUrlTraitTest extends TestCase
{
    const QUOTE_ID = 1;
    const ORDER_ID = 2;
    const INCREMENT_ID = 3;
    const REDIRECT_URL = 'https://bolt-rediect.com';
    const TRANSACTION_REFERENCE = 'TRANSACTION_REFERENCE_TEST';

    /**
     * @var ReceivedUrlTrait
     */
    private $currentMock;

    /**
     * @var Cart
     */
    private $cartHelper;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var Quote
     */
    private $quote;

    /**
     * @var Order
     */
    private $order;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var Config
     */
    private $configHelper;

    public function setUp()
    {
        $this->currentMock = $this->getMockBuilder(ReceivedUrlTrait::class)
            ->enableOriginalConstructor()
            ->getMockForTrait();
        $this->cartHelper = $this->createPartialMock(Cart::class, ['getQuoteById']);
        $this->orderHelper = $this->createPartialMock(OrderHelper::class, ['getExistingOrder']);
        $this->configHelper = $this->createPartialMock(Config::class, ['getSigningSecret']);
        $this->checkoutSession = $this->createPartialMock(CheckoutSession::class,
            ['setLastQuoteId', 'setLastSuccessQuoteId', 'clearHelperData', 'setLastOrderId', 'setRedirectUrl', 'setLastRealOrderId', 'setLastOrderStatus']
        );
        $this->quote = $this->createPartialMock(Quote::class, ['getId']);
        $this->order = $this->createPartialMock(Order::class, ['getId', 'getIncrementId', 'getStatus']);
        TestHelper::setProperty($this->currentMock, 'cartHelper', $this->cartHelper);
        TestHelper::setProperty($this->currentMock, 'checkoutSession', $this->checkoutSession);
        TestHelper::setProperty($this->currentMock, 'orderHelper', $this->orderHelper);
        TestHelper::setProperty($this->currentMock, 'configHelper', $this->configHelper);
    }

    /**
     * @test
     */
    public function getQuoteById()
    {
        $this->cartHelper->expects(self::once())->method('getQuoteById')->with(self::QUOTE_ID)->willReturn($this->quote);
        TestHelper::invokeMethod($this->currentMock, 'getQuoteById', [self::QUOTE_ID]);
    }


    /**
     * @test
     */
    public function clearQuoteSession()
    {
        $this->quote->expects(self::any())->method('getId')->willReturn(self::QUOTE_ID);
        $this->checkoutSession->expects(self::once())->method('setLastQuoteId')->with(self::QUOTE_ID)->willReturnSelf();
        $this->checkoutSession->expects(self::once())->method('setLastSuccessQuoteId')->with(self::QUOTE_ID)->willReturnSelf();
        $this->checkoutSession->expects(self::once())->method('clearHelperData')->willReturnSelf();

        TestHelper::invokeMethod($this->currentMock, 'clearQuoteSession', [$this->quote]);
    }

    /**
     * @test
     */
    public function clearOrderSession()
    {
        $this->order->expects(self::any())->method('getId')->willReturn(self::ORDER_ID);
        $this->order->expects(self::any())->method('getIncrementId')->willReturn(self::INCREMENT_ID);
        $this->order->expects(self::any())->method('getStatus')->willReturn(Order::STATE_PROCESSING);
        $this->checkoutSession->expects(self::once())->method('setLastOrderId')->with(self::ORDER_ID)->willReturnSelf();
        $this->checkoutSession->expects(self::once())->method('setRedirectUrl')->with(self::REDIRECT_URL)->willReturnSelf();
        $this->checkoutSession->expects(self::once())->method('setLastRealOrderId')->with(self::INCREMENT_ID)->willReturnSelf();
        $this->checkoutSession->expects(self::once())->method('setLastOrderStatus')->with(Order::STATE_PROCESSING)->willReturnSelf();

        TestHelper::invokeMethod($this->currentMock, 'clearOrderSession', [$this->order, self::REDIRECT_URL]);
    }

    /**
     * @test
     */
    public function getOrderByIncrementId()
    {
        $this->orderHelper->expects(self::once())->method('getExistingOrder')->with(self::INCREMENT_ID)->willReturn([$this->order]);
        TestHelper::invokeMethod($this->currentMock, 'getOrderByIncrementId', [self::INCREMENT_ID]);
    }

    /**
     * @test
     */
    public function getOrderByIncrementId_throwException()
    {
        $this->orderHelper->expects(self::once())->method('getExistingOrder')->with(self::INCREMENT_ID)->willReturn(null);
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Could not find the order data.');
        TestHelper::invokeMethod($this->currentMock, 'getOrderByIncrementId', [self::INCREMENT_ID]);
    }

    /**
     * @test
     *
     */
    public function getReferenceFromPayload()
    {
        $payload = [
            "transaction_reference" => self::TRANSACTION_REFERENCE,
            "carrier"               => "United States Postal Service",
            "items"                 => [
                (object)[
                    'reference'=>'12345',
                    'options'=>[(object)[
                        "name"  => "Size",
                        "value" => "XS",
                    ]],
                ],
            ],
        ];

        $result = TestHelper::invokeMethod($this->currentMock, 'getReferenceFromPayload', [$payload]);

        $this->assertEquals(self::TRANSACTION_REFERENCE, $result);
    }
}
