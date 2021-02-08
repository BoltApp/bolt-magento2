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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\ThirdPartyModules\Aheadworks;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\OrderService;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use Bolt\Boltpay\Test\Unit\BoltTestCase;

/**
 * @coversDefaultClass \Bolt\Boltpay\ThirdPartyModules\Aheadworks\RewardPoints
 */
class RewardPointsTest extends BoltTestCase
{
    const CUSTOMER_ID = 1234;
    const WEBSITE_ID = 1;
    const STORE_ID = 1;
    const ORDER_ID = 123;

    /**
     * @var Discount|\PHPUnit_Framework_MockObject_MockObject
     */
    private $discountHelperMock;

    /**
     * @var Bugsnag|\PHPUnit_Framework_MockObject_MockObject
     */
    private $bugsnagHelperMock;

    /**
     * @var CartRepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $quoteRepositoryMock;

    /**
     * @var OrderService|\PHPUnit_Framework_MockObject_MockObject
     */
    private $orderServiceMock;

    /**
     * @var \Bolt\Boltpay\ThirdPartyModules\Aheadworks\RewardPoints|\PHPUnit_Framework_MockObject_MockObject
     */
    private $currentMock;

    /**
     * @var \Aheadworks\RewardPoints\Api\CustomerRewardPointsManagementInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $aheadworksCustomerRewardPointsManagementMock;

    /**j
     *
     * @var \Aheadworks\RewardPoints\Model\Config|\PHPUnit_Framework_MockObject_MockObject
     */
    private $aheadworksConfigMock;

    /**
     * @var Quote|\PHPUnit_Framework_MockObject_MockObject
     */
    private $quoteMock;

    /**
     * @var Config|MockObject
     */
    private $configHelperMock;

    /**
     * @var Session|MockObject
     */
    private $customerSessionMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        $this->discountHelperMock = $this->createPartialMock(Discount::class, []);
        $this->bugsnagHelperMock = $this->createMock(Bugsnag::class);
        $this->quoteRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->orderServiceMock = $this->createMock(OrderService::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->customerSessionMock = $this->createMock(Session::class);
        $this->currentMock = $this->getMockBuilder(\Bolt\Boltpay\ThirdPartyModules\Aheadworks\RewardPoints::class)
            ->setConstructorArgs(
                [
                    $this->discountHelperMock,
                    $this->bugsnagHelperMock,
                    $this->configHelperMock,
                    $this->customerSessionMock,
                    $this->quoteRepositoryMock,
                    $this->orderServiceMock
                ]
            )
            ->setMethods(null)
            ->getMock();
        $this->aheadworksCustomerRewardPointsManagementMock = $this->getMockBuilder(
            '\Aheadworks\RewardPoints\Api\CustomerRewardPointsManagementInterface'
        )
            ->setMethods(['getCustomerRewardPointsBalanceBaseCurrency'])
            ->disableAutoload()
            ->disableOriginalConstructor()
            ->getMock();
        $this->aheadworksConfigMock = $this->getMockBuilder('\Aheadworks\RewardPoints\Model\Config')
            ->setMethods(['isApplyingPointsToShipping'])
            ->disableAutoload()
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteMock = $this->createPartialMock(
            Quote::class,
            ['getStore', 'getData', 'getQuoteCurrencyCode', 'getCustomerId', 'collectTotals', 'setData']
        );
    }

