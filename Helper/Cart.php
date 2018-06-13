<?php
/**
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bolt\Boltpay\Helper;

use Bolt\Boltpay\Model\Response;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Catalog\Model\ProductFactory;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Zend_Http_Client_Exception;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\BlockFactory;
use Magento\Store\Model\App\Emulation;
use Magento\Customer\Model\Address;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\Quote\TotalsCollector;

/**
 * Boltpay Cart helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Cart extends AbstractHelper
{
    /** @var CheckoutSession */
    private $checkoutSession;

    /** @var CustomerSession */
    private $customerSession;

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var BlockFactory
     */
    private  $blockFactory;

    /**
     * @var Emulation
     */
    private  $appEmulation;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var TotalsCollector
     */
    private $totalsCollector;

    // Billing / shipping address fields that are required when the address data is sent to Bolt.
    private $required_address_fields = [
        'first_name',
        'last_name',
        'street_address1',
        'locality',
        'region',
        'postal_code',
        'country_code',
    ];

    private $required_billing_address_fields  = [
        'email',
    ];

    ///////////////////////////////////////////////////////
    // Store discount types, internal and 3rd party.
    // Can appear as keys in Quote::getTotals result array.
    ///////////////////////////////////////////////////////
    private $discount_types = [
        'giftvoucheraftertax',
    ];
    ///////////////////////////////////////////////////////

    // Totals adjustment treshold
    private $treshold = 0.01;

    /**
     * @param Context           $context
     * @param CheckoutSession   $checkoutSession
     * @param ProductFactory    $productFactory
     * @param ApiHelper         $apiHelper
     * @param ConfigHelper      $configHelper
     * @param CustomerSession   $customerSession
     * @param LogHelper         $logHelper
     * @param Bugsnag           $bugsnag
     * @param DataObjectFactory $dataObjectFactory
     * @param BlockFactory      $blockFactory
     * @param Emulation         $appEmulation
     * @param QuoteFactory      $quoteFactory
     * @param TotalsCollector   $totalsCollector
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        ProductFactory $productFactory,
        ApiHelper $apiHelper,
        ConfigHelper $configHelper,
        CustomerSession $customerSession,
        LogHelper $logHelper,
        Bugsnag $bugsnag,
        DataObjectFactory $dataObjectFactory,
        BlockFactory $blockFactory,
        Emulation $appEmulation,
        QuoteFactory $quoteFactory,
        TotalsCollector $totalsCollector
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->productFactory = $productFactory;
        $this->apiHelper = $apiHelper;
        $this->configHelper = $configHelper;
        $this->customerSession = $customerSession;
        $this->logHelper = $logHelper;
        $this->bugsnag = $bugsnag;
        $this->blockFactory = $blockFactory;
        $this->appEmulation = $appEmulation;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->quoteFactory = $quoteFactory;
        $this->totalsCollector = $totalsCollector;
    }

    /**
     * Create order on bolt
     *
     * @param bool $payment_only               flag that represents the type of checkout
     * @param string $place_order_payload      additional data collected from the (one page checkout) page,
     *                                         i.e. billing address to be saved with the order
     *
     * @return Response|void
     * @throws \Exception
     * @throws LocalizedException
     * @throws Zend_Http_Client_Exception
     */
    public function getBoltpayOrder($payment_only, $place_order_payload)
    {
        //Get cart data
        $cart = $this->getCartData($payment_only, $place_order_payload);
        if (!$cart) {
            return;
        }

        $apiKey = $this->configHelper->getApiKey();

        //Request Data
        $requestData = $this->dataObjectFactory->create();
        $requestData->setApiData(['cart' => $cart]);
        $requestData->setDynamicApiUrl(ApiHelper::API_CREATE_ORDER);
        $requestData->setApiKey($apiKey);

        //Build Request
        $request = $this->apiHelper->buildRequest($requestData);
        $result  = $this->apiHelper->sendRequest($request);
        return $result;
    }

    /**
     * Sign a payload using the Bolt endpoint
     *
     * @param array $signRequest  payload to sign
     *
     * @return Response|int
     */
    private function getSignResponse($signRequest)
    {
        $apiKey = $this->configHelper->getApiKey();

        //Request Data
        $requestData = $this->dataObjectFactory->create();
        $requestData->setApiData($signRequest);
        $requestData->setDynamicApiUrl(ApiHelper::API_SIGN);
        $requestData->setApiKey($apiKey);

        $request = $this->apiHelper->buildRequest($requestData);
        try {
            $result = $this->apiHelper->sendRequest($request);
        } catch (\Exception $e) {
            return null;
        }
        return $result;
    }

    /**
     * Get the hints data for checkout
     *
     * @param string $place_order_payload      additional data collected from the (one page checkout) page,
     *                                         i.e. billing address to be saved with the order
     *
     * @return array
     */
    public function getHints($place_order_payload)
    {
        /** @var  Quote */
        $quote = $this->checkoutSession->getQuote();

        if ($place_order_payload) {
            $place_order_payload = @json_decode($place_order_payload);
            $email = @$place_order_payload->email;
        }

        $hints = ['prefill' => []];

        /**
         * Update hints from address data
         *
         * @param Address $shippingAddress
         */
        $prefillHints = function($shippingAddress) use (&$hints, $quote) {

            if (!$shippingAddress) return;

            $prefill = [
                'firstName'    => $shippingAddress->getFirstname(),
                'lastName'     => $shippingAddress->getLastname(),
                'email'        => @$email ?: $shippingAddress->getEmail() ?: $quote->getCustomerEmail(),
                'phone'        => $shippingAddress->getTelephone(),
                'addressLine1' => $shippingAddress->getStreetLine(1),
                'addressLine2' => $shippingAddress->getStreetLine(2),
                'city'         => $shippingAddress->getCity(),
                'state'        => $shippingAddress->getRegion(),
                'zip'          => $shippingAddress->getPostcode(),
                'country'      => $shippingAddress->getCountryId(),
            ];

            foreach ($prefill as $name => $value) {
                if (empty($value)) {
                    unset($prefill[$name]);
                }
            }

            $hints['prefill'] = array_merge($hints['prefill'], $prefill);
        };

        // Logged in customes.
        // Merchant scope and prefill.
        if ($this->customerSession->isLoggedIn()) {

            $customer = $this->customerSession->getCustomer();

            $signRequest = [
                'merchant_user_id' => $customer->getId(),
            ];
            $signResponse = $this->getSignResponse($signRequest)->getResponse();

            if ($signResponse) {
                $hints['signed_merchant_user_id'] = [
                    "merchant_user_id" => $signResponse->merchant_user_id,
                    "signature"        => $signResponse->signature,
                    "nonce"            => $signResponse->nonce,
                ];
            }

            $prefillHints($customer->getDefaultShippingAddress());

            $hints['prefill']['email'] = @$hints['prefill']['email'] ?: $customer->getEmail();
        }

        // Quote shipping address.
        // If assigned it takes precedence over logged in user default address.
        $prefillHints($quote->getShippingAddress());

        return $hints;
    }

    /**
     * Get cart data.
     * The reference of total methods: dev/tests/api-functional/testsuite/Magento/Quote/Api/CartTotalRepositoryTest.php
     *
     * @param bool $payment_only               flag that represents the type of checkout
     * @param string $place_order_payload      additional data collected from the (one page checkout) page,
     *                                         i.e. billing address to be saved with the order
     * @param Quote $quote
     *
     * @return array
     * @throws \Exception
     */
    public function getCartData($payment_only, $place_order_payload, $quote = null)
    {
        // If quote is not passed load it from the session.
        // The quote is passed from an API call (i.e. discount code validation)
        $quote = $quote ?: $this->checkoutSession->getQuote();

        $cart = [];

        if (null === $quote->getId()) {
            // The cart creation sometimes gets called from frontend event when no quote exists.
            // It is store specific, for example a minicart with 0 items.
            // Commenting this bugsnag notification out. It uselessly bloats the log.
            // $this->bugsnag->notifyError('Get Cart Data Error', 'Non existing session quote object');
            return $cart;
        }

        $quote->setBoltParentQuoteId($quote->getId());
        $quote->getReservedOrderId() ?: $this->setReservedOrderId($quote);

        ////////////////////////////////////////////////////////
        // CLONE THE QUOTE and quote billing / shipping  address
        ////////////////////////////////////////////////////////
        $clone = $this->quoteFactory->create();

        $clone->merge($quote);

        foreach ($quote->getData() as $key => $value) {
            $clone->setData($key, $value);
        }

        $clone->setId(null);
        $clone->setIsActive(false);
        $clone->save();

        foreach ($quote->getBillingAddress()->getData() as $key => $value) {
            if ($key != 'address_id') $clone->getBillingAddress()->setData($key, $value);
        }

        foreach ($quote->getShippingAddress()->getData() as $key => $value) {
            if ($key != 'address_id') $clone->getShippingAddress()->setData($key, $value);
        }

        $billingAddress  = $clone->getBillingAddress()->save();
        $shippingAddress = $clone->getShippingAddress()->save();
        ////////////////////////////////////////////////////////

        // Get array of all items what can be display directly
        $items = $clone->getAllVisibleItems();

        if (!$items) {
            // This is the case when customer empties the cart.
            // Not an error. Commenting out bugsnag report for now.
            // $this->bugsnag->notifyError('Get Cart Data Error', 'The cart is empty');
            return $cart;
        }

        $clone->collectTotals();
        $totals = $clone->getTotals();

        // Order reference id
        $cart['order_reference'] = $clone->getId();

        //Set display_id as reserve order id
        $cart['display_id'] = $clone->getReservedOrderId();

        //Currency
        $cart['currency'] = $clone->getQuoteCurrencyCode();

        $totalAmount = 0;
        $diff = 0;

        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        // The "appEmulation" and block creation code is necessary for geting correct image url from an API call.
        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        $this->appEmulation->startEnvironmentEmulation(
            $quote->getStoreId(),
            \Magento\Framework\App\Area::AREA_FRONTEND,
            true
        );
        /** @var  \Magento\Catalog\Block\Product\ListProduct $imageBlock */
        $imageBlock = $this->blockFactory->createBlock('Magento\Catalog\Block\Product\ListProduct');

        foreach ($items as $item) {

            $product = [];
            $productId = $item->getProductId();

            $unit_price   = $item->getCalculationPrice();
            $total_amount = $unit_price * $item->getQty();

            $rounded_total_amount = $this->getRoundAmount($total_amount);

            // Aggregate eventual total differences if prices are stored with more than 2 decimal places
            $diff += $total_amount * 100 -$rounded_total_amount;

            // Aggregate cart total
            $totalAmount += $rounded_total_amount;

            $product['reference']    = $productId;
            $product['name']         = $item->getName();
            $product['total_amount'] = $rounded_total_amount;
            $product['unit_price']   = $this->getRoundAmount($unit_price);
            $product['quantity']     = round($item->getQty());
            $product['sku']          = trim($item->getSku());

            ////////////////////////////////////
            // Get product description and image
            ////////////////////////////////////
            /**
             * @var \Magento\Catalog\Model\Product
             */
            $_product = $this->productFactory->create()->load($productId);
            $product['description'] = strip_tags($_product->getDescription());
            try {
                $productImage = $imageBlock->getImage($_product, 'product_small_image');
            } catch (\Exception $e) {
                try {
                    $productImage = $imageBlock->getImage($_product, 'product_image');
                } catch (\Exception $e) {
                    $this->bugsnag->registerCallback(function ($report) use ($product) {
                        $report->setMetaData([
                            'ITEM' => $product
                        ]);
                    });
                    $this->bugsnag->notifyError('Item image missing', "SKU: {$product['sku']}");
                }
            }
            if (@$productImage) $product['image_url'] = $productImage->getImageUrl();
            ////////////////////////////////////

            //Add product to items array
            $cart['items'][] = $product;
        }

        $this->appEmulation->stopEnvironmentEmulation();
        /////////////////////////////////////////////////////////////////////////////////////////////////////////

        // Billing address
        $cart['billing_address'] = [
            'first_name'      => $billingAddress->getFirstname(),
            'last_name'       => $billingAddress->getLastname(),
            'company'         => $billingAddress->getCompany(),
            'phone'           => $billingAddress->getTelephone(),
            'street_address1' => $billingAddress->getStreetLine(1),
            'street_address2' => $billingAddress->getStreetLine(2),
            'locality'        => $billingAddress->getCity(),
            'region'          => $billingAddress->getRegion(),
            'postal_code'     => $billingAddress->getPostcode(),
            'country_code'    => $billingAddress->getCountryId(),
        ];

        $email = $billingAddress->getEmail() ?: $shippingAddress->getEmail() ?: $this->customerSession->getCustomer()->getEmail();

        // additional data sent, i.e. billing address from checkout page
        if ($place_order_payload) {
            $place_order_payload = json_decode($place_order_payload);

            $email                = @$place_order_payload->email ?: $email;
            $billing_address      = @$place_order_payload->billingAddress;
            $billingStreetAddress = (array)@$billing_address->street;

            if ($billing_address) {
                $cart['billing_address'] = [
                    'first_name'      => @$billing_address->firstname,
                    'last_name'       => @$billing_address->lastname,
                    'company'         => @$billing_address->company,
                    'phone'           => @$billing_address->telephone,
                    'street_address1' => (string)@$billingStreetAddress[0],
                    'street_address2' => (string)@$billingStreetAddress[1],
                    'locality'        => @$billing_address->city,
                    'region'          => @$billing_address->region,
                    'postal_code'     => @$billing_address->postcode,
                    'country_code'    => @$billing_address->countryId,
                ];
            }
        }

        if ($email) {
            $cart['billing_address']['email'] = $email;
        }

        $address = $clone->isVirtual() ? $billingAddress : $shippingAddress;

        // payment only checkout, include shipments, tax and grand total
        if ($payment_only) {

            if (! $clone->isVirtual()) {

                // assign parent shipping method to clone
                $address->setCollectShippingRates(true);
                $address->setShippingMethod($quote->getShippingAddress()->getShippingMethod());

                $this->totalsCollector->collectAddressTotals($clone, $address);
                $address->save();

                // Shipping address
                $shipping_address = [
                    'first_name' => $address->getFirstname(),
                    'last_name' => $address->getLastname(),
                    'company' => $address->getCompany(),
                    'phone' => $address->getTelephone(),
                    'street_address1' => $address->getStreetLine(1),
                    'street_address2' => $address->getStreetLine(2),
                    'locality' => $address->getCity(),
                    'region' => $address->getRegion(),
                    'postal_code' => $address->getPostcode(),
                    'country_code' => $address->getCountryId(),
                ];

                $email = $address->getEmail() ?: $email;
                if ($email) {
                    $shipping_address['email'] = $email;
                }

                foreach ($this->required_address_fields as $field) {
                    if (empty($shipping_address[$field])) {
                        unset($shipping_address);
                        break;
                    }
                }

                if (@$shipping_address) {
                    $cost = $address->getShippingAmount();
                    $rounded_cost = $this->getRoundAmount($cost);

                    $diff += $cost * 100 - $rounded_cost;
                    $totalAmount += $rounded_cost;

                    $cart['shipments'] = [[
                        'cost' => $rounded_cost,
                        'tax_amount' => $this->getRoundAmount($address->getShippingTaxAmount()),
                        'shipping_address' => $shipping_address,
                        'service' => $shippingAddress->getShippingDescription(),
                        'reference' => $shippingAddress->getShippingMethod(),
                    ]];
                }
            } else {
                $this->totalsCollector->collectAddressTotals($clone, $address);
                $address->save();
            }

            $tax_amount         = $address->getTaxAmount();
            $rounded_tax_amount = $this->getRoundAmount($tax_amount);

            $diff += $tax_amount * 100 - $rounded_tax_amount;

            $taxAmount = $rounded_tax_amount;
            $totalAmount += $rounded_tax_amount;
        } else {
            // multi-step checkout, subtotal with discounts, no shipping, no tax
            $taxAmount = 0;
        }

        // include potential rounding difference and reset $diff accumulator
        $cart['items'][0]['total_amount'] += round($diff);
        $totalAmount += round($diff);
        $diff = 0;

        // unset billing if not all required fields are present
        $required_billing_fields = array_merge($this->required_address_fields, $this->required_billing_address_fields);
        foreach ($required_billing_fields as $field) {
            if (empty($cart['billing_address'][$field])) {
                unset($cart['billing_address']);
                break;
            }
        }

        // add discount data
        $cart['discounts'] = [];

        /////////////////////////////////////////////////////////////////////////////////
        // Process store integral discounts and coupons
        /////////////////////////////////////////////////////////////////////////////////
        if ($amount = @$address->getDiscountAmount()) {
            $amount         = abs($amount);
            $rounded_amount = $this->getRoundAmount($amount);

            $cart['discounts'][] = [
                'description' => trim(__('Discount ') . $address->getDiscountDescription()),
                'amount'      => $rounded_amount,
            ];

            $diff -= $amount * 100 - $rounded_amount;
            $totalAmount -= $rounded_amount;
        }
        /////////////////////////////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////////////
        // Process Store Credit
        /////////////////////////////////////////////////////////////////////////////////
        if ($clone->getUseCustomerBalance()) {

            if ($payment_only && $amount = abs($clone->getCustomerBalanceAmountUsed())) {

                $rounded_amount = $this->getRoundAmount($amount);

                $cart['discounts'][] = [
                    'description' => 'Store Credit',
                    'amount'      => $rounded_amount,
                ];

                $diff -= $amount * 100 - $rounded_amount;
                $totalAmount -= $rounded_amount;

            } else {

                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $balanceModel = $objectManager->create('Magento\CustomerBalance\Model\Balance');

                $balanceModel->setCustomer(
                    $this->customerSession->getCustomer()
                )->setWebsiteId(
                    $this->checkoutSession->getQuote()->getStore()->getWebsiteId()
                );
                $balanceModel->loadByCustomer();

                if ($amount = abs($balanceModel->getAmount())) {

                    $rounded_amount = $this->getRoundAmount($amount);

                    $cart['discounts'][] = [
                        'description' => 'Store Credit',
                        'amount'      => $rounded_amount,
                        'type'        => 'fixed_amount',
                    ];

                    $totalAmount -= $rounded_amount;
                }
            }
        }
        /////////////////////////////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////////////
        // Process Reward Points
        /////////////////////////////////////////////////////////////////////////////////
        if ($clone->getUseRewardPoints()) {

            if ($payment_only && $amount = abs($clone->getRewardCurrencyAmount())) {

                $rounded_amount = $this->getRoundAmount($amount);

                $cart['discounts'][] = [
                    'description' => 'Reward Points',
                    'amount'      => $rounded_amount,
                ];

                $diff -= $amount * 100 - $rounded_amount;
                $totalAmount -= $rounded_amount;

            } else {

                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $rewardModel = $objectManager->create('Magento\Reward\Model\Reward');

                $rewardModel->setCustomer(
                    $this->customerSession->getCustomer()
                )->setWebsiteId(
                    $this->checkoutSession->getQuote()->getStore()->getWebsiteId()
                );
                $rewardModel->loadByCustomer();

                if ($amount = abs($rewardModel->getCurrencyAmount())) {

                    $rounded_amount = $this->getRoundAmount($amount);

                    $cart['discounts'][] = [
                        'description' => 'Reward Points',
                        'amount'      => $rounded_amount,
                        'type'        => 'fixed_amount',
                    ];

                    $totalAmount -= $rounded_amount;
                }
            }
        }
        /////////////////////////////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////////////
        // Process other discounts, stored in totals array
        /////////////////////////////////////////////////////////////////////////////////
        foreach ($this->discount_types as $discount) {
            if (@$totals[$discount] && $amount = @$totals[$discount]->getValue()) {
                $amount = abs($amount);
                $rounded_amount = $this->getRoundAmount($amount);

                $cart['discounts'][] = [
                    'description' => @$totals[$discount]->getTitle(),
                    'amount'      => $rounded_amount,
                ];

                $diff -= $amount * 100 - $rounded_amount;
                $totalAmount -= $rounded_amount;
            }
        }
        /////////////////////////////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////////////
        // Add fixed amount type to all discounts if total amount is negative
        // and set total to 0. Otherwise add calculated diff to cart total.
        /////////////////////////////////////////////////////////////////////////////////
        if ($totalAmount < 0) {
            $totalAmount = 0;
            foreach ($cart['discounts'] as &$discount) {
                $discount['type'] = 'fixed_amount';
            }
        } else {
            // add the diff to first item total to pass bolt order create check
            $cart['items'][0]['total_amount'] += round($diff);
            $totalAmount += round($diff);
        }
        /////////////////////////////////////////////////////////////////////////////////

        $cart['total_amount'] = $totalAmount;
        $cart['tax_amount']   = $taxAmount;

        if (abs($diff) >= $this->treshold) {
            $this->bugsnag->registerCallback(function ($report) use ($diff, $cart) {
                $report->setMetaData([
                    'TOTALS_DIFF' => [
                        'diff' => $diff,
                        'cart' => $cart,
                    ]
                ]);
            });
            $this->bugsnag->notifyError('Cart Totals Mismatch', "Totals adjusted by $diff.");
        }

        $this->logHelper->addInfoLog(json_encode($cart, JSON_PRETTY_PRINT));

        return $cart;
    }

    /**
     * Reserve order id for the quote
     * @param Quote $quote
     *
     * @return  string
     * @throws \Exception
     */
    public function setReservedOrderId($quote)
    {
        $quote->reserveOrderId()->save();
        $reserveOrderId = $quote->getReservedOrderId();
        return $reserveOrderId;
    }

    /**
     * Round amount helper
     *
     * @return  int
     */
    public function getRoundAmount($amount)
    {
        return round($amount * 100);
    }

    /**
     * Email validator
     *
     * @param string $email
     * @return bool
     * @throws \Zend_Validate_Exception
     */
    public function validateEmail($email) {

        $emailClass = version_compare(
            $this->configHelper->getStoreVersion(),
            '2.2.0',
            '<'
        ) ? 'EmailAddress' : \Magento\Framework\Validator\EmailAddress::class;

        return \Zend_Validate::is($email, $emailClass);
    }
}
