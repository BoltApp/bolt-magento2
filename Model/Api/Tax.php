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

use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Bolt\Boltpay\Api\Data\ShippingTaxDataInterface;
use Bolt\Boltpay\Api\TaxInterface;
use Bolt\Boltpay\Api\Data\TaxDataInterfaceFactory;
use Bolt\Boltpay\Api\Data\TaxResultInterfaceFactory;
use Bolt\Boltpay\Api\Data\TaxResultInterface;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Checkout\Api\TotalsInformationManagementInterface;
use Magento\Checkout\Api\Data\TotalsInformationInterface;
use Bolt\Boltpay\Model\Api\ShippingTaxContext;
use Bolt\Boltpay\Model\Api\ShippingTax;
use Magento\Quote\Api\Data\TotalsInterface;

/**
 * Class ShippingMethods
 * Shipping and Tax hook endpoint. Get shipping methods using shipping address and cart details
 *
 * @package Bolt\Boltpay\Model\Api
 */
class Tax extends ShippingTax implements TaxInterface
{
    const METRICS_SUCCESS_KEY = 'tax.success';
    const METRICS_FAILURE_KEY = 'tax.failure';
    const METRICS_LATENCY_KEY = 'tax.latency';

    const CACHE_IDENTIFIER_PREFIX = 'TAX';

    /**
     * @var TaxDataInterfaceFactory
     */
    protected $taxDataInterfaceFactory;

    /**
     * @var TaxResultInterfaceFactory
     */
    protected $taxResultInterfaceFactory;

    /**
     * @var TotalsInformationManagementInterface
     */
    protected $totalsInformationManagementInterface;

    /**
     * @var ShippingTaxContext
     */
    protected $shippingTaxContext;

    /**
     * Assigns local references to global resources
     *
     * @param ShippingTaxContext $shippingTaxContext
     *
     * @param TaxDataInterfaceFactory $taxDataInterfaceFactory
     * @param TaxResultInterfaceFactory $taxResultInterfaceFactory
     * @param TotalsInformationManagementInterface $totalsInformationManagementInterface
     */
    public function __construct(
        ShippingTaxContext $shippingTaxContext,

        TaxDataInterfaceFactory $taxDataInterfaceFactory,
        TaxResultInterfaceFactory $taxResultInterfaceFactory,
        TotalsInformationManagementInterface $totalsInformationManagementInterface
    ) {
        parent::__construct($shippingTaxContext);

        $this->taxDataInterfaceFactory = $taxDataInterfaceFactory;
        $this->taxResultInterfaceFactory = $taxResultInterfaceFactory;
        $this->totalsInformationManagementInterface = $totalsInformationManagementInterface;
    }

    /**
     * @param array $addressData
     * @param array $shipping_option
     * @return TotalsInformationInterface
     */
    public function createAddressInformation($addressData, $shipping_option)
    {
        $selectedOption = $shipping_option['reference'];

        list($carrierCode, $methodCode) = explode('_', $selectedOption);

        $address = $this->quote->isVirtual() ? $this->quote->getBillingAddress() : $this->quote->getShippingAddress();

        $addressData = $this->reformatAddressData($addressData);
        $address->addData($addressData);

        /**
         * @var TotalsInformationInterface $addressInformation
         */
        $addressInformation = $this->objectManager->create(
            '\Magento\Checkout\Api\Data\TotalsInformationInterface'
        );

        $addressInformation->setShippingCarrierCode($carrierCode);
        $addressInformation->setShippingMethodCode($methodCode);
        $addressInformation->setAddress($address);

        return $addressInformation;
    }

    /**
     * @param TotalsInterface $totalsInformation
     * @param string $currencyCode
     * @return TaxResultInterface
     * @throws \Exception
     */
    public function createTaxResult($totalsInformation, $currencyCode)
    {
        $majorAmount = $totalsInformation->getTaxAmount();
        $taxAmount = CurrencyUtils::toMinor($majorAmount, $currencyCode);

        /**
         * @var TaxResultInterface $taxResult
         */
        $taxResult = $this->taxResultInterfaceFactory->create();
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
        $shippingOption = $this->shippingOptionInterfaceFactory->create();
        $shippingOption->setTaxAmount(CurrencyUtils::toMinor($totalsInformation->getShippingTaxAmount(), $currencyCode));
        $shippingOption->setService($shipping_option['service']);
        $shippingOption->setCost(CurrencyUtils::toMinor($totalsInformation->getShippingAmount(), $currencyCode));
        $shippingOption->setReference($shipping_option['reference']);

        return $shippingOption;
    }

    /**
     * @param array $addressData
     * @param array $shipping_option
     * @return TaxDataInterface
     * @throws \Exception
     */
    public function generateResult($addressData, $shipping_option)
    {
        $addressInformation = $this->createAddressInformation($addressData, $shipping_option);

        $totalsInformation = $this->totalsInformationManagementInterface->calculate(
            $this->quote->getId(),
            $addressInformation
        );

        $currencyCode = $this->quote->getQuoteCurrencyCode();

        $taxResult = $this->createTaxResult($totalsInformation, $currencyCode);
        $shippingOption = $this->createShippingOption($totalsInformation, $currencyCode, $shipping_option);

        // shipping tax is already included, don't count it twice
        $taxResult->setSubtotalAmount($taxResult->getSubtotalAmount() - $shippingOption->getTaxAmount());

        $taxData = $this->taxDataInterfaceFactory->create();
        $taxData->setTaxResult($taxResult);
        $taxData->setShippingOption($shippingOption);

        return $taxData;
    }
}
