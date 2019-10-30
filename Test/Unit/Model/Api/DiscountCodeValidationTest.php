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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Model\Api\DiscountCodeValidation as BoltDiscountCodeValidation;

use Magento\Framework\DataObject;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleRepository;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebApiException;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

use Magento\SalesRule\Model\CouponFactory;
use Magento\SalesRule\Model\ResourceModel\Coupon\UsageFactory;
use Magento\Framework\DataObjectFactory;
use Magento\SalesRule\Model\Rule\CustomerFactory;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Magento\SalesRule\Model\Rule\Condition\AddressFactory;

use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;

use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Helper\Bugsnag;

use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\Quote\Api\CartRepositoryInterface as QuoteRepository;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Quote\Model\Quote\TotalsCollector;


/**
 * Class DiscountCodeValidationTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\DiscountCodeValidation
 */
class DiscountCodeValidationTest extends TestCase
{
    const QUOTE_ID = 1001;
    const IMMUTABLE_QUOTE_ID = 1000;
    const INCREMENT_ID = 100050001;
    const DISPLAY_ID = self::INCREMENT_ID . ' / ' . self::QUOTE_ID;
    const RULE_ID = 6;
    const COUPON_ID = 5;

    /**
     * @var CouponFactory
     */
    private $couponFactoryMock;

    /**
     * @var UsageFactory
     */
    private $usageFactoryMock;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactoryMock;

    /**
     * @var MockObject|ThirdPartyModuleFactory
     */
    private $moduleGiftCardAccountMock;

    /**
     * @var MockObject|ThirdPartyModuleFactory
     */
    private $moduleUnirgyGiftCertMock;

    /**
     * @var MockObject|ThirdPartyModuleFactory|\Unirgy\Giftcert\Helper\Data
     */
    private $moduleUnirgyGiftCertHelperMock;

    /**
     * @var CustomerFactory
     */
    private $customerFactoryMock;

    /**
     * @var AddressFactory
     */
    private $addressFactory;

    /**
     * @var MockObject|Request
     */
    private $request;

    /**
     * @var MockObject|Response
     */
    private $response;

    /**
     * @var MockObject|HookHelper
     */
    private $hookHelper;

    /**
     * @var MockObject|LogHelper
     */
    private $logHelper;

    /**
     * @var MockObject|ConfigHelper
     */
    private $configHelper;

    /**
     * @var MockObject|CartHelper
     */
    private $cartHelper;

    /**
     * @var MockObject|DiscountHelper
     */
    private $discountHelper;

    /**
     * @var MockObject|Bugsnag
     */
    private $bugsnag;

    /**
     * @var MockObject|TimezoneInterface
     */
    private $timezone;

    /**
     * @var MockObject|BoltErrorResponse
     */
    private $errorResponse;

    /**
     * @var QuoteRepository
     */
    private $quoteRepositoryForUnirgyGiftCert;

    /**
     * @var MockObject|CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var RegionModel
     */
    private $regionModel;

    /**
     * @var TotalsCollector
     */
    private $totalsCollector;

    /**
     * @var MockObject|RuleRepository
     */
    private $ruleRepositoryMock;

    /**
     * @var MockObject|BoltDiscountCodeValidation
     */
    private $currentMock;

    /**
     * @var MockObject|Rule
     */
    private $ruleMock;

    /**
     * @var MockObject|\Magento\Quote\Model\Quote\Address
     */
    private $shippingAddressMock;

    /**
     * @var MockObject|\Magento\SalesRule\Model\Coupon
     */
    private $couponMock;

    /**
     * @var MockObject|Quote
     */
    private $quoteMock;

    /**
     * @var MockObject|Quote
     */
    private $immutableQuoteMock;

    /**
     * @var MockObject|DataObject
     */
    private $dataObjectMock;

