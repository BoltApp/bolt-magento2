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

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Session;
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

/**
 * Class DiscountTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper
 */
class DiscountTest extends TestCase
{

    /**
     * @var Quote
     */
    private $quote;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $amastyAccountFactory;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $amastyGiftCardManagement;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $amastyQuoteFactory;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $amastyQuoteResource;

    /**
     * @var ThirdPartyModuleFactory]
     */
    private $amastyQuoteRepository;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $amastyAccountCollection;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $unirgyCertRepository;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $mirasvitStoreCreditHelper;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $mirasvitStoreCreditCalculationHelper;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $mirasvitStoreCreditCalculationConfig;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $mirasvitStoreCreditConfig;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $mirasvitRewardsPurchaseHelper;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $mageplazaGiftCardCollection;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $mageplazaGiftCardFactory;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $amastyRewardsResourceQuote;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $amastyRewardsQuote;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $aheadworksCustomerStoreCreditManagement;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $bssStoreCreditHelper;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $bssStoreCreditCollection;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var AppState
     */
    private $appState;

    /**
     * @var Session
     */
    private $sessionHelper;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->context = $this->createMock(Context::class);
        $this->resource = $this->createMock(ResourceConnection::class);
        $this->amastyAccountFactory = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyGiftCardManagement = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyQuoteFactory = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyQuoteResource = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyQuoteRepository = $this->createMock(ThirdPartyModuleFactory::class);
        $this->amastyAccountCollection = $this->createMock(ThirdPartyModuleFactory::class);
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
    }

    /**
     * @test
     * @param $data
     * @dataProvider providerGetMageplazaGiftCardCodes
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getMageplazaGiftCardCodes($data)
    {
        $currentMock = new Discount(
            $this->context,
            $this->resource,
            $this->amastyAccountFactory,
            $this->amastyGiftCardManagement,
            $this->amastyQuoteFactory,
            $this->amastyQuoteResource,
            $this->amastyQuoteRepository,
            $this->amastyAccountCollection,
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
        $this->sessionHelper->expects(self::once())->method('getCheckoutSession')->willReturnSelf();
        $this->sessionHelper->expects(self::once())->method('getGiftCardsData')->willReturn($data['gift_cards_data_session']);
        $this->quote->method('getMpGiftCards')->willReturn($data['mp_gift_card_quote']);
        $result = $currentMock->getMageplazaGiftCardCodes($this->quote);
        $this->assertEquals(['gift_card'], $result);
    }

    public function providerGetMageplazaGiftCardCodes()
    {
        return [
            ['data' => [
                'gift_cards_data_session' => ['mp_gift_cards' => ['gift_card' => 1000]],
                'mp_gift_card_quote' => null
            ]
            ],
            ['data' => [
                'gift_cards_data_session' => [],
                'mp_gift_card_quote' => '{"gift_card":100}',
            ]
            ]
        ];
    }
}
