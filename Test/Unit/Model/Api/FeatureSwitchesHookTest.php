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

namespace Bolt\Boltpay\Test\Unit\Model\Api;


use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Manager;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Model\Api\FeatureSwitchesHook;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\Webapi\ErrorProcessor;
use \Magento\Framework\App\State;
use Magento\Framework\Webapi\Exception;
use Magento\Framework\Webapi\Rest\Response\RendererFactory;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class FeatureSwitchesHookTest extends TestCase
{
    /**
     * @var HookHelper
     */
    private $hookHelper;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var MetricsClient
     */
    private $metricsClient;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /* @var StoreManagerInterface */
    protected $storeManager;

    /* @var Manager */
    protected $fsManager;

    /**
     * @var BoltErrorResponse
     */
    private $errorResponse;

    /**
     * @var FeatureSwitchesHook
     */
    private $fsHook;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $rendererFactory = $this->createMock(RendererFactory::class);
        $errorProcessor = $this->createMock(ErrorProcessor::class);
        $appState = $this->createMock(State::class);

        $this->hookHelper = $this->createMock(HookHelper::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->metricsClient = $this->createMock(MetricsClient::class);
        $this->response = new Response($rendererFactory, $errorProcessor, $appState);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->fsManager = $this->createMock(Manager::class);
        $this->errorResponse = new BoltErrorResponse();


        $this->fsHook = (new ObjectManager($this))->getObject(
            FeatureSwitchesHook::class,
            [
                'hookHelper' => $this->hookHelper,
                'logHelper' => $this->logHelper,
                'metricsClient' => $this->metricsClient,
                'response' => $this->response,
                'configHelper' => $this->configHelper,
                'storeManager' => $this->storeManager,
                'fsManager' => $this->fsManager,
                'errorResponse' => $this->errorResponse,
            ]
        );
    }

    public function testWorkingUpdateFromBolt() {
        $this->fsManager
            ->expects($this->once())
            ->method('updateSwitchesFromBolt');

        $this->fsHook->notifyChanged();

        $this->logHelper
            ->expects($this->never())
        ->method('addInfoLog');

        $this->assertEquals(200, $this->response->getStatusCode());
        $this->assertEquals('{"status":"success"}', $this->response->getBody());
    }

    public function testNotWorkingUpdatesFromBolt() {
        $this->fsManager
            ->expects($this->once())
            ->method('updateSwitchesFromBolt')
            ->willThrowException(new \Exception("oops"));

        $this->logHelper
            ->expects($this->exactly(2))
            ->method('addInfoLog');

        $this->fsHook->notifyChanged();

        $this->assertEquals(
            Exception::HTTP_INTERNAL_ERROR,
            $this->response->getStatusCode());
        $this->assertEquals(
            '{"status":"failure","error":{"code":6001,"message":"oops"}}',
            $this->response->getBody());
    }
}