    /**
     * @test
     * that constructor sets the expected internal properties
     *
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperties()
    {
        $instance = new \Bolt\Boltpay\ThirdPartyModules\Aheadworks\RewardPoints(
            $this->discountHelperMock,
            $this->bugsnagHelperMock,
            $this->configHelperMock,
            $this->customerSessionMock,
            $this->quoteRepositoryMock,
            $this->orderServiceMock
        );
        static::assertAttributeEquals($this->discountHelperMock, 'discountHelper', $instance);
        static::assertAttributeEquals($this->bugsnagHelperMock, 'bugsnagHelper', $instance);
        static::assertAttributeEquals($this->configHelperMock, 'configHelper', $instance);
        static::assertAttributeEquals($this->customerSessionMock, 'customerSession', $instance);
        static::assertAttributeEquals($this->quoteRepositoryMock, 'quoteRepository', $instance);
        static::assertAttributeEquals($this->orderServiceMock, 'orderService', $instance);
    }

    /**
     * @test
     * that collectDiscounts will add Reward Points to collected discounts taking amount from the customer balance
     * if points apply to shipping
     *
     * @covers ::collectDiscounts
     */
    public function collectDiscounts_ifUseRewardPointsInQuoteAndAppliesToShipping_addsRewardPointsDiscountFromBalance()
    {
        $this->quoteMock->expects(static::exactly(1))->method('getData')
            ->withConsecutive(['aw_use_reward_points'])
            ->willReturnMap([['aw_use_reward_points', null, true]]);
        $storeMock = $this->createMock(Store::class);
        $storeMock->expects(static::once())->method('getWebsiteId')->willReturn(self::WEBSITE_ID);
        $this->quoteMock->expects(static::once())->method('getQuoteCurrencyCode')->willReturn('USD');
        $this->quoteMock->expects(static::once())->method('getCustomerId')->willReturn(self::CUSTOMER_ID);
        $this->quoteMock->expects(static::once())->method('getStore')->willReturn($storeMock);
        $this->aheadworksConfigMock->expects(static::once())->method('isApplyingPointsToShipping')->willReturn(true);
        $customerBalance = 5;
        $this->aheadworksCustomerRewardPointsManagementMock->expects(static::once())
            ->method('getCustomerRewardPointsBalanceBaseCurrency')->with(self::CUSTOMER_ID)->willReturn(
                $customerBalance
            );

        $totalBefore = 1500;
        $diffBefore = 0;
        list ($discounts, $totalAmount, $diff) = $this->currentMock->collectDiscounts(
            [[], $totalBefore, $diffBefore],
            $this->aheadworksCustomerRewardPointsManagementMock,
            $this->aheadworksConfigMock,
            $this->quoteMock,
            $this->quoteMock,
            false
        );
        static::assertEquals(
            [
                [
                    'description'       => __('Reward Points'),
                    'amount'            => $customerBalance * 100,
                    'reference'         => 'aw_reward_points',
                    'discount_category' => 'store_credit',
                    'discount_type'     => 'fixed_amount',
                    'type'              => 'fixed_amount',
                ]
            ],
            $discounts
        );
        static::assertEquals($totalBefore - $customerBalance * 100, $totalAmount);
        static::assertEquals(0, $diff);
    }

    /**
     * @test
     * that collectDiscounts will add Reward Points to collected discounts taking amount from the quote total
     * if points don't apply to shipping
     *
     * @covers ::collectDiscounts
     */
    public function collectDiscounts_ifUseRewardPointsDontApplyToShipping_addsRewardPointsDiscountFromQuoteTotal()
    {
        $rewardPointsTotal = 10;
        $this->quoteMock->expects(static::exactly(2))->method('getData')
            ->withConsecutive(['aw_use_reward_points'], ['base_aw_reward_points_amount'])
            ->willReturnMap(
                [['aw_use_reward_points', null, true], ['base_aw_reward_points_amount', null, $rewardPointsTotal],]
            );
        $storeMock = $this->createMock(Store::class);
        $storeMock->expects(static::once())->method('getWebsiteId')->willReturn(self::WEBSITE_ID);
        $this->quoteMock->expects(static::once())->method('getQuoteCurrencyCode')->willReturn('USD');
        $this->quoteMock->expects(static::never())->method('getCustomerId')->willReturn(self::CUSTOMER_ID);
        $this->quoteMock->expects(static::once())->method('getStore')->willReturn($storeMock);
        $this->aheadworksConfigMock->expects(static::once())->method('isApplyingPointsToShipping')->willReturn(false);
        $customerBalance = 5;
        $this->aheadworksCustomerRewardPointsManagementMock->expects(static::never())
            ->method('getCustomerRewardPointsBalanceBaseCurrency')->with(self::CUSTOMER_ID)->willReturn(
                $customerBalance
            );

        $totalBefore = 3500;
        $diffBefore = 0;
        list ($discounts, $totalAmount, $diff) = $this->currentMock->collectDiscounts(
            [[], $totalBefore, $diffBefore],
            $this->aheadworksCustomerRewardPointsManagementMock,
            $this->aheadworksConfigMock,
            $this->quoteMock,
            $this->quoteMock,
            false
        );
        static::assertEquals(
            [
                [
                    'description'       => __('Reward Points'),
                    'amount'            => $rewardPointsTotal * 100,
                    'reference'         => 'aw_reward_points',
                    'discount_category' => 'store_credit',
                    'discount_type'     => 'fixed_amount',
                    'type'              => 'fixed_amount',
                ]
            ],
            $discounts
        );
        static::assertEquals($totalBefore - $rewardPointsTotal * 100, $totalAmount);
        static::assertEquals(0, $diff);
    }

