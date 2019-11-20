<?php

namespace Bolt\Boltpay\Test\Unit\Controller\Cart;

use Bolt\Boltpay\Controller\Cart\Email;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class EmailTest extends TestCase
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var MockObject|CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var MockObject|CustomerSession
     */
    private $customerSession;

    /**
     * @var MockObject|Bugsnag
     */
    private $bugsnag;

    /**
     * @var MockObject|CartHelper
     */
    private $cartHelper;

    /**
     * @var MockObject|Quote quote
     */
    private $quote;

    /**
     * @var RequestInterface request
     */
    private $request;

    /**
     * @var Email
     */
    private $currentMock;

    public function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    private function initRequiredMocks()
    {
        //objects needed for constructor
        $this->context = $this->createMock(Context::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->customerSession = $this->createMock(CustomerSession::class);

        $this->bugsnag = $this->createMock(Bugsnag::class);

        $this->cartHelper = $this->createMock(CartHelper::class);

        //objects needed for method
        $this->quote = $this->createMock(Quote::class);
        $this->quote->method('getId')->willReturn('1234');

        $this->request = $this->createMock(RequestInterface::class);

        //methods
        $this->cartHelper->method('quoteResourceSave')->willReturn(null);
        $this->context->method('getRequest')->willReturn($this->request);
    }

    private function initCurrentMock()
    {
        $this->currentMock = $this->getMockBuilder(Email::class)
            ->setConstructorArgs([
                $this->context,
                $this->checkoutSession,
                $this->customerSession,
                $this->bugsnag,
                $this->cartHelper
            ])
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    public function testExecute_quoteDoesNotExist()
    {
        $this->checkoutSession->method('getQuote')->willReturn(null);
        $exception = new LocalizedException(__('Quote does not exist.'));
        $this->bugsnag->expects($this->once())
            ->method('notifyException')
            ->with($this->equalTo($exception));
        $this->currentMock->execute();
    }

    public function testExecute_noQuoteId()
    {
        $this->quote->method('getId')->willReturn(false);
        $exception = new LocalizedException(__('Quote does not exist.'));
        $this->bugsnag->expects($this->once())
                    ->method('notifyException')
                    ->with($this->equalTo($exception));
        $this->currentMock->execute();
    }

    public function testExecute_noEmail()
    {
        $this->checkoutSession->method('getQuote')->willReturn($this->quote);
        $this->request->method('getParam')->willReturn('');
        $exception = new LocalizedException(__('No email received.'));
        $this->bugsnag->expects($this->once())
                    ->method('notifyException')
                    ->with($this->equalTo($exception));
        $this->currentMock->execute();
    }

    /**
     * This doesn't really seem to test invalid emails as the validateEmail method
     * needs to be mocked. Generally just making sure that if validateEmail comes
     * back false we error handle properly
     */
    public function testExecute_invalidEmail()
    {
        $invalidEmail = 'invalidemail';
        $this->checkoutSession->method('getQuote')->willReturn($this->quote);
        $this->request->method('getParam')->willReturn($invalidEmail);
        $this->cartHelper->method('validateEmail')->willReturn(false);
        $exception = new LocalizedException(__('Invalid email: %1', $invalidEmail));
        $this->bugsnag->expects($this->once())
                    ->method('notifyException')
                    ->with($this->equalTo($exception));
        $this->currentMock->execute();
    }

    public function testExecute_notifyException()
    {
        $this->bugsnag->expects($this->once())->method('notifyException');
        $this->currentMock->execute();
    }

    public function testExecute_happyPath()
    {
        $this->checkoutSession->method('getQuote')->willReturn($this->quote);
        $this->cartHelper->method('validateEmail')->willReturn(true);
        $this->request->method('getParam')->willReturn('example@email.com');
        $this->checkoutSession->expects($this->once())->method('getQuote');
        $this->cartHelper->expects($this->once())->method('quoteResourceSave');
        $this->currentMock->execute();
    }
}
