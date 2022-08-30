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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\Grabagun;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Api\Data\StoreAddressInterfaceFactory;
use Bolt\Boltpay\Api\Data\ShipToStoreOptionInterfaceFactory;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\GraphQl\Query\Resolver\Argument\SearchCriteria\Builder;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Grabagun\DealerLocator\Api\DealerRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Bolt\Boltpay\Helper\Order as OrderHelper;

class InStorePickup
{
    const GOOGLE_ENDPOINT = 'https://maps.google.com/maps/api/geocode/json';

    /**
     * @var Bugsnag Bugsnag helper instance
     */
    private $bugsnagHelper;

    /**
     * @var Builder
     */
    private $searchCriteriaBuilder;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @var FilterGroupBuilder
     */
    private $filterGroupBuilder;

    /**
     * @var DateTimeFactory
     */
    private $dateTimeFactory;

    /**
     * @var Formatter
     */
    private $dealerFormatter;

    /**
     * @var DealerRepositoryInterface
     */
    private $dealerRepository;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var Json
     */
    private $json;

    /**
     * Core store config
     *
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * InStorePickup constructor.
     * @param Bugsnag $bugsnagHelper
     * @param Builder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param DateTimeFactory $dateTimeFactory
     * @param StoreAddressInterfaceFactory $storeAddressFactory
     * @param ShipToStoreOptionInterfaceFactory $shipToStoreOptionFactory
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        Builder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        DateTimeFactory $dateTimeFactory,
        StoreAddressInterfaceFactory $storeAddressFactory,
        ShipToStoreOptionInterfaceFactory $shipToStoreOptionFactory,
        ClientInterface $client,
        Json $json,
        ScopeConfigInterface $scopeConfig,
        OrderHelper $orderHelper
    )
    {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->storeAddressFactory = $storeAddressFactory;
        $this->shipToStoreOptionFactory = $shipToStoreOptionFactory;
        $this->client = $client;
        $this->json = $json;
        $this->scopeConfig = $scopeConfig;
        $this->orderHelper = $orderHelper;
    }

    /**
     * @param array $result
     * @param Magento\Quote\Model\Quote $quote
     * @param array $shippingOptions
     * @param array $addressData
     * @return array
     */
    public function getShipToStoreOptions(
        $result,
        \Grabagun\DealerLocator\Api\DealerRepositoryInterface $dealerRepository,
        \Grabagun\DealerLocator\Model\Resolver\Formatter $dealerFormatter,
        \Grabagun\Shipping\Helper\ShippingMethodHelper $grabgunShippingMethodHelper,
        $quote,
        $shippingOptions,
        $addressData
    )
    {
        try {
            $tmpShippingOptions = [];

            $fflOnlyQuote = true;
            $quoteItems = $quote->getAllItems();
            foreach ($quoteItems as $item) {
                if (!$grabgunShippingMethodHelper->itemShippedToFflDealer($item->getSku())) {
                    $fflOnlyQuote = false;
                    break;
                }
            }
            $shipToStoreDescription = '';
            foreach ($shippingOptions as $shippingOption) {
                if ($shippingOption->getReference() == 'firearmshipping_firearmshipping_standard') {
                    $shipToStoreDescription = $shippingOption->getService();
                }
            }
            if ($fflOnlyQuote) {
                $this->dealerFormatter = $dealerFormatter;
                $this->dealerRepository = $dealerRepository;
                $getGeoCodesForAddress = $this->getGeoCodesForAddress($addressData);
                // set default to 10 miles

                $defaultRadius = $this->scopeConfig->getValue('dealerlocator/dealerlocator/default_radius', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                $args = [
                    'currentPage' => 1,
                    'distance' => [
                        'distance' => $defaultRadius,
                        'lat' => $getGeoCodesForAddress['lat'],
                        'lng' => $getGeoCodesForAddress['lng']
                    ],
                    'pageSize' => 25,
                ];
                $searchCriteria = $this->searchCriteriaBuilder->build('dealers', $args);
                $searchCriteria->setCurrentPage($args['currentPage']);
                $searchCriteria->setPageSize($args['pageSize']);
                if (isset($args['search'])) {
                    $this->addSearchTerm($args['search'], $searchCriteria);
                }
                if ($this->validateDistance($args)) {
                    $this->addDistanceFilter($args['distance'], $searchCriteria);
                }
                if (isset($args['sotOnly'])) {
                    $this->addSotFilter((bool)$args['sotOnly'], $searchCriteria);
                }

                $this->addBlockedFilter($searchCriteria);
                $this->addExpiredFilter($searchCriteria);

                $dealers = $this->dealerRepository->getList($searchCriteria);
                $shipToStoreOptions = [];
                foreach ($dealers->getItems() as $dealer) {
                    $storeAddress = $this->storeAddressFactory->create();
                    $storeAddress->setStreetAddress1($dealer->getAddress());
                    $storeAddress->setLocality($dealer->getCity());
                    $storeAddress->setRegion($dealer->getState());
                    $storeAddress->setPostalCode($dealer->getZipcode());
                    $storeAddress->setCountryCode('US');
                    $shipToStoreOption = $this->shipToStoreOptionFactory->create();

                    $shipToStoreOption->setReference($dealer->getId());
                    $shipToStoreOption->setStoreName($dealer->getDealerName());
                    $shipToStoreOption->setAddress($storeAddress);
                    $shipToStoreOption->setDistance($defaultRadius);
                    $shipToStoreOption->setDistanceUnit('mile');
                    $shipToStoreOption->setDescription($shipToStoreDescription);
                    $shipToStoreOptions[] = $shipToStoreOption;
                }

                $result = [$shipToStoreOptions, []];
            } else {
                $result = [$tmpShippingOptions, $shippingOptions];
            }

        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
            return [];
        }
        return $result;
    }

    /**
     * Adds search filter to results
     *
     * @param string $term
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchCriteriaInterface
     */
    private function addSearchTerm(
        string $term,
        SearchCriteriaInterface $searchCriteria
    ): SearchCriteriaInterface
    {
        $searchTermFilter = $this->filterBuilder
            ->setField('dealer_name')
            ->setValue('%' . $term . '%')
            ->setConditionType('like')
            ->create();
        $this->filterGroupBuilder->addFilter($searchTermFilter);
        $filterGroups = $searchCriteria->getFilterGroups();
        $filterGroups[] = $this->filterGroupBuilder->create();
        $searchCriteria->setFilterGroups($filterGroups);
        $searchCriteria->setRequestName('quick_search_container');
        return $searchCriteria;
    }

    /**
     * Adds sotOnly filter to results
     *
     * @param bool $sotOnly
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchCriteriaInterface
     */
    private function addSotFilter(
        bool $sotOnly,
        SearchCriteriaInterface $searchCriteria
    ): SearchCriteriaInterface
    {
        $sotOnlyFilter = $this->filterBuilder
            ->setField('is_class3')
            ->setValue($sotOnly)
            ->setConditionType('eq')
            ->create();
        $this->filterGroupBuilder->addFilter($sotOnlyFilter);
        $filterGroups = $searchCriteria->getFilterGroups();
        $filterGroups[] = $this->filterGroupBuilder->create();
        $searchCriteria->setFilterGroups($filterGroups);
        return $searchCriteria;
    }

    /**
     * Adds distance filter to results
     *
     * @param array $distanceArgs
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchCriteriaInterface
     */
    private function addDistanceFilter(
        array $distanceArgs,
        SearchCriteriaInterface $searchCriteria
    ): SearchCriteriaInterface
    {
        $searchDistanceFilter = $this->filterBuilder
            ->setField('distance')
            ->setValue($distanceArgs['lat'] . ',' . $distanceArgs['lng'] . ',' . $distanceArgs['distance'])
            ->setConditionType('distance')
            ->create();
        $this->filterGroupBuilder->addFilter($searchDistanceFilter);
        $filterGroups = $searchCriteria->getFilterGroups();
        $filterGroups[] = $this->filterGroupBuilder->create();
        $searchCriteria->setFilterGroups($filterGroups);
        return $searchCriteria;
    }

    /**
     * Validates arguments for distance filter
     *
     * @param array $args
     * @return bool
     */
    private function validateDistance(array $args): bool
    {
        if (isset($args['distance'])) {
            if (
                isset($args['distance']['lat']) &&
                isset($args['distance']['lng']) &&
                isset($args['distance']['distance'])
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Filters out dealers that are configured to be blocked
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchCriteriaInterface
     */
    private function addBlockedFilter(SearchCriteriaInterface $searchCriteria): SearchCriteriaInterface
    {
        $blockedFilter = $this->filterBuilder
            ->setField('blocked')
            ->setValue(1)
            ->setConditionType('neq')
            ->create();
        $this->filterGroupBuilder->addFilter($blockedFilter);
        $filterGroups = $searchCriteria->getFilterGroups();
        $filterGroups[] = $this->filterGroupBuilder->create();
        $searchCriteria->setFilterGroups($filterGroups);
        return $searchCriteria;
    }

    /**
     * Filters out dealers that are expired
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchCriteriaInterface
     */
    private function addExpiredFilter(SearchCriteriaInterface $searchCriteria): SearchCriteriaInterface
    {
        /** @var DateTime $currentDate */
        $currentDate = $this->dateTimeFactory->create();

        $expiredFilter = $this->filterBuilder
            ->setField('expiration_date')
            ->setValue($currentDate->date())
            ->setConditionType('gt')
            ->create();
        $hasExpiredDateFilter = $this->filterBuilder
            ->setField('expiration_date')
            ->setConditionType('null')
            ->create();
        // OR filter setup so that null expiration dates are not filtered out
        $this->filterGroupBuilder->addFilter($hasExpiredDateFilter);
        $this->filterGroupBuilder->addFilter($expiredFilter);
        $filterGroups = $searchCriteria->getFilterGroups();
        $filterGroups[] = $this->filterGroupBuilder->create();
        $searchCriteria->setFilterGroups($filterGroups);
        return $searchCriteria;
    }

    /**
     * @return mixed
     */
    private function getGeoCodesForAddress($address)
    {
        $queryString = http_build_query([
            'key' => $this->scopeConfig->getValue('dealerlocator/dealerlocator/google_api_key', 'store'),
            'components' => implode('|', [
                'country:' . $address['country_code'],
                'postal_code:' . $address['postal_code'],
                'locality:' . $address['locality'],
            ]),
            'address' => $address['street_address1'] . ', ' . $address['postal_code'] . ' ' . $address['locality'],
        ]);

        $this->client->get(self::GOOGLE_ENDPOINT . '?' . $queryString);
        if ($this->client->getStatus() !== 200) {
            throw new LocalizedException(__('Unable to connect google API for geocoding'));
        }

        $res = $this->json->unserialize($this->client->getBody());

        if ($res['status'] !== 'OK') {
            throw new LocalizedException(__('Unable to geocode address %1', $queryString));
        }

        foreach ($res['results'] as $result) {
            $location = $result['geometry']['location'];
            $geoCodesForAddress = [
                'lat' => (float)$location['lat'],
                'lng' => (float)$location['lng'],
            ];

        }

        return $geoCodesForAddress;
    }

    /**
     * @param $result
     * @param \Grabagun\Shipping\Model\ShippingMethodManagement $grabgunShippingMethodManagement
     * @param $quote
     * @param $ship_to_store_option
     * @param $addressData
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getShipToStoreCarrierMethodCodes(
        $result,
        \Grabagun\Shipping\Model\ShippingMethodManagement $grabgunShippingMethodManagement,
        $quote,
        $ship_to_store_option,
        $addressData
    )
    {
        $dealerStoreId = $ship_to_store_option['reference'];
        if ($dealerStoreId) {
            $shippingMethods = $grabgunShippingMethodManagement->estimateByDealerId($quote->getId(), $dealerStoreId);
            if ($shippingMethods) {
                $dealerShipingMethod = $shippingMethods[0];
                $quote->getShippingAddress()->setShippingMethod($dealerShipingMethod->getCarrierCode() . '_' . $dealerShipingMethod->getMethodCode());
                return [$dealerShipingMethod->getCarrierCode(), $dealerShipingMethod->getMethodCode()];
            }
        }
    }

    /**
     * @param Magento\InventoryInStorePickupQuote\Model\ToQuoteAddress $addressConverter
     * @param Magento\InventoryInStorePickupApi\Model\GetPickupLocationInterface $getPickupLocation
     * @param Magento\Quote\Model\Quote $quote
     * @param \stdClass $transaction
     * @return array
     */
    public function setInStoreShippingAddressForPrepareQuote(
        $quote,
        $transaction
    )
    {
        try {
            if (isset($transaction->order->cart->in_store_shipments[0]->shipment)) {
                $shipment = $transaction->order->cart->in_store_shipments[0]->shipment;
                $address = $transaction->order->cart->in_store_shipments[0]->address ?? null;
                if ($address) {
                    $address->country_code = 'US';
                    $this->orderHelper->setAddress($quote->getShippingAddress(), $address);
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }

    /**
     * @param $quote
     * @param \Grabagun\Shipping\Model\ShippingMethodManagement $shippingMethodManagement
     * @param $transaction
     */
    public function setInStoreShippingMethodForPrepareQuote(
        \Grabagun\Shipping\Model\ShippingMethodManagement $shippingMethodManagement,
        $quote,
        $transaction
    )
    {
        try {
            if (isset($transaction->order->cart->in_store_shipments[0]->shipment)) {
                $shipment = $transaction->order->cart->in_store_shipments[0]->shipment;
                $shippingMethods = $shippingMethodManagement->estimateByDealerId($quote->getId(), $shipment->reference);
                if ($shippingMethods) {
                    $dealerShipingMethod = $shippingMethods[0];
                    $shippingAddress = $quote->getShippingAddress();
                    $shippingAddress->setCollectShippingRates(true);
                    $shippingAddress->setShippingMethod($dealerShipingMethod->getCarrierCode() . '_' . $dealerShipingMethod->getMethodCode())->save();
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }


    /**
     * @param Magento\InventoryInStorePickupQuote\Model\ToQuoteAddress $addressConverter
     * @param Magento\InventoryInStorePickupApi\Model\GetPickupLocationInterface $getPickupLocation
     * @param Magento\Quote\Model\Quote $quote
     * @param \stdClass $transaction
     * @return array
     */
    public function isInStorePickupShipping(
        $result,
        $quote,
        $transaction
    )
    {
        if (isset($transaction->order->cart->in_store_shipments[0]->address)) {
            $address = $transaction->order->cart->in_store_shipments[0]->address ?? null;
            return $address;
        }

        return $result;
    }
}
