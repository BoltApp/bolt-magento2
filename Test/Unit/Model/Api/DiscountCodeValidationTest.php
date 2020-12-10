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

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Model\Api\DiscountCodeValidation as BoltDiscountCodeValidation;

use Magento\Framework\DataObject;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleRepository;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

use Magento\SalesRule\Model\ResourceModel\Coupon\UsageFactory;
use Magento\Framework\DataObjectFactory;
use Magento\SalesRule\Model\Rule\CustomerFactory;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Magento\SalesRule\Model\Rule\Condition\AddressFactory;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\Quote\Api\CartRepositoryInterface as QuoteRepository;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Quote\Model\Quote\TotalsCollector;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Model\Api\UpdateCartCommon;
use Bolt\Boltpay\Model\Api\UpdateCartContext;
use Bolt\Boltpay\Model\Api\UpdateDiscountTrait;
use Bolt\Boltpay\Test\Unit\TestHelper;

/**
 * Class DiscountCodeValidationTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\DiscountCodeValidation
 */
class DiscountCodeValidationTest extends TestCase
{
    const PARENT_QUOTE_ID = "1000";
    const IMMUTABLE_QUOTE_ID = "1001";
    const INCREMENT_ID = 100050001;
    const DISPLAY_ID = self::INCREMENT_ID;
    const RULE_ID = 6;
    const COUPON_ID = 5;
    const WEBSITE_ID = 1;
    const CUSTOMER_ID = 100;
    const USAGE_LIMIT = 100;
    const COUPON_CODE = 'TEST_COUPON';
    const STORE_ID = 1;

    /**
     * @var UsageFactory
     */
    private $usageFactoryMock;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactoryMock;

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
     * @var MockObject|OrderHelper
     */
    private $orderHelper;

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
     * @var MockObject|\Magento\Framework\App\CacheInterface system cache model
     */
    private $cache;

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
     * @var MockObject|EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;
    
