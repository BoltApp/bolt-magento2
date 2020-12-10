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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebApiException;
use Magento\Quote\Model\Quote;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleRepository;
use Magento\SalesRule\Model\Coupon;
use Magento\SalesRule\Model\ResourceModel\Coupon\UsageFactory;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\SalesRule\Model\Rule\CustomerFactory;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Checkout\Model\Session as CheckoutSession;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use PHPUnit\Framework\TestCase;

/**
 * Class UpdateDiscountTraitTest
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\UpdateDiscountTrait
 */
class UpdateDiscountTraitTest extends TestCase
{
    const QUOTE_ID = 1;
    const ORDER_ID = 2;
    const INCREMENT_ID = 3;
    const WEBSITE_ID = 1;
    const STORE_ID = 1;
    const COUPON_CODE = 'testcoupon';
    const COUPON_ID = 5;
    const RULE_ID = 6;
    const USAGE_LIMIT = 100;
    const CUSTOMER_ID = 200;

    /**
     * @var RuleRepository|MockObject
     */
    private $ruleRepository;

    /**
     * @var LogHelper|MockObject
     */
    private $logHelper;

    /**
     * @var UsageFactory|MockObject
     */
    private $usageFactory;

    /**
     * @var DataObjectFactory|MockObject
     */
    private $objectFactory;

    /**
     * @var TimezoneInterface|MockObject
     */
    private $timezone;

    /**
     * @var CustomerFactory|MockObject
     */
    private $customerFactory;

    /**
     * @var Bugsnag|MockObject
     */
    private $bugsnag;

    /**
     * @var DiscountHelper|MockObject
     */
    private $discountHelper;

    /**
     * @var TotalsCollector|MockObject
     */
    private $totalsCollector;

    /**
     * @var Rule|MockObject
     */
    private $ruleMock;

    /**
     * @var MockObject|\Magento\Quote\Model\Quote\Address
     */
    private $shippingAddressMock;

    /**
     * @var MockObject|Rule\Customer
     */
    private $ruleCustomerMock;

    /**
     * @var MockObject|DataObject
     */
    private $dataObjectMock;

    /**
     * @var MockObject|SessionHelper
     */
    protected $sessionHelper;

    /**
     * @var MockObject|EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;

    /**
     * @var UpdateDiscountTrait
     */
    private $currentMock;


