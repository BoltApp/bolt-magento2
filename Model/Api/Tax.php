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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
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
use Bolt\Boltpay\Model\Api\ShippingTaxContext;
use Bolt\Boltpay\Model\Api\ShippingTax;
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
     * @param array $shipping_option
     * @return TotalsInformationInterface
     */
    public function setAddressInformation($addressData, $shipping_option)
    {
        $address = $this->populateAddress($addressData);
        $this->addressInformation->setAddress($address);

        if (!$shipping_option) {
            return ;
        }

        $selectedOption = $shipping_option['reference'];
        list($carrierCode, $methodCode) = explode('_', $selectedOption, 2);

        $this->addressInformation->setShippingCarrierCode($carrierCode);
        $this->addressInformation->setShippingMethodCode($methodCode);
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
        $shippingOption->setService(@$shipping_option['service']);
        $shippingOption->setCost(CurrencyUtils::toMinor($totalsInformation->getShippingAmount(), $currencyCode));
        $shippingOption->setReference(@$shipping_option['reference']);

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
        $this->setAddressInformation($addressData, $shipping_option);

        $totalsInformation = $this->totalsInformationManagement->calculate(
            $this->quote->getId(),
            $this->addressInformation
        );

        $currencyCode = $this->quote->getQuoteCurrencyCode();

        $taxResult = $this->createTaxResult($totalsInformation, $currencyCode);
        $shippingOption = $this->createShippingOption($totalsInformation, $currencyCode, $shipping_option);

        $taxData = $this->taxDataFactory->create();
        $taxData->setTaxResult($taxResult);
        $taxData->setShippingOption($shippingOption);

        return $taxData;
    }
}
