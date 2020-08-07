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

use Bolt\Boltpay\Api\Data\FeatureSwitchInterface;
use Bolt\Boltpay\Model\FeatureSwitchRepository;
use Bolt\Boltpay\Model\FeatureSwitchFactory;
use Bolt\Boltpay\Model\FeatureSwitch;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Registry;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class FeatureSwitchRepositoryTest extends TestCase
{
    /**
     * @var FeatureSwitch|\PHPUnit_Framework_MockObject_MockObject
     */
    private $mockSwitch;

    /**
     * @var FeatureSwitchRepository
     */
    private $switchRepo;

    /**
     * Setup for FeatureSwitchRepositoryTest Class
     */
    public function setUp()
    {
        $context = $this->createMock(Context::class);
        $registry = $this->createMock(Registry::class);
        $this->mockSwitch = $this
            ->getMockBuilder(FeatureSwitch::class)
            ->disableOriginalConstructor()
            ->getMock();
        $factory = $this->createMock(FeatureSwitchFactory::class);
        $factory
            ->method('create')
            ->willReturn($this->mockSwitch);

        $this->switchRepo = (new ObjectManager($this))->getObject(
            FeatureSwitchRepository::class,
            [
                'featureSwitchFactory' => $factory,
            ]
        );
    }

    /**
     * @test
     * Test that getByName() works as expected.
     */
    public function getByName()
    {
        $foundSwitch = $this
            ->getMockBuilder(FeatureSwitchInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $foundSwitch
            ->method('getName')
            ->willReturn("SOME_SWITCH");

        $this->mockSwitch
            ->expects($this->once())
            ->method('getResource')
            ->willReturn($this->mockSwitch);
        $this->mockSwitch
            ->expects($this->once())
            ->method('load')
            ->with($this->anything(), "SOME_SWITCH", FeatureSwitch::NAME)
            ->willReturn($foundSwitch);

        $this->mockSwitch
            ->method('getName')
            ->willReturn("SOME_SWITCH");

        $this->assertEquals(
            $foundSwitch->getName(),
            $this->switchRepo->getByName("SOME_SWITCH")->getName()
        );
    }

    /**
     * @test
     */
    public function upsertByName_switchFound()
    {
        $foundSwitch = $this
            ->getMockBuilder(FeatureSwitchInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $foundSwitch
            ->method('getName')
            ->willReturn("SOME_SWITCH");

        $this->mockSwitch
            ->expects($this->exactly(2))
            ->method('getResource')
            ->willReturn($this->mockSwitch);
        $this->mockSwitch
            ->expects($this->once())
            ->method('load')
            ->with($this->anything(), "SOME_SWITCH", FeatureSwitch::NAME)
            ->willReturn($foundSwitch);

        $this->mockSwitch
            ->method('getName')
            ->willReturn("SOME_SWITCH");

        $this->mockSwitch
            ->expects($this->once())
            ->method('save')
            ->with(
                $this->isInstanceOf('Bolt\BoltPay\Api\Data\FeatureSwitchInterface')
            );

        $this->mockSwitch->expects($this->once())->method('setValue')->with(true);
        $this->mockSwitch->expects($this->once())
            ->method('setDefaultValue')->with(false);
        $this->mockSwitch->expects($this->once())
            ->method('setRolloutPercentage')->with(10);

        $this->switchRepo->upsertByName("SOME_SWITCH", true, false, 10);
    }

    /**
     * @test
     */
    public function upsertByName_switchNotFound()
    {
        $this->mockSwitch
            ->expects($this->exactly(2))
            ->method('getResource')
            ->willReturn($this->mockSwitch);
        $this->mockSwitch
            ->expects($this->once())
            ->method('load')
            ->with($this->anything(), "SOME_SWITCH", FeatureSwitch::NAME)
            ->willReturn(null);

        $this->mockSwitch
            ->expects($this->once())
            ->method('save')
            ->with(
                $this->isInstanceOf('Bolt\BoltPay\Api\Data\FeatureSwitchInterface')
            );

        $this->mockSwitch->expects($this->once())->method('setValue')->with(true);
        $this->mockSwitch->expects($this->once())
            ->method('setDefaultValue')->with(false);
        $this->mockSwitch->expects($this->once())
            ->method('setRolloutPercentage')->with(10);

        $this->switchRepo->upsertByName("SOME_SWITCH", true, false, 10);
    }

    /**
     * @test
     */
    public function save()
    {
        $this->mockSwitch
            ->expects($this->once())
            ->method('getResource')
            ->willReturn($this->mockSwitch);
        $this->mockSwitch
            ->expects($this->once())
            ->method('save')
            ->with(
                $this->isInstanceOf('Bolt\BoltPay\Api\Data\FeatureSwitchInterface')
            );

        $this->switchRepo->save($this->mockSwitch);
    }

    /**
     * @test
     */
    public function delete()
    {
        $this->mockSwitch
            ->expects($this->once())
            ->method('getResource')
            ->willReturn($this->mockSwitch);
        $this->mockSwitch
            ->expects($this->once())
            ->method('delete')
            ->with(
                $this->isInstanceOf('Bolt\BoltPay\Api\Data\FeatureSwitchInterface')
            );

        $this->switchRepo->delete($this->mockSwitch);
    }
}
