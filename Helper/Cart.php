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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Helper;

use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider as DeciderHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Model\Response;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Catalog\Model\Config\Source\Product\Thumbnail as ThumbnailSource;
use Magento\Catalog\Model\ProductRepository;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface as Serialize;
use Magento\Framework\Session\SessionManagerInterface as CheckoutSession;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Msrp\Helper\Data as MsrpHelper;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface as QuoteRepository;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Address\Total as AddressTotal;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResourceModel;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface as OrderRepository;
use Magento\Sales\Model\Order;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Model\RuleRepository;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Store\Model\Store;
use Magento\Framework\App\ObjectManager;

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
    const BOLT_ORDER_TAG = 'Bolt_Order';
    const ADDRESS_COUNTRY_CODE_KEY = 'country_code';
    const ADDRESS_REGION_KEY = 'region';
    const ADDRESS_COUNTRY_CODE_US = 'US';
    const BOLT_ORDER_CACHE_LIFETIME = 3600; // one hour
    const BOLT_CHECKOUT_TYPE_MULTISTEP = 1;
    const BOLT_CHECKOUT_TYPE_PPC = 2;
    const BOLT_CHECKOUT_TYPE_BACKOFFICE = 3;
    const BOLT_CHECKOUT_TYPE_PPC_COMPLETE = 4;
    const MAGENTO_SKU_DELIMITER = '-';

    /** @var CacheInterface */
    private $cache;

    /** @var CartInterface */
    private $lastImmutableQuote = null;

    /** @var CheckoutSession */
    private $checkoutSession;

    /** @var CustomerSession */
    private $customerSession;

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var ProductRepository
     */
    private $productRepository;

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
     * @var ImageFactory
     */
    private $imageHelperFactory;

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

    /**
     * @var HookHelper
     */
    private $hookHelper;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @var EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;

    /**
     * @var RuleRepository
     */
    private $ruleRepository;

    // Billing / shipping address fields that are required when the address data is sent to Bolt.
    private $requiredAddressFields = [
        'first_name',
        'last_name',
        'street_address1',
        'locality',
        'postal_code',
        'country_code',
        'email'
    ];

    /////////////////////////////////////////////////////////////////////////////
    // Store discount type keys and description prefixes, internal and 3rd party.
    // Can appear as keys in Quote::getTotals result array.
    /////////////////////////////////////////////////////////////////////////////
    protected $discountTypes = [
        Discount::GIFT_VOUCHER_AFTER_TAX => '',
        Discount::UNIRGY_GIFT_CERT => '',
        Discount::GIFT_VOUCHER => ''
    ];
    /////////////////////////////////////////////////////////////////////////////

    // Totals adjustment threshold
    private $threshold = 0.01;

    /**
     * @var array
     */
    private $quotes = [];

    /**
     * @var ResourceConnection $resource
     */
    private $resourceConnection;

    /**
     * @var Order[]
     */
    private $orderData;

    /**
     * @var CartManagementInterface
     */
    private $quoteManagement;

    /**
     * @var MetricsClient
     */
    private $metricsClient;

    /**
     * @var DeciderHelper
     */
    private $deciderHelper;

    /**
     * @var Serialize
     */
    private $serialize;

    /**
     * @var MsrpHelper
     */
    private $msrpHelper;

    /**
     * @var PriceHelper
     */
    private $priceHelper;

    /**
     * @var Store
     */
    private $store;

    /**
     * @var QuoteIdMaskResourceModel
     */
    private $quoteIdMaskResourceModel;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param ProductRepository $productRepository
     * @param Api $apiHelper
     * @param Config $configHelper
     * @param CustomerSession $customerSession
     * @param Log $logHelper
     * @param Bugsnag $bugsnag
     * @param DataObjectFactory $dataObjectFactory
     * @param ImageFactory $imageHelperFactory
     * @param Emulation $appEmulation
     * @param QuoteFactory $quoteFactory
     * @param TotalsCollector $totalsCollector
     * @param QuoteRepository $quoteRepository
     * @param OrderRepository $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param QuoteResource $quoteResource
     * @param Session $sessionHelper
     * @param CheckoutHelper $checkoutHelper
     * @param Discount $discountHelper
     * @param CacheInterface $cache
     * @param ResourceConnection $resourceConnection
     * @param CartManagementInterface $quoteManagement
     * @param Hook $hookHelper
     * @param CustomerRepository $customerRepository
     * @param MetricsClient $metricsClient
     * @param DeciderHelper $deciderHelper
     * @param Serialize $serialize
     * @param EventsForThirdPartyModules $eventsForThirdPartyModules
     * @param RuleRepository $ruleRepository
     * @param MsrpHelper $msrpHelper
     * @param PriceHelper $priceHelper
     * @param Store $store
     * @param QuoteIdMaskResourceModel|null $quoteIdMaskResourceModel
     * @param QuoteIdMaskFactory|null $quoteIdMaskFactory
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        ProductRepository $productRepository,
        ApiHelper $apiHelper,
        ConfigHelper $configHelper,
        CustomerSession $customerSession,
        LogHelper $logHelper,
        Bugsnag $bugsnag,
        DataObjectFactory $dataObjectFactory,
        ImageFactory $imageHelperFactory,
        Emulation $appEmulation,
        QuoteFactory $quoteFactory,
        TotalsCollector $totalsCollector,
        QuoteRepository $quoteRepository,
        OrderRepository $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        QuoteResource $quoteResource,
        SessionHelper $sessionHelper,
        CheckoutHelper $checkoutHelper,
        DiscountHelper $discountHelper,
        CacheInterface $cache,
        ResourceConnection $resourceConnection,
        CartManagementInterface $quoteManagement,
        HookHelper $hookHelper,
        CustomerRepository $customerRepository,
        MetricsClient $metricsClient,
        DeciderHelper $deciderHelper,
        Serialize $serialize,
        EventsForThirdPartyModules $eventsForThirdPartyModules,
        RuleRepository $ruleRepository,
        MsrpHelper $msrpHelper,
        PriceHelper $priceHelper,
        Store $store,
        ?QuoteIdMaskResourceModel $quoteIdMaskResourceModel = null,
        ?QuoteIdMaskFactory $quoteIdMaskFactory = null
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->productRepository = $productRepository;
        $this->apiHelper = $apiHelper;
        $this->configHelper = $configHelper;
        $this->customerSession = $customerSession;
        $this->logHelper = $logHelper;
        $this->bugsnag = $bugsnag;
        $this->imageHelperFactory = $imageHelperFactory;
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
        $this->cache = $cache;
        $this->resourceConnection = $resourceConnection;
        $this->quoteManagement = $quoteManagement;
        $this->hookHelper = $hookHelper;
        $this->customerRepository = $customerRepository;
        $this->metricsClient = $metricsClient;
        $this->deciderHelper = $deciderHelper;
        $this->serialize = $serialize;
        $this->eventsForThirdPartyModules = $eventsForThirdPartyModules;
        $this->ruleRepository = $ruleRepository;
        $this->msrpHelper = $msrpHelper;
        $this->priceHelper = $priceHelper;
        $this->store = $store;
        $this->quoteIdMaskResourceModel = $quoteIdMaskResourceModel ?:
            ObjectManager::getInstance()->get(QuoteIdMaskResourceModel::class);
        $this->quoteIdMaskFactory = $quoteIdMaskFactory ?:
            ObjectManager::getInstance()->get(QuoteIdMaskFactory::class);
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
     * @return \Magento\Quote\Model\Quote|false
     */
    public function getQuoteById($quoteId)
    {
        if (!isset($this->quotes[$quoteId])) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('main_table.entity_id', $quoteId)->create();

            $collection = $this->quoteRepository
                ->getList($searchCriteria)
                ->getItems();

            $quote = reset($collection);

            if ($quote === false) {
                return false;
            }

            $this->quotes[$quoteId] = $quote;
        }

        return $this->quotes[$quoteId];
    }

    /**
     * Load Quote by id if active
     * @param $quoteId
     * @return CartInterface
     * @throws NoSuchEntityException
     */
    public function getActiveQuoteById($quoteId)
    {
        return $this->quoteRepository->getActive($quoteId);
    }

    /**
     * Load Order by increment id
     *
     * @param string $incrementId
     * @param bool   $forceLoad - use it if needed to load data without cache.
     *
     * @return OrderInterface|false
     */
    public function getOrderByIncrementId($incrementId, $forceLoad = false)
    {
        if ($forceLoad || !isset($this->orderData[$incrementId])) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $incrementId, 'eq')
                ->create();
            $collection = $this->orderRepository
                ->getList($searchCriteria)
                ->getItems();

            $this->orderData[$incrementId] = reset($collection);
        }
        return $this->orderData[$incrementId];
    }

    /**
     * Load Order by quote id
     *
     * @param string $quoteId
     *
     * @return OrderInterface|false
     */
    public function getOrderByQuoteId($quoteId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('quote_id', $quoteId, 'eq')
            ->create();
        $collection = $this->orderRepository
            ->getList($searchCriteria)
            ->getItems();

        return reset($collection);
    }

    /**
     * Load Order by order id
     *
     * @param $orderId
     * @return OrderInterface|mixed
     */
    public function getOrderById($orderId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('main_table.entity_id', $orderId)->create();

        $collection = $this->orderRepository
            ->getList($searchCriteria)
            ->getItems();

        return reset($collection);
    }

    /**
     * Save quote via repository
     *
     * @param CartInterface $quote
     */
    public function saveQuote($quote)
    {
        $this->quoteRepository->save($quote);
    }

    /**
     * Delete quote via repository
     *
     * @param CartInterface $quote
     */
    public function deleteQuote($quote)
    {
        $this->eventsForThirdPartyModules->dispatchEvent("beforeCartDeleteQuote", $quote);
        $this->quoteRepository->delete($quote);
    }

    /**
     * Save quote via resource model
     *
     * @param CartInterface $quote
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function quoteResourceSave($quote)
    {
        $this->quoteResource->save($quote);
    }

    /**
     * Get Bolt Order Caching flag configuration
     *
     * @param null|int    $storeId             The ID of the Magento store
     * @return bool
     */
    protected function isBoltOrderCachingEnabled($storeId)
    {
        return $this->configHelper->isBoltOrderCachingEnabled($storeId);
    }

    /**
     * Load data from Magento cache
     *
     * @param string $identifier
     * @param bool $unserialize
     * @return bool|mixed|string
     */
    protected function loadFromCache($identifier, $unserialize = true)
    {
        $cached = $this->cache->load($identifier);
        if (!$cached) {
            return false;
        }

        try {
            return $unserialize ? $this->serialize->unserialize($cached) : $cached;
        } catch (\InvalidArgumentException $e) {
            $this->bugsnag->notifyException($e);
            return false;
        }
    }

    /**
     * Save data to Magento cache
     *
     * @param array|object $data
     * @param string $identifier
     * @param int $lifeTime
     * @param bool $serialize
     * @param array $tags
     */
    protected function saveToCache($data, $identifier, $tags = [], $lifeTime = null, $serialize = true)
    {
        if ($serialize) {
            $data = $data instanceof \Magento\Framework\DataObject ? $data->toJson() : $this->serialize->serialize($data);
        }
        $this->cache->save($data, $identifier, $tags, $lifeTime);
    }

    /**
     * Last created immutable quote setter
     *
     * @param $quote
     */
    protected function setLastImmutableQuote($quote)
    {
        $this->lastImmutableQuote = $quote;
    }

    /**
     * Last created immutable quote getter
     *
     * @return CartInterface
     */
    protected function getLastImmutableQuote()
    {
        return $this->lastImmutableQuote;
    }

    /**
     * Cache the session id for the quote
     *
     * @param int|string $quoteId
     */
    protected function saveCartSession($quoteId)
    {
        $this->sessionHelper->saveSession($quoteId, $this->checkoutSession);
    }

    /**
     * Get store id from the session quote
     *
     * @return int
     */
    public function getSessionQuoteStoreId()
    {
        $sessionQuote = $this->checkoutSession->getQuote();
        return $sessionQuote && $sessionQuote->getStoreId() ? $sessionQuote->getStoreId() : null;
    }

    /**
     * Call Bolt Ceate Order API
     *
     * @param array $cart
     * @param int $storeId
     * @return Response|int
     * @throws LocalizedException
     */
    protected function boltCreateOrder($cart, $storeId = null)
    {
        $apiKey = $this->configHelper->getApiKey($storeId);

        //Request Data
        $requestData = $this->dataObjectFactory->create();
        $requestData->setApiData(['cart' => $cart]);
        $requestData->setDynamicApiUrl(ApiHelper::API_CREATE_ORDER);
        $requestData->setApiKey($apiKey);

        //Build Request
        $request = $this->apiHelper->buildRequest($requestData);
        $boltOrder  = $this->apiHelper->sendRequest($request);

        $boltOrder->setStoreId($storeId);

        return $boltOrder;
    }

    /**
     * Get the hash of the cart to be used as cache identifier
     *
     * @param array $cart
     * @return string
     */
    protected function getCartCacheIdentifier($cart)
    {
        // display_id is always different for every new cart / immutable quote
        // unset it in the cache identifier so the rest of the data can be matched
        unset($cart['display_id']);
        unset($cart['metadata']['immutable_quote_id']);
        $identifier  = json_encode($cart);
        // extend cache identifier with custom address fields
        if ($immutableQuote = $this->getLastImmutableQuote()) {
            $identifier .= $this->convertCustomAddressFieldsToCacheIdentifier($immutableQuote);
            $identifier .= $this->convertExternalFieldsToCacheIdentifier($immutableQuote);
        }
        $identifier .= $this->deciderHelper->isAPIDrivenIntegrationEnabled();
        $identifier = $this->eventsForThirdPartyModules->runFilter('getCartCacheIdentifier', $identifier, $immutableQuote, $cart);

        return hash('md5', $identifier);
    }

    /**
     * @param Quote $immutableQuote
     * @return string
     */
    protected function convertExternalFieldsToCacheIdentifier($immutableQuote)
    {
        $cacheIdentifier = "";
        // add gift message id into cart cache identifier
        if ($giftMessageId = $immutableQuote->getGiftMessageId()) {
            $cacheIdentifier .= $giftMessageId;
        }

        // add gift wrapping id into cart cache identifier
        if ($giftWrappingId = $immutableQuote->getGwId()) {
            $cacheIdentifier .= $giftWrappingId;
        }

        // add gift wrapping item ids into cart cache identifier
        $quoteItems = $immutableQuote->getAllVisibleItems();
        if ($quoteItems) {
            foreach ($quoteItems as $item) {
                if ($item->getGwId()) {
                    $cacheIdentifier .= $item->getItemId() . '-' . $item->getGwId();
                }
            }
        }

        $cacheIdentifier .= $immutableQuote->getCustomerGroupId();
        $cacheIdentifier .= $immutableQuote->getCustomerId();

        return $cacheIdentifier;
    }

    /**
     * Get array of custom address field names converted to PascalCase
     * used to build method names (i.e. getters)
     *
     * @param int $storeId
     * @return array
     */
    protected function getCustomAddressFieldsPascalCaseArray($storeId)
    {
        // get custom address fields from config
        $customAddressFields = explode(
            ',',
            $this->configHelper->getPrefetchAddressFields($storeId)
        );
        // trim values and filter out empty strings
        $customAddressFields = array_filter(array_map('trim', $customAddressFields));
        // convert to PascalCase
        $customAddressFields = array_map(
            function ($el) {
                return str_replace('_', '', ucwords($el, '_'));
            },
            $customAddressFields
        );

        return $customAddressFields;
    }

    /**
     * Create cache identifier string from custom address fields
     *
     * @param Quote $quote
     * @return string
     */
    public function convertCustomAddressFieldsToCacheIdentifier($quote)
    {
        $customAddressFields = $this->getCustomAddressFieldsPascalCaseArray($quote->getStoreId());
        $address = $this->getCalculationAddress($quote);

        $cacheIdentifier = "";

        // get the value of each valid field and include it in the cache identifier
        foreach ($customAddressFields as $key) {
            $hasField = 'has'.$key;
            $getValue = 'get'.$key;
            if ($address->$hasField()) {
                $cacheIdentifier .= '_'.$address->$getValue();
            }
        }

        return $cacheIdentifier;
    }

    /**
     * Get the id of the quote Bolt order was created for
     *
     * @param Object $response
     * @return mixed
     */
    public function getImmutableQuoteIdFromBoltOrder($response)
    {
        $immutableQuoteId = null;
        // If response is null don't even bother trying, we return null
        if ($response) {
            if (isset($response->cart->metadata->immutable_quote_id)) {
                $immutableQuoteId = $response->cart->metadata->immutable_quote_id;
            } else {
                // check if cart was created in plugin version before 2.14.0
                if (isset($response->cart->display_id)) {
                    $result = explode(' / ', $response->cart->display_id);
                    if (count($result) == 2) {
                        list(, $immutableQuoteId) = $result;
                    }
                }
            }
        }
        if (!$immutableQuoteId) {
            $this->bugsnag->notifyException(new \Exception("Bolt order doesn't contain immutable order id"));
        }
        return $immutableQuoteId;
    }

    /**
     * Get the id of the quote Bolt order was created for
     * The same as getImmutableQuoteIdFromBoltOrder but cart is in array format
     *
     * @param Response $boltOrder
     * @return mixed
     */
    public function getImmutableQuoteIdFromBoltCartArray($boltCart)
    {
        $immutableQuoteId = null;
        if (isset($boltCart['metadata']['immutable_quote_id'])) {
            $immutableQuoteId = $boltCart['metadata']['immutable_quote_id'];
        } else {
            // check if cart was created in plugin version before 2.14.0
            if (isset($boltCart['display_id'])) {
                list(, $immutableQuoteId) = explode(' / ', $boltCart['display_id']);
            }
        }
        if (!$immutableQuoteId) {
            $this->bugsnag->notifyException(new \Exception("Bolt order doesn't contain immutable order id"));
        }
        return $immutableQuoteId;
    }

    /**
     * Check if the quote is not deleted
     *
     * @param int|string $quoteId
     * @return bool
     * @throws NoSuchEntityException
     */
    protected function isQuoteAvailable($quoteId)
    {
        return (bool)$this->getQuoteById($quoteId);
    }

    /**
     * Update quote updated_at column
     *
     * @param int|string $quoteId
     */
    protected function updateQuoteTimestamp($quoteId)
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            // get table name with prefix
            $tableName = $this->resourceConnection->getTableName('quote');

            $sql = "UPDATE {$tableName} SET updated_at = CURRENT_TIMESTAMP WHERE entity_id = :entity_id";
            $bind = [
                'entity_id' => $quoteId
            ];

            $connection->query($sql, $bind);

            $connection->commit();
        } catch (\Zend_Db_Statement_Exception $e) {
            $connection->rollBack();
            $this->bugsnag->notifyException($e);
        }
    }

    /**
     * Clear external data applied to immutable quote (third party modules DB tables)
     *
     * @param Quote $quote
     */
    protected function clearExternalData($quote)
    {
        $this->eventsForThirdPartyModules->dispatchEvent("clearExternalData", $quote);
    }

    /**
     * Create order on bolt
     *
     * @param bool        $paymentOnly         flag that represents the type of checkout
     * @param null|string $placeOrderPayload   additional data collected from the (one page checkout) page,
     *                                         i.e. billing address to be saved with the order
     * @param null|int    $storeId             The ID of the Magento store
     *
     * @return Response|void
     * @throws LocalizedException
     */
    public function getBoltpayOrder($paymentOnly, $placeOrderPayload = null, $storeId = null)
    {
        //Get cart data
        $cart = $this->getCartData($paymentOnly, $placeOrderPayload);

        if (!$cart) {
            return;
        }

        $quote = $this->checkoutSession->getQuote();

        if ($this->doesOrderExist($cart, $quote)) {
            $this->deactivateSessionQuote($quote);
            throw new LocalizedException(
                __('Order was created. Please reload the page and try again')
            );
        }

        if (version_compare($this->configHelper->getStoreVersion(), '2.3.6', '<') && $this->deciderHelper->isAPIDrivenIntegrationEnabled()) {
            $this->saveQuote($quote);
        }

        // If storeId was missed through request, then try to get it from the session quote.
        if ($storeId === null) {
            $storeId = $this->getSessionQuoteStoreId();
        }

        // Try fetching data from cache
        if ($isBoltOrderCachingEnabled = $this->isBoltOrderCachingEnabled($storeId)) {

            $cacheIdentifier = $this->getCartCacheIdentifier($cart);

            if ($boltOrderData = $this->loadFromCache($cacheIdentifier)) {
                // re-create response object from the cached response
                $boltOrder = new Response(
                    [
                        'store_id' => $boltOrderData['store_id'],
                        // further in the code we expect the reponse as an object
                        'response' => ArrayHelper::arrayToObject($boltOrderData['response'])
                    ]
                );

                if ($this->isBackendSession() || $this->deciderHelper->isAPIDrivenIntegrationEnabled()) {
                    return $boltOrder;
                }

                $immutableQuoteId = $this->getImmutableQuoteIdFromBoltOrder($boltOrder->getResponse());

                // found in cache, check if the old immutable quote is still there
                if ($immutableQuoteId && $this->isQuoteAvailable($immutableQuoteId)) {
                    // update old immutable quote updated_at timestamp,
                    $this->updateQuoteTimestamp($immutableQuoteId);
                    // clear external data applied to immutable quote (third party modules DB tables)
                    $this->clearExternalData($this->getLastImmutableQuote());
                    // delete the last quote and return cached order
                    $this->deleteQuote($this->getLastImmutableQuote());
                    return $boltOrder;
                }
            }
        }

        // cache the session id
        $this->saveCartSession($cart['order_reference']);

        $boltOrder = $this->boltCreateOrder($cart, $storeId);

        // cache Bolt order
        if ($isBoltOrderCachingEnabled) {
            if ($this->deciderHelper->isAPIDrivenIntegrationEnabled()) {
                $this->cache->clean([self::BOLT_ORDER_TAG . '_' . $cart['order_reference']]);
            }
            $this->saveToCache(
                $boltOrder,
                $cacheIdentifier,
                [self::BOLT_ORDER_TAG, self::BOLT_ORDER_TAG . '_' . $cart['order_reference']],
                self::BOLT_ORDER_CACHE_LIFETIME
            );
        }

        return $boltOrder;
    }

    public function deactivateSessionQuote($quote)
    {
        try {
            if ($quote && $quote->getIsActive()) {
                $quoteId = $quote->getId();
                $quote->setIsActive(false)->save();
                $this->bugsnag->notifyError('Deactivate quote that associates with an existing order', "Quote Id: {$quoteId}");
            };

        } catch (\Exception $exception) {
            $this->bugsnag->notifyException($exception);
        }
    }

    /**
     * @param $cart
     * @param $quote
     * @return false|OrderInterface
     */
    public function doesOrderExist($cart, $quote)
    {
        $incrementId = $cart['display_id'];
        $order = $this->getOrderByIncrementId($incrementId);

        if ($quote && !$order) {
            $order = $this->getOrderByQuoteId($quote->getId());
        }

        return $order;
    }

    /**
     * Sign a payload using the Bolt endpoint
     *
     * @param array     $signRequest payload to sign
     * @param null|int  $storeId
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
     * @param string $checkoutType             'cart' | 'admin' | 'product' Default to `admin`
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getHints($cartReference = null, $checkoutType = 'admin')
    {
        /** @var Quote */
        if ($checkoutType != 'product') {
            $quote = $cartReference ?
                $this->getQuoteById($cartReference) :
                $this->checkoutSession->getQuote();
        } else {
            // For product page checkout we dont't have any Quote object
            $quote = null;
        }

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
                'email'        => $address->getEmail(),
                'phone'        => $address->getTelephone(),
                'addressLine1' => $address->getStreetLine(1),
                'addressLine2' => $address->getStreetLine(2),
                'city'         => $address->getCity(),
                'state'        => $address->getRegion(),
                'zip'          => $address->getPostcode(),
                'country'      => $address->getCountryId(),
            ];
            if (!$prefill['email'] && $quote) {
                $prefill['email'] = $quote->getCustomerEmail();
            }

            if (!$prefill['firstName'] && $this->customerSession->isLoggedIn()){
                $prefill['firstName'] = $this->customerSession->getCustomer()->getData('firstname');
            }

            if (!$prefill['lastName'] && $this->customerSession->isLoggedIn()){
                $prefill['lastName'] = $this->customerSession->getCustomer()->getData('lastname');
            }

            // Skip pre-fill for Apple Pay related data.
            if ($prefill['email'] == 'na@bolt.com' || $prefill['phone'] == '8005550111' || $prefill['addressLine1'] == 'tbd') {
                return;
            }

            foreach ($prefill as $name => $value) {
                if (empty($value)) {
                    unset($prefill[$name]);
                }
            }

            $hints['prefill'] = array_merge($hints['prefill'], $prefill);
        };

        // Logged in customers.
        // Merchant scope and prefill.
        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->customerSession->getCustomer();

            if (!$this->deciderHelper->ifShouldDisablePrefillAddressForLoggedInCustomer()) {
                $signRequest = [
                    'merchant_user_id' => $customer->getId(),
                ];
                $signResponse = $this->getSignResponse(
                    $signRequest,
                    $quote ? $quote->getStoreId() : null
                );
                if ($signResponse) {
                    $signResponse = $signResponse->getResponse();
                }

                if ($signResponse) {
                    $hints['signed_merchant_user_id'] = [
                        "merchant_user_id" => $signResponse->merchant_user_id,
                        "signature"        => $signResponse->signature,
                        "nonce"            => $signResponse->nonce,
                    ];
                }
            }

            if (!$this->deciderHelper->ifShouldDisablePrefillAddressForLoggedInCustomer()) {
                if ($quote && $quote->isVirtual()) {
                    $prefillHints($customer->getDefaultBillingAddress());
                } else {
                    // TODO: use billing address for checkout on product page and virtual products
                    $prefillHints($customer->getDefaultShippingAddress());
                }
            }

            $hints['prefill']['email'] = $customer->getEmail();
            if ($checkoutType=='product') {
                $hints['metadata']['encrypted_user_id'] = $this->getEncodeUserId();
            }
        }

        // Quote shipping / billing address.
        // If assigned it takes precedence over logged in user default address.
        if ($quote) {
            if ($quote->isVirtual()) {
                $prefillHints($quote->getBillingAddress());
            } else {
                $prefillHints($quote->getShippingAddress());
            }
        }

        if ($checkoutType === 'admin') {
            $hints['virtual_terminal_mode'] = true;
        }

        $hints['prefill'] = (object)$hints['prefill'];
        return $hints;
    }

    /**
     *  Generate JSON data contains user_id and signature
     *  It used for PPC (product page checkout)
     *
     */
    private function getEncodeUserId()
    {
        $user_id = $this->customerSession->getCustomer()->getId();
        $result = [
            'user_id'   => $user_id,
            'timestamp' => time()
        ];
        $result['signature'] = $this->hookHelper->computeSignature(json_encode($result));

        return json_encode($result);
    }

    /**
     * Set immutable quote and addresses data from the parent quote
     *
     * @param Quote|QuoteAddress $parent parent object
     * @param Quote|QuoteAddress $child  child object
     * @param bool $save                 if set to true save the $child instance upon the transfer
     * @param array $emailFields         fields that need to pass email validation to be transfered, skipped otherwise
     * @param array $excludeFields       fields to be excluded from the transfer (e.g. unique identifiers)
     */
    private function transferData(
        $parent,
        $child,
        $save = true,
        $emailFields = ['customer_email', 'email'],
        $excludeFields = ['entity_id', 'address_id', 'reserved_order_id', 'remote_ip',
            'address_sales_rule_id', 'cart_fixed_rules', 'cached_items_all', 'customer_note']
    ) {
        foreach ($parent->getData() as $key => $value) {
            if (in_array($key, $excludeFields)) {
                continue;
            }
            if (in_array($key, $emailFields) && !$this->validateEmail($value)) {
                continue;
            }

            $child->setData($key, $value);
        }

        // Reset the calculated items of address, so address->getAllItems() can return up-to-date data.
        if ($child instanceof \Magento\Customer\Model\Address\AbstractAddress) {
            $child->unsetData("cached_items_all");
        }

        // Update the property Quote::KEY_ITEMS with proper items.
        if ($child instanceof \Magento\Quote\Model\Quote) {
            $child->setItems($child->getAllVisibleItems());
        }

        if ($save) {
            $child->save();
        }
    }

    /**
     * Clone quote data from source to destination
     *
     * @param Quote $source
     * @param Quote $destination
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function replicateQuoteData($source, $destination)
    {
        // Skip the replication if source and destination point to the same quote
        // E.g. delayed Save Order - immutable quote is cleared by cron and we use the parent instead
        if ($source->getId() === $destination->getId()) {
            return;
        }

        $destinationId = $destination->getId();
        $destinationActive = (bool)$destination->getIsActive();

        $destination->removeAllItems();

        foreach ($source->getAllVisibleItems() as $item) {
            $newItem = clone $item;
            $destination->addItem($newItem);
            if ($item->getHasChildren()) {
                foreach ($item->getChildren() as $child) {
                    $newChild = clone $child;
                    $newChild->setParentItem($newItem);
                    $destination->addItem($newChild);
                }
            }
        }

        $destination->getBillingAddress()->setShouldIgnoreValidation(true);
        $this->transferData($source->getBillingAddress(), $destination->getBillingAddress());

        $destination->getShippingAddress()->setShouldIgnoreValidation(true);
        $this->transferData($source->getShippingAddress(), $destination->getShippingAddress());

        $this->transferData($source, $destination, false);

        $destination->setId($destinationId);
        $destination->setIsActive($destinationActive);

        $this->quoteResourceSave($destination);

        // Third-party plugins can replicate required data.
        $this->eventsForThirdPartyModules->dispatchEvent("replicateQuoteData", $source, $destination);
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
     * @return Quote
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    protected function createImmutableQuote($quote)
    {
        // assign origin session type flag to the parent quote
        // to be replicated to the immutable quote with the other data
        if ($this->isBackendSession()) {
            $quote->setBoltCheckoutType(self::BOLT_CHECKOUT_TYPE_BACKOFFICE);
        }

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
        // the region field is required for US addresses
        if (isset($address[self::ADDRESS_COUNTRY_CODE_KEY]) &&
            $address[self::ADDRESS_COUNTRY_CODE_KEY] == self::ADDRESS_COUNTRY_CODE_US &&
            empty($address[self::ADDRESS_REGION_KEY])
        ) {
            return false;
        }
        return true;
    }

    /**
     * Bugsnag address data
     *
     * @param array $addressData
     */
    private function logAddressData($addressData)
    {
        $this->bugsnag->registerCallback(function ($report) use ($addressData) {
            $report->setMetaData([
                'ADDRESS_DATA' => $addressData
            ]);
        });
    }

    /**
     * Check the session type.
     *
     * @return bool
     */
    private function isBackendSession()
    {
        return $this->checkoutSession instanceof \Magento\Backend\Model\Session\Quote;
    }

    /**
     * Get additional attributes value by product SKU
     *
     * @param string $sku product SKU
     * @param string $storeId storeId
     * @param array $additionalAttributes array of attribute names
     *
     * @return array
     */
    private function getAdditionalAttributes($sku, $storeId, $additionalAttributes)
    {
        if (!$additionalAttributes) {
            return [];
        }
        try {
            $product = $this->productRepository->get($sku, false, $storeId);
        } catch (NoSuchEntityException $e) {
            $this->bugsnag->notifyException($e);
            return [];
        }
        $properties = [];
        foreach ($additionalAttributes as $attributeName) {
            if ($product->getData($attributeName)) {
                $attributeValue = (string) $this->eventsForThirdPartyModules->runFilter(
                    'filterCartItemsAdditionalAttributeValue',
                    $product->getAttributeText($attributeName),
                    $sku,
                    $storeId,
                    $attributeName,
                    $product
                );
                $properties[]   = (object)[
                    'name'  => $attributeName,
                    'value' => $attributeValue,
                    'type'  => 'attribute',
                ];
            }
        }
        return $properties;
    }

    /**
     * Create cart data items array, given a quote
     * @param $quote
     * @param null $storeId
     * @param int $totalAmount
     * @param int $diff
     * @return array
     * @throws \Exception
     */
    public function getCartItems($quote, $storeId = null, $totalAmount = 0, $diff = 0)
    {
        $items = $quote->getAllVisibleItems();
        $currencyCode = $quote->getQuoteCurrencyCode();

        list($products, $totalAmount, $diff) = $this->getCartItemsFromItems($items, false, $currencyCode, $storeId, $totalAmount, $diff);

        // getTotals is only available on a quote
        $total = $quote->getTotals();
        if (isset($total['giftwrapping'])) {
            $giftWrapping = $total['giftwrapping'];
            if ($gwId = $giftWrapping->getGwId()) {
                $product = $this->getItemDataFromGiftWrappingId($gwId, $currencyCode, $giftWrapping);
                $totalAmount += $product['total_amount'];
                $products[] = $product;
            }

            if ($gwItemIds = $giftWrapping->getGwItemIds()) {
                foreach ($gwItemIds as $gwItemId) {
                    $product = $this->getItemDataFromGiftWrappingId($gwItemId['gw_id'], $currencyCode, $giftWrapping);
                    $totalAmount += $product['total_amount'];
                    $products[] = $product;
                }
            }
        }

        return $this->eventsForThirdPartyModules->runFilter(
            'filterCartItems',
            [$products, $totalAmount, $diff],
            $quote,
            $storeId
        );
    }

    /**
     * @param $gwId
     * @param $currencyCode
     * @param $giftWrapping
     * @return array
     * @throws \Exception
     */
    public function getItemDataFromGiftWrappingId($gwId, $currencyCode, $giftWrapping) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $giftWrappingModel = $objectManager->create('Magento\GiftWrapping\Model\Wrapping')->load($gwId);
        $price = $giftWrappingModel->getBasePrice();
        $product = [];
        $product['reference']    = $gwId;
        $product['name']         = $giftWrapping->getTitle()->getText().' ['.$giftWrappingModel->getDesign().']';
        $product['total_amount'] = CurrencyUtils::toMinor($price, $currencyCode);
        $product['unit_price']   = CurrencyUtils::toMinor($price, $currencyCode);
        $product['quantity']     = 1;
        $product['sku']          = trim($giftWrapping->getCode());
        $product['type']         = self::ITEM_TYPE_PHYSICAL;

        if ($giftWrappingModel->getImageUrl()){
            $product['image_url']    = $giftWrappingModel->getImageUrl();
        }

        return $product;
    }/**


    /**
     * Create cart data items array, given an order
     * @param $order
     * @param null $storeId
     * @param int $totalAmount
     * @param int $diff
     * @return array
     * @throws \Exception
     */
    public function getCartItemsForOrder($order, $storeId = null, $totalAmount = 0, $diff = 0)
    {
        $storeId = $order->getStoreId();
        $currencyCode = $order->getOrderCurrencyCode();
        $items = $order->getAllVisibleItems();

        return $this->getCartItemsFromItems($items, true, $currencyCode, $storeId, $totalAmount, $diff);
    }

    /**
     * Create cart data items array, given an array of items
     * fetched from either a quote or an order
     * @param $items
     * @param $isOrder
     * @param $currencyCode
     * @param null $storeId
     * @param int $totalAmount
     * @param int $diff
     * @return array
     * @throws \Exception
     */
    protected function getCartItemsFromItems($items, $isOrder, $currencyCode, $storeId = null, $totalAmount = 0, $diff = 0)
    {
        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        // The "appEmulation" is necessary for getting correct image url from an API call.
        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        $this->appEmulation->startEnvironmentEmulation(
            $storeId,
            \Magento\Framework\App\Area::AREA_FRONTEND,
            true
        );
        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        $imageHelper = $this->imageHelperFactory->create();

        $additionalAttributes = $this->configHelper->getProductAttributesList($storeId);

        $products = array_map(
            function ($item) use ($imageHelper, &$totalAmount, &$diff, $isOrder, $storeId, $currencyCode, $additionalAttributes) {
                $_product = $item->getProduct();
                if (!$_product) {
                    return [];
                }
                $product = [];
                if ($isOrder) {
                    $unitPrice = $item->getPrice();
                    $quantity = round($item->getQtyOrdered());
                } else {
                    $unitPrice = $item->getCalculationPrice();
                    $quantity = round($item->getQty());
                }

                $itemTotalAmount = $unitPrice * $quantity;
                $roundedTotalAmount = CurrencyUtils::toMinor($unitPrice, $currencyCode) * $quantity;

                // Aggregate eventual total differences if prices are stored with more than 2 decimal places
                $diff += CurrencyUtils::toMinorWithoutRounding($itemTotalAmount, $currencyCode) -$roundedTotalAmount;

                // Aggregate cart total
                $totalAmount += $roundedTotalAmount;

                ////////////////////////////////////
                // Load item product object
                ////////////////////////////////////
                $customizableOptions = null;
                $itemSku = trim($item->getSku());
                $itemReference = $item->getProductId();
                $itemName = $item->getName();

                //By default this feature switch is enabled.
                if ($this->deciderHelper->isCustomizableOptionsSupport()) {
                    try {
                        $customizableOptions = $this->getProductCustomizableOptions($item);
                        if ($customizableOptions) {
                            $itemSku = $this->getProductActualSkuByCustomizableOptions($itemSku, $customizableOptions);
                        }
                    } catch (\Exception $e) {
                        $this->bugsnag->registerCallback(function ($report) use ($item) {
                            $report->setMetaData([
                                'product_id' => $item->getProductId(),
                                'product_name' => $item->getName(),
                                'product_sku' => $item->getSku()
                            ]);
                        });

                        $this->bugsnag->notifyError('Could not retrieve product customizable options', $e->getMessage());
                        $customizableOptions = null;
                    }
                }

                $product['merchant_product_id'] = $item->getProductId();
                try {
                    if ($customizableOptions || $item->getProductType() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                        $_product = $this->productRepository->get($itemSku);
                        $itemReference = $_product->getId();
                        $itemName = $_product->getName();
                        $product['merchant_variant_id'] = $_product->getID();
                    }
                } catch (\Exception $e) {
                    $this->bugsnag->registerCallback(function ($report) use ($item) {
                        $report->setMetaData([
                            'product_id' => $item->getProductId(),
                            'product_name' => $item->getName(),
                            'product_sku' => $item->getSku()
                        ]);
                    });
                    $this->bugsnag->notifyError('Could not retrieve product by sku', $e->getMessage());
                }

                $product['reference']    = $itemReference;
                $product['name']         = $itemName;
                $product['total_amount'] = $roundedTotalAmount;
                $product['unit_price']   = CurrencyUtils::toMinor($unitPrice, $currencyCode);
                $product['quantity']     = $quantity;
                $product['sku']          = $this->getSkuFromQuoteItem($item);

                if (!$this->deciderHelper->isMSRPPriceDisabled() && $this->msrpHelper->canApplyMsrp($_product) && $_product->getMsrp() !== null) {
                    $product['msrp']     = CurrencyUtils::toMinor($_product->getMsrp(), $currencyCode);
                }

                // In current Bolt checkout flow, the shipping and tax endpoint is not called for virtual carts,
                // It means we don't support taxes for virtual product and should handle all products as physical
                // TODO: Remove the feature switch check when issue will be solved https://boltpay.atlassian.net/browse/DC-181
                if ($item->getIsVirtual() && !$this->deciderHelper->handleVirtualProductsAsPhysical()) {
                    $product['type'] = self::ITEM_TYPE_DIGITAL;
                } else {
                    $product['type'] = self::ITEM_TYPE_PHYSICAL;
                }

                ///////////////////////////////////////////
                // Get item attributes / product properties
                ///////////////////////////////////////////
                $item_options = $_product->getTypeInstance()->getOrderOptions($_product);
                $properties = [];
                if (isset($item_options['attributes_info'])) {
                    foreach ($item_options['attributes_info'] as $attribute_info) {
                        // Convert attribute to string if it's a boolean before sending to the Bolt API
                        $attributeValue = is_bool($attribute_info['value']) ? var_export($attribute_info['value'], true) : $attribute_info['value'];
                        $attributeLabel = $attribute_info['label'];
                        $properties[] = (object) [
                            'name' => $attributeLabel,
                            'value' => $attributeValue
                        ];
                        if (strcasecmp($attributeLabel, 'color') == 0) {
                            $product['color'] = $attributeValue;
                        }

                        if (strcasecmp($attributeLabel, 'size') == 0) {
                            $product['size'] = $attributeValue;
                        }
                    }
                }

                //By default this feature switch is enabled.
                if ($this->deciderHelper->isCustomizableOptionsSupport() && !empty($customizableOptions)) {
                    foreach ($customizableOptions as $customizableOption) {
                        $properties[] = (object) [
                            'name' => $customizableOption['title'],
                            'value' => $customizableOption['value']
                        ];
                    }
                }

                foreach ($this->getAdditionalAttributes($itemSku, $storeId, $additionalAttributes) as $attribute) {
                    $properties[] = $attribute;
                }

                $properties = $this->eventsForThirdPartyModules->runFilter(
                    'filterCartItemsProperties',
                    $properties,
                    $item
                );

                if ($properties) {
                    $product['properties'] = $properties;
                }
                ////////////////////////////////////
                // Get product description and image
                ////////////////////////////////////
                $product['description'] = str_replace(["\r\n", "\n", "\r"], ' ', strip_tags((string)$_product->getDescription()));
                $variantProductToGetImage = $_product;

                // This will override the $_product with the variant product to get the variant image rather than the main product image.
                try {
                    $variantProductToGetImage = $this->getProductToGetImageForQuoteItem($item);
                } catch (\Exception $e) {
                    $this->bugsnag->registerCallback(function ($report) use ($product) {
                        $report->setMetaData([
                            'ITEM' => $product
                        ]);
                    });
                    $this->bugsnag->notifyError('Could not retrieve product', "ProductId: {$product['reference']}, SKU: {$product['sku']}");
                }
                try {
                    $productImageUrl = $imageHelper->init($variantProductToGetImage, 'product_small_image')->getUrl();
                } catch (\Exception $e) {
                    try {
                        $productImageUrl = $imageHelper->init($variantProductToGetImage, 'product_base_image')->getUrl();
                    } catch (\Exception $e) {
                        $this->bugsnag->registerCallback(function ($report) use ($product) {
                            $report->setMetaData([
                                'ITEM' => $product
                            ]);
                        });
                        $this->bugsnag->notifyError('Item image missing', "SKU: {$product['sku']}");
                    }
                }
                if (isset($productImageUrl) && $productImageUrl) {
                    $product['image_url'] = ltrim($productImageUrl, '/');
                }
                ////////////////////////////////////
                return  $product;
            },
            $items
        );

        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        $this->appEmulation->stopEnvironmentEmulation();
        /////////////////////////////////////////////////////////////////////////////////////////////////////////

        return [$products, $totalAmount, $diff];
    }

    /**
     * Return the selected customizable options of quote item.
     *
     * @param Quote/Item $item
     *
     * @return array
     */
    public function getProductCustomizableOptions($item)
    {
        $optionIds = $item->getOptionByCode('option_ids');
        if (!$optionIds) {
            return [];
        }

        $customizableOptions = [];
        $product = $item->getProduct();
        foreach (explode(',', ($optionIds->getValue() ?: '')) as $optionId) {
            $option = $product->getOptionById($optionId);
            if ($option) {
                $confItemOption = $product->getCustomOption(\Magento\Catalog\Model\Product\Type\AbstractType::OPTION_PREFIX . $optionId);
                $itemOption = $item->getOptionByCode(\Magento\Catalog\Model\Product\Type\AbstractType::OPTION_PREFIX . $option->getId());
                $group = $option->groupFactory($option->getType())
                    ->setOption($option)
                    ->setConfigurationItem($item)
                    ->setConfigurationItemOption($itemOption)
                    ->setListener(new \Magento\Framework\DataObject());
                $optionSku = $group->getOptionSku($confItemOption->getValue(), self::MAGENTO_SKU_DELIMITER);
                $customizableOptions[] = [
                    'title' => $option->getTitle(),
                    'value' => $group->getFormattedOptionValue($confItemOption->getValue()),
                    'sku'   => $optionSku ?: ''
                ];
            }
        }

        return $customizableOptions;
    }

    /**
     * If the product has customizable options, the sku of selected option would be appended to product sku for quote item,
     * this function is to remove the sku of options and return actual sku of product.
     *
     * @param string $sku
     * @param array $customizableOptions
     *
     * @return string
     */
    public function getProductActualSkuByCustomizableOptions($sku, $customizableOptions)
    {
        foreach ($customizableOptions as $customizableOption) {
            if ($customizableOption['sku']) {
                $sku = str_replace(self::MAGENTO_SKU_DELIMITER.$customizableOption['sku'], '', $sku);
            }
        }

        return $sku;
    }

    /**
     * @param $item
     * @return \Magento\Catalog\Model\Product
     */
    public function getProductToGetImageForQuoteItem($item)
    {
        $productType = $item->getProductType();
        if ($productType == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            return $this->getProductToGetImageForConfigurableItem($item);
        }

        if ($productType == \Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE) {
            return $this->getProductToGetImageForGroupedItem($item);
        }

        return $item->getProduct();
    }

    /**
     * @see \Magento\ConfigurableProduct\Block\Cart\Item\Renderer\Configurable::getProductForThumbnail logic
     * (Magento version is less than 2.2.7)
     *
     * @see \Magento\ConfigurableProduct\Model\Product\Configuration\Item\ItemProductResolver::getProductForThumbnail logic
     * (Magento version is greater or equal to 2.2.7)
     *
     * @param $item
     * @return \Magento\Catalog\Model\Product
     */
    private function getProductToGetImageForConfigurableItem($item)
    {
        $parentProduct = $item->getProduct();
        /** @var \Magento\Quote\Model\Quote\Item\Option $option */
        $option = $item->getOptionByCode('simple_product');
        $childProduct = $option ? $option->getProduct() : $parentProduct;
        $configValue = $this->configHelper->getScopeConfig()->getValue(
            'checkout/cart/configurable_product_image',
            ScopeInterface::SCOPE_STORE
        );

        /**
         * Show parent product thumbnail if it must be always shown according to the related setting in system config
         * or if child thumbnail is not available
         */
        if ($configValue == ThumbnailSource::OPTION_USE_PARENT_IMAGE ||
            !($childProduct && $childProduct->getThumbnail() && $childProduct->getThumbnail() != 'no_selection')
        ) {
            return $parentProduct;
        }

        return $childProduct;
    }

    /**
     * @see \Magento\GroupedProduct\Block\Cart\Item\Renderer\Grouped::getProductForThumbnail logic
     * (Magento version is less than 2.2.7)
     *
     * @see \Magento\GroupedProduct\Model\Product\Configuration\Item\ItemProductResolver::getProductForThumbnail logic
     * (Magento version is greater or equal to 2.2.7)
     *
     * @param $item
     * @return \Magento\Catalog\Model\Product
     */
    private function getProductToGetImageForGroupedItem($item)
    {
        /** @var \Magento\Quote\Model\Quote\Item\Option $option */
        $option = $item->getOptionByCode('product_type');
        $childProduct = $item->getProduct();
        $groupedProduct = $option ? $option->getProduct() : $childProduct;

        $configValue = $this->configHelper->getScopeConfig()->getValue(
            'checkout/cart/grouped_product_image',
            ScopeInterface::SCOPE_STORE
        );

        /**
         * Show grouped product thumbnail if it must be always shown according to the related setting in system config
         * or if child product thumbnail is not available
         */
        if ($configValue == ThumbnailSource::OPTION_USE_PARENT_IMAGE ||
            !($childProduct && $childProduct->getThumbnail() && $childProduct->getThumbnail() != 'no_selection')
        ) {
            return $groupedProduct;
        }

        return $childProduct;
    }

    /**
     * Return user group id for logged in users and "0" for guest users
     *
     */
    private function getUserGroupId()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return "0";
        }
        return $this->customerSession->getCustomer()->getGroupId();
    }

    /**
     * Get the address for totals calculation based on the quote physical / virtual type
     *
     * @param Quote $quote
     * @return QuoteAddress
     */
    protected function getCalculationAddress($quote)
    {
        return $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
    }

    /**
     * Get cart data.
     * The reference of total methods: dev/tests/api-functional/testsuite/Magento/Quote/Api/CartTotalRepositoryTest.php
     *
     * @param bool        $paymentOnly       flag that represents the type of checkout
     * @param string|null $placeOrderPayload additional data collected from the (one page checkout) page,
     *                                       i.e. billing address to be saved with the order
     * @param Quote|null  $immutableQuote    If passed do not create new clone, get data existing one data.
     *                                       discount validation, bugsnag report
     *
     * @return array
     * @throws \Exception
     */
    public function getCartData($paymentOnly, $placeOrderPayload = null, $immutableQuote = null)
    {

        // If the immutable quote is passed (i.e. discount code validation, bugsnag report generation)
        // load the parent quote, otherwise load the session quote
        /** @var Quote $quote */
        $quote = ($immutableQuote && $immutableQuote->getBoltParentQuoteId() != $immutableQuote->getId()) ?
            $this->getQuoteById($immutableQuote->getBoltParentQuoteId()) :
            $this->checkoutSession->getQuote();

        // The cart creation sometimes gets called when no (parent) quote exists:
        // 1. From frontend event handler: It is store specific, for example a minicart with 0 items.
        // 2. From backend, with $immutableQuote passed as parameter, parent already inactive / deleted:
        //    a) discount code validation
        //    b) bugsnag report generation
        // In case #1 the empty cart is returned
        // In case #2 the cart generation continues for the cloned quote
        if (!$immutableQuote && (!$quote || !$quote->getAllVisibleItems())) {
            return [];
        }

        if ($this->deciderHelper->isPreventBoltCartForQuotesWithError() && $quote && !empty($quote->getHasError())) {
            $this->bugsnag->notifyError(
                'Bolt cart prevented for quote with error',
                '',
                /**
                 * @param \Bugsnag\Report $report
                 */
                function ($report) use ($quote) {
                    $report->addMetaData(
                        [
                            'quote_error_messages' => array_map(
                                /**
                                 * @param \Magento\Framework\Message\Error $error
                                 *
                                 * @return string
                                 */
                                function ($error) {
                                    return $error->toString();
                                },
                                $quote->getErrors()
                            )
                        ]
                    );
                }
            );
            return [];
        }

        ////////////////////////////////////////////////////////
        // CLONE THE QUOTE and quote billing / shipping  address
        // if immutable quote is not passed to the method - the
        // cart data is being created for sending to Bolt create
        // order API, otherwise skip this step
        ////////////////////////////////////////////////////////
        if ($this->deciderHelper->isAPIDrivenIntegrationEnabled()) {
            $quote->setBoltParentQuoteId($quote->getId());
            if ($this->isBackendSession() && $quote->getBoltCheckoutType() != self::BOLT_CHECKOUT_TYPE_BACKOFFICE) {
                $quote->setBoltCheckoutType(self::BOLT_CHECKOUT_TYPE_BACKOFFICE);
                $quote->save();
            }
        }
        elseif (!$immutableQuote) {
            $immutableQuote = $this->createImmutableQuote($quote);
        }

        ////////////////////////////////////////////////////////

        // Get array of all items that can be display directly
        if ($immutableQuote) {
            $items = $immutableQuote->getAllVisibleItems();
        } else {
            // hereinafter null immutable quote means we are in new API driven flow
            $items = $quote->getAllVisibleItems();
        }

        if (!$items) {
            // This is the case when customer empties the cart.
            return [];
        }

        if ($immutableQuote) {
            $this->setLastImmutableQuote($immutableQuote);
            if ($this->isBackendSession() && !$immutableQuote->isVirtual()) {
                $address = $this->getCalculationAddress($immutableQuote);
                $address->setCollectShippingRates(true);
            }
            $immutableQuote->collectTotals();
        } else {
            if ($this->deciderHelper->isRecalculateTotalForAPIDrivenIntegration()) {
                // we did not change the quote there is no reason to spend time
                // recalculating total
                // but we have this settings just in case
                $quote->collectTotals();
            }
        }

        $cart = $this->buildCartFromQuote($quote, $immutableQuote, $items, $placeOrderPayload, $paymentOnly);
        if ($cart == []) {
            // empty cart implies there was an error
            return $cart;
        }

        if ($immutableQuote) {
            $cart['order_reference'] = $immutableQuote->getBoltParentQuoteId();
            $this->sessionHelper->cacheFormKey($immutableQuote);
        } else {
            $cart['order_reference'] = $quote->getId();
        }

        if ($this->deciderHelper->isIncludeUserGroupIntoCart()) {
            $cart['metadata']['user_group_id'] = $this->getUserGroupId();
        }

        return $cart;
    }

    /**
     * Build cart data from quote.
     *
     * @param Quote  $parentQuote
     * @param Quote  $immutableQuote
     * @param array  $items
     * @param bool   $paymentOnly           flag that represents the type of checkout
     * @param string $placeOrderPayload     additional data collected from the (one page checkout) page,
     * @param bool   $requireBillingAddress require that the billing address is set
     *
     * @return array
     * @throws \Exception
     */
    public function buildCartFromQuote($parentQuote, $immutableQuote, $items, $placeOrderPayload, $paymentOnly, $requireBillingAddress = true)
    {
        // work with parent (native) quote for API driven flow
        // and with immutable quote for legacy flow
        $quote = $immutableQuote ?: $parentQuote;
        $cart = [];

        $billingAddress  = $quote->getBillingAddress();
        $shippingAddress = $quote->getShippingAddress();

        //Use display_id to hold and transmit, all the way back and forth, reserved order id
        $cart['display_id'] = '';

        if ($immutableQuote) {
            $cart['metadata']['immutable_quote_id'] = $immutableQuote->getId();
        }

        // Transmit session ID via cart metadata, making it available for session emulation in API calls
        if ($this->deciderHelper->isAddSessionIdToCartMetadata()) {
            $cart['metadata'][SessionHelper::ENCRYPTED_SESSION_ID_KEY] = $this->encryptMetadataValue(
                $this->checkoutSession->getSessionId()
            );
        }

        $sessionData = $this->eventsForThirdPartyModules->runFilter('collectSessionData', [], $parentQuote, $immutableQuote);
        if (!empty($sessionData)) {
            $cart['metadata'][Session::ENCRYPTED_SESSION_DATA_KEY] = $this->encryptMetadataValue(
                json_encode($sessionData)
            );
        }

        //store order id from session to add support for order edit
        if ($this->checkoutSession->getOrderId()) {
            $cart['metadata']['original_order_entity_id'] = $this->checkoutSession->getOrderId();
        }

        //Currency
        $currencyCode = $quote->getQuoteCurrencyCode();
        $cart['currency'] = $currencyCode;

        list ($cart['items'], $totalAmount, $diff) = $this->getCartItems($quote, $quote->getStoreId());

        // Email field is mandatory for saving the address.
        // For back-office orders (payment only) we need to get it from the store.
        // Trying several possible places.
        $email = $billingAddress->getEmail()
            ?: $shippingAddress->getEmail()
            ?: $this->customerSession->getCustomer()->getEmail()
            ?: $quote->getCustomerEmail();

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

            $billAddress          = $placeOrderPayload->billingAddress ?? null;
            $billingStreetAddress = $billAddress->street ?? [];

            if ($billAddress) {
                $cartBillingAddress = [
                    'first_name'      => $billAddress->firstname ?? null,
                    'last_name'       => $billAddress->lastname ?? null,
                    'company'         => $billAddress->company ?? null,
                    'phone'           => $billAddress->telephone ?? null,
                    'street_address1' => isset($billingStreetAddress[0]) ? (string)$billingStreetAddress[0] : null,
                    'street_address2' => isset($billingStreetAddress[1]) ? (string)$billingStreetAddress[1] : null,
                    'locality'        => $billAddress->city ?? null,
                    'region'          => $billAddress->region ?? null,
                    'postal_code'     => $billAddress->postcode ?? null,
                    'country_code'    => $billAddress->countryId ?? null,
                    'email'           => $placeOrderPayload->email ?? $email
                ];
            }

            // For payment only we use email from payload if we don't have any email in quote
            if (!$email && isset($placeOrderPayload->email) && $placeOrderPayload->email) {
                $email = $placeOrderPayload->email;
            }
        }

        // Billing address is not mandatory set it only if it is complete
        if ($this->isAddressComplete($cartBillingAddress)) {
            $cart['billing_address'] = $cartBillingAddress;
        }

        $address = $this->getCalculationAddress($quote);

        // payment only checkout, include shipments, tax and grand total
        if ($paymentOnly) {
            if ($quote->isVirtual()) {
                if (!empty($cart['billing_address'])) {
                    $this->collectAddressTotals($quote, $address);
                    $address->save();
                } elseif ($requireBillingAddress) {
                    $this->logAddressData($cartBillingAddress);
                    $this->bugsnag->notifyError(
                        'Order create error',
                        'Billing address data insufficient.'
                    );

                    throw new LocalizedException(
                        __('Billing address is missing. Please input all required fields in billing address form and try again')
                    );
                }
            } else {
                // assign parent shipping method to clone
                if (!$address->getShippingMethod() && $parentQuote) {
                    $address->setShippingMethod($parentQuote->getShippingAddress()->getShippingMethod());
                }

                if (!$address->getShippingMethod()) {
                    $this->bugsnag->notifyError(
                        'Order create error',
                        'Shipping method not set.'
                    );

                    throw new LocalizedException(
                        __('Shipping method is missing. Please select shipping method and try again')
                    );
                }

                if (!$this->isBackendSession()) {
                    $address->setCollectShippingRates(true);
                    $this->collectAddressTotals($quote, $address);
                    $address->save();
                }

                if (
                    $this->isBackendSession()
                    && $requireBillingAddress
                    && !$this->isAddressComplete($cartBillingAddress)
                ) {
                    $this->logAddressData($cartBillingAddress);
                    $this->bugsnag->notifyError(
                        'Order create error',
                        'Billing address data insufficient.'
                    );

                    throw new LocalizedException(
                        __('Billing address is missing. Please input all required fields in billing address form and try again')
                    );
                }

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
                    $shippingService = $address->getShippingDescription();
                    $shippingDiscountAmount = $this->eventsForThirdPartyModules->runFilter("collectShippingDiscounts", $address->getShippingDiscountAmount(), $quote, $address);
                    if ($shippingDiscountAmount >= DiscountHelper::MIN_NONZERO_VALUE && !$this->ignoreAdjustingShippingAmount($quote)) {
                        $cost = $cost - $shippingDiscountAmount;
                        $rounded_cost = CurrencyUtils::toMinor($cost, $currencyCode);
                        if ($rounded_cost == 0) {
                            $shippingService .= ' [free&nbsp;shipping&nbsp;discount]';
                        } else {
                            $shippingDiscountAmount = $this->priceHelper->currency($shippingDiscountAmount, true, false);
                            $shippingService .= " [$shippingDiscountAmount" . "&nbsp;discount]";
                        }
                    } else {
                        $rounded_cost = CurrencyUtils::toMinor($cost, $currencyCode);
                    }
                    $diff += CurrencyUtils::toMinorWithoutRounding($cost, $currencyCode) - $rounded_cost;
                    $totalAmount += $rounded_cost;

                    $cart['shipments'] = [[
                        'cost' => $rounded_cost,
                        'tax_amount' => CurrencyUtils::toMinor($address->getShippingTaxAmount(), $currencyCode),
                        'shipping_address' => $shipAddress,
                        'service' => $shippingService,
                        'reference' => $shippingAddress->getShippingMethod(),
                    ]];
                } else {
                    $this->logAddressData($shipAddress);
                    $this->bugsnag->notifyError(
                        'Order create error',
                        'Shipping address data insufficient.'
                    );

                    throw new LocalizedException(
                        __('Shipping address is missing. Please input all required fields in shipping address form and try again')
                    );
                }
            }

            $storeTaxAmount   = $address->getTaxAmount();
            $roundedTaxAmount = CurrencyUtils::toMinor($storeTaxAmount, $currencyCode);

            $diff += CurrencyUtils::toMinorWithoutRounding($storeTaxAmount, $currencyCode) - $roundedTaxAmount;

            $taxAmount    = $roundedTaxAmount;
            $totalAmount += $roundedTaxAmount;
        } else {
            // multi-step checkout, subtotal with discounts, no shipping, no tax
            $taxAmount = 0;
        }

        // add discount data
        list ($discounts, $totalAmount, $diff) = $this->collectDiscounts(
            $totalAmount,
            $diff,
            $paymentOnly,
            $quote
        );
        $cart['discounts'] = $discounts;
        /////////////////////////////////////////////////////////////////////////////////
        // Add fixed amount type to all discounts if total amount is negative
        // and set total to 0. Otherwise add calculated diff to cart total.
        /////////////////////////////////////////////////////////////////////////////////
        if ($totalAmount < 0) {
            $totalAmount = 0;
            foreach ($cart['discounts'] as &$discount) {
                $discount['type']          = 'fixed_amount'; // For v1/merchant/order
                $discount['discount_type'] = 'fixed_amount'; // For v1/discounts.code.apply and v2/cart.update
            }
        } else {
            // add the diff to first item total to pass bolt order create check
            $cart['items'][0]['total_amount'] += round($diff);
            $totalAmount += round($diff);
        }
        /////////////////////////////////////////////////////////////////////////////////

        $cart['total_amount'] = $totalAmount;
        $cart['tax_amount']   = $taxAmount;

        if (abs((float)$diff) >= $this->threshold) {
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

        return $this->eventsForThirdPartyModules->runFilter('filterCart', $cart, $quote);
    }

    /**
     * Email validator
     *
     * @param string $email
     * @return bool
     * @throws \Magento\Framework\Validator\ValidateException
     */
    public function validateEmail($email)
    {
        $emailClass = version_compare(
            $this->configHelper->getStoreVersion(),
            '2.2.0',
            '<'
        ) ? 'EmailAddress' : \Magento\Framework\Validator\EmailAddress::class;

        if (class_exists('\Magento\Framework\Validator\ValidatorChain')) {
            return \Magento\Framework\Validator\ValidatorChain::is($email, $emailClass);
        } else {
            return \Zend_Validate::is($email, $emailClass);
        }
    }

    /**
     * Get website_id from the session quote store
     *
     * @return string|int|null
     */
    protected function getWebsiteId()
    {
        return $this->checkoutSession->getQuote()->getStore()->getWebsiteId();
    }

    /**
     * Collect various type of discounts and add them to cart
     *
     * @param int $totalAmount
     * @param float $diff
     * @param AddressTotal[] $totals
     * @param bool $paymentOnly
     * @return array
     * @throws NoSuchEntityException
     */
    public function collectDiscounts(
        $totalAmount,
        $diff,
        $paymentOnly,
        $quote
    ) {
        $currencyCode = $quote->getQuoteCurrencyCode();
        $parentQuote = $this->getQuoteById($quote->getBoltParentQuoteId());
        $address = $this->getCalculationAddress($quote);
        /** @var AddressTotal[] */
        $totals = $quote->getTotals();
        $this->logHelper->addInfoLog('### CartTotals: ' . json_encode(array_keys($totals)));
        $discounts = [];
        /////////////////////////////////////////////////////////////////////////////////
        // Process store integral discounts and coupons.
        // For some types of applied coupon, the discount amount could be zero before
        // selecting specific shipping option, so the conditional statement should also
        // check if getCouponCode is not null
        /////////////////////////////////////////////////////////////////////////////////
        if (($amount = abs((float)$address->getDiscountAmount())) || $quote->getCouponCode()) {
            $amount = CurrencyUtils::toMinor($amount, $currencyCode);
            $ruleDiscountDetails = $this->getSaleRuleDiscounts($quote);
            list($ruleDiscountDetails, $discounts, $totalAmount) = $this->eventsForThirdPartyModules->runFilter('filterQuoteDiscountDetails', [$ruleDiscountDetails, $discounts, $totalAmount], $quote);
            foreach ($ruleDiscountDetails as $salesruleId => $ruleDiscountAmount) {
                $rule = $this->ruleRepository->getById($salesruleId);
                $roundedAmount = CurrencyUtils::toMinor($ruleDiscountAmount, $currencyCode);
                $discountType = $this->discountHelper->getBoltDiscountType($rule);
                switch ($rule->getCouponType()) {
                    case RuleInterface::COUPON_TYPE_SPECIFIC_COUPON:
                    case RuleInterface::COUPON_TYPE_AUTO:
                        $couponCode = $quote->getCouponCode();
                        $ruleDescription = $rule->getDescription();
                        $description = $ruleDescription !== '' ? $ruleDescription : 'Discount (' . $couponCode . ')';
                        $discounts[] = [
                            'rule_id'           => $rule->getRuleId(),
                            'description'       => $description,
                            'amount'            => $roundedAmount,
                            'reference'         => $couponCode,
                            'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_COUPON,
                            'discount_type'     => $discountType, // For v1/discounts.code.apply and v2/cart.update
                            'type'              => $discountType, // For v1/merchant/order
                        ];
                        $this->logEmptyDiscountCode($couponCode, $description);

                        break;
                    case RuleInterface::COUPON_TYPE_NO_COUPON:
                    default:
                        $description = $rule->getDescription();
                        if (!$description && $this->deciderHelper->isUseRuleNameIfDescriptionEmpty()) {
                            $description = $rule->getName();
                        }
                        $discounts[] = [
                            'rule_id'           => $rule->getRuleId(),
                            'description'       => trim(__('Discount ') . $description),
                            'amount'            => $roundedAmount,
                            'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_AUTO_PROMO,
                            'discount_type'     => $discountType, // For v1/discounts.code.apply and v2/cart.update
                            'type'              => $discountType, // For v1/merchant/order
                        ];

                        break;
                }
                $amount -= $roundedAmount;
                $totalAmount -= $roundedAmount;
            }
            if ($this->deciderHelper->isAPIDrivenIntegrationEnabled()
                && $this->deciderHelper->isSkipCartDiscountTotalMismatch()
                && $amount) {
                uasort($discounts, function ($a, $b) {
                    return $b['amount'] <=> $a['amount'];
                });
                $firstKey = array_key_first($discounts);
                $discounts[$firstKey]['amount'] += $amount;
                ksort($discounts);
                $totalAmount -= $amount;
                $this->bugsnag->notifyError('Cart discount total mismatch', "Skip cart discount total mismatch. Actual Mismatch {$amount}. Discounts details: (before rounding)" . var_export($ruleDiscountDetails, true) . " (after rounding) " .var_export($discounts, true));
            }
        }
        /////////////////////////////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////////////
        // Process Store Credit
        /////////////////////////////////////////////////////////////////////////////////
        if ($quote->getUseCustomerBalance()) {
            if ($paymentOnly && $amount = abs((float)$quote->getCustomerBalanceAmountUsed())) {
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);

                $discounts[] = [
                    'description'       => 'Store Credit',
                    'amount'            => $roundedAmount,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    'discount_type'     => DiscountHelper::BOLT_DISCOUNT_TYPE_FIXED, // For v1/discounts.code.apply and v2/cart.update
                    'type'              => DiscountHelper::BOLT_DISCOUNT_TYPE_FIXED, // For v1/merchant/order
                ];

                $diff -= CurrencyUtils::toMinorWithoutRounding($amount, $currencyCode) - $roundedAmount;
                $totalAmount -= $roundedAmount;
            } else {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $balanceModel = $objectManager->create('Magento\CustomerBalance\Model\Balance');

                $balanceModel->setCustomer(
                    $this->customerSession->getCustomer()
                )->setWebsiteId(
                    $this->getWebsiteId()
                );
                $balanceModel->loadByCustomer();

                if ($amount = abs((float)$balanceModel->getAmount())) {
                    $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);

                    $discounts[] = [
                        'description'       => 'Store Credit',
                        'amount'            => $roundedAmount,
                        'reference'         => \Bolt\Boltpay\ThirdPartyModules\Magento\CustomerBalance::STORE_CREDIT,
                        'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                        'discount_type'     => Discount::BOLT_DISCOUNT_TYPE_FIXED, // For v1/discounts.code.apply and v2/cart.update
                        'type'              => Discount::BOLT_DISCOUNT_TYPE_FIXED, // For v1/merchant/order
                    ];

                    $diff -= CurrencyUtils::toMinorWithoutRounding($amount, $currencyCode) - $roundedAmount;
                    $totalAmount -= $roundedAmount;
                }
            }
        }

        /////////////////////////////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////////////
        // Process Reward Points
        /////////////////////////////////////////////////////////////////////////////////
        if ($quote->getUseRewardPoints()) {
            if ($paymentOnly && $amount = abs((float)$quote->getRewardCurrencyAmount())) {
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);

                $discounts[] = [
                    'description'       => 'Reward Points',
                    'amount'            => $roundedAmount,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    'discount_type'     => Discount::BOLT_DISCOUNT_TYPE_FIXED, // For v1/discounts.code.apply and v2/cart.update
                    'type'              => Discount::BOLT_DISCOUNT_TYPE_FIXED, // For v1/merchant/order
                ];

                $diff -= CurrencyUtils::toMinorWithoutRounding($amount, $currencyCode) - $roundedAmount;
                $totalAmount -= $roundedAmount;
            } else {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $rewardModel = $objectManager->create('Magento\Reward\Model\Reward');

                $rewardModel->setCustomer(
                    $this->customerSession->getCustomer()
                )->setWebsiteId(
                    $this->getWebsiteId()
                );
                $rewardModel->loadByCustomer();

                if ($amount = abs((float)$rewardModel->getCurrencyAmount())) {
                    $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);

                    $discounts[] = [
                        'description'       => 'Reward Points',
                        'amount'            => $roundedAmount,
                        'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                        'discount_type'     => DiscountHelper::BOLT_DISCOUNT_TYPE_FIXED, // For v1/discounts.code.apply and v2/cart.update
                        'type'              => DiscountHelper::BOLT_DISCOUNT_TYPE_FIXED, // For v1/merchant/order
                    ];

                    $diff -= CurrencyUtils::toMinorWithoutRounding($amount, $currencyCode) - $roundedAmount;
                    $totalAmount -= $roundedAmount;
                }
            }
        }
        /////////////////////////////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////////////
        // Process other discounts, stored in totals array
        /////////////////////////////////////////////////////////////////////////////////
        foreach ($this->discountTypes as $discount => $description) {
            $totalDiscount = $totals[$discount] ?? null;
            if ($totalDiscount && $amount = $totalDiscount->getValue()) {
                $roundedDiscountAmount = 0;
                $discountAmount = 0;

                if ($discount == Discount::UNIRGY_GIFT_CERT && $quote->getData('giftcert_code')) {
                    ///////////////////////////////////////////////////////////////////////////
                    /// Was added a proper Unirgy_Giftcert Amount to the discount.
                    /// The GiftCert accumulate correct balance only after each collectTotals.
                    ///  The Unirgy_Giftcert add the only discount which covers only product price.
                    ///  We should get the whole balance at first of the Giftcert.
                    ///////////////////////////////////////////////////////////////////////////
                    $gcCode = $quote->getData('giftcert_code');
                    $giftCertBalance = $this->discountHelper->getUnirgyGiftCertBalanceByCode($gcCode);
                    if ($giftCertBalance > 0) {
                        $amount = $giftCertBalance;
                    }
                    $discountAmount = abs((float)$amount);
                    $roundedDiscountAmount = CurrencyUtils::toMinor($discountAmount, $currencyCode);
                    $gcDescription = $description . $totalDiscount->getTitle();
                    $discountItem = [
                        'description'       => $gcDescription,
                        'amount'            => $roundedDiscountAmount,
                        'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_GIFTCARD,
                        'reference'         => $gcCode,
                        'discount_type'     => DiscountHelper::BOLT_DISCOUNT_TYPE_FIXED, // For v1/discounts.code.apply and v2/cart.update
                        'type'              => DiscountHelper::BOLT_DISCOUNT_TYPE_FIXED, // For v1/merchant/order
                    ];
                    $this->logEmptyDiscountCode($gcCode, $gcDescription);
                    $discounts[] = $discountItem;
                } else {
                    $discountAmount = abs((float)$amount);
                    $roundedDiscountAmount = CurrencyUtils::toMinor($discountAmount, $currencyCode);

                    $discountItem = [
                        'description' => $description . $totalDiscount->getTitle(),
                        'amount'      => $roundedDiscountAmount,
                    ];

                    $discounts[] = $discountItem;
                }

                if ($discount == Discount::GIFT_VOUCHER) {
                    // the amount is added to adress discount included above, $address->getDiscountAmount(),
                    // by plugin implementation, subtract it so this discount is shown separately and totals are in sync
                    $discounts[0]['amount'] -= $roundedDiscountAmount;
                } else {
                    $diff -= CurrencyUtils::toMinorWithoutRounding($discountAmount, $currencyCode) - $roundedDiscountAmount;
                    $totalAmount -= $roundedDiscountAmount;
                }
            }
        }
        // TODO: move all third party plugins support into filter
        return $this->eventsForThirdPartyModules->runFilter(
            "collectDiscounts",
            [$discounts, $totalAmount, $diff],
            $quote,
            $parentQuote,
            $paymentOnly
        );
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
     * @param null|int   $storeId
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function hasProductRestrictions($quote = null)
    {
        /** @var Quote $quote */
        $quote = $quote ?: $this->checkoutSession->getQuote();
        $storeId = $quote->getStoreId();

        $toggleCheckout = $this->configHelper->getToggleCheckout($storeId);

        if (!$toggleCheckout || !$toggleCheckout->active) {
            return false;
        }

        // get configured Product model getters that can restrict Bolt checkout usage
        $productRestrictionMethods = isset($toggleCheckout->productRestrictionMethods) ? $toggleCheckout->productRestrictionMethods : [];

        // get configured Quote Item getters that can restrict Bolt checkout usage
        $itemRestrictionMethods = isset($toggleCheckout->itemRestrictionMethods) ? $toggleCheckout->itemRestrictionMethods : [];

        if (!$productRestrictionMethods && !$itemRestrictionMethods) {
            return false;
        }

        foreach ($quote->getAllVisibleItems() as $item) {
            /** @var \Magento\Quote\Model\Quote\Item $item */
            // call every method on item, if returns true, do restrict
            foreach ($itemRestrictionMethods as $method) {
                if ($item->$method()) {
                    return true;
                }
            }
            // Non empty check to avoid unnecessary model load
            if ($productRestrictionMethods) {
                // get item product
                $product = $this->productRepository->get($item->getSku(), false, $storeId);
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

    /**
     * Create cart by request
     * TODO: add support for foreign currencies
     *
     * @param array $request
     *
     * @return array cart_data in bolt format
     * @throws \Exception
     * @throws BoltException
     */
    public function createCartByRequest($request)
    {
        $options = json_decode((string)$request['items'][0]['options'], true);
        $storeId = $options['storeId'];

        // try returning from cache
        if ($isBoltOrderCachingEnabled = $this->isBoltOrderCachingEnabled($storeId)) {
            // $request contains Magento form_id as an item options property.
            // The form_id is unique per browser session, making the request unique for each user.
            // Before sending the request, we ensure that the form_id exists.
            // Therefore, we can rely on it in generating the cache identifier.
            $cacheIdentifier = $this->getCartCacheIdentifier($request);
            if ($cart = $this->loadFromCache($cacheIdentifier)) {
                if (!$this->getOrderByQuoteId($cart['order_reference'])) {
                    return $cart;
                }
            }
        }
        if (isset($request['currency'])) {
            $this->setCurrentCurrencyCode($request['currency']);
        }
        $cart = $this->createCart($request['items'], $request['metadata'], $storeId);

        // cache and return
        if ($isBoltOrderCachingEnabled) {
            $this->saveToCache(
                $cart,
                $cacheIdentifier,
                [self::BOLT_ORDER_TAG, self::BOLT_ORDER_TAG . '_' . $cart['order_reference']],
                self::BOLT_ORDER_CACHE_LIFETIME
            );
        }
        return $cart;
    }

    /**
     * Create a cart with the provided items
     * @param $items - cart items
     * @param $metadata - cart metadata
     * @param $storeId
     * @return array cart_data in bolt format
     * @throws BoltException
     */
    public function createCart($items, $metadata = null, $storeId = null)
    {
        $quoteId = $this->quoteManagement->createEmptyCart();
        $quote = $this->quoteFactory->create()->load($quoteId);

        $quote->setBoltParentQuoteId($quoteId);
        $quote->setBoltCheckoutType(self::BOLT_CHECKOUT_TYPE_PPC);

        if (isset($metadata['encrypted_user_id'])) {
            $this->assignQuoteCustomerByEncryptedUserId($quote, $metadata['encrypted_user_id']);
        }

        //add item to quote
        foreach ($items as $item) {
            $product = $this->productRepository->getById($item['reference']);

            $options = json_decode((string)$item['options'], true);
            if (isset($options['storeId']) && $options['storeId']) {
                $quote->setStoreId($options['storeId']);
            }
            unset($options['storeId']);
            unset($options['form_key']);
            $options['qty'] = $item['quantity'];
            if (!empty($options['bundle_option'])) {
                foreach ($options['bundle_option'] as $id => $bundleOption) {
                    if (strpos($bundleOption, ',') !== false) {
                        $options['bundle_option'][$id] = explode(',', $bundleOption);
                    }
                }
            }
            $options = new \Magento\Framework\DataObject($options);

            try {
                $quote->addProduct($product, $options);
            } catch (\Exception $e) {
                $error_message = $e->getMessage();
                if ($error_message == 'Product that you are trying to add is not available.') {
                    throw new BoltException(
                        __($error_message),
                        null,
                        BoltErrorResponse::ERR_PPC_OUT_OF_STOCK
                    );
                } else {
                    throw new BoltException(
                        __('The requested qty is not available'),
                        null,
                        BoltErrorResponse::ERR_PPC_INVALID_QUANTITY
                    );
                }
            };
        }

        $quote->setIsActive(false);
        $this->saveQuote($quote);
        $this->sessionHelper->loadSession($quote, $metadata);
        $this->checkoutSession = $this->sessionHelper->getCheckoutSession();
        //make sure we recollect totals
        $quote->setTotalsCollectedFlag(false);
        $this->eventsForThirdPartyModules->dispatchEvent("beforeGetCartDataForCreateCart", $quote, $this->sessionHelper->getCheckoutSession());
        $cart_data = $this->getCartData(false, '', $quote);
        $cart_data['order_reference'] = $quoteId;
        $this->quoteResourceSave($quote);
        $this->saveQuote($quote);

        return $cart_data;
    }

    /**
     * Assign customer to Quote by encrypted user id
     *
     * @param Quote $quote
     * @param string $encrypted_user_id string generated by self::getEncodeUserId
     */
    private function assignQuoteCustomerByEncryptedUserId($quote, $encrypted_user_id)
    {
        $metadata = json_decode((string)$encrypted_user_id);
        if (! $metadata || ! isset($metadata->user_id) || ! isset($metadata->timestamp) || ! isset($metadata->signature)) {
            throw new WebapiException(__('Incorrect encrypted_user_id'), 6306, 422);
        }

        $payload = [
            'user_id'   => $metadata->user_id,
            'timestamp' => $metadata->timestamp
        ];

        if (!$this->hookHelper->verifySignature(json_encode($payload), $metadata->signature)) {
            throw new WebapiException(__('Incorrect signature'), 6306, 422);
        }

        if (time() - $metadata->timestamp > 3600) {
            throw new WebapiException(__('Outdated encrypted_user_id'), 6306, 422);
        }

        try {
            $customer = $this->customerRepository->getById($metadata->user_id);
        } catch (\Exception $e) {
            throw new WebapiException(__('Incorrect user_id'), 6306, 422);
        }
        $quote->assignCustomer($customer); // Assign quote to Customer
        $quote->setCustomerIsGuest(0);
    }

    public function calculateCartAndHints($paymentOnly = false, $placeOrderPayload = [])
    {
        $startTime = $this->metricsClient->getCurrentTime();
        $result    = [];

        try {
            if ($this->hasProductRestrictions()) {
                throw new BoltException(__('The cart has products not allowed for Bolt checkout'));
            }

            if (! $this->isCheckoutAllowed()) {
                throw new BoltException(__('Guest checkout is not allowed.'));
            }

            // call the Bolt API
            $boltpayOrder = $this->getBoltpayOrder($paymentOnly, $placeOrderPayload);

            // format and send the response
            $response = $boltpayOrder ? $boltpayOrder->getResponse() : null;

            if ($response) {
                $responseData = json_decode(json_encode($response), true);
                $this->metricsClient->processMetric("order_token.success", 1, "order_token.latency", $startTime);
            } else {
                // Empty cart - order_token not fetched because doesn't exist. Not a failure.
                $responseData = [];
                $responseData['cart'] = [];
            }

            // get immutable quote id stored with cart data
            if (!$this->deciderHelper->isAPIDrivenIntegrationEnabled() && $response) {
                $cartReference = $this->getImmutableQuoteIdFromBoltCartArray($responseData['cart']);
            } else {
                $cartReference = '';
            }

            $cart = array_merge($responseData['cart'], [
                'orderToken'    => $response ? $responseData['token'] : '',
                'cartReference' => $cartReference,
            ]);

            if (isset($cart['currency']['currency']) && $cart['currency']['currency']) {
                // cart data validation requirement
                $cart['currency']['currency_code'] = $cart['currency']['currency'];
            }

            $hints = $this->getHints($cartReference, 'cart');

            $result = [
                'status'  => 'success',
                'cart'    => $cart,
                'hints'   => $hints,
                'backUrl' => '',
            ];
        } catch (BoltException $e) {
            $result = [
                'status'   => 'success',
                'restrict' => true,
                'message'  => $e->getMessage(),
                'backUrl'  => '',
            ];
            $this->metricsClient->processMetric("order_token.failure", 1, "order_token.latency", $startTime);
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);

            $result = [
                'status'  => 'failure',
                'message' => $e->getMessage(),
                'backUrl' => '',
            ];
            $this->metricsClient->processMetric("order_token.failure", 1, "order_token.latency", $startTime);
        } finally {
            return $result;
        }
    }

    /**
     * Reset checkout session
     *
     * @param $checkoutSession
     */
    public function resetCheckoutSession($checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Report to Bugsnag if the discount code is empty.
     *
     * @param $code
     * @param $description
     */
    public function logEmptyDiscountCode($code, $description)
    {
        if (empty($code)) {
            $this->bugsnag->notifyError('Empty discount code', "Info: {$description}");
        }
    }

    /**
     * @param string $data
     *
     * @return string encrypted version of the provided data string
     */
    protected function encryptMetadataValue($data)
    {
        return base64_encode((string)$this->configHelper->encrypt($data));
    }

    /**
     * Retrieves customer by email and website id
     *
     * @param string   $email
     * @param int|null $websiteId
     *
     * @return false|\Magento\Customer\Api\Data\CustomerInterface
     */
    public function getCustomerByEmail($email, $websiteId = null)
    {
        try {
            return $this->customerRepository->get($email, $websiteId);
        } catch (LocalizedException $e) {
            return false;
        }
    }

    /**
     * Collect address total.
     *
     * @param \Magento\Quote\Model\Quote   $quote
     * @param \Magento\Quote\Model\Quote\Address $address
     *
     */
    public function collectAddressTotals($quote, $address)
    {
        /**
         * To calculate a right discount value
         * before calculate totals
         * need to reset Cart Fixed Rules in the quote
         */
        $quote->setCartFixedRules([]);
        $this->totalsCollector->collectAddressTotals($quote, $address);
    }

    /**
     * @return DeciderHelper
     */
    public function getFeatureSwitchDeciderHelper()
    {
        return $this->deciderHelper;
    }

    /**
     * Ignore adjusting shipping amount if quote has a cart rule of "Fixed amount discount for whole cart" and "Apply to shipping amount"
     *
     * @param $quote
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function ignoreAdjustingShippingAmount($quote)
    {
        if ($quote->getAppliedRuleIds()){
            $salesruleIds = explode(',', $quote->getAppliedRuleIds());

            foreach ($salesruleIds as $salesruleId) {
                $rule = $this->ruleRepository->getById($salesruleId);
                if (
                    $rule &&
                    $rule->getSimpleAction() == \Magento\SalesRule\Api\Data\RuleInterface::DISCOUNT_ACTION_FIXED_AMOUNT_FOR_CART
                    && $rule->getApplyToShipping()
                    && $rule->getDiscountAmount() <= $quote->getSubtotal()
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param $quote
     * @param $shippingMethod
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function checkIfQuoteHasCartFixedAmountAndApplyToShippingRuleAndTableRateShippingMethod($quote, $shippingMethod) {
        if ($shippingMethod !== 'tablerate_bestway') {
            return false;
        }

        return $this->checkIfQuoteHasCartFixedAmountAndApplyToShippingRule($quote);
    }

    /**
     * @param $quote
     * @param $shippingMethod
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function checkIfQuoteHasCartFixedAmountAndApplyToShippingRule($quote)
    {
        if ($quote->getAppliedRuleIds()){
            $salesruleIds = explode(',', $quote->getAppliedRuleIds());

            foreach ($salesruleIds as $salesruleId) {
                $rule = $this->ruleRepository->getById($salesruleId);
                if (
                    $rule &&
                    $rule->getSimpleAction() == \Magento\SalesRule\Api\Data\RuleInterface::DISCOUNT_ACTION_FIXED_AMOUNT_FOR_CART
                    && $rule->getApplyToShipping()
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Collect discount amount from each applied sale rules.
     *
     * @param \Magento\Quote\Model\Quote $quote
     *
     * @return array
     */
    public function getSaleRuleDiscounts($quote)
    {
        $address = $this->getCalculationAddress($quote);
        if ($this->deciderHelper->isAPIDrivenIntegrationEnabled() && ($address->getAppliedRuleIds() != $quote->getAppliedRuleIds())) {
            $quote->collectTotals();
        }
        if (!($appliedSaleRuleIds = $address->getAppliedRuleIds())) {
            return [];
        }
        $salesRuleIds = explode(',', $appliedSaleRuleIds);
        $saleRuleDiscountsDetails = [];
        // If the Magento version < 2.3.4, we still collect discounts details via plugin methods.
        if ($this->isCollectDiscountsByPlugin($quote)) {
            $saleRuleDiscountsDetails = $this->sessionHelper->getCheckoutSession()->getBoltCollectSaleRuleDiscounts([]);
        } else {
            /* @var \Magento\SalesRule\Api\Data\RuleDiscountInterface $ruleDiscounts */
            $extensionSaleRuleDiscounts = $address->getExtensionAttributes()->getDiscounts();
            $cartFixedRules = $address->getCartFixedRules();
            if ($extensionSaleRuleDiscounts && is_array($extensionSaleRuleDiscounts)) {
                foreach ($extensionSaleRuleDiscounts as $value) {
                    /* @var \Magento\SalesRule\Api\Data\DiscountDataInterface $discountData */
                    $discountData = $value->getDiscountData();
                    $salesRuleId = $value->getRuleID();
                    if (!empty($cartFixedRules) && array_key_exists($salesRuleId, $cartFixedRules) && $cartFixedRules[$salesRuleId] > DiscountHelper::MIN_NONZERO_VALUE) {
                        $rule = $this->ruleRepository->getById($salesRuleId);
                        $saleRuleDiscountsDetails[$salesRuleId] = $discountData->getAmount() + $rule->getDiscountAmount() - $cartFixedRules[$salesRuleId];
                    } else {
                        $saleRuleDiscountsDetails[$salesRuleId] = $discountData->getAmount();
                    }
                }
            }
        }

        $ruleDiscountDetails = [];
        foreach ($salesRuleIds as $salesRuleId) {
            $rule = $this->ruleRepository->getById($salesRuleId);
            // Only collect non-zero discount / free shipping promotion(with coupon code)
            if (!$rule || (!array_key_exists($salesRuleId, $saleRuleDiscountsDetails) && $rule->getCouponType() == RuleInterface::COUPON_TYPE_NO_COUPON)) {
                continue;
            }
            $ruleDiscountDetails[$salesRuleId] = array_key_exists($salesRuleId, $saleRuleDiscountsDetails) ? $saleRuleDiscountsDetails[$salesRuleId] : 0;
        }
        return $ruleDiscountDetails;
    }

    /**
     * If the Magento version < 2.3.4, we still collect discounts details via plugin methods.
     *
     * @param \Magento\Quote\Model\Quote $quote
     *
     * @return bool
     */
    public function isCollectDiscountsByPlugin($quote)
    {
        //By default this feature switch is disabled.
        if ($this->deciderHelper->isCollectDiscountsByPlugin()) {
            return true;
        }
        if (!$this->configHelper->isActive($quote->getStore()->getId())) {
            return false;
        }
        if (version_compare($this->configHelper->getStoreVersion(), '2.3.4', '<')) {
            return true;
        }
        // For bundle product, there is a bug in M2 core (https://github.com/magento/magento2/commit/5dabdb6a104159458fd9c0847447d641e75daa0e, fixed in M2 v2.4.4),
        // as a result, if the cart contains any bundle product, $address->getExtensionAttributes()->getDiscounts() returns incomplete discounts data.
        // Then we have to use plugin methods to collect discounts details if M2 version of merchant's store is older than v2.4.4
        if (version_compare($this->configHelper->getStoreVersion(), '2.4.4', '<')) {
            $items = $quote->getAllVisibleItems();
            foreach ($items as $item) {
                $_product = $item->getProduct();
                if (!$_product) {
                    continue;
                }
                if ($item->getProductType() == \Magento\Bundle\Model\Product\Type::TYPE_CODE) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get SKU of quote item
     *
     * @param \Magento\Quote\Model\Quote\Item $item
     *
     * @return string
     */
    public function getSkuFromQuoteItem($item)
    {
        // Get "Dynamic SKU" of the bundle product, so the quote item can be recognized.
        if ($item->getProductType() == \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE
            && ($product = $item->getProduct())
            && ($oldSkuType = $product->getData('sku_type'))) {
            // If "Dynamic SKU" is disabled, we need to enable it temporarily.
            $product->setData('sku_type', 0);
            $itemSku = $product->getTypeInstance()->getSku($product);
            $product->setData('sku_type', $oldSkuType);
            return $itemSku;
        }

        return trim($item->getSku());
    }

    /**
     * Set current store currency code
     *
     * @param string $code
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setCurrentCurrencyCode($code)
    {
        if ($code) {
            $this->store->setCurrentCurrencyCode($code);
        }
    }

    /**
     * Returns exists or generate new quote masked id
     *
     * @param int $quoteId
     * @return string
     */
    public function getQuoteMaskedId(int $quoteId): string
    {
        try {
            $quoteIdMask = $this->quoteIdMaskFactory->create();
            $this->quoteIdMaskResourceModel->load($quoteIdMask, $quoteId, 'quote_id');
            if ($quoteIdMask->getMaskedId()) {
                return $quoteIdMask->getMaskedId();
            }
            return $this->generateQuoteMaskedId($quoteId);
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
        return '';
    }

    /**
     * Generates new cart masked id
     *
     * @param int $quoteId
     * @return string
     * @throws AlreadyExistsException
     */
    private function generateQuoteMaskedId(int $quoteId): string
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create();
        $quoteIdMask->setQuoteId($quoteId);
        $this->quoteIdMaskResourceModel->save($quoteIdMask);
        return $quoteIdMask->getMaskedId();
    }
}
