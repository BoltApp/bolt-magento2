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

use Bolt\Boltpay\Api\Data\ShippingDataInterface;
use Bolt\Boltpay\Api\Data\ShippingDataInterfaceFactory;
use Bolt\Boltpay\Api\ShippingInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Exception\BoltException;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Bolt\Boltpay\Model\Api\ShippingTaxContext;
use \Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Api\Data\EstimateAddressInterfaceFactory;

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
     * @var ShippingMethodManagementInterface
     */
    protected $shippingMethodManagement;

    /**
     * @var ShippingTaxContext
     */
    protected $shippingTaxContext;

    /**
     * @var EstimateAddressInterfaceFactory
     */
    protected $estimateAddressFactory;

    /**
     * Assigns local references to global resources
     *
     * @param ShippingTaxContext $shippingTaxContext
     *
     * @param ShippingDataInterfaceFactory $shippingDataFactory
     * @param ShippingMethodManagementInterface $shippingMethodManagement
     * @param EstimateAddressInterfaceFactory $estimateAddressFactory
     */
    public function __construct(
        ShippingTaxContext $shippingTaxContext,
        ShippingDataInterfaceFactory $shippingDataFactory,
        ShippingMethodManagementInterface $shippingMethodManagement,
        EstimateAddressInterfaceFactory $estimateAddressFactory
    ) {
        parent::__construct($shippingTaxContext);

        $this->shippingDataFactory = $shippingDataFactory;
        $this->shippingMethodManagement = $shippingMethodManagement;
        $this->estimateAddressFactory = $estimateAddressFactory;
    }

    /**
     * @param array $addressData
     * @param null $shipping_option
     * @return ShippingDataInterface
     * @throws LocalizedException
     */
    public function generateResult($addressData, $shipping_option)
    {
        $shippingOptions = $this->getShippingOptions($addressData);
        /**
         * @var ShippingDataInterface $shippingData
         */
        $shippingData = $this->shippingDataFactory->create();
        $shippingData->setShippingOptions($shippingOptions);
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
        $addressData = $this->reformatAddressData($addressData);

        $estimateAddress = $this->estimateAddressFactory->create();

        $estimateAddress->setRegionId(@$addressData['region_id']);
        $estimateAddress->setRegion(@$addressData['region']);
        $estimateAddress->setCountryId(@$addressData['country_id']);
        $estimateAddress->setPostcode(@$addressData['postcode']);
        /**
         * @var ShippingMethodInterface[] $shippingOptionsArray
         */
        $shippingOptionsArray = $this->shippingMethodManagement
            ->estimateByAddress($this->quote->getId(), $estimateAddress);
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
            $this->bugsnag->registerCallback(function ($report) use ($addressData) {
                $report->setMetaData([
                    'NO SHIPPING' => [
                        'address' => $addressData,
                        'immutable quote ID' => $this->quote->getId(),
                        'parent quote ID' => $this->quote->getBoltParentQuoteId(),
                        'order increment ID' => $this->quote->getReservedOrderId(),
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
