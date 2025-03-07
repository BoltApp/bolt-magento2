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

namespace Bolt\Boltpay\ThirdPartyModules\MageWorx;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Api\Data\StoreAddressInterfaceFactory;
use Bolt\Boltpay\Api\Data\ShipToStoreOptionInterfaceFactory;

use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\HTTP\Header;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Store\Model\StoreManagerInterface;

class Pickup
{
    /**
     * @var Bugsnag Bugsnag helper instance
     */
    private $bugsnagHelper;

    /**
     * @var StoreAddressInterfaceFactory
     */
    protected $storeAddressFactory;

    /**
     * @var ShipToStoreOptionInterfaceFactory
     */
    protected $shipToStoreOptionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var RegionFactory
     */
    protected $regionFactory;

    /**
     * @var SessionManagerInterface
     */
    protected $sessionManager;

    /**
     * @var CookieManagerInterface
     */
    protected $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    protected $cookieMetadataFactory;

    /**
     * @var Header
     */
    protected $httpHeader;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var string
     */
    private const DELIVERY_METHOD = 'mageworxpickup_mageworxpickup';

    /**
     * @var string
     */
    private const COOKIE_NAME = 'mageworx_location_id';

    /**
     * @param Bugsnag                      $bugsnagHelper
     * @param StoreAddressInterfaceFactory $storeAddressFactory
     * @param RegionFactory                $regionFactory
     * @param SessionManagerInterface      $sessionManager
     * @param CookieManagerInterface       $cookieManager
     * @param CookieMetadataFactory        $cookieMetadataFactory
     * @param Header                       $httpHeader
     * @param StoreManagerInterface        $storeManager
     * @param ClientFactory                $clientFactory
     * @param SerializerInterface          $serializer
     */
    public function __construct(
        Bugsnag  $bugsnagHelper,
        StoreAddressInterfaceFactory $storeAddressFactory,
        ShipToStoreOptionInterfaceFactory $shipToStoreOptionFactory,
        SessionManagerInterface $sessionManager,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        Header $httpHeader,
        RegionFactory $regionFactory,
        StoreManagerInterface $storeManager,
        ClientFactory $clientFactory,
        SerializerInterface $serializer
    ) {
        $this->bugsnagHelper            = $bugsnagHelper;
        $this->storeAddressFactory      = $storeAddressFactory;
        $this->shipToStoreOptionFactory = $shipToStoreOptionFactory;
        $this->sessionManager           = $sessionManager;
        $this->cookieManager            = $cookieManager;
        $this->cookieMetadataFactory    = $cookieMetadataFactory;
        $this->httpHeader               = $httpHeader;
        $this->regionFactory            = $regionFactory;
        $this->storeManager             = $storeManager;
        $this->clientFactory            = $clientFactory;
        $this->serializer               = $serializer;
    }

