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
use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use \PHPUnit\Framework\TestCase;
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
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\Data\CartExtension;
use Magento\Framework\Api\ExtensibleDataInterface as GiftCardQuote;

/**
 * Class DiscountTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper
 * @coversDefaultClass \Bolt\Boltpay\Helper\Discount
 */
class DiscountTest extends TestCase
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
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the legacy Amasty GiftCard model Account factory
     */
    private $amastyLegacyAccountFactory;
    
    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the legacy Amasty GiftCard model GiftCardManagement
     */
    private $amastyLegacyGiftCardManagement;
    
    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the legacy Amasty GiftCard model Quote factory
     */
    private $amastyLegacyQuoteFactory;
    
    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the legacy Amasty GiftCard model ResourceModel\Quote
     */
    private $amastyLegacyQuoteResource;
    
    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the legacy Amasty GiftCard model Repository\QuoteRepository
     */
    private $amastyLegacyQuoteRepository;
    
    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the legacy Amasty GiftCard model ResourceModel\Account\Collection
     */
    private $amastyLegacyAccountCollection;
    
    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the legacy Amasty GiftCardAccount model GiftCardAccount\Repository
     */
    private $amastyAccountFactory;
    
    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the legacy Amasty GiftCardAccount model GiftCardAccount\GiftCardAccountManagement
     */
    private $amastyGiftCardAccountManagement;
    
    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the legacy Amasty GiftCardAccount model GiftCardAccount\ResourceModel\Collection
     */
    private $amastyGiftCardAccountCollection;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Unirgy Giftcert repository
     */
    private $unirgyCertRepository;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Mirasvit Store Credit helper
     */
    private $mirasvitStoreCreditHelper;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Mirasvit Store Credit calculation helper
     */
    private $mirasvitStoreCreditCalculationHelper;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Mirasvit Store Credit calculation config model
     */
    private $mirasvitStoreCreditCalculationConfig;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Mirasvit Store Credit config model
     */
    private $mirasvitStoreCreditConfig;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Mirasvit Rewards purchase helper
     */
    private $mirasvitRewardsPurchaseHelper;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Mageplaza giftcard collection
     */
    private $mageplazaGiftCardCollection;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Mageplaza giftcard factory
     */
    private $mageplazaGiftCardFactory;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Amasty Rewards resource quote
     */
    private $amastyRewardsResourceQuote;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Amasty Rewards quote
     */
    private $amastyRewardsQuote;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Aheadworks Customer Store Credit management model
     */
    private $aheadworksCustomerStoreCreditManagement;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Bss StoreCredit helper
     */
    private $bssStoreCreditHelper;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Bss StoreCredit collection
     */
    private $bssStoreCreditCollection;

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
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->context = $this->createMock(Context::class);
        $this->resource = $this->createMock(ResourceConnection::class);        
        $this->amastyLegacyAccountFactory = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyLegacyGiftCardManagement = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyLegacyQuoteFactory = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyLegacyQuoteResource = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyLegacyQuoteRepository = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyLegacyAccountCollection = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyAccountFactory = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyGiftCardAccountManagement = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyGiftCardAccountCollection = $this->createMock(ThirdPartyModuleFactory::class);        
        $this->unirgyCertRepository = $this->createMock(ThirdPartyModuleFactory::class);
        $this->mirasvitStoreCreditHelper = $this->createMock(ThirdPartyModuleFactory::class);
        $this->mirasvitStoreCreditCalculationHelper = $this->createMock(ThirdPartyModuleFactory::class);
        $this->mirasvitStoreCreditCalculationConfig = $this->createMock(ThirdPartyModuleFactory::class);
        $this->mirasvitStoreCreditConfig = $this->createMock(ThirdPartyModuleFactory::class);
        $this->mirasvitRewardsPurchaseHelper = $this->createMock(ThirdPartyModuleFactory::class);
        $this->mageplazaGiftCardCollection = $this->createMock(ThirdPartyModuleFactory::class);
        $this->mageplazaGiftCardFactory = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyRewardsResourceQuote = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyRewardsQuote = $this->createMock(ThirdPartyModuleFactory::class);
        $this->aheadworksCustomerStoreCreditManagement = $this->createMock(ThirdPartyModuleFactory::class);
        $this->bssStoreCreditHelper = $this->createMock(ThirdPartyModuleFactory::class);
        $this->bssStoreCreditCollection = $this->createMock(ThirdPartyModuleFactory::class);
        $this->quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->appState = $this->createMock(AppState::class);
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
                    $this->amastyLegacyAccountFactory,
                    $this->amastyLegacyGiftCardManagement,
                    $this->amastyLegacyQuoteFactory,
                    $this->amastyLegacyQuoteResource,
                    $this->amastyLegacyQuoteRepository,
                    $this->amastyLegacyAccountCollection,
                    $this->amastyAccountFactory,
                    $this->amastyGiftCardAccountManagement,
                    $this->amastyGiftCardAccountCollection,
                    $this->unirgyCertRepository,
                    $this->mirasvitStoreCreditHelper,
                    $this->mirasvitStoreCreditCalculationHelper,
                    $this->mirasvitStoreCreditCalculationConfig,
                    $this->mirasvitStoreCreditConfig,
                    $this->mirasvitRewardsPurchaseHelper,
                    $this->mageplazaGiftCardCollection,
                    $this->mageplazaGiftCardFactory,
                    $this->amastyRewardsResourceQuote,
                    $this->amastyRewardsQuote,
                    $this->aheadworksCustomerStoreCreditManagement,
                    $this->bssStoreCreditHelper,
                    $this->bssStoreCreditCollection,
                    $this->quoteRepository,
                    $this->configHelper,
                    $this->bugsnag,
                    $this->appState,
                    $this->sessionHelper,
                    $this->logHelper,
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
            $this->amastyLegacyAccountFactory,
            $this->amastyLegacyGiftCardManagement,
            $this->amastyLegacyQuoteFactory,
            $this->amastyLegacyQuoteResource,
            $this->amastyLegacyQuoteRepository,
            $this->amastyLegacyAccountCollection,
            $this->amastyAccountFactory,
            $this->amastyGiftCardAccountManagement,
            $this->amastyGiftCardAccountCollection,
            $this->unirgyCertRepository,
            $this->mirasvitStoreCreditHelper,
            $this->mirasvitStoreCreditCalculationHelper,
            $this->mirasvitStoreCreditCalculationConfig,
            $this->mirasvitStoreCreditConfig,
            $this->mirasvitRewardsPurchaseHelper,
            $this->mageplazaGiftCardCollection,
            $this->mageplazaGiftCardFactory,
            $this->amastyRewardsResourceQuote,
            $this->amastyRewardsQuote,
            $this->aheadworksCustomerStoreCreditManagement,
            $this->bssStoreCreditHelper,
            $this->bssStoreCreditCollection,
            $this->quoteRepository,
            $this->configHelper,
            $this->bugsnag,
            $this->appState,
            $this->sessionHelper,
            $this->logHelper
        );

        static::assertAttributeEquals($this->resource, 'resource', $instance);
        static::assertAttributeEquals($this->amastyLegacyAccountFactory, 'amastyLegacyAccountFactory', $instance);
        static::assertAttributeEquals($this->amastyLegacyGiftCardManagement, 'amastyLegacyGiftCardManagement', $instance);
        static::assertAttributeEquals($this->amastyLegacyQuoteFactory, 'amastyLegacyQuoteFactory', $instance);
        static::assertAttributeEquals($this->amastyLegacyQuoteResource, 'amastyLegacyQuoteResource', $instance);
        static::assertAttributeEquals($this->amastyLegacyQuoteRepository, 'amastyLegacyQuoteRepository', $instance);
        static::assertAttributeEquals($this->amastyLegacyAccountCollection, 'amastyLegacyAccountCollection', $instance);
        static::assertAttributeEquals($this->amastyAccountFactory, 'amastyAccountFactory', $instance);
        static::assertAttributeEquals($this->amastyGiftCardAccountManagement, 'amastyGiftCardAccountManagement', $instance);
        static::assertAttributeEquals($this->amastyGiftCardAccountCollection, 'amastyGiftCardAccountCollection', $instance);        
        static::assertAttributeEquals($this->unirgyCertRepository, 'unirgyCertRepository', $instance);
        static::assertAttributeEquals($this->mirasvitStoreCreditHelper, 'mirasvitStoreCreditHelper', $instance);
        static::assertAttributeEquals(
            $this->mirasvitStoreCreditCalculationHelper,
            'mirasvitStoreCreditCalculationHelper',
            $instance
        );
        static::assertAttributeEquals(
            $this->mirasvitStoreCreditCalculationConfig,
            'mirasvitStoreCreditCalculationConfig',
            $instance
        );
        static::assertAttributeEquals($this->mirasvitStoreCreditConfig, 'mirasvitStoreCreditConfig', $instance);
        static::assertAttributeEquals($this->mirasvitRewardsPurchaseHelper, 'mirasvitRewardsPurchaseHelper', $instance);
        static::assertAttributeEquals($this->mageplazaGiftCardCollection, 'mageplazaGiftCardCollection', $instance);
        static::assertAttributeEquals($this->mageplazaGiftCardFactory, 'mageplazaGiftCardFactory', $instance);
        static::assertAttributeEquals($this->amastyRewardsResourceQuote, 'amastyRewardsResourceQuote', $instance);
        static::assertAttributeEquals($this->amastyRewardsQuote, 'amastyRewardsQuote', $instance);
        static::assertAttributeEquals(
            $this->aheadworksCustomerStoreCreditManagement,
            'aheadworksCustomerStoreCreditManagement',
            $instance
        );
        static::assertAttributeEquals($this->bssStoreCreditHelper, 'bssStoreCreditHelper', $instance);
        static::assertAttributeEquals($this->bssStoreCreditCollection, 'bssStoreCreditCollection', $instance);
        static::assertAttributeEquals($this->quoteRepository, 'quoteRepository', $instance);
        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
        static::assertAttributeEquals($this->bugsnag, 'bugsnag', $instance);
        static::assertAttributeEquals($this->appState, 'appState', $instance);
        static::assertAttributeEquals($this->sessionHelper, 'sessionHelper', $instance);
        static::assertAttributeEquals($this->logHelper, 'logHelper', $instance);
    }

    /**
     * @test
     * that isAmastyGiftCardAvailable returns the availability of the Amasty Gift Card module
     *
     * @covers ::isAmastyGiftCardAvailable
     *
     * @dataProvider isAmastyGiftCardAvailable_withVariousAmastyModuleAvailabilityProvider
     *
     * @param bool $amastyModuleAvailable stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isAvailable}
     * @param bool $amastyLegacyModuleAvailable stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isAvailable}
     * @param bool $expectedResult of the method call
     */
    public function isAmastyGiftCardAvailable_withVariousAmastyModuleAvailability_returnsAvailability(
        $amastyModuleAvailable,
        $amastyLegacyModuleAvailable,
        $expectedResult
    ) {
        $this->initCurrentMock();
        $this->amastyAccountFactory->expects(static::once())->method('isAvailable')->willReturn($amastyModuleAvailable);
        $this->amastyLegacyAccountFactory->expects(!$amastyModuleAvailable ? static::once() : static::never())
             ->method('isAvailable')->willReturn($amastyLegacyModuleAvailable);

        static::assertEquals($expectedResult, $this->currentMock->isAmastyGiftCardAvailable());
    }

    /**
     * Data provider for {@see isAmastyGiftCardAvailable_withVariousAmastyModuleAvailability_returnsAvailability}
     *
     * @return array[] containing Amasty Gift Card module availability flag and expected result of the method call
     */
    public function isAmastyGiftCardAvailable_withVariousAmastyModuleAvailabilityProvider()
    {
        return [
            ['amastyModuleAvailable' => false, 'amastyLegacyModuleAvailable' => true, 'expectedResult' => true],
            ['amastyModuleAvailable' => true, 'amastyLegacyModuleAvailable' => false, 'expectedResult' => true],
            ['amastyModuleAvailable' => false, 'amastyLegacyModuleAvailable' => false, 'expectedResult' => false],
        ];
    }
    
    /**
     * @test
     * that isAmastyGiftCardLegacyVersion returns if the Amasty Gift Card module is a legacy version or not
     *
     * @covers ::isAmastyGiftCardLegacyVersion
     *
     * @dataProvider isAmastyGiftCardLegacyVersion__withVariousAmastyModuleVersionProvider
     *
     * @param bool $amastyClassExistence stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isExists}
     * @param bool $expectedResult of the method call
     */
    public function isAmastyGiftCardLegacyVersion__withVariousAmastyModuleVersion_returnsProperResult(
        $amastyClassExistence,
        $expectedResult
    ) {
        $this->initCurrentMock();
        $this->amastyLegacyAccountFactory->expects(static::once())->method('isExists')->willReturn($amastyClassExistence);

        static::assertEquals($expectedResult, $this->currentMock->isAmastyGiftCardLegacyVersion());
    }
    
    /**
     * Data provider for {@see isAmastyGiftCardLegacyVersion__withVariousAmastyModuleVersion_returnsProperResult}
     *
     * @return array[] containing Amasty Gift Card class existence flag and expected result of the method call
     */
    public function isAmastyGiftCardLegacyVersion__withVariousAmastyModuleVersionProvider()
    {
        return [
            ['amastyClassExistence' => true, 'expectedResult' => true],
            ['amastyClassExistence' => false, 'expectedResult' => false],
        ];
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
     * that loadAmastyGiftCard returns null if the Amasty Gift Card module is unavailable
     *
     * @covers ::loadAmastyGiftCard
     */
    public function loadAmastyGiftCard_withAmastyGiftCardModuleUnavailable_returnsNull()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable']);
        $couponCode = 'testCouponCode';
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(false);

        static::assertNull($this->currentMock->loadAmastyGiftCard($couponCode, 1111));
    }

    /**
     * @test
     * that loadAmastyGiftCard returns Amasty Gift Card GiftCardAccount\Repository object
     * if one is available for the provided giftcard code and website id
     *
     * @covers ::loadAmastyGiftCard
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function loadAmastyGiftCard_withAmastyGiftCardAvailableForWebsite_returnsAmastyGiftCardAccountModel()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion', 'create']);
        $couponCode = 'testCouponCode';
        $websiteId = 1111;
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(false);

        $accountModelMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)->setMethods(
            [
                'getInstance',
                'create',
                'getByCode',
                'getId',
                'getWebsiteId',
            ]
        )->disableOriginalConstructor()->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyAccountFactory', $accountModelMock);

        $accountModelMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $accountModelMock->expects(static::once())->method('create')->willReturnSelf();
        $accountModelMock->expects(static::once())->method('getByCode')->with($couponCode)->willReturnSelf();
        $accountModelMock->expects(static::once())->method('getId')->with()->willReturnSelf();
        $accountModelMock->expects(static::exactly(2))->method('getWebsiteId')->with()
            ->willReturnOnConsecutiveCalls($websiteId, $websiteId);

        static::assertEquals($accountModelMock, $this->currentMock->loadAmastyGiftCard($couponCode, $websiteId));
    }
    
    /**
     * @test
     * that loadAmastyGiftCard returns Amasty Gift Card legacy Account object
     * if one is available for the provided giftcard code and website id
     *
     * @covers ::loadAmastyGiftCard
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function loadAmastyGiftCard_legacyVersion_withAmastyGiftCardAvailableForWebsite_returnsAmastyGiftCardAccountModel()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion', 'create']);
        $couponCode = 'testCouponCode';
        $websiteId = 1111;
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(true);

        $accountModelMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)->setMethods(
            [
                'getInstance',
                'create',
                'loadByCode',
                'getId',
                'getWebsiteId',
            ]
        )->disableOriginalConstructor()->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyLegacyAccountFactory', $accountModelMock);

        $accountModelMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $accountModelMock->expects(static::once())->method('create')->willReturnSelf();
        $accountModelMock->expects(static::once())->method('loadByCode')->with($couponCode)->willReturnSelf();
        $accountModelMock->expects(static::once())->method('getId')->with()->willReturnSelf();
        $accountModelMock->expects(static::exactly(2))->method('getWebsiteId')->with()
            ->willReturnOnConsecutiveCalls($websiteId, $websiteId);

        static::assertEquals($accountModelMock, $this->currentMock->loadAmastyGiftCard($couponCode, $websiteId));
    }

    /**
     * @test
     * that loadAmastyGiftCard returns null if an exception occurs during gift card loading
     *
     * @covers ::loadAmastyGiftCard
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function loadAmastyGiftCard_whenUnableToLoadCardDueToException_returnsNull()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion', 'create']);
        $couponCode = 'testCouponCode';
        $websiteId = 1111;
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(false);

        $accountModelMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)->setMethods(
            [
                'getInstance',
                'create',
                'getByCode',
                'getId',
                'getWebsiteId',
            ]
        )->disableOriginalConstructor()->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyAccountFactory', $accountModelMock);

        $exceptionMock = $this->createMock(\Exception::class);
        $accountModelMock->expects(static::once())->method('getInstance')->willThrowException($exceptionMock);

        static::assertNull($this->currentMock->loadAmastyGiftCard($couponCode, $websiteId));
    }
    
    /**
     * @test
     * that loadAmastyGiftCard returns null if an exception occurs during gift card loading
     *
     * @covers ::loadAmastyGiftCard
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function loadAmastyGiftCard_legacyVersion_whenUnableToLoadCardDueToException_returnsNull()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion', 'create']);
        $couponCode = 'testCouponCode';
        $websiteId = 1111;
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(true);

        $accountModelMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)->setMethods(
            [
                'getInstance',
                'create',
                'loadByCode',
                'getId',
                'getWebsiteId',
            ]
        )->disableOriginalConstructor()->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyLegacyAccountFactory', $accountModelMock);

        $exceptionMock = $this->createMock(\Exception::class);
        $accountModelMock->expects(static::once())->method('getInstance')->willThrowException($exceptionMock);

        static::assertNull($this->currentMock->loadAmastyGiftCard($couponCode, $websiteId));
    }
    
    /**
     * @test
     * that applyAmastyGiftCard throws a localized exception if one of the following is true:
     * 1. Gift Card code is not valid
     * 2. Gift Card cannot be applied to quote
     * 3. Gift Card account is already in the quote
     * 4. Maximum discount is already reached
     *
     * @covers ::applyAmastyGiftCard
     *
     * @dataProvider applyAmastyGiftCard_legacyVersion_withVariousCouponPropertiesProvider
     *
     * @param bool   $isValid stubbed result of the giftcard code validation call
     * {@see \Amasty\GiftCard\Model\GiftCardManagement::validateCode}
     * @param int    $code Amasty Gift Card code
     * @param bool   $applyForQuote stubbed flag result of the call that determines if the provided account model
     *                              can be applied to current quote
     * @param int    $subtotal amount from the Quote Gift Card
     * @param int    $quoteGiftCodeId value of the Quote Gift Code Id
     * @param int    $accountModelCodeId value of the Account Model Code Id
     * @param int    $quoteGiftAmount value of the Quote Gift Amount
     * @param string $expectedExceptionMessage the thrown exception message
     *
     * @throws ReflectionException if unable to set internal mock properties
     * @throws LocalizedException from tested method
     */
    public function applyAmastyGiftCard_legacyVersion_withVariousCouponProperties_throwsException(
        $isValid,
        $code,
        $applyForQuote,
        $subtotal,
        $quoteGiftCodeId,
        $accountModelCodeId,
        $quoteGiftAmount,
        $expectedExceptionMessage
    ) {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion', 'getAmastyPayForEverything']);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(true);
        
        $giftTestAmount = 1322;
        $quoteId = 2;

        $accountModel = $this->getMockBuilder('Amasty\GiftCard\Model\Account')
            ->setMethods(['getCurrentValue', 'canApplyCardForQuote', 'getCodeId', 'getId'])
            ->disableOriginalConstructor()
            ->getMock();

        $accountModel->expects(static::once())->method('getCurrentValue')->willReturn($giftTestAmount);

        $quote = $this->createPartialMock(
            Quote::class,
            [
                'getId',
                'getShippingAddress',
                'setTotalsCollectedFlag',
                'collectTotals',
                'setDataChanges',
                'setCollectShippingRates'
            ]
        );
        $quote->expects(static::once())->method('getId')->willReturn($quoteId);

        $amastyGiftCardMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'validateCode'])
            ->disableOriginalConstructor()
            ->getMock();
                                                     
        TestHelper::setProperty($this->currentMock, 'amastyLegacyGiftCardManagement', $amastyGiftCardMock);

        $amastyGiftCardMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $amastyGiftCardMock->expects(static::once())->method('validateCode')->with($quote, $code)->willReturn($isValid);

        $accountModel->expects($isValid ? static::once() : static::never())
            ->method('canApplyCardForQuote')
            ->with($quote)
            ->willReturn($applyForQuote);

        $quoteGiftCard = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(
                [
                    'getInstance',
                    'create',
                    'getSubtotal',
                    'getCodeId',
                    'getGiftAmount',
                    'unsetData',
                    'getIdFieldName',
                    'setQuoteId',
                    'setCodeId',
                    'setAccountId',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyLegacyQuoteFactory', $quoteGiftCard);

        $quoteGiftCard->expects($applyForQuote ? static::once() : static::never())
            ->method('getInstance')
            ->willReturnSelf();
        $quoteGiftCard->expects($applyForQuote ? static::once() : static::never())->method('create')->willReturnSelf();

        $amastyResourceMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'load'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyLegacyQuoteResource', $amastyResourceMock);

        $amastyResourceMock->expects($applyForQuote ? static::once() : static::never())
            ->method('getInstance')
            ->willReturnSelf();
        $amastyResourceMock->expects($applyForQuote ? static::once() : static::never())
            ->method('load')
            ->with($quoteGiftCard, $quoteId, 'quote_id')
            ->willReturnSelf();

        $quoteGiftCard->expects($applyForQuote ? static::once() : static::never())
            ->method('getSubtotal')
            ->with($quote)
            ->willReturn($subtotal);

        $quoteGiftCard->expects($applyForQuote ? static::exactly(2) : static::never())
            ->method('getCodeId')
            ->willReturnOnConsecutiveCalls($quoteGiftCodeId, $quoteGiftCodeId);

        $accountModel->expects($applyForQuote ? static::once() : static::never())
            ->method('getCodeId')
            ->willReturn($accountModelCodeId);

        $quoteGiftCard
            ->expects(
                $applyForQuote && ($quoteGiftCodeId !== $accountModelCodeId) ? static::exactly(2) : static::never()
            )
            ->method('getGiftAmount')
            ->willReturnOnConsecutiveCalls($quoteGiftAmount, $quoteGiftAmount);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $this->currentMock->applyAmastyGiftCard($code, $accountModel, $quote);
    }

    /**
     * Data provider for {@see applyAmastyGiftCard_legacyVersion_withVariousCouponProperties_throwsException}
     *
     * @return array[] containing
     * 1. stubbed flag result of the giftcard code validation call
     * 2. Amasty Gift Card code
     * 3. flag value that shows if the Amasty Gift Card Model is applicable for a Quote
     * 4. subtotal amount from the Quote Gift Card
     * 5. value of the Quote Gift Code id
     * 6. value of the Account Model Code id
     * 7. value of the Quote Gift Amount
     * 8. the expected exception message
     */
    public function applyAmastyGiftCard_legacyVersion_withVariousCouponPropertiesProvider()
    {
        return [
            [
                "isValid"            => false,
                "code"               => 2323,
                "applyForQuote"      => false,
                "subtotal"           => 100,
                "quoteGiftCodeId"    => 10,
                "accountModelCodeId" => 10,
                "quoteGiftAmount"    => 10,
                "exceptionMessage"   => "Coupon with specified code \"2323\" is not valid."
            ],
            [
                "isValid"            => true,
                "code"               => 5362,
                "applyForQuote"      => true,
                "subtotal"           => 200,
                "quoteGiftCodeId"    => 20,
                "accountModelCodeId" => 20,
                "quoteGiftAmount"    => 1000,
                "exceptionMessage"   => "This gift card account is already in the quote."
            ],
            [
                "isValid"            => true,
                "code"               => 3878,
                "applyForQuote"      => true,
                "subtotal"           => 20,
                "quoteGiftCodeId"    => 20,
                "accountModelCodeId" => 30,
                "quoteGiftAmount"    => 20,
                "exceptionMessage"   => "Gift card can't be applied. Maximum discount reached."
            ],
            [
                "isValid"            => true,
                "code"               => 9899,
                "applyForQuote"      => false,
                "subtotal"           => 400,
                "quoteGiftCodeId"    => 10,
                "accountModelCodeId" => 10,
                "quoteGiftAmount"    => 10,
                "exceptionMessage"   => "Gift card can't be applied."
            ],
        ];
    }
    
    /**
     * @test
     * that applyAmastyGiftCard returns pay items everything or pay for items only after meeting the following conditions:
     * 1. Amasty Gift Card code is valid
     * 2. provided Amasty Gift Card Account model can apply the provided card to quote
     * 3. gift card is not already applied to the quote
     * 4. maximum discount is not already reached
     *
     * @covers ::applyAmastyGiftCard
     *
     * @dataProvider applyAmastyGiftCard_withVariousPayForItemsProvider
     *
     * @param bool $amastyPayForEverything stubbed result of {@see \Bolt\Boltpay\Helper\Discount::getAmastyPayForEverything}
     * @param int  $giftAmount current gift card balance before applying it to the quote
     * @param int  $totalsAmastyGiftCard value of the Amasty Gift Card quote total
     * @param int  $expectedResult of the method call
     *
     * @throws LocalizedException from tested method
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function applyAmastyGiftCard_withVariousPayForItems_returnsPayItems(
        $amastyPayForEverything,
        $giftAmount,
        $totalsAmastyGiftCard,
        $expectedResult
    ) {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion', 'getAmastyPayForEverything']);
        $code = 321312;
        $quoteId = 2;
        
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(false);

        $accountModel = $this->getMockBuilder('Amasty\GiftCardAccount\Model\GiftCardAccount\RepositoryFactory')
            ->setMethods(['getCurrentValue'])
            ->disableOriginalConstructor()
            ->getMock();

        $accountModel->expects(static::once())->method('getCurrentValue')->willReturn($giftAmount);

        $quote = $this->createPartialMock(
            Quote::class,
            [
                'getId',
                'getTotals'
            ]
        );
        $quote->expects(static::once())->method('getId')->willReturn($quoteId);

        $amastyGiftCardMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'applyGiftCardToCart'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyGiftCardAccountManagement', $amastyGiftCardMock);

        $amastyGiftCardMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $amastyGiftCardMock->expects(static::once())->method('applyGiftCardToCart')->with($quoteId, $code)->willReturn($code);

        $this->currentMock->expects(static::once())
            ->method('getAmastyPayForEverything')
            ->willReturn($amastyPayForEverything);

        $quote->expects(!$amastyPayForEverything ? static::once() : static::never())
            ->method('getTotals')
            ->willReturn([Discount::AMASTY_GIFTCARD => new DataObject(['value' => $totalsAmastyGiftCard])]);
            
        $this->quoteRepository->expects(!$amastyPayForEverything ? static::once() : static::never())
            ->method('getActive')->with($quoteId)->willReturn($quote);

        static::assertEquals($expectedResult, $this->currentMock->applyAmastyGiftCard($code, $accountModel, $quote));
    }

    /**
     * @test
     * that applyAmastyGiftCard returns pay items everything or pay for items only after meeting the following conditions:
     * 1. Amasty Gift Card code is valid
     * 2. provided Amasty Gift Card Account model can apply the provided card to quote
     * 3. gift card is not already applied to the quote
     * 4. maximum discount is not already reached
     *
     * @covers ::applyAmastyGiftCard
     *
     * @dataProvider applyAmastyGiftCard_withVariousPayForItemsProvider
     *
     * @param bool $amastyPayForEverything stubbed result of {@see \Bolt\Boltpay\Helper\Discount::getAmastyPayForEverything}
     * @param int  $giftAmount current gift card balance before applying it to the quote
     * @param int  $totalsAmastyGiftCard value of the Amasty Gift Card quote total
     * @param int  $expectedResult of the method call
     *
     * @throws LocalizedException from tested method
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function applyAmastyGiftCard_legacyVersion_withVariousPayForItems_returnsPayItems(
        $amastyPayForEverything,
        $giftAmount,
        $totalsAmastyGiftCard,
        $expectedResult
    ) {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion', 'getAmastyPayForEverything']);
        $code = 321312;
        $quoteId = 2;
        
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(true);

        $accountModel = $this->getMockBuilder('Amasty\GiftCard\Model\Account')
            ->setMethods(['getCurrentValue', 'canApplyCardForQuote', 'getCodeId', 'getId'])
            ->disableOriginalConstructor()
            ->getMock();

        $accountModel->expects(static::once())->method('getCurrentValue')->willReturn($giftAmount);

        $quote = $this->createPartialMock(
            Quote::class,
            [
                'getId',
                'getShippingAddress',
                'setTotalsCollectedFlag',
                'collectTotals',
                'setDataChanges',
                'setCollectShippingRates',
                'getTotals',
                'getValue',
            ]
        );
        $quote->expects(static::once())->method('getId')->willReturn($quoteId);

        $amastyGiftCardMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'validateCode'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyLegacyGiftCardManagement', $amastyGiftCardMock);

        $amastyGiftCardMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $amastyGiftCardMock->expects(static::once())->method('validateCode')->with($quote, $code)->willReturn(true);

        $accountModel->expects(static::once())->method('canApplyCardForQuote')->with($quote)->willReturn(true);

        $quoteGiftCard = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(
                [
                    'getInstance',
                    'create',
                    'getSubtotal',
                    'getCodeId',
                    'getGiftAmount',
                    'unsetData',
                    'getIdFieldName',
                    'setQuoteId',
                    'setCodeId',
                    'setAccountId',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyLegacyQuoteFactory', $quoteGiftCard);

        $quoteGiftCard->expects(static::once())->method('getInstance')->willReturnSelf();
        $quoteGiftCard->expects(static::once())->method('create')->willReturnSelf();

        $amastyResourceMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'load'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyLegacyQuoteResource', $amastyResourceMock);

        $amastyResourceMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $amastyResourceMock->expects(static::once())
            ->method('load')
            ->with($quoteGiftCard, $quoteId, 'quote_id')
            ->willReturnSelf();

        $subtotal = 22;
        $quoteCodeId = 15;
        $accountModelCodeId = 55;
        $giftAmount = 500;
        $quoteGiftCard->expects(static::once())->method('getSubtotal')->with($quote)->willReturn($subtotal);
        $quoteGiftCard->expects(static::exactly(2))
            ->method('getCodeId')
            ->willReturnOnConsecutiveCalls($quoteCodeId, $quoteCodeId);

        $accountModel->expects(static::exactly(2))
            ->method('getCodeId')
            ->willReturnOnConsecutiveCalls($accountModelCodeId, $accountModelCodeId);

        $quoteGiftCard->expects(static::exactly(2))
            ->method('getGiftAmount')
            ->willReturnOnConsecutiveCalls($giftAmount, $giftAmount);

        $idFieldName = 'field';
        $quoteGiftCard->expects(static::once())->method('getIdFieldName')->with()->willReturn($idFieldName);
        $quoteGiftCard->expects(static::once())->method('unsetData')->with($idFieldName)->willReturnSelf();
        $quoteGiftCard->expects(static::once())->method('setQuoteId')->with($quoteId)->willReturnSelf();
        $quoteGiftCard->expects(static::once())->method('setCodeId')->with($accountModelCodeId)->willReturnSelf();

        $accountModelId = 58;
        $accountModel->expects(static::once())->method('getId')->willReturn($accountModelId);

        $quoteGiftCard->expects(static::once())->method('setAccountId')->with($accountModelId)->willReturnSelf();

        $amastyQuoteMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'save'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyLegacyQuoteRepository', $amastyQuoteMock);

        $amastyQuoteMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $amastyQuoteMock->expects(static::once())->method('save')->with($quoteGiftCard)->willReturnSelf();

        $quote->expects(static::once())->method('getShippingAddress')->willReturnSelf();
        $quote->expects(static::once())->method('setCollectShippingRates')->with(true)->willReturnSelf();
        $quote->expects(static::once())->method('setTotalsCollectedFlag')->with(false)->willReturnSelf();
        $quote->expects(static::once())->method('collectTotals')->willReturnSelf();
        $quote->expects(static::once())->method('setDataChanges')->with(true)->willReturnSelf();

        $this->currentMock->expects(static::once())
            ->method('getAmastyPayForEverything')
            ->willReturn($amastyPayForEverything);

        $quote->expects(!$amastyPayForEverything ? static::once() : static::never())
            ->method('getTotals')
            ->willReturn([Discount::AMASTY_GIFTCARD => new DataObject(['value' => $totalsAmastyGiftCard])]);

        static::assertEquals($expectedResult, $this->currentMock->applyAmastyGiftCard($code, $accountModel, $quote));
    }

    /**
     * Data provider for {@see applyAmastyGiftCard_withVariousPayForItems_returnsPayItems}
     *
     * @return array[] containing
     * 1. stubbed result of {@see \Bolt\Boltpay\Helper\Discount::getAmastyPayForEverything}
     * 2. current gift card balance before applying it to the quote
     * 3. value of the Amasty Gift Card quote total
     * 4. expected result of the method call
     */
    public function applyAmastyGiftCard_withVariousPayForItemsProvider()
    {
        return [
            [
                'amastyPayForEverything' => true,
                'giftAmount'             => 100,
                'totalsAmastyGiftCard'   => 500,
                'expectedResult'         => 100,
            ],
            [
                'amastyPayForEverything' => false,
                'giftAmount'             => 300,
                'totalsAmastyGiftCard'   => 200,
                'expectedResult'         => 200,
            ],
        ];
    }

    /**
     * @test
     * that cloneAmastyGiftCards returns null if the Amasty Gift Card module is unavailable
     *
     * @covers ::cloneAmastyGiftCards
     */
    public function cloneAmastyGiftCards_ifAmastyGiftCardIsUnavailable_returnsNull()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable']);
        $sourceQuoteId = 232;
        $destinationQuoteId = 1111;
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(false);

        static::assertNull($this->currentMock->cloneAmastyGiftCards($sourceQuoteId, $destinationQuoteId));
    }

    /**
     * @test
     * that cloneAmastyGiftCards:
     * 1. connects to the database
     * 2. clears gift cart codes previously applied to the immutable quote
     * 3. copies all gift cart codes applied to the parent quote onto the immutable quote
     * 4. commits the queries
     *
     * @covers ::cloneAmastyGiftCards
     */
    public function cloneAmastyGiftCards_ifAmastyGiftCardIsAvailable_commitsTheQuery()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);        
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(false);
        $sourceQuoteId = 232;
        $destinationQuoteId = 1111;

        $this->resource->method('getConnection')->willReturn($this->connectionMock);
        $this->connectionMock->expects(static::once())->method('beginTransaction')->willReturnSelf();
        $testTableName = 'amasty_giftcard_quote';
        $this->resource->method('getTableName')->with('amasty_giftcard_quote')->willReturn($testTableName);

        $deleteSql = "DELETE FROM {$testTableName} WHERE quote_id = :destination_quote_id";

        $sqlInsert = "INSERT INTO {$testTableName} (quote_id, gift_cards, gift_amount, base_gift_amount, gift_amount_used, base_gift_amount_used) 
                        SELECT :destination_quote_id, gift_cards, gift_amount, base_gift_amount, gift_amount_used, base_gift_amount_used
                        FROM {$testTableName} WHERE quote_id = :source_quote_id";

        $this->connectionMock->expects(static::exactly(2))->method('query')->withConsecutive(
            [$deleteSql, ['destination_quote_id' => $destinationQuoteId]],
            [$sqlInsert, ['destination_quote_id' => $destinationQuoteId, 'source_quote_id' => $sourceQuoteId]]
        )->willReturnSelf();

        $this->connectionMock->expects(static::once())->method('commit')->willReturnSelf();

        $this->currentMock->cloneAmastyGiftCards($sourceQuoteId, $destinationQuoteId);
    }
    
    /**
     * @test
     * that cloneAmastyGiftCards:
     * 1. connects to the database
     * 2. clears gift cart codes previously applied to the immutable quote
     * 3. copies all gift cart codes applied to the parent quote onto the immutable quote
     * 4. commits the queries
     *
     * @covers ::cloneAmastyGiftCards
     */
    public function cloneAmastyGiftCards_legacyVersion_ifAmastyGiftCardIsAvailable_commitsTheQuery()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);        
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(true);
        $sourceQuoteId = 232;
        $destinationQuoteId = 1111;

        $this->resource->method('getConnection')->willReturn($this->connectionMock);
        $this->connectionMock->expects(static::once())->method('beginTransaction')->willReturnSelf();
        $testTableName = 'amasty_amgiftcard_quotes';
        $this->resource->method('getTableName')->with('amasty_amgiftcard_quote')->willReturn($testTableName);

        $deleteSql = "DELETE FROM {$testTableName} WHERE quote_id = :destination_quote_id";

        $sqlInsert = "INSERT INTO {$testTableName} (quote_id, code_id, account_id, base_gift_amount, code) 
                        SELECT :destination_quote_id, code_id, account_id, base_gift_amount, code
                        FROM {$testTableName} WHERE quote_id = :source_quote_id";

        $this->connectionMock->expects(static::exactly(2))->method('query')->withConsecutive(
            [$deleteSql, ['destination_quote_id' => $destinationQuoteId]],
            [$sqlInsert, ['destination_quote_id' => $destinationQuoteId, 'source_quote_id' => $sourceQuoteId]]
        )->willReturnSelf();

        $this->connectionMock->expects(static::once())->method('commit')->willReturnSelf();

        $this->currentMock->cloneAmastyGiftCards($sourceQuoteId, $destinationQuoteId);
    }
    
    /**
     * @test
     * that cloneAmastyGiftCards will rollback the SQL query and notify the Bugsnag if
     * the SQL query execution throws an exception
     *
     * @covers ::cloneAmastyGiftCards
     */
    public function cloneAmastyGiftCards_ifQueryExceptionIsThrown_notifiesBugSnag()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);
        $sourceQuoteId = 232;
        $destinationQuoteId = 1111;
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(false);
        $this->resource->method('getConnection')->willReturn($this->connectionMock);
        $this->connectionMock->expects(static::once())->method('beginTransaction')->willReturnSelf();
        $testTableName = 'amasty_giftcard_quote';
        $this->resource->method('getTableName')->with('amasty_giftcard_quote')->willReturn($testTableName);

        $deleteSql = "DELETE FROM {$testTableName} WHERE quote_id = :destination_quote_id";

        $exception = $this->createMock(Zend_Db_Statement_Exception::class);

        $this->connectionMock->expects(static::once())
            ->method('query')
            ->with($deleteSql, ['destination_quote_id' => $destinationQuoteId])
            ->willThrowException($exception);

        $this->connectionMock->expects(static::once())->method('rollBack')->willReturnSelf();

        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception)->willReturnSelf();

        $this->currentMock->cloneAmastyGiftCards($sourceQuoteId, $destinationQuoteId);
    }

    /**
     * @test
     * that cloneAmastyGiftCards will rollback the SQL query and notify the Bugsnag if
     * the SQL query execution throws an exception
     *
     * @covers ::cloneAmastyGiftCards
     */
    public function cloneAmastyGiftCards_legacyVersion_ifQueryExceptionIsThrown_notifiesBugSnag()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);
        $sourceQuoteId = 232;
        $destinationQuoteId = 1111;
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(true);
        $this->resource->method('getConnection')->willReturn($this->connectionMock);
        $this->connectionMock->expects(static::once())->method('beginTransaction')->willReturnSelf();
        $testTableName = 'amasty_amgiftcard_quote';
        $this->resource->method('getTableName')->with('amasty_amgiftcard_quote')->willReturn($testTableName);

        $deleteSql = "DELETE FROM {$testTableName} WHERE quote_id = :destination_quote_id";

        $exception = $this->createMock(Zend_Db_Statement_Exception::class);

        $this->connectionMock->expects(static::once())
            ->method('query')
            ->with($deleteSql, ['destination_quote_id' => $destinationQuoteId])
            ->willThrowException($exception);

        $this->connectionMock->expects(static::once())->method('rollBack')->willReturnSelf();

        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception)->willReturnSelf();

        $this->currentMock->cloneAmastyGiftCards($sourceQuoteId, $destinationQuoteId);
    }

    /**
     * @test
     * that clearAmastyGiftCard returns null if the Amasty Gift Card module is not available
     *
     * @covers ::clearAmastyGiftCard
     */
    public function clearAmastyGiftCard_ifAmastyGiftCardIsUnavailable_returnsNull()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable']);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(false);

        static::assertNull($this->currentMock->clearAmastyGiftCard($this->quote));
    }

    /**
     * @test
     * that clearAmastyGiftCard removes Amasty Gift Card from the provided quote using a custom SQL query
     *
     * @covers ::clearAmastyGiftCard
     */
    public function clearAmastyGiftCard_ifAmastyGiftCardIsAvailable_removesCardInfo()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(false);
        $this->resource->method('getConnection')->willReturn($this->connectionMock);
        $testTableName = 'amasty_giftcard_quote';
        $this->resource->method('getTableName')->with('amasty_giftcard_quote')->willReturn($testTableName);

        $quoteId = 11;
        $quoteMock = $this->createPartialMock(Quote::class, ['getId']);
        $quoteMock->method('getId')->willReturn($quoteId);

        $sql = "DELETE FROM {$testTableName} WHERE quote_id = :quote_id";
        $bind = [
            'quote_id' => $quoteId
        ];

        $this->connectionMock->expects(static::once())->method('query')->with($sql, $bind)->willReturnSelf();
        $this->currentMock->clearAmastyGiftCard($quoteMock);
    }
    
    /**
     * @test
     * that clearAmastyGiftCard removes Amasty Gift Card from the provided quote using a custom SQL query
     *
     * @covers ::clearAmastyGiftCard
     */
    public function clearAmastyGiftCard_legacyVersion_ifAmastyGiftCardIsAvailable_removesCardInfo()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(true);
        $this->resource->method('getConnection')->willReturn($this->connectionMock);
        $testTableName = 'amasty_amgiftcard_quote';
        $this->resource->method('getTableName')->with('amasty_amgiftcard_quote')->willReturn($testTableName);

        $quoteId = 11;
        $quoteMock = $this->createPartialMock(Quote::class, ['getId']);
        $quoteMock->method('getId')->willReturn($quoteId);

        $sql = "DELETE FROM {$testTableName} WHERE quote_id = :quote_id";
        $bind = [
            'quote_id' => $quoteId
        ];

        $this->connectionMock->expects(static::once())->method('query')->with($sql, $bind)->willReturnSelf();
        $this->currentMock->clearAmastyGiftCard($quoteMock);
    }

    /**
     * @test
     * that clearAmastyGiftCard only notifies the Bugsnag if the SQL query throws an exception
     *
     * @covers ::clearAmastyGiftCard
     */
    public function clearAmastyGiftCard_ifQueryThrowsException_notifiesBugSnag()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(false);
        $this->resource->method('getConnection')->willReturn($this->connectionMock);
        $testTableName = 'amasty_giftcard_quote';
        $this->resource->method('getTableName')->with('amasty_giftcard_quote')->willReturn($testTableName);

        $quoteId = 11;
        $quoteMock = $this->createPartialMock(Quote::class, ['getId']);
        $quoteMock->method('getId')->willReturn($quoteId);

        $sql = "DELETE FROM {$testTableName} WHERE quote_id = :quote_id";
        $bind = [
            'quote_id' => $quoteId
        ];
        $exception = $this->createMock(Zend_Db_Statement_Exception::class);
        $this->connectionMock->expects(static::once())->method('query')->with($sql, $bind)
            ->willThrowException($exception);

        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception)->willReturnSelf();
        $this->currentMock->clearAmastyGiftCard($quoteMock);
    }
    
    /**
     * @test
     * that clearAmastyGiftCard only notifies the Bugsnag if the SQL query throws an exception
     *
     * @covers ::clearAmastyGiftCard
     */
    public function clearAmastyGiftCard_legacyVersion_ifQueryThrowsException_notifiesBugSnag()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(true);
        $this->resource->method('getConnection')->willReturn($this->connectionMock);
        $testTableName = 'amasty_amgiftcard_quote';
        $this->resource->method('getTableName')->with('amasty_amgiftcard_quote')->willReturn($testTableName);

        $quoteId = 11;
        $quoteMock = $this->createPartialMock(Quote::class, ['getId']);
        $quoteMock->method('getId')->willReturn($quoteId);

        $sql = "DELETE FROM {$testTableName} WHERE quote_id = :quote_id";
        $bind = [
            'quote_id' => $quoteId
        ];
        $exception = $this->createMock(Zend_Db_Statement_Exception::class);
        $this->connectionMock->expects(static::once())->method('query')->with($sql, $bind)
            ->willThrowException($exception);

        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception)->willReturnSelf();
        $this->currentMock->clearAmastyGiftCard($quoteMock);
    }

    /**
     * @test
     * that deleteRedundantAmastyGiftCards returns null if the Amasty Gift Card module is not available
     *
     * @covers ::deleteRedundantAmastyGiftCards
     */
    public function deleteRedundantAmastyGiftCards_ifAmastyGiftCardIsUnavailable_returnsNull()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable']);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(false);

        static::assertNull($this->currentMock->deleteRedundantAmastyGiftCards($this->quote));
    }

    /**
     * @test
     * that deleteRedundantAmastyGiftCards deletes redundant Amasty Giftcards for the provided quote
     * using a custom SQL query if the Amasty Giftcards module is available
     *
     * @covers ::deleteRedundantAmastyGiftCards
     */
    public function deleteRedundantAmastyGiftCards_ifAmastyGiftCardIsAvailable_executesQuery()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(false);
        $this->resource->method('getConnection')->willReturn($this->connectionMock);
        $testTableName = 'quotes';
        $giftCardTable = 'test_table';
        $this->resource->expects(static::exactly(2))
            ->method('getTableName')
            ->withConsecutive(['amasty_giftcard_quote'], ['quote'])
            ->willReturnOnConsecutiveCalls($giftCardTable, $testTableName);

        $quoteId = 11;
        $quoteMock = $this->createPartialMock(Quote::class, ['getId', 'getBoltParentQuoteId']);
        $quoteMock->expects(static::exactly(2))->method('getBoltParentQuoteId')
            ->willReturnOnConsecutiveCalls($quoteId, $quoteId);

        $sql = "DELETE FROM {$giftCardTable} WHERE quote_id IN 
                    (SELECT entity_id FROM {$testTableName} 
                    WHERE bolt_parent_quote_id = :bolt_parent_quote_id AND entity_id != :entity_id)";

        $bind = [
            'bolt_parent_quote_id' => $quoteId,
            'entity_id'            => $quoteId
        ];

        $this->connectionMock->expects(static::once())->method('query')->with($sql, $bind)->willReturnSelf();

        $this->currentMock->deleteRedundantAmastyGiftCards($quoteMock);
    }
    
    /**
     * @test
     * that deleteRedundantAmastyGiftCards deletes redundant Amasty Giftcards for the provided quote
     * using a custom SQL query if the Amasty Giftcards module is available
     *
     * @covers ::deleteRedundantAmastyGiftCards
     */
    public function deleteRedundantAmastyGiftCards_legacyVersion_ifAmastyGiftCardIsAvailable_executesQuery()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(true);
        $this->resource->method('getConnection')->willReturn($this->connectionMock);
        $testTableName = 'quotes';
        $giftCardTable = 'test_table';
        $this->resource->expects(static::exactly(2))
            ->method('getTableName')
            ->withConsecutive(['amasty_amgiftcard_quote'], ['quote'])
            ->willReturnOnConsecutiveCalls($giftCardTable, $testTableName);

        $quoteId = 11;
        $quoteMock = $this->createPartialMock(Quote::class, ['getId', 'getBoltParentQuoteId']);
        $quoteMock->expects(static::exactly(2))->method('getBoltParentQuoteId')
            ->willReturnOnConsecutiveCalls($quoteId, $quoteId);

        $sql = "DELETE FROM {$giftCardTable} WHERE quote_id IN 
                    (SELECT entity_id FROM {$testTableName} 
                    WHERE bolt_parent_quote_id = :bolt_parent_quote_id AND entity_id != :entity_id)";

        $bind = [
            'bolt_parent_quote_id' => $quoteId,
            'entity_id'            => $quoteId
        ];

        $this->connectionMock->expects(static::once())->method('query')->with($sql, $bind)->willReturnSelf();

        $this->currentMock->deleteRedundantAmastyGiftCards($quoteMock);
    }

    /**
     * @test
     * that deleteRedundantAmastyGiftCards only notifies Bugsnag if the SQL query throws an exception
     *
     * @covers ::deleteRedundantAmastyGiftCards
     */
    public function deleteRedundantAmastyGiftCards_ifAmastyGiftCardIsAvailableAndQueryThrowsException_notifiesBugSnag()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(false);
        $this->resource->method('getConnection')->willReturn($this->connectionMock);
        $testTableName = 'quotes';
        $giftCardTable = 'test_table';
        $this->resource->expects(static::exactly(2))->method('getTableName')->withConsecutive(
            ['amasty_giftcard_quote'],
            ['quote']
        )->willReturnOnConsecutiveCalls($giftCardTable, $testTableName);

        $quoteId = 11;
        $quoteMock = $this->createPartialMock(Quote::class, ['getId', 'getBoltParentQuoteId']);
        $quoteMock->expects(static::exactly(2))->method('getBoltParentQuoteId')
            ->willReturnOnConsecutiveCalls($quoteId, $quoteId);

        $sql = "DELETE FROM {$giftCardTable} WHERE quote_id IN 
                    (SELECT entity_id FROM {$testTableName} 
                    WHERE bolt_parent_quote_id = :bolt_parent_quote_id AND entity_id != :entity_id)";
        $bind = [
            'bolt_parent_quote_id' => $quoteId,
            'entity_id'            => $quoteId
        ];

        $exception = $this->createMock(Zend_Db_Statement_Exception::class);
        $this->connectionMock->expects(static::once())->method('query')->with($sql, $bind)->willThrowException(
            $exception
        );
        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception)->willReturnSelf();
        $this->currentMock->deleteRedundantAmastyGiftCards($quoteMock);
    }
    
    /**
     * @test
     * that deleteRedundantAmastyGiftCards only notifies Bugsnag if the SQL query throws an exception
     *
     * @covers ::deleteRedundantAmastyGiftCards
     */
    public function deleteRedundantAmastyGiftCards_legacyVersion_ifAmastyGiftCardIsAvailableAndQueryThrowsException_notifiesBugSnag()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(true);
        $this->resource->method('getConnection')->willReturn($this->connectionMock);
        $testTableName = 'quotes';
        $giftCardTable = 'test_table';
        $this->resource->expects(static::exactly(2))->method('getTableName')->withConsecutive(
            ['amasty_amgiftcard_quote'],
            ['quote']
        )->willReturnOnConsecutiveCalls($giftCardTable, $testTableName);

        $quoteId = 11;
        $quoteMock = $this->createPartialMock(Quote::class, ['getId', 'getBoltParentQuoteId']);
        $quoteMock->expects(static::exactly(2))->method('getBoltParentQuoteId')
            ->willReturnOnConsecutiveCalls($quoteId, $quoteId);

        $sql = "DELETE FROM {$giftCardTable} WHERE quote_id IN 
                    (SELECT entity_id FROM {$testTableName} 
                    WHERE bolt_parent_quote_id = :bolt_parent_quote_id AND entity_id != :entity_id)";
        $bind = [
            'bolt_parent_quote_id' => $quoteId,
            'entity_id'            => $quoteId
        ];

        $exception = $this->createMock(Zend_Db_Statement_Exception::class);
        $this->connectionMock->expects(static::once())->method('query')->with($sql, $bind)->willThrowException(
            $exception
        );
        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception)->willReturnSelf();
        $this->currentMock->deleteRedundantAmastyGiftCards($quoteMock);
    }

    /**
     * @test
     * that removeAmastyGiftCard returns null if the Amasty Gift Card module is not available
     *
     * @covers ::removeAmastyGiftCard
     */
    public function removeAmastyGiftCard_ifAmastyGiftCardIsUnavailable_returnsNull()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable']);
        $testCodeId = 12;
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(false);

        static::assertNull($this->currentMock->removeAmastyGiftCard($testCodeId, $this->quote));
    }
    
    /**
     * @test
     * that removeAmastyGiftCard collects totals and saves the quote if the Amasty Gift Card module is available
     *
     * @covers ::removeAmastyGiftCard
     */
    public function removeAmastyGiftCard_ifAmastyGiftCardIsAvailable_collectsTotalsAndSavesQuote()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(false);
        $codeId = 5;
        $code = 'BOLTTESTCARD';
        $quoteId = 11;
        $cardInfo = [
            [
                'id' => 5,
                'code' => $code,
                'amount' => 10,
                'b_amount' => 10
            ]
        ];
        $quoteMock = $this->createPartialMock(
            Quote::class,
            [
                'getId',
                'getExtensionAttributes'
            ]
        );
        $extensionAttributes = $this->createPartialMock(CartExtension::class, ['getAmGiftcardQuote']);
        $gCardQuote = $this->createPartialMock(GiftCardQuote::class, ['getGiftCards']);
        
        $gCardQuote->expects($this->atLeastOnce())->method('getGiftCards')
            ->willReturn($cardInfo);
        
        $extensionAttributes->expects($this->atLeastOnce())->method('getAmGiftcardQuote')
            ->willReturn($gCardQuote);        
        
        $quoteMock->expects(static::once())->method('getId')->willReturn($quoteId);
        $quoteMock->expects($this->atLeastOnce())->method('getExtensionAttributes')
            ->willReturn($extensionAttributes);

        $amastyGiftCardMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'removeGiftCardFromCart'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyGiftCardAccountManagement', $amastyGiftCardMock);

        $amastyGiftCardMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $amastyGiftCardMock->expects(static::once())->method('removeGiftCardFromCart')->with($quoteId, $code)->willReturn($code);
        $this->currentMock->removeAmastyGiftCard($codeId, $quoteMock);
    }

    /**
     * @test
     * that removeAmastyGiftCard collects totals and saves the quote if the Amasty Gift Card module is available
     *
     * @covers ::removeAmastyGiftCard
     */
    public function removeAmastyGiftCard_legacyVersion_ifAmastyGiftCardIsAvailable_collectsTotalsAndSavesQuote()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(true);
        $codeId = 5;

        $this->resource->expects(static::once())->method('getConnection')->willReturn($this->connectionMock);
        $testTableName = 'amasty_amgiftcard_quote';
        $this->resource->method('getTableName')->with('amasty_amgiftcard_quote')->willReturn($testTableName);

        $quoteId = 11;
        $quoteMock = $this->createPartialMock(
            Quote::class,
            [
                'getId',
                'getShippingAddress',
                'setTotalsCollectedFlag',
                'collectTotals',
                'setDataChanges',
                'setCollectShippingRates'
            ]
        );
        $quoteMock->expects(static::once())->method('getId')->willReturn($quoteId);

        $sql = "DELETE FROM {$testTableName} WHERE code_id = :code_id AND quote_id = :quote_id";
        $bind = ['code_id' => $codeId, 'quote_id' => $quoteId];

        $this->connectionMock->expects(static::once())->method('query')->with($sql, $bind)->willReturnSelf();

        $quoteMock->expects(static::once())->method('getShippingAddress')->willReturnSelf();
        $quoteMock->expects(static::once())->method('setCollectShippingRates')->with(true)->willReturnSelf();
        $quoteMock->expects(static::once())->method('setTotalsCollectedFlag')->with(false)->willReturnSelf();
        $quoteMock->expects(static::once())->method('collectTotals')->willReturnSelf();
        $quoteMock->expects(static::once())->method('setDataChanges')->with(true)->willReturnSelf();

        $this->currentMock->removeAmastyGiftCard($codeId, $quoteMock);
    }

    /**
     * @test
     * that removeAmastyGiftCard only notifies Bugsnag if an exception occurs during gift card removal
     *
     * @covers ::removeAmastyGiftCard
     */
    public function removeAmastyGiftCard_legacyVersion_queryThrowsException_notifiesBugSnag()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(true);
        $codeId = 5;

        $this->resource->expects(static::once())->method('getConnection')->willReturn($this->connectionMock);
        $testTableName = 'amasty_amgiftcard_quotes';
        $this->resource->method('getTableName')->with('amasty_amgiftcard_quote')->willReturn($testTableName);

        $quoteId = 11;
        $quoteMock = $this->createPartialMock(Quote::class, ['getId']);
        $quoteMock->expects(static::once())->method('getId')->willReturn($quoteId);

        $sql = "DELETE FROM {$testTableName} WHERE code_id = :code_id AND quote_id = :quote_id";
        $bind = ['code_id' => $codeId, 'quote_id' => $quoteId];

        $this->connectionMock->expects(static::once())->method('query')->with($sql, $bind)->willReturnSelf();

        $exception = $this->createMock(Zend_Db_Statement_Exception::class);
        $this->connectionMock->expects(static::once())->method('query')->with($sql, $bind)->willThrowException(
            $exception
        );

        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception)->willReturnSelf();
        $this->currentMock->removeAmastyGiftCard($codeId, $quoteMock);
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
     * that getAmastyGiftCardCodesFromTotals returns the array of the Amasty Gift Card codes
     *
     * @covers ::getAmastyGiftCardCodesFromTotals
     */
    public function getAmastyGiftCardCodesFromTotals_always_returnsArrayOfGiftCardCodes()
    {
        $this->initCurrentMock();
        $totalsMock = $this->createPartialMock(Quote\Address\Total::class, ['getTitle']);
        $totalsMock->expects(static::once())->method('getTitle')->willReturn('first');

        static::assertEquals(
            ['first'],
            $this->currentMock->getAmastyGiftCardCodesFromTotals(
                [
                    Discount::AMASTY_GIFTCARD => $totalsMock
                ]
            )
        );
    }
    
    /**
     * @test
     * that getAmastyGiftCardCodesCurrentValue returns accumulated unused balances
     * of the provided Amasty Gift Card codes
     *
     * @covers ::getAmastyGiftCardCodesCurrentValue
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function getAmastyGiftCardCodesCurrentValue_always_returnsGiftCardsUnusedBalance()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(false);
        
        $giftCardCodes = [22, 33, 44, 55];

        $amastyAccountMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'addCodeTable', 'addFieldToFilter', 'getData'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyGiftCardAccountCollection', $amastyAccountMock);

        $amastyAccountMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $amastyAccountMock->expects(static::once())->method('addCodeTable')->willReturnSelf();
        $amastyAccountMock->expects(static::once())
            ->method('addFieldToFilter')
            ->with('code', ['in' => $giftCardCodes])
            ->willReturnSelf();
        $giftCardCodesUnusedBalance = [
            ["current_value" => 20],
            ["current_value" => 30],
            ["current_value" => 40],
            ["current_value" => 50],
        ];
        $amastyAccountMock->expects(static::once())->method('getData')->willReturn($giftCardCodesUnusedBalance);

        static::assertEquals(
            array_sum(array_column($giftCardCodesUnusedBalance, 'current_value')),
            $this->currentMock->getAmastyGiftCardCodesCurrentValue($giftCardCodes)
        );
    }

    /**
     * @test
     * that getAmastyGiftCardCodesCurrentValue returns accumulated unused balances
     * of the provided Amasty Gift Card codes
     *
     * @covers ::getAmastyGiftCardCodesCurrentValue
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function getAmastyGiftCardCodesCurrentValue_legacyVersion_always_returnsGiftCardsUnusedBalance()
    {
        $this->initCurrentMock(['isAmastyGiftCardAvailable', 'isAmastyGiftCardLegacyVersion']);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardAvailable')->willReturn(true);
        $this->currentMock->expects(static::once())->method('isAmastyGiftCardLegacyVersion')->willReturn(true);
        
        $giftCardCodes = [22, 33, 44, 55];

        $amastyAccountMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'joinCode', 'addFieldToFilter', 'getData'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyLegacyAccountCollection', $amastyAccountMock);

        $amastyAccountMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $amastyAccountMock->expects(static::once())->method('joinCode')->willReturnSelf();
        $amastyAccountMock->expects(static::once())
            ->method('addFieldToFilter')
            ->with('gift_code', ['in' => $giftCardCodes])
            ->willReturnSelf();
        $giftCardCodesUnusedBalance = [
            ["current_value" => 20],
            ["current_value" => 30],
            ["current_value" => 40],
            ["current_value" => 50],
        ];
        $amastyAccountMock->expects(static::once())->method('getData')->willReturn($giftCardCodesUnusedBalance);

        static::assertEquals(
            array_sum(array_column($giftCardCodesUnusedBalance, 'current_value')),
            $this->currentMock->getAmastyGiftCardCodesCurrentValue($giftCardCodes)
        );
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
     * that isBssStoreCreditAllowed returns whether BSS Store Credit is available
     *
     * @covers ::isBssStoreCreditAllowed
     *
     * @dataProvider isBssStoreCreditAllowed_withVariousCreditHelperAvailabilityProvider
     *
     * @param bool $isBssStoreCreditAvailable stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isAvailable}
     * @param bool $bssActiveConfig Bss general config value for 'active' config
     * @param int  $expectedResult of the tested method
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function isBssStoreCreditAllowed_withVariousCreditHelperAvailability_returnsBssStoreCreditAllowed(
        $isBssStoreCreditAvailable,
        $bssActiveConfig,
        $expectedResult
    ) {
        $this->initCurrentMock();

        $bssStoreCreditHelper = $this->createPartialMock(
            ThirdPartyModuleFactory::class,
            ['isAvailable', 'getInstance', 'getGeneralConfig']
        );
        TestHelper::setProperty($this->currentMock, 'bssStoreCreditHelper', $bssStoreCreditHelper);
        $bssStoreCreditHelper->expects(static::once())->method('isAvailable')->willReturn($isBssStoreCreditAvailable);
        $bssStoreCreditHelper->method('getInstance')->willReturnSelf();
        $bssStoreCreditHelper->expects($bssActiveConfig ? static::once() : static::never())
            ->method('getGeneralConfig')
            ->with('active')
            ->willReturn($bssActiveConfig);

        static::assertEquals($expectedResult, $this->currentMock->isBssStoreCreditAllowed());
    }

    /**
     * Data provider for {@see isBssStoreCreditAllowed_withVariousCreditHelperAvailability_returnsBssStoreCreditAllowed}
     *
     * @return array[] containing flags Bss Store Credit Availability, expected general config and
     * expected result of the method call
     */
    public function isBssStoreCreditAllowed_withVariousCreditHelperAvailabilityProvider()
    {
        return [
            ['isBssStoreCreditAvailable' => false, 'bssActiveConfig' => false, 'expectedResult' => 0],
            ['isBssStoreCreditAvailable' => true, 'bssActiveConfig' => 1, 'expectedResult' => 1],
        ];
    }

    /**
     * @test
     * that getBssStoreCreditAmount returns Bss Store Credit amount applied to provided parent quote
     * by calling {@see \Bolt\Boltpay\Helper\Discount::getBssStoreCreditBalanceAmount}
     *
     * @covers ::getBssStoreCreditAmount
     *
     * @throws ReflectionException if unable to set internal mocked properties
     */
    public function getBssStoreCreditAmount_ifIsAppliedToShippingAndTax_returnsStoreCreditAmount()
    {
        $this->initCurrentMock(['getBssStoreCreditBalanceAmount']);
        $immutableQuoteMock = $this->createPartialMock(
            Quote::class,
            ['getBaseBssStorecreditAmountInput', 'getSubtotal', 'setBaseBssStorecreditAmountInput', 'save']
        );
        $parentQuote = $this->createPartialMock(Quote::class, ['setBaseBssStorecreditAmountInput', 'save']);

        $bssStoreCreditHelper = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'getGeneralConfig'])
            ->disableOriginalConstructor()
            ->getMock();
        TestHelper::setProperty($this->currentMock, 'bssStoreCreditHelper', $bssStoreCreditHelper);

        $bssStoreCreditHelper->expects(static::once())->method('getInstance')->willReturnSelf();
        $bssStoreCreditHelper->expects(static::exactly(2))
            ->method('getGeneralConfig')
            ->withConsecutive(['used_shipping'], ['used_tax'])
            ->willReturnOnConsecutiveCalls(false, true);
        $storeCreditAmount = 10;
        $immutableQuoteMock->expects(static::once())
            ->method('getBaseBssStorecreditAmountInput')
            ->willReturn($storeCreditAmount);
        $subtotal = 5;
        $immutableQuoteMock->expects(static::once())->method('getSubtotal')->willReturn($subtotal);
        $newStoreCreditAmount = 55;
        $this->currentMock->expects(static::once())
            ->method('getBssStoreCreditBalanceAmount')
            ->with($parentQuote)
            ->willReturn($newStoreCreditAmount);
        $parentQuote->expects(static::once())
            ->method('setBaseBssStorecreditAmountInput')
            ->with($newStoreCreditAmount)
            ->willReturnSelf();
        $parentQuote->expects(static::once())->method('save')->willReturnSelf();

        $immutableQuoteMock->expects(static::once())
            ->method('setBaseBssStorecreditAmountInput')
            ->with($newStoreCreditAmount)
            ->willReturnSelf();
        $immutableQuoteMock->expects(static::once())->method('save')->willReturnSelf();

        static::assertEquals(
            $newStoreCreditAmount,
            $this->currentMock->getBssStoreCreditAmount($immutableQuoteMock, $parentQuote)
        );
    }

    /**
     * @test
     * that getBssStoreCreditAmount returns base store credit amount from input if the store credit
     * is configured to not be applied to neither shipping nor tax
     *
     * @covers ::getBssStoreCreditAmount
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function getBssStoreCreditAmount_whenCantAppliedShippingAndTax_returnsStoreCreditAmount()
    {
        $this->initCurrentMock(['getBssStoreCreditBalanceAmount']);
        $immutableQuoteMock = $this->createPartialMock(
            Quote::class,
            ['getBaseBssStorecreditAmountInput', 'getSubtotal', 'setBaseBssStorecreditAmountInput', 'save']
        );
        $parentQuote = $this->createPartialMock(Quote::class, ['setBaseBssStorecreditAmountInput', 'save']);

        $bssStoreCreditHelper = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'getGeneralConfig'])
            ->disableOriginalConstructor()
            ->getMock();
        TestHelper::setProperty($this->currentMock, 'bssStoreCreditHelper', $bssStoreCreditHelper);

        $bssStoreCreditHelper->expects(static::once())->method('getInstance')->willReturnSelf();
        $bssStoreCreditHelper->expects(static::exactly(2))
            ->method('getGeneralConfig')
            ->withConsecutive(['used_shipping'], ['used_tax'])
            ->willReturnOnConsecutiveCalls(false, false);
        $storeCreditAmount = 10;
        $immutableQuoteMock->expects(static::once())
            ->method('getBaseBssStorecreditAmountInput')
            ->willReturn($storeCreditAmount);

        static::assertEquals(
            $storeCreditAmount,
            $this->currentMock->getBssStoreCreditAmount($immutableQuoteMock, $parentQuote)
        );
    }

    /**
     * @test
     * that getBssStoreCreditAmount returns zero for the store credit amount and notifies the Bugsnag if
     * an exception occurs when retrieving amount
     *
     * @covers ::getBssStoreCreditAmount
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function getBssStoreCreditAmount_quoteThrowsException_returnsZeroCreditAmount()
    {
        $this->initCurrentMock(['getBssStoreCreditBalanceAmount']);
        $immutableQuoteMock = $this->createPartialMock(
            Quote::class,
            ['getBaseBssStorecreditAmountInput', 'getSubtotal', 'setBaseBssStorecreditAmountInput', 'save']
        );
        $parentQuote = $this->createPartialMock(Quote::class, ['setBaseBssStorecreditAmountInput', 'save']);

        $bssStoreCreditHelper = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'getGeneralConfig'])
            ->disableOriginalConstructor()
            ->getMock();
        TestHelper::setProperty($this->currentMock, 'bssStoreCreditHelper', $bssStoreCreditHelper);

        $bssStoreCreditHelper->expects(static::once())->method('getInstance')->willReturnSelf();
        $bssStoreCreditHelper->expects(static::exactly(2))
            ->method('getGeneralConfig')
            ->withConsecutive(['used_shipping'], ['used_tax'])
            ->willReturnOnConsecutiveCalls(false, true);
        $storeCreditAmount = 10;
        $immutableQuoteMock->expects(static::once())
            ->method('getBaseBssStorecreditAmountInput')
            ->willReturn($storeCreditAmount);
        $subtotal = 5;
        $immutableQuoteMock->expects(static::once())->method('getSubtotal')->willReturn($subtotal);
        $newStoreCreditAmount = 55;
        $this->currentMock->expects(static::once())
            ->method('getBssStoreCreditBalanceAmount')
            ->with($parentQuote)
            ->willReturn($newStoreCreditAmount);
        $parentQuote->expects(static::once())
            ->method('setBaseBssStorecreditAmountInput')
            ->with($newStoreCreditAmount)
            ->willReturnSelf();
        $exception = $this->createMock(\Exception::class);

        $parentQuote->expects(static::once())->method('save')->willThrowException($exception);

        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception)->willReturnSelf();

        static::assertEquals(
            0,
            $this->currentMock->getBssStoreCreditAmount($immutableQuoteMock, $parentQuote)
        );
    }

    /**
     * @test
     * that getBssStoreCreditBalanceAmount returns the Bss Store Credit balance by adding up all balances
     * loaded by provided quote's customer id
     *
     * @covers ::getBssStoreCreditBalanceAmount
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function getBssStoreCreditBalanceAmount_always_returnsBalance()
    {
        $this->initCurrentMock();
        $bssStoreCreditHelper = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'addFieldToFilter', 'getData'])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock = $this->createPartialMock(Quote::class, ['getCustomerId']);
        $quoteCustomerId = 5;
        $quoteMock->expects(static::once())->method('getCustomerId')->willReturn($quoteCustomerId);

        TestHelper::setProperty($this->currentMock, 'bssStoreCreditCollection', $bssStoreCreditHelper);

        $bssStoreCreditHelper->expects(static::once())->method('getInstance')->willReturnSelf();
        $bssStoreCreditHelper->expects(static::once())
            ->method('addFieldToFilter')
            ->with('customer_id', ['in' => $quoteCustomerId])
            ->willReturnSelf();
        $data = [
            ['balance_amount' => 5],
            ['balance_amount' => 10]
        ];
        $bssStoreCreditHelper->expects(static::once())->method('getData')->willReturn($data);

        static::assertEquals(
            array_sum(array_column($data, 'balance_amount')),
            $this->currentMock->getBssStoreCreditBalanceAmount($quoteMock)
        );
    }

    /**
     * @test
     * that isMirasvitStoreCreditAllowed returns whether the Mirasvit Store Credit is allowed for the provided quote
     *
     * @covers ::isMirasvitStoreCreditAllowed
     *
     * @dataProvider isMirasvitStoreCreditAllowed_withVariousCreditAmountsProvider
     *
     * @param bool $mirasvitStoreCreditAvailability flag representing module availability
     * @param int  $quoteCreditAmount quote data field representing Store Credit amount used in the quote
     * @param int  $mirasvitStoreCreditAmount value of Mirasvit Store Credit,
     * stubbed result of {@see \Bolt\Boltpay\Helper\Discount::getMirasvitStoreCreditUsedAmount}
     * @param bool $expectedResult of the method call
     *
     * @throws ReflectionException if unable to create quote mock
     */
    public function isMirasvitStoreCreditAllowed_withVariousCreditAmounts_returnsMirasvitStoreCreditAvailability(
        $mirasvitStoreCreditAvailability,
        $quoteCreditAmount,
        $mirasvitStoreCreditAmount,
        $expectedResult
    ) {
        $this->initCurrentMock(['getMirasvitStoreCreditUsedAmount']);
        $quoteMock = $this->createPartialMock(Quote::class, ['getCreditAmountUsed']);

        $this->mirasvitStoreCreditHelper->expects(static::once())
            ->method('isAvailable')
            ->willReturn($mirasvitStoreCreditAvailability);

        $quoteMock->expects($mirasvitStoreCreditAvailability ? static::once() : static::never())
            ->method('getCreditAmountUsed')
            ->willReturn($quoteCreditAmount);

        $this->currentMock
            ->expects($mirasvitStoreCreditAvailability && $quoteCreditAmount ? static::once() : static::never())
            ->method('getMirasvitStoreCreditUsedAmount')
            ->with($quoteMock)
            ->willReturn($mirasvitStoreCreditAmount);

        static::assertEquals($expectedResult, $this->currentMock->isMirasvitStoreCreditAllowed($quoteMock));
    }

    /**
     * Data provider for
     * @see isMirasvitStoreCreditAllowed_withVariousCreditAmounts_returnsMirasvitStoreCreditAvailability
     *
     * @return array[] containing flags Mirasvit Store Credit module availability, quote credit amount used,
     * Mirasvit Store Credit Amount used and expected result of the method call
     */
    public function isMirasvitStoreCreditAllowed_withVariousCreditAmountsProvider()
    {
        return [
            [
                'mirasvitStoreCreditAvailability' => false,
                'quoteCreditAmount'               => null,
                'mirasvitStoreCreditAmount'       => 0,
                'expectedResult'                  => false,
            ],
            [
                'mirasvitStoreCreditAvailability' => true,
                'quoteCreditAmount'               => 0,
                'mirasvitStoreCreditAmount'       => 0,
                'expectedResult'                  => false,
            ],
            [
                'mirasvitStoreCreditAvailability' => true,
                'quoteCreditAmount'               => 3,
                'mirasvitStoreCreditAmount'       => 10,
                'expectedResult'                  => true,
            ],
        ];
    }

    /**
     * @test
     * that getMirasvitStoreCreditAmount returns unresolved total from {@see \Mirasvit\Credit\Helper\Calculation::calc}
     * if the checkout type is not payment and the unresolved total is less than store credit balance
     *
     * @covers ::getMirasvitStoreCreditAmount
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function getMirasvitStoreCreditAmount_ifHasPaymentOnly_returnsMinAmount()
    {
        $this->initCurrentMock(['getMirasvitStoreCreditUsedAmount']);
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->setMethods(['getGrandTotal', 'getCreditAmountUsed', 'getTotals', 'getValue'])
            ->disableOriginalConstructor()
            ->getMock();
        $mirasvitBalanceAmount = 100;
        $this->currentMock->expects(static::once())
            ->method('getMirasvitStoreCreditUsedAmount')
            ->with($quoteMock)
            ->willReturn($mirasvitBalanceAmount);
        $grandTotal = 10;
        $creditAmountUsed = 15;
        $quoteMock->expects(static::once())->method('getGrandTotal')->willReturn($grandTotal);
        $quoteMock->expects(static::once())->method('getCreditAmountUsed')->willReturn($creditAmountUsed);
        $unresolvedTotal = $grandTotal + $creditAmountUsed;
        $taxValue = 4;
        $creditAmountUsed = ['tax' => $quoteMock];
        $quoteMock->expects(static::once())->method('getTotals')->willReturn($creditAmountUsed);
        $quoteMock->expects(static::once())->method('getValue')->willReturn($taxValue);
        $shippingValue = 0;
        $quoteMock->expects(static::once())->method('getValue')->willReturn($shippingValue);

        $creditCalculatorMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'calc'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'mirasvitStoreCreditCalculationHelper', $creditCalculatorMock);

        $creditCalculatorMock->expects(static::once())->method('getInstance')->willReturnSelf();

        $unresolvedTotalSum = $unresolvedTotal + $taxValue + $shippingValue;
        $creditCalculatorMock->expects(static::once())
            ->method('calc')
            ->with($unresolvedTotal, $taxValue, $shippingValue)
            ->willReturn($unresolvedTotalSum);

        static::assertEquals(
            $unresolvedTotalSum,
            $this->currentMock->getMirasvitStoreCreditAmount($quoteMock, true)
        );
    }

    /**
     * @test
     * that getMirasvitStoreCreditAmount returns balance amount retrieved from
     * {@see \Bolt\Boltpay\Helper\Discount::getMirasvitStoreCreditUsedAmount} if provided paymentOnly parameter is true
     * and Mirasvit calculation tax is configured to be included
     *
     * @covers ::getMirasvitStoreCreditAmount
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function getMirasvitStoreCreditAmount_ifPaymentOnlyAndTaxIncluded_returnsBalanceAmount()
    {
        $this->initCurrentMock(['getMirasvitStoreCreditUsedAmount']);
        $quoteMock = $this->createPartialMock(
            Quote::class,
            ['getGrandTotal', 'getCreditAmountUsed', 'getTotals', 'getValue']
        );
        $mirasvitBalanceAmount = 100;
        $this->currentMock->expects(static::once())
            ->method('getMirasvitStoreCreditUsedAmount')
            ->with($quoteMock)
            ->willReturn($mirasvitBalanceAmount);

        $mirasvitStoreCCCMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'isTaxIncluded', 'IsShippingIncluded'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'mirasvitStoreCreditCalculationConfig', $mirasvitStoreCCCMock);

        $mirasvitStoreCCCMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $mirasvitStoreCCCMock->expects(static::once())->method('isTaxIncluded')->willReturn(true);

        static::assertEquals(
            $mirasvitBalanceAmount,
            $this->currentMock->getMirasvitStoreCreditAmount($quoteMock, false)
        );
    }

    /**
     * @test
     * that getMirasvitStoreCreditUsedAmount returns the Mirasvit store credit balance if
     * currency code balance is equal to the currency code quote
     *
     * @covers ::getMirasvitStoreCreditUsedAmount
     *
     * @throws ReflectionException if unable to set internal mock properties or
     * getMirasvitStoreCreditUsedAmount method doesn't exist
     */
    public function getMirasvitStoreCreditUsedAmount_whenQuoteCurrencyMatchesBalanceCurrency_returnsAmount()
    {
        $this->initCurrentMock();

        $quoteMock = $this->getMockBuilder(Quote::class)
            ->setMethods(['getCustomerId', 'getManualUsedCredit', 'getQuoteCurrencyCode'])
            ->disableOriginalConstructor()
            ->getMock();
        $balanceMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'getBalance', 'getAmount', 'getCurrencyCode'])
            ->disableOriginalConstructor()
            ->getMock();
        TestHelper::setProperty($this->currentMock, 'mirasvitStoreCreditHelper', $balanceMock);

        $balanceMock->expects(static::once())->method('getInstance')->willReturnSelf();

        $customerId = 2;
        $quoteCurrencyCode = 'USD';
        $quoteMock->expects(static::once())->method('getCustomerId')->willReturn($customerId);
        $quoteMock->expects(static::exactly(2))
            ->method('getQuoteCurrencyCode')
            ->willReturnOnConsecutiveCalls($quoteCurrencyCode, $quoteCurrencyCode);

        $balanceMock->expects(static::once())
            ->method('getBalance')
            ->with($customerId, $quoteCurrencyCode)
            ->willReturnSelf();

        $manualUsedCredit = 15.5;
        $quoteMock->expects(static::exactly(2))
            ->method('getManualUsedCredit')
            ->willReturnOnConsecutiveCalls($manualUsedCredit, $manualUsedCredit);

        $balanceMock->expects(static::once())->method('getCurrencyCode')->willReturn($quoteCurrencyCode);

        static::assertEquals(
            $manualUsedCredit,
            TestHelper::invokeMethod($this->currentMock, 'getMirasvitStoreCreditUsedAmount', [$quoteMock])
        );
    }

    /**
     * @test
     * that getMirasvitStoreCreditUsedAmount returns used amount converted into quote currency if quote currency
     * doesn't match Mirasvit credit balance currency
     *
     * @covers ::getMirasvitStoreCreditUsedAmount
     *
     * @throws ReflectionException if unable to set internal mock properties or
     * getMirasvitStoreCreditUsedAmount method doesn't exist
     */
    public function getMirasvitStoreCreditUsedAmount_ifQuoteCurrencyCodeDoesNotMatchBalanceCurrencyCode_convertsToQuoteCurrency()
    {
        $this->initCurrentMock();

        $quoteMock = $this->getMockBuilder(Quote::class)
            ->setMethods(['getCustomerId', 'getManualUsedCredit', 'getQuoteCurrencyCode', 'getStore'])
            ->disableOriginalConstructor()
            ->getMock();
        $balanceMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'getBalance', 'getAmount', 'getCurrencyCode'])
            ->disableOriginalConstructor()
            ->getMock();
        TestHelper::setProperty($this->currentMock, 'mirasvitStoreCreditHelper', $balanceMock);

        $balanceMock->expects(static::once())->method('getInstance')->willReturnSelf();

        $customerId = 2;
        $quoteCurrencyCode = 'USD';
        $quoteMock->expects(static::once())->method('getCustomerId')->willReturn($customerId);
        $quoteMock->expects(static::exactly(3))
            ->method('getQuoteCurrencyCode')
            ->willReturnOnConsecutiveCalls($quoteCurrencyCode, $quoteCurrencyCode, $quoteCurrencyCode);

        $balanceMock->expects(static::once())
            ->method('getBalance')
            ->with($customerId, $quoteCurrencyCode)
            ->willReturnSelf();

        $manualUsedCredit = 15.5;
        $quoteMock->expects(static::exactly(2))
            ->method('getManualUsedCredit')
            ->willReturnOnConsecutiveCalls($manualUsedCredit, $manualUsedCredit);

        $balanceCurrencyCode = 'EUR';
        $balanceMock->expects(static::exactly(2))
            ->method('getCurrencyCode')
            ->willReturnOnConsecutiveCalls($balanceCurrencyCode, $balanceCurrencyCode);

        $mirasvitStoreCCHelperMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'convertToCurrency'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'mirasvitStoreCreditCalculationHelper', $mirasvitStoreCCHelperMock);

        $store = '';
        $quoteMock->expects(static::once())->method('getStore')->willReturn($store);

        $mirasvitStoreCCHelperMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $newAmount = 20.4;
        $mirasvitStoreCCHelperMock->expects(static::once())
            ->method('convertToCurrency')
            ->with($manualUsedCredit, $balanceCurrencyCode, $quoteCurrencyCode, $store)
            ->willReturn($newAmount);

        static::assertEquals(
            $newAmount,
            TestHelper::invokeMethod($this->currentMock, 'getMirasvitStoreCreditUsedAmount', [$quoteMock])
        );
    }

    /**
     * @test
     * that isMirasvitAdminQuoteUsingCreditObserver returns true only if:
     * 1. Quote payment method is Bolt
     * 2. Current area is admin
     * 3. Quote use store credit is set to yes
     * otherwise false
     *
     * @covers ::isMirasvitAdminQuoteUsingCreditObserver
     *
     * @dataProvider isMirasvitAdminQuoteUsingCreditObserver_withVariousCreditConfigPropertiesProvider
     *
     * @param bool $mirasvitStoreCreditConfigAvailable flag for Mirasvit StoreCredit module availability
     * @param bool $useCredit quote use credit value
     * @param bool $expectException whether to expect exception
     * @param bool $expectedResult of the tested method call
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function isMirasvitAdminQuoteUsingCreditObserver_withVariousCreditConfigProperties_returnsCreditAvailability(
        $mirasvitStoreCreditConfigAvailable,
        $useCredit,
        $expectException,
        $expectedResult
    ) {
        $this->initCurrentMock();
        $mirasvitStoreCreditConfigMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['isAvailable', 'getInstance'])
            ->disableOriginalConstructor()
            ->getMock();
        TestHelper::setProperty($this->currentMock, 'mirasvitStoreCreditConfig', $mirasvitStoreCreditConfigMock);

        $observerMock = $this->getMockBuilder(Observer::class)
            ->setMethods(['getEvent', 'getPayment', 'getMethod', 'getQuote', 'getUseCredit'])
            ->disableOriginalConstructor()
            ->getMock();

        $mirasvitConfigMock = new class {
            const USE_CREDIT_YES = true;
        };

        $mirasvitStoreCreditConfigMock->expects(static::once())
            ->method('isAvailable')
            ->willReturn($mirasvitStoreCreditConfigAvailable);

        $observerMock->expects($mirasvitStoreCreditConfigAvailable ? static::once() : static::never())
            ->method('getEvent')->willReturnSelf();
        $observerMock->expects($mirasvitStoreCreditConfigAvailable ? static::once() : static::never())
            ->method('getPayment')->willReturnSelf();
        $mirasvitStoreCreditConfigMock->expects($mirasvitStoreCreditConfigAvailable ? static::once() : static::never())
            ->method('getInstance')->willReturn($mirasvitConfigMock);

        $observerMock->expects($mirasvitStoreCreditConfigAvailable ? static::once() : static::never())
            ->method('getMethod')->willReturn(Payment::METHOD_CODE);
        $this->appState->expects($mirasvitStoreCreditConfigAvailable ? static::once() : static::never())
            ->method('getAreaCode')->willReturn(FrontNameResolver::AREA_CODE);

        $observerMock->expects($mirasvitStoreCreditConfigAvailable ? static::once() : static::never())
            ->method('getQuote')->willReturnSelf();
        $exceptionMock = $this->createMock(\Exception::class);

        $observerMock->expects($mirasvitStoreCreditConfigAvailable ? static::once() : static::never())
            ->method('getUseCredit')
            ->will($expectException ? static::throwException($exceptionMock) : static::returnValue($useCredit));

        static::assertEquals(
            $expectedResult,
            $this->currentMock->isMirasvitAdminQuoteUsingCreditObserver($observerMock)
        );
    }

    /**
     * Data provider for
     * @see isMirasvitAdminQuoteUsingCreditObserver_withVariousCreditConfigProperties_returnsCreditAvailability
     *
     * @return array[] containing Mirasvit Store Credit module availability flag, use credit, expect exception and
     * expected result of the tested method
     */
    public function isMirasvitAdminQuoteUsingCreditObserver_withVariousCreditConfigPropertiesProvider()
    {
        return [
            [
                "mirasvitStoreCreditConfigAvailable" => false,
                "useCredit"                          => true,
                "expectException"                    => false,
                "expectedResult"                     => false,
            ],
            [
                "mirasvitStoreCreditConfigAvailable" => true,
                "useCredit"                          => true,
                "expectException"                    => false,
                "expectedResult"                     => true,
            ],
            [
                "mirasvitStoreCreditConfigAvailable" => true,
                "useCredit"                          => false,
                "expectException"                    => false,
                "expectedResult"                     => false,
            ],
            [
                "mirasvitStoreCreditConfigAvailable" => true,
                "useCredit"                          => true,
                "expectException"                    => true,
                "expectedResult"                     => false,
            ],
        ];
    }

    /**
     * @test
     * that getMirasvitRewardsAmount returns zero if the Mirasvit rewards purchase helper is unavailable
     *
     * @covers ::getMirasvitRewardsAmount
     */
    public function getMirasvitRewardsAmount_ifPurchaseIsDisabled_returnsZeroAmount()
    {
        $this->initCurrentMock();
        $quote = $this->createMock(Quote::class);
        $this->mirasvitRewardsPurchaseHelper->expects(static::once())->method('getInstance')->willReturn(false);

        static::assertEquals(0, $this->currentMock->getMirasvitRewardsAmount($quote));
    }

    /**
     * @test
     * that getMirasvitRewardsAmount returns the Mirasvit Rewards amount used in the provided quote
     *
     * @covers ::getMirasvitRewardsAmount
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function getMirasvitRewardsAmount_ifPurchaseIsEnabled_returnsUsedAmount()
    {
        $this->initCurrentMock();
        $quote = $this->createMock(Quote::class);
        $mirasvitStoreCreditConfigMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'getByQuote', 'getSpendAmount'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'mirasvitRewardsPurchaseHelper', $mirasvitStoreCreditConfigMock);
        $spendAmount = 55;
        $mirasvitStoreCreditConfigMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $mirasvitStoreCreditConfigMock->expects(static::once())->method('getByQuote')->with($quote)->willReturnSelf();
        $mirasvitStoreCreditConfigMock->expects(static::once())->method('getSpendAmount')->willReturn($spendAmount);

        static::assertEquals($spendAmount, $this->currentMock->getMirasvitRewardsAmount($quote));
    }

    /**
     * @test
     * that isMageplazaGiftCardAvailable returns the Mageplaza Gift Card module availability
     *
     * @covers ::isMageplazaGiftCardAvailable
     *
     * @dataProvider isMageplazaGiftCardAvailable_withVariousMageplazaGiftCardFactoryStatesProvider
     *
     * @param bool $mageplazaGiftCardFactoryAvailability stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isAvailable}
     * @param bool $expectedResult of the method call
     */
    public function isMageplazaGiftCardAvailable_withVariousAmastyRewardAvailability_returnsAvailability(
        $mageplazaGiftCardFactoryAvailability,
        $expectedResult
    ) {
        $this->initCurrentMock();
        $this->mageplazaGiftCardFactory->expects(static::once())
            ->method('isAvailable')
            ->willReturn($mageplazaGiftCardFactoryAvailability);

        static::assertEquals($expectedResult, $this->currentMock->isMageplazaGiftCardAvailable());
    }

    /**
     * Data provider for {@see isMageplazaGiftCardAvailable_withVariousAmastyRewardAvailability_returnsAvailability}
     *
     * @return array[] containing Mageplaza Gift Card module availability and expected result of the method call
     */
    public function isMageplazaGiftCardAvailable_withVariousMageplazaGiftCardFactoryStatesProvider()
    {
        return [
            ['mageplazaGiftCardFactoryAvailability' => true, 'expectedResult' => true],
            ['mageplazaGiftCardFactoryAvailability' => false, 'expectedResult' => false],
        ];
    }

    /**
     * @test
     * that loadMageplazaGiftCard returns null if the Mageplaza Gift Card is unavailable
     *
     * @covers ::loadMageplazaGiftCard
     */
    public function loadMageplazaGiftCard_cardIsUnavailable_returnsNull()
    {
        $this->initCurrentMock(['isMageplazaGiftCardAvailable']);
        $code = 'testCouponCode';
        $storeId = 23;
        $this->currentMock->method('isMageplazaGiftCardAvailable')->willReturn(false);

        static::assertNull($this->currentMock->loadMageplazaGiftCard($code, $storeId));
    }

    /**
     * @test
     * that loadMageplazaGiftCard returns Magplaza Gift Card account model related to the provided code only when:
     * 1. Mageplaza Gift Card module is available
     * 2. The Account Model is loaded successfully
     * 3. Either account model store id matches the provided store id, or it is empty
     *
     * @covers ::loadMageplazaGiftCard
     *
     * @dataProvider loadMageplazaGiftCard_withVariousAccountModelPropertiesProvider
     *
     * @param bool          $isMageplazaGiftCardAvailable flag value
     * @param \Mageplaza\GiftCard\Model\GiftCard|null $accountModel Mageplaza Gift Card mocked instance or null
     * @param int|null      $accountModelId value of Mageplaza Gift Card Model id or null
     * @param int           $accountModelStoreId value of Mageplaza Gift Card Model store id
     * @param int           $storeId value of provided store id
     * @param bool          $expectException flag value
     * @param \Mageplaza\GiftCard\Model\GiftCard|null $expectedResult of the tested method
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function loadMageplazaGiftCard_withVariousAccountModelProperties_returnsAccountModel(
        $isMageplazaGiftCardAvailable,
        $accountModel,
        $accountModelId,
        $accountModelStoreId,
        $storeId,
        $expectException,
        $expectedResult
    ) {
        $this->initCurrentMock(['isMageplazaGiftCardAvailable']);
        $code = 'testCouponCode';
        $this->currentMock->method('isMageplazaGiftCardAvailable')->willReturn($isMageplazaGiftCardAvailable);

        $mageplazaMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)->setMethods(
            [
                'getInstance',
                'load',
                'getId',
                'getStoreId',
            ]
        )->disableOriginalConstructor()->getMock();

        TestHelper::setProperty($this->currentMock, 'mageplazaGiftCardFactory', $mageplazaMock);

        $mageplazaMock->expects($isMageplazaGiftCardAvailable ? static::once() : static::never())
            ->method('getInstance')
            ->willReturnSelf();
        $mageplazaMock->expects($isMageplazaGiftCardAvailable ? static::once() : static::never())
            ->method('load')
            ->with($code, 'code')
            ->willReturn($accountModel);
        $exception = $this->createMock(\Exception::class);
        if ($accountModel) {
            $accountModel->expects($isMageplazaGiftCardAvailable ? static::once() : static::never())
                ->method('getId')
                ->will($expectException ? static::throwException($exception) : static::returnValue($accountModelId));
            $accountModel->expects(
                $isMageplazaGiftCardAvailable && !$expectException ? static::exactly(2) : static::never()
            )->method('getStoreId')->willReturnOnConsecutiveCalls($accountModelStoreId, $accountModelStoreId);
        }
        static::assertEquals($expectedResult, $this->currentMock->loadMageplazaGiftCard($code, $storeId));
    }

    /**
     * Data provider for {@see loadMageplazaGiftCard_withVariousAccountModelProperties_returnsAccountModel}
     *
     * @return array[] containing is MagePlaza Gift Card module available flag, account model instance, account model id,
     * account model store id, store id, expect exception flag and expected result of the tested method
     *
     * @throws ReflectionException if unable to create account model mock
     */
    public function loadMageplazaGiftCard_withVariousAccountModelPropertiesProvider()
    {
        return [
            [
                'isMageplazaGiftCardAvailable' => false,
                'accountModel'                 => $this->createPartialMock(
                    ThirdPartyModuleFactory::class,
                    [
                        'getInstance',
                        'load',
                        'getId',
                        'getStoreId'
                    ]
                ),
                'accountModelId'               => 1,
                'accountModelStoreId'          => 22,
                'storeId'                      => 23,
                'expectException'              => false,
                'expectedResult'               => null,
            ],
            [
                'isMageplazaGiftCardAvailable' => true,
                'accountModel'                 => null,
                'accountModelId'               => null,
                'accountModelStoreId'          => 42,
                'storeId'                      => 22,
                'expectException'              => false,
                'expectedResult'               => null,
            ],
            [
                'isMageplazaGiftCardAvailable' => true,
                'accountModel'                 => $this->createPartialMock(
                    ThirdPartyModuleFactory::class,
                    [
                        'getInstance',
                        'load',
                        'getId',
                        'getStoreId'
                    ]
                ),
                'accountModelId'               => null,
                'accountModelStoreId'          => 22,
                'storeId'                      => 22,
                'expectException'              => false,
                'expectedResult'               => null,
            ],
            [
                'isMageplazaGiftCardAvailable' => true,
                'accountModel'                 => $this->createPartialMock(
                    ThirdPartyModuleFactory::class,
                    [
                        'getInstance',
                        'load',
                        'getId',
                        'getStoreId'
                    ]
                ),
                'accountModelId'               => 66,
                'accountModelStoreId'          => 22,
                'storeId'                      => 84,
                'expectException'              => false,
                'expectedResult'               => null,
            ],
            [
                'isMageplazaGiftCardAvailable' => true,
                'accountModel'                 => $this->createPartialMock(
                    ThirdPartyModuleFactory::class,
                    [
                        'getInstance',
                        'load',
                        'getId',
                        'getStoreId'
                    ]
                ),
                'accountModelId'               => 62,
                'accountModelStoreId'          => 54,
                'storeId'                      => 54,
                'expectException'              => true,
                'expectedResult'               => null,
            ],
            [
                'isMageplazaGiftCardAvailable' => true,
                'accountModel'                 => $this->createPartialMock(
                    ThirdPartyModuleFactory::class,
                    [
                        'getInstance',
                        'load',
                        'getId',
                        'getStoreId'
                    ]
                ),
                'accountModelId'               => 62,
                'accountModelStoreId'          => 54,
                'storeId'                      => 54,
                'expectException'              => false,
                'expectedResult'               => $this->createPartialMock(
                    ThirdPartyModuleFactory::class,
                    [
                        'getInstance',
                        'load',
                        'getId',
                        'getStoreId'
                    ]
                ),
            ],
        ];
    }

    /**
     * @test
     * loadMageplazaGiftCard returns null when model account can't get the id
     *
     * @covers ::loadMageplazaGiftCard
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function loadMageplazaGiftCard_cantGetId_returnsNull()
    {
        $this->initCurrentMock(['isMageplazaGiftCardAvailable']);
        $code = 'testCouponCode';
        $storeId = 23;
        $this->currentMock->method('isMageplazaGiftCardAvailable')->willReturn(true);


        $accountModelMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)->setMethods(
            [
                'getInstance',
                'load',
                'getId',
                'getStoreId',
            ]
        )->disableOriginalConstructor()->getMock();

        TestHelper::setProperty($this->currentMock, 'mageplazaGiftCardFactory', $accountModelMock);

        $accountModelMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $accountModelMock->expects(static::once())->method('load')->with($code, 'code')->willReturnSelf();
        $accountModelMock->expects(static::once())->method('getId')->with()->willReturn(false);

        static::assertNull($this->currentMock->loadMageplazaGiftCard($code, $storeId));
    }

    /**
     * @test
     * that loadMageplazaGiftCard returns null when Mageplaza Gift Card is available and an exception is thrown
     *
     * @covers ::loadMageplazaGiftCard
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function loadMageplazaGiftCard_ifAccountModelThrowsException_returnsNull()
    {
        $this->initCurrentMock(['isMageplazaGiftCardAvailable']);
        $code = 'testCouponCode';
        $storeId = 23;
        $this->currentMock->method('isMageplazaGiftCardAvailable')->willReturn(true);


        $accountModelMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)->setMethods(
            [
                'getInstance',
                'load',
                'getId',
                'getStoreId',
            ]
        )->disableOriginalConstructor()->getMock();

        TestHelper::setProperty($this->currentMock, 'mageplazaGiftCardFactory', $accountModelMock);

        $accountModelMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $exception = $this->createMock(\Exception::class);
        $accountModelMock->expects(static::once())->method('load')->with($code, 'code')->willThrowException($exception);

        static::assertNull($this->currentMock->loadMageplazaGiftCard($code, $storeId));
    }

    /**
     * @test
     * that getMageplazaGiftCardCodes returns Mageplaza GiftCard codes from the provided quote as an array
     *
     * @covers ::getMageplazaGiftCardCodes
     *
     * @dataProvider getMageplazaGiftCardCodes_withVariousGiftCardsProvider
     *
     * @param array       $giftCardsDataSession dummy Gift Card session data
     * @param string|null $quoteMpGiftCards value for quote property containing Mageplaza Giftcard data
     *
     * @throws LocalizedException from tested method
     */
    public function getMageplazaGiftCardCodes_withVariousGiftCards_returnGiftCardCodes(
        $giftCardsDataSession,
        $quoteMpGiftCards
    ) {
        $this->initCurrentMock();
        $this->sessionHelper->expects(self::once())->method('getCheckoutSession')->willReturnSelf();
        $this->sessionHelper->expects(self::once())->method('getGiftCardsData')->willReturn($giftCardsDataSession);
        $this->quote->method('getMpGiftCards')->willReturn($quoteMpGiftCards);
        $result = $this->currentMock->getMageplazaGiftCardCodes($this->quote);
        static::assertEquals(['gift_card'], $result);
    }

    /**
     * Data provider for {@see getMageplazaGiftCardCodes_withVariousGiftCards_returnGiftCardCodes}
     *
     * @return array[] containing Gift Card session data and value for quote property containing Mageplaza Giftcard data
     */
    public function getMageplazaGiftCardCodes_withVariousGiftCardsProvider()
    {
        return [
            [
                'giftCardsDataSession' => ['mp_gift_cards' => ['gift_card' => 1000]],
                'quoteMpGiftCards'        => null
            ],
            [
                'giftCardsDataSession' => [],
                'quoteMpGiftCards'        => '{"gift_card":100}',
            ],
        ];
    }

    /**
     * @test
     * that getMageplazaGiftCardCodesCurrentValue returns accumulated balance of all the applied Mageplaza Gift Cards
     *
     * @covers ::getMageplazaGiftCardCodesCurrentValue
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function getMageplazaGiftCardCodesCurrentValue_always_returnsSummedBalance()
    {
        $this->initCurrentMock();

        $giftCardCodes = [22, 33, 44, 55];

        $mageplazaGiftCardCollectionMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'addFieldToFilter', 'getData'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'mageplazaGiftCardCollection', $mageplazaGiftCardCollectionMock);

        $mageplazaGiftCardCollectionMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $mageplazaGiftCardCollectionMock->expects(static::once())
            ->method('addFieldToFilter')
            ->with('code', ['in' => $giftCardCodes])
            ->willReturnSelf();

        $giftCards = [
            ['code' => 22, 'balance' => 323],
            ['code' => 33, 'balance' => 523],
            ['code' => 44, 'balance' => 723],
            ['code' => 55, 'balance' => 123],
        ];
        $mageplazaGiftCardCollectionMock->expects(static::once())->method('getData')->willReturn($giftCards);

        $expectedResult = array_sum(array_column($giftCards, 'balance'));

        static::assertEquals($expectedResult, $this->currentMock->getMageplazaGiftCardCodesCurrentValue($giftCardCodes));
    }

    /**
     * @test
     * that removeMageplazaGiftCard returns null if the Mageplaza Gift Card module is unavailable
     *
     * @covers ::removeMageplazaGiftCard
     */
    public function removeMageplazaGiftCard_ifMageplazaGiftCardModuleIsUnavailable_returnsNull()
    {
        $this->initCurrentMock(['isMageplazaGiftCardAvailable']);
        $quote = $this->createMock(Quote::class);
        $codeId = 1232;
        $this->currentMock->expects(static::once())->method('isMageplazaGiftCardAvailable')->willReturn(false);

        static::assertNull($this->currentMock->removeMageplazaGiftCard($codeId, $quote));
    }

    /**
     * @test
     * that removeMageplazaGiftCard only notifies the Bugsnag if an exception is thrown when removing giftcard
     *
     * @covers ::removeMageplazaGiftCard
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function removeMageplazaGiftCard_ifAccountModelThrowsException_notifiesBugSnag()
    {
        $this->initCurrentMock(['isMageplazaGiftCardAvailable']);
        $quote = $this->createMock(Quote::class);
        $codeId = 1232;
        $this->currentMock->expects(static::once())->method('isMageplazaGiftCardAvailable')->willReturn(true);


        $accountModelMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)->setMethods(
            [
                'getInstance',
                'load',
                'getCode',
                'getId',
            ]
        )->disableOriginalConstructor()->getMock();

        TestHelper::setProperty($this->currentMock, 'mageplazaGiftCardFactory', $accountModelMock);

        $accountModelMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $exception = $this->createMock(\Exception::class);
        $accountModelMock->expects(static::once())->method('load')->with($codeId)->willThrowException($exception);
        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception)->willReturnSelf();

        static::assertNull($this->currentMock->removeMageplazaGiftCard($codeId, $quote));
    }

    /**
     * @test
     * that removeMageplazaGiftCard removes the Mageplaza Gift Card and updates totals if
     * 1. Mageplaza Gift Card module is available
     * 2. Giftcard account is successfully loaded by provided id
     *
     * @covers ::removeMageplazaGiftCard
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function removeMageplazaGiftCard_ifAccountModelHasId_updatesTotals()
    {
        $this->initCurrentMock(['isMageplazaGiftCardAvailable']);

        $codeId = 1232;
        $quote = $this->createPartialMock(
            Quote::class,
            [
                'getShippingAddress',
                'setCollectShippingRates',
                'setTotalsCollectedFlag',
                'collectTotals',
                'setDataChanges',
                'setData',
            ]
        );
        $this->currentMock->expects(static::once())->method('isMageplazaGiftCardAvailable')->willReturn(true);

        $accountModelMock = $this->createPartialMock(
            ThirdPartyModuleFactory::class,
            [
                'getInstance',
                'load',
                'getCode',
                'getId',
            ]
        );

        TestHelper::setProperty($this->currentMock, 'mageplazaGiftCardFactory', $accountModelMock);

        $accountModelMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $accountModelMock->expects(static::once())->method('load')->with($codeId)->willReturnSelf();

        $giftCardMock = $this->createPartialMock(
            ThirdPartyModuleFactory::class,
            ['getGiftCardsData', 'getCheckoutSession']
        );
        TestHelper::setProperty($this->currentMock, 'sessionHelper', $giftCardMock);
        $checkoutSession = $this->createPartialMock(CheckoutSession::class, ['setGiftCardsData']);

        $giftCardMock->expects(static::exactly(2))->method('getCheckoutSession')
            ->willReturnOnConsecutiveCalls($giftCardMock, $checkoutSession);

        $code = 2323;
        $giftCardsData = [Discount::MAGEPLAZA_GIFTCARD_QUOTE_KEY => [$code => 22]];
        $giftCardMock->expects(static::once())->method('getGiftCardsData')->willReturn($giftCardsData);

        $accountModelMock->expects(static::once())->method('getCode')->willReturn($code);
        $id = 5;
        $accountModelMock->expects(static::once())->method('getId')->willReturn($id);
        unset($giftCardsData[Discount::MAGEPLAZA_GIFTCARD_QUOTE_KEY][$code]);
        $checkoutSession->expects(self::once())->method('setGiftCardsData')->with($giftCardsData)->willReturnSelf();

        $quote->expects(static::once())->method('setData')
            ->with(Discount::MAGEPLAZA_GIFTCARD_QUOTE_KEY, null)
            ->willReturnSelf();
        $quote->expects(static::once())->method('getShippingAddress')->willReturnSelf();
        $quote->expects(static::once())->method('setCollectShippingRates')->with(true)->willReturnSelf();
        $quote->expects(static::once())->method('setTotalsCollectedFlag')->with(false)->willReturnSelf();
        $quote->expects(static::once())->method('collectTotals')->willReturnSelf();
        $quote->expects(static::once())->method('setDataChanges')->with(true)->willReturnSelf();

        $this->currentMock->removeMageplazaGiftCard($codeId, $quote);
    }

    /**
     * @test
     * that applyMageplazaGiftCard returns null when the Mageplaza Gift Card coupon is unavailable
     *
     * @covers ::applyMageplazaGiftCard
     */
    public function applyMageplazaGiftCard_ifCardIsUnavailable_returnsNull()
    {
        $this->initCurrentMock(['isMageplazaGiftCardAvailable']);
        $quote = $this->createMock(Quote::class);
        $code = 1232;
        $this->currentMock->expects(static::once())->method('isMageplazaGiftCardAvailable')->willReturn(false);

        static::assertNull($this->currentMock->applyMageplazaGiftCard($code, $quote));
    }

    /**
     * @test
     * that applyMageplazaGiftCard applies Mageplaza Gift Card coupon to the provided quote if
     * 1. Mageplaza Gift Card module is available
     * 2. Mageplaza Gift Card is not present in totals
     *
     * @covers ::applyMageplazaGiftCard
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function applyMageplazaGiftCard_ifCardIsAvailable_returnsTotalsGiftCardValue()
    {
        $this->initCurrentMock(['isMageplazaGiftCardAvailable']);
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(
                [
                    'getTotals',
                    'getShippingAddress',
                    'setCollectShippingRates',
                    'setTotalsCollectedFlag',
                    'collectTotals',
                    'setDataChanges',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $code = 1232;
        $this->currentMock->expects(static::once())->method('isMageplazaGiftCardAvailable')->willReturn(true);

        $giftCardMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getGiftCardsData', 'getCheckoutSession'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'sessionHelper', $giftCardMock);
        $checkoutSession = $this->createPartialMock(CheckoutSession::class, ['setGiftCardsData']);
        $giftCardMock->expects(static::exactly(2))
            ->method('getCheckoutSession')
            ->willReturnOnConsecutiveCalls($giftCardMock, $checkoutSession);

        $giftCardData = [Discount::MAGEPLAZA_GIFTCARD_QUOTE_KEY => [$code => 0]];
        $giftCardMock->expects(static::once())
            ->method('getGiftCardsData')
            ->willReturn($giftCardData);

        $checkoutSession->expects(self::once())
            ->method('setGiftCardsData')
            ->with($giftCardData)
            ->willReturnSelf();

        $quote->expects(static::once())->method('getShippingAddress')->willReturnSelf();
        $quote->expects(static::once())->method('setCollectShippingRates')->with(true)->willReturnSelf();
        $quote->expects(static::once())->method('setTotalsCollectedFlag')->with(false)->willReturnSelf();
        $quote->expects(static::once())->method('collectTotals')->willReturnSelf();
        $quote->expects(static::once())->method('setDataChanges')->with(true)->willReturnSelf();
        $magePlazaMock = $this->createPartialMock(ThirdPartyModuleFactory::class, ['getValue']);

        $totals = [
            Discount::MAGEPLAZA_GIFTCARD => $magePlazaMock
        ];
        $quote->expects(self::once())->method('getTotals')->willReturn($totals);
        $mageplazaValue = 22;
        $magePlazaMock->expects(self::once())->method('getValue')->willReturn($mageplazaValue);

        static::assertEquals($mageplazaValue, $this->currentMock->applyMageplazaGiftCard($code, $quote));
    }

    /**
     * @test
     * that applyMageplazaGiftCard only notifies the Bugsnag when an exception is thrown when applying giftcard
     *
     * @covers ::applyMageplazaGiftCard
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function applyMageplazaGiftCard_throwsException_notifiesBugSnag()
    {
        $this->initCurrentMock(['isMageplazaGiftCardAvailable']);
        $quote = $this->createMock(Quote::class);

        $code = 1232;
        $this->currentMock->expects(static::once())->method('isMageplazaGiftCardAvailable')->willReturn(true);

        $giftCardMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getGiftCardsData', 'setGiftCardsData', 'getCheckoutSession'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'sessionHelper', $giftCardMock);
        $giftCardMock->expects(static::once())->method('getCheckoutSession')->willReturnSelf();
        $exception = $this->createMock(\Exception::class);

        $giftCardMock->expects(static::once())
            ->method('getGiftCardsData')
            ->willThrowException($exception);

        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception)->willReturnSelf();

        $this->currentMock->applyMageplazaGiftCard($code, $quote);
    }

    /**
     * @test
     * that applyMageplazaDiscountToQuote returns null when Mageplaza Gift Card module is unavailable
     *
     * @covers ::applyMageplazaDiscountToQuote
     */
    public function applyMageplazaDiscountToQuote_ifCardIsUnavailable_returnsNull()
    {
        $this->initCurrentMock(['isMageplazaGiftCardAvailable']);
        $quote = $this->createMock(Quote::class);
        $this->currentMock->expects(static::once())->method('isMageplazaGiftCardAvailable')->willReturn(false);

        static::assertNull($this->currentMock->applyMageplazaDiscountToQuote($quote));
    }

    /**
     * @test
     * that applyMageplazaDiscountToQuote first removes and then re-applies the Mageplaza Gift Card discount to the quote using
     * @see \Bolt\Boltpay\Helper\Discount::removeMageplazaGiftCard
     * @see \Bolt\Boltpay\Helper\Discount::applyMageplazaGiftCard
     *
     * @covers ::applyMageplazaDiscountToQuote
     */
    public function applyMageplazaDiscountToQuote_ifCardIsAvailable_appliesCardToQuote()
    {
        $this->initCurrentMock(
            [
                'isMageplazaGiftCardAvailable',
                'loadMageplazaGiftCard',
                'removeMageplazaGiftCard',
                'applyMageplazaGiftCard',
            ]
        );
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(['getData', 'getStoreId'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->currentMock->expects(static::once())->method('isMageplazaGiftCardAvailable')->willReturn(true);

        $mpGiftCardsArray = [
            [232 => 11],
            [132 => 15],
        ];
        $mpGiftCards = json_encode($mpGiftCardsArray);

        $quote->expects(static::once())
            ->method('getData')
            ->with(Discount::MAGEPLAZA_GIFTCARD_QUOTE_KEY)
            ->willReturn($mpGiftCards);

        $storeId = 11;
        $quote->expects(static::exactly(2))->method('getStoreId')
            ->willReturnOnConsecutiveCalls($storeId, $storeId);

        $giftCardMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getId', 'getCode'])
            ->disableOriginalConstructor()
            ->getMock();

        $giftCardMockTwo = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getId', 'getCode'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->currentMock->expects(static::exactly(2))
            ->method('loadMageplazaGiftCard')
            ->withConsecutive([array_keys($mpGiftCardsArray)[0]], [array_keys($mpGiftCardsArray)[1]])
            ->willReturnOnConsecutiveCalls($giftCardMock, $giftCardMockTwo);

        $giftCardMock->expects(self::exactly(2))->method('getId')->willReturnOnConsecutiveCalls(11, 11);
        $giftCardMockTwo->expects(self::exactly(2))->method('getId')->willReturnOnConsecutiveCalls(12, 12);
        $code = 323;
        $codeTwo = 4343;
        $giftCardMock->expects(self::once())->method('getCode')->willReturn($code);
        $giftCardMockTwo->expects(self::once())->method('getCode')->willReturn($codeTwo);

        $this->currentMock->expects(self::exactly(2))
            ->method('removeMageplazaGiftCard')
            ->withConsecutive([11, $quote], [12, $quote]);
        $this->currentMock->expects(self::exactly(2))
            ->method('applyMageplazaGiftCard')
            ->withConsecutive([$code], [$codeTwo]);

        $this->currentMock->applyMageplazaDiscountToQuote($quote);
    }

    /**
     * @test
     * that applyMageplazaDiscountToQuote notifies only Bugsnag if an exception is thrown during discount application
     *
     * @covers ::applyMageplazaDiscountToQuote
     */
    public function applyMageplazaDiscountToQuote_throwsException_notifiesBugSnag()
    {
        $this->initCurrentMock(['isMageplazaGiftCardAvailable', 'loadMageplazaGiftCard']);
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(['getData', 'getStoreId'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->currentMock->expects(static::once())->method('isMageplazaGiftCardAvailable')->willReturn(true);

        $exception = $this->createMock(\Exception::class);

        $quote->expects(static::once())->method('getData')->with(Discount::MAGEPLAZA_GIFTCARD_QUOTE_KEY)
            ->willThrowException($exception);

        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception)->willReturnSelf();

        $this->currentMock->applyMageplazaDiscountToQuote($quote);
    }

    /**
     * @test
     * that isAmastyRewardPointsAvailable returns the Amasty Reward Points module availability
     *
     * @covers ::isAmastyRewardPointsAvailable
     *
     * @dataProvider isAmastyRewardPointsAvailable_withVariousAmastyRewardAvailabilitiesProvider
     *
     * @param bool $amastyRewardsQuoteAvailability stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isAvailable}
     * @param bool $expectedResult of the method call
     */
    public function isAmastyRewardPointsAvailable_withVariousAmastyRewardAvailabilities_returnsAvailability(
        $amastyRewardsQuoteAvailability,
        $expectedResult
    ) {
        $this->initCurrentMock();
        $this->amastyRewardsQuote->expects(static::once())
            ->method('isAvailable')
            ->willReturn($amastyRewardsQuoteAvailability);

        static::assertEquals($expectedResult, $this->currentMock->isAmastyRewardPointsAvailable());
    }

    /**
     * Data provider for {@see isAmastyRewardPointsAvailable_withVariousAmastyRewardAvailabilities_returnsAvailability}
     *
     * @return array[] containing Amasty Reward Points module availability and expected result of the method call
     */
    public function isAmastyRewardPointsAvailable_withVariousAmastyRewardAvailabilitiesProvider()
    {
        return [
            ['amastyRewardsQuoteAvailability' => true, 'expectedResult' => true],
            ['amastyRewardsQuoteAvailability' => false, 'expectedResult' => false],
        ];
    }

    /**
     * @test
     * that setAmastyRewardPoints returns null if the Amasty Reward Points module is unavailable
     *
     * @covers ::setAmastyRewardPoints
     */
    public function setAmastyRewardPoints_ifCardIsUnavailable_returnsNull()
    {
        $this->initCurrentMock(['isAmastyRewardPointsAvailable']);
        $sourceQuote = $this->createMock(Quote::class);
        $this->currentMock->expects(static::once())->method('isAmastyRewardPointsAvailable')->willReturn(false);

        static::assertNull($this->currentMock->setAmastyRewardPoints($sourceQuote, null));
    }

    /**
     * @test
     * that setAmastyRewardPoints re-applies Amasty Reward Points to source quote if destination quote is not provided
     *
     * @covers ::setAmastyRewardPoints
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function setAmastyRewardPoints_ifDestinationIsNotProvided_setsSourceAsDestination()
    {
        $this->initCurrentMock(['isAmastyRewardPointsAvailable']);
        $sourceQuote = $this->getMockBuilder(Quote::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->currentMock->expects(static::once())->method('isAmastyRewardPointsAvailable')->willReturn(true);

        $amastyRewardsResourceQuote = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'loadByQuoteId', 'getUsedRewards'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyRewardsResourceQuote', $amastyRewardsResourceQuote);

        $amastyRewardsQuote = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'addReward'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyRewardsQuote', $amastyRewardsQuote);

        $amastyRewardsResourceQuote->expects(static::once())->method('getInstance')->willReturnSelf();
        $amastyRewardsQuote->expects(static::once())->method('getInstance')->willReturnSelf();

        $sourceId = 23;
        $sourceQuote->expects(static::once())->method('getId')->willReturn($sourceId);

        $amastyRewardsResourceQuote->expects(static::once())
            ->method('loadByQuoteId')
            ->with($sourceId)
            ->willReturn(null);

        $this->currentMock->setAmastyRewardPoints($sourceQuote, null);
    }

    /**
     * @test
     * that setAmastyRewardPoints copies the Amasty Reward Points data from the source quote to the destination quote if:
     * 1. Amasty Reward Points module is available
     * 2. the source quote has a linked Amasty quote
     *
     * @covers ::setAmastyRewardPoints
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function setAmastyRewardPoints_isSourceHasAmastyQuote_copiesPointsFromSourceToDestination()
    {
        $this->initCurrentMock(['isAmastyRewardPointsAvailable']);
        $sourceQuote = $this->getMockBuilder(Quote::class)
            ->setMethods(['getId', 'setAmrewardsPoint'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->currentMock->expects(static::once())->method('isAmastyRewardPointsAvailable')->willReturn(true);

        $amastyRewardsResourceQuote = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'loadByQuoteId', 'getUsedRewards'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyRewardsResourceQuote', $amastyRewardsResourceQuote);

        $amastyRewardsQuote = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'addReward'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyRewardsQuote', $amastyRewardsQuote);

        $amastyRewardsResourceQuote->expects(static::once())->method('getInstance')->willReturnSelf();
        $amastyRewardsQuote->expects(static::once())->method('getInstance')->willReturnSelf();

        $sourceId = 23;
        $sourceQuote->expects(static::exactly(3))
            ->method('getId')
            ->willReturnOnConsecutiveCalls($sourceId, $sourceId, $sourceId);

        $amastyRewardsResourceQuote->expects(static::once())
            ->method('loadByQuoteId')
            ->with($sourceId)
            ->willReturnSelf();

        $amastyRewardPoints = 55;
        $amastyRewardsResourceQuote->expects(static::once())
            ->method('getUsedRewards')
            ->with($sourceId)
            ->willReturn($amastyRewardPoints);

        $amastyRewardsQuote->expects(static::once())
            ->method('addReward')
            ->with($sourceId, $amastyRewardPoints)
            ->willReturnSelf();

        $sourceQuote->expects(static::once())->method('setAmrewardsPoint')->with($amastyRewardPoints)->willReturnSelf();

        $this->currentMock->setAmastyRewardPoints($sourceQuote, null);
    }

    /**
     * @test
     * that setAmastyRewardPoints sets the provided source quote rewards data
     * onto provided destination quote rewards data if all the following requirements are met:
     * 1. Amasty Reward Points module is available
     * 2. a destination quote is provided
     * 3. the source has Amasty quote
     *
     * @covers ::setAmastyRewardPoints
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function setAmastyRewardPoints_ifDestinationHasAmastyQuote_destinationSetsOwnProperties()
    {
        $this->initCurrentMock(['isAmastyRewardPointsAvailable']);
        $sourceQuote = $this->getMockBuilder(Quote::class)
            ->setMethods(['getId', 'setAmrewardsPoint'])
            ->disableOriginalConstructor()
            ->getMock();

        $destinationQuote = $this->getMockBuilder(Quote::class)
            ->setMethods(['getId', 'setAmrewardsPoint'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->currentMock->expects(static::once())->method('isAmastyRewardPointsAvailable')->willReturn(true);

        $amastyRewardsResourceQuote = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'loadByQuoteId', 'getUsedRewards'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyRewardsResourceQuote', $amastyRewardsResourceQuote);

        $amastyRewardsQuote = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'addReward'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'amastyRewardsQuote', $amastyRewardsQuote);

        $amastyRewardsResourceQuote->expects(static::once())->method('getInstance')->willReturnSelf();
        $amastyRewardsQuote->expects(static::once())->method('getInstance')->willReturnSelf();

        $sourceId = 23;
        $sourceQuote->expects(static::exactly(2))
            ->method('getId')
            ->willReturnOnConsecutiveCalls($sourceId, $sourceId);

        $destinationId = 23;
        $destinationQuote->expects(static::once())
            ->method('getId')
            ->willReturn($destinationId);

        $amastyRewardsResourceQuote->expects(static::once())
            ->method('loadByQuoteId')
            ->with($sourceId)
            ->willReturnSelf();

        $amastyRewardPoints = 55;
        $amastyRewardsResourceQuote->expects(static::once())
            ->method('getUsedRewards')
            ->with($sourceId)
            ->willReturn($amastyRewardPoints);

        $amastyRewardsQuote->expects(static::once())
            ->method('addReward')
            ->with($sourceId, $amastyRewardPoints)
            ->willReturnSelf();

        $destinationQuote->expects(static::once())
            ->method('setAmrewardsPoint')
            ->with($amastyRewardPoints)
            ->willReturnSelf();

        $this->currentMock->setAmastyRewardPoints($sourceQuote, $destinationQuote);
    }

    /**
     * @test
     * that deleteRedundantAmastyRewardPoints doesn't delete redunt Amasty Reward Points if the module is unavailable
     *
     * @covers ::deleteRedundantAmastyRewardPoints
     */
    public function deleteRedundantAmastyRewardPoints_ifRewardPointsIsUnavailable_doesNotDelete()
    {
        $this->initCurrentMock(['isAmastyRewardPointsAvailable']);
        $quote = $this->createMock(Quote::class);
        $this->currentMock->expects(static::once())->method('isAmastyRewardPointsAvailable')->willReturn(false);

        static::assertNull($this->currentMock->deleteRedundantAmastyRewardPoints($quote));
    }

    /**
     * @test
     * that deleteRedundantAmastyRewardPoints deletes redundant Amasty Reward Points related to provided quote
     * by executing a custom SQL query
     *
     * @covers ::deleteRedundantAmastyRewardPoints
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function deleteRedundantAmastyRewardPoints_ifAmastyRewardPointsIsAvailable_deletesRedundantAmastyRewardPoints()
    {
        $this->initCurrentMock(['isAmastyRewardPointsAvailable']);
        $this->currentMock->expects(static::once())->method('isAmastyRewardPointsAvailable')->willReturn(true);
        TestHelper::setProperty($this->currentMock, 'resource', $this->connectionMock);

        $this->connectionMock->method('getConnection')->willReturn($this->connectionMock);
        $testTableName = 'quotes';
        $giftCardTable = 'test_table';
        $this->connectionMock->expects(static::exactly(2))
            ->method('getTableName')
            ->withConsecutive(['amasty_rewards_quote'], ['quote'])
            ->willReturnOnConsecutiveCalls($giftCardTable, $testTableName);

        $quoteId = 11;

        $quoteMock = $this->createPartialMock(Quote::class, ['getId', 'getBoltParentQuoteId']);

        $quoteMock->expects(static::exactly(2))
            ->method('getBoltParentQuoteId')
            ->willReturnOnConsecutiveCalls($quoteId, $quoteId);

        $sql = "DELETE FROM {$giftCardTable} WHERE quote_id IN
                    (SELECT entity_id FROM {$testTableName}
                    WHERE bolt_parent_quote_id = :bolt_parent_quote_id AND entity_id != :entity_id)";

        $bind = [
            'bolt_parent_quote_id' => $quoteId,
            'entity_id'            => $quoteId
        ];

        $this->connectionMock->expects(static::once())->method('query')->with($sql, $bind)->willReturnSelf();

        $this->currentMock->deleteRedundantAmastyRewardPoints($quoteMock);
    }

    /**
     * @test
     * that deleteRedundantAmastyRewardPoints only notifies Bugsnag if an exception occurs
     * during the deletion query execution
     *
     * @covers ::deleteRedundantAmastyRewardPoints
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function deleteRedundantAmastyRewardPoints_sqlThrowsException_notifiesBugSnag()
    {
        $this->initCurrentMock(['isAmastyRewardPointsAvailable']);
        $this->currentMock->expects(static::once())->method('isAmastyRewardPointsAvailable')->willReturn(true);
        TestHelper::setProperty($this->currentMock, 'resource', $this->connectionMock);
        $this->connectionMock->method('getConnection')->willReturn($this->connectionMock);
        $testTableName = 'quotes';
        $giftCardTable = 'test_table';
        $this->connectionMock->expects(static::exactly(2))
            ->method('getTableName')
            ->withConsecutive(['amasty_rewards_quote'], ['quote'])
            ->willReturnOnConsecutiveCalls($giftCardTable, $testTableName);

        $quoteId = 11;

        $quoteMock = $this->createPartialMock(Quote::class, ['getId', 'getBoltParentQuoteId']);

        $quoteMock->expects(static::exactly(2))
            ->method('getBoltParentQuoteId')
            ->willReturnOnConsecutiveCalls($quoteId, $quoteId);

        $sql = "DELETE FROM {$giftCardTable} WHERE quote_id IN
                    (SELECT entity_id FROM {$testTableName}
                    WHERE bolt_parent_quote_id = :bolt_parent_quote_id AND entity_id != :entity_id)";

        $bind = [
            'bolt_parent_quote_id' => $quoteId,
            'entity_id'            => $quoteId
        ];
        $exception = $this->createMock(Zend_Db_Statement_Exception::class);
        $this->connectionMock->expects(static::once())->method('query')->with($sql, $bind)->willThrowException(
            $exception
        );

        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception)->willReturnSelf();

        $this->currentMock->deleteRedundantAmastyRewardPoints($quoteMock);
    }

    /**
     * @test
     * that clearAmastyRewardPoints doesn't clear Amasty Reward Points for the provided quote it the module
     * is not unavailable
     *
     * @covers ::clearAmastyRewardPoints
     */
    public function clearAmastyRewardPoints_ifRewardPointsIsUnavailable_doesNotClear()
    {
        $this->initCurrentMock(['isAmastyRewardPointsAvailable']);
        $quote = $this->createMock(Quote::class);
        $this->currentMock->expects(static::once())->method('isAmastyRewardPointsAvailable')->willReturn(false);

        static::assertNull($this->currentMock->clearAmastyRewardPoints($quote));
    }

    /**
     * @test
     * that clearAmastyRewardPoints deletes reward points related to provided quote by executing a custom SQL query
     *
     * @covers ::clearAmastyRewardPoints
     *
     * @throws ReflectionException if unable to set mocked internal properties
     */
    public function clearAmastyRewardPoints_ifRewardPointsIsUnavailable_executesQuery()
    {
        $this->initCurrentMock(['isAmastyRewardPointsAvailable']);
        $this->currentMock->expects(static::once())->method('isAmastyRewardPointsAvailable')->willReturn(true);
        TestHelper::setProperty($this->currentMock, 'resource', $this->connectionMock);
        $this->connectionMock->method('getConnection')->willReturn($this->connectionMock);
        $rewardsTable = 'rewards';
        $this->connectionMock->expects(static::once())
            ->method('getTableName')
            ->with('amasty_rewards_quote')
            ->willReturn($rewardsTable);

        $quoteId = 11;

        $quoteMock = $this->createPartialMock(Quote::class, ['getId']);
        $quoteMock->expects(static::once())->method('getId')->willReturn($quoteId);

        $sql = "DELETE FROM {$rewardsTable} WHERE quote_id = :quote_id";

        $bind = ['quote_id' => $quoteId];

        $this->connectionMock->expects(static::once())->method('query')->with($sql, $bind)->willReturnSelf();

        $this->currentMock->clearAmastyRewardPoints($quoteMock);
    }

    /**
     * @test
     * that clearAmastyRewardPoints only notifies Bugsnag if an exception is thrown
     * during the rewards table clearing query execution
     *
     * @covers ::clearAmastyRewardPoints
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function clearAmastyRewardPoints_onException_notifiesBugSnag()
    {
        $this->initCurrentMock(['isAmastyRewardPointsAvailable']);
        $this->currentMock->expects(static::once())->method('isAmastyRewardPointsAvailable')->willReturn(true);
        TestHelper::setProperty($this->currentMock, 'resource', $this->connectionMock);
        $this->connectionMock->method('getConnection')->willReturn($this->connectionMock);
        $rewardsTable = 'rewards';
        $this->connectionMock->expects(static::once())
            ->method('getTableName')
            ->with('amasty_rewards_quote')
            ->willReturn($rewardsTable);

        $quoteId = 11;

        $quoteMock = $this->createPartialMock(Quote::class, ['getId']);
        $quoteMock->expects(static::once())->method('getId')->willReturn($quoteId);

        $sql = "DELETE FROM {$rewardsTable} WHERE quote_id = :quote_id";

        $bind = ['quote_id' => $quoteId];

        $exception = $this->createMock(Zend_Db_Statement_Exception::class);

        $this->connectionMock->expects(static::once())
            ->method('query')
            ->with($sql, $bind)
            ->willThrowException($exception);

        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception)->willReturnSelf();

        $this->currentMock->clearAmastyRewardPoints($quoteMock);
    }

    /**
     * @test
     * that isAheadworksStoreCreditAvailable returns availability of Aheadworks Store Credit module
     *
     * @covers ::isAheadworksStoreCreditAvailable
     *
     * @dataProvider isAheadworksStoreCreditAvailable_withVariousAheadWorksAvailabilitiesProvider
     *
     * @param bool $aheadworksStoreCreditAvailable stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isAvailable}
     * @param bool $expectedResult of the method call
     */
    public function isAheadworksStoreCreditAvailable_withVariousAheadWorksAvailabilities_returnsAvailability(
        $aheadworksStoreCreditAvailable,
        $expectedResult
    ) {
        $this->initCurrentMock();
        $this->aheadworksCustomerStoreCreditManagement->expects(static::once())
            ->method('isAvailable')
            ->willReturn($aheadworksStoreCreditAvailable);

        static::assertEquals($expectedResult, $this->currentMock->isAheadworksStoreCreditAvailable());
    }

    /**
     * Data provider for {@see isAheadworksStoreCreditAvailable_withVariousAheadWorksAvailabilities_returnsAvailability}
     *
     * @return array[] containing Aheadworks Store Credit module availability and expected result of the method call
     */
    public function isAheadworksStoreCreditAvailable_withVariousAheadWorksAvailabilitiesProvider()
    {
        return [
            ['aheadworksStoreCreditAvailable' => true, 'expectedResult' => true],
            ['aheadworksStoreCreditAvailable' => false, 'expectedResult' => false],
        ];
    }

    /**
     * @test
     * that getAheadworksStoreCredit returns Aheadworks user store credit when Aheadworks store credit is available
     *
     * @covers ::getAheadworksStoreCredit
     *
     * @dataProvider getAheadworksStoreCredit_withVariousAheadworksStoreCreditAvailabilitiesProvider
     *
     * @param bool  $AheadworksStoreCreditAvailable stubbed result of {@see \Bolt\Boltpay\Helper\Discount::isAheadworksStoreCreditAvailable}
     * @param float $expectedResult of the tested method call
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function getAheadworksStoreCredit_withVariousAheadworksStoreCreditAvailabilities_returnsUserStoreCredit(
        $AheadworksStoreCreditAvailable,
        $expectedResult
    ) {
        $this->initCurrentMock(['isAheadworksStoreCreditAvailable']);
        $customerId = 87;

        $this->currentMock->expects(static::once())
            ->method('isAheadworksStoreCreditAvailable')
            ->willReturn($AheadworksStoreCreditAvailable);

        $aheadworksCustomerMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'getCustomerStoreCreditBalance'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'aheadworksCustomerStoreCreditManagement', $aheadworksCustomerMock);
        $aheadworksCustomerMock->expects($AheadworksStoreCreditAvailable ? static::once() : static::never())
            ->method('getInstance')
            ->willReturnSelf();
        $aheadworksCustomerMock->expects($AheadworksStoreCreditAvailable ? static::once() : static::never())
            ->method('getCustomerStoreCreditBalance')
            ->with($customerId)
            ->willReturn($expectedResult);

        static::assertEquals($expectedResult, $this->currentMock->getAheadworksStoreCredit($customerId));
    }

    /**
     * Data provider for
     * @see getAheadworksStoreCredit_withVariousAheadworksStoreCreditAvailabilities_returnsUserStoreCredit
     *
     * @return array[] containing Aheadwork Store Credit flag and expected result of the tested method
     */
    public function getAheadworksStoreCredit_withVariousAheadworksStoreCreditAvailabilitiesProvider()
    {
        return [
            ["AheadworksStoreCreditAvailable" => true, "expectedResult" => 595.3],
            ["AheadworksStoreCreditAvailable" => false, "expectedResult" => 0],
        ];
    }

    /**
     * @test
     * that applyExternalDiscountData sets the Amasty reward points and applies the Miravist reward points
     * by calling {@see \Bolt\Boltpay\Helper\Discount::setAmastyRewardPoints} and
     * {@see \Bolt\Boltpay\Helper\Discount::applyMiravistRewardPoint}
     *
     * @covers ::applyExternalDiscountData
     */
    public function applyExternalDiscountData_always_appliesRewardPoints()
    {
        $this->initCurrentMock(['setAmastyRewardPoints', 'applyMiravistRewardPoint']);
        $quote = $this->createMock(Quote::class);

        $this->currentMock->expects(static::once())->method('setAmastyRewardPoints')->with($quote)->willReturnSelf();
        $this->currentMock->expects(static::once())->method('applyMiravistRewardPoint')->with($quote)->willReturnSelf();

        $this->currentMock->applyExternalDiscountData($quote);
    }

    /**
     * @test
     * that applyMiravistRewardPoint returns null when the Mirasvit Rewards Purchase module is not available
     *
     * @covers ::applyMiravistRewardPoint
     */
    public function applyMiravistRewardPoint_whenMirasvitRewardsPurchaseHelperIsNotSet_returnsNull()
    {
        $this->initCurrentMock();
        $immutableQuote = $this->getMockBuilder(Quote::class)
            ->setMethods(['getBoltParentQuoteId'])
            ->disableOriginalConstructor()
            ->getMock();
        $immutableQuote->expects(static::once())->method('getBoltParentQuoteId')->willReturnSelf();
        $this->mirasvitRewardsPurchaseHelper->expects(static::once())->method('getInstance')->willReturn(null);

        static::assertNull($this->currentMock->applyMiravistRewardPoint($immutableQuote));
    }

    /**
     * @test
     * that applyMiravistRewardPoint sets the points if the parent quote rewards spend amount is greater than zero
     *
     * @covers ::applyMiravistRewardPoint
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function applyMiravistRewardPoint_whenPurchaseHelperIsAvailable_savesRewards()
    {
        $this->initCurrentMock();
        $immutableQuote = $this->getMockBuilder(Quote::class)
            ->setMethods(['getBoltParentQuoteId'])
            ->disableOriginalConstructor()
            ->getMock();

        $parentQuoteId = 4;
        $immutableQuote->expects(static::once())->method('getBoltParentQuoteId')->willReturn($parentQuoteId);
        $mirasvitRewardsMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(
                [
                    'getInstance',
                    'getByQuote',
                    'getSpendPoints',
                    'setSpendPoints',
                    'getSpendMinAmount',
                    'setSpendMinAmount',
                    'getSpendMaxAmount',
                    'setSpendMaxAmount',
                    'getSpendAmount',
                    'setSpendAmount',
                    'getBaseSpendAmount',
                    'setBaseSpendAmount',
                    'save',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'mirasvitRewardsPurchaseHelper', $mirasvitRewardsMock);

        $mirasvitRewardsMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $parentPurchase = 423;
        $mirasvitRewardsMock->expects(static::exactly(2))
            ->method('getByQuote')
            ->withConsecutive([$parentQuoteId], [$immutableQuote])
            ->willReturnSelf();

        $spendAmount = 77;
        $mirasvitRewardsMock->expects(static::exactly(2))
            ->method('getSpendAmount')
            ->willReturnOnConsecutiveCalls($parentPurchase, $spendAmount);

        $spendPoints = 132;
        $mirasvitRewardsMock->expects(static::once())->method('getSpendPoints')->willReturn($spendPoints);
        $mirasvitRewardsMock->expects(static::once())->method('setSpendPoints')->willReturnSelf();

        $spendMinAmount = 23;
        $mirasvitRewardsMock->expects(static::once())->method('getSpendMinAmount')->willReturn($spendMinAmount);
        $mirasvitRewardsMock->expects(static::once())->method('setSpendMinAmount')->willReturnSelf();

        $spendMaxAmount = 83;
        $mirasvitRewardsMock->expects(static::once())->method('getSpendMaxAmount')->willReturn($spendMaxAmount);
        $mirasvitRewardsMock->expects(static::once())->method('setSpendMaxAmount')->willReturnSelf();

        $mirasvitRewardsMock->expects(static::once())->method('setSpendAmount')->willReturnSelf();

        $baseSpendAmount = 79;
        $mirasvitRewardsMock->expects(static::once())->method('getBaseSpendAmount')->willReturn($baseSpendAmount);
        $mirasvitRewardsMock->expects(static::once())->method('setBaseSpendAmount')->willReturnSelf();

        $mirasvitRewardsMock->expects(static::once())->method('save')->willReturnSelf();

        static::assertNull($this->currentMock->applyMiravistRewardPoint($immutableQuote));
    }

    /**
     * @test
     * that applyMiravistRewardPoint doesn't apply the reward points if parent quote rewards spend amount is below zero
     *
     * @covers ::applyMiravistRewardPoint
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function applyMiravistRewardPoint_ifSpendAmountIsZero_setsParentPurchase()
    {
        $this->initCurrentMock();
        $immutableQuote = $this->getMockBuilder(Quote::class)
            ->setMethods(['getBoltParentQuoteId'])
            ->disableOriginalConstructor()
            ->getMock();

        $parentQuoteId = 4;
        $immutableQuote->expects(static::once())->method('getBoltParentQuoteId')->willReturn($parentQuoteId);
        $mirasvitRewardsMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'getByQuote', 'getSpendAmount'])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'mirasvitRewardsPurchaseHelper', $mirasvitRewardsMock);

        $mirasvitRewardsMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $parentPurchase = 0;
        $mirasvitRewardsMock->expects(static::once())
            ->method('getByQuote')
            ->with($parentQuoteId)
            ->willReturnSelf();

        $mirasvitRewardsMock->expects(static::once())->method('getSpendAmount')->willReturn($parentPurchase);

        $this->currentMock->applyMiravistRewardPoint($immutableQuote);
    }

    /**
     * @test
     * that applyMiravistRewardPoint only notifies Bugsnag if an exception is thrown during points application
     *
     * @covers ::applyMiravistRewardPoint
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function applyMiravistRewardPoint_throwsException_notifiesBugSnag()
    {
        $this->initCurrentMock();
        $immutableQuote = $this->getMockBuilder(Quote::class)
            ->setMethods(['getBoltParentQuoteId'])
            ->disableOriginalConstructor()
            ->getMock();

        $parentQuoteId = 4;
        $immutableQuote->expects(static::once())->method('getBoltParentQuoteId')->willReturn($parentQuoteId);
        $mirasvitRewardsMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(
                [
                    'getInstance',
                    'getByQuote',
                    'getSpendPoints',
                    'setSpendPoints',
                    'getSpendMinAmount',
                    'setSpendMinAmount',
                    'getSpendMaxAmount',
                    'setSpendMaxAmount',
                    'getSpendAmount',
                    'setSpendAmount',
                    'getBaseSpendAmount',
                    'setBaseSpendAmount',
                    'save',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::setProperty($this->currentMock, 'mirasvitRewardsPurchaseHelper', $mirasvitRewardsMock);

        $mirasvitRewardsMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $parentPurchase = 423;
        $mirasvitRewardsMock->expects(static::exactly(2))
            ->method('getByQuote')
            ->withConsecutive([$parentQuoteId], [$immutableQuote])
            ->willReturnSelf();

        $spendAmount = 77;
        $mirasvitRewardsMock->expects(static::exactly(2))
            ->method('getSpendAmount')
            ->willReturnOnConsecutiveCalls($parentPurchase, $spendAmount);

        $spendPoints = 132;
        $mirasvitRewardsMock->expects(static::once())->method('getSpendPoints')->willReturn($spendPoints);
        $mirasvitRewardsMock->expects(static::once())->method('setSpendPoints')->willReturnSelf();

        $spendMinAmount = 23;
        $mirasvitRewardsMock->expects(static::once())->method('getSpendMinAmount')->willReturn($spendMinAmount);
        $mirasvitRewardsMock->expects(static::once())->method('setSpendMinAmount')->willReturnSelf();

        $spendMaxAmount = 83;
        $mirasvitRewardsMock->expects(static::once())->method('getSpendMaxAmount')->willReturn($spendMaxAmount);
        $mirasvitRewardsMock->expects(static::once())->method('setSpendMaxAmount')->willReturnSelf();

        $mirasvitRewardsMock->expects(static::once())->method('setSpendAmount')->willReturnSelf();

        $baseSpendAmount = 79;
        $mirasvitRewardsMock->expects(static::once())->method('getBaseSpendAmount')->willReturn($baseSpendAmount);
        $mirasvitRewardsMock->expects(static::once())->method('setBaseSpendAmount')->willReturnSelf();

        $exception = $this->createMock(\Exception::class);
        $mirasvitRewardsMock->expects(static::once())->method('save')->willThrowException($exception);

        $this->bugsnag->expects(static::once())->method('notifyException')->with($exception)->willReturnSelf();

        static::assertNull($this->currentMock->applyMiravistRewardPoint($immutableQuote));
    }
}