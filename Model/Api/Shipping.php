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

use Bolt\Boltpay\Api\Data\ShippingDataInterface;
use Bolt\Boltpay\Api\Data\ShippingTaxDataInterface;
use Bolt\Boltpay\Api\ShippingInterface;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Api\Data\ShippingDataInterfaceFactory;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Exception\BoltException;
use Magento\Quote\Model\ShippingMethodManagement;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Model\Data\Region;
use Bolt\Boltpay\Model\Api\ShippingTaxContext;

/**
 * Class ShippingMethods
 * Shipping options hook endpoint. Get shipping methods using shipping address and cart details
 *
 * @package Bolt\Boltpay\Model\Api
 */
class Shipping extends ShippingTax implements ShippingInterface
{
    const NO_SHIPPING_SERVICE = 'No Shipping Required';
    const NO_SHIPPING_REFERENCE = 'noshipping';

    const METRICS_SUCCESS_KEY = 'shipping.success';
    const METRICS_FAILURE_KEY = 'shipping.failure';
    const METRICS_LATENCY_KEY = 'shipping.latency';

    const CACHE_IDENTIFIER_PREFIX = 'SHIPPING';

    /**
     * @var ShippingDataInterfaceFactory
     */
    protected $shippingDataInterfaceFactory;

    /**
     * @var ShippingMethodManagement
     */
    protected $shippingMethodManagement;

    /**
     * Customer address interface factory.
     * @var AddressInterfaceFactory $addressFactory
     */
    protected $addressFactory;

    /**
     * @var ShippingTaxContext
     */
    protected $shippingTaxContext;

    /**
     * Assigns local references to global resources
     *
     * @param ShippingTaxContext $shippingTaxContext
     *
     * @param ShippingDataInterfaceFactory $shippingDataInterfaceFactory
     * @param ShippingMethodManagement $shippingMethodManagement
     * @param AddressInterfaceFactory $addressFactory
     */
    public function __construct(
        ShippingTaxContext $shippingTaxContext,

        ShippingDataInterfaceFactory $shippingDataInterfaceFactory,
        ShippingMethodManagement $shippingMethodManagement,
        AddressInterfaceFactory $addressFactory
    ) {
        parent::__construct($shippingTaxContext);

        $this->shippingDataInterfaceFactory = $shippingDataInterfaceFactory;
        $this->shippingMethodManagement = $shippingMethodManagement;
        $this->addressFactory = $addressFactory;
    }

    /**
     * @param array $addressData
     * @param null $shipping_option
     * @return ShippingDataInterface
     * @throws LocalizedException
     */
    public function generateResult($addressData, $shipping_option)
    {
        $shippingOptions = $this->getShippingOptionsArray($this->quote, $addressData);
        /**
         * @var ShippingDataInterface $shippingData
         */
        $shippingData = $this->shippingDataInterfaceFactory->create();
        $shippingData->setShippingOptions($shippingOptions);
        return $shippingData;
    }

    protected function restrictedMethodCaller($object, $methodName, ...$params)
    {
        // Call protected method with a Closure proxy
        $methodCaller = function ($methodName, ...$params) {
            return $this->$methodName(...$params);
        };
        return $methodCaller->call($object, $methodName, ...$params);
    }

    /**
     * Collects shipping options for the quote and received address data
     *
     * @param Quote $quote
     * @param array $addressData
     *
     * @return ShippingOptionInterface[]
     */
    public function getShippingOptionsArray($quote, $addressData)
    {
        if ($quote->isVirtual()) {
            return [
                $this->shippingOptionInterfaceFactory
                    ->create()
                    ->setService(self::NO_SHIPPING_SERVICE)
                    ->setCost(0)
                    ->setReference(self::NO_SHIPPING_REFERENCE)
            ];
        }

        $regionInstance = $this->regionModel->loadByName(@$addressData['region'], @$addressData['country_code']);

        $addressData = $this->reformatAddressData($addressData);

        $region = $this->objectManager->create(
            'Magento\Customer\Model\Data\Region',
            [
                Region::REGION => $addressData['region'],
                Region::REGION_ID => $addressData['region_id'],
                Region::REGION_CODE => $regionInstance->getCode()
            ]
        );

        /**
         * @var \Magento\Framework\Api\ExtensibleDataInterface $address
         */
        $address = $this->addressFactory->create()
            ->setCountryId($addressData['country_id'])
            ->setPostcode($addressData['postcode'])
            ->setRegionId($addressData['region_id'])
            ->setRegion($region);
        /**
         * @var \Magento\Quote\Api\Data\ShippingMethodInterface[] $shippingMethodArray
         */
        $shippingMethodArray = $this->restrictedMethodCaller(
            $this->shippingMethodManagement,
            'getEstimatedRates',
            $quote,
            $addressData['country_id'],
            $addressData['postcode'],
            $addressData['region_id'],
            $addressData['region'],
            $address
        );

        $shippingMethods = [];
        $errors = [];

        foreach ($shippingMethodArray as $shippingMethod) {
            $service = $shippingMethod->getCarrierTitle() . ' - ' . $shippingMethod->getMethodTitle();
            $method  = $shippingMethod->getCarrierCode() . '_' . $shippingMethod->getMethodCode();

            $cost = $shippingMethod->getAmount();
            $currencyCode = $quote->getQuoteCurrencyCode();
            $roundedCost = CurrencyUtils::toMinor($cost, $currencyCode);

            $error = $shippingMethod->getErrorMessage();

            if ($error) {
                $errors[] = [
                    'service'    => $service,
                    'reference'  => $method,
                    'cost'       => $roundedCost,
                    'error'      => $error
                ];
                continue;
            }

            $shippingMethods[] = $this->shippingOptionInterfaceFactory
                ->create()
                ->setService($service)
                ->setCost($roundedCost)
                ->setReference($method);
        }

        if ($errors) {
            $this->bugsnag->registerCallback(function ($report) use ($errors, $addressData) {
                $report->setMetaData([
                    'SHIPPING METHOD' => [
                        'address' => $addressData,
                        'errors'  => $errors
                    ]
                ]);
            });

            $this->bugsnag->notifyError('Shipping Method Error', $error);
        }

        if (!$shippingMethods) {
            $this->bugsnag->registerCallback(function ($report) use ($quote, $addressData) {
                $report->setMetaData([
                    'SHIPPING' => [
                        'address' => $addressData,
                        'immutable quote ID' => $quote->getId(),
                        'parent quote ID' => $quote->getBoltParentQuoteId(),
                        'order increment ID' => $quote->getReservedOrderId(),
                        'Store Id'  => $quote->getStoreId()
                    ]
                ]);
            });

            throw new BoltException(
                __('No Shipping Methods retrieved'),
                null,
                BoltErrorResponse::ERR_SERVICE
            );
        }
        return $shippingMethods;
    }
}
