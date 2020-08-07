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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model;

use Bolt\Boltpay\Model\FeatureSwitch;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Model\ResourceModel;

class FeatureSwitchTest extends TestCase
{
    /**
     * @var \Bolt\Boltpay\Model\FeatureSwitch
     */
    private $mockFeatureSwitch;

    /**
     * Setup for FeatureSwitchTest Class
     */
    public function setUp()
    {
        $this->mockFeatureSwitch = $this->getMockBuilder(FeatureSwitch::class)
            ->disableOriginalConstructor()
            ->setMethods(['_init'])
            ->getMock();
    }

    /**
     * @test
     */
    public function testConstruct()
    {
        $this->mockFeatureSwitch->expects($this->once())->method('_init')
            ->with(ResourceModel\FeatureSwitch::class)
            ->willReturnSelf();

        TestHelper::invokeMethod($this->mockFeatureSwitch, '_construct');
    }

    /**
     * @test
     */
    public function setAndGetName()
    {
        $this->mockFeatureSwitch->setName('name');
        $this->assertEquals('name', $this->mockFeatureSwitch->getName());
    }

    /**
     * @test
     */
    public function setAndGetValue()
    {
        $this->mockFeatureSwitch->setValue('value');
        $this->assertEquals('value', $this->mockFeatureSwitch->getValue());
    }

    /**
     * @test
     */
    public function setAndGetDefaultValue()
    {
        $this->mockFeatureSwitch->setDefaultValue('defaultValue');
        $this->assertEquals('defaultValue', $this->mockFeatureSwitch->getDefaultValue());
    }

    /**
     * @test
     */
    public function setAndGetRolloutPercentage()
    {
        $this->mockFeatureSwitch->setRolloutPercentage('rolloutPercentage');
        $this->assertEquals('rolloutPercentage', $this->mockFeatureSwitch->getRolloutPercentage());
    }
}
