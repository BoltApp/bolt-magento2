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
use Bolt\Boltpay\Model\ErrorResponse;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\Helper\Bootstrap;
use Bolt\Boltpay\Api\Data\ShipToStoreOptionInterfaceFactory;
use Bolt\Boltpay\Api\Data\StoreAddressInterfaceFactory;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;

/**
 * Class ShippingTaxContextTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\ShippingTaxContext
 */
class ShippingTaxContextTest extends BoltTestCase
{
    /**
     * @var ShippingTaxContext|MockObject
     */
    private $shippingTaxContext;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    protected function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->shippingTaxContext = $this->objectManager->create(ShippingTaxContext::class);
    }

    /**
     * @test
     * that getHookHelper would returns hook helper instance
     *
     * @covers ::getHookHelper
     */
    public function getHookHelper_always_returnsHookHelper()
    {
        $this->assertEquals($this->objectManager->create(HookHelper::class), $this->shippingTaxContext->getHookHelper());
    }

    /**
     * @test
     * that getCartHelper would returns cart helper instance
     *
     * @covers ::getCartHelper
     */
    public function getCartHelper_always_returnsCartHelper()
    {
        $this->assertEquals($this->objectManager->create(CartHelper::class), $this->shippingTaxContext->getCartHelper());
    }

    /**
     * @test
     * that getLogHelper would returns log helper instance
     *
     * @covers ::getLogHelper
     */
    public function getLogHelper_always_returnsLogHelper()
    {
        $this->assertEquals($this->objectManager->create(LogHelper::class), $this->shippingTaxContext->getLogHelper());
    }

    /**
     * @test
     * that getConfigHelper would returns config helper instance
     *
     * @covers ::getConfigHelper
     */
    public function getConfigHelper_always_returnsConfigHelper()
    {
        $this->assertEquals($this->objectManager->create(ConfigHelper::class), $this->shippingTaxContext->getConfigHelper());
    }

    /**
     * @test
     * that getSessionHelper would returns session helper instance
     *
     * @covers ::getSessionHelper
     */
    public function getSessionHelper_always_returnsSessionHelper()
    {
        $this->assertEquals($this->objectManager->create(SessionHelper::class), $this->shippingTaxContext->getSessionHelper());
    }

    /**
     * @test
     * that getDiscountHelper would returns discount helper instance
     *
     * @covers ::getDiscountHelper
     */
    public function getDiscountHelper_always_returnsDiscountHelper()
    {
        $this->assertEquals($this->objectManager->create(DiscountHelper::class), $this->shippingTaxContext->getDiscountHelper());
    }

    /**
     * @test
     * that getBugsnag would returns bugsnag instance
     *
     * @covers ::getBugsnag
     */
    public function getBugsnag_always_returnsBugsnag()
    {
        $this->assertEquals($this->objectManager->create(Bugsnag::class), $this->shippingTaxContext->getBugsnag());
    }

    /**
     * @test
     * that getMetricsClient would returns metrics client instance
     *
     * @covers ::getMetricsClient
     */
    public function getMetricsClient_always_returnsMetricsClient()
    {
        $this->assertEquals($this->objectManager->create(MetricsClient::class), $this->shippingTaxContext->getMetricsClient());
    }

    /**
     * @test
     * that getErrorResponse would returns error response instance
     *
     * @covers ::getErrorResponse
     */
    public function getErrorResponse_always_returnsErrorResponse()
    {
        $this->assertEquals($this->objectManager->create(ErrorResponse::class), $this->shippingTaxContext->getErrorResponse());
    }

    /**
     * @test
     * that getRegionModel would returns region model instance
     *
     * @covers ::getRegionModel
     */
    public function getRegionModel_always_returnsRegionModel()
    {
        $this->assertEquals($this->objectManager->create(RegionModel::class), $this->shippingTaxContext->getRegionModel());
    }

    /**
     * @test
     * that getResponse would returns response instance
     *
     * @covers ::getResponse
     */
    public function getResponse_always_returnsResponse()
    {
        $this->assertEquals($this->objectManager->create(Response::class), $this->shippingTaxContext->getResponse());
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
            $this->objectManager->create(ShippingOptionInterfaceFactory::class),
            $this->shippingTaxContext->getShippingOptionFactory()
        );
    }

    /**
     * @test
     * that getShipToStoreOptionFactory would returns ship to store option factory instance
     *
     * @covers ::getShipToStoreOptionFactory
     */
    public function getShipToStoreOptionFactory_always_returnsShipToStoreOptionFactory()
    {
        $this->assertEquals(
            $this->objectManager->create(ShipToStoreOptionInterfaceFactory::class),
            $this->shippingTaxContext->getShipToStoreOptionFactory()
        );
    }

    /**
     * @test
     * that getStoreAddressFactory would returns store address factory instance
     *
     * @covers ::getStoreAddressFactory
     */
    public function getStoreAddressFactory_always_returnsStoreAddressFactory()
    {
        $this->assertEquals(
            $this->objectManager->create(StoreAddressInterfaceFactory::class),
            $this->shippingTaxContext->getStoreAddressFactory()
        );
    }

    /**
     * @test
     * that getEventsForThirdPartyModules would returns eventsForThirdPartyModules instance
     *
     * @covers ::getEventsForThirdPartyModules
     */
    public function getEventsForThirdPartyModules_always_returnsEventsForThirdPartyModules()
    {
        $this->assertEquals(
            $this->objectManager->create(EventsForThirdPartyModules::class),
            $this->shippingTaxContext->getEventsForThirdPartyModules()
        );
    }
}
