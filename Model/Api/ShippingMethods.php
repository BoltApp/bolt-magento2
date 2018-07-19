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

use Bolt\Boltpay\Api\Data\ShippingOptionsInterface;
use Bolt\Boltpay\Api\ShippingMethodsInterface;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Quote\Model\Quote\TotalsCollector;
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
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;

/**
 * Class ShippingMethods
 * Shipping and Tax hook endpoint. Get shipping methods using shipping address and cart details
 *
 * @package Bolt\Boltpay\Model\Api
 */
class ShippingMethods implements ShippingMethodsInterface
{
    const NO_SHIPPING_SERVICE = 'No Shipping Required';
    const NO_SHIPPING_REFERENCE = 'noshipping';

    /**
     * @var HookHelper
     */
    private $hookHelper;

    /**
     * @var CartHelper
     */
    private $cartHelper;

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

    /**
     * @var PriceHelper
     */
    private $priceHelper;

    // Totals adjustment threshold
    private $threshold = 1;

    private $taxAdjusted = false;

    /**
     * Assigns local references to global resources
     *
     * @param HookHelper $hookHelper
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
     * @param PriceHelper $priceHelper
     */
    public function __construct(
        HookHelper $hookHelper,
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
        CustomerFactory $customerFactory,
        PriceHelper $priceHelper
    ) {
        $this->hookHelper = $hookHelper;
        $this->cartHelper = $cartHelper;
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
        $this->priceHelper = $priceHelper;
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

                $quoteItems = array_map(function($item){
                    $product = [];
                    $productId = $item->getProductId();
                    $unitPrice   = $item->getCalculationPrice();
                    $totalAmount = $unitPrice * $item->getQty();
                    $roundedTotalAmount = $this->cartHelper->getRoundAmount($totalAmount);
                    $product['reference']    = $productId;
                    $product['name']         = $item->getName();
                    $product['description']  = $item->getDescription();
                    $product['total_amount'] = $roundedTotalAmount;
                    $product['unit_price']   = $this->cartHelper->getRoundAmount($unitPrice);
                    $product['quantity']     = round($item->getQty());
                    $product['sku']          = trim($item->getSku());
                    return $product;
                }, $quote->getAllVisibleItems());

                $report->setMetaData([
                    'CART_MISMATCH' => [
                        'cart_items' => $cart['items'],
                        'quote_items' => $quoteItems,
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

            // kepping variable names camelCased.
            // shipping_address is expected REST parameter name, must stay in snake_case.
            $addressData = $shipping_address;

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

            // get immutable quote id stored with transaction
            list(, $quoteId) = explode(' / ', $cart['display_id']);

            // Load quote from entity id
            $quote = $this->cartHelper->getQuoteById($quoteId);

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

            $shippingOptionsModel = $this->shippingEstimation($quote, $addressData);

            if ($this->taxAdjusted) {
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
     * @param array $addressData
     *
     * @return ShippingOptionsInterface
     * @throws LocalizedException
     */
    public function shippingEstimation($quote, $addressData)
    {
        ////////////////////////////////////////////////////////////////////////////////////////
        // Check cache storage for estimate. If the quote_id, total_amount, items, country_code,
        // applied rules (discounts), region and postal_code match then use the cached version.
        ////////////////////////////////////////////////////////////////////////////////////////
        if ($prefetchShipping = $this->configHelper->getPrefetchShipping()) {

            // use parent quote id for caching.
            // if everything else matches the cache is used more efficiently this way
            $parentQuoteId =$quote->getBoltParentQuoteId();

            $cacheIdentifier = $parentQuoteId.'_'.round($quote->getSubtotal()*100).'_'.
                $addressData['country_code']. '_'.$addressData['region'].'_'.$addressData['postal_code'];

            // include products in cache key
            foreach($quote->getAllVisibleItems() as $item) {
                $cacheIdentifier .= '_'.trim($item->getSku()).'_'.$item->getQty();
            }

            // include applied rule ids (discounts) in cache key
            $ruleIds = str_replace(',', '_', $quote->getAppliedRuleIds());
            if ($ruleIds) $cacheIdentifier .= '_'.$ruleIds;

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

            $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();

            // get the value of each valid field and include it in the cache identifier
            foreach ($prefetchAddressFields as $key) {
                $getter = 'get'.$key;
                $value = $address->$getter();
                if ($value) $cacheIdentifier .= '_'.$value;
            }

            $cacheIdentifier = md5($cacheIdentifier);

            if ($serialized = $this->cache->load($cacheIdentifier)) {
                $address->setShippingMethod(null)->save();
                return unserialize($serialized);
            }
        }
        ////////////////////////////////////////////////////////////////////////////////////////

        // Get region id
        $region = $this->regionModel->loadByName(@$addressData['region'], @$addressData['country_code']);

        // Check the email address
        $email = $this->cartHelper->validateEmail(@$addressData['email']) ? $addressData['email'] : null;

        // Reformat address data
        $addressData = [
            'country_id' => @$addressData['country_code'],
            'postcode'   => @$addressData['postal_code'],
            'region'     => @$addressData['region'],
            'region_id'  => $region ? $region->getId() : null,
            'city'       => @$addressData['locality'],
            'street'     => trim(@$addressData['street_address1'] . "\n" . @$addressData['street_address2']),
            'email'      => $email,
            'company'    => @$addressData['company'],
        ];

        foreach ($addressData as $key => $value) {
            if (empty($value)) {
                unset($addressData[$key]);
            }
        }

        $shippingMethods = $this->getShippingOptions($quote, $addressData);

        $shippingOptionsModel = $this->shippingOptionsInterfaceFactory->create();
        $shippingOptionsModel->setShippingOptions($shippingMethods);

        $shippingTaxModel = $this->shippingTaxInterfaceFactory->create();
        $shippingTaxModel->setAmount(0);
        $shippingOptionsModel->setTaxResult($shippingTaxModel);

        // Cache the calculated result
        if ($prefetchShipping) {
            $this->cache->save(serialize($shippingOptionsModel), $cacheIdentifier, [], 3600);
        }

        return $shippingOptionsModel;
    }

    /**
     * Reset shipping calculation
     *
     * On some store setups shipping prices are conditionally changed
     * depending on some custom logic. If it is done as a plugin for
     * some method in the Magento shipping flow, then that method
     * may be (indirectly) called from our Shipping And Tax flow more
     * than once, resulting in wrong prices. This function resets
     * address shipping calculation but can seriously slow down the
     * process (on a system with many shipping options available).
     * Use it carefully only when necesarry.
     *
     * @param \Magento\Quote\Model\Quote\Address $shippingAddress
     */
    private function resetShippingCalculationIfNeeded ($shippingAddress) {
        if ($this->configHelper->getResetShippingCalculation()) {
            $shippingAddress->removeAllShippingRates();
            $shippingAddress->setCollectShippingRates(true);
        }
    }

    /**
     * Collects shipping options for the quote and received address data
     *
     * @param Quote $quote
     * @param array $addressData
     *
     * @return ShippingOptionInterface[]
     */
    private function getShippingOptions($quote, $addressData)
    {
        if ($quote->isVirtual()) {

            $billingAddress = $quote->getBillingAddress();
            $billingAddress->addData($addressData);

            $quote->collectTotals();

            $this->totalsCollector->collectAddressTotals($quote, $billingAddress);
            $taxAmount = $this->cartHelper->getRoundAmount($billingAddress->getTaxAmount());

            return [
                $this->shippingOptionInterfaceFactory
                    ->create()
                    ->setService(self::NO_SHIPPING_SERVICE)
                    ->setCost(0)
                    ->setReference(self::NO_SHIPPING_REFERENCE)
                    ->setTaxAmount($taxAmount)
            ];
        }

        $output = [];

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->addData($addressData);

        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->setShippingMethod(null);

        $quote->collectTotals();

        $this->totalsCollector->collectAddressTotals($quote, $shippingAddress);
        $shippingRates = $shippingAddress->getGroupedAllShippingRates();

        $this->resetShippingCalculationIfNeeded($shippingAddress);

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

            $this->resetShippingCalculationIfNeeded($shippingAddress);

            $shippingAddress->setShippingMethod($method);
            // In order to get correct shipping discounts the following method must be called twice.
            // Being a bug in Magento, or a bug in the tested store version, shipping discounts
            // are not collected the first time the method is called.
            // There was one loop step delay in applying discount to shipping options when method was called once.
            $this->totalsCollector->collectAddressTotals($quote, $shippingAddress);
            $this->totalsCollector->collectAddressTotals($quote, $shippingAddress);

            $discountAmount = $shippingAddress->getShippingDiscountAmount();

            $cost        = $shippingAddress->getShippingAmount() - $discountAmount;
            $roundedCost = $this->cartHelper->getRoundAmount($cost);

            $diff = $cost * 100 - $roundedCost;

            $taxAmount = $this->cartHelper->getRoundAmount($shippingAddress->getTaxAmount() + $diff / 100);

            if ($discountAmount) {
                if ($cost == 0) {
                    $service .= ' [free&nbsp;shipping&nbsp;discount]';
                } else {
                    $discount = $this->priceHelper->currency($discountAmount, true, false);
                    $service .= " [$discount" . "&nbsp;discount]";
                }
                $service = html_entity_decode($service);
            }

            if (abs($diff) >= $this->threshold) {
                $this->taxAdjusted = true;
                $this->bugsnag->registerCallback(function ($report) use (
                    $method,
                    $diff,
                    $service,
                    $roundedCost,
                    $taxAmount
                ) {
                    $report->setMetaData([
                        'TOTALS_DIFF' => [
                            $method => [
                                'diff'       => $diff,
                                'service'    => $service,
                                'reference'  => $method,
                                'cost'       => $roundedCost,
                                'tax_amount' => $taxAmount,
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
                    'cost'       => $roundedCost,
                    'tax_amount' => $taxAmount,
                    'error'      => $error,
                ];

                continue;
            }

            $shippingMethods[] = $this->shippingOptionInterfaceFactory
                ->create()
                ->setService($service)
                ->setCost($roundedCost)
                ->setReference($method)
                ->setTaxAmount($taxAmount);
        }

        $shippingAddress->setShippingMethod(null)->save();

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

        return $shippingMethods;
    }
}
