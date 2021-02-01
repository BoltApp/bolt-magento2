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

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Session;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Helper\Discount;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use \Magento\Framework\App\State as AppState;
use Bolt\Boltpay\Helper\Log as LogHelper;
use ReflectionException;
use Zend_Db_Statement_Exception;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Quote\Api\Data\CartExtension;
use Magento\Framework\Api\ExtensibleDataInterface as GiftCardQuote;
use Magento\SalesRule\Model\CouponFactory;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;

/**
 * Class DiscountTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper
 * @coversDefaultClass \Bolt\Boltpay\Helper\Discount
 */
class DiscountTest extends BoltTestCase
{
    /**
     * @var MockObject|Discount mocked instance of the class tested
     */
    private $currentMock;

    /**
     * @var MockObject|Quote mocked instace of the Magento quote
     */
    private $quote;

    /**
     * @var MockObject|Context mocked instance of the helper context
     */
    private $context;

    /**
     * @var MockObject|ResourceConnection mocked instance of the resource collection
     */
    private $resource;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Unirgy Giftcert repository
     */
    private $unirgyCertRepository;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Unirgy Giftcert helper
     */
    private $unirgyGiftCertHelper;

    /**
     * @var MockObject|CartRepositoryInterface mocked instance of the the Quote repository model
     */
    private $quoteRepository;

    /**
     * @var MockObject|ConfigHelper mocked instance of the Boltpay configuration helper
     */
    private $configHelper;

    /**
     * @var MockObject|Bugsnag mocked instance of the Boltpay Bugsnag helper
     */
    private $bugsnag;

    /**
     * @var MockObject|AppState mocked instance of the application state model
     */
    private $appState;

    /**
     * @var MockObject|Session mocked instance of the Boltpay Session helper
     */
    private $sessionHelper;

    /**
     * @var MockObject|LogHelper mocked instance of the Boltpay Log helper
     */
    private $logHelper;

    /**
     * @var MockObject|Mysql mocked instance of Mysql adapter
     */
    private $connectionMock;

    /**
     * @var CouponFactory
     */
    private $couponFactoryMock;

    /**
     * @var MockObject|RuleRepository
     */
    private $ruleRepositoryMock;

