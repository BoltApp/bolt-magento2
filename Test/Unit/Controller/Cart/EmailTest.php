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

namespace Bolt\Boltpay\Test\Unit\Controller\Cart;

use Bolt\Boltpay\Controller\Cart\Email;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

/**
 * Class EmailTest
 * @package Bolt\Boltpay\Test\Unit\Controller\Cart
 * @coversDefaultClass \Bolt\Boltpay\Controller\Cart\Email
 */
class EmailTest extends BoltTestCase
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
    private $email;

    private $objectManager;

    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->checkoutSession = Bootstrap::getObjectManager()->create(CheckoutSession::class);
        $this->email = (new ObjectManager($this))->getObject(Email::class);
    }

    /**
     * @test
     */
    public function execute_quoteDoesNotExist()
    {
        $bugsnag = $this->createMock(Bugsnag::class);
        $exception = new LocalizedException(__('Quote does not exist.'));
        $bugsnag->method('notifyException')->with($this->equalTo($exception));
        TestHelper::setProperty($this->email, 'bugsnag', $bugsnag);
        $this->email->execute();
    }

    /**
     * @test
     */
    public function execute_noQuoteId()
    {
        $bugsnag = $this->createMock(Bugsnag::class);
        $this->checkoutSession->setQuoteId(false);
        $exception = new LocalizedException(__('Quote does not exist.'));
        $bugsnag->method('notifyException')->with($this->equalTo($exception));
        TestHelper::setProperty($this->email, 'bugsnag', $bugsnag);
        $this->email->execute();
    }

    /**
     * @test
     */
    public function execute_noEmail()
    {
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();
        $this->checkoutSession->setQuoteId($quoteId);
        $exception = new LocalizedException(__('No email received.'));
        $bugsnag = $this->createMock(Bugsnag::class);
        $bugsnag->expects($this->once())->method('notifyException')->with($this->equalTo($exception));
        TestHelper::setProperty($this->email, 'bugsnag', $bugsnag);
        TestHelper::setProperty($this->email, 'checkoutSession', $this->checkoutSession);
        $this->email->execute();

    }

    /**
     * @test
     */
    public function execute_invalidEmail()
    {
        $invalidEmail = 'invalidemail';
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();

        $this->checkoutSession->setQuoteId($quoteId);
        /** @var $request RequestInterface */
        $request = Bootstrap::getObjectManager()->get(RequestInterface::class);
        $request->setParams(['email' => $invalidEmail]);

        $exception = new LocalizedException(__('Invalid email: %1', $invalidEmail));
        $bugsnag = $this->createPartialMock(Bugsnag::class,['notifyException']);
        $bugsnag->expects($this->once())->method('notifyException')->with($this->equalTo($exception));

        TestHelper::setProperty($this->email, 'bugsnag', $bugsnag);
        TestHelper::setInaccessibleProperty($this->email, '_request', $request);
        TestHelper::setProperty($this->email, 'checkoutSession', $this->checkoutSession);
        $this->email->execute();
    }

    /**
     * @test
     */
    public function execute_happyPath()
    {
        $validEmail = 'integration@bolt.com';
        $quote = TestUtils::createQuote();
        $quoteId = $quote->getId();

        $this->checkoutSession->setQuoteId($quoteId);
        /** @var $request RequestInterface */
        $request = Bootstrap::getObjectManager()->get(RequestInterface::class);
        $request->setParams(['email' => $validEmail]);

        $cartHelper = Bootstrap::getObjectManager()->create(CartHelper::class);

        TestHelper::setInaccessibleProperty($this->email, '_request', $request);
        TestHelper::setProperty($this->email, 'checkoutSession', $this->checkoutSession);
        TestHelper::setProperty($this->email, 'cartHelper', $cartHelper);

        $this->email->execute();
        $this->assertEquals(
            $validEmail,
            Bootstrap::getObjectManager()->create(Quote::class)->load($quoteId)->getCustomerEmail()
        );
    }
}