    /**
     * @var MockObject|Rule\Customer
     */
    private $ruleCustomerMock;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    /**
     * @test
     */
    public function validate_simpleCoupon()
    {
        $quoteId = self::QUOTE_ID;
        $parentQuoteId = self::IMMUTABLE_QUOTE_ID;
        $customerId = null;
        $couponCode = 'FIXED20';
        $ruleId = self::RULE_ID;
        $request_data = (object)([
            'discount_code' => $couponCode,
            'cart'          =>
                (object)([
                    'order_reference' => self::QUOTE_ID,
                    'display_id'      => self::DISPLAY_ID
                ])
        ]);

        $this->request->method('getContent')->willReturn(json_encode($request_data));

        $this->couponMock->method('loadByCode')->willReturnSelf();
        $this->couponMock->method('isObjectNew')->willReturn(false);
        $this->couponMock->method('getCouponId')->willReturn(3);
        $this->couponMock->method('getId')->willReturn(3);
        $this->couponMock->method('getRuleId')->willReturn(self::RULE_ID);
        $this->couponMock->method('getUsageLimit')->willReturn(100);
        $this->couponMock->method('getTimesUsed')->willReturn(null);

        $this->moduleUnirgyGiftCertMock->method('getInstance')->willReturn(null);

        $this->ruleMock->method('getRuleId')->willReturn($ruleId);
        $this->ruleMock->method('getDescription')->willReturn('Simple discount code');
        $this->ruleMock->method('getSimpleAction')->willReturn('cart_fixed');
        $this->ruleMock->method('getFromDate')->willReturn(null);
        $this->ruleMock->method('getWebsiteIds')->will($this->returnValue(['1']));

        $this->configHelper->method('getIgnoredShippingAddressCoupons')->willReturn([]);

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->shippingAddressMock->method('getGroupedAllShippingRates')
            ->willReturn([['flatrate' => $addressRate]]);

        $this->cartHelper->method('getActiveQuoteById')
            ->will(
                $this->returnCallback(
                    function ($arg) use ($couponCode, $customerId, $quoteId, $parentQuoteId) {
                        return $this->getQuoteMock(
                            $couponCode,
                            $this->shippingAddressMock,
                            $customerId,
                            $quoteId,
                            $parentQuoteId
                        );
                    }
                )
            );
        $this->cartHelper->method('getQuoteById')
            ->will(
                $this->returnCallback(
                    function ($arg) use ($couponCode, $customerId, $quoteId, $parentQuoteId) {
                        return $this->getQuoteMock(
                            $couponCode,
                            $this->shippingAddressMock,
                            $customerId,
                            false,
                            $quoteId,
                            $parentQuoteId
                        );
                    }
                )
            );

        $result = $this->currentMock->validate();

        // If another exception happens, the test will fail.
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function validateWithShippingOnlyCoupon()
    {
        $customerId = null;
        $couponCode = 'FREESHIPPINGFIXED';

        $request_shipping_addr = (object)[
            'company'         => "",
            'country'         => "United States",
            'country_code'    => "US",
            'email'           => "integration@bolt.com",
            'first_name'      => "YevhenBolt",
            'last_name'       => "BoltTest2",
            'locality'        => "New York",
            'phone'           => "+1 231 231 1234",
            'postal_code'     => "10001",
            'region'          => "New York",
            'street_address1' => "228 5th Avenue",
            'street_address2' => "",
            'email_address'   => 'test@bolt.com',
        ];
        $request_data = (object)([
            'discount_code' => $couponCode,
            'cart'          =>
                (object)([
                    'order_reference' => self::QUOTE_ID,
                    'display_id'      => self::DISPLAY_ID,
                    'shipments'       =>
                        [
                            0 =>
                                (object)([
                                    'shipping_address' => $request_shipping_addr,
                                    'shipping_method'  => 'unknown',
                                    'service'          => 'Flat Rate - Fixed',
                                    'cost'             =>
                                        (object)([
                                            'amount'          => 500,
                                            'currency'        => 'USD',
                                            'currency_symbol' => '$',
                                        ]),
                                    'tax_amount'       =>
                                        (object)([
                                            'amount'          => 0,
                                            'currency'        => 'USD',
                                            'currency_symbol' => '$',
                                        ]),
                                    'reference'        => 'flatrate_flatrate',
                                ]),
                        ],
                ])
        ]);
        $this->ruleMock->method('getRuleId')
            ->willReturn(self::RULE_ID);
        $this->ruleMock->method('getDescription')
            ->willReturn('Simple discount code');
        $this->ruleMock->method('getSimpleAction')
            ->willReturn('cart_fixed');
        $this->ruleMock->method('getFromDate')
            ->willReturn(null);
        $this->ruleMock->method('getWebsiteIds')
            ->willReturn(['1']);

        // Add Shipping Method Condition
        $shippingCondMock = $this->getMockBuilder(AddressFactory::class)
            ->setMethods(['getType', 'getAttribute', 'getOperator', 'getValue'])
            ->disableOriginalConstructor()
            ->getMock();
        $shippingCondMock->method('getType')
            ->willReturn(\Magento\SalesRule\Model\Rule\Condition\Address::class);
        $shippingCondMock->method('getAttribute')
            ->willReturn('shipping_method');
        $shippingCondMock->method('getOperator')
            ->willReturn('==');
        $shippingCondMock->method('getValue')
            ->willReturn('flatrate_flatrate');

        $this->request->method('getContent')
            ->willReturn(json_encode($request_data));

        $this->couponMock->method('loadByCode')->willReturnSelf();
        $this->couponMock->method('isObjectNew')->willReturn(false);
        $this->couponMock->method('getCouponId')->willReturn(3);
        $this->couponMock->method('getId')->willReturn(3);
        $this->couponMock->method('getRuleId')->willReturn(self::RULE_ID);
        $this->couponMock->method('getUsageLimit')->willReturn(100);
        $this->couponMock->method('getTimesUsed')->willReturn(null);

        $this->configHelper->method('getIgnoredShippingAddressCoupons')->willReturn([]);

        $this->moduleUnirgyGiftCertMock->method('getInstance')
            ->willReturn(null);

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingRates = [['flatrate' => $addressRate]];
        $this->shippingAddressMock->method('getGroupedAllShippingRates')->willReturn($shippingRates);

        $this->cartHelper->method('getActiveQuoteById')
            ->will(
                $this->returnCallback(
                    function ($arg) use ($couponCode, $customerId) {
                        return $this->getQuoteMock(
                            $couponCode,
                            $this->shippingAddressMock,
                            $customerId,
                            self::QUOTE_ID,
                            self::IMMUTABLE_QUOTE_ID
                        );
                    }
                )
            );
        $this->cartHelper->method('getQuoteById')
            ->will(
                $this->returnCallback(
                    function ($arg) use ($couponCode, $customerId) {
                        return $this->getQuoteMock(
                            $couponCode,
                            $this->shippingAddressMock,
                            $customerId,
                            false,
                            self::QUOTE_ID,
                            self::IMMUTABLE_QUOTE_ID
                        );
                    }
                )
            );
        $this->cartHelper->method('handleSpecialAddressCases')
            ->willReturn($request_shipping_addr);
        $this->cartHelper->method('validateEmail')
            ->willReturn(true);

        $result = $this->currentMock->validate();

        // If another exception happens, the test will fail.
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function validateWithIncorrectWebsiteId()
    {
        $quoteId = self::QUOTE_ID;
        $parentQuoteId = self::IMMUTABLE_QUOTE_ID;
        $customerId = null;
        $couponCode = 'BOLT_TEST';
        $websiteId = '7';
        $ruleId = self::RULE_ID;

        $request_shipping_addr = (object)[
            'company'         => "",
            'country'         => "United States",
            'country_code'    => "US",
            'email'           => "integration@bolt.com",
            'first_name'      => "YevhenBolt",
            'last_name'       => "BoltTest2",
            'locality'        => "New York",
            'phone'           => "+1 231 231 1234",
            'postal_code'     => "10001",
            'region'          => "New York",
            'street_address1' => "228 5th Avenue",
            'street_address2' => "",
            'email_address'   => 'test@bolt.com',
        ];
        $request_data = (object)([
            'discount_code' => $couponCode,
            'cart'          =>
                (object)([
                    'order_reference' => $quoteId,
                    'display_id'      => '100050001 / ' . $quoteId,
                    'shipments'       =>
                        [
                            0 =>
                                (object)([
                                    'shipping_address' => $request_shipping_addr,
                                    'shipping_method'  => 'unknown',
                                    'service'          => 'Flat Rate - Fixed',
                                    'cost'             =>
                                        (object)([
                                            'amount'          => 500,
                                            'currency'        => 'USD',
                                            'currency_symbol' => '$',
                                        ]),
                                    'tax_amount'       =>
                                        (object)([
                                            'amount'          => 0,
                                            'currency'        => 'USD',
                                            'currency_symbol' => '$',
                                        ]),
                                    'reference'        => 'flatrate_flatrate',
                                ]),
                        ],
                ])
        ]);

        $ruleMethods = [
            'getRuleId',
            'getToDate',
            'getFromDate',
            'getDescription',
            'getSimpleAction',
            'getWebsiteIds'
        ];
        $this->ruleMock->method('getRuleId')
            ->willReturn($ruleId);
        $this->ruleMock->method('getDescription')
            ->willReturn('Simple discount code');
        $this->ruleMock->method('getSimpleAction')
            ->willReturn('cart_fixed');
        $this->ruleMock->method('getFromDate')
            ->willReturn(null);
        $this->ruleMock->method('getWebsiteIds')
            ->will($this->returnValue([$websiteId]));

        // Add Shipping Method Condition
        $shippingCondMock = $this->getMockBuilder(AddressFactory::class)
            ->setMethods(['getType', 'getAttribute', 'getOperator', 'getValue'])
            ->disableOriginalConstructor()
            ->getMock();
        $shippingCondMock->method('getType')
            ->willReturn(\Magento\SalesRule\Model\Rule\Condition\Address::class);
        $shippingCondMock->method('getAttribute')
            ->willReturn('shipping_method');
        $shippingCondMock->method('getOperator')
            ->willReturn('==');
        $shippingCondMock->method('getValue')
            ->willReturn('flatrate_flatrate');

        $this->request->method('getContent')
            ->willReturn(json_encode($request_data));

        $this->couponMock->method('loadByCode')->willReturnSelf();
        $this->couponMock->method('isObjectNew')->willReturn(false);
        $this->couponMock->method('getCouponId')->willReturn(3);
        $this->couponMock->method('getId')->willReturn(3);
        $this->couponMock->method('getRuleId')->willReturn($ruleId);
        $this->couponMock->method('getUsageLimit')->willReturn(100);
        $this->couponMock->method('getTimesUsed')->willReturn(null);

        $this->configHelper->method('getIgnoredShippingAddressCoupons')->willReturn([]);

        $this->moduleUnirgyGiftCertMock->method('getInstance')
            ->willReturn(null);

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingRates = [['flatrate' => $addressRate]];
        $this->shippingAddressMock->method('getGroupedAllShippingRates')
            ->willReturn($shippingRates);

        $this->cartHelper->method('getActiveQuoteById')
            ->will(
                $this->returnCallback(
                    function ($arg) use ($couponCode, $customerId, $quoteId, $parentQuoteId) {
                        return $this->getQuoteMock(
                            $couponCode,
                            $this->shippingAddressMock,
                            $customerId,
                            $quoteId,
                            $parentQuoteId
                        );
                    }
                )
            );
        $this->cartHelper->method('getQuoteById')
            ->will(
                $this->returnCallback(
                    function ($arg) use ($couponCode, $customerId, $quoteId, $parentQuoteId) {
                        return $this->getQuoteMock(
                            $couponCode,
                            $this->shippingAddressMock,
                            $customerId,
                            $quoteId,
                            $parentQuoteId
                        );
                    }
                )
            );
        $this->expectErrorResponse(
            BoltErrorResponse::ERR_CODE_INVALID,
            'The coupon code ' . $couponCode . ' is not found',
            404
        );

        $moduleGiftCardAccountMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance'])
            ->disableOriginalConstructor()
            ->getMock();

        $moduleGiftCardAccountMock->method('getInstance')
            ->willReturn(null);

        $result = $this->currentMock->validate();

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function validate_noCartIdentificationData()
    {
        $requestContent = ['cart' => ['order_reference' => self::QUOTE_ID]];

        $this->request->expects(self::atLeastOnce())->method('getContent')->willReturn(json_encode($requestContent));
        $this->expectErrorResponse(
            BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
            'The order reference is invalid.',
            422
        );

        self::assertFalse($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_noQuoteException()
    {
        $requestContent = ['cart' => ['order_reference' => self::QUOTE_ID]];
        $exception = new NoSuchEntityException();

        $this->request->expects(self::atLeastOnce())->method('getContent')->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')->with(self::QUOTE_ID)
            ->willThrowException($exception);

        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->expectErrorResponse(
            BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
            sprintf('The cart reference [%s] is not found.', self::QUOTE_ID),
            404
        );

        self::assertFalse($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_noOrderReference()
    {
        $requestContent = [];

        $this->request->expects(self::atLeastOnce())->method('getContent')->willReturn(json_encode($requestContent));

        $this->bugsnag->expects(self::once())->method('notifyError')->with(
            BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
            'The cart.order_reference is not set or empty.'
        );
        $this->expectErrorResponse(
            BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
            'The cart reference is not found.',
            404
        );

        self::assertFalse($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_emptyCoupon()
    {
        $requestContent = ['cart' => ['order_reference' => self::QUOTE_ID, 'display_id' => self::DISPLAY_ID]];

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::QUOTE_ID)->willReturn($this->getQuoteMock(''));

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_CODE_INVALID,
            'No coupon code provided',
            422
        );

        self::assertFalse($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_couponNotFound()
    {
        $couponCode = 123;
        $requestContent = [
            'cart' => [
                'order_reference' => self::QUOTE_ID,
                'display_id'      => self::DISPLAY_ID,
                'discount_code'   => $couponCode
            ]
        ];

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::QUOTE_ID)->willReturn($this->getQuoteMock(''));
        $this->couponMock->expects(self::atLeastOnce())->method('loadByCode')->willReturn(null);

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_CODE_INVALID,
            sprintf('The coupon code %s is not found', $couponCode),
            404
        );

        self::assertFalse($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_orderAlreadyCreated()
    {
        $couponCode = 123;
        $requestContent = [
            'cart' => [
                'order_reference' => self::QUOTE_ID,
                'display_id'      => self::DISPLAY_ID,
                'discount_code'   => $couponCode
            ]
        ];

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::QUOTE_ID)->willReturn($this->getQuoteMock(''));
        $this->couponMock->expects(self::atLeastOnce())->method('loadByCode')->willReturnSelf();
        $this->cartHelper->expects(self::once())->method('getOrderByIncrementId')
            ->with(self::INCREMENT_ID)->willReturn(true);

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
            sprintf('The order #%s has already been created.', self::INCREMENT_ID),
            422
        );

        self::assertFalse($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_noImmutableQuote()
    {
        $couponCode = 123;
        $requestContent = [
            'cart' => [
                'order_reference' => self::QUOTE_ID,
                'display_id'      => self::DISPLAY_ID,
                'discount_code'   => $couponCode
            ]
        ];

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::QUOTE_ID)->willReturn($this->getQuoteMock(''));
        $this->couponMock->expects(self::atLeastOnce())->method('loadByCode')->willReturnSelf();
        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::QUOTE_ID)->willReturn(false);

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
            sprintf('The cart reference [%s] is not found.', self::QUOTE_ID),
            404
        );

        self::assertFalse($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_emptyQuote()
    {
        $couponCode = 123;
        $requestContent = [
            'cart' => [
                'order_reference' => self::QUOTE_ID,
                'display_id'      => self::DISPLAY_ID,
                'discount_code'   => $couponCode
            ]
        ];

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::QUOTE_ID)->willReturn($this->getQuoteMock(''));
        $this->couponMock->expects(self::atLeastOnce())->method('loadByCode')->willReturnSelf();

        $this->quoteMock->expects(self::once())->method('getItemsCount')->willReturn(0);
        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::QUOTE_ID)->willReturn($this->quoteMock);

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
            sprintf('The cart for order reference [%s] is empty.', self::QUOTE_ID),
            422
        );

        self::assertFalse($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_coupon_useParentQuoteShippingAddressDiscount()
    {
        $couponCode = '123';
        $requestContent = [
            'cart' => [
                'order_reference' => self::QUOTE_ID,
                'display_id'      => self::DISPLAY_ID,
                'discount_code'   => $couponCode
            ]
        ];

        $result = [
            'status'          => 'success',
            'discount_code'   => $couponCode,
            'discount_amount' => 0,
            'description'     => 'Discount ',
            'discount_type'   => 'fixed_amount',
            'cart'            => ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        ];

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::QUOTE_ID)->willReturn($this->getQuoteMock($couponCode));
        $this->ruleMock->expects(self::once())->method('getSimpleAction')->willReturn('cart_fixed');

        $this->couponMock->expects(self::atLeastOnce())->method('loadByCode')->willReturnSelf();
        $this->couponMock->expects(self::atLeastOnce())->method('getCouponId')->willReturn(self::RULE_ID);
        $this->couponMock->expects(self::atLeastOnce())->method('getRuleId')->willReturn(self::RULE_ID);

        $this->quoteMock->expects(self::once())->method('getItemsCount')->willReturn(1);
        $this->quoteMock->expects(self::atLeastOnce())->method('getCouponCode')->willReturn($couponCode);
        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::QUOTE_ID)->willReturn($this->quoteMock);

        $this->configHelper->expects(self::once())->method('getIgnoredShippingAddressCoupons')
            ->willReturn([$couponCode]);
        $this->expectSuccessResponse($result);

        self::assertTrue($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_giftcardAccount()
    {
        $couponCode = '123';
        $requestContent = [
            'cart' => [
                'order_reference' => self::QUOTE_ID,
                'display_id'      => self::DISPLAY_ID,
                'discount_code'   => $couponCode
            ]
        ];
        $result = [
            'status'          => 'success',
            'discount_code'   => $couponCode,
            'discount_amount' => 1000,
            'description'     => 'Gift Card',
            'discount_type'   => 'fixed_amount',
            'cart'            => ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        ];
        $immutableQuoteMock = $this->getQuoteMock($couponCode);
        $immutableQuoteMock->method('getGiftCardsAmount')->willReturn(10);

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::QUOTE_ID)->willReturn($immutableQuoteMock);
        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::QUOTE_ID)->willReturn($this->quoteMock);


        $this->moduleGiftCardAccountMock->method('getInstance')->willReturnSelf();

        $giftcardMock = $this->getMockBuilder('\Magento\GiftCardAccount\Model\Giftcardaccount')
            ->setMethods(['isEmpty', 'isValid', 'getId', 'addToCart'])
            ->getMock();
        $giftcardMock->method('isEmpty')->willReturn(false);
        $giftcardMock->method('isValid')->willReturn(true);
        $giftcardMock->method('getId')->willReturn(1);
        $giftcardMock->method('addToCart')->willReturn(1);

        $giftcardMock->expects(self::exactly(2))->method('addToCart')
            ->withConsecutive($this->quoteMock, $immutableQuoteMock);

        $this->moduleGiftCardAccountMock->expects(self::once())->method('getFirstItem')
            ->willReturn($giftcardMock);

        $this->quoteMock->expects(self::once())->method('getItemsCount')->willReturn(1);

        $this->expectSuccessResponse($result);

        self::assertTrue($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_neitherCouponNorGiftcard()
    {
        $couponCode = '123';
        $requestContent = [
            'cart' => [
                'order_reference' => self::QUOTE_ID,
                'display_id'      => self::DISPLAY_ID,
                'discount_code'   => $couponCode
            ]
        ];
        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::QUOTE_ID)->willReturn($this->getQuoteMock($couponCode));
        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::QUOTE_ID)->willReturn($this->quoteMock);


        $this->moduleGiftCardAccountMock->method('getInstance')->willReturnSelf();

        $giftcardMock = $this->getMockBuilder('\Magento\GiftCardAccount\Model\Giftcardaccount')
            ->setMethods(['isEmpty', 'isValid', 'getId', 'addToCart'])
            ->getMock();
        $giftcardMock->method('isEmpty')->willReturn(false);
        $giftcardMock->method('isValid')->willReturn(true);
        $giftcardMock->method('getId')->willReturn(false);

        $this->moduleGiftCardAccountMock->expects(self::once())->method('getFirstItem')
            ->willReturn($giftcardMock);

        $this->quoteMock->expects(self::once())->method('getItemsCount')->willReturn(1);

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_SERVICE,
            'Something happened with current code.',
            400,
            ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        );

        self::assertFalse($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_webhookPreProcessException()
    {
        $requestContent = ['cart' => ['order_reference' => self::QUOTE_ID, 'display_id' => self::DISPLAY_ID]];

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::QUOTE_ID)->willReturn($this->getQuoteMock(''));

        $this->hookHelper->expects(self::once())->method('preProcessWebhook')
            ->willThrowException(new LocalizedException(__('Localized Exception')));

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_SERVICE,
            'Localized Exception',
            500
        );

        self::assertFalse($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_validationException()
    {
        $requestContent = ['cart' => ['order_reference' => self::QUOTE_ID, 'display_id' => self::DISPLAY_ID]];

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::QUOTE_ID)->willReturn($this->getQuoteMock(''));

        $this->hookHelper->expects(self::once())->method('preProcessWebhook')
            ->willThrowException(new \Zend_Validate_Exception('Validation Exception'));

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_SERVICE,
            'Validation Exception',
            500
        );

        self::assertFalse($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function applyingCouponCode()
    {
        $couponCode = '123';
        $reflectionClass = new \ReflectionClass(BoltDiscountCodeValidation::class);
        $method = $reflectionClass->getMethod('applyingCouponCode');
        $method->setAccessible(true);

        $this->couponMock->expects(self::once())->method('getId')->willReturn(1);
        $this->couponMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([1]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $this->dataObjectMock->method('getCouponId')->willReturn(self::COUPON_ID);
        $this->dataObjectMock->method('getTimesUsed')->willReturn(1);

        $quoteMock = $this->getQuoteMock($couponCode);
        $immutableQuoteMock = $this->getQuoteMock($couponCode);
        self::assertEquals(
            $method->invoke(
                $this->currentMock,
                $couponCode,
                $this->couponMock,
                $quoteMock,
                $immutableQuoteMock
            ),
            [
                'status'          => 'success',
                'discount_code'   => $couponCode,
                'discount_amount' => 0,
                'description'     => 'Discount',
                'discount_type'   => '',
            ]
        );

    }

    /**
     * @test
     */
    public function applyingCouponCode_noSuchEntity()
    {
        $couponCode = '123';
        $reflectionClass = new \ReflectionClass(BoltDiscountCodeValidation::class);
        $method = $reflectionClass->getMethod('applyingCouponCode');
        $method->setAccessible(true);

        $this->couponMock->expects(self::once())->method('getRuleId')
            ->willThrowException(new NoSuchEntityException());

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_CODE_INVALID,
            sprintf('The coupon code %s is not found', $couponCode),
            404
        );

        $this->invokeNonAccessibleMethod(
            'applyingCouponCode',
            [
                $couponCode,
                $this->couponMock,
                $this->immutableQuoteMock,
                $this->quoteMock
            ]
        );

    }

    /**
     * @test
     */
    public function applyingCouponCode_invalidToDate()
    {
        $couponCode = '123';
        $reflectionClass = new \ReflectionClass(BoltDiscountCodeValidation::class);
        $method = $reflectionClass->getMethod('applyingCouponCode');
        $method->setAccessible(true);

        $this->couponMock->expects(self::once())->method('getId')->willReturn(1);
        $this->couponMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([1]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_CODE_EXPIRED,
            sprintf('The code [%s] has expired.', $couponCode),
            422,
            ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        );

        $immutableQuoteMock = $this->getQuoteMock($couponCode);
        $method->invoke(
            $this->currentMock,
            $couponCode,
            $this->couponMock,
            $this->quoteMock,
            $immutableQuoteMock
        );
    }

    /**
     * @test
     */
    public function applyingCouponCode_invalidFromDate()
    {
        $couponCode = '123';
        $reflectionClass = new \ReflectionClass(BoltDiscountCodeValidation::class);
        $method = $reflectionClass->getMethod('applyingCouponCode');
        $method->setAccessible(true);

        $this->couponMock->expects(self::once())->method('getId')->willReturn(1);
        $this->couponMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([1]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $fromDate = date('Y-m-d', strtotime('tomorrow'));
        $this->ruleMock->expects(self::exactly(2))->method('getFromDate')
            ->willReturn($fromDate);

        $this->timezone->expects(self::once())->method('formatDate')
            ->with(
                new \DateTime($fromDate),
                \IntlDateFormatter::MEDIUM
            )
            ->willReturn($fromDate);

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_CODE_NOT_AVAILABLE,
            'Code available from ' . $fromDate,
            422,
            ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        );

        $immutableQuoteMock = $this->getQuoteMock($couponCode);
        $method->invoke(
            $this->currentMock,
            $couponCode,
            $this->couponMock,
            $this->quoteMock,
            $immutableQuoteMock
        );
    }

    /**
     * @test
     */
    public function applyingCouponCode_exceededUsageLimit()
    {
        $couponCode = '123';
        $reflectionClass = new \ReflectionClass(BoltDiscountCodeValidation::class);
        $method = $reflectionClass->getMethod('applyingCouponCode');
        $method->setAccessible(true);

        $this->couponMock->expects(self::once())->method('getId')->willReturn(1);
        $this->couponMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->couponMock->expects(self::exactly(2))->method('getUsageLimit')->willReturn(2);
        $this->couponMock->expects(self::once())->method('getTimesUsed')->willReturn(2);

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([1]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_CODE_LIMIT_REACHED,
            sprintf('The code [%s] has exceeded usage limit.', $couponCode),
            422,
            ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        );

        $immutableQuoteMock = $this->getQuoteMock($couponCode);
        $method->invoke(
            $this->currentMock,
            $couponCode,
            $this->couponMock,
            $this->quoteMock,
            $immutableQuoteMock
        );
    }

    /**
     * @test
     */
    public function applyingCouponCode_perRuleCustomerUsage_exceededUsageLimit()
    {
        $couponCode = '123';
        $reflectionClass = new \ReflectionClass(BoltDiscountCodeValidation::class);
        $method = $reflectionClass->getMethod('applyingCouponCode');
        $method->setAccessible(true);

        $this->couponMock->expects(self::once())->method('getId')->willReturn(1);
        $this->couponMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->couponMock->expects(self::once())->method('getUsagePerCustomer')->willReturn(1);

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([1]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));
        $this->ruleMock->expects(self::once())->method('getUsesPerCustomer')->willReturn(1);

        $this->ruleCustomerMock->method('getId')->willReturn(1);
        $this->ruleCustomerMock->method('getTimesUsed')->willReturn(1);

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_CODE_LIMIT_REACHED,
            sprintf('The code [%s] has exceeded usage limit.', $couponCode),
            422,
            ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        );

        $immutableQuoteMock = $this->getQuoteMock($couponCode);
        $this->quoteMock->expects(self::once())->method('getCustomerId')->willReturn(1);
        self::assertFalse(
            $method->invoke(
                $this->currentMock,
                $couponCode,
                $this->couponMock,
                $this->quoteMock,
                $immutableQuoteMock
            )
        );
    }

    /**
     * @test
     */
    public function applyingCouponCode_perCustomerUsage_exceededUsageLimit()
    {
        $couponCode = '123';
        $reflectionClass = new \ReflectionClass(BoltDiscountCodeValidation::class);
        $method = $reflectionClass->getMethod('applyingCouponCode');
        $method->setAccessible(true);

        $this->couponMock->expects(self::once())->method('getId')->willReturn(self::COUPON_ID);
        $this->couponMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->couponMock->expects(self::once())->method('getUsagePerCustomer')->willReturn(1);

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([1]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $this->dataObjectMock->method('getCouponId')->willReturn(self::COUPON_ID);
        $this->dataObjectMock->method('getTimesUsed')->willReturn(1);

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_CODE_LIMIT_REACHED,
            sprintf('The code [%s] has exceeded usage limit.', $couponCode),
            422,
            ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        );

        $immutableQuoteMock = $this->getQuoteMock($couponCode);
        $this->quoteMock->expects(self::once())->method('getCustomerId')->willReturn(1);
        self::assertFalse(
            $method->invoke(
                $this->currentMock,
                $couponCode,
                $this->couponMock,
                $this->quoteMock,
                $immutableQuoteMock
            )
        );
    }

    /**
     * @test
     */
    public function applyingCouponCode_virtualImmutableQuote()
    {
        $couponCode = '123';
        $reflectionClass = new \ReflectionClass(BoltDiscountCodeValidation::class);
        $method = $reflectionClass->getMethod('applyingCouponCode');
        $method->setAccessible(true);

        $this->couponMock->expects(self::once())->method('getId')->willReturn(1);
        $this->couponMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([1]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $this->dataObjectMock->method('getCouponId')->willReturn(self::COUPON_ID);
        $this->dataObjectMock->method('getTimesUsed')->willReturn(1);

        $quoteMock = $this->getQuoteMock($couponCode, null);
        $immutableQuoteMock = $this->getQuoteMock($couponCode, null, null, true);
        $method->invoke(
            $this->currentMock,
            $couponCode,
            $this->couponMock,
            $immutableQuoteMock,
            $quoteMock
        );
    }

    /**
     * @test
     */
    public function applyingCouponCode_errorWhenSetting()
    {
        $couponCode = '123';
        $reflectionClass = new \ReflectionClass(BoltDiscountCodeValidation::class);
        $method = $reflectionClass->getMethod('applyingCouponCode');
        $method->setAccessible(true);

        $this->couponMock->expects(self::once())->method('getId')->willReturn(1);
        $this->couponMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([1]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $this->dataObjectMock->method('getCouponId')->willReturn(self::COUPON_ID);
        $this->dataObjectMock->method('getTimesUsed')->willReturn(1);

        $quoteMock = $this->getQuoteMock($couponCode);
        $exception = new \Exception('General exception');
        $quoteMock->method('setCouponCode')->willThrowException($exception);
        $immutableQuoteMock = $this->getQuoteMock($couponCode);

        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->expectErrorResponse(
            BoltErrorResponse::ERR_SERVICE,
            $exception->getMessage(),
            422,
            ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        );
        self::assertFalse(
            $method->invoke(
                $this->currentMock,
                $couponCode,
                $this->couponMock,
                $quoteMock,
                $immutableQuoteMock
            )
        );
    }

    /**
     * @test
     */
    public function applyingCouponCode_couponCodeNotSet()
    {
        $couponCode = '123';
        $reflectionClass = new \ReflectionClass(BoltDiscountCodeValidation::class);
        $method = $reflectionClass->getMethod('applyingCouponCode');
        $method->setAccessible(true);

        $this->couponMock->expects(self::once())->method('getId')->willReturn(1);
        $this->couponMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([1]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $this->dataObjectMock->method('getCouponId')->willReturn(self::COUPON_ID);
        $this->dataObjectMock->method('getTimesUsed')->willReturn(1);

        $quoteMock = $this->getQuoteMock($couponCode);
        $immutableQuoteMock = $this->getQuoteMock('');
        $immutableQuoteMock->method('getCouponCode')->willReturn(null);

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_SERVICE,
            __('Coupon code does not equal with a quote code!'),
            422,
            ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        );
        self::assertFalse(
            $method->invoke(
                $this->currentMock,
                $couponCode,
                $this->couponMock,
                $immutableQuoteMock,
                $quoteMock
            )
        );
    }

    /**
     * @test
     */
    public function applyingGiftCardCode_amasty()
    {
        $couponCode = '123';
        $discountAmount = 100;
        $giftCard = $this->getMockBuilder('\Amasty\GiftCard\Model\Account')
            ->setMethods(['getCodeId'])
            ->disableOriginalConstructor()
            ->getMock();
        $giftCard->expects(self::once())->method('getCodeId')->willReturn(1);
        $quoteMock = $this->getQuoteMock($couponCode);
        $immutableQuoteMock = $this->getQuoteMock($couponCode);

        $this->discountHelper->expects(self::once())->method('removeAmastyGiftCard')
            ->with(1, $quoteMock);
        $this->discountHelper->expects(self::once())->method('applyAmastyGiftCard')
            ->with($couponCode, $giftCard, $quoteMock)->willReturn($discountAmount);
        $this->discountHelper->expects(self::once())->method('cloneAmastyGiftCards')
            ->with(1001, 1001);

        self::assertEquals(
            $this->invokeNonAccessibleMethod(
                'applyingGiftCardCode',
                [
                    $couponCode,
                    $giftCard,
                    $immutableQuoteMock,
                    $quoteMock,
                ]
            ),
            [
                'status'          => 'success',
                'discount_code'   => $couponCode,
                'discount_amount' => $discountAmount * 100,
                'description'     => __('Gift Card'),
                'discount_type'   => 'fixed_amount',
            ]
        );
    }

    /**
     * @test
     */
    public function applyingGiftCardCode_amasty_invalidException()
    {
        $couponCode = '123';
        $exception = new LocalizedException(__('Coupon with specified code "%1" is not valid.', $couponCode));
        $giftCard = $this->getMockBuilder('\Amasty\GiftCard\Model\Account')
            ->setMethods(['getCodeId'])
            ->disableOriginalConstructor()
            ->getMock();
        $giftCard->expects(self::once())->method('getCodeId')->willReturn(1);
        $quoteMock = $this->getQuoteMock($couponCode);
        $immutableQuoteMock = $this->getQuoteMock($couponCode);

        $this->discountHelper->expects(self::once())->method('removeAmastyGiftCard')
            ->with(1, $quoteMock);
        $this->discountHelper->expects(self::once())->method('applyAmastyGiftCard')
            ->willThrowException($exception);

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_SERVICE,
            $exception->getMessage(),
            422,
            ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        );

        self::assertFalse(
            $this->invokeNonAccessibleMethod(
                'applyingGiftCardCode',
                [
                    $couponCode,
                    $giftCard,
                    $immutableQuoteMock,
                    $quoteMock,
                ]
            )
        );
    }

    /**
     * @test
     */
    public function applyingGiftCardCode_mageplaza()
    {
        $couponCode = '123';
        $discountAmount = 100;
        $giftCardId = 1;

        $giftCardMock = $this->getMockBuilder('\Mageplaza\GiftCard\Model\GiftCard')
            ->setMethods(['getId', 'getBalance'])
            ->disableOriginalConstructor()
            ->getMock();

        $giftCardMock->expects(self::any())->method('getId')->willReturn($giftCardId);
        $giftCardMock->expects(self::once())->method('getBalance')->willReturn($discountAmount);

        $quoteMock = $this->getQuoteMock($couponCode);
        $immutableQuoteMock = $this->getQuoteMock($couponCode);

        $this->discountHelper->expects(self::exactly(2))->method('removeMageplazaGiftCard')
            ->withConsecutive(
                [$giftCardId, $immutableQuoteMock],
                [$giftCardId, $quoteMock]
            );
        $this->discountHelper->expects(self::exactly(2))->method('applyMageplazaGiftCard')
            ->withConsecutive(
                [$couponCode, $immutableQuoteMock],
                [$couponCode, $quoteMock]
            );

        self::assertEquals(
            $this->invokeNonAccessibleMethod(
                'applyingGiftCardCode',
                [
                    $couponCode,
                    $giftCardMock,
                    $immutableQuoteMock,
                    $quoteMock,
                ]
            ),
            [
                'status'          => 'success',
                'discount_code'   => $couponCode,
                'discount_amount' => $discountAmount * 100,
                'description'     => __('Gift Card'),
                'discount_type'   => 'fixed_amount',
            ]
        );
    }

    /**
     * @test
     */
    public function applyingGiftCardCode_unirgy()
    {
        $couponCode = '123';
        $discountAmount = 100;

        $giftCard = new class
        {
            const GIFTCERT_CODE = 'GIFTCERT_CODE';
        };
        $class = get_class($giftCard);
        class_alias($class, '\Unirgy\Giftcert\Model\Cert');

        $giftCardMock = $this->getMockBuilder('\Unirgy\Giftcert\Model\Cert')
            ->setMethods(['getCertNumber', 'getBalance'])
            ->disableOriginalConstructor()
            ->getMock();

        $certNumber = 1;
        $giftCardMock->expects(self::any())->method('getCertNumber')->willReturn($certNumber);
        $giftCardMock->expects(self::once())->method('getBalance')->willReturn($discountAmount);


        $quoteMock = $this->getQuoteMock($couponCode);
        $immutableQuoteMock = $this->getQuoteMock($couponCode);

        $this->checkoutSession->expects(self::once())->method('getQuote')
            ->willReturn($quoteMock);

        $this->moduleUnirgyGiftCertHelperMock->expects(self::exactly(3))->method('addCertificate')
            ->withConsecutive(
                [$certNumber, $immutableQuoteMock, $this->quoteRepositoryForUnirgyGiftCert],
                [$certNumber, $quoteMock, $this->quoteRepositoryForUnirgyGiftCert],
                [$certNumber, $quoteMock, $this->quoteRepositoryForUnirgyGiftCert]
            );

        self::assertEquals(
            $this->invokeNonAccessibleMethod(
                'applyingGiftCardCode',
                [
                    $couponCode,
                    $giftCardMock,
                    $immutableQuoteMock,
                    $quoteMock,
                ]
            ),
            [
                'status'          => 'success',
                'discount_code'   => $couponCode,
                'discount_amount' => $discountAmount * 100,
                'description'     => __('Gift Card'),
                'discount_type'   => 'fixed_amount',
            ]
        );
    }

    /**
     * @test
     * @covers ::convertToBoltDiscountType
     * @dataProvider getMagentoToBoltDiscountTypes
     */
    public function convertToBoltDiscountType($input, $expected)
    {
        self::assertEquals(
            $this->invokeNonAccessibleMethod('convertToBoltDiscountType', [$input]),
            $expected
        );
    }

    public function getMagentoToBoltDiscountTypes()
    {
        return [
            ['by_fixed', 'fixed_amount'],
            ['cart_fixed', 'fixed_amount'],
            ['by_percent', 'percentage'],
            ['by_shipping', 'shipping'],
        ];
    }

    /**
     * @test
     * @covers ::loadGiftCertData
     */
    public function loadGiftCertData()
    {
        $couponCode = '123';
        $giftCertRepository = $this->getMockBuilder('\Unirgy\Giftcert\Model\GiftcertRepository')
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();
        $giftCertMock = $this->getMockBuilder('\Unirgy\Giftcert\Model\Cert')
            ->setMethods(['getStoreId', 'getData'])
            ->disableOriginalConstructor()
            ->getMock();
        $giftCertMock->expects(self::once())->method('getStoreId')->willReturn(1);
        $giftCertMock->expects(self::once())->method('getData')->with('status')
            ->willReturn('A');

        $giftCertRepository->method('get')->with($couponCode)->willReturn($giftCertMock);
        $this->moduleUnirgyGiftCertMock->method('getInstance')->willReturn($giftCertRepository);

        self::assertEquals(
            $this->invokeNonAccessibleMethod('loadGiftCertData', [$couponCode, 1]),
            $giftCertMock
        );
    }

    /**
     * @test
     * @covers ::loadGiftCertData
     */
    public function loadGiftCertData_noGiftCert()
    {
        $couponCode = '123';
        $giftCertRepository = $this->getMockBuilder('\Unirgy\Giftcert\Model\GiftcertRepository')
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();

        $giftCertRepository->method('get')->with($couponCode)->willThrowException(new NoSuchEntityException());

        $this->moduleUnirgyGiftCertMock->method('getInstance')->willReturn($giftCertRepository);

        self::assertEquals(
            $this->invokeNonAccessibleMethod('loadGiftCertData', [$couponCode, 1]),
            null
        );
    }

    /**
     * @test
     * @covers ::getParentQuoteDiscountResult
     */
    public function getParentQuoteDiscountResult_couponNotFound()
    {
        $couponCode = '123';
        $this->couponMock->expects(self::once())->method('getRuleId')->willReturn(self::COUPON_ID);
        $this->ruleRepositoryMock->expects(self::once())->method('getById')->with(self::COUPON_ID)
            ->willThrowException(new NoSuchEntityException());

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_CODE_INVALID,
            sprintf('The coupon code %s is not found', $couponCode),
            404
        );
        self::assertFalse(
            $this->invokeNonAccessibleMethod(
                'getParentQuoteDiscountResult',
                [$couponCode, $this->couponMock, $this->quoteMock]
            )
        );
    }

    /**
     * Get quote mock with quote items
     *
     * @param $couponCode
     * @param $shippingAddress
     * @param $customerId
     * @param bool $isVirtual
     * @param int $quoteId
     * @param int $parentQuoteId
     * @return MockObject
     * @throws \ReflectionException
     * @oaram $couponCode
     */
    private function getQuoteMock($couponCode, $shippingAddress = null, $customerId = null, $isVirtual = false, $quoteId = 1001, $parentQuoteId = 1000)
    {
        if (is_null($shippingAddress)) {
            $shippingAddress = $this->shippingAddressMock;
        }
        $quoteItem = $this->getMockBuilder(\Magento\Quote\Model\Quote\Item::class)
            ->setMethods(['getSku', 'getQty', 'getCalculationPrice'])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteItem->method('getSku')->willReturn('TestProduct');
        $quoteItem->method('getQty')->willReturn(1);

        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(
                [
                    'getId',
                    'getBoltParentQuoteId',
                    'getSubtotal',
                    'getAllVisibleItems',
                    'getAppliedRuleIds',
                    'isVirtual',
                    'getShippingAddress',
                    'getBillingAddress',
                    'collectTotals',
                    'getQuoteCurrencyCode',
                    'getItemsCount',
                    'getCustomerId',
                    'setCouponCode',
                    'getCouponCode',
                    'getStoreId',
                    'getStore',
                    'getWebsiteId',
                    'save',
                    'getGiftCardsAmount'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $quote->method('getId')->willReturn($quoteId);
        $quote->method('getBoltParentQuoteId')->willReturn($parentQuoteId);
        $quote->method('getSubtotal')->willReturn(100);
        $quote->method('getAllVisibleItems')->willReturn([$quoteItem]);
        $quote->method('getAppliedRuleIds')->willReturn('2,3');
        $quote->method('isVirtual')->willReturn($isVirtual);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($shippingAddress);
        $quote->method('getQuoteCurrencyCode')->willReturn('$');
        $quote->method('collectTotals')->willReturnSelf();
        $quote->method('getItemsCount')->willReturn(1);
        $quote->method('getCustomerId')->willReturn($customerId);
        $quote->expects($this->any())->method('setCouponCode')->willReturnSelf();
        $quote->method('getCouponCode')->willReturn($couponCode);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getStore')->willReturnSelf();
        $quote->method('getWebsiteId')->willReturn(1);
        $quote->method('save')->willReturnSelf();

        return $quote;
    }

    private function createFactoryMocks()
    {
        $this->couponFactoryMock = $this->getMockBuilder(CouponFactory::class)
            ->setMethods(
                [
                    'create',
                    'loadByCode',
                    'isObjectNew',
                    'getCouponId',
                    'getId',
                    'getRuleId',
                    'getUsageLimit',
                    'getTimesUsed'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $this->couponFactoryMock->method('create')
            ->willReturn($this->couponMock);

        $this->usageFactoryMock = $this->getMockBuilder(UsageFactory::class)
            ->setMethods(['create', 'loadByCustomerCoupon'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->usageFactoryMock->method('create')
            ->willReturnSelf();
        $this->usageFactoryMock->method('loadByCustomerCoupon')
            ->withAnyParameters()
            ->willReturnSelf();

        $this->dataObjectMock = $this->getMockBuilder(DataObject::class)
            ->setMethods(['getCouponId', 'getTimesUsed'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->dataObjectFactoryMock = $this->getMockBuilder(DataObjectFactory::class)
            ->setMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->dataObjectFactoryMock->method('create')
            ->willReturn($this->dataObjectMock);

        $this->moduleGiftCardAccountMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'addFieldToFilter', 'addWebsiteFilter', 'getFirstItem'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->moduleGiftCardAccountMock->method('addFieldToFilter')->willReturnSelf();
        $this->moduleGiftCardAccountMock->method('addWebsiteFilter')->willReturnSelf();

        $this->moduleUnirgyGiftCertMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->moduleUnirgyGiftCertHelperMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'addCertificate'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->moduleUnirgyGiftCertHelperMock->method('getInstance')
            ->willReturnSelf();

        $this->ruleCustomerMock = $this->getMockBuilder(Rule\Customer::class)
            ->setMethods(['loadByCustomerRule', 'getId', 'getTimesUsed'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->customerFactoryMock = $this->getMockBuilder(CustomerFactory::class)
            ->setMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->customerFactoryMock->method('create')
            ->willReturn($this->ruleCustomerMock);
        $this->ruleCustomerMock->method('loadByCustomerRule')
            ->withAnyParameters()
            ->willReturnSelf();
    }

    private function createHelperMocks()
    {
        $this->hookHelper = $this->createMock(HookHelper::class);

        $this->logHelper = $this->createMock(LogHelper::class);

        $this->cartHelper = $this->getMockBuilder(CartHelper::class)
            ->setMethods(
                [
                    'getOrderByIncrementId',
                    'getQuoteById',
                    'getActiveQuoteById',
                    'handleSpecialAddressCases',
                    'validateEmail',
                    'getRoundAmount',
                    'getCartData'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $this->cartHelper->method('getRoundAmount')
            ->willReturnCallback(
                function ($amount) {
                    return (int)round($amount * 100);
                }
            );

        $this->configHelper = $this->getMockBuilder(ConfigHelper::class)
            ->setMethods(['getIgnoredShippingAddressCoupons'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->discountHelper = $this->getMockBuilder(DiscountHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->bugsnag = $this->getMockBuilder(Bugsnag::class)
            ->setMethods(['notifyException', 'notifyError'])
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function initRequiredMocks()
    {
        $this->request = $this->getMockBuilder(Request::class)
            ->setMethods(['getContent'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->response = $this->getMockBuilder(Response::class)
            ->setMethods(['setHttpResponseCode', 'setBody', 'sendResponse'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->errorResponse = $this->getMockBuilder(BoltErrorResponse::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->timezone = $this->createMock(TimezoneInterface::class);
        $this->quoteRepositoryForUnirgyGiftCert = $this->createMock(QuoteRepository::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->regionModel = $this->getMockBuilder(RegionModel::class)
            ->setMethods(['loadByName'])
            ->disableOriginalConstructor()
            ->getMock();


        $this->totalsCollector = $this->getMockBuilder(TotalsCollector::class)
            ->setMethods(['collectAddressTotals'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->totalsCollector->method('collectAddressTotals')
            ->withAnyParameters()
            ->willReturnSelf();

        $this->ruleMock = $this->getMockBuilder(Rule::class)
            ->setMethods(
                [
                    'getRuleId',
                    'getToDate',
                    'getFromDate',
                    'getDescription',
                    'getSimpleAction',
                    'getWebsiteIds',
                    'getUsesPerCustomer'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $this->ruleRepositoryMock = $this->getMockBuilder(RuleRepository::class)
            ->setMethods(['getRuleId', 'getById'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->ruleRepositoryMock->method('getById')->with(self::RULE_ID)->willReturn($this->ruleMock);

        $this->shippingAddressMock = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
            ->setMethods(
                [
                    'addData',
                    'setCollectShippingRates',
                    'setShippingMethod',
                    'getGroupedAllShippingRates',
                    'getShippingDiscountAmount',
                    'getShippingAmount',
                    'save'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $this->shippingAddressMock->method('setShippingMethod')->withAnyParameters()->willReturnSelf();
        $this->shippingAddressMock->method('save')->willReturnSelf();
        $this->shippingAddressMock->method('setCollectShippingRates')->with(true)->willReturnSelf();
        $this->shippingAddressMock->method('getShippingDiscountAmount')->willReturn('0');
        $this->shippingAddressMock->method('getShippingAmount')->willReturn('5');

        $this->couponMock = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
            ->setMethods(
                [
                    'loadByCode',
                    'isObjectNew',
                    'getCouponId',
                    'getId',
                    'getRuleId',
                    'getUsageLimit',
                    'getUsagePerCustomer',
                    'getTimesUsed'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->setMethods(['getItemsCount', 'getCouponCode', 'getCustomerId'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->immutableQuoteMock = $this->getMockBuilder(Quote::class)
            ->setMethods(['getItemsCount', 'getCouponCode'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->createFactoryMocks();
        $this->createHelperMocks();
    }

    protected function initCurrentMock()
    {
        $this->currentMock = $this->getMockBuilder(BoltDiscountCodeValidation::class)
            ->setMethods(['getInfoInstance'])
            ->setConstructorArgs(
                [
                    $this->request,
                    $this->response,
                    $this->couponFactoryMock,
                    $this->moduleGiftCardAccountMock,
                    $this->moduleUnirgyGiftCertMock,
                    $this->moduleUnirgyGiftCertHelperMock,
                    $this->quoteRepositoryForUnirgyGiftCert,
                    $this->checkoutSession,
                    $this->ruleRepositoryMock,
                    $this->logHelper,
                    $this->errorResponse,
                    $this->usageFactoryMock,
                    $this->dataObjectFactoryMock,
                    $this->timezone,
                    $this->customerFactoryMock,
                    $this->bugsnag,
                    $this->cartHelper,
                    $this->configHelper,
                    $this->hookHelper,
                    $this->discountHelper,
                    $this->regionModel,
                    $this->totalsCollector
                ]
            )->getMock();

        return $this->currentMock;
    }

    private function expectErrorResponse($errCode, $message, $httpStatusCode, $cartData = null)
    {
        $additionalErrorResponseData = [];
        if ($cartData) {
            $additionalErrorResponseData['cart'] = $cartData;
        }
        $encodeErrorResult = '';
        $this->errorResponse->expects(self::once())->method('prepareErrorMessage')
            ->with($errCode, $message, $additionalErrorResponseData)->willReturn($encodeErrorResult);
        $this->response->expects(self::once())->method('setHttpResponseCode')->with($httpStatusCode);
        $this->response->expects(self::once())->method('setBody')->with($encodeErrorResult);
        $this->response->expects(self::once())->method('sendResponse');
    }

    private function expectSuccessResponse($result, $quote = null)
    {
        $this->response->expects(self::once())->method('setBody')->with(json_encode($result));
        $this->response->expects(self::once())->method('sendResponse');
    }

    /**
     * @param string $method
     * @param array $arguments
     * @return mixed
     * @throws \ReflectionException
     */
    protected function invokeNonAccessibleMethod(string $method, array $arguments)
    {
        $reflectionClass = new \ReflectionClass(BoltDiscountCodeValidation::class);
        $method = $reflectionClass->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs(
            $this->currentMock,
            $arguments
        );
    }
}
