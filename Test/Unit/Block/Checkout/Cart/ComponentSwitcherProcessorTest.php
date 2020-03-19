<?php

namespace Bolt\Boltpay\Test\Unit\Block\Checkout\Cart;

use Bolt\Boltpay\Block\Checkout\Cart\ComponentSwitcherProcessor;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ResourceInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

/**
 * @coversDefaultClass \Bolt\Boltpay\Block\Checkout\Cart\ComponentSwitcherProcessor
 */
class ComponentSwitcherProcessorTest extends TestCase
{
    /**
     * Mocked instance of config helper
     *
     * @var PHPUnit_Framework_MockObject_MockObject|ConfigHelper
     */
    protected $configHelper;

    /**
     * Mocked instance of the Config Helper context
     *
     * @var PHPUnit_Framework_MockObject_MockObject|Context
     */
    protected $helperContextMock;

    /**
     * Mocked instance of the class being tested
     *
     * @var PHPUnit_Framework_MockObject_MockObject|ComponentSwitcherProcessor
     */
    protected $currentMock;

    /**
     * Setup test dependencies
     *
     * @return void
     */
    protected function setUp()
    {
        $this->helperContextMock = $this->createMock(Context::class);
        $this->configHelper = $this->getMockBuilder(ConfigHelper::class)
            ->setMethods(['useStoreCreditConfig', 'useRewardPointsConfig'])
            ->setConstructorArgs(
                [
                    $this->helperContextMock,
                    $this->createMock(EncryptorInterface::class),
                    $this->createMock(ResourceInterface::class),
                    $this->createMock(ProductMetadataInterface::class),
                    $this->createMock(Http::class)
                ]
            )
            ->getMock();
        $this->currentMock = $this->getMockBuilder(ComponentSwitcherProcessor::class)
            ->setMethods()
            ->setConstructorArgs(
                [
                    $this->configHelper
                ]
            )
            ->getMock();
    }


    /**
     * Test process method sets componentDisabled property to inverse value of config
     *
     * @test
     *
     * @param bool $useStoreCreditConfig Configuration value for Store Credit
     * @param bool $useRewardPointsConfig Configuration value for Reward Points
     *
     * @dataProvider process_withVariousConfigurationStates_returnsModifiedJsLayoutProvider
     *
     * @return void
     */
    public function process_withVariousConfigurationStates_returnsModifiedJsLayout($useStoreCreditConfig, $useRewardPointsConfig)
    {
        $this->configHelper->expects(self::once())->method('useStoreCreditConfig')
            ->willReturn($useStoreCreditConfig);
        $this->configHelper->expects(self::once())->method('useRewardPointsConfig')
            ->willReturn($useRewardPointsConfig);
        $jsLayout = [
            'components' => [
                'block-totals' => [
                    'children' => [
                        'storeCredit'  => [],
                        'rewardPoints' => []
                    ]
                ]
            ]
        ];
        $result = $this->currentMock->process($jsLayout);
        $blockTotalsChildren = $result['components']['block-totals']['children'];

        if ($useStoreCreditConfig) {
            self::assertArrayHasKey('storeCredit', $blockTotalsChildren);
        } else {
            self::assertArrayNotHasKey('storeCredit', $blockTotalsChildren);
        }

        if ($useRewardPointsConfig) {
            self::assertArrayHasKey('rewardPoints', $blockTotalsChildren);
        } else {
            self::assertArrayNotHasKey('rewardPoints', $blockTotalsChildren);
        }
    }

    /**
     * Provides all configuration combinations for Store Credit and Reward Points
     *
     * @return array of bool pairs
     */
    public function process_withVariousConfigurationStates_returnsModifiedJsLayoutProvider()
    {
        return [
            [true, true],
            [true, false],
            [false, true],
            [false, false]
        ];
    }
}
