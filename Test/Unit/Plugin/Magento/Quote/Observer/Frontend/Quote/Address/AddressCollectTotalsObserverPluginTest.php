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

namespace Bolt\Boltpay\Test\Unit\Plugin\Magento\Quote\Observer\Frontend\Quote\Address;

use Bolt\Boltpay\Plugin\Magento\Quote\Observer\Frontend\Quote\Address\AddressCollectTotalsObserverPlugin;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Customer\Model\Customer;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ShippingAssignment;
use Magento\Quote\Observer\Frontend\Quote\Address\CollectTotalsObserver;
use Magento\Quote\Observer\Frontend\Quote\Address\VatValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\Magento\Quote\Observer\Frontend\Quote\Address\AddressCollectTotalsObserverPlugin
 */
class AddressCollectTotalsObserverPluginTest extends BoltTestCase
{

    /** @var string Test email */
    const EMAIL = 'example@example.com';

    /** @var int Test customer id */
    const CUSTOMER_ID = 123;

    /** @var int Test store id */
    const STORE_ID = 1;

    /**
     * @var VatValidator|MockObject
     */
    private $vatValidatorMock;

    /**
     * @var AddressCollectTotalsObserverPlugin|MockObject
     */
    private $currentMock;

    /**
     * @var Quote|MockObject
     */
    private $quoteMock;

    /**
     * @var CollectTotalsObserver|MockObject
     */
    private $subjectMock;

    /**
     * @var Observer|MockObject
     */
    private $observerMock;

    /**
     * @var Customer|MockObject
     */
    private $customerMock;

    /**
     * Configure common test dependencies
     */
    protected function setUpInternal()
    {
        $this->vatValidatorMock = $this->createMock(VatValidator::class);
        $this->currentMock = $this->getMockBuilder(AddressCollectTotalsObserverPlugin::class)
            ->enableOriginalConstructor()
            ->setConstructorArgs([$this->vatValidatorMock])
            ->setMethods(['isEnabled'])
            ->getMock();
        $this->quoteMock = $this->createPartialMock(
            Quote::class,
            [
                'getCustomer',
                'setCustomerEmail',
                'getCustomerEmail',
            ]
        );
        $this->customerMock = $this->createMock(\Magento\Customer\Model\Data\Customer::class);
        $this->quoteMock->method('getCustomer')->willReturn($this->customerMock);
        $this->subjectMock = $this->createMock(CollectTotalsObserver::class);
        $this->observerMock = $this->createPartialMock(Observer::class, ['getQuote', 'getShippingAssignment']);
        $this->observerMock->method('getQuote')->willReturn($this->quoteMock);
    }

    /**
     * @test
     *
     * @covers ::__construct
     */
    public function __construct_always_setsProperty()
    {
        $instance = new AddressCollectTotalsObserverPlugin($this->vatValidatorMock);
        static::assertAttributeEquals($this->vatValidatorMock, 'vatValidator', $instance);
    }

    /**
     * @test
     * that beforeExecute collects customer email before they are overwritten
     *
     * @covers ::beforeExecute
     */
    public function beforeExecute_ifEmailIsNotCollected_collectsCustomerEmail()
    {
        $this->observerMock->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->expects(static::once())->method('getCustomerEmail')->willReturn(self::EMAIL);
        $this->currentMock->beforeExecute($this->subjectMock, $this->observerMock);
        static::assertAttributeEquals(self::EMAIL, 'emailBefore', $this->currentMock);
        $this->currentMock->beforeExecute($this->subjectMock, $this->observerMock);
        static::assertAttributeEquals(self::EMAIL, 'emailBefore', $this->currentMock);
    }

