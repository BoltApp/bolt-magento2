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
    const PARENT_QUOTE_ID = 1000;
    const IMMUTABLE_QUOTE_ID = 1001;
    const INCREMENT_ID = 100050001;
    const DISPLAY_ID = self::INCREMENT_ID . ' / ' . self::IMMUTABLE_QUOTE_ID;
    const RULE_ID = 6;
    const COUPON_ID = 5;
    const WEBSITE_ID = 1;
    const CUSTOMER_ID = 100;
    const USAGE_LIMIT = 100;
    const COUPON_CODE = 'TEST_COUPON';
    const STORE_ID = 1;

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
    private $parentQuoteMock;

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
     * Override the default configuration with passed $config and
     * set up $this->couponMock method expectations and return values
     * method configuration example: ['someMethodName' => [
     *                                   'returnMethod' => 'willReturn',
     *                                   'returnValue' => true,
     *                                   'expects' => 'exactly',
     *                                   'expectsValue' => 2,
     *                                   'with' => 1
     *                               ]]
     *
     * @param array $config
     */
    private function configureCouponMockMethods($config = [])
    {
        $defaults = [
            'loadByCode' => [
                'returnMethod' => 'willReturnSelf'
            ],
            'isObjectNew' => [
                'returnMethod' => 'willReturn',
                'returnValue' => false,
            ],
            'getCouponId' => [
                'returnMethod' => 'willReturn',
                'returnValue' => self::COUPON_ID,
            ],
            'getId' => [
                'returnMethod' => 'willReturn',
                'returnValue' => self::COUPON_ID
            ],
            'getRuleId' => [
                'returnMethod' => 'willReturn',
                'returnValue' => self::RULE_ID
            ],
            'getUsageLimit' => [
                'returnMethod' => 'willReturn',
                'returnValue' => self::USAGE_LIMIT,
            ],
            'getTimesUsed' => [
                'returnMethod' => 'willReturn',
                'returnValue' => self::USAGE_LIMIT - 1,
            ],
            'getUsagePerCustomer' => [
                'returnMethod' => 'willReturn',
                'returnValue' => 1
            ]
        ];

        $config = array_replace_recursive($defaults, $config);

        foreach ($config as $method => $options) {

            $builder = $this->couponMock;

            if (array_key_exists('expects', $options)) {
                $expects = $options['expects'];
                if (array_key_exists('expectsValue', $options)) {
                    $builder = $builder->expects(self::$expects($options['expectsValue']));
                } else {
                    $builder = $builder->expects(self::$expects());
                }
            }

            if (array_key_exists('with', $options)) {
                $builder = $builder->with($options['with']);
            }

            $builder = $builder->method($method);

            if (array_key_exists('returnMethod', $options)) {
                $returnMethod = $options['returnMethod'];
                if (array_key_exists('returnValue', $options)) {
                    $builder = $builder->$returnMethod($options['returnValue']);
                } else {
                    $builder = $builder->$returnMethod();
                }
            }
        }
    }

    /**
     * @test
     */
    public function validate_simpleCoupon()
    {
        $couponCode = 'FIXED20';
        $request_data = [
            'discount_code' => $couponCode,
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'display_id'      => self::DISPLAY_ID
            ]
        ];

        $this->request->method('getContent')->willReturn(json_encode($request_data));

        $this->configureCouponMockMethods();

        $this->moduleUnirgyGiftCertMock->method('getInstance')->willReturn(null);

        $this->ruleMock->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->method('getDescription')->willReturn('Simple discount code');
        $this->ruleMock->method('getSimpleAction')->willReturn('cart_fixed');
        $this->ruleMock->method('getFromDate')->willReturn(null);
        $this->ruleMock->method('getWebsiteIds')->will($this->returnValue([self::WEBSITE_ID]));

        $this->configHelper->method('getIgnoredShippingAddressCoupons')->willReturn([]);

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->shippingAddressMock->method('getGroupedAllShippingRates')
            ->willReturn([['flatrate' => $addressRate]]);

        $this->cartHelper->method('getActiveQuoteById')
            ->with(self::PARENT_QUOTE_ID)
            ->willReturn($this->getQuoteMock(
                $couponCode,
                null,
                null,
                false,
                self::PARENT_QUOTE_ID,
                self::PARENT_QUOTE_ID
            ));

        $this->cartHelper->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn($this->getQuoteMock(
                $couponCode,
                null,
                null,
                false,
                self::IMMUTABLE_QUOTE_ID,
                self::PARENT_QUOTE_ID
            ));

        $result = $this->currentMock->validate();

        // If another exception happens, the test will fail.
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function validateWithShippingOnlyCoupon()
    {
        $couponCode = 'FREESHIPPINGFIXED';

        $request_shipping_addr = [
            'company'         => "",
            'country'         => "United States",
            'country_code'    => "US",
            'first_name'      => "Bolt",
            'last_name'       => "Test",
            'locality'        => "New York",
            'phone'           => "+1 231 231 1234",
            'postal_code'     => "10001",
            'region'          => "New York",
            'street_address1' => "228 5th Avenue",
            'street_address2' => "",
            'email_address'   => 'test@bolt.com',
        ];
        $request_data = [
            'discount_code' => $couponCode,
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'display_id'  => self::DISPLAY_ID,
                'shipments' => [
                    0 => [
                        'shipping_address' => $request_shipping_addr,
                        'shipping_method' => 'unknown',
                        'service' => 'Flat Rate - Fixed',
                        'cost' => [
                            'amount' => 500,
                            'currency' => 'USD',
                            'currency_symbol' => '$',
                        ],
                        'tax_amount' => [
                            'amount' => 0,
                            'currency' => 'USD',
                            'currency_symbol' => '$',
                        ],
                        'reference' => 'flatrate_flatrate',
                    ],
                ],
            ]
        ];
        $this->ruleMock->method('getRuleId')
            ->willReturn(self::RULE_ID);
        $this->ruleMock->method('getDescription')
            ->willReturn('Simple discount code');
        $this->ruleMock->method('getSimpleAction')
            ->willReturn('cart_fixed');
        $this->ruleMock->method('getFromDate')
            ->willReturn(null);
        $this->ruleMock->method('getWebsiteIds')
            ->willReturn([self::WEBSITE_ID]);

        $this->request->method('getContent')
            ->willReturn(json_encode($request_data));

        $this->configureCouponMockMethods();

        $this->configHelper->method('getIgnoredShippingAddressCoupons')->willReturn([]);

        $this->moduleUnirgyGiftCertMock->method('getInstance')
            ->willReturn(null);

        $addressRate = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\Rate::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingRates = [['flatrate' => $addressRate]];
        $this->shippingAddressMock->method('getGroupedAllShippingRates')->willReturn($shippingRates);

        $this->cartHelper->method('getActiveQuoteById')
            ->with(self::PARENT_QUOTE_ID)
            ->willReturn($this->getQuoteMock(
                $couponCode,
                null,
                null,
                false,
                self::PARENT_QUOTE_ID,
                self::PARENT_QUOTE_ID
            ));

        $this->cartHelper->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn($this->getQuoteMock(
                $couponCode,
                null,
                null,
                false,
                self::IMMUTABLE_QUOTE_ID,
                self::PARENT_QUOTE_ID
            ));

        $this->cartHelper->method('handleSpecialAddressCases')
            ->willReturn((object)$request_shipping_addr);
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
        $couponCode = 'BOLT_TEST';
        $websiteId = 7;

        $request_shipping_addr = [
            'company'         => "",
            'country'         => "United States",
            'country_code'    => "US",
            'first_name'      => "Bolt",
            'last_name'       => "Test",
            'locality'        => "New York",
            'phone'           => "+1 231 231 1234",
            'postal_code'     => "10001",
            'region'          => "New York",
            'street_address1' => "228 5th Avenue",
            'street_address2' => "",
            'email_address'   => 'test@bolt.com',
        ];
        $request_data = [
            'discount_code' => $couponCode,
            'cart'          => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'display_id'      => self::DISPLAY_ID,
                'shipments'       => [
                    0 => [
                        'shipping_address' => $request_shipping_addr,
                        'shipping_method'  => 'unknown',
                        'service'          => 'Flat Rate - Fixed',
                        'cost'             =>
                            [
                                'amount'          => 500,
                                'currency'        => 'USD',
                                'currency_symbol' => '$',
                            ],
                        'tax_amount'       =>
                            [
                                'amount'          => 0,
                                'currency'        => 'USD',
                                'currency_symbol' => '$',
                            ],
                        'reference'        => 'flatrate_flatrate',
                    ],
                ],
            ]
        ];

        $ruleMethods = [
            'getRuleId',
            'getToDate',
            'getFromDate',
            'getDescription',
            'getSimpleAction',
            'getWebsiteIds'
        ];
        $this->ruleMock->method('getRuleId')
            ->willReturn(self::RULE_ID);
        $this->ruleMock->method('getDescription')
            ->willReturn('Simple discount code');
        $this->ruleMock->method('getSimpleAction')
            ->willReturn('cart_fixed');
        $this->ruleMock->method('getFromDate')
            ->willReturn(null);
        $this->ruleMock->method('getWebsiteIds')
            ->will($this->returnValue([$websiteId]));

        $this->request->method('getContent')
            ->willReturn(json_encode($request_data));

        $this->configureCouponMockMethods();

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
            ->with(self::PARENT_QUOTE_ID)
            ->willReturn($this->getQuoteMock(
                $couponCode,
                null,
                null,
                false,
                self::PARENT_QUOTE_ID,
                self::PARENT_QUOTE_ID
            ));

        $this->cartHelper->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn($this->getQuoteMock(
                $couponCode,
                null,
                null,
                false,
                self::IMMUTABLE_QUOTE_ID,
                self::PARENT_QUOTE_ID
            ));

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
        $requestContent = ['cart' => ['order_reference' => self::PARENT_QUOTE_ID]];

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
        $requestContent = ['cart' => ['order_reference' => self::PARENT_QUOTE_ID]];
        $exception = new NoSuchEntityException();

        $this->request->expects(self::atLeastOnce())->method('getContent')->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')->with(self::PARENT_QUOTE_ID)
            ->willThrowException($exception);

        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->expectErrorResponse(
            BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
            sprintf('The cart reference [%s] is not found.', self::PARENT_QUOTE_ID),
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
        $requestContent = [
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'display_id'      => self::DISPLAY_ID
            ]
        ];

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::PARENT_QUOTE_ID)
            ->willReturn($this->getQuoteMock(
                '',
                null,
                null,
                false,
                self::PARENT_QUOTE_ID,
                self::PARENT_QUOTE_ID
            ));

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
        $requestContent = [
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'display_id'      => self::DISPLAY_ID,
                'discount_code'   => self::COUPON_CODE
            ]
        ];

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::PARENT_QUOTE_ID)
            ->willReturn($this->getQuoteMock(
                '',
                null,
                null,
                false,
                self::PARENT_QUOTE_ID,
                self::PARENT_QUOTE_ID
            ));

        $this->configureCouponMockMethods([
            'loadByCode' => [
                'returnMethod' => 'willReturn',
                'returnValue' => null
            ]]
        );

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_CODE_INVALID,
            sprintf('The coupon code %s is not found', self::COUPON_CODE),
            404
        );

        self::assertFalse($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_orderAlreadyCreated()
    {
        $requestContent = [
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'display_id'      => self::DISPLAY_ID,
                'discount_code'   => self::COUPON_CODE
            ]
        ];

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::PARENT_QUOTE_ID)
            ->willReturn($this->getQuoteMock(
                '',
                null,
                null,
                false,
                self::PARENT_QUOTE_ID,
                self::PARENT_QUOTE_ID
            ));

        $this->configureCouponMockMethods([
                'loadByCode' => [
                    'expects' => 'once'
                ]
            ]
        );

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
        $requestContent = [
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'display_id'      => self::DISPLAY_ID,
                'discount_code'   => self::COUPON_CODE
            ]
        ];

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::PARENT_QUOTE_ID)
            ->willReturn($this->getQuoteMock(
                '',
                null,
                null,
                false,
                self::PARENT_QUOTE_ID,
                self::PARENT_QUOTE_ID
            ));

        $this->configureCouponMockMethods([
                'loadByCode' => [
                    'expects' => 'atLeastOnce'
                ]
            ]
        );

        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)->willReturn(false);

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
            sprintf('The cart reference [%s] is not found.', self::IMMUTABLE_QUOTE_ID),
            404
        );

        self::assertFalse($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_emptyQuote()
    {
        $requestContent = [
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'display_id'      => self::DISPLAY_ID,
                'discount_code'   => self::COUPON_CODE
            ]
        ];

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::PARENT_QUOTE_ID)
            ->willReturn($this->getQuoteMock(
                '',
                null,
                null,
                false,
                self::PARENT_QUOTE_ID,
                self::PARENT_QUOTE_ID
            ));

        $this->configureCouponMockMethods([
                'loadByCode' => [
                    'expects' => 'atLeastOnce'
                ]
            ]
        );

        $this->immutableQuoteMock->expects(self::once())->method('getItemsCount')->willReturn(0);
        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)->willReturn($this->immutableQuoteMock);

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
            sprintf('The cart for order reference [%s] is empty.', self::IMMUTABLE_QUOTE_ID),
            422
        );

        self::assertFalse($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_coupon_useParentQuoteShippingAddressDiscount()
    {
        $shippingDiscountAmount = 10;
        $requestContent = [
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'display_id'      => self::DISPLAY_ID,
                'discount_code'   => self::COUPON_CODE
            ]
        ];

        $result = [
            'status'          => 'success',
            'discount_code'   => self::COUPON_CODE,
            'discount_amount' => $shippingDiscountAmount * 100,
            'description'     => 'Discount Test Shipping Discount Description',
            'discount_type'   => 'fixed_amount',
            'cart'            => [
                'total_amount' => 10000,
                'tax_amount' => 0,
                'discounts' => $shippingDiscountAmount * 100
            ]
        ];

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));

        $this->shippingAddressMock->method('getDiscountAmount')->willReturn($shippingDiscountAmount);
        $this->shippingAddressMock->method('getDiscountDescription')->willReturn('Test Shipping Discount Description');

        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );

        $this->cartHelper->method('getCartData')->willReturn([
            'total_amount' => 10000,
            'tax_amount'   => 0,
            'discounts'    => $shippingDiscountAmount * 100,
        ]);

        $immutableQuoteMock = $this->getQuoteMock(self::COUPON_CODE);

        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::PARENT_QUOTE_ID)->willReturn($parentQuoteMock);
        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)->willReturn($immutableQuoteMock);
        $this->ruleMock->expects(self::once())->method('getSimpleAction')->willReturn('cart_fixed');

        $this->configureCouponMockMethods([
                'loadByCode' => [
                    'expects' => 'atLeastOnce'
                ],
                'getCouponId' => [
                    'expects' => 'atLeastOnce'
                ],
                'getRuleId' => [
                    'expects' => 'atLeastOnce'
                ]
            ]
        );

        $immutableQuoteMock->expects(self::once())->method('getItemsCount')->willReturn(1);
        $parentQuoteMock->expects(self::atLeastOnce())->method('getCouponCode')->willReturn(self::COUPON_CODE);
        $immutableQuoteMock->expects(self::atLeastOnce())->method('getCouponCode')->willReturn(self::COUPON_CODE);

        $this->configHelper->expects(self::once())->method('getIgnoredShippingAddressCoupons')
            ->willReturn([self::COUPON_CODE]);

        $this->response->expects(self::once())->method('setBody')->with(json_encode($result));

        self::assertTrue($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_giftCardAccount()
    {
        $giftCardAmount = 15;
        $requestContent = [
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'display_id'      => self::DISPLAY_ID,
                'discount_code'   => self::COUPON_CODE
            ]
        ];
        $result = [
            'status'          => 'success',
            'discount_code'   => self::COUPON_CODE,
            'discount_amount' => $giftCardAmount * 100,
            'description'     => 'Gift Card',
            'discount_type'   => 'fixed_amount',
            'cart'            => ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        ];

        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null ,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );

        $immutableQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE
        );

        $parentQuoteMock->method('getGiftCardsAmount')->willReturn($giftCardAmount);

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::PARENT_QUOTE_ID)->willReturn($parentQuoteMock);
        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)->willReturn($immutableQuoteMock);

        $this->moduleGiftCardAccountMock->method('getInstance')->willReturnSelf();

        $giftcardMock = $this->getMockBuilder('\Magento\GiftCardAccount\Model\Giftcardaccount')
            ->disableOriginalConstructor()
            ->setMethods(['isEmpty', 'isValid', 'getId', 'addToCart'])
            ->getMock();
        $giftcardMock->method('isEmpty')->willReturn(false);
        $giftcardMock->method('isValid')->willReturn(true);
        $giftcardMock->method('getId')->willReturn(123);

        $giftcardMock->expects(self::exactly(2))->method('addToCart')
            ->withConsecutive($immutableQuoteMock, $parentQuoteMock)
            ->willReturn($giftcardMock);

        $this->moduleGiftCardAccountMock->expects(self::once())->method('getFirstItem')
            ->willReturn($giftcardMock);

        $immutableQuoteMock->expects(self::once())->method('getItemsCount')->willReturn(1);

        $this->expectSuccessResponse($result);

        self::assertTrue($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_neitherCouponNorGiftcard()
    {
        $requestContent = [
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'display_id'      => self::DISPLAY_ID,
                'discount_code'   => self::COUPON_CODE
            ]
        ];
        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::PARENT_QUOTE_ID)
            ->willReturn(
                $this->getQuoteMock(
                    self::COUPON_CODE,
                    null,
                    null ,
                    false,
                    self::PARENT_QUOTE_ID,
                    self::PARENT_QUOTE_ID
                ));
        $this->cartHelper->expects(self::once())->method('getQuoteById')
            ->with(self::IMMUTABLE_QUOTE_ID)->willReturn($this->immutableQuoteMock);

        $this->moduleGiftCardAccountMock->method('getInstance')->willReturnSelf();

        $giftcardMock = $this->getMockBuilder('\Magento\GiftCardAccount\Model\Giftcardaccount')
            ->disableOriginalConstructor()
            ->setMethods(['isEmpty', 'isValid', 'getId', 'addToCart'])
            ->getMock();
        $giftcardMock->method('isEmpty')->willReturn(false);
        $giftcardMock->method('isValid')->willReturn(true);
        $giftcardMock->method('getId')->willReturn(null);

        $this->moduleGiftCardAccountMock->expects(self::once())->method('getFirstItem')
            ->willReturn($giftcardMock);

        $this->immutableQuoteMock->expects(self::once())->method('getItemsCount')->willReturn(1);

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
        $requestContent = ['cart' => ['order_reference' => self::PARENT_QUOTE_ID, 'display_id' => self::DISPLAY_ID]];

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::PARENT_QUOTE_ID)
            ->willReturn(
                $this->getQuoteMock(
                    '',
                    null,
                    null ,
                    false,
                    self::PARENT_QUOTE_ID,
                    self::PARENT_QUOTE_ID
                ));

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
        $requestContent = ['cart' => ['order_reference' => self::PARENT_QUOTE_ID, 'display_id' => self::DISPLAY_ID]];

        $this->request->expects(self::atLeastOnce())->method('getContent')
            ->willReturn(json_encode($requestContent));
        $this->cartHelper->expects(self::once())->method('getActiveQuoteById')
            ->with(self::PARENT_QUOTE_ID)
            ->willReturn($this->getQuoteMock(
                '',
                null,
                null,
                false,
                self::PARENT_QUOTE_ID,
                self::PARENT_QUOTE_ID
            ));

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
        $this->configureCouponMockMethods([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ]
            ]
        );

        $discountDesctiption = 'Test Discount';
        $this->ruleMock->method('getDescription')->willReturn($discountDesctiption);
        $this->ruleMock->expects(self::once())->method('getSimpleAction')->willReturn('by_percent');
        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $shippingDiscountAmount = 5;
        $this->shippingAddressMock->method('getDiscountAmount')->willReturn($shippingDiscountAmount);

        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null ,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );
        $immutableQuoteMock = $this->getQuoteMock(self::COUPON_CODE);

        $expected = [
            'status'          => 'success',
            'discount_code'   => self::COUPON_CODE,
            'discount_amount' => $shippingDiscountAmount * 100,
            'description'     => 'Discount ' . $discountDesctiption,
            'discount_type'   => 'percentage',
        ];

        $result = $this->invokeNonAccessibleMethod(
            'applyingCouponCode', [
                self::COUPON_CODE,
                $this->couponMock,
                $immutableQuoteMock,
                $parentQuoteMock
            ]
        );

        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function applyingCouponCode_noSuchEntity()
    {
        $this->configureCouponMockMethods([
                'getRuleId' => [
                    'expects' => 'once',
                    'returnMethod' => 'willThrowException',
                    'returnValue' => new NoSuchEntityException()
                ]
            ]
        );

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_CODE_INVALID,
            sprintf('The coupon code %s is not found', self::COUPON_CODE),
            404
        );

        $this->assertFalse(
            $this->invokeNonAccessibleMethod(
                'applyingCouponCode', [
                    self::COUPON_CODE,
                    $this->couponMock,
                    $this->immutableQuoteMock,
                    $this->parentQuoteMock
                ]
            )
        );
    }

    /**
     * @test
     */
    public function applyingCouponCode_invalidToDate()
    {
        $this->configureCouponMockMethods([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ]
            ]
        );

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_CODE_EXPIRED,
            sprintf('The code [%s] has expired.', self::COUPON_CODE),
            422,
            ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        );

        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null ,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );

        $this->assertFalse(
            $this->invokeNonAccessibleMethod(
                'applyingCouponCode', [
                    self::COUPON_CODE,
                    $this->couponMock,
                    $this->immutableQuoteMock,
                    $parentQuoteMock
                ]
            )
        );
    }

    /**
     * @test
     */
    public function applyingCouponCode_invalidFromDate()
    {
        $this->configureCouponMockMethods([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ]
            ]
        );

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
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

        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null ,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );

        $this->assertFalse(
            $this->invokeNonAccessibleMethod(
                'applyingCouponCode', [
                    self::COUPON_CODE,
                    $this->couponMock,
                    $this->immutableQuoteMock,
                    $parentQuoteMock
                ]
            )
        );
    }

    /**
     * @test
     */
    public function applyingCouponCode_exceededUsageLimit()
    {
        $this->configureCouponMockMethods([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ],
                'getUsageLimit' => [
                    'expects' => 'exactly',
                    'expectsValue' => 2,
                    'returnValue' => 2
                ],
                'getTimesUsed' => [
                    'expects' => 'once',
                    'returnValue' => 2
                ]
            ]
        );

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_CODE_LIMIT_REACHED,
            sprintf('The code [%s] has exceeded usage limit.', self::COUPON_CODE),
            422,
            ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        );

        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null ,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );

        $this->assertFalse(
            $this->invokeNonAccessibleMethod(
                'applyingCouponCode', [
                    self::COUPON_CODE,
                    $this->couponMock,
                    $this->immutableQuoteMock,
                    $parentQuoteMock
                ]
            )
        );
    }

    /**
     * @test
     */
    public function applyingCouponCode_perRuleCustomerUsage_exceededUsageLimit()
    {
        $this->configureCouponMockMethods([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ],
                'getUsagePerCustomer' => [
                    'expects' => 'once'
                ]
            ]
        );

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
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
            sprintf('The code [%s] has exceeded usage limit.', self::COUPON_CODE),
            422,
            ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        );

        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            self::CUSTOMER_ID,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );

        $immutableQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            self::CUSTOMER_ID
        );

        $this->assertFalse(
            $this->invokeNonAccessibleMethod(
                'applyingCouponCode', [
                    self::COUPON_CODE,
                    $this->couponMock,
                    $immutableQuoteMock,
                    $parentQuoteMock
                ]
            )
        );
    }

    /**
     * @test
     */
    public function applyingCouponCode_perCustomerUsage_exceededUsageLimit()
    {
        $this->configureCouponMockMethods([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ],
                'getUsagePerCustomer' => [
                    'expects' => 'once'
                ]
            ]
        );

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $this->dataObjectMock->method('getCouponId')->willReturn(self::COUPON_ID);
        $this->dataObjectMock->method('getTimesUsed')->willReturn(1);

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_CODE_LIMIT_REACHED,
            sprintf('The code [%s] has exceeded usage limit.', self::COUPON_CODE),
            422,
            ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        );

        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            self::CUSTOMER_ID,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );

        $immutableQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            self::CUSTOMER_ID
        );

        $this->assertFalse(
            $this->invokeNonAccessibleMethod(
                'applyingCouponCode', [
                    self::COUPON_CODE,
                    $this->couponMock,
                    $immutableQuoteMock,
                    $parentQuoteMock
                ]
            )
        );
    }

    /**
     * @test
     */
    public function applyingCouponCode_virtualImmutableQuote()
    {
        $discountAmount = 15;
        $this->shippingAddressMock->method('getDiscountAmount')->willReturn($discountAmount);

        $expected = [
            'status' => 'success',
            'discount_code' => self::COUPON_CODE,
            'discount_amount' => $discountAmount * 100,
            'description' => 'Discount',
            'discount_type' => 'fixed_amount',
        ];

        $this->configureCouponMockMethods([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ]
            ]
        );

        $this->ruleMock->expects(self::once())->method('getSimpleAction')->willReturn('cart_fixed');
        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $this->dataObjectMock->method('getCouponId')->willReturn(self::COUPON_ID);
        $this->dataObjectMock->method('getTimesUsed')->willReturn(1);

        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null,
            true,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );
        $immutableQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null,
            true
        );

        $result = $this->invokeNonAccessibleMethod(
            'applyingCouponCode', [
                self::COUPON_CODE,
                $this->couponMock,
                $immutableQuoteMock,
                $parentQuoteMock
            ]
        );

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function applyingCouponCode_errorWhenSetting()
    {
        $this->configureCouponMockMethods([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ]
            ]
        );

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $this->dataObjectMock->method('getCouponId')->willReturn(self::COUPON_ID);
        $this->dataObjectMock->method('getTimesUsed')->willReturn(1);

        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );
        $exception = new \Exception('General exception');
        $parentQuoteMock->method('setCouponCode')->willThrowException($exception);
        $immutableQuoteMock = $this->getQuoteMock(self::COUPON_CODE);

        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->expectErrorResponse(
            BoltErrorResponse::ERR_SERVICE,
            $exception->getMessage(),
            422,
            ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        );

        $this->assertFalse(
            $this->invokeNonAccessibleMethod(
                'applyingCouponCode', [
                    self::COUPON_CODE,
                    $this->couponMock,
                    $immutableQuoteMock,
                    $parentQuoteMock
                ]
            )
        );
    }

    /**
     * @test
     */
    public function applyingCouponCode_couponCodeNotSet()
    {
        $this->configureCouponMockMethods([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ]
            ]
        );

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $this->dataObjectMock->method('getCouponId')->willReturn(self::COUPON_ID);
        $this->dataObjectMock->method('getTimesUsed')->willReturn(1);

        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );
        $immutableQuoteMock = $this->getQuoteMock('');
        $immutableQuoteMock->method('getCouponCode')->willReturn(null);

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_SERVICE,
            __('Coupon code does not equal with a quote code!'),
            422,
            ['total_amount' => null, 'tax_amount' => null, 'discounts' => null]
        );

        $this->assertFalse(
            $this->invokeNonAccessibleMethod(
                'applyingCouponCode', [
                    self::COUPON_CODE,
                    $this->couponMock,
                    $immutableQuoteMock,
                    $parentQuoteMock
                ]
            )
        );
    }

    /**
     * @test
     */
    public function applyingGiftCardCode_amasty()
    {
        $discountAmount = 100;
        $amastyCodeId = 200;
        $giftCard = $this->getMockBuilder('\Amasty\GiftCard\Model\Account')
            ->setMethods(['getCodeId'])
            ->disableOriginalConstructor()
            ->getMock();
        $giftCard->expects(self::once())->method('getCodeId')->willReturn($amastyCodeId);
        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );
        $immutableQuoteMock = $this->getQuoteMock(self::COUPON_CODE);

        $this->discountHelper->expects(self::once())->method('removeAmastyGiftCard')
            ->with($amastyCodeId, $parentQuoteMock);
        $this->discountHelper->expects(self::once())->method('applyAmastyGiftCard')
            ->with(self::COUPON_CODE, $giftCard, $parentQuoteMock)->willReturn($discountAmount);
        $this->discountHelper->expects(self::once())->method('cloneAmastyGiftCards')
            ->with(self::PARENT_QUOTE_ID, self::IMMUTABLE_QUOTE_ID);

        $expected = [
            'status'          => 'success',
            'discount_code'   => self::COUPON_CODE,
            'discount_amount' => $discountAmount * 100,
            'description'     => __('Gift Card'),
            'discount_type'   => 'fixed_amount',
        ];

        $result = $this->invokeNonAccessibleMethod(
            'applyingGiftCardCode',
            [
                self::COUPON_CODE,
                $giftCard,
                $immutableQuoteMock,
                $parentQuoteMock,
            ]
        );

        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function applyingGiftCardCode_amasty_invalidException()
    {
        $exception = new LocalizedException(__('Coupon with specified code "%1" is not valid.', self::COUPON_CODE));
        $giftCard = $this->getMockBuilder('\Amasty\GiftCard\Model\Account')
            ->setMethods(['getCodeId'])
            ->disableOriginalConstructor()
            ->getMock();
        $giftCard->expects(self::once())->method('getCodeId')->willReturn(1);
        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );
        $immutableQuoteMock = $this->getQuoteMock(self::COUPON_CODE);

        $this->discountHelper->expects(self::once())->method('removeAmastyGiftCard')
            ->with(1, $parentQuoteMock);
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
                    self::COUPON_CODE,
                    $giftCard,
                    $immutableQuoteMock,
                    $parentQuoteMock,
                ]
            )
        );
    }

    /**
     * @test
     */
    public function applyingGiftCardCode_mageplaza()
    {
        $discountAmount = 100;
        $giftCardId = 1;

        $giftCardMock = $this->getMockBuilder('\Mageplaza\GiftCard\Model\GiftCard')
            ->setMethods(['getId', 'getBalance'])
            ->disableOriginalConstructor()
            ->getMock();

        $giftCardMock->expects(self::any())->method('getId')->willReturn($giftCardId);
        $giftCardMock->expects(self::once())->method('getBalance')->willReturn($discountAmount);

        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );
        $immutableQuoteMock = $this->getQuoteMock(self::COUPON_CODE);

        $this->discountHelper->expects(self::exactly(2))->method('removeMageplazaGiftCard')
            ->withConsecutive(
                [$giftCardId, $immutableQuoteMock],
                [$giftCardId, $parentQuoteMock]
            );
        $this->discountHelper->expects(self::exactly(2))->method('applyMageplazaGiftCard')
            ->withConsecutive(
                [self::COUPON_CODE, $immutableQuoteMock],
                [self::COUPON_CODE, $parentQuoteMock]
            );

        $expected = [
            'status'          => 'success',
            'discount_code'   => self::COUPON_CODE,
            'discount_amount' => $discountAmount * 100,
            'description'     => __('Gift Card'),
            'discount_type'   => 'fixed_amount',
        ];

        $result = $this->invokeNonAccessibleMethod(
            'applyingGiftCardCode',
            [
                self::COUPON_CODE,
                $giftCardMock,
                $immutableQuoteMock,
                $parentQuoteMock,
            ]
        );

        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function applyingGiftCardCode_unirgy()
    {
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


        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );
        $immutableQuoteMock = $this->getQuoteMock(self::COUPON_CODE);

        $this->checkoutSession->expects(self::once())->method('getQuote')
            ->willReturn($parentQuoteMock);

        $this->moduleUnirgyGiftCertHelperMock->expects(self::exactly(3))->method('addCertificate')
            ->withConsecutive(
                [$certNumber, $immutableQuoteMock, $this->quoteRepositoryForUnirgyGiftCert],
                [$certNumber, $parentQuoteMock, $this->quoteRepositoryForUnirgyGiftCert],
                [$certNumber, $parentQuoteMock, $this->quoteRepositoryForUnirgyGiftCert]
            );

        $expected = [
            'status'          => 'success',
            'discount_code'   => self::COUPON_CODE,
            'discount_amount' => $discountAmount * 100,
            'description'     => __('Gift Card'),
            'discount_type'   => 'fixed_amount',
        ];

        $result = $this->invokeNonAccessibleMethod(
            'applyingGiftCardCode',
            [
                self::COUPON_CODE,
                $giftCardMock,
                $immutableQuoteMock,
                $parentQuoteMock,
            ]
        );

        self::assertEquals($expected, $result);
    }

    /**
     * @test
     * @covers ::convertToBoltDiscountType
     * @dataProvider getMagentoToBoltDiscountTypes
     */
    public function convertToBoltDiscountType($input, $expected)
    {
        self::assertEquals(
            $expected,
            $this->invokeNonAccessibleMethod('convertToBoltDiscountType', [$input])
        );
    }

    public function getMagentoToBoltDiscountTypes()
    {
        return [
            ['by_fixed', 'fixed_amount'],
            ['cart_fixed', 'fixed_amount'],
            ['by_percent', 'percentage'],
            ['by_shipping', 'shipping'],
            ['', ''],
        ];
    }

    /**
     * @test
     * @covers ::loadGiftCertData
     */
    public function loadGiftCertData()
    {
        $giftCertRepository = $this->getMockBuilder('\Unirgy\Giftcert\Model\GiftcertRepository')
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();
        $giftCertMock = $this->getMockBuilder('\Unirgy\Giftcert\Model\Cert')
            ->setMethods(['getStoreId', 'getData'])
            ->disableOriginalConstructor()
            ->getMock();
        $giftCertMock->expects(self::once())->method('getStoreId')->willReturn(self::STORE_ID);
        $giftCertMock->expects(self::once())->method('getData')->with('status')
            ->willReturn('A');

        $giftCertRepository->method('get')->with(self::COUPON_CODE)->willReturn($giftCertMock);
        $this->moduleUnirgyGiftCertMock->method('getInstance')->willReturn($giftCertRepository);

        self::assertEquals(
            $giftCertMock,
            $this->invokeNonAccessibleMethod('loadGiftCertData', [self::COUPON_CODE, self::STORE_ID])
        );
    }

    /**
     * @test
     * @covers ::loadGiftCertData
     */
    public function loadGiftCertData_noGiftCert()
    {
        $giftCertRepository = $this->getMockBuilder('\Unirgy\Giftcert\Model\GiftcertRepository')
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();

        $giftCertRepository->method('get')->with(self::COUPON_CODE)->willThrowException(new NoSuchEntityException());

        $this->moduleUnirgyGiftCertMock->method('getInstance')->willReturn($giftCertRepository);

        self::assertNull(
            $this->invokeNonAccessibleMethod('loadGiftCertData', [self::COUPON_CODE, self::STORE_ID])
        );
    }

    /**
     * @test
     * @covers ::getParentQuoteDiscountResult
     */
    public function getParentQuoteDiscountResult_couponNotFound()
    {
        $this->configureCouponMockMethods([
                'getRuleId' => [
                    'expects' => 'once'
                ]
            ]
        );

        $this->ruleRepositoryMock->expects(self::once())->method('getById')->with(self::RULE_ID)
            ->willThrowException(new NoSuchEntityException());

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_CODE_INVALID,
            sprintf('The coupon code %s is not found', self::COUPON_CODE),
            404
        );
        self::assertFalse(
            $this->invokeNonAccessibleMethod(
                'getParentQuoteDiscountResult',
                [self::COUPON_CODE, $this->couponMock, $this->parentQuoteMock]
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
    private function getQuoteMock(
        $couponCode,
        $shippingAddress = null,
        $customerId = null,
        $isVirtual = false,
        $quoteId = self::IMMUTABLE_QUOTE_ID,
        $parentQuoteId = self::PARENT_QUOTE_ID
    ) {
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
        $quote->method('getStoreId')->willReturn(self::STORE_ID);
        $quote->method('getStore')->willReturnSelf();
        $quote->method('getWebsiteId')->willReturn(self::WEBSITE_ID);
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
                    'save',
                    'getDiscountAmount',
                    'getDiscountDescription'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $this->shippingAddressMock->method('setShippingMethod')->withAnyParameters()->willReturnSelf();
        $this->shippingAddressMock->method('save')->willReturnSelf();
        $this->shippingAddressMock->method('setCollectShippingRates')->with(true)->willReturnSelf();
        $this->shippingAddressMock->method('getShippingDiscountAmount')->willReturn(0);
        $this->shippingAddressMock->method('getShippingAmount')->willReturn(5);

        $this->couponMock = $this->getMockBuilder(\Magento\SalesRule\Model\Coupon::class)
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

        $this->parentQuoteMock = $this->getMockBuilder(Quote::class)
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
