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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
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
use ReflectionException;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Framework\App\ObjectManager;
use Magento\TestFramework\Helper\Bootstrap;

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
     * @var VatValidator
     */
    private $vatValidator;

    /**
     * @var AddressCollectTotalsObserverPlugin
     */
    private $addressCollectTotalsObserverPlugin;

    /**
     * @var CollectTotalsObserver
     */
    private $subject;

    /**
     * @var Observer
     */
    private $observer;

    private $objectManager;

    /**
     * Configure common test dependencies
     */
    protected function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->vatValidator = $this->objectManager->create(VatValidator::class);
        $this->subject = $this->objectManager->create(CollectTotalsObserver::class);
        $this->addressCollectTotalsObserverPlugin = $this->objectManager->create(AddressCollectTotalsObserverPlugin::class);
        $this->observer = $this->objectManager->create(Observer::class);
    }

    /**
     * @test
     *
     * @covers ::__construct
     */
    public function __construct_always_setsProperty()
    {
        $instance = new AddressCollectTotalsObserverPlugin($this->vatValidator);
        static::assertAttributeEquals($this->vatValidator, 'vatValidator', $instance);
    }

    /**
     * @test
     * that beforeExecute collects customer email before they are overwritten
     *
     * @covers ::beforeExecute
     */
    public function beforeExecute_ifEmailIsNotCollected_collectsCustomerEmail()
    {
        $quote = TestUtils::createQuote();
        $quote->setCustomerEmail(self::EMAIL);
        $this->observer->setData('quote', $quote);
        $this->addressCollectTotalsObserverPlugin->beforeExecute($this->subject, $this->observer);
        static::assertAttributeEquals(self::EMAIL, 'emailBefore', $this->addressCollectTotalsObserverPlugin);
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

        $quoteMock = $this->createPartialMock(
            Quote::class,
            [
                'getCustomer',
                'setCustomerEmail',
            ]
        );
        $customerMock = $this->createMock(\Magento\Customer\Model\Data\Customer::class);
        $vatValidatorMock = $this->createMock(VatValidator::class);
        $subjectMock = $this->createMock(CollectTotalsObserver::class);
        $observerMock = $this->createPartialMock(Observer::class, ['getQuote', 'getShippingAssignment']);
        $observerMock->method('getQuote')->willReturn($quoteMock);
        $quoteMock->method('getCustomer')->willReturn($customerMock);

        TestHelper::setProperty($this->addressCollectTotalsObserverPlugin, 'emailBefore', self::EMAIL);
        TestHelper::setProperty($this->addressCollectTotalsObserverPlugin, 'vatValidator', $vatValidatorMock);
        $shippingAssignmentMock = $this->createPartialMock(
            ShippingAssignment::class,
            ['getShipping', 'getAddress']
        );
        $shippingAssignmentMock->method('getShipping')->willReturnSelf();
        $quoteAddressMock = $this->createMock(Quote\Address::class);
        $shippingAssignmentMock->method('getAddress')->willReturn($quoteAddressMock);
        $customerMock->method('getStoreId')->willReturn(self::STORE_ID);
        $observerMock->method('getShippingAssignment')->willReturn($shippingAssignmentMock);
        $customerMock->method('getDisableAutoGroupChange')->willReturn($customerDisableAutoGroupChange);
        $vatValidatorMock->method('isEnabled')->with($quoteAddressMock, self::STORE_ID)
            ->willReturn($vatValidatorIsEnabledForAddress);
        $customerMock->method('getId')->willReturn($customerId);

        $quoteMock->expects($expectRestore ? static::once() : static::never())->method('setCustomerEmail')
            ->with(self::EMAIL);
        $customerMock->expects($expectRestore ? static::once() : static::never())->method('setEmail')
            ->with(self::EMAIL);
        $result = null;
        static::assertSame($result, $this->addressCollectTotalsObserverPlugin->afterExecute($subjectMock, $result, $observerMock));
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
