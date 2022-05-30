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

use Bolt\Boltpay\Controller\ReceivedUrlTrait;
use Bolt\Boltpay\Helper\Cart;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Bolt\Boltpay\Controller\ReceivedUrlInterface;

/**
 * Class ReceivedUrlTraitTest
 * @coversDefaultClass \Bolt\Boltpay\Controller\ReceivedUrlTrait
 */
class ReceivedUrlTraitTest extends BoltTestCase
{
    const QUOTE_ID = 1;
    const ORDER_ID = 2;
    const INCREMENT_ID = 3;
    const REDIRECT_URL = 'https://bolt-rediect.com';
    const TRANSACTION_REFERENCE = 'TRANSACTION_REFERENCE_TEST';
    
    const DECODED_BOLT_PAYLOAD = '{"display_id":"' .self::JSON_DISPLAY_ID. '", "transaction_reference":"' .self::TRANSACTION_REFERENCE. '"}';
    const DISPLAY_ID = self::INCREMENT_ID;
    const JSON_DISPLAY_ID = self::INCREMENT_ID;
    const SIGNING_SECRET = 'signing secret';
    const STORE_ID = 1;

    /**
     * @var ReceivedUrlTrait|\PHPUnit\Framework\MockObject\MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $currentMock;

    /**
     * @var Cart|\PHPUnit\Framework\MockObject\MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $cartHelper;

    /**
     * @var CheckoutSession|\PHPUnit\Framework\MockObject\MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $checkoutSession;

    /**
     * @var Quote|\PHPUnit\Framework\MockObject\MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $quote;

    /**
     * @var Order|\PHPUnit\Framework\MockObject\MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $order;

    /**
     * @var OrderHelper|\PHPUnit\Framework\MockObject\MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $orderHelper;

    /**
     * @var Config|\PHPUnit\Framework\MockObject\MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $configHelper;

    /**
     * @var \Magento\Framework\HTTP\PhpEnvironment\Request|\PHPUnit\Framework\MockObject\MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $requestMock;

    /**
     * @var \Bolt\Boltpay\Helper\FeatureSwitch\Decider|\PHPUnit\Framework\MockObject\MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $deciderMock;

    /**
     * @var \Magento\Sales\Model\Order\Payment|\PHPUnit\Framework\MockObject\MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $paymentMock;

    public function setUpInternal()
    {
        $this->currentMock = $this->getMockBuilder(ReceivedUrlTrait::class)
            ->enableOriginalConstructor()
            ->setMethods(['getRequest', 'redirectToAdminIfNeeded', 'getRedirectUrl', '_redirect'])
            ->getMockForTrait();
        $this->cartHelper = $this->createPartialMock(Cart::class, ['getQuoteById', 'getFeatureSwitchDeciderHelper']);
        $this->orderHelper = $this->createPartialMock(OrderHelper::class, ['getExistingOrder', 'fetchTransactionInfo', 'formatReferenceUrl', 'dispatchPostCheckoutEvents']);
        $this->configHelper = $this->createPartialMock(Config::class, ['getSigningSecret']);
        $this->checkoutSession = $this->createPartialMock(
            CheckoutSession::class,
            ['setLastQuoteId', 'setLastSuccessQuoteId', 'clearHelperData', 'setLastOrderId', 'setRedirectUrl', 'setLastRealOrderId', 'setLastOrderStatus']
        );
        $this->requestMock = $this->createMock(\Magento\Framework\HTTP\PhpEnvironment\Request::class);
        $this->currentMock->method('getRequest')->willReturn($this->requestMock);
        $this->deciderMock = $this->createMock(\Bolt\Boltpay\Helper\FeatureSwitch\Decider::class);
        $this->cartHelper->method('getFeatureSwitchDeciderHelper')->willReturn($this->deciderMock);
        $this->quote = $this->createPartialMock(Quote::class, ['getId']);
        $this->order = $this->createPartialMock(Order::class, ['getId', 'getIncrementId', 'getStatus', 'getState', 'getPayment', 'addStatusHistoryComment', 'save']);
        $this->paymentMock = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
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

    /**
     * @test
     * that execute will use {@see \Bolt\Boltpay\Helper\Order::setOrderPaymentInfoData} to set credit card data to order
     * if the M2_SET_ORDER_PAYMENT_INFO_DATA_ON_SUCCESS_PAGE feature switch is enabled
     * order payment object is available and transaction is succesfully retrieved
     *
     * @covers \Bolt\Boltpay\Controller\ReceivedUrlTrait::execute
     *      
     * @dataProvider execute_ifFeatureSwitchIsEnabled_setsPaymentInfoDataProvider
     */
    public function execute_ifFeatureSwitchIsEnabled_setsPaymentInfoData(
        $isSetOrderPaymentInfoDataOnSuccessPage,
        $isPaymentAvailable,
        $isTransactionAvailable
    ) {
        $encodedBoltPayload = base64_encode(self::DECODED_BOLT_PAYLOAD);
        $hashBoltPayloadWithKey = hash_hmac('sha256', $encodedBoltPayload, self::SIGNING_SECRET, true);
        $boltSignature = base64_encode(base64_encode($hashBoltPayloadWithKey));
        $this->requestMock->expects(static::exactly(3))->method('getParam')->willReturnMap(
            [
                ['bolt_signature', null, $boltSignature],
                ['bolt_payload', null, $encodedBoltPayload],
                ['store_id', null, self::STORE_ID],
            ]
        );
        
        $this->checkoutSession->method(new \PHPUnit\Framework\Constraint\RegularExpression("/[setLastQuoteId|setLastSuccessQuoteId]/"))
            ->willReturnSelf();
        
        $this->configHelper->expects(static::once())->method('getSigningSecret')->with(self::STORE_ID)->willReturn(self::SIGNING_SECRET);
        $this->orderHelper->expects(static::once())->method('getExistingOrder')->willReturn($this->order);
        $this->cartHelper->expects(static::once())->method('getQuoteById')->willReturn($this->quote);
        $this->order->expects(static::once())->method('getState')->willReturn(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $this->order->expects(static::once())->method('addStatusHistoryComment')->willReturnSelf();
        
        $this->deciderMock->method('isSetOrderPaymentInfoDataOnSuccessPage')
            ->willReturn($isSetOrderPaymentInfoDataOnSuccessPage);
        $this->order->method('getPayment')->willReturn($isPaymentAvailable ? $this->paymentMock : null);
        $this->orderHelper->method('fetchTransactionInfo')->willReturn(json_decode(self::DECODED_BOLT_PAYLOAD));

        $this->currentMock->execute();
    }

    /**
     * Data provider for {@see execute_ifFeatureSwitchIsEnabled_setsPaymentInfoData}
     *
     * @return \bool[][]
     */
    public function execute_ifFeatureSwitchIsEnabled_setsPaymentInfoDataProvider()
    {
        return TestHelper::getAllBooleanCombinations(3);
    }
}
