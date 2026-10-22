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
 *
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.boltapp.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Observer\Adminhtml\Sales;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Model\CustomerCreditCardFactory as CustomerCreditCardFactory;
use Bolt\Boltpay\Model\ResponseFactory;
use Bolt\Boltpay\Observer\Adminhtml\Sales\RechargeCustomer;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class RechargeCustomerTest
 *
 * @coversDefaultClass \Bolt\Boltpay\Observer\Adminhtml\Sales\RechargeCustomer
 */
class RechargeCustomerTest extends BoltTestCase
{
    const ID = '111';
    const QUOTE_ID = '112';
    const REFERENCE = 'DBT9-RL78-TTMM';
    const REFERENCE_URL = 'https://merchant-sandbox.boltapp.com/transaction/8NLY-4NJQ-KFH6';

    /**
     * @var \Bolt\Boltpay\Observer\Adminhtml\Sales\RechargeCustomer
     */
    private $rechargeCustomerMock;

    /**
     * @var Observer
     */
    private $observerMock;

    /**
     * @var Order
     */
    private $orderMock;

    /**
     * @var Payment
     */
    private $paymentMock;

    /**
     * @var Bugsnag
     */
    private $bugsnagMock;

    /**
     * @var Http
     */
    private $requestMock;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var ResponseFactory
     */
    private $responseFactory;

    /**
     * @var CustomerCreditCardFactory
     */
    private $customerCreditCardFactoryMock;

    /**
     * @test
     */
    #[Test]
    public function execute()
    {
        $this->paymentMock->expects(self::any())->method('getMethod')->willReturn(\Bolt\Boltpay\Model\Payment::METHOD_CODE);
        $this->orderMock->expects(self::any())->method('getPayment')->willReturn($this->paymentMock);

        $this->observerMock->expects(self::any())->method('getEvent')->willReturnSelf();
        $this->observerMock->expects(self::any())->method('getOrder')->willReturn($this->orderMock);
        $this->requestMock->expects(self::any())->method('getParam')->with('bolt-credit-cards')->willReturn(self::ID);

        $this->customerCreditCardFactoryMock->expects(self::once())->method('create')->willReturnSelf();
        $this->customerCreditCardFactoryMock->expects(self::once())->method('load')->with(self::ID)->willReturnSelf();
        $this->customerCreditCardFactoryMock->expects(self::once())->method('recharge')->with($this->orderMock)->willReturn($this->responseFactory);

        $responseData = new \stdClass();
        $responseData->transaction = new \stdClass();
        $responseData->transaction->reference = self::REFERENCE;

        $this->responseFactory->expects(self::once())->method('getResponse')->willReturn($responseData);
        $this->orderHelper->expects(self::once())->method('formatReferenceUrl')->with(self::REFERENCE)->willReturn(self::REFERENCE_URL);
        $this->orderMock->expects(self::once())->method('addStatusHistoryComment')->willReturnSelf();

        $this->assertTrue($this->rechargeCustomerMock->execute($this->observerMock));
    }

    /**
     * @test
     */
    #[Test]
    public function execute_withoutPayment()
    {
        $this->orderMock->expects(self::once())->method('getPayment')->willReturn(null);
        $this->observerMock->expects(self::once())->method('getEvent')->willReturnSelf();
        $this->observerMock->expects(self::once())->method('getOrder')->willReturn($this->orderMock);
        $this->assertFalse($this->rechargeCustomerMock->execute($this->observerMock));
    }

    /**
     * @test
     */
    #[Test]
    public function execute_withInvalidPaymentMethod()
    {
        $this->paymentMock->expects(self::any())->method('getMethod')->willReturn('is_not_boltpay');
        $this->orderMock->expects(self::any())->method('getPayment')->willReturn($this->paymentMock);

        $this->observerMock->expects(self::any())->method('getEvent')->willReturnSelf();
        $this->observerMock->expects(self::any())->method('getOrder')->willReturn($this->orderMock);

        $this->assertFalse($this->rechargeCustomerMock->execute($this->observerMock));
    }

    /**
     * @test
     */
    #[Test]
    public function execute_withException()
    {
        $this->paymentMock->expects(self::any())->method('getMethod')->willReturn(\Bolt\Boltpay\Model\Payment::METHOD_CODE);
        $this->orderMock->expects(self::any())->method('getPayment')->willReturn($this->paymentMock);

        $this->observerMock->expects(self::any())->method('getEvent')->willReturnSelf();
        $this->observerMock->expects(self::any())->method('getOrder')->willReturn($this->orderMock);
        $this->requestMock->expects(self::any())->method('getParam')->with('bolt-credit-cards')->willReturn(self::ID);

        $this->customerCreditCardFactoryMock->expects(self::once())->method('create')->willReturnSelf();
        $this->customerCreditCardFactoryMock->expects(self::once())->method('load')->with(self::ID)->willReturnSelf();
        $this->customerCreditCardFactoryMock->expects(self::once())->method('recharge')->with($this->orderMock)->willThrowException(new \Exception('Error'));

        $this->bugsnagMock->expects(self::once())->method('notifyException')->willReturnSelf();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error');
        $this->rechargeCustomerMock->execute($this->observerMock);
    }

    protected function setUpInternal()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    private function initRequiredMocks()
    {
        $this->requestMock = $this->getMockBuilderWithMethods(Http::class, ['getParam'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->customerCreditCardFactoryMock = $this->getMockBuilderWithMethods(CustomerCreditCardFactory::class, ['create', 'load', 'getConsumerId', 'getCreditCardId', 'recharge', 'getResponse'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->paymentMock = $this->getMockBuilderWithMethods(Payment::class, ['getMethod'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderMock = $this->getMockBuilderWithMethods(Order::class, ['getPayment', 'addStatusHistoryComment'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->observerMock = $this->getMockBuilderWithMethods(Observer::class, ['getEvent', 'getOrder'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->bugsnagMock = $this->getMockBuilderWithMethods(Bugsnag::class, ['notifyException'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderHelper = $this->getMockBuilderWithMethods(OrderHelper::class, ['formatReferenceUrl'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->responseFactory = $this->getMockBuilderWithMethods(ResponseFactory::class, ['getResponse'])
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function initCurrentMock()
    {
        $this->rechargeCustomerMock = $this->getMockBuilderWithMethods(RechargeCustomer::class, ['_init'])
            ->setConstructorArgs([
                $this->bugsnagMock,
                $this->requestMock,
                $this->customerCreditCardFactoryMock,
                $this->orderHelper
            ])
            ->getMock();
    }
}
