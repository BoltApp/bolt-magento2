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
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Unirgy Giftcert helper
     */
    private $unirgyGiftCertHelper;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Amasty Rewards resource quote
     */
    private $amastyRewardsResourceQuote;

    /**
     * @var MockObject|ThirdPartyModuleFactory mocked instance of the Amasty Rewards quote
     */
    private $amastyRewardsQuote;

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
     * @var MockObject|ThirdPartyModuleFactory
     */
    private $moduleGiftCardAccountMock;

    /**
     * @var MockObject|ThirdPartyModuleFactory
     */
    private $moduleGiftCardAccountHelperMock;

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
     * @var ThirdPartyModuleFactory|MockObject
     * mocked instance of the Quote Extension Attribute repository added by Amasty Giftcard
     */
    private $amastyGiftCardAccountQuoteExtensionRepositoryFactory;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp(): void
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
        $this->amastyGiftCardAccountQuoteExtensionRepositoryFactory = $this->createMock(ThirdPartyModuleFactory::class);
        $this->unirgyCertRepository = $this->createMock(ThirdPartyModuleFactory::class);
        $this->unirgyGiftCertHelper = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyRewardsResourceQuote = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyRewardsQuote = $this->createMock(ThirdPartyModuleFactory::class);
        $this->moduleGiftCardAccountMock = $this->createMock(ThirdPartyModuleFactory::class);
        $this->moduleGiftCardAccountHelperMock = $this->createMock(ThirdPartyModuleFactory::class);
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
                    $this->amastyLegacyAccountFactory,
                    $this->amastyLegacyGiftCardManagement,
                    $this->amastyLegacyQuoteFactory,
                    $this->amastyLegacyQuoteResource,
                    $this->amastyLegacyQuoteRepository,
                    $this->amastyLegacyAccountCollection,
                    $this->amastyAccountFactory,
                    $this->amastyGiftCardAccountManagement,
                    $this->amastyGiftCardAccountCollection,
                    $this->amastyGiftCardAccountQuoteExtensionRepositoryFactory,
                    $this->unirgyCertRepository,
                    $this->unirgyGiftCertHelper,
                    $this->amastyRewardsResourceQuote,
                    $this->amastyRewardsQuote,
                    $this->moduleGiftCardAccountMock,
                    $this->moduleGiftCardAccountHelperMock,
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
            $this->amastyLegacyAccountFactory,
            $this->amastyLegacyGiftCardManagement,
            $this->amastyLegacyQuoteFactory,
            $this->amastyLegacyQuoteResource,
            $this->amastyLegacyQuoteRepository,
            $this->amastyLegacyAccountCollection,
            $this->amastyAccountFactory,
            $this->amastyGiftCardAccountManagement,
            $this->amastyGiftCardAccountCollection,
            $this->amastyGiftCardAccountQuoteExtensionRepositoryFactory,
            $this->unirgyCertRepository,
            $this->unirgyGiftCertHelper,
            $this->amastyRewardsResourceQuote,
            $this->amastyRewardsQuote,
            $this->moduleGiftCardAccountMock,
            $this->moduleGiftCardAccountHelperMock,
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
        static::assertAttributeEquals($this->unirgyGiftCertHelper, 'unirgyGiftCertHelper', $instance);
        static::assertAttributeEquals($this->amastyRewardsResourceQuote, 'amastyRewardsResourceQuote', $instance);
        static::assertAttributeEquals($this->amastyRewardsQuote, 'amastyRewardsQuote', $instance);
        static::assertAttributeEquals($this->moduleGiftCardAccountMock, 'moduleGiftCardAccount', $instance);
        static::assertAttributeEquals($this->moduleGiftCardAccountHelperMock, 'moduleGiftCardAccountHelper', $instance);
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
        $this->currentMock->applyExternalDiscountData($quote);
    }

    /**
     * @test
     * that isMagentoGiftCardAccountAvailable returns availability of Magento Gift Card module
     *
     * @covers ::isMagentoGiftCardAccountAvailable
     *
     * @dataProvider isMagentoGiftCardAccountAvailable_withVariousMagentoGiftCardAccountAvailabilitiesProvider
     *
     * @param bool $magentoGiftCardAccountAvailable stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isAvailable}
     * @param bool $expectedResult of the method call
     */
    public function isMagentoGiftCardAccountAvailable_withVariousMagentoGiftCardAccountAvailabilities_returnsAvailability(
        $magentoGiftCardAccountAvailable,
        $expectedResult
    ) {
        $this->initCurrentMock();
        $this->moduleGiftCardAccountMock->expects(static::once())
            ->method('isAvailable')
            ->willReturn($magentoGiftCardAccountAvailable);

        static::assertEquals($expectedResult, $this->currentMock->isMagentoGiftCardAccountAvailable());
    }

    /**
     * Data provider for {@see isMagentoGiftCardAccountAvailable_withVariousMagentoGiftCardAccountAvailabilities_returnsAvailability}
     *
     * @return array[] containing Magento Gift Card module availability and expected result of the method call
     */
    public function isMagentoGiftCardAccountAvailable_withVariousMagentoGiftCardAccountAvailabilitiesProvider()
    {
        return [
            ['magentoGiftCardAccountAvailable' => true, 'expectedResult' => true],
            ['magentoGiftCardAccountAvailable' => false, 'expectedResult' => false],
        ];
    }

    /**
     * @test
     * that loadMagentoGiftCardAccount returns null if the Magento Gift Card is unavailable
     *
     * @covers ::loadMagentoGiftCardAccount
     */
    public function loadMagentoGiftCardAccount_cardIsUnavailable_returnsNull()
    {
        $this->initCurrentMock(['isMagentoGiftCardAccountAvailable']);
        $code = 'testCouponCode';
        $storeId = 23;
        $this->currentMock->method('isMagentoGiftCardAccountAvailable')->willReturn(false);

        static::assertNull($this->currentMock->loadMagentoGiftCardAccount($code, $storeId));
    }

    /**
     * @test
     * that loadMagentoGiftCardAccount returns null if the Magento Gift Card is unavailable
     *
     * @covers ::loadMagentoGiftCardAccount
     */
    public function loadMagentoGiftCardAccount_intanceNull_returnsNull()
    {
        $this->initCurrentMock(['isMagentoGiftCardAccountAvailable']);
        $code = 'testCouponCode';
        $storeId = 23;
        $this->currentMock->method('isMagentoGiftCardAccountAvailable')->willReturn(true);

        $this->moduleGiftCardAccountMock->expects(static::once())
            ->method('getInstance')
            ->willReturn(null);

        static::assertNull($this->currentMock->loadMagentoGiftCardAccount($code, $storeId));
    }

    /**
     * @test
     * that loadMagentoGiftCardAccount returns \Magento\GiftCardAccount\Model\Giftcardaccount object
     * if one is available for the provided giftcard code and website id
     *
     * @covers ::loadMagentoGiftCardAccount
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function loadMagentoGiftCardAccount_withMagentoGiftCardAvailableForWebsite_returnsAmastyGiftCardAccountModel()
    {
        $this->initCurrentMock(['isMagentoGiftCardAccountAvailable']);
        $couponCode = 'testCouponCode';
        $websiteId = 1111;
        $this->currentMock->expects(static::once())->method('isMagentoGiftCardAccountAvailable')->willReturn(true);

        $accountModelMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)->setMethods(
            [
                'getInstance',
                'addFieldToFilter',
                'addWebsiteFilter',
                'getFirstItem',
            ]
        )->disableOriginalConstructor()->getMock();

        TestHelper::setProperty($this->currentMock, 'moduleGiftCardAccount', $accountModelMock);

        $accountModelMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $accountModelMock->expects(static::once())->method('addFieldToFilter')->with('code', ['eq' => $couponCode])->willReturnSelf();
        $accountModelMock->expects(static::once())->method('addWebsiteFilter')->with([0, $websiteId])->willReturnSelf();

        $giftcardMock = $this->getMockBuilder('\Magento\GiftCardAccount\Model\Giftcardaccount')
            ->disableOriginalConstructor()
            ->setMethods(['isEmpty', 'isValid'])
            ->getMock();
        $giftcardMock->method('isEmpty')->willReturn(false);
        $giftcardMock->method('isValid')->willReturn(true);

        $accountModelMock->expects(self::once())->method('getFirstItem')
            ->willReturn($giftcardMock);

        static::assertEquals($giftcardMock, $this->currentMock->loadMagentoGiftCardAccount($couponCode, $websiteId));
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

    /**
     * @test
     * that getMagentoGiftCardAccountGiftCardData returns empty array if the MagentoGiftCardAccount is unavailable
     *
     * @covers ::getMagentoGiftCardAccountGiftCardData
     *
     */
    public function getMagentoGiftCardAccountGiftCardData_MagentoGiftCardAccountUnAvailable_returnEmptyArray()
    {
        $this->initCurrentMock(['isMagentoGiftCardAccountAvailable']);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->currentMock->method('isMagentoGiftCardAccountAvailable')->willReturn(false);

        static::assertEquals([], $this->currentMock->getMagentoGiftCardAccountGiftCardData($quote));
    }

    /**
     * @test
     * that getMagentoGiftCardAccountGiftCardData returns empty array if the GiftCardAccountHelper is unavailable
     *
     * @covers ::getMagentoGiftCardAccountGiftCardData
     *
     */
    public function getMagentoGiftCardAccountGiftCardData_GiftCardAccountHelperUnAvailable_returnEmptyArray()
    {
        $this->initCurrentMock(['isMagentoGiftCardAccountAvailable']);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->currentMock->method('isMagentoGiftCardAccountAvailable')->willReturn(true);
        $this->moduleGiftCardAccountHelperMock->expects(static::once())->method('getInstance')->willReturn(null);

        static::assertEquals([], $this->currentMock->getMagentoGiftCardAccountGiftCardData($quote));
    }

    /**
     * @test
     * that getMagentoGiftCardAccountGiftCardData returns empty array if the no gift card in the quote
     *
     * @covers ::getMagentoGiftCardAccountGiftCardData
     *
     */
    public function getMagentoGiftCardAccountGiftCardData_NoGiftCardInQuote_returnEmptyArray()
    {
        $this->initCurrentMock(['isMagentoGiftCardAccountAvailable']);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->currentMock->method('isMagentoGiftCardAccountAvailable')->willReturn(true);

        $moduleGiftCardAccountHelperMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'getCards'])
            ->disableOriginalConstructor()
            ->getMock();
        $moduleGiftCardAccountHelperMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $moduleGiftCardAccountHelperMock->expects(static::once())->method('getCards')->with($quote)->willReturn([]);
        TestHelper::setProperty($this->currentMock, 'moduleGiftCardAccountHelper', $moduleGiftCardAccountHelperMock);

        static::assertEquals([], $this->currentMock->getMagentoGiftCardAccountGiftCardData($quote));
    }

    /**
     * @test
     * that getMagentoGiftCardAccountGiftCardData returns gift card array
     *
     * @covers ::getMagentoGiftCardAccountGiftCardData
     *
     */
    public function getMagentoGiftCardAccountGiftCardData_returnGiftCardArray()
    {
        $this->initCurrentMock(['isMagentoGiftCardAccountAvailable']);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->currentMock->method('isMagentoGiftCardAccountAvailable')->willReturn(true);

        $cards = [
            [
                'a' => 50,
                'c' => '1234'
            ],
            [
                'a' => 50,
                'c' => '5678'
            ]
        ];
        $moduleGiftCardAccountHelperMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setMethods(['getInstance', 'getCards'])
            ->disableOriginalConstructor()
            ->getMock();
        $moduleGiftCardAccountHelperMock->expects(static::once())->method('getInstance')->willReturnSelf();
        $moduleGiftCardAccountHelperMock->expects(static::once())->method('getCards')->with($quote)->willReturn($cards);
        TestHelper::setProperty($this->currentMock, 'moduleGiftCardAccountHelper', $moduleGiftCardAccountHelperMock);

        static::assertEquals(['1234'=>50, '5678'=>50], $this->currentMock->getMagentoGiftCardAccountGiftCardData($quote));
    }
}