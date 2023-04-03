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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Exception as WebApiException;
use Magento\Quote\Model\Quote;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Quote\Api\CartRepositoryInterface as CartRepository;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\SalesRule\Model\RuleRepository;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\ArrayHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;

/**
 * Class UpdateCartCommon
 *
 * @package Bolt\Boltpay\Model\Api
 */
abstract class UpdateCartCommon
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var LogHelper
     */
    protected $logHelper;

    /**
     * @var Bugsnag
     */
    protected $bugsnag;

    /**
     * @var CartHelper
     */
    protected $cartHelper;

    /**
     * @var HookHelper
     */
    protected $hookHelper;

    /**
     * @var BoltErrorResponse
     */
    protected $errorResponse;

    /**
     * @var RegionModel
     */
    protected $regionModel;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var EventsForThirdPartyModules
     */
    protected $eventsForThirdPartyModules;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var RuleRepository
     */
    protected $ruleRepository;

    /**
     * @var CartRepository
     */
    protected $cartRepository;

    /**
     * @var SessionHelper
     */
    protected $sessionHelper;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var Decider
     */
    protected $featureSwitches;
    
    /**
     * @var ShippingAssignmentProcessor
     */
    protected $shippingAssignmentProcessor;

    /**
     * @var CartExtensionFactory
     */
    protected $cartExtensionFactory;

    /**
     * UpdateCartCommon constructor.
     *
     * @param UpdateCartContext $updateCartContext
     */
    public function __construct(
        UpdateCartContext $updateCartContext
    ) {
        $this->request = $updateCartContext->getRequest();
        $this->response = $updateCartContext->getResponse();
        $this->hookHelper = $updateCartContext->getHookHelper();
        $this->errorResponse = $updateCartContext->getBoltErrorResponse();
        $this->logHelper = $updateCartContext->getLogHelper();
        $this->bugsnag = $updateCartContext->getBugsnag();
        $this->regionModel = $updateCartContext->getRegionModel();
        $this->orderHelper = $updateCartContext->getOrderHelper();
        $this->cartHelper = $updateCartContext->getCartHelper();
        $this->configHelper = $updateCartContext->getConfigHelper();
        $this->cache = $updateCartContext->getCache();
        $this->eventsForThirdPartyModules = $updateCartContext->getEventsForThirdPartyModules();
        $this->checkoutSession = $updateCartContext->getCheckoutSession();
        $this->ruleRepository = $updateCartContext->getRuleRepository();
        $this->cartRepository = $updateCartContext->getCartRepositoryInterface();
        $this->sessionHelper = $updateCartContext->getSessionHelper();
        $this->productRepository = $updateCartContext->getProductRepositoryInterface();
        $this->featureSwitches = $updateCartContext->getFeatureSwitches();
        $this->cartExtensionFactory = $updateCartContext->getCartExtensionFactory();
        $this->shippingAssignmentProcessor = $updateCartContext->getShippingAssignmentProcessor();
    }

    /**
     * Validate the related quote.
     *
     * @param $immutableQuoteId
     * @return Quote[]
     * @throws BoltException
     */
    public function validateQuote($immutableQuoteId)
    {
        // check the existence of child quote
        $immutableQuote = $this->cartHelper->getQuoteById($immutableQuoteId);
        if (!$immutableQuote) {
            throw new BoltException(
                __(sprintf('The cart reference [%s] cannot be found.', $immutableQuoteId)),
                null,
                BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION
            );
        }

        $parentQuoteId = $immutableQuote->getBoltParentQuoteId();

        if (empty($parentQuoteId)) {
            $this->bugsnag->notifyError(
                BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                'Parent quote does not exist'
            );
            throw new BoltException(
                __('Parent quote does not exist'),
                null,
                BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION
            );
        }

        /** @var Quote $parentQuote */
        if ($immutableQuoteId == $parentQuoteId) {
            // Product Page Checkout - quotes are created as inactive
            $parentQuote = $this->cartHelper->getQuoteById($parentQuoteId);
        } else {
            $parentQuote = $this->cartHelper->getActiveQuoteById($parentQuoteId);
        }

        // check if the order has already been created
        if ($this->orderHelper->getExistingOrder(null, $parentQuoteId)) {
            throw new BoltException(
                __(sprintf('The order with quote #%s has already been created ', $parentQuoteId)),
                null,
                BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION
            );
        }

        // check if cart is empty
        if (!$immutableQuote->getItemsCount()) {
            throw new BoltException(
                __(sprintf('The cart for order reference [%s] is empty.', $immutableQuoteId)),
                null,
                BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION
            );
        }

        return [
            $parentQuote,
            $immutableQuote,
        ];
    }



    /**
     *
     * Set the shipment if request payload has that info.
     *
     * @param array $shipment
     * @param Quote $immutableQuote
     *
     * @throws LocalizedException
     * @throws WebApiException
     */
    public function setShipment($shipment, $immutableQuote)
    {
        $shippingAddress = $immutableQuote->getShippingAddress();
        $address = $shipment['shipping_address'];
        $address = $this->cartHelper->handleSpecialAddressCases($address);
        $region = $this->regionModel->loadByName(ArrayHelper::getValueFromArray($address, 'region', ''), ArrayHelper::getValueFromArray($address, 'country_code', ''));
        $addressData = [
                    'firstname'    => ArrayHelper::getValueFromArray($address, 'first_name', ''),
                    'lastname'     => ArrayHelper::getValueFromArray($address, 'last_name', ''),
                    'street'       => trim(ArrayHelper::getValueFromArray($address, 'street_address1', '') . "\n" . ArrayHelper::getValueFromArray($address, 'street_address2', '')),
                    'city'         => ArrayHelper::getValueFromArray($address, 'locality', ''),
                    'country_id'   => ArrayHelper::getValueFromArray($address, 'country_code', ''),
                    'region'       => ArrayHelper::getValueFromArray($address, 'region', ''),
                    'postcode'     => ArrayHelper::getValueFromArray($address, 'postal_code', ''),
                    'telephone'    => ArrayHelper::getValueFromArray($address, 'phone_number', ''),
                    'region_id'    => $region ? $region->getId() : null,
                    'company'      => ArrayHelper::getValueFromArray($address, 'company', ''),
                ];
        if ($this->cartHelper->validateEmail(ArrayHelper::getValueFromArray($address, 'email_address', ''))) {
            $addressData['email'] = $address['email_address'];
        }

        $shippingAddress->setShouldIgnoreValidation(true);
        $shippingAddress->addData($addressData);

        $shippingAddress
            ->setShippingMethod($shipment['reference'])
            ->save();
    }

    /**
     * @param null|int $storeId
     * @throws LocalizedException
     * @throws WebApiException
     */
    public function preProcessWebhook($storeId = null)
    {
        HookHelper::$fromBolt = true;
        $this->hookHelper->preProcessWebhook($storeId);
    }

    /**
     * @return array
     */
    protected function getRequestContent()
    {
        $content = $this->request->getContent();
        $this->logHelper->addInfoLog($content);
        return json_decode((string)$content);
    }

    /**
     * Collect and update quote totals.
     * @param Quote $quote
     */
    protected function updateTotals($quote)
    {
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $quote->setDataChanges(true);
        $this->cartRepository->save($quote);
    }

    /**
     * Create cart data items array.
     * @param Quote $quote
     */
    protected function getCartItems($quote)
    {
        $items = $quote->getAllVisibleItems();
        $currencyCode = $quote->getQuoteCurrencyCode();
        $products = [];

        foreach ($items as $item) {
            $product = [];

            $itemReference = $item->getProductId();
            //By default this feature switch is enabled.
            if ($this->featureSwitches->isCustomizableOptionsSupport()) {
                $itemProduct = $item->getProduct();
                $customizableOptions = $this->cartHelper->getProductCustomizableOptions($item);
                $itemSku = trim($item->getSku());

                if ($customizableOptions) {
                    $itemSku = $this->cartHelper->getProductActualSkuByCustomizableOptions($itemSku, $customizableOptions);
                }

                try {
                    $_product = $this->productRepository->get($itemSku);
                    $itemReference = $_product->getId();
                } catch (\Exception $e) {
                    $this->bugsnag->notifyException($e);
                }
            }

            $product['reference']    = $itemReference;
            $product['quantity']     = round($item->getQty());
            $product['unit_price']   = $item->getCalculationPrice();
            $product['quote_item_id']= $item->getId();
            $product['quote_item']   = $item;

            $products[] = $product;
        }

        return $products;
    }

    /**
     * Load logged in customer checkout and customer sessions from cached session id.
     * Replace the quote with $parentQuote in checkout session.
     * In this way, the quote object can be associated with cart properly as well.
     *
     * @param Quote $quote
     * @param array $metadata
     *
     * @throws LocalizedException
     */
    public function updateSession($quote, $metadata = [])
    {
        $this->sessionHelper->loadSession($quote, $metadata ?? []);
        $this->cartHelper->resetCheckoutSession($this->sessionHelper->getCheckoutSession());
    }
    
    /**
     * When the feature switch M2_HANDLE_VIRTUAL_PRODUCTS_AS_PHYSICAL is enabled and the virtual quote has coupon applied,
     * the billing address is required to get cart data.
     *
     * @param Quote $quote
     * @param array $requestData
     *
     */
    public function createPayloadForVirtualQuote($quote, $requestData)
    {
        if (!$quote->isVirtual() || !$this->featureSwitches->handleVirtualProductsAsPhysical()) {
            return null;
        }
        $billingAddress = $requestData['cart']['billing_address']
                          ?? $requestData['billing_address']
                          ?? null;
        if (!$billingAddress) {
            return null;
        }
        $payload = [
            'billingAddress' => [
                'firstname' => $billingAddress['first_name'] ?? '',
                'lastname'  => $billingAddress['last_name'] ?? '',
                'company'   => $billingAddress['company'] ?? '',
                'telephone' => $billingAddress['phone_number'] ?? '',
                'street'    => [$billingAddress['street_address1'] ?? '', $billingAddress['street_address2'] ?? ''],
                'city'      => $billingAddress['locality'] ?? '',
                'region'    => $billingAddress['region'] ?? '',
                'postcode'  => $billingAddress['postal_code'] ?? '',
                'countryId' => $billingAddress['country_code'] ?? '',
                'email'     => $billingAddress['email_address'] ?? '',
            ],
        ];
        return json_encode((object)$payload);
    }
    
    /**
     * Create shipping assignment for shopping cart
     *
     * @param CartInterface $quote
     */
    public function setShippingAssignments($quote)
    {
        $shippingAssignments = [];
        if (!$quote->isVirtual() && $quote->getItemsQty() > 0) {
            $shippingAssignments[] = $this->shippingAssignmentProcessor->create($quote);
        }
        $cartExtension = $quote->getExtensionAttributes();
        if ($cartExtension === null) {
            $cartExtension = $this->cartExtensionFactory->create();
        }
        $cartExtension->setShippingAssignments($shippingAssignments);
        $quote->setExtensionAttributes($cartExtension);
    }

    /**
     * @param int        $errCode
     * @param string     $message
     * @param int        $httpStatusCode
     * @param null|Quote $quote
     *
     * @return void
     * @throws \Exception
     */
    abstract protected function sendErrorResponse($errCode, $message, $httpStatusCode, $quote = null);

    /**
     * @param array $result
     * @throws \Exception
     */
    abstract protected function sendSuccessResponse($result, $quote = null);
}
