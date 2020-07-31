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

namespace Bolt\Boltpay\Test\Unit\Observer\Adminhtml\Sales;

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Observer\Adminhtml\Sales\RechargeCustomer;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\App\Request\Http;
use Bolt\Boltpay\Model\CustomerCreditCardFactory as CustomerCreditCardFactory;
use Bolt\Boltpay\Model\ResponseFactory;
use Bolt\Boltpay\Helper\Order as OrderHelper;

/**
 * Class RechargeCustomerTest
 * @coversDefaultClass \Bolt\Boltpay\Model\Payment
 */
class RechargeCustomerTest extends TestCase
{
    const ID  = '111';
    const QUOTE_ID  = '112';
    const REFERENCE  = 'DBT9-RL78-TTMM';
    const REFERENCE_URL  = 'https://merchant-sandbox.bolt.com/transaction/8NLY-4NJQ-KFH6';

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

    private $orderHelper;
    private $responseFactory;

    /**
     * @var CustomerCreditCardFactory
     */
    private $customerCreditCardFactoryMock;

    protected function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    private function initRequiredMocks()
    {
        $this->requestMock = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()
            ->setMethods(['getParam'])
            ->getMock();

        $this->customerCreditCardFactoryMock = $this->getMockBuilder(CustomerCreditCardFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create','load','getConsumerId','getCreditCardId','recharge', 'getResponse'])
            ->getMock();

        $this->paymentMock = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->setMethods(['getMethod'])
            ->getMock();

        $this->orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPayment','addStatusHistoryComment'])
            ->getMock();

        $this->observerMock = $this->getMockBuilder(Observer::class)
            ->disableOriginalConstructor()
            ->setMethods(['getEvent', 'getOrder'])
            ->getMock();

        $this->bugsnagMock = $this->getMockBuilder(Bugsnag::class)
            ->disableOriginalConstructor()
            ->setMethods(['notifyException'])
            ->getMock();

        $this->orderHelper = $this->getMockBuilder(OrderHelper::class)
            ->disableOriginalConstructor()
            ->setMethods(['formatReferenceUrl'])
            ->getMock();

        $this->responseFactory = $this->getMockBuilder(ResponseFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['getResponse'])
            ->getMock();
    }

    private function initCurrentMock()
    {
        $this->rechargeCustomerMock = $this->getMockBuilder(RechargeCustomer::class)
            ->setConstructorArgs([
                $this->bugsnagMock,
                $this->requestMock,
                $this->customerCreditCardFactoryMock,
                $this->orderHelper
            ])
            ->setMethods(['_init'])
            ->getMock();
    }

    /**
     * @test
     */
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
}