    /**
     * @param  array                                              $result
     * @param  MageWorx\StoreLocator\Helper\Data                  $mageWorxHelper
     * @param  \Magento\Quote\Model\Quote                         $quote
     * @param  $shippingOptions
     * @param  $addressData
     * @return array
     */
    public function getShipToStoreOptions(
        $result,
        $mageWorxHelper,
        $quote,
        $shippingOptions,
        $addressData
    ) {
        try {
            if (empty($shippingOptions)) {
                return $result;
            }

            $tmpShippingOptions = [];
            $hasInStorePickup = false;
            $shipToStoreOptions = [];
            foreach ($shippingOptions as $shippingOption) {
                if ($shippingOption->getReference() == self::DELIVERY_METHOD) {
                    $hasInStorePickup = true;
                    $inStorePickupCost = $shippingOption->getCost();
                } else {
                    $tmpShippingOptions[] = $shippingOption;
                }
            }
            $result = [$shipToStoreOptions, $tmpShippingOptions];
            if (!$hasInStorePickup) {
                return $result;
            } else {
                $filters = $this->getFilters($addressData, $mageWorxHelper);
                if (empty($filters)) {
                    return $result;
                }
                $locations = $mageWorxHelper->getLocationsForCurrentQuote($filters);
                if (count($locations)) {
                    $distanceUnit = ($filters['unit'] == 'miles') ? 'mile' : 'km';
                    foreach ($locations as $location) {
                        $distance = ($this->calculateCircleDistance($location->getLatitude(), $location->getLongitude(), $filters['autocomplete']['lat'], $filters['autocomplete']['lng'])) / 1000;
                        $distance = round((($distanceUnit == 'km') ?: $distance * 0.621371), 2);
                        if ($filters['radius'] > 0 && $distance > $filters['radius']) {
                            continue;
                        }

                        $storeAddress = $this->storeAddressFactory->create();
                        $locationRegion = $this->regionFactory->create()->loadByName($location->getRegion(), $location->getCountryId());
                        $storeAddress->setStreetAddress1($location->getAddress());
                        $storeAddress->setLocality($location->getCity());
                        $storeAddress->setRegion($locationRegion->getRegion());
                        $storeAddress->setPostalCode($location->getPostcode());
                        $storeAddress->setCountryCode($location->getCountryId());

                        $shipToStoreOption = $this->shipToStoreOptionFactory->create();
                        $locationId = $location->getId();
                        $locationName = $location->getName();
                        $shipToStoreOption->setReference(self::DELIVERY_METHOD . '_' . $locationId);
                        $shipToStoreOption->setCost($inStorePickupCost);
                        $shipToStoreOption->setStoreName($locationName);
                        $shipToStoreOption->setAddress($storeAddress);
                        $shipToStoreOption->setDistance($distance);
                        $shipToStoreOption->setDistanceUnit($distanceUnit);
                        $shipToStoreOptions[] = $shipToStoreOption;
                    }
                    if (!empty($shipToStoreOptions)) {
                        usort($shipToStoreOptions, function ($first, $second) {
                            return $first->getDistance() - $second->getDistance();
                        });
                    }
                }
            }

            $result = [$shipToStoreOptions, $tmpShippingOptions];
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
    }

    /**
     * @param array                     $result
     * @param Magento\Quote\Model\Quote $quote
     * @param array                     $ship_to_store_option
     * @param array                     $addressData
     * @return array
     */
    public function getShipToStoreCarrierMethodCodes(
        $result,
        $quote,
        $ship_to_store_option,
        $addressData
    ) {
        $referenceCodes = explode('_', $ship_to_store_option['reference']);
        if ($this->getLocationIdFromCodes($referenceCodes)) {
            return [$referenceCodes[0], $referenceCodes[1]];
        }

        return $result;
    }

    /**
     * @param Magento\Checkout\Api\Data\TotalsInformationInterface $addressInformation
     * @param Magento\Quote\Model\Quote $quote
     * @param array                     $shipping_option
     * @param array                     $ship_to_store_option
     * @param array                     $addressData
     */
    public function setExtraAddressInformation(
        $addressInformation,
        $quote,
        $shipping_option,
        $ship_to_store_option,
        $addressData
    ) {
        try {
            $carrierCode = $addressInformation->getShippingCarrierCode();
            $methodCode = $addressInformation->getShippingMethodCode();
            if ($carrierCode . '_' . $methodCode == self::DELIVERY_METHOD) {
                $referenceCodes = explode('_', $ship_to_store_option['reference']);
                $locationId = $referenceCodes[2];
                $this->sessionManager->setData('mageworx_pickup_location_id', $locationId);
                $_COOKIE[self::COOKIE_NAME] = $locationId;
                $quote->setMageworxPickupLocationId($locationId);
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @param \stdClass $transaction
     */
    public function setInStoreShippingMethodForPrepareQuote(
        $quote,
        $transaction
    ) {
        try {
            $shipment = $transaction->order->cart->in_store_shipments[0]->shipment;
            $referenceCodes = explode('_', $shipment->reference);
            $locationId = $this->getLocationIdFromCodes($referenceCodes);
            if ($locationId) {
                $shippingAddress = $quote->getShippingAddress();
                $shippingAddress->setCollectShippingRates(true);
                $shippingAddress->setShippingMethod(self::DELIVERY_METHOD)->save();
                $this->sessionManager->setData('mageworx_pickup_location_id', $locationId);
                $_COOKIE[self::COOKIE_NAME] = $locationId;
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }

    /**
     * @param Magento\InventoryInStorePickupQuote\Model\ToQuoteAddress $addressConverter
     * @param Magento\InventoryInStorePickupApi\Model\GetPickupLocationInterface $getPickupLocation
     * @param Magento\Quote\Model\Quote $quote
     * @param \stdClass                 $transaction
     * @return array
     */
    public function isInStorePickupShipping(
        $result,
        $quote,
        $transaction
    ) {
        if (isset($transaction->order->cart->in_store_shipments[0]->shipment->shipping_address)) {
            $referenceCodes = explode('_', $transaction->order->cart->in_store_shipments[0]->shipment->reference);
            if ($this->getLocationIdFromCodes($referenceCodes)) {
                return $transaction->order->cart->in_store_shipments[0]->shipment->shipping_address;
            }
        }

        return $result;
    }

    /**
     * @param MageWorx\Locations\Api\LocationRepositoryInterface $locationRepository
     * @param Magento\Quote\Model\Quote $quote
     * @param \stdClass                 $transaction
     */
    public function setInStoreShippingAddressForPrepareQuote(
        $locationRepository,
        $quote,
        $transaction
    ) {
        try {
            if (isset($transaction->order->cart->in_store_shipments[0]->shipment)) {
                $shipment = $transaction->order->cart->in_store_shipments[0]->shipment;
                $referenceCodes = explode('_', $shipment->reference);
                $locationId = $this->getLocationIdFromCodes($referenceCodes);
                if ($locationId) {
                    $location = $locationRepository->getById($locationId);
                    $locationRegion = $this->regionFactory->create()->loadByName($location->getRegion(), $location->getCountryId());
                    $shippingAddress = $quote->getShippingAddress();
                    $shippingAddress->setData('firstname', $transaction->order->cart->in_store_shipments[0]->store_name);
                    $shippingAddress->setData('lastname', 'Store');
                    $shippingAddress->setRegionId($locationRegion->getRegionId());
                    $shippingAddress->setRegion($locationRegion->getRegion());
                    $shippingAddress->setCountryId($location->getCountryId());
                    $shippingAddress->setPostcode($location->getPostcode());
                    $shippingAddress->setCity($location->getCity());
                    $shippingAddress->setStreet($location->getAddress());
                    if ($location->getPhoneNumber()) {
                        $shippingAddress->setTelephone($location->getPhoneNumber());
                    }

                    $quote->setShippingAddress($shippingAddress)->save();
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }

    /**
     * @param array $referenceCodes
     * @return int
     */
    private function getLocationIdFromCodes($referenceCodes)
    {
        if (count($referenceCodes) > 2 && $referenceCodes[0] . '_' . $referenceCodes[1] == self::DELIVERY_METHOD) {
            return $referenceCodes[2];
        }

        return 0;
    }

    /**
     * @param mixed $addressData
     * @param MageWorx\StoreLocator\Helper\Data $mageWorxHelper
     * @return array
     */
    protected function getFilters($addressData, $mageWorxHelper)
    {
        $filters = [];
        $radius = $mageWorxHelper->getRadiusValueFromSession() ?? $mageWorxHelper->getDefaultRadiusValue();
        $radiusUnit = $mageWorxHelper->getRadiusUnit();
        $googleMapApiKey = $mageWorxHelper->getMapApiKey();
        $address = ($addressData['street_address1'] ?? '') . ',' . ($addressData['locality'] ?? '') . ',' . ($addressData['region'] ?? '') . ' ' . ($addressData['postal_code'] ?? '') . ',' . ($addressData['country_code'] ?? '');
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
            return $filters;
        }
        $status = $response->getStatusCode();
        if ($status !== 200) {
            return $filters;
        }
        $responseBody = $response->getBody();
        $responseContent = $responseBody->getContents();
        $content = $this->serializer->unserialize($responseContent);
        if (!empty($content) && is_array($content) && isset($content['status']) && $content['status'] == 'OK') {
            $filters['radius']       = $radius;
            $filters['autocomplete'] = [
                'small_city' => $addressData['locality'] ?? '',
                'city' => $addressData['locality'] ?? '',
                'region' => $addressData['region'] ?? '',
                'postcode' => $addressData['postal_code'] ?? '',
                'country_id' => $addressData['country_code'] ?? '',
                'lat' => $content['results'][0]['geometry']['location']['lat'],
                'lng' => $content['results'][0]['geometry']['location']['lng'],
                'skip_radius' => false,
            ];
            $filters['unit'] = $radiusUnit;
        }

        return $filters;
    }

    /**
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStoreId()
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * Calculates the great-circle distance between two points, with
     * the Vincenty formula.
     * @param float $latitudeFrom Latitude of start point in [deg decimal]
     * @param float $longitudeFrom Longitude of start point in [deg decimal]
     * @param float $latitudeTo Latitude of target point in [deg decimal]
     * @param float $longitudeTo Longitude of target point in [deg decimal]
     * @param float $earthRadius Mean earth radius in [m]
     * @return float Distance between points in [m] (same as earthRadius)
     */
    public function calculateCircleDistance(
        $latitudeFrom,
        $longitudeFrom,
        $latitudeTo,
        $longitudeTo,
        $earthRadius = 6371000
    ) {
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
        return $angle * $earthRadius;
    }
}
