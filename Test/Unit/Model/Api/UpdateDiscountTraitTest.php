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
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Model\Api\UpdateCartContext;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Model\Api\UpdateDiscountTrait;
use PHPUnit\Framework\TestCase;

/**
 * Class UpdateDiscountTraitTest
 * @coversDefaultClass \Bolt\Boltpay\Controller\UpdateDiscountTrait
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
     * @var UpdateDiscountTrait
     */
    private $currentMock;


    public function setUp()
    {            
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

        $this->totalsCollector = $this->getMockBuilder(TotalsCollector::class)
            ->setMethods(['collectAddressTotals'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->totalsCollector->method('collectAddressTotals')
            ->withAnyParameters()
            ->willReturnSelf();
        
        TestHelper::setProperty($this->currentMock, 'ruleRepository', $this->ruleRepository);
        TestHelper::setProperty($this->currentMock, 'logHelper', $this->logHelper);
        TestHelper::setProperty($this->currentMock, 'usageFactory', $this->usageFactory);
        TestHelper::setProperty($this->currentMock, 'objectFactory', $this->objectFactory);
        TestHelper::setProperty($this->currentMock, 'timezone', $this->timezone);
        TestHelper::setProperty($this->currentMock, 'customerFactory', $this->customerFactory);
        TestHelper::setProperty($this->currentMock, 'bugsnag', $this->bugsnag);
        TestHelper::setProperty($this->currentMock, 'discountHelper', $this->discountHelper);
        TestHelper::setProperty($this->currentMock, 'totalsCollector', $this->totalsCollector);
        
        $this->initRequiredMocks();
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
                    'getUsesPerCustomer'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        
        $this->ruleRepository->method('getById')->with(self::RULE_ID)->willReturn($this->ruleMock);
        
        $this->shippingAddressMock = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
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
     * 
     */
    public function verifyCouponCode_withEmptyCode_returnFalse()
    {
        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_CODE_INVALID,'No coupon code provided',422);
            
        $result = TestHelper::invokeMethod($this->currentMock, 'verifyCouponCode', ['', self::WEBSITE_ID, self::STORE_ID]);
        
        $this->assertFalse($result);
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
        $giftcardMock = $this->getMockBuilder('\Amasty\GiftCard\Model\Account')
            ->disableOriginalConstructor()
            ->getMock();
        
        $this->discountHelper->expects(static::once())->method('loadMagentoGiftCardAccount')->with(self::COUPON_CODE, self::WEBSITE_ID)
            ->willReturn(null);
            
        $this->discountHelper->expects(static::once())->method('loadAmastyGiftCard')->with(self::COUPON_CODE, self::WEBSITE_ID)
            ->willReturn($giftcardMock);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'verifyCouponCode', [self::COUPON_CODE, self::WEBSITE_ID, self::STORE_ID]);
        
        $this->assertEquals([null,$giftcardMock], $result);
    }
    
    /**
     * @test
     * 
     */
    public function verifyCouponCode_returnMageplazaGiftCard()
    {
        $giftcardMock = $this->getMockBuilder('\Mageplaza\GiftCard\Model\GiftCard')
            ->disableOriginalConstructor()
            ->getMock();
        
        $this->discountHelper->expects(static::once())->method('loadMagentoGiftCardAccount')->with(self::COUPON_CODE, self::WEBSITE_ID)
            ->willReturn(null);
            
        $this->discountHelper->expects(static::once())->method('loadAmastyGiftCard')->with(self::COUPON_CODE, self::WEBSITE_ID)
            ->willReturn(null);
        
        $this->discountHelper->expects(static::once())->method('loadMageplazaGiftCard')->with(self::COUPON_CODE, self::STORE_ID)
            ->willReturn($giftcardMock);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'verifyCouponCode', [self::COUPON_CODE, self::WEBSITE_ID, self::STORE_ID]);
        
        $this->assertEquals([null,$giftcardMock], $result);
    }
    
    /**
     * @test
     * 
     */
    public function verifyCouponCode_couponObjNull_returnFalse()
    {
        $this->discountHelper->expects(static::once())->method('loadMagentoGiftCardAccount')->with(self::COUPON_CODE, self::WEBSITE_ID)
            ->willReturn(null);
            
        $this->discountHelper->expects(static::once())->method('loadAmastyGiftCard')->with(self::COUPON_CODE, self::WEBSITE_ID)
            ->willReturn(null);
        
        $this->discountHelper->expects(static::once())->method('loadMageplazaGiftCard')->with(self::COUPON_CODE, self::STORE_ID)
            ->willReturn(null);
            
        $this->discountHelper->expects(static::once())->method('loadCouponCodeData')->with(self::COUPON_CODE)
            ->willReturn(null);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'verifyCouponCode', [self::COUPON_CODE, self::WEBSITE_ID, self::STORE_ID]);
        
        $this->assertFalse($result);
    }
    
    /**
     * @test
     * 
     */
    public function verifyCouponCode_couponObjNew_returnFalse()
    {
        $coupon = $this->getCouponMock([
                'isObjectNew' => [
                    'expects' => 'once',
                    'returnValue' => true,
                ]
            ]);

        $this->discountHelper->expects(static::once())->method('loadMagentoGiftCardAccount')->with(self::COUPON_CODE, self::WEBSITE_ID)
            ->willReturn(null);
            
        $this->discountHelper->expects(static::once())->method('loadAmastyGiftCard')->with(self::COUPON_CODE, self::WEBSITE_ID)
            ->willReturn(null);
        
        $this->discountHelper->expects(static::once())->method('loadMageplazaGiftCard')->with(self::COUPON_CODE, self::STORE_ID)
            ->willReturn(null);
            
        $this->discountHelper->expects(static::once())->method('loadCouponCodeData')->with(self::COUPON_CODE)
            ->willReturn($coupon);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'verifyCouponCode', [self::COUPON_CODE, self::WEBSITE_ID, self::STORE_ID]);
        
        $this->assertFalse($result);
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
            
        $this->discountHelper->expects(static::once())->method('loadAmastyGiftCard')->with(self::COUPON_CODE, self::WEBSITE_ID)
            ->willReturn(null);
        
        $this->discountHelper->expects(static::once())->method('loadMageplazaGiftCard')->with(self::COUPON_CODE, self::STORE_ID)
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
            
        $this->discountHelper->expects(self::once())->method('setCouponCode')
            ->with($quote, self::COUPON_CODE);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'applyDiscount', [self::COUPON_CODE, $coupon, null, $quote]);
        
        $this->assertTrue($result);
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
        
        $this->assertTrue($result);
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
            
        $this->discountHelper->expects(self::once())->method('setCouponCode')
            ->with($quote, self::COUPON_CODE);
            
        $result = TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
        
        $this->assertTrue($result);
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
        
        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_CODE_INVALID,'The coupon code ' . self::COUPON_CODE . ' is not found',422,$quote);

        $result = TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
        
        $this->assertFalse($result);
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
        
        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_CODE_EXPIRED,'The code ['.self::COUPON_CODE.'] has expired.',422,$quote);

        $result = TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
        
        $this->assertFalse($result);
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

        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_CODE_NOT_AVAILABLE,'Code available from ' . $fromDate,422,$quote);

        $result = TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
        
        $this->assertFalse($result);
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

        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_CODE_LIMIT_REACHED,'The code ['.self::COUPON_CODE.'] has exceeded usage limit.',422,$quote);     

        $result = TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
        
        $this->assertFalse($result);
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

        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_CODE_LIMIT_REACHED,'The code ['.self::COUPON_CODE.'] has exceeded usage limit.',422,$quote); 

        $result = TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
        
        $this->assertFalse($result);
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

        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_CODE_LIMIT_REACHED,'The code ['.self::COUPON_CODE.'] has exceeded usage limit.',422,$quote);      

        $result = TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
        
        $this->assertFalse($result);
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

        $this->dataObjectMock->method('getCouponId')->willReturn(self::COUPON_ID);
        $this->dataObjectMock->method('getTimesUsed')->willReturn(1);
        
        $this->discountHelper->expects(self::once())->method('setCouponCode')
            ->with($quote, self::COUPON_CODE);

        $result = TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
        
        $this->assertTrue($result);
    }

    /**
     * @test
     *
     */
    public function applyingCouponCode_errorWhenSetting()
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

        $this->dataObjectMock->method('getCouponId')->willReturn(self::COUPON_ID);
        $this->dataObjectMock->method('getTimesUsed')->willReturn(1);

        $quote = $this->getQuoteMock();
        
        $exception = new \Exception('General exception');
        $this->discountHelper->expects(self::once())->method('setCouponCode')
            ->with($quote, self::COUPON_CODE)->willThrowException($exception);

        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_SERVICE,'General exception',422,$quote);

        $result = TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
        
        $this->assertFalse($result);
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

        $this->dataObjectMock->method('getCouponId')->willReturn(self::COUPON_ID);
        $this->dataObjectMock->method('getTimesUsed')->willReturn(1);

        $quote = $this->getQuoteMock('');
        $quote->method('getCouponCode')->willReturn(null);

        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_SERVICE,'Coupon code does not equal with a quote code!',422,$quote);

        $result = TestHelper::invokeMethod($this->currentMock, 'applyingCouponCode', [self::COUPON_CODE, $coupon, $quote]);
        
        $this->assertFalse($result);
    }
    
    /**
     * @test
     *
     */
    public function applyingGiftCardCode_amastyGiftCard()
    {
        $quote = $this->getQuoteMock();
        
        $giftcardMock = $this->getMockBuilder('\Amasty\GiftCard\Model\Account')
            ->disableOriginalConstructor()
            ->getMock();        
        $this->discountHelper->expects(static::once())->method('removeAmastyGiftCard')->with(self::COUPON_CODE, $quote);            
        $this->discountHelper->expects(static::once())->method('applyAmastyGiftCard')->with(self::COUPON_CODE, $giftcardMock, $quote);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'applyingGiftCardCode', [self::COUPON_CODE, $giftcardMock, $quote]);
        
        $this->assertTrue($result);
    }
    
    /**
     * @test
     *
     */
    public function applyingGiftCardCode_mageplazaGiftCard()
    {
        $quote = $this->getQuoteMock();
        
        $giftcardMock = $this->getMockBuilder('\Mageplaza\GiftCard\Model\GiftCard')
            ->disableOriginalConstructor()
            ->getMock();        
        $this->discountHelper->expects(static::once())->method('removeMageplazaGiftCard')->with(self::COUPON_CODE, $quote);            
        $this->discountHelper->expects(static::once())->method('applyMageplazaGiftCard')->with(self::COUPON_CODE, $quote);
        
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
    public function applyingGiftCardCode_throwException()
    {
        $quote = $this->getQuoteMock();
        
        $giftcardMock = $this->getMockBuilder('\Mageplaza\GiftCard\Model\GiftCard')
            ->disableOriginalConstructor()
            ->getMock();
        $exception = new \Exception('General exception');
        $this->discountHelper->expects(static::once())->method('removeMageplazaGiftCard')
            ->with(self::COUPON_CODE, $quote)->willThrowException($exception);

        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_SERVICE,'General exception',422,$quote);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'applyingGiftCardCode', [self::COUPON_CODE, $giftcardMock, $quote]);
        
        $this->assertFalse($result);
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
    public function removeDiscount_removeGiftCard()
    {
        $couponCode = 'giftcard1234';
        $discounts = [
            $couponCode => 'gift_card',    
        ];
        $quote = $this->getQuoteMock();
        $giftcardMock = $this->getMockBuilder('\Mageplaza\GiftCard\Model\GiftCard')
            ->setMethods(['getCode'])
            ->disableOriginalConstructor()
            ->getMock();
        $giftcardMock->expects(static::once())->method('getCode')->willReturn($couponCode);
        $this->discountHelper->expects(static::once())->method('loadMagentoGiftCardAccount')->with($couponCode, self::WEBSITE_ID)
            ->willReturn(null);            
        $this->discountHelper->expects(static::once())->method('loadAmastyGiftCard')->with($couponCode, self::WEBSITE_ID)
            ->willReturn(null);        
        $this->discountHelper->expects(static::once())->method('loadMageplazaGiftCard')->with($couponCode, self::STORE_ID)
            ->willReturn($giftcardMock);
        $this->discountHelper->expects(static::once())->method('removeMageplazaGiftCard')->with($couponCode, $quote);
        
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
        
        $exception = new \Exception('Coupon code ' . self::COUPON_CODE . ' does not exist!');
        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
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

        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_SERVICE,'General exception',422,$quote);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'removeCouponCode', [$quote]);
        
        $this->assertFalse($result);
    }
    
    /**
     * @test
     *
     */
    public function removeGiftCardCode_mageplazaGiftCard()
    {
        $quote = $this->getQuoteMock();
        $giftcardMock = $this->getMockBuilder('\Mageplaza\GiftCard\Model\GiftCard')
            ->setMethods(['getCode'])
            ->disableOriginalConstructor()
            ->getMock();
        $giftcardMock->expects(static::once())->method('getCode')->willReturn(self::COUPON_CODE);        
        $this->discountHelper->expects(static::once())->method('removeMageplazaGiftCard')->with(self::COUPON_CODE, $quote);            

        $result = TestHelper::invokeMethod($this->currentMock, 'removeGiftCardCode', [self::COUPON_CODE, $giftcardMock, $quote]);
        
        $this->assertTrue($result);
    }
    
    /**
     * @test
     *
     */
    public function removeGiftCardCode_amastyGiftCard()
    {
        $codeId = 100;
        $quote = $this->getQuoteMock();
        $giftcardMock = $this->getMockBuilder('\Amasty\GiftCard\Model\Account')
            ->setMethods(['getCodeId'])
            ->disableOriginalConstructor()
            ->getMock();
        $giftcardMock->expects(static::once())->method('getCodeId')->willReturn($codeId);        
        $this->discountHelper->expects(static::once())->method('removeAmastyGiftCard')->with($codeId, $quote);            

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
        $exception = new \Exception('The GiftCard '.self::COUPON_CODE.' does not support removal');

        $this->bugsnag->expects(self::once())->method('notifyException')->with($exception);
        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_SERVICE,'The GiftCard '.self::COUPON_CODE.' does not support removal',422,$quote);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'removeGiftCardCode', [self::COUPON_CODE, null, $quote]);
        
        $this->assertFalse($result);
    }

}
