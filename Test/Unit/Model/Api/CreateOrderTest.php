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

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Model\Api\CreateOrder;

/**
 * Class CreateOrderTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 */
class CreateOrderTest extends TestCase
{
    const STORE_ID = 1;
    const MINIMUM_ORDER_AMOUNT = 50;

    /**
     * @var HookHelper
     */
    private $hookHelper;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

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

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @var BackendUrl
     */
    private $backendUrl;

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var SessionHelper
     */
    private $sessionHelper;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $currentMock;

    /**
     * @var Quote
     */
    private $quoteMock;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    private function initRequiredMocks()
    {
        $this->hookHelper = $this->createMock(HookHelper::class);
        $this->orderHelper = $this->createMock(OrderHelper::class);
        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->request = $this->createMock(Request::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->metricsClient = $this->createMock(MetricsClient::class);
        $this->response = $this->createMock(Response::class);
        $this->url = $this->createMock(UrlInterface::class);
        $this->backendUrl = $this->createMock(BackendUrl::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->stockRegistry = $this->createMock(StockRegistryInterface::class);
        $this->sessionHelper = $this->createMock(SessionHelper::class);

        $this->quoteMock = $this->createMock(Quote::class);
    }

    private function initCurrentMock()
    {
        $this->currentMock = $this->getMockBuilder(CreateOrder::class)
            ->setConstructorArgs([
                $this->hookHelper,
                $this->orderHelper,
                $this->cartHelper,
                $this->logHelper,
                $this->request,
                $this->bugsnag,
                $this->metricsClient,
                $this->response,
                $this->url,
                $this->backendUrl,
                $this->configHelper,
                $this->stockRegistry,
                $this->sessionHelper
            ])
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     * @test
     */
    public function validateMinimumAmount_valid()
    {
        $this->quoteMock->expects(static::once())->method('validateMinimumAmount')->willReturn(true);
        $this->currentMock->validateMinimumAmount($this->quoteMock);
    }

    /**
     * @test
     */
    public function validateMinimumAmount_invalid()
    {
        $this->quoteMock->expects(static::once())->method('validateMinimumAmount')->willReturn(false);
        $this->quoteMock->expects(static::once())->method('getStoreId')->willReturn(static::STORE_ID);
        $this->configHelper->expects(static::once())->method('getMinimumOrderAmount')->with(static::STORE_ID)
            ->willReturn(static::MINIMUM_ORDER_AMOUNT);
        $this->bugsnag->expects(static::once())->method('registerCallback');
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(\Bolt\Boltpay\Model\Api\CreateOrder::E_BOLT_MINIMUM_PRICE_NOT_MET);
        $this->expectExceptionMessage(
            sprintf(
                'The minimum order amount: %s has not being met.', static::MINIMUM_ORDER_AMOUNT
            )
        );
        $this->currentMock->validateMinimumAmount($this->quoteMock);
    }
}
