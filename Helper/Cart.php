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
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;

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
    private $blockFactory;

    /**
     * @var Emulation
     */
    private $appEmulation;

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

    /** @var SessionHelper */
    private $sessionHelper;

    /**
     * @var CheckoutHelper
     */
    private $checkoutHelper;

    /**
     * @var DiscountHelper
     */
    private $discountHelper;

    // Billing / shipping address fields that are required when the address data is sent to Bolt.
    private $requiredAddressFields = [
        'first_name',
        'last_name',
        'street_address1',
        'locality',
        'region',
        'postal_code',
        'country_code',
        'email'
    ];

    /////////////////////////////////////////////////////////////////////////////
    // Store discount type keys and description prefixes, internal and 3rd party.
    // Can appear as keys in Quote::getTotals result array.
    /////////////////////////////////////////////////////////////////////////////
    private $discountTypes = [
        Discount::GIFT_VOUCHER_AFTER_TAX => '',
        Discount::GIFT_CARD_ACCOUNT => '',
        Discount::UNIRGY_GIFT_CERT => '',
        Discount::AMASTY_GIFTCARD => 'Gift Card ',
        Discount::GIFT_VOUCHER => ''
    ];
    /////////////////////////////////////////////////////////////////////////////

    // Totals adjustment treshold
    private $treshold = 0.01;

    /**
     * @var array
     */
    private $quotes = [];

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
     * @param SessionHelper $sessionHelper
     * @param CheckoutHelper $checkoutHelper
     * @param DiscountHelper $discountHelper
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
        QuoteResource $quoteResource,
        SessionHelper $sessionHelper,
        CheckoutHelper $checkoutHelper,
        DiscountHelper $discountHelper
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
        $this->sessionHelper = $sessionHelper;
        $this->checkoutHelper = $checkoutHelper;
        $this->discountHelper = $discountHelper;
    }

    /**
     * Check if guest checkout is allowed
     *
     * @return bool
     */
    public function isCheckoutAllowed()
    {
        return $this->customerSession->isLoggedIn() || $this->checkoutHelper->isAllowedGuestCheckout($this->checkoutSession->getQuote());
    }

    /**
     * Load Quote by id
     * @param $quoteId
     * @return \Magento\Quote\Model\Quote
     * @throws NoSuchEntityException
     */
    public function getQuoteById($quoteId)
    {
        if (!isset($this->quotes[$quoteId])) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('main_table.entity_id', $quoteId)->create();

            $collection = $this->quoteRepository
                ->getList($searchCriteria)
                ->getItems();

            $this->quotes[$quoteId] = reset($collection);
        }

        return $this->quotes[$quoteId];
    }

    /**
     * Load Quote by id if active
     * @param $quoteId
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws NoSuchEntityException
     */
    public function getActiveQuoteById($quoteId)
    {
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
     * Save quote via repository
     *
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     */
    public function saveQuote($quote)
    {
        $this->quoteRepository->save($quote);
    }

    /**
     * Save quote via resource model
     *
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function quoteResourceSave($quote)
    {
        $this->quoteResource->save($quote);
    }

    /**
     * Create order on bolt
     *
     * @param bool   $paymentOnly              flag that represents the type of checkout
     * @param string $placeOrderPayload        additional data collected from the (one page checkout) page,
     *                                         i.e. billing address to be saved with the order
     * @param null|int    $storeId
     *
     * @return Response|void
     * @throws LocalizedException
     * @throws Zend_Http_Client_Exception
     */
    public function getBoltpayOrder($paymentOnly, $placeOrderPayload, $storeId = null)
    {
        //Get cart data
        $cart = $this->getCartData($paymentOnly, $placeOrderPayload);
        if (!$cart) {
            return;
        }

        // cache the session id
        $this->sessionHelper->saveSession($cart['order_reference'], $this->checkoutSession);

        // If storeId was missed through request, then try to get it from the session quote.
        if ($storeId === null && $this->checkoutSession->getQuote()) {
            $storeId = $this->checkoutSession->getQuote()->getStoreId();
        }

        $apiKey = $this->configHelper->getApiKey($storeId);

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
     * @param array $signRequest payload to sign
     * @param null  $storeId
     *
     * @return Response|int
     */
    private function getSignResponse($signRequest, $storeId = null)
    {
        $apiKey = $this->configHelper->getApiKey($storeId);

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
     * @param string $cartReference            (immutable) quote id
     * @param string $checkoutType             'cart' | 'admin' Default to `admin`
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getHints($cartReference = null, $checkoutType = 'admin')
    {
        /** @var Quote */
        $quote = $cartReference ?
            $this->getQuoteById($cartReference) :
            $this->checkoutSession->getQuote();

        $hints = ['prefill' => []];

        /**
         * Update hints from address data
         *
         * @param Address $address
         */
        $prefillHints = function ($address) use (&$hints, $quote) {

            if (!$address) {
                return;
            }

            $prefill = [
                'firstName'    => $address->getFirstname(),
                'lastName'     => $address->getLastname(),
                'email'        => $address->getEmail() ?: $quote->getCustomerEmail(),
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
            $signResponse = $this->getSignResponse($signRequest, $quote->getStoreId())->getResponse();

            if ($signResponse) {
                $hints['merchant_user_id'] = $signResponse->merchant_user_id;
                $hints['signature'] = $signResponse->signature;
                $hints['nonce'] = $signResponse->nonce;
            }

            if ($quote->isVirtual()) {
                $prefillHints($customer->getDefaultBillingAddress());
            } else {
                $prefillHints($customer->getDefaultShippingAddress());
            }

            $hints['prefill']['email'] = $customer->getEmail();
        }

        // Quote shipping / billing address.
        // If assigned it takes precedence over logged in user default address.
        if ($quote->isVirtual()) {
            $prefillHints($quote->getBillingAddress());
        } else {
            $prefillHints($quote->getShippingAddress());
        }

        if ($checkoutType === 'admin') {
            $hints['virtual_terminal_mode'] = true;
        }

        return $hints;
    }

    /**
     * Set immutable quote and addresses data from the parent quote
     *
     * @param Quote|QuoteAddress $parent parent object
     * @param Quote|QuoteAddress $child  child object
     * @param bool $save                 if set to true save the $child instance upon the transfer
     * @param array $emailFields         fields that need to pass email validation to be transfered, skipped otherwise
     * @param array $excludeFields       fields to be excluded from the transfer (e.g. unique identifiers)
     * @throws \Zend_Validate_Exception
     */
    private function transferData(
        $parent,
        $child,
        $save = true,
        $emailFields = ['customer_email', 'email'],
        $excludeFields = ['entity_id', 'address_id', 'reserved_order_id']
    ) {
        foreach ($parent->getData() as $key => $value) {

            if (in_array($key, $excludeFields)) continue;
            if (in_array($key, $emailFields) && !$this->validateEmail($value)) continue;

            $child->setData($key, $value);
        }
        if ($save) $child->save();
    }

    /**
     * Clone quote data from source to destination
     *
     * @param Quote $source
     * @param Quote $destination
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Zend_Validate_Exception
     */
    public function replicateQuoteData($source, $destination)
    {
        // Skip the replication if source and destination point to the same quote
        // E.g. delayed Save Order - immutable quote is cleared by cron and we use the parent instead
        if ($source->getId() == $destination->getId()) return;

        $destinationId = $destination->getId();
        $destinationActive = (bool)$destination->getIsActive();

        $destination->removeAllItems();

        $destination->merge($source);

        $destination->getBillingAddress()->setShouldIgnoreValidation(true);
        $this->transferData($source->getBillingAddress(), $destination->getBillingAddress());

        $destination->getShippingAddress()->setShouldIgnoreValidation(true);
        $this->transferData($source->getShippingAddress(), $destination->getShippingAddress());

        $this->transferData($source, $destination, false);

        $destination->setId($destinationId);
        $destination->setIsActive($destinationActive);

        $this->quoteResourceSave($destination);

        // If Amasty Gif Cart Extension is present clone applied gift cards
        $this->discountHelper->cloneAmastyGiftCards($source->getId(), $destination->getId());
    }

    /**
     * Reserve Order Id for the first immutable quote created.
     * Store it in BoltReservedOrderId field in the parent quote
     * as well as in any subsequently created immutable quote.
     * Defer setting ReservedOrderId on the parent quote
     * until the quote to order submission.
     * Reason: Some 3rd party plugins read this value and if set
     * consider the quote to be a complete order.
     *
     * @param Quote $immutableQuote
     * @param Quote $quote
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    protected function reserveOrderId($immutableQuote, $quote)
    {
        $reservedOrderId = $immutableQuote->getBoltReservedOrderId();
        if (!$reservedOrderId) {
            $reservedOrderId = $immutableQuote->reserveOrderId()->getReservedOrderId();
            $immutableQuote->setBoltReservedOrderId($reservedOrderId);
            $quote->setBoltReservedOrderId($reservedOrderId);
            $this->quoteResourceSave($quote);
        } else {
            $immutableQuote->setReservedOrderId($reservedOrderId);
        }
        $this->quoteResourceSave($immutableQuote);
    }

    /**
     * Create an immutable quote.
     * Set the BoltParentQuoteId to the parent quote, if not set already,
     * so it can be replicated to immutable quote in replicateQuoteData.
     *
     * @param Quote $quote
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Zend_Validate_Exception
     */
    protected function createImmutableQuote($quote)
    {
        if (!$quote->getBoltParentQuoteId()) {
            $quote->setBoltParentQuoteId($quote->getId());
            $this->quoteResourceSave($quote);
        }
        /** @var Quote $immutableQuote */
        $immutableQuote = $this->quoteFactory->create();

        $this->replicateQuoteData($quote, $immutableQuote);

        return $immutableQuote;
    }

    /**
     * Check if all the required address fields are populated.
     *
     * @param array $address
     * @return bool
     */
    private function isAddressComplete($address)
    {
        foreach ($this->requiredAddressFields as $field) {
            if (empty($address[$field])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Bugsnag address data
     *
     * @param array $addressData
     */
    private function logAddressData($addressData) {
        $this->bugsnag->registerCallback(function ($report) use ($addressData) {
            $report->setMetaData([
                'ADDRESS_DATA' => $addressData
            ]);
        });
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
                $this->getQuoteById($immutableQuote->getBoltParentQuoteId()) :
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
            $immutableQuote = $this->createImmutableQuote($quote);
            $this->reserveOrderId($immutableQuote, $quote);
        }

        $billingAddress  = $immutableQuote->getBillingAddress();
        $shippingAddress = $immutableQuote->getShippingAddress();
        ////////////////////////////////////////////////////////

        // Get array of all items that can be display directly
        $items = $immutableQuote->getAllVisibleItems();

        if (!$items) {
            // This is the case when customer empties the cart.
            return $cart;
        }

        $immutableQuote->collectTotals();
        $totals = $immutableQuote->getTotals();

        $this->logHelper->addInfoLog('### CartTotals: ' . json_encode(array_keys($totals)));

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
            $item_options = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
            if(isset($item_options['attributes_info'])){
                $properties = array();
                foreach($item_options['attributes_info'] as $attribute_info){
                    $properties[] = (object) array( "name" => $attribute_info['label'], "value" => $attribute_info['value'] );
                }
                $product['properties'] = $properties;
            }
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
            if (@$productImage) {
                $product['image_url'] = ltrim($productImage->getImageUrl(),'/');
            }
            ////////////////////////////////////

            //Add product to items array
            $cart['items'][] = $product;
        }

        $this->appEmulation->stopEnvironmentEmulation();
        /////////////////////////////////////////////////////////////////////////////////////////////////////////

        // Email field is mandatory for saving the address.
        // For back-office orders (payment only) we need to get it from the store.
        // Trying several possible places.
        $email = $billingAddress->getEmail()
            ?: $shippingAddress->getEmail()
            ?: $this->customerSession->getCustomer()->getEmail()
            ?: $immutableQuote->getCustomerEmail();

        // Billing address
        $cartBillingAddress = [
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
            'email'           => $email
        ];

        // additional data sent, i.e. billing address from checkout page
        if ($placeOrderPayload) {
            $placeOrderPayload = json_decode($placeOrderPayload);

            $billAddress          = @$placeOrderPayload->billingAddress;
            $billingStreetAddress = (array)@$billAddress->street;

            if ($billAddress) {
                $cartBillingAddress = [
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
                    'email'           => @$placeOrderPayload->email ?: $email
                ];
            }
        }

        // Billing address is not mandatory set it only if it is complete
        if ($this->isAddressComplete($cartBillingAddress)) {
            $cart['billing_address'] = $cartBillingAddress;
        }

        $address = $immutableQuote->isVirtual() ? $billingAddress : $shippingAddress;

        // payment only checkout, include shipments, tax and grand total
        if ($paymentOnly) {
            if ($immutableQuote->isVirtual()) {
                if (@$cart['billing_address']){
                    $this->totalsCollector->collectAddressTotals($immutableQuote, $address);
                    $address->save();
                } else {
                    $this->logAddressData($cartBillingAddress);
                    $this->bugsnag->notifyError(
                        'Order create error',
                        'Billing address data insufficient.'
                    );
                    return [];
                }
            } else {
                $address->setCollectShippingRates(true);

                // assign parent shipping method to clone
                if (!$address->getShippingMethod() && $quote) {
                    $address->setShippingMethod($quote->getShippingAddress()->getShippingMethod());
                }

                if (!$address->getShippingMethod()) {
                    $this->bugsnag->notifyError(
                        'Order create error',
                        'Shipping method not set.'
                    );
                    return [];
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
                    'email' => $address->getEmail() ?: $email
                ];

                if ($this->isAddressComplete($shipAddress)) {
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
                } else {
                    $this->logAddressData($shipAddress);
                    $this->bugsnag->notifyError(
                        'Order create error',
                        'Shipping address data insufficient.'
                    );
                    return [];
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

        // add discount data
        $cart['discounts'] = [];

        /////////////////////////////////////////////////////////////////////////////////
        // Process store integral discounts and coupons.
        // For some types of applied coupon, the discount amount could be zero before
        // selecting specific shipping option, so the conditional statement should also
        // check if getCouponCode is not null
        /////////////////////////////////////////////////////////////////////////////////
        if ( ($amount = $address->getDiscountAmount()) || $address->getCouponCode() ) {
            $amount         = abs($amount);
            $roundedAmount = $this->getRoundAmount($amount);

            $cart['discounts'][] = [
                'description' => trim(__('Discount ') . $address->getDiscountDescription()),
                'amount'      => $roundedAmount,
                'reference'   => $address->getCouponCode()
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
        foreach ($this->discountTypes as $discount => $description) {

            if (@$totals[$discount] && $amount = @$totals[$discount]->getValue()) {
                ///////////////////////////////////////////////////////////////////////////
                // If Amasty gift cards can be used for shipping and tax (PayForEverything)
                // accumulate all the applied gift cards balance as discount amount. If the
                // final discounts sum is greater than the cart total amount ($totalAmount < 0)
                // the "fixed_amount" type is added below.
                ///////////////////////////////////////////////////////////////////////////
                if ($discount ==  Discount::AMASTY_GIFTCARD && $this->discountHelper->getAmastyPayForEverything()) {

                    $giftCardCodes = $this->discountHelper->getAmastyGiftCardCodesFromTotals($totals);
                    $amount = $this->discountHelper->getAmastyGiftCardCodesCurrentValue($giftCardCodes);
                }
                ///////////////////////////////////////////////////////////////////////////

                ///////////////////////////////////////////////////////////////////////////
                /// Was added a proper Unirgy_Giftcert Amount to the discount.
                /// The GiftCert accumulate correct balance only after each collectTotals.
                ///  The Unirgy_Giftcert add the only discount which covers only product price.
                ///  We should get the whole balance at first of the Giftcert.
                ///////////////////////////////////////////////////////////////////////////
                if ($discount == Discount::UNIRGY_GIFT_CERT && $immutableQuote->getData('giftcert_code')) {
                    $gcCode = $immutableQuote->getData('giftcert_code');
                    $giftCertBalance = $this->discountHelper->getUnirgyGiftCertBalanceByCode($gcCode);
                    if ($giftCertBalance > 0) {
                        $amount = $giftCertBalance;
                    }
                }

                $amount = abs($amount);
                $roundedAmount = $this->getRoundAmount($amount);

                $cart['discounts'][] = [
                    'description' => $description . @$totals[$discount]->getTitle(),
                    'amount'      => $roundedAmount,
                ];

                if ($discount == Discount::GIFT_VOUCHER) {
                    // the amount is added to adress discount included above, $address->getDiscountAmount(),
                    // by plugin implementation, subtract it so this discount is shown separately and totals are in sync
                    $cart['discounts'][0]['amount'] -= $roundedAmount;
                } else {
                    $diff -= $amount * 100 - $roundedAmount;
                    $totalAmount -= $roundedAmount;
                }
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
    public function validateEmail($email)
    {
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
    public function handleSpecialAddressCases($addressData)
    {
        return $this->handlePuertoRico($addressData);
    }

    /**
     * Handle Puerto Rico address special case. Bolt thinks Puerto Rico is a country magento thinks it is US.
     *
     * @param array|object $addressData
     * @return array|object
     */
    private function handlePuertoRico($addressData)
    {
        $address = (array)$addressData;
        if ($address['country_code'] === 'PR') {
            $address['country_code'] = 'US';
            $address['country'] = 'United States';
            $address['region'] = 'Puerto Rico';
        }
        return is_object($addressData) ? (object)$address : $address;
    }

    /**
     * Check the cart items for properties that are a restriction to Bolt checkout.
     * Properties are checked with getters specified in configuration.
     *
     * @param Quote|null $quote
     * @param null|int   $magentoStoreId
     *
     * @return bool
     */
    public function hasProductRestrictions($quote = null, $magentoStoreId = null)
    {

        $toggleCheckout = $this->configHelper->getToggleCheckout($magentoStoreId);

        if (!$toggleCheckout || !$toggleCheckout->active) {
            return false;
        }

        // get configured Product model getters that can restrict Bolt checkout usage
        $productRestrictionMethods = $toggleCheckout->productRestrictionMethods ?: [];

        // get configured Quote Item getters that can restrict Bolt checkout usage
        $itemRestrictionMethods = $toggleCheckout->itemRestrictionMethods ?: [];

        if (!$productRestrictionMethods && !$itemRestrictionMethods) {
            return false;
        }

        /** @var Quote $quote */
        $quote = $quote ?: $this->checkoutSession->getQuote();
        foreach ($quote->getAllVisibleItems() as $item) {
            // call every method on item, if returns true, do restrict
            foreach ($itemRestrictionMethods as $method) {
                if ($item->$method()) {
                    return true;
                }
            }
            // Non empty check to avoid unnecessary model load
            if ($productRestrictionMethods) {
                // get item product
                $product = $this->productFactory->create()->load($item->getProductId());
                // call every method on product, if returns true, do restrict
                foreach ($productRestrictionMethods as $method) {
                    if ($product->$method()) {
                        return true;
                    }
                }
            }
        }

        // no restrictions
        return false;
    }
}
