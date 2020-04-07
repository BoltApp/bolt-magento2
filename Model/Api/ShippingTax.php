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

use Bolt\Boltpay\Api\Data\ShippingTaxDataInterface;
use Bolt\Boltpay\Api\Data\TaxDataInterface;
use Bolt\Boltpay\Api\TaxInterface;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Api\Data\TaxDataInterfaceFactory;
use Bolt\Boltpay\Api\Data\TaxResultInterfaceFactory;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\App\CacheInterface;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use Magento\Customer\Model\Data\Region;
use Magento\Checkout\Api\TotalsInformationManagementInterface;
use Magento\Checkout\Api\Data\TotalsInformationInterface;
use Bolt\Boltpay\Model\Api\ShippingTaxContext;
use Magento\Framework\App\ObjectManager;

/**
 * Class ShippingMethods
 * Shipping and Tax hook endpoint. Get shipping methods using shipping address and cart details
 *
 * @package Bolt\Boltpay\Model\Api
 */
abstract class ShippingTax
{
    CONST METRICS_SUCCESS_KEY = 'shipping_tax.success';
    CONST METRICS_FAILURE_KEY = 'shipping_tax.failure';
    CONST METRICS_LATENCY_KEY = 'shipping_tax.latency';

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
     * @var CacheInterface
     */
    protected $cache;

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
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var ShippingTaxContext
     */
    protected $shippingTaxContext;

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
        $this->cache = $shippingTaxContext->getCache();
        $this->regionModel = $shippingTaxContext->getRegionModel();
        $this->response = $shippingTaxContext->getResponse();
        $this->objectManager = $shippingTaxContext->getObjectManager();
    }

    /**
     * Validate request address
     *
     * @param $addressData
     * @throws BoltException
     * @throws \Zend_Validate_Exception
     */
    protected function validateAddressData($addressData)
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
    protected function validateEmail($email)
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
     * @throws LocalizedException
     * @throws \Magento\Framework\Webapi\Exception
     */
    protected function preprocessHook()
    {
        HookHelper::$fromBolt = true;
        $this->hookHelper->preProcessWebhook($this->quote->getStoreId());
    }

    /**
     * Fetch and apply external quote data, not stored within a quote or totals (third party modules DB tables)
     * If data is applied it is used as a part of the cache identifier.
     *
     * @param Quote $quote
     * @return string
     */
    public function applyExternalQuoteData($quote)
    {
        $data = '';
        $this->discountHelper->applyExternalDiscountData($quote);
        if ($quote->getAmrewardsPoint()) {
            $data .= $quote->getAmrewardsPoint();
        }
        if($rewardsAmount = $this->discountHelper->getMirasvitRewardsAmount($quote)){
            $data .=$rewardsAmount;
        }
        return $data;
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
     * Get tax for a given shipping option.
     *
     * @api
     * @param array $cart cart details
     * @param array $shipping_address shipping address
     * @param array $shipping_option selected shipping option
     * @return ShippingTaxDataInterface
     */
    public function execute($cart, $shipping_address, $shipping_option = null)
    {
        // echo statement initially
        $startTime = $this->metricsClient->getCurrentTime();
        try {
            // get immutable quote id stored with transaction
            list(, $quoteId) = explode(' / ', $cart['display_id']);

            // Load quote from entity id
            $this->quote = $this->getQuoteById($quoteId);

            if (!$this->quote) {
                $this->throwUnknownQuoteIdException($quoteId);
            }

//            $this->preprocessHook();

            $this->quote->getStore()->setCurrentCurrencyCode($this->quote->getQuoteCurrencyCode());

            // Load logged in customer checkout and customer sessions from cached session id.
            // Replace parent quote with immutable quote in checkout session.
            $this->sessionHelper->loadSession($this->quote);

            $addressData = $this->cartHelper->handleSpecialAddressCases($shipping_address);

            if (isset($addressData['email']) && $addressData['email'] !== null) {
                $this->validateAddressData($addressData);
            }

            $result = $this->generateResult($addressData, $shipping_option);

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

    abstract public function generateResult($addressData, $shipping_option);
}
