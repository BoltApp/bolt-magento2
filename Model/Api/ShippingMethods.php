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
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Exception\BoltException;

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
     * @var BoltErrorResponse
     */
    private $errorResponse;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var PriceHelper
     */
    private $priceHelper;

    /** @var SessionHelper */
    private $sessionHelper;

    // Totals adjustment threshold
    private $threshold = 1;

    private $taxAdjusted = false;

    /**
     * Assigns local references to global resources
     *
     * @param HookHelper                      $hookHelper
     * @param RegionModel                     $regionModel
     * @param ShippingOptionsInterfaceFactory $shippingOptionsInterfaceFactory
     * @param ShippingTaxInterfaceFactory     $shippingTaxInterfaceFactory
     * @param CartHelper                      $cartHelper
     * @param TotalsCollector                 $totalsCollector
     * @param ShippingMethodConverter         $converter
     * @param ShippingOptionInterfaceFactory  $shippingOptionInterfaceFactory
     * @param Bugsnag                         $bugsnag
     * @param LogHelper                       $logHelper
     * @param BoltErrorResponse               $errorResponse
     * @param Response                        $response
     * @param ConfigHelper                    $configHelper
     * @param Request                         $request
     * @param CacheInterface                  $cache
     * @param PriceHelper                     $priceHelper
     * @param SessionHelper                   $sessionHelper
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
        BoltErrorResponse $errorResponse,
        Response $response,
        ConfigHelper $configHelper,
        Request $request,
        CacheInterface $cache,
        PriceHelper $priceHelper,
        SessionHelper $sessionHelper
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
        $this->errorResponse = $errorResponse;
        $this->response = $response;
        $this->configHelper = $configHelper;
        $this->request = $request;
        $this->cache = $cache;
        $this->priceHelper = $priceHelper;
        $this->sessionHelper = $sessionHelper;
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
    protected function checkCartItems($cart, $quote)
    {
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

                $quoteItems = array_map(function ($item) {
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
     * Validate request address
     *
     * @param $addressData
     * @throws BoltException
     * @throws \Zend_Validate_Exception
     */
    private function validateAddressData($addressData)
    {
        $this->validateEmail(@$addressData['email']);
    }

    /**
     * Validate request email
     *
     * @param $email
     * @throws BoltException
     * @throws \Zend_Validate_Exception
     */
    private function validateEmail($email)
    {
        if (!$this->cartHelper->validateEmail($email)) {
            throw new BoltException(
                __('Invalid email: %1', $email),
                null,
                BoltErrorResponse::ERR_UNIQUE_EMAIL_REQUIRED
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
//            $this->logHelper->addInfoLog($this->request->getContent());

            $this->preprocessHook();

            // get immutable quote id stored with transaction
            list(, $quoteId) = explode(' / ', $cart['display_id']);

            // Load quote from entity id
            $quote = $this->cartHelper->getQuoteById($quoteId);

            if (!$quote || !$quote->getId()) {
                $this->throwUnknownQuoteIdException($quoteId);
            }

            $this->checkCartItems($cart, $quote);

            // Load logged in customer checkout and customer sessions from cached session id.
            // Replace parent quote with immutable quote in checkout session.
            $this->sessionHelper->loadSession($quote);

            $addressData = $this->cartHelper->handleSpecialAddressCases($shipping_address);

            if (isset($addressData['email']) && $addressData['email'] !== null) {
                $this->validateAddressData($addressData);
            }

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
            $this->catchExceptionAndSendError($e, $e->getMessage(), $e->getCode(), $e->getHttpCode());
        } catch (BoltException $e) {
            $this->catchExceptionAndSendError($e, $e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            $msg = __('Unprocessable Entity') . ': ' . $e->getMessage();
            $this->catchExceptionAndSendError($e, $msg, 6009, 422);
        }
    }

    /**
     * @param        $exception
     * @param string $msg
     * @param int    $code
     * @param int    $httpStatusCode
     */
    protected function catchExceptionAndSendError($exception, $msg = '', $code = 6009, $httpStatusCode = 422)
    {
        $this->bugsnag->notifyException($exception);

        $this->sendErrorResponse($code, $msg, $httpStatusCode);
    }

    /**
     * @param $quoteId
     * @throws LocalizedException
     */
    protected function throwUnknownQuoteIdException($quoteId)
    {
        throw new LocalizedException(
            __('Unknown quote id: %1.', $quoteId)
        );
    }

    /**
     * @param $quote
     * @throws \Magento\Framework\Exception\SessionException
     */
    protected function loadSessionByQuote($quote)
    {
        return $this->sessionHelper->loadSession($quote);
    }

    /**
     * @param $quoteId
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getQuoteById($quoteId)
    {
        return $this->cartHelper->getQuoteById($quoteId);
    }

    /**
     * @throws LocalizedException
     * @throws \Magento\Framework\Webapi\Exception
     */
    protected function preprocessHook()
    {
        $this->hookHelper->setCommonMetaData();
        $this->hookHelper->setHeaders();

        $this->hookHelper->verifyWebhook();
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
            $parentQuoteId = $quote->getBoltParentQuoteId();

            $cacheIdentifier = $parentQuoteId.'_'.round($quote->getSubtotal()*100).'_'.
                $addressData['country_code']. '_'.$addressData['region'].'_'.$addressData['postal_code'];

            // include products in cache key
            foreach ($quote->getAllVisibleItems() as $item) {
                $cacheIdentifier .= '_'.trim($item->getSku()).'_'.$item->getQty();
            }

            // include applied rule ids (discounts) in cache key
            $ruleIds = str_replace(',', '_', $quote->getAppliedRuleIds());
            if ($ruleIds) {
                $cacheIdentifier .= '_'.$ruleIds;
            }

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
                if ($value) {
                    $cacheIdentifier .= '_'.$value;
                }
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

        // Accept valid email or an empty variable (when run from prefetch controller)
        if ($email = @$addressData['email']) {
            $this->validateEmail($email);
        }

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

        $shippingOptionsModel = $this->getShippingOptionsData($shippingMethods);

        // Cache the calculated result
        if ($prefetchShipping) {
            $this->cache->save(serialize($shippingOptionsModel), $cacheIdentifier, [], 3600);
        }

        return $shippingOptionsModel;
    }

    /**
     * Set shipping methods to the ShippingOptions object
     *
     * @param $shippingMethods
     */
    protected function getShippingOptionsData($shippingMethods)
    {
        $shippingOptionsModel = $this->shippingOptionsInterfaceFactory->create();

        $shippingTaxModel = $this->shippingTaxInterfaceFactory->create();
        $shippingTaxModel->setAmount(0);

        $shippingOptionsModel->setShippingOptions($shippingMethods);
        $shippingOptionsModel->setTaxResult($shippingTaxModel);

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
    private function resetShippingCalculationIfNeeded($shippingAddress)
    {
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
    public function getShippingOptions($quote, $addressData)
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

            // Checked if Unirgy_Giftcert code present in the quote and added discount amount.
            if (!$discountAmount
                && $shippingAddress->getGiftcertCode()
                && (float)$shippingAddress->getGiftcertAmount() > 0
            ) {
                if ($shippingAddress->getGrandTotal() == 0) {
                    $discountAmount = $shippingAddress->getShippingAmount();
                } else {
                    $discountAmount = $shippingAddress->getShippingAmount() - $shippingAddress->getGrandTotal();
                }
            }

            $cost        = $shippingAddress->getShippingAmount() - $discountAmount;
            $roundedCost = $this->cartHelper->getRoundAmount($cost);

            $diff = $cost * 100 - $roundedCost;

            $taxAmount = $this->cartHelper->getRoundAmount($shippingAddress->getTaxAmount() + $diff / 100);

            if ($discountAmount) {
                if ($cost == 0) {
                    // Added Unirgy_Giftcert amount to the message.
                    if ((float)$shippingAddress->getGiftcertAmount() > 0 && $shippingAddress->getGrandTotal() == 0) {
                        $service .= " [$discountAmount$" . "&nbsp;giftcert]";
                    } else {
                        $service .= ' [free&nbsp;shipping&nbsp;discount]';
                    }
                } else {
                    $discount = $this->priceHelper->currency($discountAmount, true, false);

                    // Added Unirgy_Giftcert amount to the message.
                    if ($shippingAddress->getGiftcertCode()
                        && (float)$shippingAddress->getGiftcertAmount() > 0
                    ) {
                        $service .= " [$discount$" . "&nbsp;giftcert]";
                    } else {
                        $service .= " [$discount" . "&nbsp;discount]";
                    }
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

        if (!$shippingMethods) {
            $this->bugsnag->registerCallback(function ($report) use ($quote, $addressData) {
                $report->setMetaData([
                    'SHIPPING AND_TAX' => [
                        'address' => $addressData,
                        'immutable quote ID' => $quote->getId(),
                        'parent quote ID' => $quote->getBoltParentQuoteId(),
                        'order increment ID' => $quote->getReservedOrderId()
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

    /**
     * @param      $errCode
     * @param      $message
     * @param      $httpStatusCode
     */
    protected function sendErrorResponse($errCode, $message, $httpStatusCode)
    {
        $encodeErrorResult = $this->errorResponse->prepareErrorMessage($errCode, $message);

        $this->response->setHttpResponseCode($httpStatusCode);
        $this->response->setBody($encodeErrorResult);
        $this->response->sendResponse();
    }
}
