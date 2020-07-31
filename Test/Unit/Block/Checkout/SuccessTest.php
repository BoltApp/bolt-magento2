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

namespace Bolt\Boltpay\Test\Unit\Block\Checkout;

use Bolt\Boltpay\Block\Checkout\Success;
use Bolt\Boltpay\Helper\Config as HelperConfig;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Model\Api\Data\BoltConfigSettingFactory;

/**
 * Class SuccessTest
 *
 * @package Bolt\Boltpay\Test\Unit\Block\Checkout
 */
class SuccessTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var HelperConfig
     */
    protected $configHelper;

    /**
     * @var Success
     */
    protected $block;

    protected function setUp()
    {
        $helperContextMock = $this->createMock(\Magento\Framework\App\Helper\Context::class);
        $contextMock = $this->createMock(\Magento\Framework\View\Element\Template\Context::class);
        $requestMock = $this->getMockBuilder(Http::class)
              ->disableOriginalConstructor()
              ->setMethods(['getFullActionName'])
              ->getMock();
        $contextMock->method('getRequest')->willReturn($requestMock);

        $this->configHelper = $this->getMockBuilder(HelperConfig::class)
            ->setMethods(['shouldTrackCheckoutFunnel'])
            ->setConstructorArgs(
                [
                    $helperContextMock,
                    $this->createMock(\Magento\Framework\Encryption\EncryptorInterface::class),
                    $this->createMock(\Magento\Framework\Module\ResourceInterface::class),
                    $this->createMock(\Magento\Framework\App\ProductMetadataInterface::class),
                    $this->createMock(BoltConfigSettingFactory::class),
                    $this->createMock(\Magento\Directory\Model\RegionFactory::class),
                    $this->createMock(\Magento\Framework\Composer\ComposerFactory::class)
                ]
            )
            ->getMock();
        $deciderMock = $this->createMock(Decider::class);
        $this->block = new Success($this->configHelper, $contextMock, $deciderMock);
    }

    /**
     * @test
     * @dataProvider shouldTrackCheckoutFunnelData
     */
    public function shouldTrackCheckoutFunnel_getValueFromConfig($config, $expected)
    {
        $this->configHelper->method("shouldTrackCheckoutFunnel")->willReturn($config);

        $result = $this->block->shouldTrackCheckoutFunnel();

        $this->assertEquals($expected, $result);
    }

    public function shouldTrackCheckoutFunnelData()
    {
        return [
            [ true, true ],
            [ false, false ]
        ];
    }
}
