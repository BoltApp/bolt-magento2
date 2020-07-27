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

namespace Bolt\Boltpay\Test\Unit\Block\Checkout\Cart;

use Bolt\Boltpay\Block\Checkout\Cart\ComponentSwitcherProcessor;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Model\Api\Data\BoltConfigSettingFactory;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ResourceInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Composer\ComposerFactory;

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
            ->setMethods(['useStoreCreditConfig', 'useRewardPointsConfig', 'useAmastyStoreCreditConfig'])
            ->setConstructorArgs(
                [
                    $this->helperContextMock,
                    $this->createMock(EncryptorInterface::class),
                    $this->createMock(ResourceInterface::class),
                    $this->createMock(ProductMetadataInterface::class),
                    $this->createMock(BoltConfigSettingFactory::class),
                    $this->createMock(RegionFactory::class),
                    $this->createMock(ComposerFactory::class)
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
     * @test
     * Test process method sets componentDisabled property to inverse value of config
     *
     * @param bool $useStoreCreditConfig Configuration value for Store Credit
     * @param bool $useRewardPointsConfig Configuration value for Reward Points
     * @param bool $useAmastyStoreCreditConfig Configuration value for Amasty Store Credit
     *
     * @dataProvider process_withVariousConfigurationStates_returnsModifiedJsLayoutProvider
     *
     * @covers ::process
     */
    public function process_withVariousConfigurationStates_returnsModifiedJsLayout(
        $useStoreCreditConfig,
        $useRewardPointsConfig,
        $useAmastyStoreCreditConfig
    ) {
        $this->configHelper->expects(self::once())->method('useStoreCreditConfig')
            ->willReturn($useStoreCreditConfig);
        $this->configHelper->expects(self::once())->method('useRewardPointsConfig')
            ->willReturn($useRewardPointsConfig);
        $this->configHelper->expects(self::once())->method('useAmastyStoreCreditConfig')
            ->willReturn($useAmastyStoreCreditConfig);
        $jsLayout = [
            'components' => [
                'block-totals' => [
                    'children' => [
                        'storeCredit'         => [
                            'component' => 'Magento_CustomerBalance/js/view/payment/customer-balance',
                        ],
                        'rewardPoints'        => [
                            'component' => 'Magento_Reward/js/view/payment/reward',
                        ],
                        'amstorecredit_total' => [
                            'component' => 'Amasty_StoreCredit/js/view/checkout/totals/store-credit',
                            'sortOrder' => '90',
                        ],
                        'amstorecredit_form'  => [
                            'component' => 'Amasty_StoreCredit/js/view/checkout/payment/store-credit',
                        ],
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

        if ($useAmastyStoreCreditConfig) {
            self::assertArrayHasKey('amstorecredit_total', $blockTotalsChildren);
            self::assertArrayHasKey('amstorecredit_form', $blockTotalsChildren);
        } else {
            self::assertArrayNotHasKey('amstorecredit_total', $blockTotalsChildren);
            self::assertArrayNotHasKey('amstorecredit_form', $blockTotalsChildren);
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
            ['useStoreCreditConfig' => true, 'useRewardPointsConfig' => true, 'useAmastyStoreCreditConfig' => true],
            ['useStoreCreditConfig' => true, 'useRewardPointsConfig' => true, 'useAmastyStoreCreditConfig' => false],
            ['useStoreCreditConfig' => true, 'useRewardPointsConfig' => false, 'useAmastyStoreCreditConfig' => true],
            ['useStoreCreditConfig' => true, 'useRewardPointsConfig' => false, 'useAmastyStoreCreditConfig' => false],
            ['useStoreCreditConfig' => false, 'useRewardPointsConfig' => true, 'useAmastyStoreCreditConfig' => true],
            ['useStoreCreditConfig' => false, 'useRewardPointsConfig' => true, 'useAmastyStoreCreditConfig' => false],
            ['useStoreCreditConfig' => false, 'useRewardPointsConfig' => false, 'useAmastyStoreCreditConfig' => true],
            ['useStoreCreditConfig' => false, 'useRewardPointsConfig' => false, 'useAmastyStoreCreditConfig' => false],
        ];
    }
}
