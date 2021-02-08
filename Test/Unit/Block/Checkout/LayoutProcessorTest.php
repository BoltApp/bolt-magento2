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

namespace Bolt\Boltpay\Test\Unit\Block\Checkout;

use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Block\Checkout\LayoutProcessor;
use Bolt\Boltpay\Test\Unit\BoltTestCase;

/**
 * Class LayoutProcessorTest
 * @package Bolt\Boltpay\Test\Unit\Block\Checkout
 * @coversDefaultClass \Bolt\Boltpay\Block\Checkout\LayoutProcessor
 */
class LayoutProcessorTest extends BoltTestCase
{
     /**
     * @var MockObject|ConfigHelper mocked instance of Config helper
     */
    protected $configHelper;
    
    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
    }
    
    /**
     * @test
     * that constructor sets internal properties
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $instance = $this->getMockBuilder(LayoutProcessor::class)
            ->setConstructorArgs([$this->configHelper])
            ->enableOriginalConstructor()
            ->getMockForAbstractClass();

        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
    }
}