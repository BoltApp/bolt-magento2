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
use Grabagun\Shipping\Model\Shipping\Config;
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
use Magento\Customer\Model\CustomerFactory;
use Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory;

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
     * @var \Magento\Customer\Model\Session
     */
    private $customerSession;

    /** @var \Magento\Framework\UrlInterface $urlInterface */
    private $urlInterface;

    /** @var CustomerFactory */
    private $customerFactory;

    /**
     * @var ShippingOptionInterfaceFactory
     */
    protected $shippingOptionFactory;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $appState;
    protected $boltTax;

    /**
     * InStorePickup constructor.
     * @param Bugsnag $bugsnagHelper
     * @param Builder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param DateTimeFactory $dateTimeFactory
     * @param StoreAddressInterfaceFactory $storeAddressFactory
     * @param ShipToStoreOptionInterfaceFactory $shipToStoreOptionFactory
     * @param ClientInterface $client
     * @param Json $json
     * @param ScopeConfigInterface $scopeConfig
     * @param OrderHelper $orderHelper
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\UrlInterface $urlInterface
     * @param CustomerFactory $customerFactory
     * @param ShippingOptionInterfaceFactory $shippingOptionFactory
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
        OrderHelper $orderHelper,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\UrlInterface $urlInterface,
        CustomerFactory $customerFactory,
        ShippingOptionInterfaceFactory $shippingOptionFactory,
        \Magento\Framework\App\State $appState,
        \Bolt\Boltpay\Model\Api\Tax $boltTax
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
        $this->customerSession = $customerSession;
        $this->urlInterface = $urlInterface;
        $this->customerFactory = $customerFactory;
        $this->shippingOptionFactory = $shippingOptionFactory;
        $this->appState = $appState;
        $this->boltTax = $boltTax;
    }

    /**
     * @param $result
     * @param \Grabagun\Shipping\Model\ShippingMethodManagement $shippingMethodManagement
     * @param DealerRepositoryInterface $dealerRepository
     * @param \Grabagun\DealerLocator\Model\Resolver\Formatter $dealerFormatter
     * @param \Grabagun\Shipping\Helper\ShippingMethodHelper $grabgunShippingMethodHelper
     * @param \Grabagun\DealerLocator\Model\ResourceModel\Dealer\CollectionFactory $grabagunCollectionFactory
     * @param $quote
     * @param $shippingOptions
     * @param $addressData
     * @return array
     */
    public function getShipToStoreOptions(
        $result,
        \Grabagun\Shipping\Model\ShippingMethodManagement $shippingMethodManagement,
        \Grabagun\DealerLocator\Api\DealerRepositoryInterface $dealerRepository,
        \Grabagun\DealerLocator\Model\Resolver\Formatter $dealerFormatter,
        \Grabagun\Shipping\Helper\ShippingMethodHelper $grabgunShippingMethodHelper,
        \Grabagun\DealerLocator\Model\ResourceModel\Dealer\CollectionFactory $grabagunCollectionFactory,
        $quote,
        $shippingOptions,
        $addressData
    )
    {
        try {
            $tmpShippingOptions = [];

            $fflOnlyQuote = true;
            $quoteItems = $quote->getAllItems();
            $typeOfShipment = $this->getTypeOfShipment($grabgunShippingMethodHelper, $quote);

            if ($typeOfShipment == \Grabagun\Shipping\Model\Shipping\Config::NON_FFL_SHIPPING_ITEMS_ONLY) {
                $result = [$tmpShippingOptions, $shippingOptions];
            } else {
                $shipToStoreDescription = '';
                $shipToStoreCost = 0;
                $shipToStoreMethod = '';

                $this->dealerFormatter = $dealerFormatter;
                $this->dealerRepository = $dealerRepository;
                $getGeoCodesForAddress = $this->getGeoCodesForAddress($addressData);
                // set default to 100 miles
                $defaultRadius = $this->scopeConfig->getValue('payment/boltpay/grab_gun_default_maximum_number_of_instance', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                $defaultPageSize = $this->scopeConfig->getValue('payment/boltpay/grab_gun_default_maximum_number_of_results', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                $args = [
                    'currentPage' => 1,
                    'distance' => [
                        'distance' => $defaultRadius,
                        'lat' => $getGeoCodesForAddress['lat'],
                        'lng' => $getGeoCodesForAddress['lng']
                    ],
                    'pageSize' => $defaultPageSize,
                ];
                $doesNfaItemExistInCart = $this->doesNfaItemExistInCart($quote);
                if ($doesNfaItemExistInCart) {
                    $args['sotOnly'] = 1;
                }
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

                $preferredStoreId = false;
                if ($this->customerSession->isLoggedIn()) {
                    $preferredStoreId = (int)$this->customerSession->getCustomer()->getData(\Grabagun\DealerLocator\Model\CustomerAttributes::PREFERRED_DEALER_ATTR_CODE);
                }
                foreach ($dealers->getItems() as $dealer) {
                    if ($dealer->getId() == $preferredStoreId) {
                        continue;
                    }
                    $shippingMethods = $shippingMethodManagement->estimateByDealerId($quote->getId(), $dealer->getId());
                    if ($shippingMethods) {
                        $dealerShipingMethod = $shippingMethods[0];
                        $shipToStoreDescription = $dealerShipingMethod->getMethodTitle();
                        $shipToStoreMethod = $dealerShipingMethod->getMethodCode();
                        $shipToStoreCarrierCode = $dealerShipingMethod->getCarrierCode();
                        $shipToStoreCost = \Bolt\Boltpay\Helper\Shared\CurrencyUtils::toMinor($dealerShipingMethod->getAmount(), $quote->getBaseCurrencyCode());
                    }
                    $distance = $this->vincentyGreatCircleDistance($getGeoCodesForAddress['lat'], $getGeoCodesForAddress['lng'], $dealer->getLat(), $dealer->getLng());
                    $storeAddress = $this->storeAddressFactory->create();
                    $storeAddress->setStreetAddress1($dealer->getAddress());
                    $storeAddress->setLocality($dealer->getCity());
                    $storeAddress->setRegion($dealer->getState());
                    $storeAddress->setPostalCode($dealer->getZipcode());
                    $storeAddress->setCountryCode('US');
                    /** @var \Bolt\Boltpay\Api\Data\ShipToStoreOptionInterface $shipToStoreOption */
                    $shipToStoreOption = $this->shipToStoreOptionFactory->create();

                    $shipToStoreOption->setReference($shipToStoreMethod . '+' . $dealer->getId() . '+' . $shipToStoreCarrierCode);
                    $shipToStoreOption->setStoreName($dealer->getDealerName());
                    $shipToStoreOption->setAddress($storeAddress);
                    $shipToStoreOption->setDistance($distance);
                    $shipToStoreOption->setDistanceUnit('mile');
                    $shipToStoreOption->setDescription($shipToStoreDescription);
                    $shipToStoreOption->setCost($shipToStoreCost);
                    $shipToStoreOptions[] = $shipToStoreOption;
                }


                if ($preferredStoreId) {
                    $collection = $grabagunCollectionFactory->create()->addFieldToFilter('id', ['eq' => $preferredStoreId]);
                    $preferredStoreOptions = [];
                    $items = $collection->getItems();
                    foreach ($items as $item) {
                        if ($doesNfaItemExistInCart && !$item->getData('is_class3')) {
                            continue;
                        }
                        $shippingMethods = $shippingMethodManagement->estimateByDealerId($quote->getId(), $preferredStoreId);
                        if ($shippingMethods) {
                            $dealerShipingMethod = $shippingMethods[0];
                            $shipToStoreDescription = $dealerShipingMethod->getMethodTitle();
                            $shipToStoreCost = \Bolt\Boltpay\Helper\Shared\CurrencyUtils::toMinor($dealerShipingMethod->getAmount(), $quote->getBaseCurrencyCode());
                            $shipToStoreMethod = $dealerShipingMethod->getMethodCode();
                            $shipToStoreCarrierCode = $dealerShipingMethod->getCarrierCode();
                        }

                        $storeAddress = $this->storeAddressFactory->create();
                        $storeAddress->setStreetAddress1($item->getAddress());
                        $storeAddress->setLocality($item->getCity());
                        $storeAddress->setRegion($item->getState());
                        $storeAddress->setPostalCode($item->getZipcode());
                        $storeAddress->setCountryCode('US');
                        $shipToStoreOption = $this->shipToStoreOptionFactory->create();

                        $distance = $this->vincentyGreatCircleDistance($getGeoCodesForAddress['lat'], $getGeoCodesForAddress['lng'], $item->getLat(), $item->getLng());
                        $shipToStoreOption->setReference($shipToStoreMethod . '+' . $item->getId() . '+' . $shipToStoreCarrierCode);
                        $shipToStoreOption->setStoreName('Preferred FFL dealer: ' . $item->getDealerName());
                        $shipToStoreOption->setAddress($storeAddress);
                        $shipToStoreOption->setDistance($distance);
                        $shipToStoreOption->setDescription($shipToStoreDescription);
                        $shipToStoreOption->setDistanceUnit('mile');
                        $shipToStoreOption->setCost($shipToStoreCost);
                        $preferredStoreOptions[] = $shipToStoreOption;
                    }

                    $shipToStoreOptions = array_merge($preferredStoreOptions, $shipToStoreOptions);
                }


                if ($typeOfShipment == \Grabagun\Shipping\Model\Shipping\Config::FFL_SHIPPING_ITEMS_ONLY) {

                    $result = [$shipToStoreOptions, []];
                } else if ($typeOfShipment == \Grabagun\Shipping\Model\Shipping\Config::MIXED_SHIPPING_ITEMS) {
                    $result = [$shipToStoreOptions, $shippingOptions];
                }
            }

        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
            return [];
        }

        return $result;
    }

    /**
     * @param \Grabagun\Shipping\Helper\ShippingMethodHelper $grabgunShippingMethodHelper
     * @param $quote
     * @return string
     */
    public function getTypeOfShipment($grabgunShippingMethodHelper, $quote): string
    {
        $items = $quote->getAllItems();
        $countNonFflItems = 0;
        $countFflItems = 0;

        foreach ($items as $item) {
            if ($grabgunShippingMethodHelper->itemShippedToFflDealer($item->getSku())) {
                $countFflItems++;
            } else {
                $countNonFflItems++;
            }
        }

        $shippingType = '';
        if ($countFflItems > 0 && $countNonFflItems > 0) {
            $shippingType = \Grabagun\Shipping\Model\Shipping\Config::MIXED_SHIPPING_ITEMS;
        } elseif ($countNonFflItems === 0) {
            $shippingType = \Grabagun\Shipping\Model\Shipping\Config::FFL_SHIPPING_ITEMS_ONLY;
        } else {
            $shippingType = \Grabagun\Shipping\Model\Shipping\Config::NON_FFL_SHIPPING_ITEMS_ONLY;
        }

        return $shippingType;
    }

    /**
     * @param $quote
     */
    public function doesNfaItemExistInCart($quote)
    {
        foreach ($quote->getAllItems() as $item) {
            $productType = strtolower(
                $item->getProduct()->getAttributeText(\Grabagun\Shipping\Model\Shipping\Config::SHIPPING_RESTRICTION_TYPE_ATTRIBUTE_CODE)
            );
            if ($productType === strtolower(\Grabagun\Shipping\Model\Shipping\Config::SHIPPING_RESTRICTION_TYPE_SOT)) {
                return true;
            }
        }
        return false;
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
     * @param $result
     * @param \Grabagun\Shipping\Helper\ShippingMethodHelper $grabagunShippingMethodHelper
     * @param $product
     * @param $storeId
     * @return string
     */
    public function filterCartItemShipmentType(
        $result,
        \Grabagun\Shipping\Helper\ShippingMethodHelper $grabagunShippingMethodHelper,
        $product,
        $storeId
    )
    {
        try {
            if ($this->appState->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML) {
                return $result;
            }
            if ($grabagunShippingMethodHelper->itemShippedToFflDealer($product->getSku())) {
                $result = 'ship_to_store';
            } else {
                $result = 'door_delivery';
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
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
     * @param \Grabagun\Shipping\Helper\ShippingMethodHelper $grabgunShippingMethodHelper
     * @param $quote
     * @param $ship_to_store_option
     * @param $addressData
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getShipToStoreCarrierMethodCodes(
        $result,
        \Grabagun\Shipping\Model\ShippingMethodManagement $grabgunShippingMethodManagement,
        \Grabagun\Shipping\Helper\ShippingMethodHelper $grabgunShippingMethodHelper,
        $quote,
        $ship_to_store_option,
        $addressData
    )
    {
        $dealerStoreId = $this->getDealerInfoFromReference($ship_to_store_option['reference'])['dealer_id'];
        $typeOfShipment = $this->getTypeOfShipment($grabgunShippingMethodHelper, $quote);
        if ($dealerStoreId && $typeOfShipment == \Grabagun\Shipping\Model\Shipping\Config::FFL_SHIPPING_ITEMS_ONLY) {
            $dealerInfo = $this->getDealerInfoFromReference($ship_to_store_option['reference']);
            return [$dealerInfo['dealer_carrier_code'], $dealerInfo['dealer_shipping_method']];
        }
    }

    public function getDealerInfoFromReference($shipToStoreReference)
    {
        $dealerInfo = explode('+', $shipToStoreReference);
        return [
            'dealer_shipping_method' => $dealerInfo[0],
            'dealer_id' => $dealerInfo[1],
            'dealer_carrier_code' => $dealerInfo[2],
        ];
    }

    /**
     * @param \Grabagun\Shipping\Helper\ShippingMethodHelper $grabgunShippingMethodHelper
     * @param $quote
     * @param $transaction
     */
    public function setInStoreShippingAddressForPrepareQuote(
        \Grabagun\Shipping\Helper\ShippingMethodHelper $grabgunShippingMethodHelper,
        $quote,
        $transaction
    )
    {
        try {
            $typeOfShipment = $this->getTypeOfShipment($grabgunShippingMethodHelper, $quote);
            if ($typeOfShipment == \Grabagun\Shipping\Model\Shipping\Config::FFL_SHIPPING_ITEMS_ONLY && isset($transaction->order->cart->in_store_shipments[0]->shipment)) {
                $transactionInStoreShipment = $transaction->order->cart->in_store_shipments[0];
                $shipment = $transactionInStoreShipment->shipment;
                $address = $transactionInStoreShipment->address ?? null;
                if ($address) {
                    $address->country_code = 'US';
                    $this->orderHelper->setAddress($quote->getShippingAddress(), $address);
                    $quote->getShippingAddress()
                        ->setFirstName($shipment->shipping_address->first_name)
                        ->setLastName($shipment->shipping_address->last_name)
                        ->setCompany($transactionInStoreShipment->store_name)
                        ->save();
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }

    /**
     * @param \Grabagun\Shipping\Model\ShippingMethodManagement $shippingMethodManagement
     * @param \Grabagun\Shipping\Helper\ShippingMethodHelper $grabgunShippingMethodHelper
     * @param $quote
     * @param $transaction
     */
    public function setInStoreShippingMethodForPrepareQuote(
        \Grabagun\Shipping\Model\ShippingMethodManagement $shippingMethodManagement,
        \Grabagun\Shipping\Helper\ShippingMethodHelper $grabgunShippingMethodHelper,
        $quote,
        $transaction
    )
    {
        $typeOfShipment = $this->getTypeOfShipment($grabgunShippingMethodHelper, $quote);
        try {
            if ($typeOfShipment == \Grabagun\Shipping\Model\Shipping\Config::FFL_SHIPPING_ITEMS_ONLY && isset($transaction->order->cart->in_store_shipments[0]->shipment)) {
                $shipment = $transaction->order->cart->in_store_shipments[0]->shipment;
                $dealerStoreId = $this->getDealerInfoFromReference($shipment->reference)['dealer_id'];
                $shippingMethods = $shippingMethodManagement->estimateByDealerId($quote->getId(), $dealerStoreId);
                if ($shippingMethods) {
                    $dealerShipingMethod = $shippingMethods[0];
                    $shippingAddress = $quote->getShippingAddress();
                    $shippingAddress->setCollectShippingRates(true);
                    $shippingAddress->setShippingMethod($dealerShipingMethod->getCarrierCode() . '_' . $dealerShipingMethod->getMethodCode())->save();
                }
            }

            if ($typeOfShipment == \Grabagun\Shipping\Model\Shipping\Config::MIXED_SHIPPING_ITEMS && isset($transaction->order->cart->in_store_shipments[0]->shipment)) {
                $shippingAddress = $quote->getShippingAddress();
                $shippingAddress->setCollectShippingRates(true);
                $shippingMethod = $transaction->order->cart->shipments[0]->reference;
                $shippingAddress->setShippingMethod($shippingMethod)->save();
                $dealerInfo = $this->getDealerInfoFromReference($transaction->order->cart->in_store_shipments[0]->shipment->reference);
                $dealerId = $dealerInfo['dealer_id'];
                $dealerShippingMethod = $dealerInfo['dealer_shipping_method'];

                $quote->setData('ffl_dealer_address', $dealerId);
                $quote->setData(\Grabagun\Shipping\Model\Shipping\Config::SALES_ORDER_ATTRIBUTE_FFL_SHIPPING_ADDRESS_DESCRIPTION, $dealerShippingMethod);
                $quote->save();
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }

    /**
     * @param $result
     * @param \Grabagun\Shipping\Helper\ShippingMethodHelper $grabgunShippingMethodHelper
     * @param $quote
     * @param $transaction
     * @return null
     */
    public function isInStorePickupShipping(
        $result,
        \Grabagun\Shipping\Helper\ShippingMethodHelper $grabgunShippingMethodHelper,
        $quote,
        $transaction
    )
    {
        $typeOfShipment = $this->getTypeOfShipment($grabgunShippingMethodHelper, $quote);
        if ($typeOfShipment == \Grabagun\Shipping\Model\Shipping\Config::FFL_SHIPPING_ITEMS_ONLY && isset($transaction->order->cart->in_store_shipments[0]->address)) {
            $address = $transaction->order->cart->in_store_shipments[0]->address ?? null;
            return $address;
        }

        return $result;
    }

    /**
     * Calculates the great-circle distance between two points, with the Vincenty formula.
     * http://en.wikipedia.org/wiki/Great-circle_distance#Formulas
     *
     * @param float $latitudeFrom Latitude of start point in [deg decimal]
     * @param float $longitudeFrom Longitude of start point in [deg decimal]
     * @param float $latitudeTo Latitude of target point in [deg decimal]
     * @param float $longitudeTo Longitude of target point in [deg decimal]
     * @param float $earthRadius Mean earth radius in [m]
     * @return float Distance between points in [mile] (same as earthRadius)
     */
    public function vincentyGreatCircleDistance(
        $latitudeFrom,
        $longitudeFrom,
        $latitudeTo,
        $longitudeTo,
        $earthRadius = 6371000
    )
    {
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $lonDelta = $lonTo - $lonFrom;
        $a = pow(cos($latTo) * sin($lonDelta), 2) +
            pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
        $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

        $angle = atan2(sqrt($a), $b);
        $km = round($angle * $earthRadius / 1000, 2);
        return $km / 1.609344;
    }

    /**
     * @param $order
     * @param $customFields
     * @param $transaction
     * @throws \Exception
     */
    public function afterHandleCustomField($order, $customFields, $transaction)
    {
        if ($customerId = $order->getCustomerId()) {
            foreach ($customFields as $customField) {
                if ($customField['external_id'] == 'set_selected_store_as_default' && $customField['value'] && isset($transaction->order->cart->in_store_shipments[0]->shipment->reference)) {
                    $dealerInfo = $this->getDealerInfoFromReference($transaction->order->cart->in_store_shipments[0]->shipment->reference);
                    $id = (int)$dealerInfo['dealer_id'];
                    if ($id) {
                        $customer = $this->customerFactory->create()->load($customerId);
                        $customer->setData(\Grabagun\DealerLocator\Model\CustomerAttributes::PREFERRED_DEALER_ATTR_CODE, $id);
                        $customer->save();
                    }
                }
            }
        }
    }

    /**
     * @param $result
     * @param \Grabagun\Shipping\Helper\ShippingMethodHelper $shippingMethodHelper
     * @param $taxData
     * @param $totalsInformation
     * @param $currencyCode
     * @param $shipping_option
     * @param $ship_to_store_option
     * @param $quote
     * @return bool
     * @throws \Exception
     */
    public function filterTaxResult($result, \Grabagun\Shipping\Helper\ShippingMethodHelper $shippingMethodHelper, $taxData, $totalsInformation, $currencyCode, $shipping_option, $ship_to_store_option, $quote)
    {
        $typeOfShipment = $this->getTypeOfShipment($shippingMethodHelper, $quote);
        if ($typeOfShipment == \Grabagun\Shipping\Model\Shipping\Config::MIXED_SHIPPING_ITEMS) {
            $shipping_option = $this->boltTax->getShippingDiscount($totalsInformation, $currencyCode, $shipping_option);

            $totalShippingAmount = $shipping_option['cost'];

            $shippingOption = $this->shippingOptionFactory->create();
            $shippingOption->setCost($totalShippingAmount - $ship_to_store_option['cost']);
            $shippingOption->setTaxAmount($shipping_option['tax_amount']);
            $shippingOption->setService($shipping_option['service'] ?? null);
            $shippingOption->setReference($shipping_option['reference'] ?? null);

            $storeAddress = $this->storeAddressFactory->create();
            $storeAddress->setStreetAddress1($ship_to_store_option['address']['street_address1']);
            $storeAddress->setStreetAddress2($ship_to_store_option['address']['street_address2']);
            $storeAddress->setLocality($ship_to_store_option['address']['locality']);
            $storeAddress->setRegion($ship_to_store_option['address']['region']);
            $storeAddress->setPostalCode($ship_to_store_option['address']['postal_code']);
            $storeAddress->setCountryCode('US');

            $shipToStoreOption = $this->shipToStoreOptionFactory->create();
            $shipToStoreOption->setReference($ship_to_store_option['reference']);
            $shipToStoreOption->setCost($ship_to_store_option['cost']);
            $shipToStoreOption->setStoreName($ship_to_store_option['store_name']);
            $shipToStoreOption->setAddress($storeAddress);
            $shipToStoreOption->setDistance($ship_to_store_option['distance']);
            $shipToStoreOption->setDistanceUnit($ship_to_store_option['distance_unit']);
            $shipToStoreOption->setTaxAmount(0);

            $taxData->setShippingOption($shippingOption);
            $taxData->setShipToStoreOption($shipToStoreOption);
            return true;
        }

        return $result;
    }

    /**
     * @param \Grabagun\Shipping\Helper\ShippingMethodHelper $shippingMethodHelper
     * @param $addressInformation
     * @param $quote
     * @param $shipping_option
     * @param $ship_to_store_option
     * @param $addressData
     */
    public function setExtraAddressInformation(\Grabagun\Shipping\Helper\ShippingMethodHelper $shippingMethodHelper, $addressInformation, $quote, $shipping_option, $ship_to_store_option, $addressData)
    {
        $typeOfShipment = $this->getTypeOfShipment($shippingMethodHelper, $quote);
        if ($typeOfShipment == \Grabagun\Shipping\Model\Shipping\Config::MIXED_SHIPPING_ITEMS) {
            $dealerInfo = $this->getDealerInfoFromReference($ship_to_store_option['reference']);
            $dealerId = $dealerInfo['dealer_id'];
            $dealerShippingMethod = $dealerInfo['dealer_shipping_method'];
            $quote->setData('ffl_dealer_address', $dealerId);
            $quote->setData(\Grabagun\Shipping\Model\Shipping\Config::SALES_ORDER_ATTRIBUTE_FFL_SHIPPING_ADDRESS_DESCRIPTION, $dealerShippingMethod);
            $quote->save();
        }
    }

    /**
     * @param \Grabagun\Shipping\Helper\ShippingMethodHelper $shippingMethodHelper
     * @param $quote
     * @param $ship_to_store_option
     * @param $shipping_option
     * @throws \Bolt\Boltpay\Exception\BoltException
     */
    public function afterGetShipToStoreAndShippingOptions(
        \Grabagun\Shipping\Helper\ShippingMethodHelper $shippingMethodHelper,
        $ship_to_store_option,
        $shipping_option,
        $quote
    )
    {
        $typeOfShipment = $this->getTypeOfShipment($shippingMethodHelper, $quote);

        if ($typeOfShipment == \Grabagun\Shipping\Model\Shipping\Config::MIXED_SHIPPING_ITEMS || $typeOfShipment == \Grabagun\Shipping\Model\Shipping\Config::FFL_SHIPPING_ITEMS_ONLY) {
            if (!$ship_to_store_option) {
                throw new \Bolt\Boltpay\Exception\BoltException(
                    __('No Dealer are found'),
                    null,
                    6103
                );
            }
        }
    }

    /**
     * @param \Amasty\Shiprestriction\Model\ResourceModel\Rule\CollectionFactory $amastyShiprestrictionRuleCollection
     * @param $errors
     * @throws \Bolt\Boltpay\Exception\BoltException
     */
    public function beforeThrowingNoShippingMethodsException(
        \Amasty\Shiprestriction\Model\ResourceModel\Rule\CollectionFactory $amastyShiprestrictionRuleCollection,
        $errors
    ){
        if ($errors) {
            $amastyShiprestrictionRuleCollectionFactory = $amastyShiprestrictionRuleCollection->create();
            foreach ($errors as $error) {
                if ($amastyShiprestrictionRuleCollectionFactory->addFilter('is_active', 1)->addFilter('message',$error['error'])->getSize() > 0){
                    throw new \Bolt\Boltpay\Exception\BoltException(__($error['error']), null, 6103);
                };
            }
        }

    }
}
