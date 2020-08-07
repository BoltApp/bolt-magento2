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

namespace Bolt\Boltpay\Test\Unit\Helper\FeatureSwitch;

use Bolt\Boltpay\Helper\FeatureSwitch\Definitions;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Helper\FeatureSwitch\Manager;
use Bolt\Boltpay\Helper\GraphQL\Client as GQL;
use Bolt\Boltpay\Model\FeatureSwitch;
use Bolt\Boltpay\Model\FeatureSwitchFactory;
use Bolt\Boltpay\Model\FeatureSwitchRepository;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface as CoreSession;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Class DeciderTest
 * @package Bolt\Boltpay\Test\Unit\Helper\FeatureSwitch
 * @coversDefaultClass \Bolt\Boltpay\Helper\FeatureSwitch\Decider
 */
class DeciderTest  extends TestCase
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var GQL
     */
    private $gql;

    /**
     * @var FeatureSwitchRepository
     */
    private $fsRepo;

    /**
     * @var Decider
     */
    private $decider;
    
    /**
     * @var MockObject|State mocked instance of State
     */
    private $state;
    
    /**
     * @var MockObject|CoreSession mocked instance of CoreSession
     */
    private $session;
    
    /**
     * @var MockObject|FeatureSwitchFactory mocked instance of FeatureSwitchFactory
     */
    private $fsFactory;
    
    /**
     * @var MockObject|Manager mocked instance of Manager
     */
    private $manager;

    /**
     * @inheritdoc
     */
    public function setUp()
    {

        $this->context = $this->createMock(Context::class);
        $this->gql = $this->createMock(GQL::class);
        $this->fsRepo = $this->createMock(FeatureSwitchRepository::class);
        $this->manager = $this->createMock(Manager::class);

        $mockSwitch = $this
            ->getMockBuilder(FeatureSwitch::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->fsFactory = $this->createMock(FeatureSwitchFactory::class);
        $this->fsFactory
            ->method('create')
            ->willReturn($mockSwitch);

        $this->session = $this->createMock(CoreSession::class);
        $this->state = $this->createMock(State::class);


        $this->decider = (new ObjectManager($this))->getObject(
            Decider::class,
            [
                'context' => $this->context,
                'session' => $this->session,
                'state' => $this->state,
                'gql' => $this->gql,
                'fsRepo' => $this->fsRepo,
                'fsFactory' => $this->fsFactory
            ]
        );
    }
    
     /**
     * @test
     * that constructor sets internal properties
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $instance = new Decider(
            $this->context,
            $this->session,
            $this->state,
            $this->manager,
            $this->fsRepo,
            $this->fsFactory
        );
        
        $this->assertAttributeEquals($this->session, '_session', $instance);
        $this->assertAttributeEquals($this->state, '_state', $instance);
        $this->assertAttributeEquals($this->manager, '_manager', $instance);
        $this->assertAttributeEquals($this->fsRepo, '_fsRepo', $instance);
        $this->assertAttributeEquals($this->fsFactory, '_fsFactory', $instance);
    }

    /**
     * @test
     * @covers ::isSwitchEnabled
     */
    public function isSwitchEnabled_nothingInDbNoRollout()
    {
        $this->fsRepo
            ->expects($this->once())
            ->method('getByName')
            ->willThrowException(new NoSuchEntityException(__("no found")));

        $fsVal = $this->decider->isSwitchEnabled(Definitions::M2_SAMPLE_SWITCH_NAME);

        $this->assertEquals($fsVal, false);
    }

    /**
     * @test
     * @covers ::isSwitchEnabled
     */
    public function isSwitchEnabled_ValInDbNoRollout()
    {
        $fs = $this->createMock(FeatureSwitch::class);
        $fs->expects($this->once())->method('getDefaultValue')->willReturn(true);
        $fs->expects($this->once())->method('getRolloutPercentage')->willReturn(0);
        $this->fsRepo
            ->expects($this->once())
            ->method('getByName')
            ->willReturn($fs);

        $fsVal = $this->decider->isSwitchEnabled(Definitions::M2_SAMPLE_SWITCH_NAME);

        $this->assertEquals($fsVal, true);
    }

    /**
     * @test
     * @covers ::isSwitchEnabled
     */
    public function isSwitchEnabled_ValInDbFullRollout()
    {
        $fs = $this->createMock(FeatureSwitch::class);
        $fs->expects($this->once())->method('getValue')->willReturn(true);
        $fs->expects($this->exactly(2))
            ->method('getRolloutPercentage')->willReturn(100);

        $this->fsRepo
            ->expects($this->once())
            ->method('getByName')
            ->willReturn($fs);

        $fsVal = $this->decider->isSwitchEnabled(Definitions::M2_SAMPLE_SWITCH_NAME);

        $this->assertEquals($fsVal, true);
    }

    /**
     * @test
     * @covers ::isSwitchEnabled
     */
    public function isSwitchEnabled_ValInDbPartialRollout()
    {
        $fs = $this->createMock(FeatureSwitch::class);
        $fs->expects($this->once())->method('getValue')->willReturn(true);
        $fs->expects($this->exactly(2))
            ->method('getRolloutPercentage')->willReturn(100);

        $this->fsRepo
            ->expects($this->once())
            ->method('getByName')
            ->willReturn($fs);

        $fsVal = $this->decider->isSwitchEnabled(Definitions::M2_SAMPLE_SWITCH_NAME);

        $this->assertEquals($fsVal, true);
    }

    /**
     * @test
     * @covers ::isSwitchEnabled
     */
    public function isSwitchEnabled_throwsIfBadSwitchName()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage("Unknown feature switch");
        $this->decider->isSwitchEnabled("SECRET_FEATURE_SWITCH");
    }

    // TODO(roopakv): Figure out how to mock globals to test rollout.

    /**
     * @test
     * @covers ::isSwitchEnabled
     * @dataProvider dataProvider_isSwitchEnabled_willReturnBoolean
     * @param $result
     * @param $expected
     * @throws LocalizedException
     */
    public function isSwitchEnabled_willReturnBoolean($result, $expected) {
        $fs = $this->createMock(FeatureSwitch::class);
        $fs->expects($this->once())->method('getValue')->willReturn($result);
        $fs->expects($this->exactly(2))
            ->method('getRolloutPercentage')->willReturn(100);

        $this->fsRepo
            ->expects($this->once())
            ->method('getByName')
            ->willReturn($fs);

        $fsVal = $this->decider->isSwitchEnabled(Definitions::M2_SAMPLE_SWITCH_NAME);

        $this->assertEquals($expected, $fsVal);
    }

    public function dataProvider_isSwitchEnabled_willReturnBoolean()
    {
        return [
            ['0', false],
            ['1', true],
            [1, true],
            [0, false],
            [true, true],
            [false, false],
        ];
    }
}
