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
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Model\ResourceModel;
use Magento\Framework\App\ObjectManager;
use Magento\TestFramework\Helper\Bootstrap;

class FeatureSwitchTest extends BoltTestCase
{
    /**
     * @var \Bolt\Boltpay\Model\FeatureSwitch
     */
    private $featureSwitch;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * Setup for FeatureSwitchTest Class
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->featureSwitch = $this->objectManager->create(FeatureSwitch::class);
    }

    /**
     * @test
     */
    public function testConstruct()
    {
        TestHelper::invokeMethod($this->featureSwitch, '_construct');
        self::assertEquals(ResourceModel\FeatureSwitch::class,$this->featureSwitch->getResourceName());
    }

    /**
     * @test
     */
    public function setAndGetName()
    {
        $this->featureSwitch->setName('name');
        $this->assertEquals('name', $this->featureSwitch->getName());
    }

    /**
     * @test
     */
    public function setAndGetValue()
    {
        $this->featureSwitch->setValue(true);
        $this->assertEquals(true, $this->featureSwitch->getValue());
    }

    /**
     * @test
     */
    public function setAndGetDefaultValue()
    {
        $this->featureSwitch->setDefaultValue(false);
        $this->assertEquals(false, $this->featureSwitch->getDefaultValue());
    }

    /**
     * @test
     */
    public function setAndGetRolloutPercentage()
    {
        $this->featureSwitch->setRolloutPercentage('rolloutPercentage');
        $this->assertEquals('rolloutPercentage', $this->featureSwitch->getRolloutPercentage());
    }
}
