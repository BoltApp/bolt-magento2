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

namespace Bolt\Boltpay\Test\Unit\ThirdPartyModules\Magento;

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\ThirdPartyModules\Magento\Reward;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @coversDefaultClass \Bolt\Boltpay\ThirdPartyModules\Magento\Reward
 */
class RewardTest extends BoltTestCase
{

    /**
     * @var Config|MockObject
     */
    private $configHelperMock;

    /**
     * @var Reward|MockObject
     */
    private $currentMock;

    /**
     * @var \Magento\Framework\App\Http\Context|MockObject
     */
    private $httpContextMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        $this->configHelperMock = $this->createMock(Config::class);
        $this->httpContextMock = $this->createMock(\Magento\Framework\App\Http\Context::class);
        $this->currentMock = $this->getMockBuilder(Reward::class)
            ->setConstructorArgs(
                [
                    $this->configHelperMock,
                    $this->httpContextMock
                ]
            )
            ->setMethods(null)
            ->getMock();
    }

    /**
     * @test
     * that constructor sets the expected internal properties
     *
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperties()
    {
        $instance = new \Bolt\Boltpay\ThirdPartyModules\Magento\Reward($this->configHelperMock, $this->httpContextMock);
        static::assertAttributeEquals($this->configHelperMock, 'configHelper', $instance);
    }

    /**
     * @test
     * that filterProcessLayout will not add layout if Magento EE Rewards on Shopping Cart
     * is not enabled in the config
     *
     * @covers ::filterProcessLayout
     */
    public function filterProcessLayout_notEnabledInConfig_doesNotAddLayout()
    {
        $this->configHelperMock->expects(static::once())->method('useRewardPointsConfig')->willReturn(false);
        static::assertEquals([], $this->currentMock->filterProcessLayout([]));
    }

    /**
     * @test
     * that filterProcessLayout adds Magento EE Rewards button layout
     * if enabled in the config
     *
     * @covers ::filterProcessLayout
     */
    public function filterProcessLayout_ifEnabledInConfig_addsModuleSpecificLayout()
    {
        $this->configHelperMock->expects(static::once())->method('useRewardPointsConfig')->willReturn(true);
        $result = $this->currentMock->filterProcessLayout([]);
        static::assertEquals(
            [
                'component' => 'Magento_Reward/js/view/payment/reward'
            ],
            $result['components']['block-totals']['children']['rewardPoints']
        );
    }

    /**
     * @test
     * that collectCartDiscountJsLayout will not add layout if Magento EE Rewards on MiniCart
     * is not enabled in the config
     *
     * @covers ::filterMinicartAddonsLayout
     * @dataProvider filterMinicartAddonsLayout_withVariousStatesProvider
     *
     * @param bool $displayRewardPointsInMinicartConfig
     * @param bool $customerLoggedIn
     */
    public function filterMinicartAddonsLayout_withVariousStates_appendsLayoutIfConditionsAreMet(
        $displayRewardPointsInMinicartConfig,
        $customerLoggedIn
    ) {
        $this->configHelperMock->method('displayRewardPointsInMinicartConfig')
            ->willReturn($displayRewardPointsInMinicartConfig);
        $this->httpContextMock->method('getValue')
            ->willReturn($customerLoggedIn);
        $result = $this->currentMock->filterMinicartAddonsLayout([]);
        if ($customerLoggedIn && $displayRewardPointsInMinicartConfig) {
            static::assertEquals(
                [
                    [
                        'parent'    => 'minicart_content.extra_info',
                        'name'      => 'minicart_content.extra_info.rewards',
                        'component' => 'Magento_Reward/js/view/payment/reward',
                        'config'    => [],
                    ],
                    [
                        'parent'    => 'minicart_content.extra_info',
                        'name'      => 'minicart_content.extra_info.rewards_total',
                        'component' => 'Magento_Reward/js/view/cart/reward',
                        'config'    => [
                            'template' => 'Magento_Reward/cart/reward',
                            'title'    => 'Reward Points',
                        ],
                    ]
                ],
                $result
            );
        } else {
            static::assertEquals([], $result);
        }
    }

    /**
     * Data provider for {@see filterMinicartAddonsLayout_withVariousStates_appendsLayoutIfConditionsAreMet}
     *
     * @return array
     */
    public function filterMinicartAddonsLayout_withVariousStatesProvider()
    {
        return [
            [true, false],
            [true, true],
            [false, false],
            [false, true],
        ];
    }
}