    /**
     * @test
     * that afterExecute restores customer email value on quote with $emailBefore if
     *  1. quote customer is guest (customer_id is null)
     *  2. VAT validation is enabled in the configuration
     *
     * @dataProvider afterExecute_ifPreconditionsAreMet_restoresQuoteCustomerEmailProvider
     *
     * @covers ::afterExecute
     * @param bool $customerDisableAutoGroupChange whether quote customer is exempt from auto group change
     * @param bool $vatValidatorIsEnabledForAddress whether VAT validation is enabled for the shipping address
     * @param int|null $customerId quote customer id
     * @param bool $expectRestore whether to expect quote customer email value to be restored
     *
     * @throws ReflectionException if emailBefore property is not set
     */
    public function afterExecute_ifPreconditionsAreMet_restoresQuoteCustomerEmail(
        $customerDisableAutoGroupChange,
        $vatValidatorIsEnabledForAddress,
        $customerId,
        $expectRestore
    ) {
        TestHelper::setProperty($this->currentMock, 'emailBefore', self::EMAIL);
        $shippingAssignmentMock = $this->createPartialMock(
            ShippingAssignment::class,
            ['getShipping', 'getAddress']
        );
        $shippingAssignmentMock->method('getShipping')->willReturnSelf();
        $quoteAddressMock = $this->createMock(Quote\Address::class);
        $shippingAssignmentMock->method('getAddress')->willReturn($quoteAddressMock);
        $this->customerMock->method('getStoreId')->willReturn(self::STORE_ID);
        $this->observerMock->method('getShippingAssignment')->willReturn($shippingAssignmentMock);
        $this->customerMock->method('getDisableAutoGroupChange')->willReturn($customerDisableAutoGroupChange);
        $this->vatValidatorMock->method('isEnabled')->with($quoteAddressMock, self::STORE_ID)
            ->willReturn($vatValidatorIsEnabledForAddress);
        $this->customerMock->method('getId')->willReturn($customerId);

        $this->quoteMock->expects($expectRestore ? static::once() : static::never())->method('setCustomerEmail')
            ->with(self::EMAIL);
        $this->customerMock->expects($expectRestore ? static::once() : static::never())->method('setEmail')
            ->with(self::EMAIL);
        $result = null;
        static::assertSame($result, $this->currentMock->afterExecute($this->subjectMock, $result, $this->observerMock));
    }

    /**
     * Data provider for {@see afterExecute_ifPreconditionsAreMet_restoresQuoteCustomerEmail}
     *
     * @return array[]
     */
    public function afterExecute_ifPreconditionsAreMet_restoresQuoteCustomerEmailProvider()
    {
        return [
            [
                'customerDisableAutoGroupChange'  => false,
                'vatValidatorIsEnabledForAddress' => false,
                'customerId'                      => null,
                'expectRestore'                   => false,
            ],
            [
                'customerDisableAutoGroupChange'  => true,
                'vatValidatorIsEnabledForAddress' => false,
                'customerId'                      => null,
                'expectRestore'                   => false,
            ],
            [
                'customerDisableAutoGroupChange'  => true,
                'vatValidatorIsEnabledForAddress' => true,
                'customerId'                      => null,
                'expectRestore'                   => false,
            ],
            [
                'customerDisableAutoGroupChange'  => false,
                'vatValidatorIsEnabledForAddress' => false,
                'customerId'                      => self::CUSTOMER_ID,
                'expectRestore'                   => false,
            ],
            [
                'customerDisableAutoGroupChange'  => true,
                'vatValidatorIsEnabledForAddress' => false,
                'customerId'                      => self::CUSTOMER_ID,
                'expectRestore'                   => false,
            ],
            [
                'customerDisableAutoGroupChange'  => true,
                'vatValidatorIsEnabledForAddress' => true,
                'customerId'                      => self::CUSTOMER_ID,
                'expectRestore'                   => false,
            ],
            [
                'customerDisableAutoGroupChange'  => false,
                'vatValidatorIsEnabledForAddress' => true,
                'customerId'                      => self::CUSTOMER_ID,
                'expectRestore'                   => false,
            ],
            [
                'customerDisableAutoGroupChange'  => false,
                'vatValidatorIsEnabledForAddress' => true,
                'customerId'                      => null,
                'expectRestore'                   => true,
            ],
        ];
    }
}
