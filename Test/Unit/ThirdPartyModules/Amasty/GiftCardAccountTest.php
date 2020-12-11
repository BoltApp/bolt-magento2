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

namespace Bolt\Boltpay\Test\Unit\ThirdPartyModules\Amasty;

use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\ThirdPartyModules\Amasty\GiftCardAccount;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magento\Quote\Model\Quote;

/**
 * @coversDefaultClass \Bolt\Boltpay\ThirdPartyModules\Amasty\GiftCardAccount
 */
class GiftCardAccountTest extends TestCase
{
    /** @var int Test order id */
    const ORDER_ID = 10001;

    /** @var int Test quote id */
    const QUOTE_ID = 1234;

    /** @var int Test immutable quote id */
    const IMMUTABLE_QUOTE_ID = 1001;

    /** @var int Test parent quote id */
    const PARENT_QUOTE_ID = 1000;

    /** @var int Test customer id */
    const CUSTOMER_ID = 234;

    /** @var int Test product id */
    const PRODUCT_ID = 20102;

    /** @var int Test product price */
    const PRODUCT_PRICE = 100;

    /** @var int Test order increment id */
    const ORDER_INCREMENT_ID = 100010001;

    /** @var int Test store id */
    const STORE_ID = 1;

    /** @var string Test email address */
    const EMAIL_ADDRESS = 'integration@bolt.com';

    /** @var string Test product SKU */
    const PRODUCT_SKU = 'TestProduct';

    /** @var string */
    const CURRENCY_CODE = 'USD';

    /**
     * @var \Bolt\Boltpay\Helper\Bugsnag|MockObject mocked instance of the Bolt Bugsnag helper
     */
    private $bugsnagHelperMock;

    /**
     * @var \Amasty\GiftCardAccount\Model\GiftCardAccount\Repository|MockObject
     * mocked instance of the Amasty Giftcard repository
     */
    private $giftcardRepositoryMock;

    /**
     * @var \Amasty\GiftCardAccount\Model\GiftCardExtension\Order\Repository|MockObject
     */
    private $giftcardOrderRepositoryMock;

    /**
     * @var \Magento\Sales\Model\Order|MockObject mocked instance of the Magento Order model
     */
    private $orderMock;

    /**
     * @var \Amasty\GiftCardAccount\Api\Data\GiftCardOrderInterface|MockObject
     * mocked instance of the Order Extension Attribute added by Amasty Giftcard
     */
    private $giftcardOrderExtensionMock;

    /**
     * @var GiftCardAccount|MockObject mocked instance of the class tested
     */
    private $currentMock;

    /**
     * @var \Bolt\Boltpay\Helper\Discount|\PHPUnit_Framework_MockObject_MockObject
     */
    private $discountHelper;

    /**
     * @var \Magento\Framework\App\ResourceConnection|\PHPUnit_Framework_MockObject_MockObject
     */
    private $resourceConnection;

    /**
     * @var Total|MockObject
     */
    private $quoteAddressTotal;

    /**
     * @var string[]
     */
    private $testAddressData = [
        'company'         => "",
        'country'         => "United States",
        'country_code'    => "US",
        'email'           => self::EMAIL_ADDRESS,
        'first_name'      => "IntegrationBolt",
        'last_name'       => "BoltTest",
        'locality'        => "New York",
        'phone'           => "132 231 1234",
        'postal_code'     => "10011",
        'region'          => "New York",
        'street_address1' => "228 7th Avenue",
        'street_address2' => "228 7th Avenue 2",
    ];

    /**
     * @var Product|MockObject
     */
    private $productMock;

    /**
     * @var \Amasty\GiftCardAccount\Api\GiftCardQuoteRepositoryInterface|MockObject
     */
    private $giftcardQuoteRepositoryMock;

    /**
     * @var \Amasty\GiftCardAccount\Api\GiftCardAccountRepositoryInterface|MockObject
     */
    private $giftcardAccountRepositoryMock;

