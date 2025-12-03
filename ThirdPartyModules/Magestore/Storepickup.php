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
 * @category  Bolt
 * @package   Bolt_Boltpay
 * @copyright Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\Magestore;

use Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory;
use Bolt\Boltpay\Api\Data\StoreAddressInterfaceFactory;
use Bolt\Boltpay\Api\Data\ShipToStoreOptionInterfaceFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Geolocation;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;

use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Checkout\Model\Session;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\ShippingMethodExtensionFactory;

class Storepickup
{
    /**
     * @var Bugsnag Bugsnag helper instance
     */
    private $bugsnagHelper;

    /**
     * @var StoreAddressInterfaceFactory
     */
    private $storeAddressFactory;

    /**
     * @var ShipToStoreOptionInterfaceFactory
     */
    private $shipToStoreOptionFactory;

    /**
     * @var ShippingOptionInterfaceFactory
     */
    private $shippingOptionFactory;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var RegionFactory
     */
    private $regionFactory;

    /**
     * @var ShippingMethodExtensionFactory
     */
    private $extensionFactory;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var Geolocation
     */
    private $geolocation;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var string
     */
    public const MAGESTORE_STOREPICKUP_MODULE_NAME = 'Magestore_Storepickup';

    /**
     * @var string
     */
    public const MAGESTORE_STOREPICKUP_CARRIER_CODE = 'storepickup';

    /**
     * @var string
     */
    public const DELIVERY_METHOD = 'storepickup_storepickup';

    /**
     *
     * @param Bugsnag                           $bugsnagHelper
     * @param StoreAddressInterfaceFactory      $storeAddressFactory
     * @param ShipToStoreOptionInterfaceFactory $shipToStoreOptionFactory
     * @param ShippingOptionInterfaceFactory    $shippingOptionFactory
     * @param ClientFactory                     $clientFactory
     * @param SerializerInterface               $serializer
     * @param CartRepositoryInterface           $quoteRepository
     * @param RegionFactory                     $regionFactory
     * @param ShippingMethodExtensionFactory    $extensionFactory
     * @param Session                           $checkoutSession
     * @param Geolocation                       $geolocation
     * @param Json|null                         $json
     */
    public function __construct(
        Bugsnag  $bugsnagHelper,
        StoreAddressInterfaceFactory $storeAddressFactory,
        ShipToStoreOptionInterfaceFactory $shipToStoreOptionFactory,
        ShippingOptionInterfaceFactory $shippingOptionFactory,
        ClientFactory $clientFactory,
        SerializerInterface $serializer,
        CartRepositoryInterface $quoteRepository,
        RegionFactory $regionFactory,
        ShippingMethodExtensionFactory $extensionFactory,
        Session $checkoutSession,
        Geolocation $geolocation,
        Json $json = null
    ) {
        $this->bugsnagHelper            = $bugsnagHelper;
        $this->storeAddressFactory      = $storeAddressFactory;
        $this->shipToStoreOptionFactory = $shipToStoreOptionFactory;
        $this->shippingOptionFactory    = $shippingOptionFactory;
        $this->clientFactory            = $clientFactory;
        $this->serializer               = $serializer;
        $this->quoteRepository          = $quoteRepository;
        $this->regionFactory            = $regionFactory;
        $this->extensionFactory         = $extensionFactory;
        $this->checkoutSession          = $checkoutSession;
        $this->geolocation              = $geolocation;
        $this->json                     = $json ?: ObjectManager::getInstance()->get(Json::class);
    }