    public function setUp()
    {
        global $ifRunFilter;
        $ifRunFilter = false;

        $this->currentMock = $this->getMockBuilder(UpdateDiscountTrait::class)
            ->setMethods(['sendErrorResponse'])
            ->disableOriginalConstructor()
            ->getMockForTrait();

        $this->ruleRepository = $this->getMockBuilder(RuleRepository::class)
            ->setMethods(['getById'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->logHelper = $this->createMock(LogHelper::class);

        $this->usageFactory = $this->getMockBuilder(UsageFactory::class)
            ->setMethods(['create', 'loadByCustomerCoupon'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->usageFactory->method('create')
            ->willReturnSelf();
        $this->usageFactory->method('loadByCustomerCoupon')
            ->withAnyParameters()
            ->willReturnSelf();


        $this->objectFactory = $this->getMockBuilder(DataObjectFactory::class)
            ->setMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->timezone = $this->createMock(TimezoneInterface::class);

        $this->customerFactory = $this->getMockBuilder(CustomerFactory::class)
            ->setMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->bugsnag = $this->createMock(Bugsnag::class);

        $this->discountHelper = $this->getMockBuilder(DiscountHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->sessionHelper = $this->getMockBuilder(SessionHelper::class)
            ->setMethods(['getCheckoutSession'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->eventsForThirdPartyModules = $this->createPartialMock(EventsForThirdPartyModules::class, ['runFilter','dispatchEvent']);
        $this->eventsForThirdPartyModules
            ->method('runFilter')
            ->will($this->returnCallback(function($class, $result, $couponCode, $quote) {
                global $ifRunFilter;
                if ($ifRunFilter) {
                    return $ifRunFilter;
                } else {
                    return $result;
                }
            }));

        TestHelper::setProperty($this->currentMock, 'ruleRepository', $this->ruleRepository);
        TestHelper::setProperty($this->currentMock, 'logHelper', $this->logHelper);
        TestHelper::setProperty($this->currentMock, 'usageFactory', $this->usageFactory);
        TestHelper::setProperty($this->currentMock, 'objectFactory', $this->objectFactory);
        TestHelper::setProperty($this->currentMock, 'timezone', $this->timezone);
        TestHelper::setProperty($this->currentMock, 'customerFactory', $this->customerFactory);
        TestHelper::setProperty($this->currentMock, 'bugsnag', $this->bugsnag);
        TestHelper::setProperty($this->currentMock, 'discountHelper', $this->discountHelper);
        TestHelper::setProperty($this->currentMock, 'totalsCollector', $this->totalsCollector);
        TestHelper::setProperty($this->currentMock, 'sessionHelper', $this->sessionHelper);
        TestHelper::setProperty($this->currentMock, 'eventsForThirdPartyModules', $this->eventsForThirdPartyModules);

        $this->initRequiredMocks();
    }

    public function tearDown() {
		parent::tearDown();
		global $ifRunFilter;
        $ifRunFilter = false;
	}

    protected function initRequiredMocks()
    {
        $this->ruleMock = $this->getMockBuilder(Rule::class)
            ->setMethods(
                [
                    'getRuleId',
                    'getToDate',
                    'getFromDate',
                    'getWebsiteIds',
                    'getUsesPerCustomer',
                    'getDescription',
                    'getCustomerGroupIds',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $this->ruleRepository->method('getById')->with(self::RULE_ID)->willReturn($this->ruleMock);

        $this->shippingAddressMock = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
            ->setMethods(['getDiscountAmount'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->ruleCustomerMock = $this->getMockBuilder(Rule\Customer::class)
            ->setMethods(['loadByCustomerRule', 'getId', 'getTimesUsed'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->customerFactory->method('create')
            ->willReturn($this->ruleCustomerMock);
        $this->ruleCustomerMock->method('loadByCustomerRule')
            ->withAnyParameters()
            ->willReturnSelf();

        $this->dataObjectMock = $this->getMockBuilder(DataObject::class)
            ->setMethods(['getCouponId', 'getTimesUsed'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->objectFactory->method('create')
            ->willReturn($this->dataObjectMock);

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
        $couponCode = self::COUPON_CODE,
        $shippingAddress = null,
        $customerId = null,
        $isVirtual = false,
        $quoteId = self::QUOTE_ID
    ) {
        if (is_null($shippingAddress)) {
            $shippingAddress = $this->shippingAddressMock;
        }

        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(
                [
                    'getId',
                    'getStore',
                    'getWebsiteId',
                    'getCustomerId',
                    'getCouponCode',
                    'isVirtual',
                    'getShippingAddress',
                    'getBillingAddress',
                    'getQuoteCurrencyCode',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $quote->method('getId')->willReturn($quoteId);
        $quote->method('getStore')->willReturnSelf();
        $quote->method('getWebsiteId')->willReturn(self::WEBSITE_ID);
        $quote->method('getCustomerId')->willReturn($customerId);
        $quote->method('getCouponCode')->willReturn($couponCode);
        $quote->method('isVirtual')->willReturn($isVirtual);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($shippingAddress);
        $quote->method('getQuoteCurrencyCode')->willReturn('USD');

        return $quote;
    }

    /**
     * Override the default configuration with passed $config and
     * set up $couponMock method expectations and return values
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
    private function getCouponMock($config = [])
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

        $couponMock = $this->getMockBuilder(Coupon::class)
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

        foreach ($config as $method => $options) {

            $builder = $couponMock;

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

        return $couponMock;
    }

    /**
     * @test
     * that verifyCouponCode will throw an exception if the code string is empty
     *
     */
    public function verifyCouponCode_withEmptyCode_throwsException()
    {
        $this->expectExceptionMessage('No coupon code provided');
        $this->expectExceptionCode(BoltErrorResponse::ERR_CODE_INVALID);
        $this->expectException(BoltException::class);

        $result = TestHelper::invokeMethod($this->currentMock, 'verifyCouponCode', ['', self::WEBSITE_ID, self::STORE_ID]);
    }

    /**
     * @test
     *
     */
    public function verifyCouponCode_returnMagentoGiftCardAccount()
    {
        $giftcardMock = $this->getMockBuilder('\Magento\GiftCardAccount\Model\Giftcardaccount')
            ->disableOriginalConstructor()
            ->getMock();

        $this->discountHelper->expects(static::once())->method('loadMagentoGiftCardAccount')->with(self::COUPON_CODE, self::WEBSITE_ID)
            ->willReturn($giftcardMock);

        $result = TestHelper::invokeMethod($this->currentMock, 'verifyCouponCode', [self::COUPON_CODE, self::WEBSITE_ID, self::STORE_ID]);

        $this->assertEquals([null,$giftcardMock], $result);
    }

    /**
     * @test
     *
     */
    public function verifyCouponCode_returnAmastyGiftCard()
    {
        global $ifRunFilter;
        $giftcardMock = $this->getMockBuilder('\Amasty\GiftCard\Model\Account')
            ->disableOriginalConstructor()
            ->getMock();
        $ifRunFilter = $giftcardMock;

        $this->discountHelper->expects(static::once())->method('loadMagentoGiftCardAccount')->with(self::COUPON_CODE, self::WEBSITE_ID)
            ->willReturn(null);
        $this->discountHelper->expects(static::once())->method('loadUnirgyGiftCertData')->with(self::COUPON_CODE, self::STORE_ID)
            ->willReturn(null);
        $result = TestHelper::invokeMethod($this->currentMock, 'verifyCouponCode', [self::COUPON_CODE, self::WEBSITE_ID, self::STORE_ID]);

        $this->assertEquals([null,$giftcardMock], $result);
    }

    /**
     * @test
     * that verifyCouponCode throws an exception if the coupon is not found.
     *
     */
    public function verifyCouponCode_couponObjNull_throwsException()
    {
        $this->expectExceptionMessage(sprintf('The coupon code %s is not found', self::COUPON_CODE));
        $this->expectExceptionCode(BoltErrorResponse::ERR_CODE_INVALID);
        $this->expectException(BoltException::class);

        $this->discountHelper->expects(static::once())->method('loadMagentoGiftCardAccount')->with(self::COUPON_CODE, self::WEBSITE_ID)
            ->willReturn(null);

        $this->discountHelper->expects(static::once())->method('loadUnirgyGiftCertData')->with(self::COUPON_CODE, self::STORE_ID)
            ->willReturn(null);

        $this->discountHelper->expects(static::once())->method('loadCouponCodeData')->with(self::COUPON_CODE)
            ->willReturn(null);

        $result = TestHelper::invokeMethod($this->currentMock, 'verifyCouponCode', [self::COUPON_CODE, self::WEBSITE_ID, self::STORE_ID]);
    }

    /**
     * @test
     * that verifyCouponCode will throw an exception if the coupon object is new.
     *
     */
    public function verifyCouponCode_couponObjNew_throwsException()
    {
        $coupon = $this->getCouponMock([
                'isObjectNew' => [
                    'expects' => 'once',
                    'returnValue' => true,
                ]
            ]);

        $this->expectExceptionMessage(sprintf('The coupon code %s is not found', self::COUPON_CODE));
        $this->expectExceptionCode(BoltErrorResponse::ERR_CODE_INVALID);
        $this->expectException(BoltException::class);

        $this->discountHelper->expects(static::once())->method('loadMagentoGiftCardAccount')->with(self::COUPON_CODE, self::WEBSITE_ID)
            ->willReturn(null);

        $this->discountHelper->expects(static::once())->method('loadUnirgyGiftCertData')->with(self::COUPON_CODE, self::STORE_ID)
            ->willReturn(null);

        $this->discountHelper->expects(static::once())->method('loadCouponCodeData')->with(self::COUPON_CODE)
            ->willReturn($coupon);

        $result = TestHelper::invokeMethod($this->currentMock, 'verifyCouponCode', [self::COUPON_CODE, self::WEBSITE_ID, self::STORE_ID]);
    }

    /**
     * @test
     *
     */
    public function verifyCouponCode_success_returnCoupon()
    {
        $coupon = $this->getCouponMock([
                'isObjectNew' => [
                    'expects' => 'once',
                ]
            ]);

        $this->discountHelper->expects(static::once())->method('loadMagentoGiftCardAccount')->with(self::COUPON_CODE, self::WEBSITE_ID)
            ->willReturn(null);

        $this->discountHelper->expects(static::once())->method('loadUnirgyGiftCertData')->with(self::COUPON_CODE, self::STORE_ID)
            ->willReturn(null);

        $this->discountHelper->expects(static::once())->method('loadCouponCodeData')->with(self::COUPON_CODE)
            ->willReturn($coupon);

        $result = TestHelper::invokeMethod($this->currentMock, 'verifyCouponCode', [self::COUPON_CODE, self::WEBSITE_ID, self::STORE_ID]);

        $this->assertEquals([$coupon, null], $result);
    }

    /**
     * @test
     *
     */
    public function applyDiscount_applyCoupon_returnTrue()
    {
        $quote = $this->getQuoteMock();

        $coupon = $this->getCouponMock([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ]
            ]);

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));
        $this->ruleMock->expects(self::once())->method('getCustomerGroupIds')
            ->willReturn([0,1]);

        $this->discountHelper->expects(self::once())->method('setCouponCode')
            ->with($quote, self::COUPON_CODE);

        $checkoutSession = $this->createPartialMock(CheckoutSession::class,
            ['getBoltCollectSaleRuleDiscounts']
        );
        $checkoutSession->expects(static::once())
                        ->method('getBoltCollectSaleRuleDiscounts')
                        ->willReturn([self::RULE_ID => 10]);
        $this->sessionHelper->expects(static::once())->method('getCheckoutSession')
             ->willReturn($checkoutSession);

        $result = TestHelper::invokeMethod($this->currentMock, 'applyDiscount', [self::COUPON_CODE, $coupon, null, $quote]);

        $this->assertTrue(!empty($result));
    }

    /**
     * @test
     *
     */
    public function applyDiscount_applyGiftCard_returnTrue()
    {
        $quote = $this->getQuoteMock();

        $giftcardMock = $this->getMockBuilder('\Magento\GiftCardAccount\Model\Giftcardaccount')
            ->disableOriginalConstructor()
            ->setMethods(['getId', 'removeFromCart', 'addToCart'])
            ->getMock();

        $giftcardMock->method('getId')->willReturn(123);

        $giftcardMock->expects(self::once())->method('removeFromCart')
            ->with(true, $quote);

        $giftcardMock->expects(self::once())->method('addToCart')
            ->with(true, $quote);

        $result = TestHelper::invokeMethod($this->currentMock, 'applyDiscount', [self::COUPON_CODE, null, $giftcardMock, $quote]);

        $this->assertTrue(!empty($result));
    }

    /**
     * @test
     */
    public function applyDiscount_applyCoupon_throwException()
    {
        $quote = $this->getQuoteMock();

        $coupon = $this->getCouponMock([
                'getCouponId' => [
                    'expects' => 'once',
                    'returnValue' => 0,
                ]
            ]);

        $this->expectException(WebApiException::class);
        $this->expectExceptionMessage('Something happened with current code.');

        TestHelper::invokeMethod($this->currentMock, 'applyDiscount', [self::COUPON_CODE, $coupon, null, $quote]);
    }

    /**
     * @test
     *
     */
    public function applyingCouponCode()
    {
        $coupon = $this->getCouponMock([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ]
            ]);
        $quote = $this->getQuoteMock();

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));
        $this->ruleMock->expects(self::once())->method('getDescription')
            ->willReturn('TESTCOUPON');
        $this->ruleMock->expects(self::once())->method('getCustomerGroupIds')
            ->willReturn([0,1]);

        $this->discountHelper->expects(self::once())->method('setCouponCode')
            ->with($quote, self::COUPON_CODE);
        $this->discountHelper->expects(self::once())->method('convertToBoltDiscountType')
            ->with(self::COUPON_CODE)->willReturn('fixed_amount');

        $shippingDiscountAmount = 1000;

        $checkoutSession = $this->createPartialMock(CheckoutSession::class,
            ['getBoltCollectSaleRuleDiscounts']
        );
        $checkoutSession->expects(static::once())
                        ->method('getBoltCollectSaleRuleDiscounts')
                        ->willReturn([self::RULE_ID => 10]);
        $this->sessionHelper->expects(static::once())->method('getCheckoutSession')
             ->willReturn($checkoutSession);

        $result = [
            'status'          => 'success',
            'discount_code'   => self::COUPON_CODE,
            'discount_amount' => $shippingDiscountAmount,
            'description'     => 'Discount TESTCOUPON',
            'discount_type'   => 'fixed_amount',
        ];

        $result = TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);

        $this->assertTrue(!empty($result));
    }

    /**
     * @test
     *
     */
    public function applyingCouponCode_noSuchEntity()
    {
        $coupon = $this->getCouponMock([
                'getRuleId' => [
                    'expects' => 'once',
                    'returnMethod' => 'willThrowException',
                    'returnValue' => new NoSuchEntityException()
                ]
            ]);

        $quote = $this->getQuoteMock();

        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(sprintf('The coupon code %s is not found', self::COUPON_CODE));
        $this->expectExceptionCode(BoltErrorResponse::ERR_CODE_INVALID);

        TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
    }

    /**
     * @test
     *
     */
    public function applyingCouponCode_invalidToDate()
    {
        $coupon = $this->getCouponMock([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ]
            ]);
        $quote = $this->getQuoteMock();

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(sprintf('The code [%s] has expired', self::COUPON_CODE));
        $this->expectExceptionCode(BoltErrorResponse::ERR_CODE_EXPIRED);

        TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
    }

    /**
     * @test
     *
     */
    public function applyingCouponCode_invalidFromDate()
    {
        $coupon = $this->getCouponMock([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ]
            ]);
        $quote = $this->getQuoteMock();

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

        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(sprintf('Code available from %s', $fromDate));
        $this->expectExceptionCode(BoltErrorResponse::ERR_CODE_NOT_AVAILABLE);

        TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
    }

    /**
     * @test
     *
     */
    public function applyingCouponCode_exceededUsageLimit()
    {
        $coupon = $this->getCouponMock([
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
            ]);
        $quote = $this->getQuoteMock();

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(sprintf('The code [%s] has exceeded usage limit', self::COUPON_CODE));
        $this->expectExceptionCode(BoltErrorResponse::ERR_CODE_LIMIT_REACHED);
        
        TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
    }

    /**
     * @test
     *
     */
    public function applyingCouponCode_perRuleCustomerUsage_exceededUsageLimit()
    {
        $coupon = $this->getCouponMock([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ],
                'getUsagePerCustomer' => [
                    'expects' => 'once'
                ]
            ]);
        $quote = $this->getQuoteMock(self::COUPON_CODE,null,self::CUSTOMER_ID);

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));
        $this->ruleMock->expects(self::once())->method('getUsesPerCustomer')->willReturn(1);

        $this->ruleCustomerMock->method('getId')->willReturn(1);
        $this->ruleCustomerMock->method('getTimesUsed')->willReturn(1);

        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(sprintf('The code [%s] has exceeded usage limit', self::COUPON_CODE));
        $this->expectExceptionCode(BoltErrorResponse::ERR_CODE_LIMIT_REACHED);

        TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
    }

    /**
     * @test
     *
     */
    public function applyingCouponCode_perCustomerUsage_exceededUsageLimit()
    {
        $coupon = $this->getCouponMock([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ],
                'getUsagePerCustomer' => [
                    'expects' => 'once'
                ]
            ]);
        $quote = $this->getQuoteMock(self::COUPON_CODE,null,self::CUSTOMER_ID);

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));

        $this->dataObjectMock->method('getCouponId')->willReturn(self::COUPON_ID);
        $this->dataObjectMock->method('getTimesUsed')->willReturn(1);

        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(sprintf('The code [%s] has exceeded usage limit', self::COUPON_CODE));
        $this->expectExceptionCode(BoltErrorResponse::ERR_CODE_LIMIT_REACHED);

        TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
    }

    /**
     * @test
     *
     */
    public function applyingCouponCode_codeRequiresLogin()
    {
        $coupon = $this->getCouponMock([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ]
            ]);
        $quote = $this->getQuoteMock(self::COUPON_CODE,null,0);

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));
        $this->ruleMock->expects(self::once())->method('getCustomerGroupIds')
            ->willReturn([1]);

        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(sprintf('The coupon code %s requires login', self::COUPON_CODE));
        $this->expectExceptionCode(BoltErrorResponse::ERR_CODE_REQUIRES_LOGIN);

        TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
    }

    /**
     * @test
     *
     */
    public function applyingCouponCode_virtualImmutableQuote()
    {
        $coupon = $this->getCouponMock([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ]
            ]);

        $quote = $this->getQuoteMock(self::COUPON_CODE,null,null,true);

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));
        $this->ruleMock->expects(self::once())->method('getCustomerGroupIds')
            ->willReturn([0,1]);

        $this->dataObjectMock->method('getCouponId')->willReturn(self::COUPON_ID);
        $this->dataObjectMock->method('getTimesUsed')->willReturn(1);

        $this->discountHelper->expects(self::once())->method('setCouponCode')
            ->with($quote, self::COUPON_CODE);

        $checkoutSession = $this->createPartialMock(CheckoutSession::class,
            ['getBoltCollectSaleRuleDiscounts']
        );
        $checkoutSession->expects(static::once())
                        ->method('getBoltCollectSaleRuleDiscounts')
                        ->willReturn([self::RULE_ID => 10]);
        $this->sessionHelper->expects(static::once())->method('getCheckoutSession')
             ->willReturn($checkoutSession);

        $result = TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);

        $this->assertTrue(!empty($result));
    }

    /**
     * @test
     *
     */
    public function applyingCouponCode_couponCodeNotSet()
    {
        $coupon = $this->getCouponMock([
                'getId' => [
                    'expects' => 'once'
                ],
                'getRuleId' => [
                    'expects' => 'once'
                ]
            ]);

        $this->ruleMock->expects(self::once())->method('getWebsiteIds')->willReturn([self::WEBSITE_ID]);
        $this->ruleMock->expects(self::once())->method('getRuleId')->willReturn(self::RULE_ID);
        $this->ruleMock->expects(self::once())->method('getToDate')
            ->willReturn(date('Y-m-d', strtotime('tomorrow')));
        $this->ruleMock->expects(self::once())->method('getFromDate')
            ->willReturn(date('Y-m-d', strtotime('yesterday')));
        $this->ruleMock->expects(self::once())->method('getCustomerGroupIds')
            ->willReturn([0,1]);

        $this->dataObjectMock->method('getCouponId')->willReturn(self::COUPON_ID);
        $this->dataObjectMock->method('getTimesUsed')->willReturn(1);

        $quote = $this->getQuoteMock('');
        $quote->method('getCouponCode')->willReturn(null);

        $this->expectException(BoltException::class);
        $this->expectExceptionMessage('Coupon code does not equal with a quote code');
        $this->expectExceptionCode(BoltErrorResponse::ERR_SERVICE);

        TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
    }

    /**
     * @test
     *
     * @covers \Bolt\Boltpay\Model\Api\UpdateDiscountTrait::applyingGiftCardCode
     */
    public function applyingGiftCardCode_amastyGiftCard()
    {
        global $ifRunFilter;
        $quote = $this->getQuoteMock();

        $giftcardMock = $this->getMockBuilder('\Amasty\GiftCard\Model\Account')
            ->setMethods(['getCodeId'])
            ->disableOriginalConstructor()
            ->getMock();
        $ifRunFilter = $giftcardMock;
        $giftcardMock->expects(static::never())->method('getCodeId')->willReturn(self::COUPON_ID);

        $result = TestHelper::invokeMethod($this->currentMock, 'applyingGiftCardCode', [self::COUPON_CODE, $giftcardMock, $quote]);

        $this->assertTrue($result);
    }

    /**
     * @test
     *
     */
    public function applyingGiftCardCode_magentoGiftCard()
    {
        $quote = $this->getQuoteMock();

        $giftcardMock = $this->getMockBuilder('\Magento\GiftCardAccount\Model\Giftcardaccount')
            ->setMethods(['removeFromCart', 'addToCart'])
            ->disableOriginalConstructor()
            ->getMock();
        $giftcardMock->expects(static::once())->method('removeFromCart')->with(true, $quote);
        $giftcardMock->expects(static::once())->method('addToCart')->with(true, $quote);

        $result = TestHelper::invokeMethod($this->currentMock, 'applyingGiftCardCode', [self::COUPON_CODE, $giftcardMock, $quote]);

        $this->assertTrue($result);
    }

    /**
     * @test
     *
     */
    public function removeDiscount_removeCoupon()
    {
        $discounts = [
            self::COUPON_CODE => 'coupon',
        ];
        $quote = $this->getQuoteMock();

        $this->discountHelper->expects(self::once())->method('setCouponCode')
            ->with($quote, '');

        $result = TestHelper::invokeMethod($this->currentMock, 'removeDiscount', [self::COUPON_CODE, $discounts, $quote, self::WEBSITE_ID, self::STORE_ID]);

        $this->assertTrue($result);
    }

    /**
     * @test
     *
     */
    public function removeDiscount_removeStoreCredit()
    {
        $discounts = [
            'amstorecredit' => 'store_credit',
        ];
        $quote = $this->getQuoteMock();

        $result = TestHelper::invokeMethod($this->currentMock, 'removeDiscount', ['amstorecredit', $discounts, $quote, self::WEBSITE_ID, self::STORE_ID]);

        $this->assertTrue($result);
    }

    /**
     * @test
     *
     */
    public function removeDiscount_removeGiftCard()
    {
        $couponCode = 'giftcard1234';
        $discounts = [
            $couponCode => 'gift_card',
        ];
        $quote = $this->getQuoteMock();

        $giftcardMock = $this->getMockBuilder('\Magento\GiftCardAccount\Model\Giftcardaccount')
            ->setMethods(['removeFromCart', 'addToCart'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->discountHelper->expects(static::once())->method('loadMagentoGiftCardAccount')->with($couponCode, self::WEBSITE_ID)
            ->willReturn($giftcardMock);

        $result = TestHelper::invokeMethod($this->currentMock, 'removeDiscount', [$couponCode, $discounts, $quote, self::WEBSITE_ID, self::STORE_ID]);

        $this->assertTrue($result);
    }

    /**
     * @test
     *
     */
    public function removeDiscount_codeNotExist()
    {
        $discounts = [
            'testcoupon1' => 'coupon',
        ];
        $quote = $this->getQuoteMock();

        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_SERVICE,'Coupon code ' . self::COUPON_CODE . ' does not exist!',422,$quote);

        $result = TestHelper::invokeMethod($this->currentMock, 'removeDiscount', [self::COUPON_CODE, $discounts, $quote, self::WEBSITE_ID, self::STORE_ID]);
        $this->assertFalse($result);
    }

    /**
     * @test
     *
     */
    public function removeCouponCode()
    {
        $quote = $this->getQuoteMock();

        $this->discountHelper->expects(self::once())->method('setCouponCode')
            ->with($quote, '');

        $result = TestHelper::invokeMethod($this->currentMock, 'removeCouponCode', [$quote]);

        $this->assertTrue($result);
    }

    /**
     * @test
     *
     */
    public function removeCouponCode_throwException()
    {
        $quote = $this->getQuoteMock();

        $exception = new \Exception('General exception');
        $this->discountHelper->expects(self::once())->method('setCouponCode')
            ->with($quote, '')->willThrowException($exception);

        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_SERVICE,'General exception',422,$quote);

        $result = TestHelper::invokeMethod($this->currentMock, 'removeCouponCode', [$quote]);

        $this->assertFalse($result);
    }

    /**
     * @test
     *
     */
    public function removeGiftCardCode_amastyGiftCard()
    {
        global $ifRunFilter;
        $ifRunFilter = true;
        $codeId = 100;
        $quote = $this->getQuoteMock();
        $giftcardMock = $this->getMockBuilder('\Amasty\GiftCard\Model\Account')
            ->setMethods(['getCodeId'])
            ->disableOriginalConstructor()
            ->getMock();
        $giftcardMock->expects(static::never())->method('getCodeId')->willReturn($codeId);
        $this->discountHelper->expects(static::never())->method('removeAmastyGiftCard')->with($codeId, $quote);

        $result = TestHelper::invokeMethod($this->currentMock, 'removeGiftCardCode', [self::COUPON_CODE, $giftcardMock, $quote]);

        $this->assertTrue($result);
    }

    /**
     * @test
     *
     */
    public function removeGiftCardCode_magentoGiftCard()
    {
        $codeId = 100;
        $quote = $this->getQuoteMock();
        $giftcardMock = $this->getMockBuilder('\Magento\GiftCardAccount\Model\Giftcardaccount')
            ->setMethods(['removeFromCart'])
            ->disableOriginalConstructor()
            ->getMock();
        $giftcardMock->expects(static::once())->method('removeFromCart')->with(true, $quote);

        $result = TestHelper::invokeMethod($this->currentMock, 'removeGiftCardCode', [self::COUPON_CODE, $giftcardMock, $quote]);

        $this->assertTrue($result);
    }

    /**
     * @test
     *
     */
    public function removeGiftCardCode_throwException()
    {
        $quote = $this->getQuoteMock();

        $this->expectException(BoltException::class);
        $this->expectExceptionMessage(sprintf('The GiftCard %s does not support removal', self::COUPON_CODE));
        $this->expectExceptionCode(BoltErrorResponse::ERR_SERVICE);

        TestHelper::invokeMethod($this->currentMock, 'removeGiftCardCode', [self::COUPON_CODE, null, $quote]);
    }

    /**
     * @test
     *
     */
    public function getAppliedStoreCredit_returnArray()
    {
        global $ifRunFilter;
        $ifRunFilter = ['amstorecredit'];

        $quote = $this->getQuoteMock();

        $result = TestHelper::invokeMethod($this->currentMock, 'getAppliedStoreCredit', ['amstorecredit', $quote]);

        $expectedResult = [
            [
                'discount_category' => 'store_credit',
                'reference'         => 'amstorecredit',
            ]
        ];
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @test
     *
     */
    public function getAppliedStoreCredit_returnFalse()
    {
        $quote = $this->getQuoteMock();

        $result = TestHelper::invokeMethod($this->currentMock, 'getAppliedStoreCredit', ['amstorecredit', $quote]);

        $this->assertFalse($result);
    }

}