    /**
     * @var \Amasty\GiftCardAccount\Model\GiftCardExtension\Quote\Quote|MockObject
     */
    private $giftcardQuoteMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp(): void
    {
        $this->giftcardAccountRepositoryMock = $this->getMockBuilder(
            '\Amasty\GiftCardAccount\Api\GiftCardAccountRepositoryInterface'
        )
            ->disableAutoload()
            ->disableOriginalClone()
            ->disableOriginalConstructor()
            ->setMethods(['getById'])
            ->getMock();
        $this->giftcardQuoteRepositoryMock = $this->getMockBuilder(
            '\Amasty\GiftCardAccount\Api\GiftCardQuoteRepositoryInterface'
        )
            ->disableAutoload()
            ->disableOriginalClone()
            ->disableOriginalConstructor()
            ->setMethods(['getByQuoteId'])
            ->getMock();
        $this->giftcardQuoteMock = $this->getMockBuilder(
            '\Amasty\GiftCardAccount\Api\GiftCardQuoteRepositoryInterface'
        )
            ->disableAutoload()
            ->disableOriginalClone()
            ->disableOriginalConstructor()
            ->setMethods(['getGiftCards'])
            ->getMock();
        $this->productMock = $this->createPartialMock(Product::class, ['getDescription', 'getTypeInstance']);
        $this->productMock->method('getTypeInstance')->willReturnSelf();
        $this->bugsnagHelperMock = $this->createMock(\Bolt\Boltpay\Helper\Bugsnag::class);
        $this->discountHelper = $this->createMock(\Bolt\Boltpay\Helper\Discount::class);
        $this->resourceConnection = $this->createMock(\Magento\Framework\App\ResourceConnection::class);
        $this->giftcardRepositoryMock = $this->getMockBuilder(
            '\Amasty\GiftCardAccount\Model\GiftCardAccount\Repository'
        )->setMethods(['save', 'getById'])->disableOriginalConstructor()->disableAutoload()->getMock();
        $this->giftcardOrderRepositoryMock = $this->getMockBuilder(
            '\Amasty\GiftCardAccount\Model\GiftCardExtension\Order\Repository'
        )->setMethods(['getByOrderId'])->disableOriginalConstructor()->disableAutoload()->getMock();
        $this->orderMock = $this->createMock(\Magento\Sales\Model\Order::class);
        $this->orderMock->method('getId')->willReturn(self::ORDER_ID);
        $this->quoteAddressTotal = $this->createPartialMock(Total::class, ['getValue', 'setValue', 'getTitle']);

        $this->currentMock = $this->getMockBuilder(GiftCardAccount::class)
            ->setMethods(null)->setConstructorArgs(
                [$this->bugsnagHelperMock, $this->discountHelper, $this->resourceConnection]
            )->getMock();
        $this->giftcardOrderExtensionMock = $this->getMockBuilder(
            '\Amasty\GiftCardAccount\Model\GiftCardExtension\Order\Order'
        )->setMethods(['getGiftCards'])->disableOriginalConstructor()->disableAutoload()->getMock();
    }

    /**
     * @test
     * that constructor sets provided arguments to properties
     *
     * @covers ::__construct
     */
    public function __construct_always_setsProperties()
    {
        $instance = new GiftCardAccount(
            $this->bugsnagHelperMock,
            $this->discountHelper,
            $this->resourceConnection
        );
        static::assertAttributeEquals($this->bugsnagHelperMock, 'bugsnagHelper', $instance);
    }

    /**
     * @test
     * that beforeFailedPaymentOrderSave doesn't affect any giftcards if non are applied to the order
     *
     * @covers ::beforeFailedPaymentOrderSave
     */
    public function beforeFailedPaymentOrderSave_withNoGiftcardsOnOrder_doesNotRestoreBalance()
    {
        $this->giftcardOrderRepositoryMock->expects(static::once())->method('getByOrderId')
            ->with(self::ORDER_ID)->willThrowException(new NoSuchEntityException(__('Gift Card Order not found.')));
        $this->giftcardRepositoryMock->expects(static::never())->method('save');
        $this->currentMock->beforeFailedPaymentOrderSave(
            $this->giftcardRepositoryMock,
            $this->giftcardOrderRepositoryMock,
            $this->orderMock
        );
    }

