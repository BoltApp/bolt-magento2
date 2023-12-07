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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Manager;
use Bolt\Boltpay\Model\Api\FeatureSwitchesHook;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;

class FeatureSwitchesHookTest extends BoltTestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var FeatureSwitchesHook
     */
    private $fsHook;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->configHelper = $this->objectManager->get(ConfigHelper::class);
        $this->fsHook = $this->objectManager->create(FeatureSwitchesHook::class);
    }

    /**
     * @test
     */
    public function workingUpdateFromBolt()
    {
        $fsManager = $this->createMock(Manager::class);
        $fsManager->method('updateSwitchesFromBolt');

        TestHelper::setProperty($this->fsHook,'fsManager', $fsManager);
        $this->fsHook->notifyChanged();

        $response = TestHelper::getProperty($this->fsHook, 'response');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"status":"success"}', $response->getBody());
    }

    /**
     * @test
     */
    public function notWorkingUpdatesFromBolt()
    {
        $this->fsHook->notifyChanged();
        $exceptionMsg = "Something went wrong when talking to Bolt.";
        // in magento version under 2.3.0 zend framework contains a bug in header response regexp parser in class Zend_Http_Response::extractHeaders
        // which is throwing the "Invalid header line detected" exception if response has: "HTTP/2 403" header line.
        if (version_compare($this->configHelper->getStoreVersion(), '2.3.0', '<')) {
            $exceptionMsg = "Invalid header line detected";
        }
        $response = json_decode(TestHelper::getProperty($this->fsHook, 'response')->getBody(), true);
        $this->assertEquals(
            [
                'error' => [
                    'code' => 6001,
                    'message' => $exceptionMsg
                ],
                'status' => 'failure'
            ],
            $response
        );
    }
}