    /**
     * @var EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        $this->context = $this->createMock(Context::class);
        $this->resource = $this->createMock(ResourceConnection::class);
        $this->unirgyCertRepository = $this->createMock(ThirdPartyModuleFactory::class);
        $this->unirgyGiftCertHelper = $this->createMock(ThirdPartyModuleFactory::class);
        $this->quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->appState = $this->createMock(AppState::class);
        $this->eventsForThirdPartyModules = $this->createPartialMock(EventsForThirdPartyModules::class, ['runFilter','dispatchEvent']);
        $this->eventsForThirdPartyModules->method('runFilter')->will($this->returnArgument(1));
        $this->sessionHelper = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCheckoutSession', 'getGiftCardsData'])
            ->getMock();
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->quote = $this->createPartialMock(Quote::class, ['getMpGiftCards']);
        $this->connectionMock = $this->createPartialMock(
            Mysql::class,
            ['query', 'beginTransaction', 'commit', 'getConnection', 'getTableName', 'rollBack']
        );
        $this->couponFactoryMock = $this->createMock(CouponFactory::class);
        $this->ruleRepositoryMock = $this->createMock(RuleRepository::class);
        $this->eventsForThirdPartyModules->method('dispatchEvent')->willReturnSelf();
    }

    /**
     * Configures {@see \Bolt\Boltpay\Test\Unit\Helper\DiscountTest::$currentMock} to mock of the test class
     * based on provided parameters
     *
     * @param array $methods to be mocked
     * @param bool  $enableProxyingToOriginalMethods configuration flag
     */
    private function initCurrentMock($methods = null, $enableProxyingToOriginalMethods = false)
    {
        $builder = $this->getMockBuilder(Discount::class)
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->resource,
                    $this->unirgyCertRepository,
                    $this->unirgyGiftCertHelper,
                    $this->quoteRepository,
                    $this->configHelper,
                    $this->bugsnag,
                    $this->appState,
                    $this->sessionHelper,
                    $this->logHelper,
                    $this->couponFactoryMock,
                    $this->ruleRepositoryMock,
                    $this->eventsForThirdPartyModules
                ]
            )
            ->setMethods($methods);

        if ($enableProxyingToOriginalMethods) {
            $builder->enableProxyingToOriginalMethods();
        }

        $this->currentMock = $builder->getMock();
    }

    /**
     * @test
     * that constructor sets internal properties with provided arguments
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $instance = new Discount(
            $this->context,
            $this->resource,
            $this->unirgyCertRepository,
            $this->unirgyGiftCertHelper,
            $this->quoteRepository,
            $this->configHelper,
            $this->bugsnag,
            $this->appState,
            $this->sessionHelper,
            $this->logHelper,
            $this->couponFactoryMock,
            $this->ruleRepositoryMock,
            $this->eventsForThirdPartyModules
        );

        static::assertAttributeEquals($this->resource, 'resource', $instance);
        static::assertAttributeEquals($this->unirgyCertRepository, 'unirgyCertRepository', $instance);
        static::assertAttributeEquals($this->unirgyGiftCertHelper, 'unirgyGiftCertHelper', $instance);
        static::assertAttributeEquals($this->quoteRepository, 'quoteRepository', $instance);
        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
        static::assertAttributeEquals($this->bugsnag, 'bugsnag', $instance);
        static::assertAttributeEquals($this->appState, 'appState', $instance);
        static::assertAttributeEquals($this->sessionHelper, 'sessionHelper', $instance);
        static::assertAttributeEquals($this->logHelper, 'logHelper', $instance);
        static::assertAttributeEquals($this->couponFactoryMock, 'couponFactory', $instance);
        static::assertAttributeEquals($this->ruleRepositoryMock, 'ruleRepository', $instance);
        static::assertAttributeEquals($this->eventsForThirdPartyModules, 'eventsForThirdPartyModules', $instance);
    }

    /**
     * @test
     * that updateTotals collects shipping rates, quote totals
     * and forces quote to be saved by setting data changes flag to true
     *
     * @covers ::updateTotals
     *
     * @throws ReflectionException if updateTotals method doesn't exist
     */
    public function updateTotals_always_collectsTotalsAndSavesTheQuote()
    {
        $this->initCurrentMock();
        $quote = $this->createPartialMock(
            Quote::class,
            [
                'getShippingAddress',
                'setTotalsCollectedFlag',
                'collectTotals',
                'setDataChanges',
            ]
        );

        $quote->expects(static::once())->method('getShippingAddress')->willReturnSelf();
        $quote->expects(static::once())->method('setTotalsCollectedFlag')->with(false)->willReturnSelf();
        $quote->expects(static::once())->method('collectTotals')->willReturnSelf();
        $quote->expects(static::once())->method('setDataChanges')->with(true)->willReturnSelf();

        $this->quoteRepository->expects(static::once())->method('save')->with($quote)->willReturnSelf();
        TestHelper::invokeMethod($this->currentMock, 'updateTotals', [$quote]);
    }

    /**
     * @test
     * that getAmastyPayForEverything returns false only if both
     * 1. Amasty Gift Card config is available {@see \Bolt\Boltpay\Helper\Config::getAmastyGiftCardConfig}
     * 2. its 'payForEverything' property equals to false
     * otherwise returns true
     *
     * @covers ::getAmastyPayForEverything
     *
     * @dataProvider getAmastyPayForEverything_withVariousPayConfigsProvider
     *
     * @param bool $hasGiftCardConfig whether Amasty Gift Card config is present flag
     * @param bool $payForEverything Amasty Gift Card config value
     * @param bool $expectedResult of the method call
     */
    public function getAmastyPayForEverything_withVariousPayConfigs_returnsPayForEverythingState(
        $hasGiftCardConfig,
        $payForEverything,
        $expectedResult
    ) {
        $this->initCurrentMock();

        $amastyGiftCardConfig =  null;
        if ($hasGiftCardConfig) {
            $amastyGiftCardConfig = new \stdClass();
            $amastyGiftCardConfig->payForEverything = $payForEverything;
        }

        $this->configHelper->expects(static::once())
            ->method('getAmastyGiftCardConfig')
            ->willReturn($amastyGiftCardConfig);

        static::assertEquals($expectedResult, $this->currentMock->getAmastyPayForEverything());
    }

    /**
     * Data provider for {@see getAmastyPayForEverything_withVariousPayConfigs_returnsPayForEverythingState}
     *
     * @return array[] containing whether Amasty Gift Card config is present flag, payForEverything config value
     * and expectedResult of the method call
     */
    public function getAmastyPayForEverything_withVariousPayConfigsProvider()
    {
        return [
            ['hasGiftCardConfig' => false, 'payForEverything' => false, 'expectedResult' => true],
            ['hasGiftCardConfig' => true, 'payForEverything' => false, 'expectedResult' => false],
            ['hasGiftCardConfig' => true, 'payForEverything' => true, 'expectedResult' => true],
        ];
    }

    /**
     * @test
     * @covers ::loadUnirgyGiftCertData
     */
    public function loadUnirgyGiftCertData()
    {
        $storeId = 11;
        $couponCode = 'godieo1';
        $this->initCurrentMock();
        // mock for class \Unirgy\Giftcert\Model\GiftcertRepository that doesn't not exist
        $giftCertRepository = $this->getMockBuilder(\stdclass::class)
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();
        $giftCertMock = $this->getMockBuilder('\Unirgy\Giftcert\Model\Cert')
            ->setMethods(['getStoreId', 'getData'])
            ->disableOriginalConstructor()
            ->getMock();
        $giftCertMock->expects(self::once())->method('getStoreId')->willReturn($storeId);
        $giftCertMock->expects(self::once())->method('getData')->with('status')
            ->willReturn('A');

        $giftCertRepository->method('get')->with($couponCode)->willReturn($giftCertMock);
        $this->unirgyCertRepository->expects(static::once())->method('getInstance')->willReturn($giftCertRepository);

        $result = $this->currentMock->loadUnirgyGiftCertData($couponCode, $storeId);
        self::assertEquals(
            $giftCertMock,
            $result
        );
    }

    /**
     * @test
     * @covers ::loadUnirgyGiftCertData
     */
    public function loadUnirgyGiftCertData_noGiftCert()
    {
        $storeId = 11;
        $couponCode = 'godieo1';
        $this->initCurrentMock();
        // mock for class \Unirgy\Giftcert\Model\GiftcertRepository that doesn't not exist
        $giftCertRepository = $this->getMockBuilder(\stdclass::class)
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();

        $giftCertRepository->method('get')->with($couponCode)->willThrowException(new NoSuchEntityException());

        $this->unirgyCertRepository->expects(static::once())->method('getInstance')->willReturn($giftCertRepository);

        $result = $this->currentMock->loadUnirgyGiftCertData($couponCode, $storeId);
        self::assertNull($result);
    }

    /**
     * @test
     * that getUnirgyGiftCertBalanceByCode returns summed balances of all active Unirgy giftcards with positive balance
     * extracted from the provided giftcard code list
     *
     * @covers ::getUnirgyGiftCertBalanceByCode
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException from tested method
     */
    public function getUnirgyGiftCertBalanceByCode_withGiftcertCodeList_returnsSummedBalances()
    {
        $this->initCurrentMock();
        $giftCertCode = ' qwerty , asdfgh   , zxcvbn,mnbvcx, tyuop';

        $unirgyInstanceMock = $this->getMockBuilder('\Unirgy\Giftcert\Model\Cert')
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->unirgyCertRepository->expects(static::once())->method('getInstance')->willReturn($unirgyInstanceMock);
        $unirgyInstanceMock->expects(static::exactly(5))
            ->method('get')
            ->willReturnMap(
                [
                    ['qwerty', new DataObject(['status' => 'A', 'balance' => 234.56])],
                    ['asdfgh', new DataObject(['status' => 'I', 'balance' => 7654.32])],
                    ['tyuop', null],
                    ['zxcvbn', new DataObject(['status' => 'A', 'balance' => 5678.9])],
                    ['mnbvcx', new DataObject(['status' => 'A', 'balance' => -1234.456])],
                ]
            );

        $result = $this->currentMock->getUnirgyGiftCertBalanceByCode($giftCertCode);
        static::assertThat($result, static::isType('float'));
        static::assertEquals(5913.46, $result);
    }

    /**
     * @test
     * that loadCouponCodeData returns \Magento\SalesRule\Model\Coupon object
     *
     * @covers ::loadCouponCodeData
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function loadCouponCodeData_withCouponCode_returnCouponObject()
    {
        $this->initCurrentMock();
        $couponCode = 'testCouponCode';

        $couponMock = $this->getMockBuilder(\Magento\SalesRule\Model\Coupon::class)
            ->setMethods(
                [
                    'loadByCode'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $couponMock->expects(static::once())->method('loadByCode')->with($couponCode)->willReturnSelf();

        $this->couponFactoryMock->expects(static::once())->method('create')->willReturn($couponMock);


        static::assertEquals($couponMock, $this->currentMock->loadCouponCodeData($couponCode));
    }

    /**
     * @test
     * that convertToBoltDiscountType returns empty string if the coupon code is empty
     *
     * @covers ::convertToBoltDiscountType
     *
     */
    public function convertToBoltDiscountType_withEmptyCouponCode_returnEmptyString()
    {
        $this->initCurrentMock();
        static::assertEquals('fixed_amount', $this->currentMock->convertToBoltDiscountType(''));
    }

    /**
     * @test
     * that convertToBoltDiscountType returns empty string if the coupon code is empty
     *
     * @covers ::convertToBoltDiscountType
     *
     */
    public function convertToBoltDiscountType_loadCouponCodeDataThrowsException_notifyException()
    {
        $this->initCurrentMock(['loadCouponCodeData']);

        $exceptionMock = $this->createMock(\Exception::class);
        $this->currentMock->expects(static::once())->method('loadCouponCodeData')->willThrowException($exceptionMock);
        $this->bugsnag->expects(static::once())->method('notifyException')->with($exceptionMock)->willReturnSelf();
        $this->expectException(\Exception::class);

        $this->currentMock->convertToBoltDiscountType('testcoupon');
    }

    /**
     * @test
     * @covers ::convertToBoltDiscountType
     */
    public function convertToBoltDiscountType_withEmptyDiscountCode_returnsDefaultValue()
    {
        $this->initCurrentMock();
        $couponCode = "";
        static::assertEquals("fixed_amount", $this->currentMock->convertToBoltDiscountType($couponCode));
    }

    /**
     * @test
     * that convertToBoltDiscountType returns the Bolt discount type value
     *
     * @covers ::convertToBoltDiscountType
     *
     * @dataProvider convertToBoltDiscountType_withVariousTypesProvider
     *
     * @param string $types
     * @param bool $expectedResult of the method call
     */
    public function convertToBoltDiscountType_withVariousTypes_returnsBoltDiscountTypeValue(
        $types,
        $expectedResult
    ) {
        $couponCode = 'testcoupon';
        $this->initCurrentMock(['loadCouponCodeData']);

        $couponMock = $this->getMockBuilder(\Magento\SalesRule\Model\Coupon::class)
            ->setMethods(
                [
                    'getRuleId'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $couponMock->expects(static::once())->method('getRuleId')->willReturn(6);

        $this->currentMock->expects(static::once())->method('loadCouponCodeData')->with($couponCode)->willReturn($couponMock);

        $ruleMock = $this->getMockBuilder(Rule::class)
            ->setMethods(
                [
                    'getSimpleAction',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $ruleMock->expects(self::once())->method('getSimpleAction')->willReturn($types);

        $this->ruleRepositoryMock->expects(static::once())->method('getById')->with(6)->willReturn($ruleMock);

        static::assertEquals($expectedResult, $this->currentMock->convertToBoltDiscountType($couponCode));
    }

    /**
     * Data provider for {@see convertToBoltDiscountType_withVariousTypes_returnsBoltDiscountTypeValue}
     *
     * @return array[] containing Magento discount type and expected result of the method call
     */
    public function convertToBoltDiscountType_withVariousTypesProvider()
    {
        return [
            ['types' => 'by_fixed', 'expectedResult' => 'fixed_amount'],
            ['types' => 'cart_fixed', 'expectedResult' => 'fixed_amount'],
            ['types' => 'by_percent', 'expectedResult' => 'percentage'],
            ['types' => 'by_shipping', 'expectedResult' => 'shipping'],
            ['types' => 'none_list', 'expectedResult' => 'fixed_amount'],
            ['types' => '', 'expectedResult' => 'fixed_amount'],
        ];
    }

    /**
     * @test
     *
     * @covers ::setCouponCode
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function setCouponCode_savesProperly()
    {
        $this->initCurrentMock();
        
        $couponCode = 'testcoupon';
        
        $addressMock = $this->getMockBuilder(Quote\Address::class)
            ->setMethods(
                [
                    'setAppliedRuleIds',
                    'setCollectShippingRates'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $addressMock->expects(static::once())->method('setAppliedRuleIds')->with('')->willReturnSelf();
        $addressMock->expects(static::once())->method('setCollectShippingRates')->with(true)->willReturnSelf();

        $quote = $this->createPartialMock(
            Quote::class,
            [
                'getShippingAddress',
                'setTotalsCollectedFlag',
                'collectTotals',
                'setDataChanges',
                'setCouponCode',
                'isVirtual',
                'setAppliedRuleIds'
            ]
        );
        
        $quote->expects(static::once())->method('isVirtual')->willReturn(false);
        $quote->expects(static::exactly(2))->method('getShippingAddress')->willReturn($addressMock);
        $quote->expects(static::once())->method('setTotalsCollectedFlag')->with(false)->willReturnSelf();
        $quote->expects(static::once())->method('collectTotals')->willReturnSelf();
        $quote->expects(static::once())->method('setDataChanges')->with(true)->willReturnSelf();
        $quote->expects(static::once())->method('setCouponCode')->with($couponCode)->willReturnSelf();
        $quote->expects(static::once())->method('setAppliedRuleIds')->with('')->willReturnSelf();

        $this->quoteRepository->expects(static::once())->method('save')->with($quote)->willReturnSelf();

        static::assertNull($this->currentMock->setCouponCode($quote,$couponCode));
    }
}