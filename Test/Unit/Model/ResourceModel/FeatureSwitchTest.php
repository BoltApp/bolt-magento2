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

namespace Bolt\Boltpay\Test\Unit\Model\ResourceModel;

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Model\ResourceModel\FeatureSwitch;
use Bolt\Boltpay\Test\Unit\TestHelper;

class FeatureSwitchTest extends TestCase
{
    /**
     * @var FeatureSwitch
     */
    protected $featureSwitch;

    public function setUp()
    {
        $this->featureSwitch = $this->createPartialMock(
            FeatureSwitch::class,
            ['_init']
        );
    }

    /**
     * @test
     */
    public function construct()
    {
       $this->featureSwitch->expects(self::once())->method('_init')
            ->with('bolt_feature_switches', 'id')
            ->willReturnSelf();

        TestHelper::invokeMethod($this->featureSwitch,'_construct');
    }
}