    /**
     *
     * @param \Magento\Quote\Api\Data\ShippingMethodInterface[] $result
     * @param \Magestore\Storepickup\Model\SystemConfig $systemConfig
     * @param \Magestore\Storepickup\Model\ResourceModel\Store\CollectionFactory $storeCollectionFactory
     * @param \Magestore\Storepickup\Helper\Data $mageStoreHelper
     * @param \Magestore\Storepickup\Model\StoreFactory $storeCollection
     * @param int $cartId
     * @param \Magento\Quote\Api\Data\AddressInterface $address
     *
     * @return \Magento\Quote\Api\Data\ShippingMethodInterface[] An array of shipping methods.
     */
    public function afterEstimateByExtendedAddress(
        $result,
        $systemConfig,
        $storeCollectionFactory,
        $mageStoreHelper,
        $storeCollection,
        $cartId,
        $address
    ) {
        try {
            $tmpResult = [];
            foreach ($result as $shippingMethod) {
                if ($shippingMethod->getCarrierCode() == self::MAGESTORE_STOREPICKUP_CARRIER_CODE) {
                    $quote = $this->quoteRepository->getActive($cartId);
                    $shippingAddress = $quote->getShippingAddress();
                    if ($shippingAddress &&
                        ($country_code = $shippingAddress->getCountryId()) &&
                        ($postal_code  = $shippingAddress->getPostcode())
                    ) {
                        $addressData = [
                            'country_code' => $country_code,
                            'postal_code'  => $postal_code,
                            'region'       => $shippingAddress->getRegion(),
                            'locality'     => $shippingAddress->getCity(),
                            'street_address1' => $shippingAddress->getStreetLine(1),
                            'street_address2' => $shippingAddress->getStreetLine(2),
                        ];
                        $collection = $storeCollectionFactory->create();
                        $shipToStoreOptions = $this->getShipToStoreOptions(
                            $systemConfig,
                            $collection,
                            $mageStoreHelper,
                            $storeCollection,
                            $quote,
                            $shippingMethod,
                            $addressData
                        );
                        if (!empty($shipToStoreOptions)) {
                            $extensibleAttribute =  ($shippingMethod->getExtensionAttributes())
                                ? $shippingMethod->getExtensionAttributes()
                                : $this->extensionFactory->create();
                            $locations = $this->json->serialize($shipToStoreOptions);
                            $extensibleAttribute->setBoltShipToStoreOptions($locations);
                            $shippingMethod->setExtensionAttributes($extensibleAttribute);
                        }
                    }
                }
                $tmpResult[] = $shippingMethod;
            }
            $result = $tmpResult;
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return $result;
    }

    /**
     *
     * @param int $cartId
     * @param \Magento\Quote\Api\Data\AddressInterface $addressInformation
     *
     */
    public function beforeSaveAddressInformation(
        $cartId,
        $addressInformation
    ) {
        try {
            $quote = $this->quoteRepository->getActive($cartId);
            if ($quote->isVirtual() || !$quote->getItemsCount()) {
                return;
            }
            $carrierCode = $addressInformation->getShippingCarrierCode();
            
            if ($carrierCode != self::MAGESTORE_STOREPICKUP_CARRIER_CODE) {
                return;
            }
            $methodCode = $addressInformation->getShippingMethodCode();
            $mixCodes = explode('_', $methodCode);
            
            if (count($mixCodes) != 2 || $mixCodes[0] != self::MAGESTORE_STOREPICKUP_CARRIER_CODE) {
                return;
            }

            $addressInformation->setShippingMethodCode(self::MAGESTORE_STOREPICKUP_CARRIER_CODE);

            $storeId = $mixCodes[1];
            $storepickup_session = ['store_id' => $storeId];
            $quote->setData(
                'storepickup_session',
                json_encode($storepickup_session)
            )->save();
            $this->checkoutSession->setData('storepickup_session', $storepickup_session);

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $coreSession = $objectManager->get(\Magento\Framework\Session\Generic::class);
            $coreSession->setData('storepickup_session', $storepickup_session);
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }

    /**
     *
     * @param \Magestore\Storepickup\Model\SystemConfig $systemConfig
     * @param \Magestore\Storepickup\Model\ResourceModel\Store\Collection $collection
     * @param \Magestore\Storepickup\Helper\Data $mageStoreHelper
     * @param \Magestore\Storepickup\Model\StoreFactory $storeCollection
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Quote\Api\Data\ShippingMethodInterface $shippingMethod
     * @param array $addressData
     *
     * @return array
     */
    public function getShipToStoreOptions(
        $systemConfig,
        $collection,
        $mageStoreHelper,
        $storeCollection,
        $quote,
        $shippingMethod,
        $addressData
    ) {
        try {
            $result = [];
            $majorAmount = $shippingMethod->getAmount();
            $currencyCode = $quote->getQuoteCurrencyCode();
            $inStorePickupCost = CurrencyUtils::toMinor($majorAmount, $currencyCode);

            $collection = $this->filterStoreCollection(
                $systemConfig,
                $collection,
                $mageStoreHelper,
                $addressData
            );
            $stores = $collection->prepareJson();
            if (!empty($stores)) {
                $radius = $systemConfig->getDefaultRadius();
                $distanceUnit = ($systemConfig->getDistanceUnit() == 'Km') ? 'km' : 'mile';
                $shippingGeoData = $this->calShippingAddressGeo($addressData, $mageStoreHelper);
                if (empty($shippingGeoData)) {
                    $this->bugsnagHelper->notifyError('Fail to get geolocation', var_export($addressData, true));
                }
                $collectionstore = $storeCollection->create();
                foreach ($stores as $store) {
                    $distance = ($this->geolocation->calculateCircleDistance($store['latitude'], $store['longitude'], $shippingGeoData['lat'], $shippingGeoData['lng'])) / 1000;
                    $distance = round((($distanceUnit == 'km') ? $distance : $distance * 0.621371), 2);
                    
                    if ($radius > 0 && $distance > $radius) {
                        continue;
                    }

                    $storeData = $collectionstore->load($store['storepickup_id'], 'storepickup_id');

                    $storeAddress = $this->storeAddressFactory->create();
                    $storeAddress->setStreetAddress1($storeData->getData('address'));
                    $storeAddress->setLocality($storeData->getCity());
                    if ($storeData->getStateId()) {
                        /** @var \Magento\Directory\Model\Region $region */
                        $region = $this->regionFactory->create()->load($storeData->getStateId());
                        $storeAddress->setRegion($region->getName());
                    } else {
                        $storeAddress->setRegion($storeData->getData('state'));
                    }

                    $storeAddress->setPostalCode($storeData->getData('zipcode'));
                    $storeAddress->setCountryCode($storeData->getData('country_id'));
                    if ($storeData->getPhone()) {
                        $storeAddress->setPhone($storeData->getPhone());
                    }

                    $shipToStoreOption = $this->shipToStoreOptionFactory->create();
                    $storeName = $storeData->getData('store_name');
                    $shipToStoreOption->setReference(self::DELIVERY_METHOD . '_' . $store['storepickup_id']);
                    $shipToStoreOption->setCost($inStorePickupCost);
                    $shipToStoreOption->setStoreName($storeName);
                    $shipToStoreOption->setAddress($storeAddress);
                    $shipToStoreOption->setDistance($distance);
                    $shipToStoreOption->setDistanceUnit($distanceUnit);
                    $result[] = $shipToStoreOption;
                }
                if (!empty($shipToStoreOptions)) {
                    usort($shipToStoreOptions, function ($first, $second) {
                        return $first->getDistance() - $second->getDistance();
                    });
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
    }

    /**
     *
     * @param \Magestore\Storepickup\Model\SystemConfig $systemConfig
     * @param \Magestore\Storepickup\Model\ResourceModel\Store\Collection $collection
     * @param \Magestore\Storepickup\Helper\Data $mageStoreHelper
     * @param array $addressData
     *
     * @return \Magestore\Storepickup\Model\ResourceModel\Store\Collection
     */
    public function filterStoreCollection(
        $systemConfig,
        $collection,
        $mageStoreHelper,
        $addressData
    ) {
        try {
            $mageStoreHelper->filterStoreByWebsite($collection);
            $collection->addFieldToSelect([
                'store_name',
                'phone',
                'address',
                'latitude',
                'longitude',
                'marker_icon',
                'zoom_level',
                'rewrite_request_path',
            ]);
            $collection->setPageSize($systemConfig->getPainationSize())->setCurPage(1);
            /*
             * Filter store enabled
             */
            // phpcs:ignore
            $collection->addFieldToFilter('status', \Magestore\Storepickup\Model\Status::STATUS_ENABLED); 
            /*
             * filter by store information
             */
            $countryId = $addressData['country_code'] ?? '';
            if (!empty($countryId)) {
                $collection->addFieldToFilter('country_id', $countryId);
            }
            $state = $addressData['region'] ?? '';
            if (!empty($state)) {
                $collection->addFieldToFilter('state', ['like' => "%$state%"]);
            }
            // Disallow load base image for each store
            $collection->setLoadBaseImage(false);
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $collection;
        }
    }

    /**
     *
     * @param array $addressData
     * @param \Magestore\Storepickup\Helper\Data $mageStoreHelper
     *
     * @return array
     */
    protected function calShippingAddressGeo($addressData, $mageStoreHelper)
    {
        $geoData = [];
        $address = ($addressData['street_address1'] ?? '') . ',' . ($addressData['locality'] ?? '') . ',' . ($addressData['region'] ?? '') . ' ' . ($addressData['postal_code'] ?? '') . ',' . ($addressData['country_code'] ?? '');
        $googleMapApiKey = $mageStoreHelper->getGoogleApiKey();
        $client = $this->clientFactory->create(['config' => [
            'base_uri' => 'https://maps.googleapis.com/'
        ]]);
        $uriEndpoint = 'maps/api/geocode/json?address=' . urlencode($address) . '&sensor=false&key=' . $googleMapApiKey;
        try {
            $response = $client->request(
                Request::HTTP_METHOD_GET,
                $uriEndpoint,
                []
            );
        } catch (GuzzleException $e) {
            $this->bugsnagHelper->notifyException($e);
            return $geoData;
        }

        $status = $response->getStatusCode();
        if ($status !== 200) {
            return $geoData;
        }
        $responseBody = $response->getBody();
        $responseContent = $responseBody->getContents();
        $content = $this->serializer->unserialize($responseContent);
        if (!empty($content) && is_array($content) && isset($content['status']) && $content['status'] == 'OK') {
            $geoData = [
                'lat' => $content['results'][0]['geometry']['location']['lat'],
                'lng' => $content['results'][0]['geometry']['location']['lng'],
            ];
        }
        
        return $geoData;
    }
}