    /**
     * @test
     * that beforeFailedPaymentOrderSave doesn't affect any giftcards if non are applied to the order
     *
     * @covers ::beforeFailedPaymentOrderSave
     */
    public function beforeFailedPaymentOrderSave_withGiftcardsAppliedToOrder_restoresGiftcardBalance()
    {
        $this->giftcardOrderRepositoryMock->expects(static::once())->method('getByOrderId')
            ->with(self::ORDER_ID)->willReturn($this->giftcardOrderExtensionMock);
        $this->giftcardOrderExtensionMock->expects(static::once())->method('getGiftCards')->willReturn(
            [
                ['id' => 3, 'b_amount' => 123.45],
                ['id' => 5, 'b_amount' => 456.78],
                ['id' => 15, 'b_amount' => 232.23],
            ]
        );
        $giftcard1 = $this->createGiftcardMock();
        $giftcard2 = $this->createGiftcardMock();
        $giftcard3 = $this->createGiftcardMock();
        $this->giftcardRepositoryMock->expects(static::exactly(3))->method('getById')
            ->withConsecutive([3], [5])->willReturnMap(
                [
                    [3, $giftcard1],
                    [5, $giftcard2],
                    [15, $giftcard3],
                ]
            );
        $giftcard1->expects(static::once())->method('setCurrentValue')
            ->with((float)(123.45 + 234.23));
        $giftcard1->expects(static::once())->method('getCurrentValue')->willReturn(234.23);
        $giftcard1->expects(static::once())->method('setStatus')->with(1);
        $giftcard2->expects(static::once())->method('setCurrentValue')
            ->with((float)(456.78 + 321.54));
        $giftcard2->expects(static::once())->method('getCurrentValue')->willReturn(321.54);
        $giftcard2->expects(static::once())->method('setStatus')->with(1);
        $exception = new \Magento\Framework\Exception\LocalizedException(__(''));
        $giftcard3->expects(static::once())->method('setCurrentValue')
            ->with((float)(232.23 + 521.23))->willThrowException($exception);
        $giftcard3->expects(static::once())->method('getCurrentValue')->willReturn(521.23);
        $this->giftcardRepositoryMock->expects(static::exactly(2))->method('save')
            ->withConsecutive([$giftcard1], [$giftcard3]);
        $this->bugsnagHelperMock->expects(static::once())->method('notifyException')->with($exception);
        $this->currentMock->beforeFailedPaymentOrderSave(
            $this->giftcardRepositoryMock,
            $this->giftcardOrderRepositoryMock,
            $this->orderMock
        );
    }

