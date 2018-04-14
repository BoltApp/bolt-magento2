<?php
/**
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */


namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\Data\ShippingOptionsInterface;
use Bolt\Boltpay\Api\ShippingMethodsInterface;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Quote\Model\QuoteFactory;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Api\Data\ShippingOptionsInterfaceFactory;
use Bolt\Boltpay\Api\Data\ShippingTaxInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Checkout\Model\Session;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\App\CacheInterface;

/**
 * Class ShippingMethods
 * Shipping and Tax hook endpoint. Get shipping methods using shipping address and cart details
 *
 * @package Bolt\Boltpay\Model\Api
 */
class ShippingMethods implements ShippingMethodsInterface
{
    /**
     * @var HookHelper
     */
    protected $hookHelper;

    /**
     * @var CartHelper
     */
    protected $cartHelper;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var RegionModel
     */
    protected $regionModel;

    /**
     * @var ShippingOptionsInterfaceFactory
     */
    protected $shippingOptionsInterfaceFactory;

    /**
     * @var ShippingTaxInterfaceFactory
     */
    protected $shippingTaxInterfaceFactory;

    /**
     * @var TotalsCollector
     */
    protected $totalsCollector;

    /**
     * Shipping method converter
     *
     * @var ShippingMethodConverter
     */
    protected $converter;

    /**
     * @var ShippingOptionInterfaceFactory
     */
    protected $shippingOptionInterfaceFactory;

    /**
     * @var Bugsnag
     */
    protected $bugsnag;

    /**
     * @var LogHelper
     */
    protected $logHelper;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var CacheInterface
     */
    protected $cache;

    // Totals adjustment treshold
    private $treshold = 0.01;

    private $tax_adjusted = false;

    /**
     *
     * @param HookHelper $hookHelper
     * @param QuoteFactory $quoteFactory
     * @param RegionModel $regionModel
     * @param ShippingOptionsInterfaceFactory $shippingOptionsInterfaceFactory
     * @param ShippingTaxInterfaceFactory $shippingTaxInterfaceFactory
     * @param CartHelper $cartHelper
     * @param TotalsCollector $totalsCollector
     * @param ShippingMethodConverter $converter
     * @param ShippingOptionInterfaceFactory $shippingOptionInterfaceFactory
     * @param Bugsnag $bugsnag
     * @param LogHelper $logHelper
     * @param Response $response
     * @param ConfigHelper $configHelper
     * @param Session $checkoutSession
     * @param Request $request
     * @param CacheInterface $cache
     */
    public function __construct(
        HookHelper $hookHelper,
        QuoteFactory $quoteFactory,
        RegionModel $regionModel,
        ShippingOptionsInterfaceFactory $shippingOptionsInterfaceFactory,
        ShippingTaxInterfaceFactory $shippingTaxInterfaceFactory,
        CartHelper $cartHelper,
        TotalsCollector $totalsCollector,
        ShippingMethodConverter $converter,
        ShippingOptionInterfaceFactory $shippingOptionInterfaceFactory,
        Bugsnag $bugsnag,
        LogHelper $logHelper,
        Response $response,
        ConfigHelper $configHelper,
        Session $checkoutSession,
        Request $request,
        CacheInterface $cache
    ) {
        $this->hookHelper                      = $hookHelper;
        $this->cartHelper                      = $cartHelper;
        $this->quoteFactory                    = $quoteFactory;
        $this->regionModel                     = $regionModel;
        $this->shippingOptionsInterfaceFactory = $shippingOptionsInterfaceFactory;
        $this->shippingTaxInterfaceFactory     = $shippingTaxInterfaceFactory;
        $this->totalsCollector                 = $totalsCollector;
        $this->converter                       = $converter;
        $this->shippingOptionInterfaceFactory  = $shippingOptionInterfaceFactory;
        $this->bugsnag                         = $bugsnag;
        $this->logHelper                       = $logHelper;
        $this->response                        = $response;
        $this->configHelper                    = $configHelper;
        $this->checkoutSession                 = $checkoutSession;
        $this->request                         = $request;
        $this->cache                           = $cache;
    }

    /**
     * Get all available shipping methods.
     *
     * @api
     *
     * @param array $cart cart details
     * @param array $shipping_address shipping address
     *
     * @return ShippingOptionsInterface
     * @throws \Exception
     */
    public function getShippingMethods($cart, $shipping_address)
    {
        try {
            if ($bolt_trace_id = $this->request->getHeader(ConfigHelper::BOLT_TRACE_ID_HEADER)) {
                $this->bugsnag->registerCallback(function ($report) use ($bolt_trace_id) {
                    $report->setMetaData([
                        'BREADCRUMBS_' => [
                            'bolt_trace_id' => $bolt_trace_id,
                        ]
                    ]);
                });
            }

            $this->response->setHeader('User-Agent', 'BoltPay/Magento-'.$this->configHelper->getStoreVersion());
            $this->response->setHeader('X-Bolt-Plugin-Version', $this->configHelper->getModuleVersion());

            $this->hookHelper->verifyWebhook();

            $incrementId = $cart['display_id'];

            // Get quote from increment id
            $quote   = $this->quoteFactory->create()->load($incrementId, 'reserved_order_id');
            $quoteId = $quote->getId();

            if (empty($quoteId)) {
                throw new LocalizedException(
                    __('Invalid display_id :%1.', $incrementId)
                );
            }

            $this->checkoutSession->replaceQuote($quote);

            $shippingOptionsModel = $this->shippingEstimation($quote, $cart, $shipping_address);

            if ($this->tax_adjusted) {
                $this->bugsnag->registerCallback(function ($report) use ($shippingOptionsModel) {
                    $report->setMetaData([
                        'SHIPPING OPTIONS' => [print_r($shippingOptionsModel, 1)]
                    ]);
                });
                $this->bugsnag->notifyError('Cart Totals Mismatch', "Totals adjusted.");
            }

            return $shippingOptionsModel;
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }
    }

