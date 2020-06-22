<?php


namespace Bolt\Boltpay\Test\Unit\Helper\FeatureSwitch;

use Bolt\Boltpay\Helper\FeatureSwitch\Definitions;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
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
     * @inheritdoc
     */
    public function setUp()
    {

        $this->context = $this->createMock(Context::class);
        $this->gql = $this->createMock(GQL::class);
        $this->fsRepo = $this->createMock(FeatureSwitchRepository::class);

        $mockSwitch = $this
            ->getMockBuilder(FeatureSwitch::class)
            ->disableOriginalConstructor()
            ->getMock();
        $factory = $this->createMock(FeatureSwitchFactory::class);
        $factory
            ->method('create')
            ->willReturn($mockSwitch);

        $session = $this->createMock(CoreSession::class);
        $state = $this->createMock(State::class);


        $this->decider = (new ObjectManager($this))->getObject(
            Decider::class,
            [
                'context' => $this->context,
                'session' => $session,
                'state' => $state,
                'gql' => $this->gql,
                'fsRepo' => $this->fsRepo,
                'fsFactory' => $factory
            ]
        );
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
