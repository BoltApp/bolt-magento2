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

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\Data\ShippingDataInterface;
use Bolt\Boltpay\Api\ShippingInterface;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Api\Data\ShippingDataInterfaceFactory;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\App\CacheInterface;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Magento\Quote\Model\ShippingMethodManagement;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Model\Data\Region;
use Bolt\Boltpay\Model\Api\ShippingTaxContext;
use Magento\Framework\App\ObjectManager;

/**
 * Class ShippingMethods
 * Shipping options hook endpoint. Get shipping methods using shipping address and cart details
 *
 * @package Bolt\Boltpay\Model\Api
 */
class Shipping extends ShippingTax implements ShippingInterface
{
    const NO_SHIPPING_SERVICE = 'No Shipping Required';
    const NO_SHIPPING_REFERENCE = 'noshipping';

    CONST METRICS_SUCCESS_KEY = 'shipping.success';
    CONST METRICS_FAILURE_KEY = 'shipping.failure';
    CONST METRICS_LATENCY_KEY = 'shipping.latency';

    /**
     * @var HookHelper
     */
    protected $hookHelper;

    /**
     * @var CartHelper
     */
    protected $cartHelper;

    /**
     * @var RegionModel
     */
    protected $regionModel;

    /**
     * @var ShippingDataInterfaceFactory
     */
    protected $shippingDataInterfaceFactory;

    /**
     * @var ShippingOptionInterfaceFactory
     */
    protected $shippingOptionInterfaceFactory;

    /**
     * @var Bugsnag
     */
    protected $bugsnag;

    /**
     * @var MetricsClient
     */
    protected $metricsClient;

    /**
     * @var LogHelper
     */
    protected $logHelper;

    /**
     * @var BoltErrorResponse
     */
    protected $errorResponse;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /** @var SessionHelper */
    protected $sessionHelper;

    /** @var DiscountHelper */
    protected $discountHelper;

    /** @var Quote */
    protected $quote;

    /**
     * @var ShippingMethodManagement
     */
    protected $shippingMethodManagement;

    /**
     * Customer address interface factory.
     * @var AddressInterfaceFactory $addressFactory
     */
    protected $addressFactory;

    /**
     * @var ShippingTaxContext
     */
    protected $shippingTaxContext;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * Assigns local references to global resources
     *
     * @param ShippingTaxContext $shippingTaxContext
     *
     * @param ShippingDataInterfaceFactory $shippingDataInterfaceFactory
     * @param ShippingOptionInterfaceFactory $shippingOptionInterfaceFactory
     * @param ShippingMethodManagement $shippingMethodManagement
     * @param AddressInterfaceFactory $addressFactory
     */
    public function __construct(
        ShippingTaxContext $shippingTaxContext,

        ShippingDataInterfaceFactory $shippingDataInterfaceFactory,
        ShippingOptionInterfaceFactory $shippingOptionInterfaceFactory,
        ShippingMethodManagement $shippingMethodManagement,
        AddressInterfaceFactory $addressFactory
    ) {
        parent::__construct($shippingTaxContext);

        $this->shippingDataInterfaceFactory = $shippingDataInterfaceFactory;
        $this->shippingOptionInterfaceFactory = $shippingOptionInterfaceFactory;
        $this->shippingMethodManagement = $shippingMethodManagement;
        $this->addressFactory = $addressFactory;
    }

    public function generateResult($addressData, $shipping_option)
    {
        $shippingOptionsModel = $this->shippingEstimation($this->quote, $addressData);
        return $shippingOptionsModel;
    }

    /**
     * Get Shipping options from cache or run the Shipping options collection routine, store it in cache and return.
     *
     * @param Quote $quote
     * @param array $addressData
     *
     * @return ShippingDataInterface
     * @throws LocalizedException
     */
    public function shippingEstimation($quote, $addressData)
    {
        ////////////////////////////////////////////////////////////////////////////////////////
        // Check cache storage for estimate. If the quote_id, total_amount, items, country_code,
        // applied rules (discounts), region and postal_code match then use the cached version.
        ////////////////////////////////////////////////////////////////////////////////////////
        if ($prefetchShipping = $this->configHelper->getPrefetchShipping($quote->getStoreId()) && false) {
            // use parent quote id for caching.
            // if everything else matches the cache is used more efficiently this way
            $parentQuoteId = $quote->getBoltParentQuoteId();
            // Take into account external data applied to quote in thirt party modules
            $externalData = $this->applyExternalQuoteData($quote);

            $cacheIdentifier = $parentQuoteId.'_'.round($quote->getSubtotal()*100).'_'.
                $addressData['country_code']. '_'.$addressData['region'].'_'.$addressData['postal_code']. '_'.
                @$addressData['street_address1'].'_'.@$addressData['street_address2'].'_'.$externalData;

            // include products in cache key
            foreach ($quote->getAllVisibleItems() as $item) {
                $cacheIdentifier .= '_'.trim($item->getSku()).'_'.$item->getQty();
            }

            // include applied rule ids (discounts) in cache key
            $ruleIds = str_replace(',', '_', $quote->getAppliedRuleIds());
            if ($ruleIds) {
                $cacheIdentifier .= '_'.$ruleIds;
            }

            // extend cache identifier with custom address fields
            $cacheIdentifier .= $this->cartHelper->convertCustomAddressFieldsToCacheIdentifier($quote);

            $cacheIdentifier = md5($cacheIdentifier);

            if ($serialized = $this->cache->load($cacheIdentifier)) {
                return unserialize($serialized);
            }
        }
        ////////////////////////////////////////////////////////////////////////////////////////

        $shippingMethods = $this->getShippingOptionsArray($quote, $addressData);
        $shippingOptionsModel = $this->getShippingOptionsData($shippingMethods);

        // Cache the calculated result
        if ($prefetchShipping) {
            $this->cache->save(serialize($shippingOptionsModel), $cacheIdentifier, [], 3600);
        }

        return $shippingOptionsModel;
    }

