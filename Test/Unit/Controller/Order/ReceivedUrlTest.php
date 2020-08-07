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

namespace Bolt\Boltpay\Test\Unit\Controller\Order;

use Bolt\Boltpay\Controller\Order\ReceivedUrl;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Phrase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Constraint\ExceptionMessage;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\Response\RedirectInterface;

/**
 * @coversDefaultClass \Bolt\Boltpay\Controller\Order\ReceivedUrl
 */
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
    const BACKEND_REDIRECT_URL = 'https://red.irect.url/backend';
    const ORDER_STATUS = 'order_status';
    const TRANSACTION_REFERENCE = 'transaction_reference';
    const NO_SUCH_ENTITY_MESSAGE = 'Could not find the order data.';
    const LOCALIZED_MESSAGE = 'Localized message';
    const UNEQUAL_MESSAGE = 'bolt_signature and Magento signature are not equal';

    /**
     * @var string
     */
    private $boltSignature;

    /**
     * @var string
     */
    private $encodedBoltPayload;

    /**
     * @var array
     */
    private $defaultRequestMap;

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
     * @var Bugsnag | MockObject
     */
    private $bugsnag;

    /**
     * @var LogHelper | MockObject
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
     * @var BackendUrl\ | MockObject
     */
    private $backendUrl;

    /**
     * @var RedirectInterface | MockObject
     */
    private $redirect;

    /**
     * @test
     * that hasAdminUrlReferer returns true when referrer for current request is admin order create page
     *
     * @covers ::hasAdminUrlReferer
     *
     * @dataProvider hasAdminUrlReferer_withVariousHttpReferrersProvider
     *
     * @param string $referrerUrl of the currenct request
     * @param bool   $isAdminReferrer expected output of the method call
     *
     * @throws \ReflectionException if class tested doesn't have _request property or hasAdminUrlReferer method
     */
    public function hasAdminUrlReferer_withVariousHttpReferrers_determinesIfUrlReferIsAdminOrderCreate(
        $referrerUrl,
        $isAdminReferrer
    ) {
        $om = new ObjectManager($this);
        $backendUrl = $this->createMock(\Magento\Backend\Model\UrlInterface::class);
        $instance = $om->getObject(\Bolt\Boltpay\Controller\Order\ReceivedUrl::class, ['backendUrl' => $backendUrl]);
        $request = $this->createMock(\Magento\Framework\App\Request\Http::class);
        $backendUrl->expects(static::once())->method('setScope')->with(0);
        $request->expects(static::once())->method('getServer')->with('HTTP_REFERER')->willReturn($referrerUrl);
        $backendUrl->expects(static::once())->method('getUrl')->with("sales/order_create/index", ['_nosecret' => true])
            ->willReturn('https://example.com/admin/sales/order/create');
        TestHelper::setProperty($instance, '_request', $request);
        static::assertEquals($isAdminReferrer, TestHelper::invokeMethod($instance, 'hasAdminUrlReferer'));
    }

    /**
     * Data provider for {@see hasAdminUrlReferer_withVariousHttpReferrers_determinesIfUrlReferIsAdminOrderCreate}
     *
     * @return array[] containing referrer url and expected result of the method tested
     */
    public function hasAdminUrlReferer_withVariousHttpReferrersProvider()
    {
        return [
            [
                'referrerUrl'     => 'https://example.com/admin/sales/order/create/key/' . sha1('bolt'),
                'isAdminReferrer' => true
            ],
            [
                'referrerUrl'     => 'https://example.com/admin/sales/order/create',
                'isAdminReferrer' => true
            ],
            [
                'referrerUrl'     => '',
                'isAdminReferrer' => false
            ],
            [
                'referrerUrl'     => 'https://example.com/',
                'isAdminReferrer' => false
            ],
            [
                'referrerUrl'     => 'https://example.com/admin',
                'isAdminReferrer' => false
            ],
        ];
    }

    /**
     * @test
     */
    public function execute_HappyPath()
    {
        $request = $this->initRequest($this->defaultRequestMap);

        $order = $this->createOrderMock(Order::STATE_PENDING_PAYMENT);
        $order->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with('Bolt transaction: ' . self::FORMATTED_REFERENCE_URL)
            ->willReturnSelf();
        $order->expects($this->once())
            ->method('save')
            ->willReturnSelf();

        $quote = $this->createMock(Quote::class);
        $quote->method('getId')
            ->willReturn(self::QUOTE_ID);

        $url = $this->createUrlMock();

        $cartHelper = $this->createMock(CartHelper::class);
        $cartHelper->expects($this->once())
            ->method('getQuoteById')
            ->with(self::QUOTE_ID)
            ->willReturn($quote);

        $checkoutSession = $this->getMockBuilder(CheckoutSession::class)
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
        //clearQuoteSession($quote)
        $checkoutSession->expects($this->once())
            ->method('setLastQuoteId')
            ->with(self::QUOTE_ID)
            ->willReturnSelf();
        $checkoutSession->expects($this->once())
            ->method('setLastSuccessQuoteId')
            ->with(self::QUOTE_ID)
            ->willReturnSelf();
        $checkoutSession->expects($this->once())
            ->method('clearHelperData')
            ->willReturnSelf();

        //clearOrderSession($order, $redirectUrl)
        $checkoutSession->expects($this->once())
            ->method('setLastOrderId')
            ->with(self::ORDER_ID)
            ->willReturnSelf();
        $checkoutSession->expects($this->once())
            ->method('setRedirectUrl')
            ->with(self::REDIRECT_URL)
            ->willReturnSelf();
        $checkoutSession->expects($this->once())
            ->method('setLastRealOrderId')
            ->with(self::INCREMENT_ID)
            ->willReturnSelf();
        $checkoutSession->expects($this->once())
            ->method('setLastOrderStatus')
            ->with(self::ORDER_STATUS)
            ->willReturnSelf();

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->once())
            ->method('getSigningSecret')
            ->with(self::STORE_ID)
            ->willReturn(self::SIGNING_SECRET);
        $configHelper->expects($this->once())
            ->method('getSuccessPageRedirect')
            ->with(self::STORE_ID)
            ->willReturn(self::REDIRECT_URL);

        $context = $this->createMock(Context::class);
        $context->method('getUrl')
            ->willReturn($url);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->expects($this->once())
            ->method('getExistingOrder')
            ->with(self::INCREMENT_ID)
            ->willReturn($order);
        $orderHelper->expects($this->once())
            ->method('formatReferenceUrl')
            ->with(self::TRANSACTION_REFERENCE)
            ->willReturn(self::FORMATTED_REFERENCE_URL);
        $orderHelper->expects($this->once())
            ->method('dispatchPostCheckoutEvents')
            ->with($this->equalTo($order), $this->equalTo($quote));

        $receivedUrl = $this->initReceivedUrlMock(
            $context,
            $configHelper,
            $cartHelper,
            $this->bugsnag,
            $this->logHelper,
            $checkoutSession,
            $orderHelper
        );

        $receivedUrl->method('getRequest')
            ->willReturn($request);
        $receivedUrl->expects($this->once())
            ->method('_redirect')
            ->with(self::REDIRECT_URL);

        $receivedUrl->execute();
    }

    /**
     * @test
     * that customer will be redirected to backend received url endpoint if backoffice order is placed by admin
     *
     * @covers ::execute
     * @covers ::hasAdminUrlReferer
     * @covers ::redirectToAdminIfNeeded
     */
    public function execute_ifBackofficeOrderPlacedByAdmin_redirectsToAdminReceivedUrl()
    {
        $request = $this->initRequest($this->defaultRequestMap);

        $order = $this->createOrderMock(Order::STATE_PENDING_PAYMENT);
        $quote = $this->createPartialMock(Quote::class, [ 'getId', 'getStoreId', 'getBoltCheckoutType' ]);
        $quote->method('getId')
              ->willReturn(self::QUOTE_ID);
        $quote->expects(self::once())->method('getBoltCheckoutType')
              ->willReturn(CartHelper::BOLT_CHECKOUT_TYPE_BACKOFFICE);

        $cartHelper = $this->createMock(CartHelper::class);
        $cartHelper->expects($this->once())
                   ->method('getQuoteById')
                   ->with(self::QUOTE_ID)
                   ->willReturn($quote);

        $checkoutSession = $this->createMock(CheckoutSession::class);

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->once())
                     ->method('getSigningSecret')
                     ->with(self::STORE_ID)
                     ->willReturn(self::SIGNING_SECRET);

        $context = $this->createMock(Context::class);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->expects($this->once())
                    ->method('getExistingOrder')
                    ->with(self::INCREMENT_ID)
                    ->willReturn($order);

        $receivedUrl = $this->initReceivedUrlMock(
            $context,
            $configHelper,
            $cartHelper,
            $this->bugsnag,
            $this->logHelper,
            $checkoutSession,
            $orderHelper
        );

        $this->redirect->expects(static::never())->method('getRefererUrl');
        $request->expects(static::once())->method('getServer')->with('HTTP_REFERER')
            ->willReturn('https://example.com/admin/sales/order/create');
        $this->backendUrl->method('getUrl')
             ->willReturnOnConsecutiveCalls('https://example.com/admin/sales/order/create', self::BACKEND_REDIRECT_URL);

        $receivedUrl->method('getRequest')
                    ->willReturn($request);
        $receivedUrl->expects($this->once())
                    ->method('_redirect')
                    ->with(self::BACKEND_REDIRECT_URL);

        $receivedUrl->execute();
    }

    /**
     * @test
     */
    public function execute_IncorrectOrderState()
    {
        $request = $this->initRequest($this->defaultRequestMap);

        $order = $this->createOrderMock(Order::STATE_CLOSED);

        $quote = $this->createMock(Quote::class);
        $quote->method('getId')
            ->willReturn(self::QUOTE_ID);

        $url = $this->createUrlMock();

        $cartHelper = $this->createMock(CartHelper::class);
        $cartHelper->method('getQuoteById')
            ->willReturn($quote);

        $checkoutSession = $this->getMockBuilder(CheckoutSession::class)
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

        //clearQuoteSession
        $checkoutSession->expects($this->once())
            ->method('setLastQuoteId')
            ->with(self::QUOTE_ID)
            ->willReturnSelf();
        $checkoutSession->expects($this->once())
            ->method('setLastSuccessQuoteId')
            ->with(self::QUOTE_ID)
            ->willReturnSelf();
        $checkoutSession->expects($this->once())
            ->method('clearHelperData')
            ->willReturnSelf();

        //clearOrderSession
        $checkoutSession->expects($this->once())
            ->method('setLastOrderId')
            ->with(self::ORDER_ID)
            ->willReturnSelf();
        $checkoutSession->expects($this->once())
            ->method('setRedirectUrl')
            ->with(self::REDIRECT_URL)
            ->willReturnSelf();
        $checkoutSession->expects($this->once())
            ->method('setLastRealOrderId')
            ->with(self::INCREMENT_ID)
            ->willReturnSelf();
        $checkoutSession->expects($this->once())
            ->method('setLastOrderStatus')
            ->with(self::ORDER_STATUS)
            ->willReturnSelf();

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->method('getSigningSecret')
            ->willReturn(self::SIGNING_SECRET);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->method('getExistingOrder')
            ->willReturn($order);

        $context = $this->createMock(Context::class);
        $context->method('getUrl')
            ->willReturn($url);

        $bugsnag = $this->createMock(Bugsnag::class);
        $bugsnag->expects($this->once())
            ->method('notifyError')
            ->with(
                $this->equalTo('Pre-Auth redirect wrong order state'), //Hard-coded string in class
                $this->equalTo('OrderNo: ' . self::INCREMENT_ID . ', State: ' . ORDER::STATE_CLOSED) //Parameterized string in class
            );

        $receivedUrl = $this->initReceivedUrlMock(
            $context,
            $configHelper,
            $cartHelper,
            $bugsnag,
            $this->logHelper,
            $checkoutSession,
            $orderHelper
        );

        $receivedUrl->method('getRequest')
            ->willReturn($request);
        $receivedUrl->execute();
    }

    /**
     * @test
     */
    public function execute_SignatureAndHashUnequal()
    {
        $request = $this->initRequest($this->defaultRequestMap);

        $messageManager = $this->createMessageManagerMock();

        $bugsnag = $this->createMock(Bugsnag::class);
        $bugsnag->expects($this->once())
            ->method('registerCallback')
            ->wilLReturnCallback(
                function (callable $callback) use ($request) {
                    $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                    $reportMock->expects($this->once())
                        ->method('setMetaData')
                        ->with([
                            'bolt_signature' => $this->boltSignature,
                            'bolt_payload' => $this->encodedBoltPayload,
                            'store_id' => self::STORE_ID
                        ]);
                    $callback($reportMock);
                }
            );
        $bugsnag->expects($this->once())
            ->method('notifyError')
            ->with($this->equalTo('OrderReceivedUrl Error'), $this->equalTo(self::UNEQUAL_MESSAGE));

        $context = $this->createMock(Context::class);
        $context->method('getMessageManager')
            ->willReturn($messageManager);

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->method('getSigningSecret')->willReturn('failing hash check!');

        $logHelper = $this->createMock(LogHelper::class);
        $logHelper->expects($this->once())
            ->method('addInfoLog')
            ->with(self::UNEQUAL_MESSAGE);

        $receivedUrl = $this->initReceivedUrlMock(
            $context,
            $configHelper,
            $this->cartHelper,
            $bugsnag,
            $logHelper,
            $this->checkoutSession,
            $this->orderHelper
        );

        $receivedUrl->method('getRequest')->willReturn($request);
        $receivedUrl->expects($this->once())
            ->method('_redirect')
            ->with('/');

        $receivedUrl->execute();
    }

    /**
     * @test
     */
    public function execute_NoSuchEntityException()
    {
        $request = $this->initRequest($this->defaultRequestMap);

        $message = $this->createMessageManagerMock();
        $message->expects($this->once())
            ->method('addErrorMessage');

        $context = $this->createMock(Context::class);
        $context->method('getMessageManager')
            ->willReturn($message);

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->once())
            ->method('getSigningSecret')
            ->with(self::STORE_ID)
            ->willReturn(self::SIGNING_SECRET);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->method('getExistingOrder')
            ->willReturn(null);

        $bugsnag = $this->createMock(Bugsnag::class);
        $bugsnag->expects($this->once())
            ->method('registerCallback')
            ->willReturnCallback(
                function (callable $callback) use ($request) {
                    $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                    $reportMock->expects($this->once())
                        ->method('setMetaData')
                        ->with([
                            'order_id' => self::INCREMENT_ID,
                            'store_id' => self::STORE_ID
                        ]);
                    $callback($reportMock);
                }
            );
        $bugsnag->expects($this->once())
            ->method('notifyError')
            ->with($this->equalTo('NoSuchEntityException: '), $this->equalTo(self::NO_SUCH_ENTITY_MESSAGE));

        $receivedUrl = $this->initReceivedUrlMock(
            $context,
            $configHelper,
            $this->cartHelper,
            $bugsnag,
            $this->logHelper,
            $this->checkoutSession,
            $orderHelper
        );

        $receivedUrl->method('getRequest')
            ->willReturn($request);
        $receivedUrl->expects($this->once())
            ->method('_redirect')
            ->with('/');

        $receivedUrl->execute();
    }

    /**
     * @test
     */
    public function execute_LocalizedException()
    {
        $request = $this->initRequest($this->defaultRequestMap);

        $exception = new LocalizedException(new Phrase(self::LOCALIZED_MESSAGE));
        $logHelper = $this->createMock(LogHelper::class);

        $message = $this->createMessageManagerMock();
        $message->expects($this->once())
            ->method('addErrorMessage')
            ->with('Something went wrong. Please contact the seller.'); //Hardcoded string in class

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->method('getExistingOrder')
            ->willThrowException($exception);

        $context = $this->createMock(Context::class);
        $context->method('getMessageManager')
            ->willReturn($message);

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->once())
            ->method('getSigningSecret')
            ->with(self::STORE_ID)
            ->willReturn(self::SIGNING_SECRET);

        $bugsnag = $this->createMock(Bugsnag::class);
        $bugsnag->expects($this->once())
            ->method('registerCallback')
            ->willReturnCallback(
                function (callable $callback) use ($request) {
                    $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                    $reportMock->expects($this->once())
                        ->method('setMetaData')
                        ->with([
                            'bolt_signature' => $this->boltSignature,
                            'bolt_payload' => $this->encodedBoltPayload,
                            'store_id' => self::STORE_ID
                        ]);
                    $callback($reportMock);
                }
            );
        $bugsnag->expects($this->once())
            ->method('notifyError')
            ->with($this->equalTo('LocalizedException: '), $this->equalTo(self::LOCALIZED_MESSAGE));

        $receivedUrl = $this->initReceivedUrlMock(
            $context,
            $configHelper,
            $this->cartHelper,
            $bugsnag,
            $logHelper,
            $this->checkoutSession,
            $orderHelper
        );

        $receivedUrl->method('getRequest')
            ->willReturn($request);
        $receivedUrl->expects($this->once())
            ->method('_redirect')
            ->with('/');

        $receivedUrl->execute();
    }

    public function setUp()
    {
        $this->initRequiredMocks();
        $this->initAuthentication();
        $this->initRequestMap();
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
        $this->backendUrl = $this->createMock(BackendUrl::class);
        $this->redirect = $this->createMock(RedirectInterface::class);
    }

    private function createOrderMock($state)
    {
        $order = $this->createMock(Order::class);
        $order->method('getState')
              ->wilLReturn($state);
        $order->method('getQuoteId')
              ->willReturn(self::QUOTE_ID);
        $order->method('getId')
              ->willReturn(self::ORDER_ID);
        $order->method('getStoreId')
              ->willReturn(self::STORE_ID);
        $order->method('getIncrementId')
              ->willReturn(self::INCREMENT_ID);
        $order->method('getStatus')
              ->willReturn(self::ORDER_STATUS);
        return $order;
    }

    private function initReceivedUrlMock($context, $configHelper, $cartHelper, $bugsnag, $logHelper, $checkoutSession, $orderHelper)
    {
        $receivedUrl = $this->getMockBuilder(ReceivedUrl::class)
            ->setMethods([
                'getRequest',
                '_redirect'
            ])
            ->setConstructorArgs([
                $context,
                $configHelper,
                $cartHelper,
                $bugsnag,
                $logHelper,
                $checkoutSession,
                $orderHelper,
                $this->backendUrl,
                $this->redirect,
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

    private function initRequestMap()
    {
        $this->defaultRequestMap = [
            ['bolt_signature', null, $this->boltSignature],
            ['bolt_payload', null, $this->encodedBoltPayload],
            ['store_id', null, self::STORE_ID]
        ];
    }

    private function initRequest($requestMap)
    {
        $request = $this->createMock(\Magento\Framework\App\Request\Http::class);
        $request->expects($this->exactly(3))
            ->method('getParam')
            ->will($this->returnValueMap($requestMap));

        return $request;
    }

    private function createMessageManagerMock()
    {
        $messageManager = $this->createMock(ManagerInterface::class);
        $messageManager->method('addErrorMessage');

        return $messageManager;
    }

    private function createUrlMock()
    {
        $url = $this->createMock(UrlInterface::class);
        $url->expects($this->once())
            ->method('setScope')
            ->with(self::STORE_ID);
        $url->method('getUrl')
            ->willReturn(self::REDIRECT_URL);

        return $url;
    }
}
