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

namespace Bolt\Boltpay\Helper\GraphQL;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
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
 * Boltpay GraphQLAPI helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Client extends AbstractHelper
{

    /**
     * MerchantAPI graphQL endpoint
     */
    const MERCHANT_API_GQL_ENDPOINT = 'v2/merchant/api';

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
     * @param Context $context
     * @param ZendClientFactory $httpClientFactory
     * @param ConfigHelper $configHelper
     * @param ResponseFactory $responseFactory
     * @param RequestFactory $requestFactory
     * @param LogHelper $logHelper
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
        $this->configHelper = $configHelper;
        $this->responseFactory = $responseFactory;
        $this->requestFactory = $requestFactory;
        $this->logHelper = $logHelper;
        $this->bugsnag = $bugsnag;
    }


    private function makeGQLCall($query, $operation, $variables) {
        $result = $this->responseFactory->create();
        $client = $this->httpClientFactory->create();

        $apiKey = $this->configHelper->getApiKey();

        $gqlRequest = array(
            "operationName" => $operation,
            "variables" => $variables,
            "query" => $query
        );

        $requestData = json_encode($gqlRequest, JSON_UNESCAPED_SLASHES);

        $apiURL = $this->configHelper->getApiUrl() . self::MERCHANT_API_GQL_ENDPOINT;

        $client->setUri($apiURL);

        $client->setConfig(['maxredirects' => 0, 'timeout' => 30]);

        $headers =  ApiUtils::constructRequestHeaders(
            $this->configHelper->getStoreVersion(),
            $this->configHelper->getModuleVersion(),
            $requestData,
            $apiKey,
            array()
        );

        $client->setHeaders($headers);

        $responseBody = null;

        try {
            $response     = $client->setRawData($requestData, 'application/json')->request("POST");
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
            throw new LocalizedException(__($e->getMessage()));
        }

        if ($responseBody) {
            try {
                $resultFromJSON = ApiUtils::getJSONFromResponseBody($responseBody);
            } catch (\Exception $e) {
                throw new LocalizedException(__('Something went wrong when talking to Bolt.'));
            }

            $result->setResponse($resultFromJSON);
        } else {
            throw new LocalizedException(__('Something went wrong when talking to Bolt.'));
        }
        return $result;
    }

    /**
     * This Method makes a call to Bolt and returns the feature switches and their values for this server with
     * its current version and the current merchant in question.
     * 
     * @return mixed
     * @throws LocalizedException
     */
    public function getFeatureSwitches() {
        $res = $this->makeGQLCall(Constants::GET_FEATURE_SWITCHES_QUERY, Constants::GET_FEATURE_SWITCHES_OPERATION, array(
            "type" => Constants::PLUGIN_TYPE,
            "version" => $this->configHelper->getModuleVersion(),
        ));

        return $res;
    }

    /**
     * This method sends the logs passed in to Bolt.
     *
     * @param $jsonEncodedLogArray
     * @throws LocalizedException
     */
    public function sendLogs($jsonEncodedLogArray) {
        $res = $this->makeGQLCall(
            Constants::SEND_LOGS_QUERY,
            Constants::SEND_LOGS_OPERATION,
            array(
                "logs" => $jsonEncodedLogArray,
            )
        );
        return $res;
    }
}