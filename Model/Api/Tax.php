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

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Bolt\Boltpay\Api\TaxInterface;
use Bolt\Boltpay\Api\Data\TaxDataInterfaceFactory;
use Bolt\Boltpay\Api\Data\TaxResultInterfaceFactory;
use Bolt\Boltpay\Api\Data\TaxResultInterface;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Checkout\Api\TotalsInformationManagementInterface;
use Magento\Checkout\Api\Data\TotalsInformationInterface;
use Magento\Quote\Api\Data\TotalsInterface;

/**
 * Class Tax
 * Tax hook endpoint. Get resulting tax data.
 *
 * @package Bolt\Boltpay\Model\Api
 */
class Tax extends ShippingTax implements TaxInterface
{
    const METRICS_SUCCESS_KEY = 'tax.success';
    const METRICS_FAILURE_KEY = 'tax.failure';
    const METRICS_LATENCY_KEY = 'tax.latency';

    /**
     * @var TaxDataInterfaceFactory
     */
    protected $taxDataFactory;

    /**
     * @var TaxResultInterfaceFactory
     */
    protected $taxResultFactory;

    /**
     * @var TotalsInformationManagementInterface
     */
    protected $totalsInformationManagement;

    /**
     * @var ShippingTaxContext
     */
    protected $shippingTaxContext;

    /**
     * @var TotalsInformationInterface
     */
    protected $addressInformation;

    /**
     * Assigns local references to global resources
     *
     * @param ShippingTaxContext $shippingTaxContext
     *
     * @param TaxDataInterfaceFactory $taxDataFactory
     * @param TaxResultInterfaceFactory $taxResultFactory
     * @param TotalsInformationManagementInterface $totalsInformationManagement
     * @param TotalsInformationInterface $addressInformation
     */
    public function __construct(
        ShippingTaxContext $shippingTaxContext,
        TaxDataInterfaceFactory $taxDataFactory,
        TaxResultInterfaceFactory $taxResultFactory,
        TotalsInformationManagementInterface $totalsInformationManagement,
        TotalsInformationInterface $addressInformation
    ) {
        parent::__construct($shippingTaxContext);
        $this->taxDataFactory = $taxDataFactory;
        $this->taxResultFactory = $taxResultFactory;
        $this->totalsInformationManagement = $totalsInformationManagement;
        $this->addressInformation = $addressInformation;
    }

    /**
     * @param array $addressData
     * @param array|null $shipping_option
     * @param array|null $ship_to_store_option
     * @return TotalsInformationInterface
     */
    public function setAddressInformation($addressData, $shipping_option, $ship_to_store_option)
    {
        list($address,) = $this->populateAddress($addressData);
        $this->addressInformation->setAddress($address);

        if (!$shipping_option && !$ship_to_store_option) {
            return ;
        }

        if ($shipping_option) {
            list($carrierCode, $methodCode) = explode('_', $shipping_option['reference'], 2);
        } else { 
            list($carrierCode, $methodCode) = $this->eventsForThirdPartyModules->runFilter("getShipToStoreCarrierMethodCodes", ['', ''], $this->quote, $ship_to_store_option, $addressData);
        }

        $this->addressInformation->setShippingCarrierCode($carrierCode);
        $this->addressInformation->setShippingMethodCode($methodCode);
        
        $this->eventsForThirdPartyModules->dispatchEvent("setExtraAddressInformation", $this->addressInformation, $this->quote, $shipping_option, $ship_to_store_option, $addressData);
    }

    /**
     * @param TotalsInterface $totalsInformation
     * @param string $currencyCode
     * @return TaxResultInterface
     * @throws \Exception
     */
    public function createTaxResult($totalsInformation, $currencyCode)
    {
        $majorAmount = $totalsInformation->getTaxAmount() - $totalsInformation->getShippingTaxAmount();
        $taxAmount = CurrencyUtils::toMinor($majorAmount, $currencyCode);

        /**
         * @var TaxResultInterface $taxResult
         */
        $taxResult = $this->taxResultFactory->create();
        $taxResult->setSubtotalAmount($taxAmount);

        return $taxResult;
    }

    /**
     * @param TotalsInterface $totalsInformation
     * @param string $currencyCode
     * @param array $shipping_option
     * @return ShippingOptionInterface
     * @throws \Exception
     */
    public function createShippingOption($totalsInformation, $currencyCode, $shipping_option)
    {
        $shippingOption = $this->shippingOptionFactory->create();
        $shippingOption->setTaxAmount(CurrencyUtils::toMinor($totalsInformation->getShippingTaxAmount(), $currencyCode));
        $shippingOption->setService($shipping_option['service'] ?? null);
        $shippingOption->setCost(CurrencyUtils::toMinor($totalsInformation->getShippingAmount(), $currencyCode));
        $shippingOption->setReference($shipping_option['reference'] ?? null);

        return $shippingOption;
    }
    
    /**
     * @param TotalsInterface $totalsInformation
     * @param string $currencyCode
     * @param array $ship_to_store_option
     * @return ShipToStoreOptionInterface
     * @throws \Exception
     */
    public function createInstorePickOption($totalsInformation, $currencyCode, $ship_to_store_option)
    {
        $storeAddress = $this->storeAddressFactory->create();
        $storeAddress->setStreetAddress1($ship_to_store_option['address']['street_address1']);
        $storeAddress->setStreetAddress2($ship_to_store_option['address']['street_address2']);
        $storeAddress->setLocality($ship_to_store_option['address']['locality']);
        $storeAddress->setRegion($ship_to_store_option['address']['region']);
        $storeAddress->setPostalCode($ship_to_store_option['address']['postal_code']);
        
        $shipToStoreOption = $this->shipToStoreOptionFactory->create();
        $shipToStoreOption->setReference($ship_to_store_option['reference']);
        $shipToStoreOption->setCost(CurrencyUtils::toMinor($totalsInformation->getShippingAmount(), $currencyCode));
        $shipToStoreOption->setStoreName($ship_to_store_option['store_name']);
        $shipToStoreOption->setAddress($storeAddress);
        $shipToStoreOption->setDistance($ship_to_store_option['distance']);
        $shipToStoreOption->setDistanceUnit($ship_to_store_option['distance_unit']);
        $shipToStoreOption->setTaxAmount(CurrencyUtils::toMinor($totalsInformation->getShippingTaxAmount(), $currencyCode));

        return $shipToStoreOption;
    }

    /**
     * @param array $addressData
     * @param array|null $shipping_option
     * @param array|null $ship_to_store_option
     * @return TaxDataInterface
     * @throws \Exception
     */
    public function generateResult($addressData, $shipping_option, $ship_to_store_option)
    {
        $this->setAddressInformation($addressData, $shipping_option, $ship_to_store_option);

        $totalsInformation = $this->totalsInformationManagement->calculate(
            $this->quote->getId(),
            $this->addressInformation
        );

        $currencyCode = $this->quote->getQuoteCurrencyCode();

        $taxResult = $this->createTaxResult($totalsInformation, $currencyCode);

        $taxData = $this->taxDataFactory->create();
        $taxData->setTaxResult($taxResult);
        
        if (!empty($shipping_option)) {
            $shippingOption = $this->createShippingOption($totalsInformation, $currencyCode, $shipping_option);
            $taxData->setShippingOption($shippingOption);
        } else {
            $ship_to_store_option = $this->createInstorePickOption($totalsInformation, $currencyCode, $ship_to_store_option);
            $taxData->setShipToStoreOption($ship_to_store_option);
        }

        return $taxData;
    }
}