    /**
     * @test
     * that filterVerifyAppliedStoreCredit won't append the coupon code to the provided result if it doesn't match
     * Aheadworks Reward Points code
     *
     * @covers ::filterVerifyAppliedStoreCredit
     */
    public function filterVerifyAppliedStoreCredit_ifCodeDoesNotMatchAWRewardPoints_doesNotAppendTheCode()
    {
        $result = $this->currentMock->filterVerifyAppliedStoreCredit([], '11235813', $this->quoteMock);
        static::assertEquals([], $result);
    }

    /**
     * @test
     * that filterVerifyAppliedStoreCredit will append the coupon code to the provided result if it matches
     * Aheadworks Reward Points code
     *
     * @covers ::filterVerifyAppliedStoreCredit
     */
    public function filterVerifyAppliedStoreCredit_ifCodeMatchesAWRewardPoints_appendsTheCode()
    {
        $this->quoteMock->expects(static::once())->method('getData')->with('aw_use_reward_points')->willReturn(true);
        $result = $this->currentMock->filterVerifyAppliedStoreCredit(
            [],
            \Bolt\Boltpay\ThirdPartyModules\Aheadworks\RewardPoints::AHEADWORKS_REWARD_POINTS,
            $this->quoteMock
        );
        static::assertEquals(
            [\Bolt\Boltpay\ThirdPartyModules\Aheadworks\RewardPoints::AHEADWORKS_REWARD_POINTS],
            $result
        );
    }

    /**
     * @test
     * that removeAppliedStoreCredit removes Reward Points from quote if they are applied and the coupon code matches
     *
     * @covers ::removeAppliedStoreCredit
     */
    public function removeAppliedStoreCredit_ifCodeMatchesAndRewardPointsAreApplied_removesRewardPointsFromQuote()
    {
        $this->quoteMock->expects(static::once())->method('getData')->with('aw_use_reward_points')->willReturn(true);
        $this->quoteMock->expects(static::once())->method('setData')->with('aw_use_reward_points', false);
        $this->quoteMock->expects(static::once())->method('collectTotals')->willReturnSelf();
        $this->quoteRepositoryMock->expects(static::once())->method('save')->with($this->quoteMock);
        $this->currentMock->removeAppliedStoreCredit(
            \Bolt\Boltpay\ThirdPartyModules\Aheadworks\RewardPoints::AHEADWORKS_REWARD_POINTS,
            $this->quoteMock,
            self::WEBSITE_ID,
            self::STORE_ID
        );
    }

    /**
     * @test
     * that removeAppliedStoreCredit will notify exception if one occurs when saving the quote
     *
     * @covers ::removeAppliedStoreCredit
     */
    public function removeAppliedStoreCredit_onException_onlyNotifiesToBugsnag()
    {
        $this->quoteMock->expects(static::once())->method('getData')->with('aw_use_reward_points')->willReturn(true);
        $this->quoteMock->expects(static::once())->method('setData')->with('aw_use_reward_points', false);
        $this->quoteMock->expects(static::once())->method('collectTotals')->willReturnSelf();
        $exception = new CouldNotSaveException(__("The quote couldn't be saved."));
        $this->quoteRepositoryMock->expects(static::once())->method('save')->with($this->quoteMock)
            ->willThrowException($exception);
        $this->bugsnagHelperMock->expects(static::once())->method('notifyException')->with($exception);
        $this->currentMock->removeAppliedStoreCredit(
            \Bolt\Boltpay\ThirdPartyModules\Aheadworks\RewardPoints::AHEADWORKS_REWARD_POINTS,
            $this->quoteMock,
            self::WEBSITE_ID,
            self::STORE_ID
        );
    }

    /**
     * @test
     * that beforeFailedPaymentOrderSave manually executes
     * @see \Aheadworks\RewardPoints\Plugin\Model\Service\OrderServicePlugin::aroundCancel plugin because it is plugged
     * into {@see \Magento\Sales\Api\OrderManagementInterface::cancel} instead of
     * {@see \Magento\Sales\Model\Order::cancel} which we call in {@see \Bolt\Boltpay\Helper\Order::deleteOrder}
     *
     * @covers ::beforeFailedPaymentOrderSave
     */
    public function beforeFailedPaymentOrderSave_always_executesThirdPartyPlugin()
    {
        $aheadworksRewardPointsOrderServicePluginMock = $this->getMockBuilder(
            '\Aheadworks\RewardPoints\Plugin\Model\Service\OrderServicePlugin'
        )
            ->disableAutoload()
            ->disableOriginalConstructor()
            ->setMethods(['aroundCancel'])
            ->getMock();
        $orderMock = $this->createMock(Order::class);
        $orderMock->expects(static::once())->method('getId')->willReturn(self::ORDER_ID);
        $aheadworksRewardPointsOrderServicePluginMock->expects(static::once())->method('aroundCancel')
            ->with(
                $this->orderServiceMock,
                $this->callback(
                    function ($callback) {
                        static::assertTrue($callback(self::ORDER_ID));
                        return true;
                    }
                ),
                self::ORDER_ID
            );
        $this->currentMock->beforeFailedPaymentOrderSave($aheadworksRewardPointsOrderServicePluginMock, $orderMock);
    }

