<?php

/**
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Bolt\Boltpay\Helper;

use Bolt\Boltpay\Model\Request;
use Bolt\Boltpay\Model\Response;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\ZendClientFactory;
use Bolt\Boltpay\Model\ResponseFactory;
use Bolt\Boltpay\Model\RequestFactory;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\Phrase;
use Zend_Http_Client_Exception;

/**
 * Boltpay API helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Api extends AbstractHelper {


    /**
     * Bolt sandbox url
     */
    const API_URL_SANDBOX = 'https://api-sandbox.bolt.com/';


    /**
     * Bolt production url
     */
    const API_URL_PRODUCTION = 'https://api.bolt.com/';


    /**
     * Api current version
     */
    const API_CURRENT_VERSION = 'v1/';


	/**
	 * Api publish oauth tokens
	 */
	const API_VERIFY_SIGNATURE = 'merchant/verify_signature';


	/**
	 * Api sign a payload end point
	 */
	const API_SIGN = 'merchant/sign';


	/**
     * Api create order
     */
    const API_CREATE_ORDER = 'merchant/orders';


	/**
	 * Api void transaction
	 */
	const API_VOID_TRANSACTION = 'merchant/transactions/void';


	/**
	 * Api capture transaction
	 */
	const API_CAPTURE_TRANSACTION = 'merchant/transactions/capture';


	/**
     * Api refund transaction
     */
    const API_REFUND_TRANSACTION = 'merchant/transactions/credit';


    /**
     * Api fetch transaction info
     */
    const API_FETCH_TRANSACTION = 'merchant/transactions';


    /**
     * Bolt sandbox cdn url
     */
    const CDN_URL_SANDBOX = 'https://connect-sandbox.bolt.com';


    /**
     * Bolt production cdn url
     */
    const CDN_URL_PRODUCTION = 'https://connect.bolt.com';


    /**
     * Path for sandbox mode
     */
    const XML_PATH_SANDBOX_MODE = 'payment/boltpay/sandbox_mode';


    /**
     * @var ZendClientFactory
     */
    protected $httpClientFactory;


    /**
     * @var ConfigHelper
     */
    protected $configHelper;


    /**
     * @var LogHelper
     */
    protected $logHelper;


    /**
     * Response factory
     *
     * @var ResponseFactory
     */
    protected $responseFactory;


    /**
     * Request factory
     *
     * @var RequestFactory
     */
    protected $requestFactory;


    /**
     * @param Context           $context
     * @param ZendClientFactory $httpClientFactory
     * @param ConfigHelper      $configHelper
     * @param ResponseFactory   $responseFactory
     * @param RequestFactory    $requestFactory
     * @param LogHelper         $logHelper
     * @codeCoverageIgnore
     */
    public function __construct(
    	Context           $context,
	    ZendClientFactory $httpClientFactory,
	    ConfigHelper      $configHelper,
	    ResponseFactory   $responseFactory,
	    RequestFactory    $requestFactory,
	    LogHelper         $logHelper
    ) {
        parent::__construct($context);
        $this->httpClientFactory = $httpClientFactory;
        $this->configHelper     = $configHelper;
        $this->responseFactory   = $responseFactory;
        $this->requestFactory    = $requestFactory;
        $this->logHelper         = $logHelper;
    }


    /**
     * Get Bolt API base URL
     *
     * @return  string
     */
    public function getApiUrl() {
        //Check for sandbox mode
        if ($this->configHelper->isSandboxModeSet()) {
            return self::API_URL_SANDBOX;
        } else {
            return self::API_URL_PRODUCTION;
        }
    }


    /**
     * Get Bolt JavaScript base URL
     *
     * @return  string
     */
    public function getCdnUrl() {
        //Check for sandbox mode
        if ($this->configHelper->isSandboxModeSet()) {
            return self::CDN_URL_SANDBOX;
        } else {
            return self::CDN_URL_PRODUCTION;
        }
    }


    /**
     * Get Full API Endpoint
     *
     * @param  string $dynamicUrl
     * @return  string
     */
    public function getFullApiUrl($dynamicUrl) {
        $staticUrl  = $this->getApiUrl();
	    return $staticUrl . self::API_CURRENT_VERSION . $dynamicUrl;
    }


	/**
	 * Bolt Api call response wrapper method that checks for potential error responses.
	 *
	 * @param mixed $response         A response received from calling a Bolt endpoint
	 *
	 * @return mixed                  If there is no error then the response is returned unaltered.
	 * @throws LocalizedException  Thrown if an error is detected in a response
	 */
	public function handleErrorResponse($response) {

		if (is_null($response)) {
			throw new LocalizedException(__("BoltPay Gateway error: No response from Bolt. Please re-try again"));
		} elseif ($this->isResponseError($response)) {
			$message = sprintf("BoltPay Gateway error: %s", serialize($response));
			throw new LocalizedException(__($message));
		}
		return $response;
	}


	/**
	 * Checks if the Bolt API response indicates an error.
	 *
	 * @param array $response     Bolt API response
	 * @return bool               true if there is an error, false otherwise
	 */
	public function isResponseError($response) {
		return array_key_exists('errors', $response) || array_key_exists('error_code', $response);
	}


	/**
	 * A helper methond for checking errors in JSON object.
	 *
	 * @return null|string
	 */
	public function handleJsonParseRrror() {
		switch (json_last_error()) {
			case JSON_ERROR_NONE:
				return null;

			case JSON_ERROR_DEPTH:
				return 'Maximum stack depth exceeded';

			case JSON_ERROR_STATE_MISMATCH:
				return 'Underflow or the modes mismatch';

			case JSON_ERROR_CTRL_CHAR:
				return 'Unexpected control character found';

			case JSON_ERROR_SYNTAX:
				return 'Syntax error, malformed JSON';

			case JSON_ERROR_UTF8:
				return 'Malformed UTF-8 characters, possibly incorrectly encoded';

			default:
				return 'Unknown error';
		}
	}


	/**
	 * Send request to Bolt Gateway and return response
	 *
	 * @param Request $request
	 *
	 * @return Response|int
	 * @throws LocalizedException
	 * @throws Zend_Http_Client_Exception
	 */
    public function sendRequest(Request $request) {

	    $debugData = [];
	    $result = $this->responseFactory->create();
        $client = $this->httpClientFactory->create();

        $apiData = $request->getApiData();
        $apiUrl = $request->getApiUrl();
        $apiKey = $request->getApiKey();
        $requestMethod = $request->getRequestMethod();

	    $debugData['request_method'] = $requestMethod;

	    $requestData = $requestMethod !== "GET" ? json_encode($apiData, JSON_UNESCAPED_SLASHES) : null;
	    $debugData['request_data'] = $requestData;

        $client->setUri($apiUrl);
        $debugData['request_url'] = $apiUrl;

        $client->setConfig(['maxredirects' => 0, 'timeout' => 30]);

        $headers =  [
	        'User-Agent'            => 'BoltPay/Magento-'.$this->configHelper->getStoreVersion(),
	        'X-Bolt-Plugin-Version' => $this->configHelper->getModuleVersion(),
	        'Content-Type'          => 'application/json',
	        'Content-Length'        => $requestData ? strlen($requestData) : null,
	        'X-Api-Key'             => $apiKey,
	        'X-Nonce'               => rand(100000000000, 999999999999)
        ] + (array)$request->getHeaders();

        $client->setHeaders($headers);

	    $responseBody = null;

        try {
            $response = $client->setRawData($requestData, 'application/json')->request($requestMethod);

	        $responseBody = $response->getBody();
	        $debugData['response_data'] = $responseBody;
        } catch (\Exception $e) {
            throw new LocalizedException($this->wrapGatewayError($e->getMessage()));
        } finally {
	        $info = print_r($debugData, true);
	        //$this->logHelper->addInfoLog($info);
        }

        if ($request->getStatusOnly() && $response) {
        	return $response->getStatus();
        }

        if ($responseBody) {
            $resultJSON = json_decode($responseBody);
	        $jsonError  = $this->handleJsonParseRrror();
	        if ($jsonError != null) {
		        $message = __("JSON Parse Error: " . $jsonError . " Response: " . $responseBody);
		        throw new LocalizedException($message);
	        }
            $result->setResponse($resultJSON);
        } else {
            throw new LocalizedException(
            __('Something went wrong in the payment gateway.')
            );
        }
        return $result;
    }

    /**
     * Gateway error response wrapper
     *
     * @param string $text
     * @return Phrase
     */
    public function wrapGatewayError($text) {
        return __('Gateway error: %1', $text);
    }


	/**
	 * Build request
	 *
	 * @param DataObject $requestData
	 *
	 * @return Request
	 */
    public function buildRequest($requestData) {
        $apiData       = $requestData->getApiData();
        $dynamicApiUrl = $requestData->getDynamicApiUrl();
        $apiKey        = $requestData->getApiKey();
        $requestMethod = empty($requestData->getRequestMethod()) ? 'POST' : $requestData->getRequestMethod();
        $apiUrl        = $this->getFullApiUrl($dynamicApiUrl);

	    $headers    = (array)$requestData->getHeaders();
	    $statusOnly = (bool)$requestData->getStatusOnly();

        $request = $this->requestFactory->create();
        $request->setApiData($apiData);
        $request->setApiUrl($apiUrl);
	    $request->setApiKey($apiKey);
	    $request->setRequestMethod($requestMethod);
	    $request->setHeaders($headers);
	    $request->setStatusOnly($statusOnly);
        return $request;
    }
}
