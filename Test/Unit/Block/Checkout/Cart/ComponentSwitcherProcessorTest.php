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

namespace Bolt\Boltpay\Test\Unit\Block\Checkout\Cart;

use Bolt\Boltpay\Block\Checkout\Cart\ComponentSwitcherProcessor;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * @coversDefaultClass \Bolt\Boltpay\Block\Checkout\Cart\ComponentSwitcherProcessor
 */
class ComponentSwitcherProcessorTest extends BoltTestCase
{
    /**
     * @var ComponentSwitcherProcessor
     */
    protected $componentSwitcherProcessor;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * Setup test dependencies
     *
     * @return void
     */
    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->componentSwitcherProcessor = $this->objectManager->create(ComponentSwitcherProcessor::class);
    }

    /**
     * @test
     * @covers ::process
     */
    public function process()
    {
        $filteredJsLayout = [
            'components' => [
                'block-totals' => [
                    'children' => [
                        'storeCredit' => [
                            'component' => 'Magento_CustomerBalance/js/view/payment/customer-balance',
                        ],
                        'rewardPoints' => [
                            'component' => 'Magento_Reward/js/view/payment/reward',
                        ]
                    ]
                ]
            ]
        ];

        static::assertEquals($filteredJsLayout, $this->componentSwitcherProcessor->process($filteredJsLayout));
    }

    /**
     * @test
     * @covers ::process
     * Test process method filters the provided JS layout using the third party filter "filterProcessLayout"
     */
    public function process_withVariousConfigurationStates_returnsModifiedJsLayout()
    {
        $filteredJsLayout = [
            'components' => [
                'block-totals' => [
                    'children' => [
                        'storeCredit' => [
                            'component' => 'Magento_CustomerBalance/js/view/payment/customer-balance',
                        ],
                        'rewardPoints' => [
                            'component' => 'Magento_Reward/js/view/payment/reward',
                        ]
                    ]
                ]
            ]
        ];

        $eventThirdPartyModule = $this->createPartialMock(EventsForThirdPartyModules::class,['runFilter']);
        $eventThirdPartyModule->method('runFilter')->willReturn($filteredJsLayout);

        TestHelper::setProperty($this->componentSwitcherProcessor, 'eventsForThirdPartyModules', $eventThirdPartyModule);
        static::assertEquals($filteredJsLayout, $this->componentSwitcherProcessor->process([]));
    }
}
