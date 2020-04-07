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

use Bolt\Boltpay\Api\Data\TaxDataInterface;
use Bolt\Boltpay\Api\TaxInterface;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Api\Data\TaxDataInterfaceFactory;
use Bolt\Boltpay\Api\Data\TaxResultInterfaceFactory;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\App\CacheInterface;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Magento\Customer\Model\Data\Region;
use Magento\Checkout\Api\TotalsInformationManagementInterface;
use Magento\Checkout\Api\Data\TotalsInformationInterface;
use Bolt\Boltpay\Model\Api\ShippingTaxContext;
use Magento\Framework\App\ObjectManager;
use Bolt\Boltpay\Model\Api\ShippingTax;

/**
 * Class ShippingMethods
 * Shipping and Tax hook endpoint. Get shipping methods using shipping address and cart details
 *
 * @package Bolt\Boltpay\Model\Api
 */
class Tax extends ShippingTax implements TaxInterface
{
    CONST METRICS_SUCCESS_KEY = 'tax.success';
    CONST METRICS_FAILURE_KEY = 'tax.failure';
    CONST METRICS_LATENCY_KEY = 'tax.latency';

    /**
     * @var HookHelper
     */
    protected $hookHelper;

    /**
     * @var CartHelper
     */
    protected $cartHelper;

    /**
     * @var RegionModel
     */
    protected $regionModel;

    /**
     * @var TaxDataInterfaceFactory
     */
    protected $taxDataInterfaceFactory;

    /**
     * @var TaxResultInterfaceFactory
     */
    protected $taxResultInterfaceFactory;

    /**
     * @var ShippingOptionInterfaceFactory
     */
    protected $shippingOptionInterfaceFactory;

    /**
     * @var Bugsnag
     */
    protected $bugsnag;

    /**
     * @var MetricsClient
     */
    protected $metricsClient;

    /**
     * @var LogHelper
     */
    protected $logHelper;

    /**
     * @var BoltErrorResponse
     */
    protected $errorResponse;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var SessionHelper
     */
    protected $sessionHelper;

    /**
     * @var DiscountHelper
     */
    protected $discountHelper;

    /**
     * @var Quote
     */
    protected $quote;

    /**
     * @var TotalsInformationManagementInterface
     */
    protected $totalsInformationManagementInterface;

    /**
     * @var ShippingTaxContext
     */
    protected $shippingTaxContext;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * Assigns local references to global resources
     *
     * @param ShippingTaxContext $shippingTaxContext
     *
     * @param TaxDataInterfaceFactory $taxDataInterfaceFactory
     * @param TaxResultInterfaceFactory $taxResultInterfaceFactory
     * @param ShippingOptionInterfaceFactory $shippingOptionInterfaceFactory
     * @param TotalsInformationManagementInterface $totalsInformationManagementInterface
     */
    public function __construct(
        ShippingTaxContext $shippingTaxContext,

        TaxDataInterfaceFactory $taxDataInterfaceFactory,
        TaxResultInterfaceFactory $taxResultInterfaceFactory,
        ShippingOptionInterfaceFactory $shippingOptionInterfaceFactory,
        TotalsInformationManagementInterface $totalsInformationManagementInterface
    ) {
        parent::__construct($shippingTaxContext);

        $this->taxDataInterfaceFactory = $taxDataInterfaceFactory;
        $this->taxResultInterfaceFactory = $taxResultInterfaceFactory;
        $this->shippingOptionInterfaceFactory = $shippingOptionInterfaceFactory;
        $this->totalsInformationManagementInterface = $totalsInformationManagementInterface;
    }

    public function generateResult($addressData, $shipping_option)
    {
        $this->applyExternalQuoteData($this->quote);

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

        $totalsInformation = $this->totalsInformationManagementInterface->calculate(
            $this->quote->getId(),
            $addressInformation
        );

        $cost = $totalsInformation->getTaxAmount();
        $currencyCode = $this->quote->getQuoteCurrencyCode();
        $roundedCost = CurrencyUtils::toMinor($cost, $currencyCode);

        $taxResult = $this->taxResultInterfaceFactory->create();
        $taxResult->setSubtotalAmount($roundedCost);

        $shippingOption = $this->shippingOptionInterfaceFactory->create();
        $shippingOption->setTaxAmount(CurrencyUtils::toMinor($totalsInformation->getShippingTaxAmount(), $currencyCode));
        $shippingOption->setService($shipping_option['service']);
        $shippingOption->setCost(CurrencyUtils::toMinor($totalsInformation->getShippingAmount(), $currencyCode));
        $shippingOption->setReference($shipping_option['reference']);

        $taxDataModel = $this->taxDataInterfaceFactory->create();
        $taxDataModel->setTaxResult($taxResult);
        $taxDataModel->setShippingOption($shippingOption);

        $this->metricsClient->processMetric("tax.success", 1, "tax.latency", $startTime);

        return $taxDataModel;
    }
}
