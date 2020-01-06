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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Test\Unit\Helper\FeatureSwitch;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\FeatureSwitch\Definitions;

class DefinitionsTest extends TestCase
{
    /**
     * @test
     */
    public function getNameKeyConstant()
    {
        $this->assertEquals('name', Definitions::NAME_KEY);
    }

    /**
     * @test
     */
    public function getValueKeyConstant()
    {
        $this->assertEquals('value', Definitions::VAL_KEY);
    }

    /**
     * @test
     */
    public function getDefaultValKeyConstant()
    {
        $this->assertEquals('default_value', Definitions::DEFAULT_VAL_KEY);
    }

    /**
     * @test
     */
    public function getRolloutKeyConstant()
    {
        $this->assertEquals('rollout_percentage', Definitions::ROLLOUT_KEY);
    }

    /**
     * @test
     */
    public function getM2SampleSwitchNameConstant()
    {
        $this->assertEquals('M2_SAMPLE_SWITCH', Definitions::M2_SAMPLE_SWITCH_NAME);
    }

    /**
     * @test
     */
    public function getDefaultSwitchValuesConstant()
    {
        $this->assertEquals(
            [
                'M2_SAMPLE_SWITCH' => [
                    'name' => 'M2_SAMPLE_SWITCH',
                    'value' => true,
                    'default_value' => false,
                    'rollout_percentage' => 0
                ]
            ], Definitions::DEFAULT_SWITCH_VALUES);
    }
}