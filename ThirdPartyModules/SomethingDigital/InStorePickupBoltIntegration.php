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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\SomethingDigital;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Api\Data\StoreAddressInterfaceFactory;
use Bolt\Boltpay\Api\Data\ShipToStoreOptionInterfaceFactory;
use Magento\Directory\Model\Region as RegionModel;

class InStorePickupBoltIntegration
{
    const DEFAULT_PICKUP_TIME = '0000-00-00';
    /**
     * @var Bugsnag Bugsnag helper instance
     */
    private $bugsnagHelper;

    /**
     * @var StoreAddressInterfaceFactory
     */
    protected $storeAddressFactory;

    /**
     * @var \Magedelight\Storepickup\Model\Observer\SaveDeliveryDateToOrderObserver
     */
    protected $shipToStoreOptionFactory;

    /**
     * @var \SomethingDigital\InStorePickupBoltIntegration\Helper\PickupStoreChecker
     */
    private $pickupStoreChecker;

    /**
     * @var \Magedelight\Storepickup\Model\Observer\SaveDeliveryDateToOrderObserver
     */
    private $saveDeliveryObserver;

    /**
     * @var RegionModel
     */
    private $regionModel;

    /**
     * InStorePickupBoltIntegration constructor.
     * @param Bugsnag $bugsnagHelper
     * @param StoreAddressInterfaceFactory $storeAddressFactory
     * @param ShipToStoreOptionInterfaceFactory $shipToStoreOptionFactory
     * @param RegionModel $regionModel
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        StoreAddressInterfaceFactory $storeAddressFactory,
        ShipToStoreOptionInterfaceFactory $shipToStoreOptionFactory,
        RegionModel $regionModel
    )
    {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->storeAddressFactory = $storeAddressFactory;
        $this->shipToStoreOptionFactory = $shipToStoreOptionFactory;
        $this->regionModel = $regionModel;
    }

    /**
     * @param $result
     * @param $pickupStoreChecker
     * @param $saveDeliveryObserver
     * @param $quote
     * @param $shippingOptions
     * @param $addressData
     * @return array[]|mixed
     */
    public function getShipToStoreOptions(
        $result,
        $pickupStoreChecker,
        $saveDeliveryObserver,
        $quote,
        $shippingOptions,
        $addressData
    )
    {
        try {
            $this->pickupStoreChecker = $pickupStoreChecker;
            $this->saveDeliveryObserver = $saveDeliveryObserver;
            if (empty($shippingOptions)) {
                return $result;
            }

            $tmpShippingOptions = [];
            $hasInStorePickup = false;
            $shipToStoreOptions = [];
            foreach ($shippingOptions as $shippingOption) {
                $reference = $shippingOption->getReference();
                if ($this->pickupStoreChecker->isPickupShippingMethod($reference)) {
                    $hasInStorePickup = true;
                    $pickupStoreId = $this->pickupStoreChecker->getPickupStoreIdByShippingMethod($reference);
                    $addressData = $this->saveDeliveryObserver->getStorePickupAddress($pickupStoreId);

                    $storeAddress = $this->storeAddressFactory->create();
                    $storeAddress->setStreetAddress1($addressData['street']);
                    $storeAddress->setLocality($addressData['city']);
                    $storeAddress->setRegion($addressData['region']);
                    $storeAddress->setPostalCode($addressData['postcode']);
                    $storeAddress->setCountryCode($addressData['country_id']);
                    $shipToStoreOption = $this->shipToStoreOptionFactory->create();

                    $shipToStoreOption->setReference($reference);
                    $shipToStoreOption->setCost($shippingOption->getCost());
                    $shipToStoreOption->setStoreName($addressData['firstname']);
                    $shipToStoreOption->setAddress($storeAddress);
                    $shipToStoreOption->setDistance(null);
                    $shipToStoreOption->setDistanceUnit('km');
                    $shipToStoreOptions[] = $shipToStoreOption;
                } else {
                    $tmpShippingOptions[] = $shippingOption;
                }
            }

            if (!$hasInStorePickup) {
                return $result;
            }

            $result = [$shipToStoreOptions, $tmpShippingOptions];
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
    }

    /**
     * @param $result
     * @param $pickupStoreChecker
     * @param $quote
     * @param $ship_to_store_option
     * @param $addressData
     * @return mixed
     */
    public function getShipToStoreCarrierMethodCodes(
        $result,
        $pickupStoreChecker,
        $quote,
        $ship_to_store_option,
        $addressData
    )
    {
        $this->pickupStoreChecker = $pickupStoreChecker;
        $referenceCode = $ship_to_store_option['reference'];
        if ($this->pickupStoreChecker->isPickupShippingMethod($referenceCode)) {
            return explode('_', $ship_to_store_option['reference'], 2);
        }
        return $result;
    }

    /**
     * @param $pickupStoreChecker
     * @param $quote
     * @param $transaction
     * @return void
     */
    public function setInStoreShippingMethodForPrepareQuote(
        $pickupStoreChecker,
        $quote,
        $transaction
    )
    {
        try {
            $this->pickupStoreChecker = $pickupStoreChecker;
            $shipment = $transaction->order->cart->in_store_shipments[0]->shipment;
            $referenceCode = $shipment->reference;
            if ($this->pickupStoreChecker->isPickupShippingMethod($referenceCode)) {
                $shippingAddress = $quote->getShippingAddress();
                $shippingAddress->setCollectShippingRates(true);
                $shippingAddress->setShippingMethod($referenceCode)->save();
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }

    /**
     * @param $pickupStoreChecker
     * @param $saveDeliveryObserver
     * @param $quote
     * @param $transaction
     * @return void
     */
    public function setInStoreShippingAddressForPrepareQuote(
        $pickupStoreChecker,
        $saveDeliveryObserver,
        $quote,
        $transaction
    )
    {
        try {
            $this->pickupStoreChecker = $pickupStoreChecker;
            $this->saveDeliveryObserver = $saveDeliveryObserver;
            $shipment = $transaction->order->cart->in_store_shipments[0]->shipment;
            $referenceCode = $shipment->reference;
            if ($this->pickupStoreChecker->isPickupShippingMethod($referenceCode)) {
                $storeId = $this->pickupStoreChecker->getPickupStoreIdByShippingMethod($referenceCode);
                $addressData = $this->saveDeliveryObserver->getStorePickupAddress($storeId);
                if (is_string($addressData['region'])) {
                    $region = $this->regionModel->loadByName($addressData['region'], $addressData['country_id']);
                    $addressData['region_id'] = $region ? $region->getId() : null;
                }

                $quote->setPickupStore($storeId);
                $quote->setPickupDate(self::DEFAULT_PICKUP_TIME);
                $quote->getShippingAddress()->addData($addressData);
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
}
