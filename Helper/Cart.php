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

namespace Bolt\Boltpay\Helper;

use Bolt\Boltpay\Model\Response;
use Magento\Framework\Session\SessionManagerInterface as CheckoutSession;
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
use Magento\Quote\Api\CartRepositoryInterface as QuoteRepository;
use Magento\Sales\Api\OrderRepositoryInterface as OrderRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Boltpay Cart helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Cart extends AbstractHelper
{
    const ITEM_TYPE_PHYSICAL = 'physical';
    const ITEM_TYPE_DIGITAL  = 'digital';

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

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var QuoteResource
     */
    private $quoteResource;

    // Billing / shipping address fields that are required when the address data is sent to Bolt.
    private $requiredAddressFields = [
        'first_name',
        'last_name',
        'street_address1',
        'locality',
        'region',
        'postal_code',
        'country_code',
    ];

    private $requiredBillingAddressFields  = [
        'email',
    ];

    ///////////////////////////////////////////////////////
    // Store discount types, internal and 3rd party.
    // Can appear as keys in Quote::getTotals result array.
    ///////////////////////////////////////////////////////
    private $discountTypes = [
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
     * @param QuoteRepository   $quoteRepository
     * @param OrderRepository   $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param QuoteResource     $quoteResource
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
        TotalsCollector $totalsCollector,
        QuoteRepository $quoteRepository,
        OrderRepository $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        QuoteResource $quoteResource
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
        $this->quoteRepository = $quoteRepository;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->quoteResource = $quoteResource;
    }

    /**
     * Load Quote by id
     * @param $quoteId
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws NoSuchEntityException
     */
    public function getQuoteById($quoteId) {
        return $this->quoteRepository->get($quoteId);
    }

    /**
     * Load Quote by id if active
     * @param $quoteId
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws NoSuchEntityException
     */
    public function getActiveQuoteById($quoteId) {
        return $this->quoteRepository->getActive($quoteId);
    }

    /**
     * Load Order by increment id
     * @param $incrementId
     * @return \Magento\Sales\Api\Data\OrderInterface|mixed
     */
    public function getOrderByIncrementId($incrementId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId, 'eq')->create();
        $collection = $this->orderRepository->getList($searchCriteria)->getItems();
        return reset($collection);
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     */
    public function saveQuote($quote) {
        $this->quoteRepository->save($quote);
    }

    /**
     * Create order on bolt
     *
     * @param bool $paymentOnly               flag that represents the type of checkout
     * @param string $placeOrderPayload      additional data collected from the (one page checkout) page,
     *                                         i.e. billing address to be saved with the order
     *
     * @return Response|void
     * @throws \Exception
     * @throws LocalizedException
     * @throws Zend_Http_Client_Exception
     */
    public function getBoltpayOrder($paymentOnly, $placeOrderPayload)
    {
        //Get cart data
        $cart = $this->getCartData($paymentOnly, $placeOrderPayload);
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
     * @param string $placeOrderPayload     additional data collected from the (one page checkout) page,
     *                                         i.e. billing address to be saved with the order
     * @param string $cartReference         (immutable) quote id
     *
     * @return array
     */
    public function getHints($placeOrderPayload, $cartReference)
    {
        /** @var Quote */
        $quote = $cartReference ?
            $this->getQuoteById($cartReference) :
            $this->checkoutSession->getQuote();

        if ($placeOrderPayload) {
            $placeOrderPayload = @json_decode($placeOrderPayload);
            $email = @$placeOrderPayload->email;
        }

        $hints = ['prefill' => []];

        /**
         * Update hints from address data
         *
         * @param Address $address
         */
        $prefillHints = function($address) use (&$hints, $quote) {

            if (!$address) return;

            $prefill = [
                'firstName'    => $address->getFirstname(),
                'lastName'     => $address->getLastname(),
                'email'        => @$email ?: $address->getEmail() ?: $quote->getCustomerEmail(),
                'phone'        => $address->getTelephone(),
                'addressLine1' => $address->getStreetLine(1),
                'addressLine2' => $address->getStreetLine(2),
                'city'         => $address->getCity(),
                'state'        => $address->getRegion(),
                'zip'          => $address->getPostcode(),
                'country'      => $address->getCountryId(),
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

        // Quote shipping / billing address.
        // If assigned it takes precedence over logged in user default address.
        if ($quote->isVirtual()) {
            $prefillHints($quote->getBillingAddress());

        } else {
            $prefillHints($quote->getShippingAddress());
        }

        return $hints;
    }

    /**
     * Get cart data.
     * The reference of total methods: dev/tests/api-functional/testsuite/Magento/Quote/Api/CartTotalRepositoryTest.php
     *
     * @param bool $paymentOnly             flag that represents the type of checkout
     * @param string $placeOrderPayload     additional data collected from the (one page checkout) page,
     *                                         i.e. billing address to be saved with the order
     * @param Quote $immutableQuote         If passed do not create new clone, get data existing one data.
     *                                          discount validation, bugsnag report
     *
     * @return array
     * @throws \Exception
     */
    public function getCartData($paymentOnly, $placeOrderPayload, $immutableQuote = null)
    {
        $cart = [];

        // If the immutable quote is passed (i.e. discount code validation, bugsnag report generation)
        // load the parent quote, otherwise load the session quote
        try {
            /** @var Quote $quote */
            $quote = $immutableQuote ?
                $this->getActiveQuoteById($immutableQuote->getBoltParentQuoteId()) :
                $this->checkoutSession->getQuote();
        } catch (NoSuchEntityException $e) {
            // getActiveQuoteById(): Order has already been processed and parent quote inactive / deleted.
            $this->bugsnag->notifyException($e);
            $quote = null;
        }

        // The cart creation sometimes gets called when no (parent) quote exists:
        // 1. From frontend event handler: It is store specific, for example a minicart with 0 items.
        // 2. From backend, with $immutableQuote passed as parameter, parent already inactive / deleted:
        //    a) discount code validation
        //    b) bugsnag report generation
        // In case #1 the empty cart is returned
        // In case #2 the cart generation continues for the cloned quote
        if (!$immutableQuote && (!$quote || !$quote->getAllVisibleItems())) {
            return $cart;
        }

        ////////////////////////////////////////////////////////
        // CLONE THE QUOTE and quote billing / shipping  address
        // if immutable quote is not passed to the method - the
        // cart data is being created for sending to Bolt create
        // order API, otherwise skip this step
        ////////////////////////////////////////////////////////
        if (!$immutableQuote) {

            $quote->setBoltParentQuoteId($quote->getId());
            $quote->reserveOrderId();
            $this->quoteResource->save($quote);

            $immutableQuote = $this->quoteFactory->create();

            $immutableQuote->merge($quote);

            foreach ($quote->getData() as $key => $value) {
                $immutableQuote->setData($key, $value);
            }

            $immutableQuote->setId(null);
            $immutableQuote->setIsActive(false);
            $this->quoteResource->save($immutableQuote);

            foreach ($quote->getBillingAddress()->getData() as $key => $value) {
                if ($key != 'address_id') $immutableQuote->getBillingAddress()->setData($key, $value);
            }
            $immutableQuote->getBillingAddress()->save();

            foreach ($quote->getShippingAddress()->getData() as $key => $value) {
                if ($key != 'address_id') $immutableQuote->getShippingAddress()->setData($key, $value);
            }
            $immutableQuote->getShippingAddress()->save();
        }
        $billingAddress  = $immutableQuote->getBillingAddress();
        $shippingAddress = $immutableQuote->getShippingAddress();
        ////////////////////////////////////////////////////////

        // Get array of all items what can be display directly
        $items = $immutableQuote->getAllVisibleItems();

        if (!$items) {
            // This is the case when customer empties the cart.
            // Not an error. Commenting out bugsnag report for now.
            // $this->bugsnag->notifyError('Get Cart Data Error', 'The cart is empty');
            return $cart;
        }

        $immutableQuote->collectTotals();
        $totals = $immutableQuote->getTotals();

        // Set order_reference to parent quote id.
        // This is the constraint field on Bolt side and this way
        // duplicate payments / orders are prevented/
        $cart['order_reference'] = $immutableQuote->getBoltParentQuoteId();

        //Use display_id to hold and transmit, all the way back and forth, both reserved order id and immitable quote id
        $cart['display_id'] = $immutableQuote->getReservedOrderId() . ' / ' . $immutableQuote->getId();

        //Currency
        $cart['currency'] = $immutableQuote->getQuoteCurrencyCode();

        $totalAmount = 0;
        $diff = 0;

        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        // The "appEmulation" and block creation code is necessary for geting correct image url from an API call.
        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        $this->appEmulation->startEnvironmentEmulation(
            $immutableQuote->getStoreId(),
            \Magento\Framework\App\Area::AREA_FRONTEND,
            true
        );
        /** @var  \Magento\Catalog\Block\Product\ListProduct $imageBlock */
        $imageBlock = $this->blockFactory->createBlock('Magento\Catalog\Block\Product\ListProduct');

        foreach ($items as $item) {

            $product = [];
            $productId = $item->getProductId();

            $unitPrice   = $item->getCalculationPrice();
            $itemTotalAmount = $unitPrice * $item->getQty();

            $roundedTotalAmount = $this->getRoundAmount($itemTotalAmount);

            // Aggregate eventual total differences if prices are stored with more than 2 decimal places
            $diff += $itemTotalAmount * 100 -$roundedTotalAmount;

            // Aggregate cart total
            $totalAmount += $roundedTotalAmount;

            $product['reference']    = $productId;
            $product['name']         = $item->getName();
            $product['total_amount'] = $roundedTotalAmount;
            $product['unit_price']   = $this->getRoundAmount($unitPrice);
            $product['quantity']     = round($item->getQty());
            $product['sku']          = trim($item->getSku());
            $product['type']         = $item->getIsVirtual() ? self::ITEM_TYPE_DIGITAL : self::ITEM_TYPE_PHYSICAL;

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
        if ($placeOrderPayload) {
            $placeOrderPayload = json_decode($placeOrderPayload);

            $email                = @$placeOrderPayload->email ?: $email;
            $billAddress          = @$placeOrderPayload->billingAddress;
            $billingStreetAddress = (array)@$billAddress->street;

            if ($billAddress) {
                $cart['billing_address'] = [
                    'first_name'      => @$billAddress->firstname,
                    'last_name'       => @$billAddress->lastname,
                    'company'         => @$billAddress->company,
                    'phone'           => @$billAddress->telephone,
                    'street_address1' => (string)@$billingStreetAddress[0],
                    'street_address2' => (string)@$billingStreetAddress[1],
                    'locality'        => @$billAddress->city,
                    'region'          => @$billAddress->region,
                    'postal_code'     => @$billAddress->postcode,
                    'country_code'    => @$billAddress->countryId,
                ];
            }
        }

        if ($email) {
            $cart['billing_address']['email'] = $email;
        }

        $address = $immutableQuote->isVirtual() ? $billingAddress : $shippingAddress;

        // payment only checkout, include shipments, tax and grand total
        if ($paymentOnly) {

            if ($immutableQuote->isVirtual()) {

                $this->totalsCollector->collectAddressTotals($immutableQuote, $address);
                $address->save();

            } else {

                $address->setCollectShippingRates(true);

                // assign parent shipping method to clone
                if (!$address->getShippingMethod() && $quote) {
                    $address->setShippingMethod($quote->getShippingAddress()->getShippingMethod());
                }

                $this->totalsCollector->collectAddressTotals($immutableQuote, $address);
                $address->save();

                // Shipping address
                $shipAddress = [
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
                    $shipAddress['email'] = $email;
                }

                foreach ($this->requiredAddressFields as $field) {
                    if (empty($shipAddress[$field])) {
                        unset($shipAddress);
                        break;
                    }
                }

                if (@$shipAddress) {
                    $cost = $address->getShippingAmount();
                    $rounded_cost = $this->getRoundAmount($cost);

                    $diff += $cost * 100 - $rounded_cost;
                    $totalAmount += $rounded_cost;

                    $cart['shipments'] = [[
                        'cost' => $rounded_cost,
                        'tax_amount' => $this->getRoundAmount($address->getShippingTaxAmount()),
                        'shipping_address' => $shipAddress,
                        'service' => $shippingAddress->getShippingDescription(),
                        'reference' => $shippingAddress->getShippingMethod(),
                    ]];
                }
            }

            $storeTaxAmount   = $address->getTaxAmount();
            $roundedTaxAmount = $this->getRoundAmount($storeTaxAmount);

            $diff += $storeTaxAmount * 100 - $roundedTaxAmount;

            $taxAmount    = $roundedTaxAmount;
            $totalAmount += $roundedTaxAmount;
        } else {
            // multi-step checkout, subtotal with discounts, no shipping, no tax
            $taxAmount = 0;
        }

        // include potential rounding difference and reset $diff accumulator
        $cart['items'][0]['total_amount'] += round($diff);
        $totalAmount += round($diff);
        $diff = 0;

        // unset billing if not all required fields are present
        $requiredBillingFields = array_merge($this->requiredAddressFields, $this->requiredBillingAddressFields);
        foreach ($requiredBillingFields as $field) {
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
            $roundedAmount = $this->getRoundAmount($amount);

            $cart['discounts'][] = [
                'description' => trim(__('Discount ') . $address->getDiscountDescription()),
                'amount'      => $roundedAmount,
            ];

            $diff -= $amount * 100 - $roundedAmount;
            $totalAmount -= $roundedAmount;
        }
        /////////////////////////////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////////////
        // Process Store Credit
        /////////////////////////////////////////////////////////////////////////////////
        if ($immutableQuote->getUseCustomerBalance()) {

            if ($paymentOnly && $amount = abs($immutableQuote->getCustomerBalanceAmountUsed())) {

                $roundedAmount = $this->getRoundAmount($amount);

                $cart['discounts'][] = [
                    'description' => 'Store Credit',
                    'amount'      => $roundedAmount,
                ];

                $diff -= $amount * 100 - $roundedAmount;
                $totalAmount -= $roundedAmount;

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

                    $roundedAmount = $this->getRoundAmount($amount);

                    $cart['discounts'][] = [
                        'description' => 'Store Credit',
                        'amount'      => $roundedAmount,
                        'type'        => 'fixed_amount',
                    ];

                    $totalAmount -= $roundedAmount;
                }
            }
        }
        /////////////////////////////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////////////
        // Process Reward Points
        /////////////////////////////////////////////////////////////////////////////////
        if ($immutableQuote->getUseRewardPoints()) {

            if ($paymentOnly && $amount = abs($immutableQuote->getRewardCurrencyAmount())) {

                $roundedAmount = $this->getRoundAmount($amount);

                $cart['discounts'][] = [
                    'description' => 'Reward Points',
                    'amount'      => $roundedAmount,
                ];

                $diff -= $amount * 100 - $roundedAmount;
                $totalAmount -= $roundedAmount;

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

                    $roundedAmount = $this->getRoundAmount($amount);

                    $cart['discounts'][] = [
                        'description' => 'Reward Points',
                        'amount'      => $roundedAmount,
                        'type'        => 'fixed_amount',
                    ];

                    $totalAmount -= $roundedAmount;
                }
            }
        }
        /////////////////////////////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////////////
        // Process other discounts, stored in totals array
        /////////////////////////////////////////////////////////////////////////////////
        foreach ($this->discountTypes as $discount) {
            if (@$totals[$discount] && $amount = @$totals[$discount]->getValue()) {
                $amount = abs($amount);
                $roundedAmount = $this->getRoundAmount($amount);

                $cart['discounts'][] = [
                    'description' => @$totals[$discount]->getTitle(),
                    'amount'      => $roundedAmount,
                ];

                $diff -= $amount * 100 - $roundedAmount;
                $totalAmount -= $roundedAmount;
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

        // $this->logHelper->addInfoLog(json_encode($cart, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

        return $cart;
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

    /**
     * Special address cases handler
     *
     * @param array|object $addressData
     * @return array|object
     */
    public function handleSpecialAddressCases($addressData) {
        return $this->handlePuertoRico($addressData);
    }

    /**
     * Handle Puerto Rico address special case. Bolt thinks Puerto Rico is a country magento thinks it is US.
     *
     * @param array|object $addressData
     * @return array|object
     */
    private function handlePuertoRico($addressData) {
        $address = (array)$addressData;
        if ($address['country_code'] === 'PR') {
            $address['country_code'] = 'US';
            $address['country'] = 'United States';
            $address['region'] = 'Puerto Rico';
        }
        return is_object($addressData) ? (object)$address : $address;
    }
}
