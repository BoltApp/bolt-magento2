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

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Bolt\Boltpay\Api\TaxInterface;
use Bolt\Boltpay\Api\Data\TaxDataInterfaceFactory;
use Bolt\Boltpay\Api\Data\TaxResultInterfaceFactory;
use Bolt\Boltpay\Api\Data\TaxResultInterface;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Checkout\Api\TotalsInformationManagementInterface;
use Magento\Checkout\Api\Data\TotalsInformationInterface;
use Magento\Quote\Api\Data\TotalsInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;

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
     * @var PriceHelper
     */
    protected $priceHelper;

    /**
     * @var CartHelper
     */
    protected $cartHelper;
    
    /**
     * Quote repository.
     *
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * Assigns local references to global resources
     *
     * @param ShippingTaxContext $shippingTaxContext
     *
     * @param TaxDataInterfaceFactory $taxDataFactory
     * @param TaxResultInterfaceFactory $taxResultFactory
     * @param TotalsInformationManagementInterface $totalsInformationManagement
     * @param TotalsInformationInterface $addressInformation
     * @param PriceHelper $priceHelper
     * @param CartHelper $cartHelper
     */
    public function __construct(
        ShippingTaxContext $shippingTaxContext,
        TaxDataInterfaceFactory $taxDataFactory,
        TaxResultInterfaceFactory $taxResultFactory,
        TotalsInformationManagementInterface $totalsInformationManagement,
        TotalsInformationInterface $addressInformation,
        PriceHelper $priceHelper,
        CartHelper $cartHelper
    )
    {
        parent::__construct($shippingTaxContext);
        $this->taxDataFactory = $taxDataFactory;
        $this->taxResultFactory = $taxResultFactory;
        $this->totalsInformationManagement = $totalsInformationManagement;
        $this->addressInformation = $addressInformation;
        $this->priceHelper = $priceHelper;
        $this->cartHelper = $cartHelper;
        $this->cartRepository = $shippingTaxContext->getCartRepository();
    }

    /**
     * @param array $addressData
     * @param array|null $shipping_option
     * @param array|null $ship_to_store_option
     */
    public function setAddressInformation($addressData, $shipping_option, $ship_to_store_option)
    {
        list($address,) = $this->populateAddress($addressData);
        $this->addressInformation->setAddress($address);

        if ($this->quote->isVirtual() || (!$shipping_option && !$ship_to_store_option)) {
            return;
        }

        if ($shipping_option) {
            list($carrierCode, $methodCode) = explode('_', $shipping_option['reference'], 2);
        } else {
            list($carrierCode, $methodCode) = $this->eventsForThirdPartyModules->runFilter("getShipToStoreCarrierMethodCodes", ['', ''], $this->quote, $ship_to_store_option, $addressData);
        }

        $this->addressInformation->setShippingCarrierCode($carrierCode);
        $this->addressInformation->setShippingMethodCode($methodCode);
        
        if ($shipping_option && $this->cartHelper->checkIfQuoteHasCartFixedAmountAndApplyToShippingRule($this->quote)) {
            // If a customer applies a cart rule (fixed amount for whole cart and apply to shipping) via the cart pgae,
            // the function Magento\SalesRule\Helper\CartFixedDiscount::calculateShippingAmountWhenAppliedToShipping does not return correct value for tax calculation,
            // it is because the $address->getShippingAmount() still returns shipping amount of last selected shipping method.
            // So we need to correct the shipping amount.
            $shippingCost = CurrencyUtils::toMajor($shipping_option['cost'], $this->quote->getQuoteCurrencyCode());
            $address->setShippingAmount($shippingCost);
            $this->addressInformation->setAddress($address);
        }
        
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
        $shipping_option = $this->getShippingDiscount($totalsInformation, $currencyCode, $shipping_option);

        $shippingOption = $this->shippingOptionFactory->create();
        $shippingOption->setTaxAmount($shipping_option['tax_amount']);
        $shippingOption->setService($shipping_option['service'] ?? null);
        $shippingAmount = $this->eventsForThirdPartyModules->runFilter(
            "adjustShippingAmountInTaxEndPoint",
            $shipping_option['cost'],
            $this->quote
        );
        $shippingOption->setCost($shippingAmount);
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

        $ship_to_store_option = $this->getShippingDiscount($totalsInformation, $currencyCode, $ship_to_store_option);

        $shipToStoreOption = $this->shipToStoreOptionFactory->create();
        $shipToStoreOption->setReference($ship_to_store_option['reference']);
        $shipToStoreOption->setCost($ship_to_store_option['cost']);
        $shipToStoreOption->setStoreName($ship_to_store_option['store_name']);
        $shipToStoreOption->setAddress($storeAddress);
        $shipToStoreOption->setDistance($ship_to_store_option['distance']);
        $shipToStoreOption->setDistanceUnit($ship_to_store_option['distance_unit']);
        $shipToStoreOption->setTaxAmount($ship_to_store_option['tax_amount']);

        return $shipToStoreOption;
    }

    /**
     * @param TotalsInterface $totalsInformation
     * @param string $currencyCode
     * @param array $shippingOption
     * @return array
     * @throws \Exception
     */
    public function getShippingDiscount($totalsInformation, $currencyCode, $shippingOption)
    {
        $shippingDiscountAmount = $this->eventsForThirdPartyModules->runFilter(
            "collectShippingDiscounts",
            $totalsInformation->getShippingDiscountAmount(),
            $this->quote,
            $this->quote->getShippingAddress()
        );

        // Exclude discount amount of "Fixed amount discount for whole cart" sale rule from shipping discounts.
        // Cause the Bolt cart has full discount amount of such type of sale rule applied before collecting shipping&tax.
        if ($cartRules = $this->cartRepository->get($this->quote->getId())->getCartFixedRules()) {
            foreach ($cartRules as $cartRuleId => $cartRuleShippingDiscountAmount) {
                $shippingDiscountAmount -= $cartRuleShippingDiscountAmount;
            }
        }
        
        if ($shippingDiscountAmount >= DiscountHelper::MIN_NONZERO_VALUE && !$this->cartHelper->ignoreAdjustingShippingAmount($this->quote)) {
            $service = $shippingOption['service'] ?? '';
            $shippingCost = $totalsInformation->getShippingAmount() - $shippingDiscountAmount;
            $shippingRoundedCost = CurrencyUtils::toMinor($shippingCost, $currencyCode);
            $diff = CurrencyUtils::toMinorWithoutRounding($shippingCost, $currencyCode) - $shippingRoundedCost;
            $taxAmount = round(CurrencyUtils::toMinorWithoutRounding($totalsInformation->getShippingTaxAmount(), $currencyCode) + $diff);
            if ($shippingRoundedCost == 0) {
                $service .= ' [free&nbsp;shipping&nbsp;discount]';
            } else {
                $discount = $this->priceHelper->currency($shippingDiscountAmount, true, false);
                $service .= " [$discount" . "&nbsp;discount]";
            }
            $shippingOption['service'] = html_entity_decode($service);
        } else {
            $shippingRoundedCost = CurrencyUtils::toMinor($totalsInformation->getShippingAmount(), $currencyCode);
            $taxAmount = CurrencyUtils::toMinor($totalsInformation->getShippingTaxAmount(), $currencyCode);
        }

        $shippingOption['cost'] = $shippingRoundedCost;
        $shippingOption['tax_amount'] = $taxAmount;

        return $shippingOption;
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

        if ($shipping_option && $this->cartHelper->checkIfQuoteHasCartFixedAmountAndApplyToShippingRuleAndTableRateShippingMethod($this->quote, $shipping_option['reference']))  {
            // If a customer applies a cart rule (fixed amount for whole cart and apply to shipping) and the table rate shipping method,
            // we must re-set FreeMethodWeight of the parent quote from the immutable quote to get the correct shipping amount
            $this->immutableQuote->collectTotals();
            $this->addressInformation->getAddress()->setFreeMethodWeight(
                $this->immutableQuote->getShippingAddress()->getFreeMethodWeight()
            );
        }

        $totalsInformation = $this->totalsInformationManagement->calculate(
            $this->quote->getId(),
            $this->addressInformation
        );

        $currencyCode = $this->quote->getQuoteCurrencyCode();

        $taxResult = $this->createTaxResult($totalsInformation, $currencyCode);

        $taxData = $this->taxDataFactory->create();
        $taxData->setTaxResult($taxResult);

        if ($this->eventsForThirdPartyModules->runFilter('filterTaxResult', false, $taxData, $totalsInformation, $currencyCode, $shipping_option, $ship_to_store_option, $this->quote)) {
            return $taxData;
        }

        if (!empty($shipping_option)) {
            $shippingOption = $this->createShippingOption($totalsInformation, $currencyCode, $shipping_option);
            $taxData->setShippingOption($shippingOption);
        } elseif (!empty($ship_to_store_option)) {
            $ship_to_store_option = $this->createInstorePickOption($totalsInformation, $currencyCode, $ship_to_store_option);
            $taxData->setShipToStoreOption($ship_to_store_option);
        }

        return $taxData;
    }
}
