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
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Shared\ApiUtils;

/**
 * Boltpay API helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Api extends AbstractHelper
{

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
     * Api review transaction
     */
    const API_REVIEW_TRANSACTION = 'merchant/transactions/review';

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
     * Path for sandbox mode
     */
    const XML_PATH_SANDBOX_MODE = 'payment/boltpay/sandbox_mode';

    /**
     * @var ZendClientFactory
     */
    private $httpClientFactory;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * Response factory
     *
     * @var ResponseFactory
     */
    private $responseFactory;

    /**
     * Request factory
     *
     * @var RequestFactory
     */
    private $requestFactory;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param Context           $context
     * @param ZendClientFactory $httpClientFactory
     * @param ConfigHelper      $configHelper
     * @param ResponseFactory   $responseFactory
     * @param RequestFactory    $requestFactory
     * @param LogHelper         $logHelper
     * @param Bugsnag $bugsnag
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        ZendClientFactory $httpClientFactory,
        ConfigHelper $configHelper,
        ResponseFactory $responseFactory,
        RequestFactory $requestFactory,
        LogHelper $logHelper,
        Bugsnag $bugsnag
    ) {
        parent::__construct($context);
        $this->httpClientFactory = $httpClientFactory;
        $this->configHelper      = $configHelper;
        $this->responseFactory   = $responseFactory;
        $this->requestFactory    = $requestFactory;
        $this->logHelper         = $logHelper;
        $this->bugsnag           = $bugsnag;
    }

    /**
     * Get Full API Endpoint
     *
     * @param  string $dynamicUrl
     * @return  string
     */
    private function getFullApiUrl($dynamicUrl)
    {
        $staticUrl  = $this->configHelper->getApiUrl();
        return $staticUrl . self::API_CURRENT_VERSION . $dynamicUrl;
    }

    /**
     * Checks if the Bolt API response indicates an error.
     *
     * @param  mixed $response    Bolt API response
     * @return bool               true if there is an error, false otherwise
     */
    private function isResponseError($response)
    {
        $arr = (array)$response;
        return array_key_exists('errors', $arr) || array_key_exists('error_code', $arr);
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
    public function sendRequest(Request $request)
    {

        $result = $this->responseFactory->create();
        $client = $this->httpClientFactory->create();

        $apiData = $request->getApiData();
        $apiUrl = $request->getApiUrl();
        $apiKey = $request->getApiKey();
        $requestMethod = $request->getRequestMethod();

        $requestData = $requestMethod !== "GET" ? json_encode($apiData, JSON_UNESCAPED_SLASHES) : null;

        $client->setUri($apiUrl);

        $client->setConfig(['maxredirects' => 0, 'timeout' => 30]);

        $headers = ApiUtils::constructRequestHeaders(
            $this->configHelper->getStoreVersion(),
            $this->configHelper->getModuleVersion(),
            $requestData,
            $apiKey,
            (array)$request->getHeaders()
        );

        $request->setHeaders($headers);

        $this->bugsnag->registerCallback(function ($report) use ($request) {
            $report->setMetaData([
                'BOLT API REQUEST' => $request->getData()
            ]);
        });

        $client->setHeaders($headers);

        $responseBody = null;

        try {
            $response     = $client->setRawData($requestData, 'application/json')->request($requestMethod);
            $responseBody = $response->getBody();

            $this->bugsnag->registerCallback(function ($report) use ($response) {
                $report->setMetaData([
                    'BOLT API RESPONSE' => [
                        'headers' => $response->getHeaders(),
                        'body'    => $response->getBody(),
                    ]
                ]);
            });

            $this->bugsnag->registerCallback(function ($report) use ($response) {
                $headers = $response->getHeaders();
                $report->setMetaData([
                    'META DATA' => [
                        'bolt_trace_id' => @$headers[ConfigHelper::BOLT_TRACE_ID_HEADER],
                    ]
                ]);
            });
        } catch (\Exception $e) {
            throw new LocalizedException($this->wrapGatewayError($e->getMessage()));
        }

        if ($request->getStatusOnly() && $response) {
            return $response->getStatus();
        }

        if ($responseBody) {
            $resultFromJSON = ApiUtils::getJSONFromResponseBody($responseBody);

            $result->setResponse($resultFromJSON);
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
    private function wrapGatewayError($text)
    {
        return __('Gateway error: %1', $text);
    }

    /**
     * Build request
     *
     * @param DataObject $requestData
     *
     * @return Request
     */
    public function buildRequest($requestData)
    {
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
