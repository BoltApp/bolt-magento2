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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\Rossignol\Synolia;

use Synolia\Store\Model\Carrier as InStorePickup;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\Bugsnag as BugsnagHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Api\Data\StoreAddressInterfaceFactory;
use Bolt\Boltpay\Api\Data\ShipToStoreOptionInterfaceFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\InventorySourceSelection\Model\Address;

/**
 * Class Store
 *
 * @package Bolt\Boltpay\ThirdPartyModules\Rossignol\Synolia
 */
class Store
{
    /**
     * @var BugsnagHelper
     */
    private $bugsnagHelper;
    
    /**
     * @var OrderHelper
     */
    private $orderHelper;
    
    /**
     * @var StoreAddressInterfaceFactory
     */
    protected $storeAddressFactory;
    
    /**
     * @var ShipToStoreOptionInterfaceFactory
     */
    protected $shipToStoreOptionFactory;
    
    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * @var LogHelper
     */
    protected $logHelper;

    /**
     * Store constructor.
     *
     * @param BugsnagHelper $bugsnagHelper
     */
    public function __construct(
        BugsnagHelper $bugsnagHelper,
        OrderHelper $orderHelper,
        StoreAddressInterfaceFactory $storeAddressFactory,
        ShipToStoreOptionInterfaceFactory $shipToStoreOptionFactory,
        StoreManagerInterface $storeManager,
        LogHelper $logHelper
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->orderHelper = $orderHelper;
        $this->storeAddressFactory = $storeAddressFactory;
        $this->shipToStoreOptionFactory = $shipToStoreOptionFactory;
        $this->storeManager = $storeManager;
        $this->logHelper = $logHelper;
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
        $synoliaStoreCollectionFactory,
        $synoliaStoreGeocodeHelper,
        $quote,
        $shippingOptions,
        $addressData,
        $cart_shipment_type
    ) {
        try {
            $tmpShippingOptions = [];
            $shipToStoreOptions = [];
            $hasInStorePickup = false;
            $inStorePickupCost = 0;
$this->logHelper->addInfoLog('### StorePickup');
            foreach ($shippingOptions as $shippingOption) {
$this->logHelper->addInfoLog('### getReference');
$this->logHelper->addInfoLog($shippingOption->getReference());
                if ($shippingOption->getReference() !== InStorePickup::CARRIER_CODE.'_'.InStorePickup::CARRIER_CODE) {
                    if ($cart_shipment_type != 'ship_to_store') {
                        $tmpShippingOptions[] = $shippingOption;
                    }
                } else {
                    $hasInStorePickup = true;
                    $inStorePickupCost = $shippingOption->getCost();
                }
            }
$this->logHelper->addInfoLog('### hasInStorePickup');
$this->logHelper->addInfoLog($hasInStorePickup);
            if ($hasInStorePickup) {
                $shippingAddressQuery = $addressData['street_address1']
                                    . ', '. $addressData['locality']
                                    . ', ' . $addressData['region']
                                    . ', ' . $addressData['postal_code']
                                    . ', ' . $addressData['country_code'];
$this->logHelper->addInfoLog('### shippingAddressQuery');
$this->logHelper->addInfoLog($shippingAddressQuery);
$this->logHelper->addInfoLog('### synoliaStoreGeocodeHelper');
$this->logHelper->addInfoLog(var_export(get_class($synoliaStoreGeocodeHelper), true));
                $coordinates = $synoliaStoreGeocodeHelper->getFirstCoordinatesByAddress($shippingAddressQuery);
$this->logHelper->addInfoLog('### coordinates');
$this->logHelper->addInfoLog(var_export($coordinates, true));
                if (!empty($coordinates)) {
                    $collectionResultSearch = $synoliaStoreCollectionFactory->create();
                    $collectionResultSearch->addDistanceFilter($coordinates['lat'], $coordinates['lng'], 100);
                    if (!empty($collectionResultSearch)) {
                        $validStores = [];
                        foreach ($collectionResultSearch as $resultStore) {
$this->logHelper->addInfoLog('### resultStore->getName');
$this->logHelper->addInfoLog(var_export($resultStore->getName(), true));
                            $distance = $this->vincentyGreatCircleDistance($coordinates['lat'], $coordinates['lng'], $resultStore->getLatitude(), $resultStore->getLongitude());         

                            if ($distance < 100) {
                                $validStores[$distance * 100] = $resultStore;                       
                            }
                        }
                        ksort($validStores);
                        foreach ($validStores as $distance => $store) {
                            $storeAddress = $this->storeAddressFactory->create();
                            $storeAddress->setStreetAddress1(is_null($store->getStreet()) ? '' : $store->getStreet());
                            $storeAddress->setStreetAddress2('');
                            $storeAddress->setLocality(is_null($store->getCity()) ? '' : $store->getCity());
                            $storeAddress->setRegion('');
                            $storeAddress->setPostalCode(is_null($store->getPostalCode()) ? '' : $store->getPostalCode());
                            $storeAddress->setCountryCode(is_null($store->getCountry()) ? '' : $store->getCountry());
        
                            $shipToStoreOption = $this->shipToStoreOptionFactory->create();
        
                            $shipToStoreOption->setReference(InStorePickup::CARRIER_CODE.'_'.InStorePickup::CARRIER_CODE . '_' . $store->getIdentifier() . '_' . $store->getStoreId());
                            $shipToStoreOption->setCost($inStorePickupCost);
                            $shipToStoreOption->setStoreName(is_null($store->getName()) ? '' : $store->getName());
                            $shipToStoreOption->setAddress($storeAddress);
                            $shipToStoreOption->setDistance(round($distance / 100, 2));
                            $shipToStoreOption->setDistanceUnit('km');

                            $shipToStoreOptions[] = $shipToStoreOption;
                        }    
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
        if ($this->checkIfRossignolSynoliaInStorePickupByCode($referenceCodes)) {
            $quote->setClickandcollectId($referenceCodes[3]);
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setClickandcollectIdentifier($referenceCodes[2]);
            $shippingAddress->setShippingMethod('clickandcollect_clickandcollect');
            return [$referenceCodes[0], $referenceCodes[1]];
        }
        
        return $result;
    }
    
    /**
     * @param Magento\InventoryInStorePickupQuote\Model\ToQuoteAddress $addressConverter
     * @param Magento\InventoryInStorePickupApi\Model\GetPickupLocationInterface $getPickupLocation
     * @param Magento\Quote\Model\Quote $quote
     * @param \stdClass                 $transaction
     * @return array
     */
    public function setInStoreShippingAddressForPrepareQuote(
        $quote,
        $transaction
    ) {
        try {
            if (isset($transaction->order->cart->in_store_shipments[0]->shipment)) {
                $shipment = $transaction->order->cart->in_store_shipments[0]->shipment;
                $referenceCodes = explode('_', $shipment->reference);
                if ($this->checkIfRossignolSynoliaInStorePickupByCode($referenceCodes)) {
                    $address = $transaction->order->cart->in_store_shipments[0]->address ?? null;
                    if ($address) {
                        $address->country_code = 'US'; 
                        $this->orderHelper->setAddress($quote->getShippingAddress(), $address);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
    
    /**
     * @param Magento\InventoryInStorePickupQuote\Model\Address\SetAddressPickupLocation $setAddressPickupLocation
     * @param Magento\Quote\Model\Quote $quote
     * @param \stdClass                 $transaction
     * @return array
     */
    public function setInStoreShippingMethodForPrepareQuote(
        $quote,
        $transaction
    ) {
        try {
            if (isset($transaction->order->cart->in_store_shipments[0]->shipment)) {
                $shipment = $transaction->order->cart->in_store_shipments[0]->shipment;
                $referenceCodes = explode('_', $shipment->reference);
                if ($this->checkIfRossignolSynoliaInStorePickupByCode($referenceCodes)) {
                    $quote->setClickandcollectId($referenceCodes[3]);
                    $shippingAddress = $quote->getShippingAddress();
                    $shippingAddress->setCollectShippingRates(true);
                    $shippingAddress->setClickandcollectIdentifier($referenceCodes[2]);
                    $shippingAddress->setShippingMethod('clickandcollect_clickandcollect')->save();
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
     * @param \stdClass                 $transaction
     * @return array
     */
    public function isInStorePickupShipping(
        $result,
        $quote,
        $transaction
    ) {
        if (isset($transaction->order->cart->in_store_shipments[0]->address)) {
            $referenceCodes = explode('_', $transaction->order->cart->in_store_shipments[0]->shipment->reference);
            if ($this->checkIfRossignolSynoliaInStorePickupByCode($referenceCodes)) {
                $address = $transaction->order->cart->in_store_shipments[0]->address ?? null;
                return $address;
            }
        }
        
        return $result;
    }
    
    /**
     * @param array $referenceCodes
     * @return bool
     */
    private function checkIfRossignolSynoliaInStorePickupByCode($referenceCodes)
    {
        if (count($referenceCodes) > 2 &&
            $referenceCodes[0] . '_' . $referenceCodes[1] == InStorePickup::CARRIER_CODE.'_'.InStorePickup::CARRIER_CODE) {
            return true;
        }
        
        return false;
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
     * @return float Distance between points in [m] (same as earthRadius)
     */
    public function vincentyGreatCircleDistance(
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
        return round($angle * $earthRadius / 1000, 2);
    }
}
