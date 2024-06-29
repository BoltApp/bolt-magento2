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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Block\Checkout;

use Bolt\Boltpay\Block\Checkout\ComponentSwitcherProcessor;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * @coversDefaultClass \Bolt\Boltpay\Block\Checkout\ComponentSwitcherProcessor
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
        $jsLayout = $this->componentSwitcherProcessor->process([]);
        $this->assertTrue(
            $jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']['children']['renders']['children']['boltpay-payments']['config']['componentDisabled']
        );
    }
}
