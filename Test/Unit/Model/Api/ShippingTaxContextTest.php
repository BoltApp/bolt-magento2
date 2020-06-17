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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Model\Api\ShippingTaxContext;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Webapi\Rest\Response;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class ShippingTaxContextTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\ShippingTaxContext
 */
class ShippingTaxContextTest extends TestCase
{
    /**
     * @var HookHelper|MockObject
     */
    private $hookHelper;

    /**
     * @var CartHelper|MockObject
     */
    private $cartHelper;

    /**
     * @var LogHelper|MockObject
     */
    private $logHelper;

    /**
     * @var ConfigHelper|MockObject
     */
    private $configHelper;

    /**
     * @var SessionHelper|MockObject
     */
    private $sessionHelper;

    /**
     * @var DiscountHelper|MockObject
     */
    private $discountHelper;

    /**
     * @var Bugsnag|MockObject
     */
    private $bugsnag;

    /**
     * @var MetricsClient|MockObject
     */
    private $metricsClient;

    /**
     * @var BoltErrorResponse|MockObject
     */
    private $errorResponse;

    /**
     * @var RegionModel|MockObject
     */
    private $regionModel;

    /**
     * @var Response|MockObject
     */
    private $response;

    /**
     * @var ShippingOptionInterfaceFactory|MockObject
     */
    private $shippingOptionFactory;

    /**
     * @var ShippingTaxContext|MockObject
     */
    private $currentMock;

    protected function setUp()
    {
        $this->hookHelper = $this->createMock(HookHelper::class);
        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->sessionHelper = $this->createMock(SessionHelper::class);
        $this->discountHelper = $this->createMock(DiscountHelper::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->metricsClient = $this->createMock(MetricsClient::class);
        $this->errorResponse = $this->createMock(BoltErrorResponse::class);
        $this->regionModel = $this->createMock(RegionModel::class);
        $this->response = $this->createMock(Response::class);
        $this->shippingOptionFactory = $this->createMock(ShippingOptionInterfaceFactory::class);

        $this->currentMock = $this->getMockBuilder(ShippingTaxContext::class)
            ->setConstructorArgs(
                [
                    $this->hookHelper,
                    $this->cartHelper,
                    $this->logHelper,
                    $this->configHelper,
                    $this->sessionHelper,
                    $this->discountHelper,
                    $this->bugsnag,
                    $this->metricsClient,
                    $this->errorResponse,
                    $this->regionModel,
                    $this->response,
                    $this->shippingOptionFactory
                ]
            )
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     *
     * @test
     * that set internal properties
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $instance = new ShippingTaxContext(
            $this->hookHelper,
            $this->cartHelper,
            $this->logHelper,
            $this->configHelper,
            $this->sessionHelper,
            $this->discountHelper,
            $this->bugsnag,
            $this->metricsClient,
            $this->errorResponse,
            $this->regionModel,
            $this->response,
            $this->shippingOptionFactory
        );

        $this->assertAttributeInstanceOf(HookHelper::class, 'hookHelper', $instance);
        $this->assertAttributeInstanceOf(CartHelper::class, 'cartHelper', $instance);
        $this->assertAttributeInstanceOf(LogHelper::class, 'logHelper', $instance);
        $this->assertAttributeInstanceOf(ConfigHelper::class, 'configHelper', $instance);
        $this->assertAttributeInstanceOf(SessionHelper::class, 'sessionHelper', $instance);
        $this->assertAttributeInstanceOf(DiscountHelper::class, 'discountHelper', $instance);
        $this->assertAttributeInstanceOf(Bugsnag::class, 'bugsnag', $instance);
        $this->assertAttributeInstanceOf(MetricsClient::class, 'metricsClient', $instance);
        $this->assertAttributeInstanceOf(BoltErrorResponse::class, 'errorResponse', $instance);
        $this->assertAttributeInstanceOf(RegionModel::class, 'regionModel', $instance);
        $this->assertAttributeInstanceOf(Response::class, 'response', $instance);
        $this->assertAttributeInstanceOf(
            ShippingOptionInterfaceFactory::class,
            'shippingOptionFactory',
            $instance
        );
    }

    /**
     * @test
     * that getHookHelper would returns hook helper instance
     *
     * @covers ::getHookHelper
     */
    public function getHookHelper_always_returnsHookHelper()
    {
        $this->assertEquals($this->hookHelper, $this->currentMock->getHookHelper());
    }

    /**
     * @test
     * that getCartHelper would returns cart helper instance
     *
     * @covers ::getCartHelper
     */
    public function getCartHelper_always_returnsCartHelper()
    {
        $this->assertEquals($this->cartHelper, $this->currentMock->getCartHelper());
    }

    /**
     * @test
     * that getLogHelper would returns log helper instance
     *
     * @covers ::getLogHelper
     */
    public function getLogHelper_always_returnsLogHelper()
    {
        $this->assertEquals($this->logHelper, $this->currentMock->getLogHelper());
    }

    /**
     * @test
     * that getConfigHelper would returns config helper instance
     *
     * @covers ::getConfigHelper
     */
    public function getConfigHelper_always_returnsConfigHelper()
    {
        $this->assertEquals($this->configHelper, $this->currentMock->getConfigHelper());
    }

    /**
     * @test
     * that getSessionHelper would returns session helper instance
     *
     * @covers ::getSessionHelper
     */
    public function getSessionHelper_always_returnsSessionHelper()
    {
        $this->assertEquals($this->sessionHelper, $this->currentMock->getSessionHelper());
    }

    /**
     * @test
     * that getDiscountHelper would returns discount helper instance
     *
     * @covers ::getDiscountHelper
     */
    public function getDiscountHelper_always_returnsDiscountHelper()
    {
        $this->assertEquals($this->discountHelper, $this->currentMock->getDiscountHelper());
    }

    /**
     * @test
     * that getBugsnag would returns bugsnag instance
     *
     * @covers ::getBugsnag
     */
    public function getBugsnag_always_returnsBugsnag()
    {
        $this->assertEquals($this->bugsnag, $this->currentMock->getBugsnag());
    }

    /**
     * @test
     * that getMetricsClient would returns metrics client instance
     *
     * @covers ::getMetricsClient
     */
    public function getMetricsClient_always_returnsMetricsClient()
    {
        $this->assertEquals($this->metricsClient, $this->currentMock->getMetricsClient());
    }

    /**
     * @test
     * that getErrorResponse would returns error response instance
     *
     * @covers ::getErrorResponse
     */
    public function getErrorResponse_always_returnsErrorResponse()
    {
        $this->assertEquals($this->errorResponse, $this->currentMock->getErrorResponse());
    }

    /**
     * @test
     * that getRegionModel would returns region model instance
     *
     * @covers ::getRegionModel
     */
    public function getRegionModel_always_returnsRegionModel()
    {
        $this->assertEquals($this->regionModel, $this->currentMock->getRegionModel());
    }

    /**
     * @test
     * that getResponse would returns response instance
     *
     * @covers ::getResponse
     */
    public function getResponse_always_returnsResponse()
    {
        $this->assertEquals($this->response, $this->currentMock->getResponse());
    }

    /**
     * @test
     * that getShippingOptionFactory would returns shipping option factory instance
     *
     * @covers ::getShippingOptionFactory
     */
    public function getShippingOptionFactory_always_returnsShippingOptionFactory()
    {
        $this->assertEquals(
            $this->shippingOptionFactory,
            $this->currentMock->getShippingOptionFactory()
        );
    }
}
