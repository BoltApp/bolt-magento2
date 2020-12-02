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

namespace Bolt\Boltpay\Test\Unit\Model;

use Bolt\Boltpay\Model\FeatureSwitchRepository;
use Bolt\Boltpay\Model\FeatureSwitch;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Exception\NoSuchEntityException;

class FeatureSwitchRepositoryTest extends BoltTestCase
{
    private function checkSwitch($switch, $name, $value, $defaultValue, $rolloutPercentage)
    {
        $this->assertEquals($name,$switch->getName());
        $this->assertEquals($value,$switch->getValue());
        $this->assertEquals($defaultValue,$switch->getDefaultValue());
        $this->assertEquals($rolloutPercentage,$switch->getRolloutPercentage());
    }

    /**
     * @test
     */
    public function getByName_switchExists()
    {
        $this->skipTestInUnitTestsFlow();
        $featureSwitchRepository = Bootstrap::getObjectManager()->create(FeatureSwitchRepository::class);
        $featureSwitchRepository->upsertByName("test_switch", true, false, 100);
        $switch = $featureSwitchRepository->getByName("test_switch");
        $this->checkSwitch($switch,"test_switch", true, false, 100);
    }

    /**
     * @test
     */
    public function getByName_switchDoesNotExists()
    {
        $this->skipTestInUnitTestsFlow();
        $featureSwitchRepository = Bootstrap::getObjectManager()->create(FeatureSwitchRepository::class);
        $exception_thrown = false;
        try {
            $switch = $featureSwitchRepository->getByName("wrong_name");
        } catch (NoSuchEntityException $e) {
            $exception_thrown = true;
        }
        $this->assertTrue($exception_thrown);
    }

    /**
     * @test
     */
    public function upsertByName_shouldOverrideData()
    {
        $this->skipTestInUnitTestsFlow();
        $featureSwitchRepository = Bootstrap::getObjectManager()->create(FeatureSwitchRepository::class);
        $featureSwitchRepository->upsertByName("test_switch", true, false, 100);
        $featureSwitchRepository->upsertByName("test_switch", false, true, 90);
        $switch = $featureSwitchRepository->getByName("test_switch");
        $this->checkSwitch($switch,"test_switch", false, true, 90);
    }
}
