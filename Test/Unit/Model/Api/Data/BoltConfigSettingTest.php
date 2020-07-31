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

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Model\Api\Data\BoltConfigSetting;

/**
 * Class BoltConfigSettingTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 */
class BoltConfigSettingTest extends TestCase
{
    /**
     * @var BoltConfigSetting
     */
    protected $boltConfigSetting;

    protected function setUp()
    {
        $this->boltConfigSetting = new BoltConfigSetting();
    }

    /**
     * @test
     */
    public function setAndGetValue()
    {
        $this->boltConfigSetting->setValue('value');
        $this->assertEquals('value', $this->boltConfigSetting->getValue());
    }

    /**
     * @test
     */
    public function setAndGetName()
    {
        $this->boltConfigSetting->setName('name');
        $this->assertEquals('name', $this->boltConfigSetting->getName());
    }

    /**
     * @test
     */
    public function jsonSerialize()
    {
        $this->boltConfigSetting->setName('test');
        $this->boltConfigSetting->setValue(true);
        $result = $this->boltConfigSetting->jsonSerialize();
        $this->assertEquals([
            'name' => 'test',
            'value' => true
        ], $result);
    }
}
