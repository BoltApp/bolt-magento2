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

namespace Bolt\Boltpay\Test\Unit\ViewModel;

use PHPUnit\Framework\TestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

/**
 * @coversDefaultClass \Bolt\Boltpay\ViewModel\MinicartAddons
 */
class MinicartAddonsTest extends TestCase
{
    const DEFAULT_LAYOUT = [
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
    ];

    /**
     * @var \Bolt\Boltpay\Helper\Config|\PHPUnit_Framework_MockObject_MockObject
     */
    private $configHelper;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @var \Magento\Framework\App\Http\Context|\PHPUnit_Framework_MockObject_MockObject
     */
    private $httpContext;
    private $currentMock;

    /**
     * Setup test dependencies, called before each test
     */
    public function setUp()
    {
        $this->configHelper = $this->createMock(\Bolt\Boltpay\Helper\Config::class);
        $this->serializer = (new ObjectManager($this))
            ->getObject(\Magento\Framework\Serialize\Serializer\Json::class);
        $this->httpContext = $this->createMock(\Magento\Framework\App\Http\Context::class);
        $this->currentMock = new \Bolt\Boltpay\ViewModel\MinicartAddons(
            $this->serializer,
            $this->httpContext,
            $this->configHelper
        );
    }

    /**
     * @test
     * that __construct always sets internal properties appropriately
     *
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperties()
    {
        $instance = new \Bolt\Boltpay\ViewModel\MinicartAddons(
            $this->serializer,
            $this->httpContext,
            $this->configHelper
        );
        static::assertAttributeEquals($this->serializer, 'serializer', $instance);
        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
        static::assertAttributeEquals($this->httpContext, 'httpContext', $instance);
    }

    /**
     * @test
     * that getLayout returns layout updates based on configuration and context state
     *
     * @covers ::getLayout
     *
     * @dataProvider getLayout_withVariousStatesProvider
     *
     * @param bool  $customerLoggedIn HTTP context flag
     * @param bool  $displayRewardPointsInMinicartConfig flag value
     * @param array $expectedResult of the method call
     *
     * @throws \ReflectionException if getLayout method is not defined
     */
    public function getLayout_withVariousStates_returnsLayout(
        $customerLoggedIn,
        $displayRewardPointsInMinicartConfig,
        $expectedResult
    ) {
        $this->httpContext->expects(static::once())->method('getValue')
            ->with(\Magento\Customer\Model\Context::CONTEXT_AUTH)
            ->willReturn($customerLoggedIn);
        $this->configHelper->expects($customerLoggedIn ? static::once() : static::never())
            ->method('displayRewardPointsInMinicartConfig')
            ->willReturn($displayRewardPointsInMinicartConfig);
        static::assertEquals(
            $expectedResult,
            \Bolt\Boltpay\Test\Unit\TestHelper::invokeMethod($this->currentMock, 'getLayout')
        );
    }

    /**
     * Data provider for {@see getLayout_withVariousStates_returnsLayout}
     *
     * @return array[] containing customer logged in flag, use reward points on minicart config and expected result of the method call
     */
    public function getLayout_withVariousStatesProvider()
    {
        return [
            'Happy path - customer logged in and rewards on minicart enabled' => [
                'customerLoggedIn'                    => true,
                'displayRewardPointsInMinicartConfig' => true,
                'expectedResult'                      => self::DEFAULT_LAYOUT
            ],
            [
                'customerLoggedIn'                    => false,
                'displayRewardPointsInMinicartConfig' => true,
                'expectedResult'                      => []
            ],
            [
                'customerLoggedIn'                    => true,
                'displayRewardPointsInMinicartConfig' => false,
                'expectedResult'                      => []
            ],
        ];
    }

    /**
     * @test
     * that getLayoutJSON returns the result of the getLayout method in JSON format
     *
     * @covers ::getLayoutJSON
     */
    public function getLayoutJSON()
    {
        $currentMock = $this->createPartialMock(\Bolt\Boltpay\ViewModel\MinicartAddons::class, ['getLayout']);
        \Bolt\Boltpay\Test\Unit\TestHelper::setProperty($currentMock, 'serializer', $this->serializer);
        $currentMock->expects(static::once())->method('getLayout')->willReturn(self::DEFAULT_LAYOUT);
        $result = $currentMock->getLayoutJSON();
        static::assertJson($result);
        static::assertEquals(self::DEFAULT_LAYOUT, $this->serializer->unserialize($result));
    }

    /**
     * @test
     * that shouldShow returns true only if Bolt on minicart is enabled and at least one minicart addon is enabled
     *
     * @dataProvider shouldShow_withVariousStatesProvider
     *
     * @covers ::shouldShow
     *
     * @param bool  $minicartSupport configuration value
     * @param array $layout stubbed result of the getLayout method call
     * @param bool  $expectedResult of the method call
     *
     * @throws \ReflectionException if configHelper property is undefined
     */
    public function shouldShow_withVariousStates_determinesIfAddonsShouldBeRendered(
        $minicartSupport,
        $layout,
        $expectedResult
    ) {
        $currentMock = $this->createPartialMock(\Bolt\Boltpay\ViewModel\MinicartAddons::class, ['getLayout']);
        \Bolt\Boltpay\Test\Unit\TestHelper::setProperty($currentMock, 'configHelper', $this->configHelper);
        $this->configHelper->expects(static::once())->method('getMinicartSupport')->willReturn($minicartSupport);
        $currentMock->expects($minicartSupport ? static::once() : static::never())->method('getLayout')
            ->willReturn($layout);
        static::assertEquals($expectedResult, $currentMock->shouldShow());
    }

    /**
     * Data provider for {@see shouldShow_withVariousStates_determinesIfAddonsShouldBeRendered}
     *
     * @return array[] containing minicart support config value, subbed result of getLayout and expected result
     */
    public function shouldShow_withVariousStatesProvider()
    {
        return [
            [
                'minicartSupport' => true,
                'layout'          => self::DEFAULT_LAYOUT,
                'expectedResult'  => true,
            ],
            [
                'minicartSupport' => true,
                'layout'          => [],
                'expectedResult'  => false,
            ],
            [
                'minicartSupport' => false,
                'layout'          => self::DEFAULT_LAYOUT,
                'expectedResult'  => false,
            ],
        ];
    }
}
