<?php

namespace Bolt\Boltpay\Test\Unit\Controller\Order;

use Bolt\Boltpay\Controller\Order\ReceivedUrl;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class ReceivedUrlTest extends TestCase
{
    //TODO: figure out proper values for these things, probably some reverse engineering to be done.
    const DECODED_BOLT_PAYLOAD = '{"display_id":"' .self::JSON_DISPLAY_ID. '", "transaction_reference":"' .self::TRANSACTION_REFERENCE. '"}';
    const DISPLAY_ID = self::INCREMENT_ID. ' / ' .self::QUOTE_ID;
    const INCREMENT_ID = 'increment_id';
    const JSON_DISPLAY_ID = self::INCREMENT_ID. ' \/ ' .self::QUOTE_ID;
    const ORDER_ID = 'order_id';
    const QUOTE_ID = 'quote_id';
    const STORE_ID = '1234';
    const SIGNING_SECRET = 'signing secret';
    const FORMATTED_REFERENCE_URL = 'https://for.matted.ref/erence/url';
    const REDIRECT_URL = 'https://red.irect.url';
    const ORDER_STATUS = 'order_status';
    const TRANSACTION_REFERENCE = 'transaction_reference';

    /**
     * @var string
     */
    private $boltSignature;

    /**
     * @var string
     */
    private $encodedBoltPayload;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var ConfigHelper | MockObject
     */
    private $configHelper;

    /**
     * @var CartHelper | MockObject
     */
    private $cartHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var CheckoutSession | MockObject
     */
    private $checkoutSession;

    /**
     * @var OrderHelper | MockObject
     */
    private $orderHelper;

    /**
     * @test
     */
    public function execute_HappyPath()
    {
        $requestMap = [
            ['bolt_signature', null, $this->boltSignature],
            ['bolt_payload', null, $this->encodedBoltPayload],
            ['store_id', null, self::STORE_ID]
        ];

        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->exactly(3))
            ->method('getParam')
            ->will($this->returnValueMap($requestMap));

        $order = $this->createMock(Order::class);
        $order->method('getId')
            ->willReturn(self::ORDER_ID);
        $order->method('getQuoteId')
            ->willReturn(self::QUOTE_ID);
        $order->method('getState')
            ->willReturn(Order::STATE_PENDING_PAYMENT); //this is specifically for happy path
        $order->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with('Bolt transaction: ' . self::FORMATTED_REFERENCE_URL);
        $order->expects($this->once())
            ->method('getStoreId')
            ->willReturn(self::STORE_ID);
        $order->expects($this->once())
            ->method('getIncrementId')
            ->willReturn(self::INCREMENT_ID);
        $order->expects($this->once())
            ->method('getStatus')
            ->willReturn(self::ORDER_STATUS);

        $quote = $this->createMock(Quote::class);
        $quote->method('getId')
            ->willReturn(self::QUOTE_ID);

        $url = $this->createMock(UrlInterface::class);
        $url->expects($this->once())
            ->method('setScope')
            ->with(self::STORE_ID);
        $url->method('getUrl')
            ->willReturn(self::REDIRECT_URL);

        $this->cartHelper->expects($this->once())
            ->method('getOrderByIncrementId')
            ->with(self::INCREMENT_ID)
            ->willReturn($order);
        $this->cartHelper->expects($this->once())
            ->method('getQuoteById')
            ->with(self::QUOTE_ID)
            ->willReturn($quote);

        //clearQuoteSession($quote)
        $this->checkoutSession->expects($this->once())
            ->method('setLastQuoteId')
            ->with(self::QUOTE_ID)
            ->willReturnSelf();
        $this->checkoutSession->expects($this->once())
            ->method('setLastSuccessQuoteId')
            ->with(self::QUOTE_ID)
            ->willReturnSelf();
        $this->checkoutSession->expects($this->once())
            ->method('clearHelperData')
            ->willReturnSelf();

        //clearOrderSession($order, $redirectUrl)
        $this->checkoutSession->expects($this->once())
            ->method('setLastOrderId')
            ->with(self::ORDER_ID)
            ->willReturnSelf();
        $this->checkoutSession->expects($this->once())
            ->method('setRedirectUrl')
            ->with(self::REDIRECT_URL)
            ->willReturnSelf();
        $this->checkoutSession->expects($this->once())
            ->method('setLastRealOrderId')
            ->with(self::INCREMENT_ID)
            ->willReturnSelf();
        $this->checkoutSession->expects($this->once())
            ->method('setLastOrderStatus')
            ->with(self::ORDER_STATUS)
            ->willReturnSelf();

        $this->configHelper->expects($this->once())
            ->method('getSigningSecret')
            ->with(self::STORE_ID)
            ->willReturn(self::SIGNING_SECRET);
        $this->configHelper->expects($this->once())
            ->method('getSuccessPageRedirect')
            ->with(self::STORE_ID)
            ->willReturn(self::REDIRECT_URL);

        $this->context->method('getUrl')
            ->willReturn($url);

        $this->orderHelper->expects($this->once())
            ->method('formatReferenceUrl')
            ->with(self::TRANSACTION_REFERENCE)
            ->willReturn(self::FORMATTED_REFERENCE_URL);
        $this->orderHelper->expects($this->once())
            ->method('dispatchPostCheckoutEvents')
            ->with($this->equalTo($order), $this->equalTo($quote));

        $receivedUrl = $this->initReceivedUrlMock();

        $receivedUrl->method('getRequest')
            ->willReturn($request);
        $receivedUrl->expects($this->once())
            ->method('_redirect')
            ->with(self::REDIRECT_URL);

        $receivedUrl->execute();
    }


    public function setUp()
    {
        $this->initRequiredMocks();
        $this->initAuthentication();
    }

    private function initRequiredMocks()
    {
        $this->context = $this->createMock(Context::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->checkoutSession = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'setLastQuoteId',
                'setLastSuccessQuoteId',
                'clearHelperData',
                'setLastOrderId',
                'setRedirectUrl',
                'setLastRealOrderId',
                'setLastOrderStatus'
            ])
            ->getMock();
        $this->orderHelper = $this->createMock(OrderHelper::class);
    }

    private function initReceivedUrlMock()
    {
        $receivedUrl = $this->getMockBuilder(ReceivedUrl::class)
            ->setMethods([
                'getRequest',
                '_redirect'
            ])
            ->setConstructorArgs([
                $this->context,
                $this->configHelper,
                $this->cartHelper,
                $this->bugsnag,
                $this->logHelper,
                $this->checkoutSession,
                $this->orderHelper
            ])
            ->getMock();
        return $receivedUrl;
    }

    //sets up things to pass the check in ReceivedUrlTrait->execute()
    private function initAuthentication()
    {
        $this->encodedBoltPayload = base64_encode(self::DECODED_BOLT_PAYLOAD);
        $hashBoltPayloadWithKey = hash_hmac('sha256', $this->encodedBoltPayload, self::SIGNING_SECRET, true);
        $this->boltSignature = base64_encode(base64_encode($hashBoltPayloadWithKey));
    }
}
