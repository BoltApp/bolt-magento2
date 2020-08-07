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

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory;

/**
 * Class ShippingTaxContext
 * Common DI objects for Shipping Aand Tax endpoint handlers
 *
 * @package Bolt\Boltpay\Model\Api
 */
class ShippingTaxContext
{
    /**
     * @var HookHelper
     */
    protected $hookHelper;

    /**
     * @var CartHelper
     */
    protected $cartHelper;

    /**
     * @var LogHelper
     */
    protected $logHelper;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var SessionHelper
     */
    protected $sessionHelper;

    /**
     * @var DiscountHelper
     */
    protected $discountHelper;

    /**
     * @var Bugsnag
     */
    protected $bugsnag;

    /**
     * @var MetricsClient
     */
    protected $metricsClient;

    /**
     * @var BoltErrorResponse
     */
    protected $errorResponse;

    /**
     * @var RegionModel
     */
    protected $regionModel;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var ShippingOptionInterfaceFactory
     */
    protected $shippingOptionFactory;

    /**
     * Assigns local references to global resources
     *
     * @param HookHelper $hookHelper
     * @param CartHelper $cartHelper
     * @param LogHelper $logHelper
     * @param ConfigHelper $configHelper
     * @param SessionHelper $sessionHelper
     * @param DiscountHelper $discountHelper
     * @param Bugsnag $bugsnag
     * @param MetricsClient $metricsClient
     * @param BoltErrorResponse $errorResponse
     * @param RegionModel $regionModel
     * @param Response $response
     * @param ShippingOptionInterfaceFactory $shippingOptionFactory
     */
    public function __construct(
        HookHelper $hookHelper,
        CartHelper $cartHelper,
        LogHelper $logHelper,
        ConfigHelper $configHelper,
        SessionHelper $sessionHelper,
        DiscountHelper $discountHelper,
        Bugsnag $bugsnag,
        MetricsClient $metricsClient,
        BoltErrorResponse $errorResponse,
        RegionModel $regionModel,
        Response $response,
        ShippingOptionInterfaceFactory $shippingOptionFactory
    ) {
        $this->hookHelper = $hookHelper;
        $this->cartHelper = $cartHelper;
        $this->logHelper = $logHelper;
        $this->configHelper = $configHelper;
        $this->sessionHelper = $sessionHelper;
        $this->discountHelper = $discountHelper;
        $this->bugsnag = $bugsnag;
        $this->metricsClient = $metricsClient;
        $this->errorResponse = $errorResponse;
        $this->regionModel = $regionModel;
        $this->response = $response;
        $this->shippingOptionFactory = $shippingOptionFactory;
    }

    /**
     * @return HookHelper
     */
    public function getHookHelper()
    {
        return $this->hookHelper;
    }

    /**
     * @return CartHelper
     */
    public function getCartHelper()
    {
        return $this->cartHelper;
    }

    /**
     * @return LogHelper
     */
    public function getLogHelper()
    {
        return $this->logHelper;
    }

    /**
     * @return ConfigHelper
     */
    public function getConfigHelper()
    {
        return $this->configHelper;
    }

    /**
     * @return SessionHelper
     */
    public function getSessionHelper()
    {
        return $this->sessionHelper;
    }

    /**
     * @return DiscountHelper
     */
    public function getDiscountHelper()
    {
        return $this->discountHelper;
    }

    /**
     * @return Bugsnag
     */
    public function getBugsnag()
    {
        return $this->bugsnag;
    }

    /**
     * @return MetricsClient
     */
    public function getMetricsClient()
    {
        return $this->metricsClient;
    }

    /**
     * @return BoltErrorResponse
     */
    public function getErrorResponse()
    {
        return $this->errorResponse;
    }

    /**
     * @return RegionModel
     */
    public function getRegionModel()
    {
        return $this->regionModel;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return ShippingOptionInterfaceFactory
     */
    public function getShippingOptionFactory()
    {
        return $this->shippingOptionFactory;
    }
}
