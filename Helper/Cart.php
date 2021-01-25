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

namespace Bolt\Boltpay\Helper;

use Bolt\Boltpay\Model\Response;
use Magento\Framework\Registry;
use Magento\Framework\Session\SessionManagerInterface as CheckoutSession;
use Magento\Catalog\Model\ProductRepository;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total as AddressTotal;
use Magento\Sales\Api\Data\OrderInterface;
use Zend_Http_Client_Exception;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Framework\DataObjectFactory;
use Magento\Catalog\Helper\ImageFactory;
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
use Magento\Framework\App\CacheInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Magento\Quote\Api\CartManagementInterface;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider as DeciderHelper;
use Magento\Catalog\Model\Config\Source\Product\Thumbnail as ThumbnailSource;
use Zend\Serializer\Adapter\PhpSerialize as Serialize;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Magento\SalesRule\Model\RuleRepository;
use Magento\SalesRule\Api\Data\RuleInterface;

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
    const BOLT_ORDER_CACHE_LIFETIME = 3600; // one hour

    const BOLT_CHECKOUT_TYPE_MULTISTEP = 1;
    const BOLT_CHECKOUT_TYPE_PPC = 2;
    const BOLT_CHECKOUT_TYPE_BACKOFFICE = 3;
    const BOLT_CHECKOUT_TYPE_PPC_COMPLETE = 4;

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
        'region',
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
        Discount::GIFT_CARD_ACCOUNT => '',
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
     * @var Registry
     */
    private $coreRegistry;

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
     * @param Context                    $context
     * @param CheckoutSession            $checkoutSession
     * @param ProductRepository          $productRepository
     * @param ApiHelper                  $apiHelper
     * @param ConfigHelper               $configHelper
     * @param CustomerSession            $customerSession
     * @param LogHelper                  $logHelper
     * @param Bugsnag                    $bugsnag
     * @param DataObjectFactory          $dataObjectFactory
     * @param ImageFactory               $imageHelperFactory
     * @param Emulation                  $appEmulation
     * @param QuoteFactory               $quoteFactory
     * @param TotalsCollector            $totalsCollector
     * @param QuoteRepository            $quoteRepository
     * @param OrderRepository            $orderRepository
     * @param SearchCriteriaBuilder      $searchCriteriaBuilder
     * @param QuoteResource              $quoteResource
     * @param SessionHelper              $sessionHelper
     * @param CheckoutHelper             $checkoutHelper
     * @param DiscountHelper             $discountHelper
     * @param CacheInterface             $cache
     * @param ResourceConnection         $resourceConnection
     * @param CartManagementInterface    $quoteManagement
     * @param HookHelper                 $hookHelper
     * @param CustomerRepository         $customerRepository
     * @param Registry                   $coreRegistry
     * @param MetricsClient              $metricsClient
     * @param DeciderHelper              $deciderHelper
     * @param Serialize                  $serialize
     * @param EventsForThirdPartyModules $eventsForThirdPartyModules
     * @param RuleRepository             $ruleRepository
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
        Registry $coreRegistry,
        MetricsClient $metricsClient,
        DeciderHelper $deciderHelper,
        Serialize $serialize,
        EventsForThirdPartyModules $eventsForThirdPartyModules,
        RuleRepository $ruleRepository
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
        $this->coreRegistry = $coreRegistry;
        $this->metricsClient = $metricsClient;
        $this->deciderHelper = $deciderHelper;
        $this->serialize = $serialize;
        $this->eventsForThirdPartyModules = $eventsForThirdPartyModules;
        $this->ruleRepository = $ruleRepository;
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

        return $unserialize ? $this->serialize->unserialize($cached) : $cached;
    }

    /**
     * Save data to Magento cache
     *
     * @param mixed $data
     * @param string $identifier
     * @param int $lifeTime
     * @param bool $serialize
     * @param array $tags
     */
    protected function saveToCache($data, $identifier, $tags = [], $lifeTime = null, $serialize = true)
    {
        $data = $serialize ? $this->serialize->serialize($data) : $data;
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
     * @throws Zend_Http_Client_Exception
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

        return hash('md5', $identifier);
    }

    /**
     * @param $immutableQuote
     * @return string
     */
    private function convertExternalFieldsToCacheIdentifier($immutableQuote)
    {
        $cacheIdentifier = "";
        // add gift message id into cart cache identifier
        if($giftMessageId = $immutableQuote->getGiftMessageId()) {
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
        if ($response)
        {
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
        $this->discountHelper->clearAmastyRewardPoints($quote);
    }

    /**
     * Create order on bolt
     *
     * @param bool   $paymentOnly              flag that represents the type of checkout
     * @param string $placeOrderPayload        additional data collected from the (one page checkout) page,
     *                                         i.e. billing address to be saved with the order
     * @param null|int    $storeId             The ID of the Magento store
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

        $quote = $this->checkoutSession->getQuote();

        if ($this->doesOrderExist($cart, $quote)) {
            $this->deactivateSessionQuote($quote);
            return;
        }


        // If storeId was missed through request, then try to get it from the session quote.
        if ($storeId === null) {
            $storeId = $this->getSessionQuoteStoreId();
        }

        // Try fetching data from cache
        if ($isBoltOrderCachingEnabled = $this->isBoltOrderCachingEnabled($storeId)) {

            $cacheIdentifier = $this->getCartCacheIdentifier($cart);

            if ($boltOrder = $this->loadFromCache($cacheIdentifier)) {

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

        // Logged in customes.
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
                )->getResponse();

                if ($signResponse) {
                    $hints['signed_merchant_user_id'] = [
                        "merchant_user_id" => $signResponse->merchant_user_id,
                        "signature"        => $signResponse->signature,
                        "nonce"            => $signResponse->nonce,
                    ];
                }
            }

            if ($quote && $quote->isVirtual()) {
                $prefillHints($customer->getDefaultBillingAddress());
            } else {
                // TODO: use billing address for checkout on product page and virtual products
                $prefillHints($customer->getDefaultShippingAddress());
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
     * @throws \Zend_Validate_Exception
     */
    private function transferData(
        $parent,
        $child,
        $save = true,
        $emailFields = ['customer_email', 'email'],
        $excludeFields = ['entity_id', 'address_id', 'reserved_order_id',
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
     * @throws \Zend_Validate_Exception
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

        // If Amasty Reward Points extension is present clone applied reward points
        $this->discountHelper->setAmastyRewardPoints($source, $destination);

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
     * @throws \Zend_Validate_Exception
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
    private function getAdditionalAttributes($sku, $storeId, $additionalAttributes) {
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
                $attributeValue = (string) $product->getAttributeText($attributeName);
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
     * Create cart data items array
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

        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        // The "appEmulation" is necessary for geting correct image url from an API call.
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
            function ($item) use ($imageHelper, &$totalAmount, &$diff, $storeId, $currencyCode, $additionalAttributes) {
                $product = [];

                $unitPrice   = $item->getCalculationPrice();
                $itemTotalAmount = $unitPrice * $item->getQty();

                $roundedTotalAmount = CurrencyUtils::toMinor($unitPrice, $currencyCode) * round($item->getQty());

                // Aggregate eventual total differences if prices are stored with more than 2 decimal places
                $diff += CurrencyUtils::toMinorWithoutRounding($itemTotalAmount, $currencyCode) -$roundedTotalAmount;

                // Aggregate cart total
                $totalAmount += $roundedTotalAmount;

                ////////////////////////////////////
                // Load item product object
                ////////////////////////////////////
                $_product = $this->productRepository->get(trim($item->getSku()));

                $product['reference']    = $_product->getId();
                $product['name']         = $_product->getName();
                $product['total_amount'] = $roundedTotalAmount;
                $product['unit_price']   = CurrencyUtils::toMinor($unitPrice, $currencyCode);
                $product['quantity']     = round($item->getQty());
                $product['sku']          = trim($item->getSku());

                // In current Bolt checkout flow, the shipping and tax endpoint is not called for virtual carts,
                // It means we don't support taxes for virtual product and should handle all products as physical
                // TODO: Remove the feature switch check when issue will be solved https://boltpay.atlassian.net/browse/DC-181
                if ( $item->getIsVirtual() && !$this->deciderHelper->handleVirtualProductsAsPhysical()) {
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
                foreach ($this->getAdditionalAttributes($item->getSku(),$storeId, $additionalAttributes) as $attribute ) {
                    $properties[] = $attribute;
                }

                if ($properties) {
                    $product['properties'] = $properties;
                }
                ////////////////////////////////////
                // Get product description and image
                ////////////////////////////////////
                $product['description'] = str_replace(array("\r\n", "\n", "\r"), ' ', strip_tags($_product->getDescription()));
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

        $total = $quote->getTotals();
        if (isset($total['giftwrapping']) && ($total['giftwrapping']->getGwId() || $total['giftwrapping']->getGwItemIds())) {
            $giftWrapping = $total['giftwrapping'];
            $totalPrice = $giftWrapping->getGwPrice() + $giftWrapping->getGwItemsPrice() + $quote->getGwCardPrice();
            $product = [];
            $product['reference']    = $giftWrapping->getGwId();
            $product['name']         = $giftWrapping->getTitle()->getText();
            $product['total_amount'] = CurrencyUtils::toMinor($totalPrice, $currencyCode);
            $product['unit_price']   = CurrencyUtils::toMinor($totalPrice, $currencyCode);
            $product['quantity']     = 1;
            $product['sku']          = trim($giftWrapping->getCode());
            $product['type']         =  self::ITEM_TYPE_PHYSICAL;

            $totalAmount +=  $product['total_amount'];
            $products[] = $product;
        }

        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        $this->appEmulation->stopEnvironmentEmulation();
        /////////////////////////////////////////////////////////////////////////////////////////////////////////

        return [$products, $totalAmount, $diff];
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
    private function getProductToGetImageForGroupedItem ($item)
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
    private function getUserGroupId() {
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

        // If the immutable quote is passed (i.e. discount code validation, bugsnag report generation)
        // load the parent quote, otherwise load the session quote
        /** @var Quote $quote */
        $quote = $immutableQuote ?
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

        ////////////////////////////////////////////////////////
        // CLONE THE QUOTE and quote billing / shipping  address
        // if immutable quote is not passed to the method - the
        // cart data is being created for sending to Bolt create
        // order API, otherwise skip this step
        ////////////////////////////////////////////////////////
        if (!$immutableQuote) {
            $immutableQuote = $this->createImmutableQuote($quote);
        }

        ////////////////////////////////////////////////////////

        // Get array of all items that can be display directly
        $items = $immutableQuote->getAllVisibleItems();

        if (!$items) {
            // This is the case when customer empties the cart.
            return [];
        }

        $this->setLastImmutableQuote($immutableQuote);
        if ($this->isBackendSession()) {
            /**
             * initialize rule data for backend orders, consumed by
             * @see \Magento\CatalogRule\Observer\ProcessAdminFinalPriceObserver::execute
             */
            $this->coreRegistry->unregister('rule_data');
            $this->coreRegistry->register(
                'rule_data',
                new \Magento\Framework\DataObject(
                    [
                        'store_id'          => $this->checkoutSession->getStore()->getId(),
                        'website_id'        => $this->checkoutSession->getStore()->getWebsiteId(),
                        'customer_group_id' => $immutableQuote->getCustomerGroupId()
                            ? $immutableQuote->getCustomerGroupId()
                            : $this->checkoutSession->getCustomerGroupId()
                    ]
                )
            );
        }

        $immutableQuote->collectTotals();

        $cart = $this->buildCartFromQuote($quote, $immutableQuote, $items, $placeOrderPayload, $paymentOnly);
        if ($cart == []) {
            // empty cart implies there was an error
            return $cart;
        }

        // Set order_reference to parent quote id.
        // This is the constraint field on Bolt side and this way
        // duplicate payments / orders are prevented
        $cart['order_reference'] = $immutableQuote->getBoltParentQuoteId();

        if ($this->deciderHelper->isIncludeUserGroupIntoCart()) {
            $cart['metadata']['user_group_id'] = $this->getUserGroupId();
        }

        $this->sessionHelper->cacheFormKey($immutableQuote);

        // $this->logHelper->addInfoLog(json_encode($cart, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

        return $cart;
    }

    /**
     * Build cart data from quote.
     *
     * @param Quote $quote
     * @param Quote $immutableQuote
     * @param array $items
     * @param bool $paymentOnly             flag that represents the type of checkout
     * @param string $placeOrderPayload     additional data collected from the (one page checkout) page,
     * @param bool $requireBillingAddress   require that the billing address is set
     *
     * @return array
     * @throws \Exception
     */
    public function buildCartFromQuote($quote, $immutableQuote, $items, $placeOrderPayload, $paymentOnly, $requireBillingAddress = true)
    {
        $cart = [];

        $billingAddress  = $immutableQuote->getBillingAddress();
        $shippingAddress = $immutableQuote->getShippingAddress();

        //Use display_id to hold and transmit, all the way back and forth, reserved order id
        $cart['display_id'] = '';

        //Store immutable quote id in metadata of cart
        $cart['metadata']['immutable_quote_id'] = $immutableQuote->getId();

        // Transmit session ID via cart metadata, making it available for session emulation in API calls
        if ($this->deciderHelper->isAddSessionIdToCartMetadata()) {
            $cart['metadata'][SessionHelper::ENCRYPTED_SESSION_ID_KEY] = $this->encryptMetadataValue(
                $this->checkoutSession->getSessionId()
            );
        }

        $sessionData = $this->eventsForThirdPartyModules->runFilter('collectSessionData', [], $quote, $immutableQuote);
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
        $currencyCode = $immutableQuote->getQuoteCurrencyCode();
        $cart['currency'] = $currencyCode;

        list ($cart['items'], $totalAmount, $diff) = $this->getCartItems($immutableQuote, $immutableQuote->getStoreId());

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

        $address = $this->getCalculationAddress($immutableQuote);

        // payment only checkout, include shipments, tax and grand total
        if ($paymentOnly) {
            if ($immutableQuote->isVirtual()) {
                if (!empty($cart['billing_address'])) {
                    $this->totalsCollector->collectAddressTotals($immutableQuote, $address);
                    $address->save();
                } elseif ($requireBillingAddress) {
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
                    $rounded_cost = CurrencyUtils::toMinor($cost, $currencyCode);

                    $diff += CurrencyUtils::toMinorWithoutRounding($cost, $currencyCode) - $rounded_cost;
                    $totalAmount += $rounded_cost;

                    $cart['shipments'] = [[
                        'cost' => $rounded_cost,
                        'tax_amount' => CurrencyUtils::toMinor($address->getShippingTaxAmount(), $currencyCode),
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
            $immutableQuote
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

        if (abs($diff) >= $this->threshold) {
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

        return $cart;
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
        if (($amount = abs($address->getDiscountAmount())) || $quote->getCouponCode()) {
            // The discount amount of each sale rule is stored in the checkout session, using rule id as key,
            // Bolt\Boltpay\Plugin\SalesRuleActionDiscountPlugin
            $boltCollectSaleRuleDiscounts = $this->sessionHelper->getCheckoutSession()->getBoltCollectSaleRuleDiscounts([]);

            $salesruleIds = explode(',', $quote->getAppliedRuleIds());
            foreach ($salesruleIds as $salesruleId) {
                if (!isset($boltCollectSaleRuleDiscounts[$salesruleId])) {
                    continue;
                }
                $rule = $this->ruleRepository->getById($salesruleId);
                $ruleDiscountAmount = $boltCollectSaleRuleDiscounts[$salesruleId];
                if ($rule && $ruleDiscountAmount) {
                    $roundedAmount = CurrencyUtils::toMinor($ruleDiscountAmount, $currencyCode);
                    switch ($rule->getCouponType()) {
                        case RuleInterface::COUPON_TYPE_SPECIFIC_COUPON:
                        case RuleInterface::COUPON_TYPE_AUTO:
                            $couponCode = $quote->getCouponCode();
                            $ruleDescription = $rule->getDescription();
                            $description = $ruleDescription !== '' ? $ruleDescription : 'Discount (' . $couponCode . ')';
                            $discounts[] = [
                                'description'       => $description,
                                'amount'            => $roundedAmount,
                                'reference'         => $couponCode,
                                'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_COUPON,
                                'discount_type'     => $this->discountHelper->convertToBoltDiscountType($couponCode), // For v1/discounts.code.apply and v2/cart.update
                                'type'              => $this->discountHelper->convertToBoltDiscountType($couponCode), // For v1/merchant/order
                            ];
                            $this->logEmptyDiscountCode($couponCode, $description);

                            break;
                        case RuleInterface::COUPON_TYPE_NO_COUPON:
                        default:
                            $discounts[] = [
                                'description'       => trim(__('Discount ') . $rule->getDescription()),
                                'amount'            => $roundedAmount,
                                'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_AUTO_PROMO,
                                'discount_type'     => $this->discountHelper->getBoltDiscountType($rule->getSimpleAction()), // For v1/discounts.code.apply and v2/cart.update
                                'type'              => $this->discountHelper->getBoltDiscountType($rule->getSimpleAction()), // For v1/merchant/order
                            ];

                            break;
                    }
                    $totalAmount -= $roundedAmount;
                }
            }
        }
        /////////////////////////////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////////////
        // Process Store Credit
        /////////////////////////////////////////////////////////////////////////////////
        if ($quote->getUseCustomerBalance()) {
            if ($paymentOnly && $amount = abs($quote->getCustomerBalanceAmountUsed())) {
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);

                $discounts[] = [
                    'description'       => 'Store Credit',
                    'amount'            => $roundedAmount,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    'discount_type'     => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/discounts.code.apply and v2/cart.update
                    'type'              => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/merchant/order
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

                if ($amount = abs($balanceModel->getAmount())) {
                    $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);

                    $discounts[] = [
                        'description'       => 'Store Credit',
                        'amount'            => $roundedAmount,
                        'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                        'discount_type'     => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/discounts.code.apply and v2/cart.update
                        'type'              => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/merchant/order
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
            if ($paymentOnly && $amount = abs($quote->getRewardCurrencyAmount())) {
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);

                $discounts[] = [
                    'description'       => 'Reward Points',
                    'amount'            => $roundedAmount,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    'discount_type'     => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/discounts.code.apply and v2/cart.update
                    'type'              => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/merchant/order
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

                if ($amount = abs($rewardModel->getCurrencyAmount())) {
                    $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);

                    $discounts[] = [
                        'description'       => 'Reward Points',
                        'amount'            => $roundedAmount,
                        'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                        'discount_type'     => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/discounts.code.apply and v2/cart.update
                        'type'              => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/merchant/order
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
                    $discountAmount = abs($amount);
                    $roundedDiscountAmount = CurrencyUtils::toMinor($discountAmount, $currencyCode);
                    $gcDescription = $description . $totalDiscount->getTitle();
                    $discountItem = [
                        'description'       => $gcDescription,
                        'amount'            => $roundedDiscountAmount,
                        'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_GIFTCARD,
                        'reference'         => $gcCode,
                        'discount_type'     => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/discounts.code.apply and v2/cart.update
                        'type'              => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/merchant/order
                    ];
                    $this->logEmptyDiscountCode($gcCode, $gcDescription);
                    $discounts[] = $discountItem;
                } elseif ($discount == Discount::GIFT_CARD_ACCOUNT) {
                    $giftCardCodes = $this->discountHelper->getMagentoGiftCardAccountGiftCardData($quote);
                    foreach($giftCardCodes as $giftCardCode => $giftCardAmount) {
                        $amount = abs($giftCardAmount);
                        $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                        $discountItem = [
                            'description'       => 'Gift Card: ' . $giftCardCode,
                            'amount'            => $roundedAmount,
                            'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_GIFTCARD,
                            'reference'         => (string)$giftCardCode,
                            'discount_type'     => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/discounts.code.apply and v2/cart.update
                            'type'              => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/merchant/order
                        ];
                        $this->logEmptyDiscountCode($giftCardCode, 'Gift Card: ' . $giftCardCode);
                        $discountAmount += $amount;
                        $roundedDiscountAmount += $roundedAmount;
                        $discounts[] = $discountItem;
                    }
                } else {
                    $discountAmount = abs($amount);
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
        $options = json_decode($request['items'][0]['options'], true);
        $storeId = $options['storeId'];

        // try returning from cache
        if ($isBoltOrderCachingEnabled = $this->isBoltOrderCachingEnabled($storeId)) {
            // $request contains Magento form_id as an item options property.
            // The form_id is unique per browser session, making the request unique for each user.
            // Before sending the request, we ensure that the form_id exists.
            // Therefore, we can rely on it in generating the cache identifier.
            $cacheIdentifier = $this->getCartCacheIdentifier($request);
            if ($cart = $this->loadFromCache($cacheIdentifier)) {
                return $cart;
            }
        }

        $cart = $this->createCart($request['items'], $request['metadata']);

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
     * @return array cart_data in bolt format
     * @throws BoltException
     */
    public function createCart($items, $metadata = null)
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
            $product = $this->productRepository->getbyId($item['reference']);

            $options = json_decode($item['options'], true);
            if (isset($options['storeId']) && $options['storeId']) {
                $quote->setStoreId($options['storeId']);
            }
            unset($options['storeId']);
            unset($options['form_key']);
            $options['qty'] = $item['quantity'];
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
        $cart_data = $this->getCartData(false, '', $quote);
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
        $metadata = json_decode($encrypted_user_id);
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
                $responseData['cart'] = [];
            }

            // get immutable quote id stored with cart data
            if ($response) {
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
    private function logEmptyDiscountCode($code, $description)
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
        return base64_encode($this->configHelper->encrypt($data));
    }
}