    /**
     * @test
     * that collectDiscounts properly handles Amasty Giftcert by reading amount from giftcert balance
     *
     * @covers ::collectDiscounts
     */
    public function collectDiscounts_withAmastyGiftcard_collectsAmastyGiftcard()
    {
        $currentMock = $this->currentMock;
        $shippingAddress = $this->getAddressMock();
        $immutableQuote = $this->getQuoteMock($this->getAddressMock(), $shippingAddress);
        $shippingAddress->expects(static::any())->method('getDiscountAmount')->willReturn(false);
        $this->discountHelper->expects(static::never())->method('getUnirgyGiftCertBalanceByCode');
        $this->discountHelper->expects(static::exactly(6))
            ->method('getBoltDiscountType')->with('by_fixed')->willReturn(
                'fixed_amount'
            );
        $this->discountHelper->expects(static::once())->method('getAmastyPayForEverything')->willReturn(true);
        $this->quoteAddressTotal->expects(static::once())->method('getValue')->willReturn(5);
        $immutableQuote->expects(static::any())->method('getTotals')
            ->willReturn([Discount::AMASTY_GIFTCARD => $this->quoteAddressTotal]);
        $totalAmount = 100000; // cents
        $diff = 0;
        $paymentOnly = true;

        $this->giftcardQuoteRepositoryMock->expects(static::once())->method('getByQuoteId')
            ->with(self::IMMUTABLE_QUOTE_ID)->willReturn($this->giftcardQuoteMock);
        $this->giftcardQuoteMock->expects(static::once())->method('getGiftCards')
            ->willReturn(
                [
                    ['id' => 3,],
                    ['id' => 5,],
                    ['id' => 15,],
                ]
            );
        $this->giftcardAccountRepositoryMock->expects(static::exactly(3))
            ->method('getById')->willReturnMap(
                [
                    [3, $this->createGiftcardMock(12.34, 'QWERTY')],
                    [5, $this->createGiftcardMock(543.21, 'TEST')],
                    [15, $this->createGiftcardMock(1.23, 'ZXCVB')],
                ]
            );

        list($discounts, $totalAmountResult, $diffResult) = $currentMock->collectDiscounts(
            [
                [],
                $totalAmount,
                $diff
            ],
            $this->giftcardAccountRepositoryMock,
            $this->giftcardQuoteRepositoryMock,
            $immutableQuote,
            $this->getQuoteMock(),
            $paymentOnly
        );
        static::assertEquals($diffResult, $diff);
        $expectedDiscountAmount = 100 * (12.34 + 543.21 + 1.23);
        $expectedTotalAmount = $totalAmount - $expectedDiscountAmount;
        $expectedDiscount = [
            [
                'description'       => 'Gift Card QWERTY',
                'amount'            => 1234,
                'discount_category' => 'giftcard',
                'reference'         => 'QWERTY',
                'discount_type'     => 'fixed_amount',
                'type'              => 'fixed_amount',
            ],
            [
                'description'       => 'Gift Card TEST',
                'amount'            => 54321,
                'discount_category' => 'giftcard',
                'reference'         => 'TEST',
                'discount_type'     => 'fixed_amount',
                'type'              => 'fixed_amount',
            ],
            [
                'description'       => 'Gift Card ZXCVB',
                'amount'            => 123,
                'discount_category' => 'giftcard',
                'reference'         => 'ZXCVB',
                'discount_type'     => 'fixed_amount',
                'type'              => 'fixed_amount',
            ],
        ];
        static::assertEquals($expectedDiscount, $discounts);
        static::assertEquals($expectedTotalAmount, $totalAmountResult);
    }


    /**
     * Creates a mocked instance of the Amasty Giftcard Account
     * @param null|float  $currentValue
     * @param null|string $code
     * @return MockObject|\Amasty\GiftCardAccount\Model\GiftCardAccount\Account
     */
    private function createGiftcardMock($currentValue = null, $code = null)
    {
        $accountMock = $this->getMockBuilder('\Amasty\GiftCardAccount\Model\GiftCardAccount\Account')
            ->setMethods(['setCurrentValue', 'getCurrentValue', 'setStatus', 'getCodeModel', 'getCode'])
            ->disableOriginalConstructor()
            ->disableAutoload()
            ->disableOriginalClone()
            ->getMock();
        if ($currentValue !== null) {
            $accountMock->method('getCurrentValue')->willReturn($currentValue);
        }
        if ($code !== null) {
            $accountMock->method('getCodeModel')->willReturnSelf();
            $accountMock->method('getCode')->willReturn($code);
        }
        return $accountMock;
    }

