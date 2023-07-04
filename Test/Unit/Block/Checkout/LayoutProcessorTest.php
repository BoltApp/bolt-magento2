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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Block\Checkout;

use Bolt\Boltpay\Block\Checkout\ComponentSwitcherProcessor as LayoutProcessor;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * Class LayoutProcessorTest
 *
 * @package Bolt\Boltpay\Test\Unit\Block\Checkout
 * @coversDefaultClass \Bolt\Boltpay\Block\Checkout\LayoutProcessor
 */
class LayoutProcessorTest extends BoltTestCase
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @test
     * that constructor sets internal properties
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $instance = $this->objectManager->create(LayoutProcessor::class);
        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
    }

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->configHelper = $this->objectManager->create(ConfigHelper::class);
    }
}
