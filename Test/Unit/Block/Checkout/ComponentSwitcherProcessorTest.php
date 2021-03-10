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
 *
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Block\Checkout;

use Bolt\Boltpay\Block\Checkout\ComponentSwitcherProcessor;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Test\Unit\BoltTestCase;

/**
 * @coversDefaultClass \Bolt\Boltpay\Block\Checkout\ComponentSwitcherProcessor
 */
class ComponentSwitcherProcessorTest extends BoltTestCase
{
    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var ComponentSwitcherProcessor
     */
    private $componentSwitcherProcessor;

    public function setUpInternal()
    {
        $this->configHelper = $this->createPartialMock(ConfigHelper::class, ['isPaymentOnlyCheckoutEnabled']);
        $this->componentSwitcherProcessor = $this->getMockBuilder(ComponentSwitcherProcessor::class)
            ->setConstructorArgs(
                [
                    $this->configHelper
                ]
            )
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     * @test
     * @covers ::process
     */
    public function process()
    {
        $this->configHelper->method('isPaymentOnlyCheckoutEnabled')->willReturn(true);
        $jsLayout = $this->componentSwitcherProcessor->process([]);

        $this->assertFalse(
            $jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']['children']['renders']['children']['boltpay-payments']['config']['componentDisabled']
        );
    }
}
