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

namespace Bolt\Boltpay\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
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
use Bolt\Boltpay\Model\Api\UpdateCartContext;
use Bolt\Boltpay\Helper\ArrayHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Session as SessionHelper;

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
     * UpdateCartCommon constructor.
     *
     * @param UpdateCartContext $updateCartContext
     */
    public function __construct(
        UpdateCartContext $updateCartContext
    )
    {
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

        if(empty($parentQuoteId)) {
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
            ->setCollectShippingRates(true)
            ->collectShippingRates()
            ->save();
    }

    /**
     * @param null|int $storeId
     * @throws LocalizedException
     * @throws WebApiException
     */
    public function preProcessWebhook($storeId = null)
    {
        $this->hookHelper->preProcessWebhook($storeId);
    }

    /**
     * @return array
     */
    protected function getRequestContent()
    {
        $content = $this->request->getContent();
        $this->logHelper->addInfoLog($content);
        return json_decode($content);
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

        $products = array_map(
            function ($item) {
                $product = [];

                $product['reference']    = $item->getProductId();
                $product['quantity']     = round($item->getQty());
                $product['quote_item_id']= $item->getId();

                return $product;
            },
            $items
        );

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
        $this->sessionHelper->loadSession($quote, $metadata);
        $this->cartHelper->resetCheckoutSession($this->sessionHelper->getCheckoutSession());
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

     * @return array
     * @throws \Exception
     */
    abstract protected function sendSuccessResponse($result, $quote = null);

}
