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

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\Data\ShippingDataInterface;
use Bolt\Boltpay\Api\Data\ShippingDataInterfaceFactory;
use Bolt\Boltpay\Api\ShippingInterface;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Exception\BoltException;
use \Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\ShipmentEstimationInterface;

/**
 * Class Shipping
 * Shipping options hook endpoint. Get shipping methods using shipping address and cart details
 *
 * @package Bolt\Boltpay\Model\Api
 */
class Shipping extends ShippingTax implements ShippingInterface
{
    const METRICS_SUCCESS_KEY = 'shipping.success';
    const METRICS_FAILURE_KEY = 'shipping.failure';
    const METRICS_LATENCY_KEY = 'shipping.latency';

    /**
     * @var ShippingDataInterfaceFactory
     */
    protected $shippingDataFactory;

    /**
     * @var ShipmentEstimationInterface
     */
    protected $shippingMethodManagement;

    /**
     * @var ShippingTaxContext
     */
    protected $shippingTaxContext;

    /**
     * @var AddressInterfaceFactory
     */
    protected $addressFactory;

    /**
     * Assigns local references to global resources
     *
     * @param ShippingTaxContext $shippingTaxContext
     *
     * @param ShippingDataInterfaceFactory $shippingDataFactory
     * @param ShipmentEstimationInterface $shippingMethodManagement
     * @param AddressInterfaceFactory $addressFactory
     */
    public function __construct(
        ShippingTaxContext $shippingTaxContext,
        ShippingDataInterfaceFactory $shippingDataFactory,
        ShipmentEstimationInterface $shippingMethodManagement,
        AddressInterfaceFactory $addressFactory
    ) {
        parent::__construct($shippingTaxContext);

        $this->shippingDataFactory = $shippingDataFactory;
        $this->shippingMethodManagement = $shippingMethodManagement;
        $this->addressFactory = $addressFactory;
    }

    /**
     * @param array $addressData
     * @param null $shipping_option
     * @param null $ship_to_store_option
     * @return ShippingDataInterface
     * @throws LocalizedException
     */
    public function generateResult($addressData, $shipping_option, $ship_to_store_option)
    {
        $shippingOptions = $this->getShippingOptions($addressData);
        // Get ship to store options
        list($shipToStoreOptions, $shippingOptions) = $this->eventsForThirdPartyModules->runFilter("getShipToStoreOptions", [[],$shippingOptions], $this->quote, $shippingOptions, $addressData);
        $this->eventsForThirdPartyModules->dispatchEventAndThrowException("afterGetShipToStoreAndShippingOptions", $shipToStoreOptions, $shippingOptions, $this->quote);
        /**
         * @var ShippingDataInterface $shippingData
         */
        $shippingData = $this->shippingDataFactory->create();
        $shippingData->setShippingOptions($shippingOptions);
        $shippingData->setShipToStoreOptions($shipToStoreOptions);
        return $shippingData;
    }

    /**
     * @param ShippingMethodInterface[] $shippingOptionsArray
     * @param string $currencyCode
     * @return array[]
     * @throws \Exception
     */
    public function formatResult($shippingOptionsArray, $currencyCode)
    {
        $shippingOptions = [];
        $errors = [];

        foreach ($shippingOptionsArray as $shippingOption) {
            $service = $shippingOption->getCarrierTitle() . ' - ' . $shippingOption->getMethodTitle();
            $method  = $shippingOption->getCarrierCode() . '_' . $shippingOption->getMethodCode();

            $majorAmount = $shippingOption->getAmount();
            $cost = CurrencyUtils::toMinor($majorAmount, $currencyCode);

            if ($error = $shippingOption->getErrorMessage()) {
                $errors[] = [
                    'service'    => $service,
                    'reference'  => $method,
                    'cost'       => $cost,
                    'error'      => $error
                ];
                continue;
            }
            $shippingOptions[] = $this->shippingOptionFactory
                ->create()
                ->setService($service)
                ->setCost($cost)
                ->setReference($method);
        }

        return [$shippingOptions, $errors];
    }

    /**
     * Collects shipping options for the quote and received address data
     *
     * @param array $addressData
     *
     * @return ShippingOptionInterface[]
     */
    public function getShippingOptions($addressData)
    {
        if ($this->quote->isVirtual()) {
            $shippingOptions = [];
            $shippingOptions[] = $this->shippingOptionFactory
                ->create()
                ->setService(self::NO_SHIPPING_SERVICE)
                ->setCost(0)
                ->setReference(self::NO_SHIPPING_REFERENCE);
        } else {
            list(,$addressData) = $this->populateAddress($addressData);
    
            $address = $this->addressFactory->create();
    
            $address->setRegionId($addressData['region_id'] ?? null);
            $address->setRegion($addressData['region'] ?? null);
            $address->setCountryId($addressData['country_id'] ?? null);
            $address->setPostcode($addressData['postcode'] ?? null);
            $address->setCity($addressData['city'] ?? null);
            $address->setStreet($addressData['street'] ?? null);
    
            /**
             * @var ShippingMethodInterface[] $shippingOptionsArray
             */
            $shippingOptionsArray = $this->shippingMethodManagement
                ->estimateByExtendedAddress($this->quote->getId(), $address);
            $currencyCode = $this->quote->getQuoteCurrencyCode();
    
            list($shippingOptions, $errors) = $this->formatResult($shippingOptionsArray, $currencyCode);
    
            if ($errors) {
                $this->bugsnag->registerCallback(function ($report) use ($errors, $addressData) {
                    $report->setMetaData([
                        'SHIPPING ERRORS' => [
                            'address' => $addressData,
                            'errors'  => $errors
                        ]
                    ]);
                });
                $this->bugsnag->notifyError('SHIPPING ERRORS', 'Shipping Method Errors');
            }

            if (!$shippingOptions) {
                $this->eventsForThirdPartyModules->dispatchEventAndThrowException("beforeThrowingNoShippingMethodsException", $errors);
            }
        }

        if (!$shippingOptions) {
            $this->bugsnag->registerCallback(function ($report) use ($addressData) {
                $report->setMetaData([
                    'NO SHIPPING' => [
                        'address' => $addressData,
                        'immutable quote ID' => $this->quote->getId(),
                        'parent quote ID' => $this->quote->getBoltParentQuoteId(),
                        'Store Id' => $this->quote->getStoreId()
                    ]
                ]);
            });
            throw new BoltException(
                __('No Shipping Methods retrieved'),
                null,
                BoltErrorResponse::ERR_SERVICE
            );
        }
        return $shippingOptions;
    }
}
