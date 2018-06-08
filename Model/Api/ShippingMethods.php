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
use \Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\CustomerFactory;

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
    private $hookHelper;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var RegionModel
     */
    private $regionModel;

    /**
     * @var ShippingOptionsInterfaceFactory
     */
    private $shippingOptionsInterfaceFactory;

    /**
     * @var ShippingTaxInterfaceFactory
     */
    private $shippingTaxInterfaceFactory;

    /**
     * @var TotalsCollector
     */
    private $totalsCollector;

    /**
     * Shipping method converter
     *
     * @var ShippingMethodConverter
     */
    private $converter;

    /**
     * @var ShippingOptionInterfaceFactory
     */
    private $shippingOptionInterfaceFactory;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CacheInterface
     */
    private $cache;

    /** @var CustomerSession */
    private $customerSession;

    /**
     * @var CustomerFactory
     */
    private $customerFactory;


    // Totals adjustment threshold
    private $threshold = 0.01;

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
     * @param CustomerSession $customerSession
     * @param CustomerFactory $customerFactory
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
        CacheInterface $cache,
        CustomerSession $customerSession,
        CustomerFactory $customerFactory
    ) {
        $this->hookHelper = $hookHelper;
        $this->cartHelper = $cartHelper;
        $this->quoteFactory = $quoteFactory;
        $this->regionModel = $regionModel;
        $this->shippingOptionsInterfaceFactory = $shippingOptionsInterfaceFactory;
        $this->shippingTaxInterfaceFactory = $shippingTaxInterfaceFactory;
        $this->totalsCollector = $totalsCollector;
        $this->converter = $converter;
        $this->shippingOptionInterfaceFactory = $shippingOptionInterfaceFactory;
        $this->bugsnag = $bugsnag;
        $this->logHelper = $logHelper;
        $this->response = $response;
        $this->configHelper = $configHelper;
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
        $this->cache = $cache;
        $this->customerSession = $customerSession;
        $this->customerFactory = $customerFactory;
    }

    /**
     * Check if cart items data has changed by comparing
     * product IDs and quantities in quote and received cart data.
     * Also checks an empty cart case.
     *
     * @param array $cart cart details
     * @param Quote $quote
     * @throws LocalizedException
     */
    private function checkCartItems($cart, $quote) {

        $cartItems = [];
        foreach ($cart['items'] as $item) {
            $cartItems[$item['sku']] = $item['quantity'];
        }

        $quoteItems = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $quoteItems[trim($item->getSku())] = round($item->getQty());
        }

        if (!$quoteItems || $cartItems != $quoteItems) {
            $this->bugsnag->registerCallback(function ($report) use ($cart, $quote) {

                $quote_items = array_map(function($item){
                    $product = [];
                    $productId = $item->getProductId();
                    $unit_price   = $item->getCalculationPrice();
                    $total_amount = $unit_price * $item->getQty();
                    $rounded_total_amount = $this->cartHelper->getRoundAmount($total_amount);
                    $product['reference']    = $productId;
                    $product['name']         = $item->getName();
                    $product['description']  = $item->getDescription();
                    $product['total_amount'] = $rounded_total_amount;
                    $product['unit_price']   = $this->cartHelper->getRoundAmount($unit_price);
                    $product['quantity']     = round($item->getQty());
                    $product['sku']          = trim($item->getSku());
                    return $product;
                }, $quote->getAllVisibleItems());

                $report->setMetaData([
                    'CART_MISMATCH' => [
                        'cart_items' => $cart['items'],
                        'quote_items' => $quote_items,
                    ]
                ]);
            });
            throw new LocalizedException(
                __('Cart Items data data has changed.')
            );
        }
    }

    /**
     * Get all available shipping methods and tax data.
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

            //$this->logHelper->addInfoLog($this->request->getContent());

            if ($bolt_trace_id = $this->request->getHeader(ConfigHelper::BOLT_TRACE_ID_HEADER)) {
                $this->bugsnag->registerCallback(function ($report) use ($bolt_trace_id) {
                    $report->setMetaData([
                        'BREADCRUMBS_' => [
                            'bolt_trace_id' => $bolt_trace_id,
                        ]
                    ]);
                });
            }

            $this->response->getHeaders()->addHeaders([
                'User-Agent' => 'BoltPay/Magento-'.$this->configHelper->getStoreVersion(),
                'X-Bolt-Plugin-Version' => $this->configHelper->getModuleVersion(),
            ]);

            $this->hookHelper->verifyWebhook();

            // Load quote from entity id
            $quoteId = $cart['order_reference'];
            $quote = $this->quoteFactory->create()->load($quoteId);

            if (!$quote || !$quote->getId()) {
                throw new LocalizedException(
                    __('Unknown quote id: %1.', $quoteId)
                );
            }

            $this->checkCartItems($cart, $quote);

            if ($customerId = $quote->getCustomerId()) {
                $this->customerSession->setCustomer(
                    $this->customerFactory->create()->load($customerId)
                );
            }

            $this->checkoutSession->replaceQuote($quote);

            $shippingOptionsModel = $this->shippingEstimation($quote, $shipping_address);

            if ($this->tax_adjusted) {
                $this->bugsnag->registerCallback(function ($report) use ($shippingOptionsModel) {
                    $report->setMetaData([
                        'SHIPPING OPTIONS' => [print_r($shippingOptionsModel, 1)]
                    ]);
                });
                $this->bugsnag->notifyError('Cart Totals Mismatch', "Totals adjusted.");
            }

            return $shippingOptionsModel;

        } catch (\Magento\Framework\Webapi\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->response->setHttpResponseCode($e->getHttpCode());
            $this->response->setBody(json_encode([
                'status' => 'error',
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ]));
            $this->response->sendResponse();
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->response->setHttpResponseCode(422);
            $this->response->setBody(json_encode([
                'status' => 'error',
                'code' => '6009',
                'message' => 'Unprocessable Entity: ' . $e->getMessage()
            ]));
            $this->response->sendResponse();
        }
    }

    /**
     * Get Shipping and Tax from cache or run the Shipping options collection routine, store it in cache and return.
     *
     * @param Quote $quote
     * @param array $shipping_address
     *
     * @return ShippingOptionsInterface
     * @throws LocalizedException
     */
    public function shippingEstimation($quote, $shipping_address)
    {
        ////////////////////////////////////////////////////////////////////////////////////////
        // Check cache storage for estimate. If the quote_id, total_amount, items, country_code,
        // applied rules (discounts), region and postal_code match then use the cached version.
        ////////////////////////////////////////////////////////////////////////////////////////
        if ($prefetchShipping = $this->configHelper->getPrefetchShipping()) {

            $cache_identifier = $quote->getId().'_'.round($quote->getSubtotal()*100).'_'.
                $shipping_address['country_code']. '_'.$shipping_address['region'].'_'.$shipping_address['postal_code'];

            // include products in cache key
            foreach($quote->getAllVisibleItems() as $item) {
                $cache_identifier .= '_'.trim($item->getSku()).'_'.$item->getQty();
            }

            // include applied rule ids (discounts) in cache key
            $rule_ids = str_replace(',', '_', $quote->getAppliedRuleIds());
            if ($rule_ids) $cache_identifier .= '_'.$rule_ids;

            // get custom address fields to be included in cache key
            $prefetchAddressFields = explode(',', $this->configHelper->getPrefetchAddressFields());
            // trim values and filter out empty strings
            $prefetchAddressFields = array_filter(array_map('trim', $prefetchAddressFields));
            // convert to PascalCase
            $prefetchAddressFields = array_map(
                function ($el) {
                    return str_replace('_', '', ucwords($el, '_'));
                },
                $prefetchAddressFields
            );

            $shippingAddress = $quote->getShippingAddress();
            // get the value of each valid field and include it in the cache identifier
            foreach ($prefetchAddressFields as $key) {
                $getter = 'get'.$key;
                $value = $shippingAddress->$getter();
                if ($value) $cache_identifier .= '_'.$value;
            }

            $cache_identifier = md5($cache_identifier);

            if ($serialized = $this->cache->load($cache_identifier)) {
                $shippingAddress->setShippingMethod(null)->save();
                return unserialize($serialized);
            }
        }
        ////////////////////////////////////////////////////////////////////////////////////////

        // Get region id
        $region = $this->regionModel->loadByName(@$shipping_address['region'], @$shipping_address['country_code']);

        // Check the email address
        $email = $this->cartHelper->validateEmail(@$shipping_address['email']) ? $shipping_address['email'] : null;

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
            'email'      => $email,
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
     * Collects shipping options for the quote and received address data
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

        $shippingAddress->addData($shipping_address)->save();
        $shippingAddress->setCollectShippingRates(true);

        $shippingAddress->setShippingMethod(null);
        $this->totalsCollector->collectAddressTotals($quote, $shippingAddress);

        $shippingRates = $shippingAddress->getGroupedAllShippingRates();

        ////////////////////////////////////////////////////////////////
        /// On some store setups shipping prices are conditionally changed
        /// depending on some custom logic. If it is done as a plugin for
        /// some method in the Magento shipping flow, then that method
        /// may be (indirectly) called from our Shipping And Tax flow more
        /// than once, resulting in wrong prices. This function resets
        /// address shipping calculation but can drasticaly slow down the
        /// process. Use it carefully only when necesarry.
        ////////////////////////////////////////////////////////////////
        $resetShippingCalculation = function () use ($shippingAddress) {
            $shippingAddress->removeAllShippingRates();
            $shippingAddress->setCollectShippingRates(true);
        };
        //$resetShippingCalculation();
        ////////////////////////////////////////////////////////////////

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

            ////////////////////////////////////////////////////////////////
            /// Use carefully only when necesarry.
            ////////////////////////////////////////////////////////////////
            // $resetShippingCalculation();
            ////////////////////////////////////////////////////////////////

            $shippingAddress->setShippingMethod($method);
            $this->totalsCollector->collectAddressTotals($quote, $shippingAddress);

            $cost         = $shippingAddress->getShippingAmount();
            $rounded_cost = $this->cartHelper->getRoundAmount($cost);

            $diff = $cost * 100 - $rounded_cost;

            $tax_amount = $this->cartHelper->getRoundAmount($shippingAddress->getTaxAmount() + $diff / 100);

            if (abs($diff) >= $this->threshold) {
                $this->tax_adjusted = true;
                $this->bugsnag->registerCallback(function ($report) use (
                    $method,
                    $diff,
                    $service,
                    $rounded_cost,
                    $tax_amount
                ) {
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

        $shippingAddress->setShippingMethod(null)->save();

        if ($errors) {
            $this->bugsnag->registerCallback(function ($report) use ($errors, $shipping_address) {
                $report->setMetaData([
                    'SHIPPING METHOD' => [
                      'address' => $shipping_address,
                      'errors'  => $errors
                    ]
                ]);
            });

            $this->bugsnag->notifyError('Shipping Method Error', $error);
        }

        return $shippingMethods;
    }
}