    /**
     * Creates mocked instance of quote address
     *
     * @return Quote\Address|MockObject
     */
    private function getAddressMock()
    {
        $addressMock = $this->getMockBuilder(Quote\Address::class)
            ->setMethods(
                [
                    'getFirstname',
                    'getLastname',
                    'getCompany',
                    'getTelephone',
                    'getStreetLine',
                    'getCity',
                    'getRegion',
                    'getPostcode',
                    'getCountryId',
                    'getEmail',
                    'getDiscountAmount',
                    'getCouponCode',
                    'getDiscountDescription'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $this->setUpAddressMock($addressMock);

        return $addressMock;
    }

    /**
     * Configures provided address mock to return valid address data
     *
     * @param MockObject $addressMock
     */
    private function setUpAddressMock($addressMock)
    {
        $addressMock->method('getFirstname')->willReturn($this->testAddressData['first_name']);
        $addressMock->method('getLastname')->willReturn($this->testAddressData['last_name']);
        $addressMock->method('getCompany')->willReturn($this->testAddressData['company']);
        $addressMock->method('getTelephone')->willReturn($this->testAddressData['phone']);
        $addressMock->method('getStreetLine')
            ->willReturnMap(
                [
                    [1, $this->testAddressData['street_address1']],
                    [2, $this->testAddressData['street_address2']]
                ]
            );
        $addressMock->method('getCity')->willReturn($this->testAddressData['locality']);
        $addressMock->method('getRegion')->willReturn($this->testAddressData['region']);
        $addressMock->method('getPostcode')->willReturn($this->testAddressData['postal_code']);
        $addressMock->method('getCountryId')->willReturn($this->testAddressData['country_code']);
        $addressMock->method('getEmail')->willReturn($this->testAddressData['email']);
    }

    /**
     * Get quote mock with quote items
     *
     * @param Address|MockObject $billingAddress
     * @param Address|MockObject $shippingAddress
     *
     * @return Quote|MockObject
     */
    private function getQuoteMock($billingAddress = null, $shippingAddress = null)
    {
        if (!$billingAddress) {
            $billingAddress = $this->getAddressMock();
        }
        if (!$shippingAddress) {
            $shippingAddress = $this->getAddressMock();
        }
        $quoteItem = $this->getQuoteItemMock();

        $quoteMethods = [
            'getId',
            'getBoltParentQuoteId',
            'getSubtotal',
            'getAllVisibleItems',
            'getAppliedRuleIds',
            'isVirtual',
            'getShippingAddress',
            'collectTotals',
            'getQuoteCurrencyCode',
            'getBillingAddress',
            'getReservedOrderId',
            'getTotals',
            'getStoreId',
            'getUseRewardPoints',
            'getUseCustomerBalance',
            'getRewardCurrencyAmount',
            'getCustomerBalanceAmountUsed',
            'getData',
            'getCouponCode'
        ];
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $quote->method('getId')->willReturn(self::IMMUTABLE_QUOTE_ID);
        $quote->method('getReservedOrderId')->willReturn('100010001');
        $quote->method('getBoltParentQuoteId')->willReturn(self::PARENT_QUOTE_ID);
        $quote->method('getSubtotal')->willReturn(self::PRODUCT_PRICE);
        $quote->method('getAllVisibleItems')->willReturn([$quoteItem]);
        $quote->method('isVirtual')->willReturn(false);
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getQuoteCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $quote->method('collectTotals')->willReturnSelf();
        $quote->expects(static::any())->method('getStoreId')->willReturn("1");

        return $quote;
    }

    /**
     * @return MockObject
     */
    private function getQuoteItemMock()
    {
        $quoteItem = $this->getMockBuilder(Item::class)
            ->setMethods(
                [
                    'getSku',
                    'getQty',
                    'getCalculationPrice',
                    'getName',
                    'getIsVirtual',
                    'getProductId',
                    'getProduct'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $quoteItem->method('getName')->willReturn('Test Product');
        $quoteItem->method('getSku')->willReturn(self::PRODUCT_SKU);
        $quoteItem->method('getQty')->willReturn(1);
        $quoteItem->method('getCalculationPrice')->willReturn(self::PRODUCT_PRICE);
        $quoteItem->method('getIsVirtual')->willReturn(false);
        $quoteItem->method('getProductId')->willReturn(self::PRODUCT_ID);
        $quoteItem->method('getProduct')->willReturn($this->productMock);

        return $quoteItem;
    }
}