    /**
     * @test
     * that collectCartDiscountJsLayout will add Aheadworks Reward Points JS layout if preconditions are met
     *
     * @dataProvider collectCartDiscountJsLayout_ifPreconditionsAreMet_addsAWRewardPointsJSLayoutProvider
     * @covers ::collectCartDiscountJsLayout
     *
     * @param bool $isLoggedIn
     * @param bool $getUseAheadworksRewardPointsConfig
     * @param bool $getCustomerRewardPointsOnceMinBalance
     * @param bool $isCustomerRewardPointsSpendRateByGroup
     * @param bool $isCustomerRewardPointsSpendRate
     */
    public function collectCartDiscountJsLayout_ifPreconditionsAreMet_addsAWRewardPointsJSLayout(
        $isLoggedIn,
        $getUseAheadworksRewardPointsConfig,
        $getCustomerRewardPointsOnceMinBalance,
        $isCustomerRewardPointsSpendRateByGroup,
        $isCustomerRewardPointsSpendRate
    ) {
        $customerRewardPointsManagementMock = $this->getMockBuilder(
            '\Aheadworks\RewardPoints\Api\CustomerRewardPointsManagementInterface'
        )
            ->disableOriginalConstructor()
            ->disableAutoload()
            ->setMethods(
                [
                    'getCustomerRewardPointsOnceMinBalance',
                    'isCustomerRewardPointsSpendRateByGroup',
                    'isCustomerRewardPointsSpendRate',
                ]
            )
            ->getMock();
        $this->customerSessionMock->method('getCustomerId')->willReturn(self::CUSTOMER_ID);
        $this->customerSessionMock->method('isLoggedIn')->willReturn($isLoggedIn);
        $this->configHelperMock->method('getUseAheadworksRewardPointsConfig')->willReturn(
            $getUseAheadworksRewardPointsConfig
        );
        $customerRewardPointsManagementMock->method('getCustomerRewardPointsOnceMinBalance')->with(self::CUSTOMER_ID)
            ->willReturn($getCustomerRewardPointsOnceMinBalance ? 0 : 1000);
        $customerRewardPointsManagementMock->method('isCustomerRewardPointsSpendRateByGroup')->with(self::CUSTOMER_ID)
            ->willReturn($isCustomerRewardPointsSpendRateByGroup);
        $customerRewardPointsManagementMock->method('isCustomerRewardPointsSpendRate')->with(self::CUSTOMER_ID)
            ->willReturn($isCustomerRewardPointsSpendRate);
        $result = $this->currentMock->collectCartDiscountJsLayout([], $customerRewardPointsManagementMock);
        if ($isLoggedIn &&
            $getUseAheadworksRewardPointsConfig &&
            $getCustomerRewardPointsOnceMinBalance &&
            $isCustomerRewardPointsSpendRateByGroup &&
            $isCustomerRewardPointsSpendRate
        ) {
            static::assertArrayHasKey('aw-reward-points', $result);
            static::assertEquals(
                [
                    "sortOrder" => 0,
                    "component" => "Aheadworks_RewardPoints/js/view/payment/reward-points",
                    "config"    => [
                        'template' => 'Bolt_Boltpay/third-party-modules/aheadworks/reward-points/cart/reward-points'
                    ],
                    "children"  => [
                        "errors" => [
                            "sortOrder"   => 0,
                            "component"   => "Aheadworks_RewardPoints/js/view/payment/reward-points-messages",
                            "displayArea" => "messages",
                        ]
                    ]
                ],
                $result['aw-reward-points']
            );
        } else {
            static::assertArrayNotHasKey('aw-reward-points', $result);
        }
    }

    /**
     * Data provider for {@see collectCartDiscountJsLayout_ifPreconditionsAreMet_addsAWRewardPointsJSLayout}
     */
    public function collectCartDiscountJsLayout_ifPreconditionsAreMet_addsAWRewardPointsJSLayoutProvider()
    {
        return TestHelper::getAllBooleanCombinations(5);
    }
}