    /**
     * Set shipping methods to the ShippingOptions object
     *
     * @param ShippingOptionInterface[] $shippingMethods
     */
    protected function getShippingOptionsData($shippingMethods)
    {
        $shippingOptionsModel = $this->shippingDataInterfaceFactory->create();
        $shippingOptionsModel->setShippingOptions($shippingMethods);
        return $shippingOptionsModel;
    }

    protected function restrictedMethodCaller($object, $methodName, ...$params)
    {
        // Call protected method with a Closure proxy
        $methodCaller = function ($methodName, ...$params) {
            return $this->$methodName(...$params);
        };
        return $methodCaller->call($object, $methodName, ...$params);
    }

    /**
     * Collects shipping options for the quote and received address data
     *
     * @param Quote $quote
     * @param array $addressData
     *
     * @return ShippingOptionInterface[]
     */
    public function getShippingOptionsArray($quote, $addressData)
    {
        if ($quote->isVirtual()) {
            return [
                $this->shippingOptionInterfaceFactory
                    ->create()
                    ->setService(self::NO_SHIPPING_SERVICE)
                    ->setCost(0)
                    ->setReference(self::NO_SHIPPING_REFERENCE)
            ];
        }

        $regionInstance = $this->regionModel->loadByName(@$addressData['region'], @$addressData['country_code']);

        $addressData = $this->reformatAddressData($addressData);

        $region = $this->objectManager->create(
            'Magento\Customer\Model\Data\Region',
            [
                Region::REGION => $addressData['region'],
                Region::REGION_ID => $addressData['region_id'],
                Region::REGION_CODE => $regionInstance->getCode()
            ]
        );

        /**
         * @var \Magento\Framework\Api\ExtensibleDataInterface $address
         */
        $address = $this->addressFactory->create()
            ->setCountryId($addressData['country_id'])
            ->setPostcode($addressData['postcode'])
            ->setRegionId($addressData['region_id'])
            ->setRegion($region);
        /**
         * @var \Magento\Quote\Api\Data\ShippingMethodInterface[] $shippingMethodArray
         */
        $shippingMethodArray = $this->restrictedMethodCaller(
            $this->shippingMethodManagement,
            'getEstimatedRates',
            $quote,
            $addressData['country_id'],
            $addressData['postcode'],
            $addressData['region_id'],
            $addressData['region'],
            $address
        );

        $shippingMethods = [];
        $errors = [];

        foreach ($shippingMethodArray as $shippingMethod) {
            $service = $shippingMethod->getCarrierTitle() . ' - ' . $shippingMethod->getMethodTitle();
            $method  = $shippingMethod->getCarrierCode() . '_' . $shippingMethod->getMethodCode();

            $cost = $shippingMethod->getAmount();
            $currencyCode = $quote->getQuoteCurrencyCode();
            $roundedCost = CurrencyUtils::toMinor($cost, $currencyCode);

            $error = $shippingMethod->getErrorMessage();

            if ($error) {
                $errors[] = [
                    'service'    => $service,
                    'reference'  => $method,
                    'cost'       => $roundedCost,
                    'error'      => $error
                ];
                continue;
            }

            $shippingMethods[] = $this->shippingOptionInterfaceFactory
                ->create()
                ->setService($service)
                ->setCost($roundedCost)
                ->setReference($method);
        }

        if ($errors) {
            $this->bugsnag->registerCallback(function ($report) use ($errors, $addressData) {
                $report->setMetaData([
                    'SHIPPING METHOD' => [
                        'address' => $addressData,
                        'errors'  => $errors
                    ]
                ]);
            });

            $this->bugsnag->notifyError('Shipping Method Error', $error);
        }

        if (!$shippingMethods) {
            $this->bugsnag->registerCallback(function ($report) use ($quote, $addressData) {
                $report->setMetaData([
                    'SHIPPING' => [
                        'address' => $addressData,
                        'immutable quote ID' => $quote->getId(),
                        'parent quote ID' => $quote->getBoltParentQuoteId(),
                        'order increment ID' => $quote->getReservedOrderId(),
                        'Store Id'  => $quote->getStoreId()
                    ]
                ]);
            });

            throw new BoltException(
                __('No Shipping Methods retrieved'),
                null,
                BoltErrorResponse::ERR_SERVICE
            );
        }
        return $shippingMethods;
    }
}
