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
use Bolt\Boltpay\Helper\FeatureSwitch\Manager;
use Bolt\Boltpay\Model\FeatureSwitch;
use Bolt\Boltpay\Model\FeatureSwitchRepository;
use Bolt\Boltpay\Helper\GraphQL\Client as GQL;
use Bolt\Boltpay\Model\Response as BoltResponse;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\App\State;
use PHPUnit\Framework\TestCase;

/**
 * Class ManagerTest
 *
 * @coversDefaultClass \Bolt\Boltpay\Helper\FeatureSwitch\Manager
 */
class ManagerTest extends TestCase
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
     * @var Manager
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

        $this->manager = (new ObjectManager($this))->getObject(
            Manager::class,
            [
                'context' => $this->context,
                'gql' => $this->gql,
                'fsRepo' => $this->fsRepo
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
        $instance = new Manager(
            $this->context,
            $this->gql,
            $this->fsRepo
        );
        
        $this->assertAttributeEquals($this->gql, 'gql', $instance);
        $this->assertAttributeEquals($this->fsRepo, 'fsRepo', $instance);
    }

    /**
     * @test
     */
    public function updateSwitchesFromBolt_EmptyResponse()
    {
        $this->gql
            ->expects($this->once())
            ->method('getFeatureSwitches')
            ->willReturn(new BoltResponse());

        $this->fsRepo
            ->expects($this->never())
            ->method('upsertByName');

        $this->manager->updateSwitchesFromBolt();
    }

    /**
     * @test
     */
    public function updateSwitchesFromBolt_ResponseWithOnlyResponse()
    {
        $response = new BoltResponse();
        $respObj = ["response" => "ok"];
        $response->setData($respObj);
        $this->gql
            ->expects($this->once())
            ->method('getFeatureSwitches')
            ->willReturn($response);

        $this->fsRepo
            ->expects($this->never())
            ->method('upsertByName');

        $this->manager->updateSwitchesFromBolt();
    }

    /**
     * @test
     */
    public function updateSwitchesFromBolt_ResponseUptoData()
    {
        $response = new BoltResponse();
        $respObj = ["response" => (object)["data" => "plugin"]];
        $response->setData($respObj);
        $this->gql
            ->expects($this->once())
            ->method('getFeatureSwitches')
            ->willReturn($response);

        $this->fsRepo
            ->expects($this->never())
            ->method('upsertByName');

        $this->manager->updateSwitchesFromBolt();
    }

    /**
     * @test
     */
    public function updateSwitchesFromBolt_ResponseUptoPlugin()
    {
        $response = new BoltResponse();
        $respObj = [
            "response" => (object)[
                "data" => (object)["plugin"=>"ok"]
            ]
        ];
        $response->setData($respObj);
        $this->gql
            ->expects($this->once())
            ->method('getFeatureSwitches')
            ->willReturn($response);

        $this->fsRepo
            ->expects($this->never())
            ->method('upsertByName');

        $this->manager->updateSwitchesFromBolt();
    }

    /**
     * @test
     */
    public function updateSwitchesFromBolt_ResponseUptoFeatures()
    {
        $response = new BoltResponse();
        $respObj = [
            "response" => (object)[
                "data" => (object)[
                    "plugin"=> (object)["features" => "something"]]
            ]
        ];
        $response->setData($respObj);
        $this->gql
            ->expects($this->once())
            ->method('getFeatureSwitches')
            ->willReturn($response);

        $this->fsRepo
            ->expects($this->never())
            ->method('upsertByName');

        $this->manager->updateSwitchesFromBolt();
    }

    /**
     * @test
     */
    public function updateSwitchesFromBolt_ResponseWithSwitches()
    {
        $response = new BoltResponse();
        $respObj = [
            "response" => (object)[
                "data" => (object)[
                    "plugin"=> (object)[
                        "features" => [
                            "1" => (object) [
                                "name" => "thename",
                                "value" => true,
                                "defaultValue"=> false,
                                "rolloutPercentage" => 37
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $response->setData($respObj);
        $this->gql
            ->expects($this->once())
            ->method('getFeatureSwitches')
            ->willReturn($response);

        $this->fsRepo
            ->expects($this->once())
            ->method('upsertByName')
            ->with("thename", true, false, 37);

        $this->manager->updateSwitchesFromBolt();
    }

    /**
     * @test
     */
    public function updateSwitchesFromBolt_ResponseWithMultipleSwitches()
    {
        $response = new BoltResponse();
        $respObj = [
            "response" => (object)[
                "data" => (object)[
                    "plugin"=> (object)[
                        "features" => [
                            "1" => (object) [
                                "name" => "thename",
                                "value" => true,
                                "defaultValue"=> false,
                                "rolloutPercentage" => 37
                            ],
                            "2" => (object) [
                                "name" => "thename2",
                                "value" => false,
                                "defaultValue"=> true,
                                "rolloutPercentage" => 100
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $response->setData($respObj);
        $this->gql
            ->expects($this->once())
            ->method('getFeatureSwitches')
            ->willReturn($response);

        $this->fsRepo
            ->expects($this->at(0))
            ->method('upsertByName')
            ->with("thename", true, false, 37);
        $this->fsRepo
            ->expects($this->at(1))
            ->method('upsertByName')
            ->with("thename2", false, true, 100);
        $this->fsRepo
            ->expects($this->exactly(2))
            ->method('upsertByName');

        $this->manager->updateSwitchesFromBolt();
    }
}
