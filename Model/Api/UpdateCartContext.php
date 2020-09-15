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

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Quote\Model\Quote;
use Magento\Framework\Webapi\Exception as WebApiException;
use Magento\Directory\Model\Region as RegionModel;
use Magento\SalesRule\Model\RuleRepository;
use Magento\SalesRule\Model\Coupon;
use Magento\SalesRule\Model\ResourceModel\Coupon\UsageFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\SalesRule\Model\Rule\CustomerFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Framework\App\CacheInterface;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;

/**
 * Class UpdateCartContext
 *
 * @package Bolt\Boltpay\Model\Api
 */
class UpdateCartContext
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var LogHelper
     */
    protected $logHelper;

    /**
     * @var Bugsnag
     */
    protected $bugsnag;

    /**
     * @var CartHelper
     */
    protected $cartHelper;

    /**
     * @var HookHelper
     */
    protected $hookHelper;

    /**
     * @var BoltErrorResponse
     */
    protected $errorResponse;

    /**
     * @var RegionModel
     */
    protected $regionModel;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * @var RuleRepository
     */
    protected $ruleRepository;

    /**
     * @var UsageFactory
     */
    protected $usageFactory;

    /**
     * @var DataObjectFactory
     */
    protected $objectFactory;

    /**
     * @var TimezoneInterface
     */
    protected $timezone;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var DiscountHelper
     */
    protected $discountHelper;

    /**
     * @var TotalsCollector
     */
    protected $totalsCollector;
    
    /**
     * @var SessionHelper
     */
    protected $sessionHelper;
    
    /**
     * @var CacheInterface
     */
    protected $cache;
    
    /**
     * @var EventsForThirdPartyModules
     */
    protected $eventsForThirdPartyModules;

    /**
     * UpdateCartContext constructor.
     * 
     * Assigns local references to global resources
     *
     * @param Request                 $request
     * @param Response                $response
     * @param HookHelper              $hookHelper
     * @param BoltErrorResponse       $errorResponse
     * @param LogHelper               $logHelper
     * @param Bugsnag                 $bugsnag
     * @param RegionModel             $regionModel
     * @param OrderHelper             $orderHelper
     * @param CartHelper              $cartHelper
     * @param CheckoutSession         $checkoutSession
     * @param RuleRepository          $ruleRepository
     * @param UsageFactory            $usageFactory
     * @param DataObjectFactory       $objectFactory
     * @param TimezoneInterface       $timezone
     * @param CustomerFactory         $customerFactory
     * @param ConfigHelper            $configHelper
     * @param DiscountHelper          $discountHelper
     * @param TotalsCollector         $totalsCollector
     * @param SessionHelper           $sessionHelper
     * @param CacheInterface          $cache
     * @param EventsForThirdPartyModules $eventsForThirdPartyModules
     */
    public function __construct(
        Request $request,
        Response $response,
        HookHelper $hookHelper,
        BoltErrorResponse $errorResponse,
        LogHelper $logHelper,      
        Bugsnag $bugsnag,        
        RegionModel $regionModel,
        OrderHelper $orderHelper,  
        CartHelper $cartHelper,
        CheckoutSession $checkoutSession,
        RuleRepository $ruleRepository,
        UsageFactory $usageFactory,
        DataObjectFactory $objectFactory,
        TimezoneInterface $timezone,
        CustomerFactory $customerFactory,
        ConfigHelper $configHelper,
        DiscountHelper $discountHelper,
        TotalsCollector $totalsCollector,
        SessionHelper $sessionHelper,
        CacheInterface $cache = null,
        EventsForThirdPartyModules $eventsForThirdPartyModules
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->hookHelper = $hookHelper;
        $this->errorResponse = $errorResponse;
        $this->logHelper = $logHelper;       
        $this->bugsnag = $bugsnag;
        $this->regionModel = $regionModel;
        $this->orderHelper = $orderHelper;
        $this->cartHelper = $cartHelper;
        $this->checkoutSession = $checkoutSession;
        $this->ruleRepository = $ruleRepository;
        $this->usageFactory = $usageFactory;
        $this->objectFactory = $objectFactory;
        $this->timezone = $timezone;
        $this->customerFactory = $customerFactory;
        $this->configHelper = $configHelper;
        $this->discountHelper = $discountHelper;
        $this->totalsCollector = $totalsCollector;
        $this->sessionHelper = $sessionHelper;
        $this->cache = $cache ?: \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Framework\App\CacheInterface::class);
        $this->eventsForThirdPartyModules = $eventsForThirdPartyModules;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }
    
    /**
     * @return HookHelper
     */
    public function getHookHelper()
    {
        return $this->hookHelper;
    }
    
    /**
     * @return BoltErrorResponse
     */
    public function getBoltErrorResponse()
    {
        return $this->errorResponse;
    }

    /**
     * @return LogHelper
     */
    public function getLogHelper()
    {
        return $this->logHelper;
    }
    
    /**
     * @return Bugsnag
     */
    public function getBugsnag()
    {
        return $this->bugsnag;
    }
    
    /**
     * @return RegionModel
     */
    public function getRegionModel()
    {
        return $this->regionModel;
    }

    /**
     * @return OrderHelper
     */
    public function getOrderHelper()
    {
        return $this->orderHelper;
    }
    
    /**
     * @return CartHelper
     */
    public function getCartHelper()
    {
        return $this->cartHelper;
    }

    /**
     * @return RuleRepository
     */
    public function getRuleRepository()
    {
        return $this->ruleRepository;
    }

    /**
     * @return UsageFactory
     */
    public function getUsageFactory()
    {
        return $this->usageFactory;
    }

    /**
     * @return DataObjectFactory
     */
    public function getObjectFactory()
    {
        return $this->objectFactory;
    }

    /**
     * @return TimezoneInterface
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * @return CustomerFactory
     */
    public function getCustomerFactory()
    {
        return $this->customerFactory;
    }

    /**
     * @return ConfigHelper
     */
    public function getConfigHelper()
    {
        return $this->configHelper;
    }

    /**
     * @return CheckoutSession
     */
    public function getCheckoutSessionForUnirgyGiftCert()
    {
        return $this->checkoutSessionForUnirgyGiftCert;
    }

    /**
     * @return DiscountHelper
     */
    public function getDiscountHelper()
    {
        return $this->discountHelper;
    }

    /**
     * @return TotalsCollector
     */
    public function getTotalsCollector()
    {
        return $this->totalsCollector;
    }
    
    /**
     * @return SessionHelper
     */
    public function getSessionHelper()
    {
        return $this->sessionHelper;
    }
    
    /**
     * @return Cache
     */
    public function getCache()
    {
        return $this->cache;
    }
    
    /**
     * @return EventsForThirdPartyModules
     */
    public function getEventsForThirdPartyModules()
    {
        return $this->eventsForThirdPartyModules;
    }
    
}
