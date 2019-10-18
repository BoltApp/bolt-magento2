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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
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

    public function testUpdateSwitchesFromBolt_EmptyResponse() {
        $this->gql
            ->expects($this->once())
            ->method('getFeatureSwitches')
            ->willReturn(new BoltResponse());

        $this->fsRepo
            ->expects($this->never())
            ->method('upsertByName');

        $this->manager->updateSwitchesFromBolt();
    }

    public function testUpdateSwitchesFromBolt_ResponseWithOnlyResponse() {
        $response = new BoltResponse();
        $respObj = array("response" => "ok");
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

    public function testUpdateSwitchesFromBolt_ResponseUptoData() {
        $response = new BoltResponse();
        $respObj = array("response" => (object)array("data" => "plugin"));
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

    public function testUpdateSwitchesFromBolt_ResponseUptoPlugin() {
        $response = new BoltResponse();
        $respObj = array(
            "response" => (object)array(
                "data" => (object)array("plugin"=>"ok")
            )
        );
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

    public function testUpdateSwitchesFromBolt_ResponseUptoFeatures() {
        $response = new BoltResponse();
        $respObj = array(
            "response" => (object)array(
                "data" => (object)array(
                    "plugin"=> (object)array("features" => "something"))
            )
        );
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

    public function testUpdateSwitchesFromBolt_ResponseWithSwitches() {
        $response = new BoltResponse();
        $respObj = array(
            "response" => (object)array(
                "data" => (object)array(
                    "plugin"=> (object)array(
                        "features" => array(
                            "1" => (object) array(
                                "name" => "thename",
                                "value" => true,
                                "defaultValue"=> false,
                                "rolloutPercentage" => 37
                            )
                        )
                    )
                )
            )
        );
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

    public function testUpdateSwitchesFromBolt_ResponseWithMultipleSwitches() {
        $response = new BoltResponse();
        $respObj = array(
            "response" => (object)array(
                "data" => (object)array(
                    "plugin"=> (object)array(
                        "features" => array(
                            "1" => (object) array(
                                "name" => "thename",
                                "value" => true,
                                "defaultValue"=> false,
                                "rolloutPercentage" => 37
                            ),
                            "2" => (object) array(
                                "name" => "thename2",
                                "value" => false,
                                "defaultValue"=> true,
                                "rolloutPercentage" => 100
                            )
                        )
                    )
                )
            )
        );
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