    /**
     * @var UpdateCartContext|MockObject
     */
    private $updateCartContext;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->initRequiredMocks();        
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
        $this->initCurrentMock(['validateQuote','verifyCouponCode','applyingCouponCode']);
        $couponCode = 'FIXED20';
        $request_data = [
            'discount_code' => $couponCode,
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'metadata'        => [
                    'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
                ],
            ]
        ];

        $this->cartHelper->expects(self::once())->method('getImmutableQuoteIdFromBoltCartArray')
            ->with($request_data['cart'])
            ->willReturn(self::IMMUTABLE_QUOTE_ID);

        $this->configureCouponMockMethods();
        
        $this->configHelper->expects(self::once())->method('getIgnoredShippingAddressCoupons')
            ->with(self::STORE_ID)->willReturn([]);

        $parentQuote = $this->getQuoteMock(
            $couponCode,
            null,
            null,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );
        
        $immutableQuote = $this->getQuoteMock(
            $couponCode,
            null,
            null,
            false,
            self::IMMUTABLE_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );
        
        $this->currentMock->expects(self::atLeastOnce())->method('getRequestContent')
            ->willReturn($request_data);
        
        $this->currentMock->expects(self::once())->method('preProcessWebhook')->with(self::STORE_ID);
            
        $this->currentMock->expects(self::once())->method('validateQuote')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn([$parentQuote, $immutableQuote]);
        
        $this->currentMock->expects(self::once())->method('verifyCouponCode')
            ->with($couponCode, self::WEBSITE_ID, self::STORE_ID)->willReturn([$this->couponMock, null]);
            
        $applyCouponResult = [
            'status'          => 'success',
            'discount_code'   => $couponCode,
            'discount_amount' => 2000,
            'description'     => 'Discount FIXED20',
            'discount_type'   => 'fixed_amount',
        ];
        
        $this->currentMock->expects(self::once())->method('applyingCouponCode')
            ->with($couponCode, $this->couponMock, $immutableQuote, $parentQuote)->willReturn($applyCouponResult);

        $this->cache->expects(static::once())->method('clean')
            ->with([CartHelper::BOLT_ORDER_TAG . '_' . self::PARENT_QUOTE_ID]);

        $this->eventsForThirdPartyModules->method('dispatchEvent')->willReturnSelf();
        
        $this->cartHelper->method('getCartData')->willReturn([
            'total_amount' => 10000,
            'tax_amount'   => 0,
            'discounts'    => 2000,
        ]);
        
        $result = [
            'status'          => 'success',
            'discount_code'   => $couponCode,
            'discount_amount' => 2000,
            'description'     => 'Discount FIXED20',
            'discount_type'   => 'fixed_amount',
            'cart'            => [
                'total_amount' => 10000,
                'tax_amount' => 0,
                'discounts' => 2000
            ]
        ];
        
        $this->expectSuccessResponse($result);

        $this->assertTrue($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validateWithShippingOnlyCoupon()
    {
        $this->initCurrentMock(['validateQuote','verifyCouponCode','setShipment','applyingCouponCode','shouldUseParentQuoteShippingAddressDiscount']);
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
                'metadata'        => [
                    'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
                ],
            ]
        ];

        $this->cartHelper->expects(self::once())->method('getImmutableQuoteIdFromBoltCartArray')
            ->with($request_data['cart'])
            ->willReturn(self::IMMUTABLE_QUOTE_ID);

        $this->configureCouponMockMethods();

        $parentQuote = $this->getQuoteMock(
            $couponCode,
            null,
            null,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );
        
        $immutableQuote = $this->getQuoteMock(
            $couponCode,
            null,
            null,
            false,
            self::IMMUTABLE_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );
        
        $this->currentMock->expects(self::atLeastOnce())->method('getRequestContent')
            ->willReturn($request_data);
        
        $this->currentMock->expects(self::atLeastOnce())->method('preProcessWebhook')->with(self::STORE_ID);

        $this->currentMock->expects(self::once())->method('validateQuote')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn([$parentQuote, $immutableQuote]);
        
        $this->currentMock->expects(self::once())->method('verifyCouponCode')
            ->with($couponCode, self::WEBSITE_ID, self::STORE_ID)->willReturn([$this->couponMock, null]);
        
        $this->currentMock->expects(self::once())->method('setShipment')
            ->with($request_data['cart']['shipments'][0], $immutableQuote);
        
        $this->currentMock->expects(self::once())->method('shouldUseParentQuoteShippingAddressDiscount')
            ->with($couponCode, $immutableQuote, $parentQuote)->willReturn(false);
        
        $applyCouponResult = [
            'status'          => 'success',
            'discount_code'   => $couponCode,
            'discount_amount' => 2000,
            'description'     => 'Discount FIXED20',
            'discount_type'   => 'fixed_amount',
        ];
        
        $this->currentMock->expects(self::once())->method('applyingCouponCode')
            ->with($couponCode, $this->couponMock, $immutableQuote, $parentQuote)->willReturn($applyCouponResult);

        $this->cache->expects(static::once())->method('clean')
            ->with([CartHelper::BOLT_ORDER_TAG . '_' . self::PARENT_QUOTE_ID]);
        $this->eventsForThirdPartyModules->method('dispatchEvent')->willReturnSelf();
        
        $this->cartHelper->method('getCartData')->willReturn([
            'total_amount' => 10000,
            'tax_amount'   => 0,
            'discounts'    => 2000,
        ]);
        
        $result = [
            'status'          => 'success',
            'discount_code'   => $couponCode,
            'discount_amount' => 2000,
            'description'     => 'Discount FIXED20',
            'discount_type'   => 'fixed_amount',
            'cart'            => [
                'total_amount' => 10000,
                'tax_amount' => 0,
                'discounts' => 2000
            ]
        ];
        
        $this->expectSuccessResponse($result);
        
        $this->assertTrue($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_noOrderReference()
    {
        $this->initCurrentMock();
        
        $requestContent = [];

        $this->currentMock->expects(self::atLeastOnce())->method('getRequestContent')
            ->willReturn($requestContent);
        
        $this->currentMock->expects(self::never())->method('preProcessWebhook')->with(self::STORE_ID);

        $e = new BoltException(
                __('The cart.order_reference is not set or empty.'),
                null,
                BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION
            );

        $this->expectErrorResponse(
            BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
            'The cart.order_reference is not set or empty.',
            422
        );

        self::assertFalse($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_emptyCoupon()
    {
        $this->initCurrentMock();
        
        $requestContent = [
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'metadata'        => [
                    'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
                ]
            ]
        ];

        $this->currentMock->expects(self::atLeastOnce())->method('getRequestContent')
            ->willReturn($requestContent);
        
        $this->currentMock->expects(self::never())->method('preProcessWebhook')->with(self::STORE_ID);
        
        $this->cartHelper->expects(self::once())->method('getImmutableQuoteIdFromBoltCartArray')
            ->with($requestContent['cart'])
            ->willReturn(self::IMMUTABLE_QUOTE_ID);

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
    public function validate_coupon_useParentQuoteShippingAddressDiscount()
    {
        $this->initCurrentMock(['validateQuote','verifyCouponCode','applyingCouponCode']);
        
        $shippingDiscountAmount = 10;
        $requestContent = [
            'discount_code' => self::COUPON_CODE,
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'metadata'        => [
                    'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
                ]
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

        $this->currentMock->expects(self::atLeastOnce())->method('getRequestContent')
            ->willReturn($requestContent);
        
        $this->cartHelper->expects(self::once())->method('getImmutableQuoteIdFromBoltCartArray')
            ->with($requestContent['cart'])
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
        
        $this->currentMock->expects(self::once())->method('preProcessWebhook')->with(self::STORE_ID);
        
        $this->configHelper->expects(self::once())->method('getIgnoredShippingAddressCoupons')
            ->with(self::STORE_ID)->willReturn([self::COUPON_CODE]);
        
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
        $immutableQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null,
            false,
            self::IMMUTABLE_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );
        
        $this->currentMock->expects(self::once())->method('validateQuote')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn([$parentQuoteMock, $immutableQuoteMock]);

        $this->cartHelper->method('getCartData')->willReturn([
            'total_amount' => 10000,
            'tax_amount'   => 0,
            'discounts'    => $shippingDiscountAmount * 100,
        ]);

        $this->configureCouponMockMethods();

        $this->currentMock->expects(self::once())->method('verifyCouponCode')
            ->with(self::COUPON_CODE, self::WEBSITE_ID, self::STORE_ID)->willReturn([$this->couponMock, null]);
        
        $this->discountHelper->expects(self::once())->method('convertToBoltDiscountType')
            ->with(self::COUPON_CODE)->willReturn('fixed_amount');

        $this->expectSuccessResponse($result);

        $this->cache->expects(static::once())->method('clean')
            ->with([CartHelper::BOLT_ORDER_TAG . '_' . self::PARENT_QUOTE_ID]);
        $this->eventsForThirdPartyModules->method('dispatchEvent')->willReturnSelf();
        self::assertTrue($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_giftCardAccount()
    {
        $this->initCurrentMock(['validateQuote','verifyCouponCode']);
        $giftCardAmount = 15;
        $requestContent = [
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'discount_code'   => self::COUPON_CODE,
                'metadata'        => [
                    'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
                ]
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
            null,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );

        $immutableQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE
        );

        $parentQuoteMock->method('getGiftCardsAmount')->willReturn($giftCardAmount);

        $this->currentMock->expects(self::atLeastOnce())->method('getRequestContent')
            ->willReturn($requestContent);
            
        $this->cartHelper->expects(self::once())->method('getImmutableQuoteIdFromBoltCartArray')
            ->with($requestContent['cart'])
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
        
        $this->currentMock->expects(self::once())->method('preProcessWebhook')->with(self::STORE_ID);
            
        $this->currentMock->expects(self::once())->method('validateQuote')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn([$parentQuoteMock, $immutableQuoteMock]);

        $giftcardMock = $this->getMockBuilder('\Magento\GiftCardAccount\Model\Giftcardaccount')
            ->disableOriginalConstructor()
            ->setMethods(['isEmpty', 'isValid', 'getId', 'removeFromCart', 'addToCart'])
            ->getMock();
        $giftcardMock->method('isEmpty')->willReturn(false);
        $giftcardMock->method('isValid')->willReturn(true);
        $giftcardMock->method('getId')->willReturn(123);

        $giftcardMock->expects(self::exactly(2))->method('removeFromCart')
            ->withConsecutive($immutableQuoteMock, $parentQuoteMock)
            ->willReturn($giftcardMock);

        $giftcardMock->expects(self::exactly(2))->method('addToCart')
            ->withConsecutive($immutableQuoteMock, $parentQuoteMock)
            ->willReturn($giftcardMock);

        $this->discountHelper->expects(self::once())->method('getBoltDiscountType')
            ->with('by_fixed')->willReturn('fixed_amount');
      
        $this->currentMock->expects(self::once())->method('verifyCouponCode')
            ->with(self::COUPON_CODE, self::WEBSITE_ID, self::STORE_ID)->willReturn([null, $giftcardMock]);

        $this->expectSuccessResponse($result);

        $this->cache->expects(static::once())->method('clean')
            ->with([CartHelper::BOLT_ORDER_TAG . '_' . self::PARENT_QUOTE_ID]);
        $this->eventsForThirdPartyModules->method('dispatchEvent')->willReturnSelf();
        self::assertTrue($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_neitherCouponNorGiftcard()
    {
        $this->initCurrentMock(['validateQuote','verifyCouponCode']);
        $websiteId = 11;
        $requestContent = [
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'discount_code'   => self::COUPON_CODE,
                'metadata'        => [
                    'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
                ]
            ]
        ];
        $this->currentMock->expects(self::atLeastOnce())->method('getRequestContent')
            ->willReturn($requestContent);

        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );
        
        $this->currentMock->expects(self::once())->method('validateQuote')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn([$parentQuoteMock, $this->immutableQuoteMock]);
        
        $this->cartHelper->expects(self::once())->method('getImmutableQuoteIdFromBoltCartArray')
            ->with($requestContent['cart'])
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
            
        $this->currentMock->expects(self::once())->method('preProcessWebhook')->with(self::STORE_ID);

        $this->currentMock->expects(self::once())->method('verifyCouponCode')
            ->with(self::COUPON_CODE, self::WEBSITE_ID, self::STORE_ID)->willReturn([null, null]);

        self::assertFalse($this->currentMock->validate());
    }

    /**
     * @test
     */
    public function validate_webhookPreProcessException()
    {
        $this->initCurrentMock(['validateQuote']);
        
        $requestContent = [
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'discount_code'   => self::COUPON_CODE,
                'metadata'        => [
                    'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
                ]
            ]
        ];

        $this->currentMock->expects(self::atLeastOnce())->method('getRequestContent')
            ->willReturn($requestContent);
            
        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );
        
        $this->cartHelper->expects(self::once())->method('getImmutableQuoteIdFromBoltCartArray')
            ->with($requestContent['cart'])
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
            
        $this->currentMock->expects(self::once())->method('validateQuote')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn([$parentQuoteMock, $this->immutableQuoteMock]);

        $this->currentMock->expects(self::once())->method('preProcessWebhook')
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
        $this->initCurrentMock(['validateQuote']);
        
        $requestContent = [
            'cart' => [
                'order_reference' => self::PARENT_QUOTE_ID,
                'discount_code'   => self::COUPON_CODE,
                'metadata'        => [
                    'immutable_quote_id' => self::IMMUTABLE_QUOTE_ID,
                ]
            ]
        ];

        $this->currentMock->expects(self::atLeastOnce())->method('getRequestContent')
            ->willReturn($requestContent);
            
        $parentQuoteMock = $this->getQuoteMock(
            self::COUPON_CODE,
            null,
            null,
            false,
            self::PARENT_QUOTE_ID,
            self::PARENT_QUOTE_ID
        );
        
        $this->cartHelper->expects(self::once())->method('getImmutableQuoteIdFromBoltCartArray')
            ->with($requestContent['cart'])
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
            
        $this->currentMock->expects(self::once())->method('validateQuote')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn([$parentQuoteMock, $this->immutableQuoteMock]);
            
        $this->currentMock->expects(self::once())->method('preProcessWebhook')
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
     * @covers ::getParentQuoteDiscountResult
     */
    public function getParentQuoteDiscountResult_couponNotFound()
    {
        $this->initCurrentMock();
        $this->configureCouponMockMethods([
                'getRuleId' => [
                    'expects' => 'once'
                ]
            ]);

        $this->ruleRepositoryMock->expects(self::once())->method('getById')->with(self::RULE_ID)
            ->willThrowException(new NoSuchEntityException());

        $this->expectException(BoltException::class);
        $this->invokeNonAccessibleMethod(
            'getParentQuoteDiscountResult',
            [self::COUPON_CODE, $this->couponMock, $this->parentQuoteMock]
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
        $quote->method('getQuoteCurrencyCode')->willReturn('USD');
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
                    'getCartData',
                    'getImmutableQuoteIdFromBoltCartArray'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $this->configHelper = $this->getMockBuilder(ConfigHelper::class)
            ->setMethods(['getIgnoredShippingAddressCoupons'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->discountHelper = $this->getMockBuilder(DiscountHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderHelper = $this->getMockBuilder(OrderHelper::class)
            ->setMethods(
                [
                    'getExistingOrder',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $this->bugsnag = $this->getMockBuilder(Bugsnag::class)
            ->setMethods(['notifyException'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->cache = $this->getMockBuilder(\Magento\Framework\App\CacheInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    protected function initRequiredMocks()
    {
        $this->updateCartContext = $this->getMockBuilder(UpdateCartContext::class)
            ->disableOriginalConstructor()
            ->getMock();
            
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

        $this->eventsForThirdPartyModules = $this->createPartialMock(EventsForThirdPartyModules::class, ['runFilter','dispatchEvent']);
        $this->eventsForThirdPartyModules
            ->method('runFilter')
            ->will($this->returnArgument(1));

        $this->createFactoryMocks();
        $this->createHelperMocks();
    }

    protected function initCurrentMock($methods = [])
    {
        $methods = array_merge(['getRequestContent','preProcessWebhook'], $methods);
        $this->currentMock = $this->getMockBuilder(BoltDiscountCodeValidation::class)
            ->setMethods($methods)
            ->setConstructorArgs(
                [
                    $this->updateCartContext,
                ]
            )
            ->enableOriginalConstructor()
            ->disableProxyingToOriginalMethods()
            ->getMock();
        
        TestHelper::setProperty($this->currentMock, 'bugsnag', $this->bugsnag);
        TestHelper::setProperty($this->currentMock, 'eventsForThirdPartyModules', $this->eventsForThirdPartyModules);
        TestHelper::setProperty($this->currentMock, 'logHelper', $this->logHelper);
        TestHelper::setProperty($this->currentMock, 'cartHelper', $this->cartHelper);        
        TestHelper::setProperty($this->currentMock, 'cache', $this->cache);
        TestHelper::setProperty($this->currentMock, 'discountHelper', $this->discountHelper);
        TestHelper::setProperty($this->currentMock, 'checkoutSession', $this->checkoutSession);
        TestHelper::setProperty($this->currentMock, 'errorResponse', $this->errorResponse);
        TestHelper::setProperty($this->currentMock, 'response', $this->response);
        TestHelper::setProperty($this->currentMock, 'configHelper', $this->configHelper);
        TestHelper::setProperty($this->currentMock, 'ruleRepository', $this->ruleRepositoryMock);
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
        $this->bugsnag->expects(self::once())->method('notifyException')->with(
            new \Exception($message)
        );
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
