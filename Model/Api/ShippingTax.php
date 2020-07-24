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

use Bolt\Boltpay\Api\Data\ShippingTaxDataInterface;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Bolt\Boltpay\Model\Api\ShippingTaxContext;

/**
 * Class ShippingTax
 * Shipping and Tax hook endpoints paremt class - common methods.
 *
 * @package Bolt\Boltpay\Model\Api
 */
abstract class ShippingTax
{
    const METRICS_SUCCESS_KEY = 'shippingtax.success';
    const METRICS_FAILURE_KEY = 'shippingtax.failure';
    const METRICS_LATENCY_KEY = 'shippingtax.latency';

    /**
     * @var HookHelper
     */
    protected $hookHelper;

    /**
     * @var CartHelper
     */
    protected $cartHelper;

    /**
     * @var LogHelper
     */
    protected $logHelper;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var SessionHelper
     */
    protected $sessionHelper;

    /**
     * @var DiscountHelper
     */
    protected $discountHelper;

    /**
     * @var Bugsnag
     */
    protected $bugsnag;

    /**
     * @var MetricsClient
     */
    protected $metricsClient;

    /**
     * @var BoltErrorResponse
     */
    protected $errorResponse;

    /**
     * @var RegionModel
     */
    protected $regionModel;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var Quote
     */
    protected $quote;

    /**
     * @var ShippingTaxContext
     */
    protected $shippingTaxContext;

    /**
     * @var ShippingOptionInterfaceFactory
     */
    protected $shippingOptionFactory;

    /**
     * Assigns local references to global resources
     *
     * @param ShippingTaxContext $shippingTaxContext
     */
    public function __construct(
        ShippingTaxContext $shippingTaxContext
    ) {
        $this->hookHelper = $shippingTaxContext->getHookHelper();
        $this->cartHelper = $shippingTaxContext->getCartHelper();
        $this->logHelper = $shippingTaxContext->getLogHelper();
        $this->configHelper = $shippingTaxContext->getConfigHelper();
        $this->sessionHelper = $shippingTaxContext->getSessionHelper();
        $this->discountHelper = $shippingTaxContext->getDiscountHelper();
        $this->bugsnag = $shippingTaxContext->getBugsnag();
        $this->metricsClient = $shippingTaxContext->getMetricsClient();
        $this->errorResponse = $shippingTaxContext->getErrorResponse();
        $this->regionModel = $shippingTaxContext->getRegionModel();
        $this->response = $shippingTaxContext->getResponse();
        $this->shippingOptionFactory = $shippingTaxContext->getShippingOptionFactory();
    }

    /**
     * Validate request address
     *
     * @param $addressData
     * @throws BoltException
     * @throws \Zend_Validate_Exception
     */
    public function validateAddressData($addressData)
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
    public function validateEmail($email)
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
     * @param $quoteId
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getQuoteById($quoteId)
    {
        return $this->cartHelper->getQuoteById($quoteId);
    }

    /**
     * @param int $storeId
     * @throws LocalizedException
     * @throws \Magento\Framework\Webapi\Exception
     */
    protected function preprocessHook($storeId)
    {
        HookHelper::$fromBolt = true;
        $this->hookHelper->preProcessWebhook($storeId);
    }

    /**
     * Fetch and apply external quote data, not stored within a quote or totals (third party modules DB tables)
     *
     * @return void
     */
    public function applyExternalQuoteData()
    {
        $this->discountHelper->applyExternalDiscountData($this->quote);
    }

    public function reformatAddressData($addressData)
    {
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

        return $addressData;
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

    /**
     * @param $quoteId
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function loadQuote($quoteId)
    {
        $quote = $this->getQuoteById($quoteId);
        if (!$quote) {
            $this->throwUnknownQuoteIdException($quoteId);
        }
        return $quote;
    }

    /**
     * Get tax for a given shipping option.
     *
     * @api
     * @param mixed $cart cart details
     * @param mixed $shipping_address shipping address
     * @param mixed $shipping_option selected shipping option
     * @return ShippingTaxDataInterface
     */
    public function execute($cart, $shipping_address, $shipping_option = null)
    {
        // echo statement initially
        $startTime = $this->metricsClient->getCurrentTime();

        $this->logHelper->addInfoLog('[-= Shipping / Tax request =-]');
        $this->logHelper->addInfoLog(file_get_contents('php://input'));
        try {
            // get immutable quote id stored with transaction
            list(, $immutableQuoteId) = explode(' / ', $cart['display_id']);
            // Load immutable quote from entity id
            $immutableQuote = $this->loadQuote($immutableQuoteId);

            $this->preprocessHook($immutableQuote->getStoreId());

            // get the parent quote
            $parentQuoteId = $cart['order_reference'];
            $parentQuote = $this->loadQuote($parentQuoteId);

            $this->cartHelper->replicateQuoteData($immutableQuote, $parentQuote);

            $this->quote = $parentQuote;
            $this->quote->getStore()->setCurrentCurrencyCode($this->quote->getQuoteCurrencyCode());

            // Load logged in customer checkout and customer sessions from cached session id.
            // Replace the quote with $parentQuote in checkout session.
            $this->sessionHelper->loadSession($this->quote);

            $addressData = $this->cartHelper->handleSpecialAddressCases($shipping_address);

            if (isset($addressData['email']) && $addressData['email'] !== null) {
                $this->validateAddressData($addressData);
            }

            $result = $this->getResult($addressData, $shipping_option);

            $this->logHelper->addInfoLog('[-= Shipping / Tax result =-]');
            $this->logHelper->addInfoLog(json_encode($result, JSON_PRETTY_PRINT));

            $this->metricsClient->processMetric(static::METRICS_SUCCESS_KEY, 1, static::METRICS_LATENCY_KEY, $startTime);

            return $result;

        } catch (\Magento\Framework\Webapi\Exception $e) {
            $this->metricsClient->processMetric(static::METRICS_FAILURE_KEY, 1, static::METRICS_LATENCY_KEY, $startTime);
            $this->catchExceptionAndSendError($e, $e->getMessage(), $e->getCode(), $e->getHttpCode());
        } catch (BoltException $e) {
            $this->metricsClient->processMetric(static::METRICS_FAILURE_KEY, 1, static::METRICS_LATENCY_KEY, $startTime);
            $this->catchExceptionAndSendError($e, $e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            $this->metricsClient->processMetric(static::METRICS_FAILURE_KEY, 1, static::METRICS_LATENCY_KEY, $startTime);
            $msg = __('Unprocessable Entity') . ': ' . $e->getMessage();
            $this->catchExceptionAndSendError($e, $msg, 6009, 422);
        }
    }

    /**
     * Ally external quote data and generate the Shipping / Tax result
     *
     * @param array $addressData
     * @param array|null $shipping_option
     *
     * @return ShippingTaxDataInterface
     * @throws LocalizedException
     */
    public function getResult($addressData, $shipping_option)
    {
        // Take into account external data applied to quote in thirt party modules
        $this->applyExternalQuoteData();
        $result = $this->generateResult($addressData, $shipping_option);
        return $result;
    }

    /**
     * @param $addressData
     * @return Quote\Address
     */
    public function populateAddress($addressData)
    {
        $address = $this->quote->isVirtual() ? $this->quote->getBillingAddress() : $this->quote->getShippingAddress();
        $addressData = $this->reformatAddressData($addressData);
        $address->addData($addressData);
        return $address;
    }

    /**
     * @param array $addressData
     * @param array|null $shipping_option
     * @return ShippingTaxDataInterface
     * @throws \Exception
     */
    abstract public function generateResult($addressData, $shipping_option);
}
