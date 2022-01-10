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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\ThirdPartyModules\Magento;

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\ThirdPartyModules\Magento\Reward;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @coversDefaultClass \Bolt\Boltpay\ThirdPartyModules\Magento\Reward
 */
class RewardTest extends BoltTestCase
{

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var \Magento\Framework\App\Http\Context
     */
    private $httpContext;
    private $objectManager;

    /** @var Reward */
    private $reward;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->reward = $this->objectManager->create(Reward::class);
        $this->configHelper = $this->objectManager->create(Config::class);
        $this->httpContext = $this->objectManager->create(\Magento\Framework\App\Http\Context::class);
    }

    /**
     * @test
     * that constructor sets the expected internal properties
     *
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperties()
    {
        $instance = new \Bolt\Boltpay\ThirdPartyModules\Magento\Reward($this->configHelper, $this->httpContext);
        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
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
        static::assertEquals([], $this->reward->filterProcessLayout([]));
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
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();
        $configData = [
            [
                'path' => Config::XML_PATH_REWARD_POINTS,
                'value' => true,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $result = $this->reward->filterProcessLayout([]);
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
    )
    {
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();

        $configData = [
            [
                'path' => Config::XML_PATH_REWARD_POINTS_MINICART,
                'value' => $displayRewardPointsInMinicartConfig,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $this->httpContext->setValue('customer_logged_in', $customerLoggedIn, null);
        TestHelper::setProperty($this->reward, 'httpContext', $this->httpContext);

        $result = $this->reward->filterMinicartAddonsLayout([]);
        if ($customerLoggedIn && $displayRewardPointsInMinicartConfig) {
            static::assertEquals(
                [
                    [
                        'parent' => 'minicart_content.extra_info',
                        'name' => 'minicart_content.extra_info.rewards',
                        'component' => 'Magento_Reward/js/view/payment/reward',
                        'config' => [],
                    ],
                    [
                        'parent' => 'minicart_content.extra_info',
                        'name' => 'minicart_content.extra_info.rewards_total',
                        'component' => 'Magento_Reward/js/view/cart/reward',
                        'config' => [
                            'template' => 'Magento_Reward/cart/reward',
                            'title' => 'Reward Points',
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