    /**
     * Save shipping address in quote
     *
     * @param Quote $quote
     * @param array $cart
     * @param array $shipping_address
     *
     * @return ShippingOptionsInterface
     */
    public function shippingEstimation($quote, $cart, $shipping_address)
    {

        ////////////////////////////////////////////////////////////////////////////////////////
        // Check cache storage for estimate. If the quote_id, total_amount, country_code,
        // region and postal_code match then use the cached version.
        ////////////////////////////////////////////////////////////////////////////////////////
        $cache_identifier = $cart['order_reference'].'_'.$cart['total_amount'].'_'.$shipping_address['country_code'].'_'.$shipping_address['region'].'_'.$shipping_address['postal_code'];

        $prefetchShipping = $this->configHelper->getPrefetchShipping();

        if ($prefetchShipping && $serialized = $this->cache->load($cache_identifier)) {
            return unserialize($serialized);
        }
        ////////////////////////////////////////////////////////////////////////////////////////

        // Get region id
        $region = $this->regionModel->loadByName(@$shipping_address['region'], @$shipping_address['country_code']);

        $shipping_address = [
            'country_id' => @$shipping_address['country_code'],
            'postcode'   => @$shipping_address['postal_code'],
            'region'     => @$shipping_address['region'],
            'region_id'  => $region ? $region->getId() : null,
            'firstname'  => @$shipping_address['first_name'],
            'lastname'   => @$shipping_address['last_name'],
            'street'     => @$shipping_address['street_address1'],
            'city'       => @$shipping_address['locality'],
            'telephone'  => @$shipping_address['phone'],
        ];

        foreach ($shipping_address as $key => $value) {
            if (empty($value)) {
                unset($shipping_address[$key]);
            }
        }

        $shippingMethods = $this->getShippingOptions($quote, $shipping_address);

        $shippingOptionsModel = $this->shippingOptionsInterfaceFactory->create();
        $shippingOptionsModel->setShippingOptions($shippingMethods);

        $shippingTaxModel = $this->shippingTaxInterfaceFactory->create();
        $shippingTaxModel->setAmount(0);
        $shippingOptionsModel->setTaxResult($shippingTaxModel);

        // Cache the calculated result
        if ($prefetchShipping) {
            $this->cache->save(serialize($shippingOptionsModel), $cache_identifier, [], 3600);
        }

        return $shippingOptionsModel;
    }


    /**
     * Save shipping address in quote
     *
     * @param Quote $quote
     * @param array $shipping_address
     *
     * @return ShippingOptionInterface[]
     */
    private function getShippingOptions($quote, $shipping_address)
    {
        $output = [];

        $shippingAddress = $quote->getShippingAddress();

        $shippingAddress->addData($shipping_address);
        $shippingAddress->setCollectShippingRates(true);

        $shippingAddress->setShippingMethod(null);
        $this->totalsCollector->collectAddressTotals($quote, $shippingAddress);

        $shippingRates = $shippingAddress->getGroupedAllShippingRates();

        foreach ($shippingRates as $carrierRates) {
            foreach ($carrierRates as $rate) {
                $output[] = $this->converter->modelToDataObject($rate, $quote->getQuoteCurrencyCode());
            }
        }

        $shippingMethods = [];

        $errors = [];

        foreach ($output as $shippingMethod) {
            $service = $shippingMethod->getCarrierTitle() . ' - ' . $shippingMethod->getMethodTitle();
            $method  = $shippingMethod->getCarrierCode() . '_' . $shippingMethod->getMethodCode();

            $shippingAddress->setShippingMethod($method);
            $this->totalsCollector->collectAddressTotals($quote, $shippingAddress);

            $cost         = $shippingAddress->getShippingAmount();
            $rounded_cost = $this->cartHelper->getRoundAmount($cost);

            $diff = $cost * 100 - $rounded_cost;

            $tax_amount = $this->cartHelper->getRoundAmount($shippingAddress->getTaxAmount() + $diff / 100);

            if (abs($diff) >= $this->treshold) {
                $this->tax_adjusted = true;
                $this->bugsnag->registerCallback(function ($report) use ($method, $diff, $service, $rounded_cost, $tax_amount) {
                    $report->setMetaData([
                        'TOTALS_DIFF' => [
                            $method => [
                                'diff'       => $diff,
                                'service'    => $service,
                                'reference'  => $method,
                                'cost'       => $rounded_cost,
                                'tax_amount' => $tax_amount,
                            ]
                        ]
                    ]);
                });
            }

            $error = $shippingMethod->getErrorMessage();

            if ($error) {
                $errors[] = [
                    'service'    => $service,
                    'reference'  => $method,
                    'cost'       => $rounded_cost,
                    'tax_amount' => $tax_amount,
                    'error'      => $error,
                ];

                continue;
            }

            $shippingMethods[] = $this->shippingOptionInterfaceFactory
                ->create()
                ->setService($service)
                ->setCost($rounded_cost)
                ->setReference($method)
                ->setTaxAmount($tax_amount);
        }

        if ($errors) {
            $this->bugsnag->registerCallback(function ($report) use ($errors) {
                $report->setMetaData([
                    'SHIPPING METHOD' => $errors
                ]);
            });

            $this->bugsnag->notifyError('Shipping Method Error', $error);
        }

        return $shippingMethods;
    }
}
