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
 *
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Helper;

use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\JWT\JWT;
use Exception;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObjectFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Helper for SSO
 */
class SSOHelper extends AbstractHelper
{
    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @param Context               $context
     * @param ConfigHelper          $configHelper
     * @param StoreManagerInterface $storeManager
     * @param DataObjectFactory     $dataObjectFactory
     * @param ApiHelper             $apiHelper
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        StoreManagerInterface $storeManager,
        DataObjectFactory $dataObjectFactory,
        ApiHelper $apiHelper
    ) {
        parent::__construct($context);
        $this->configHelper = $configHelper;
        $this->storeManager = $storeManager;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->apiHelper = $apiHelper;
    }

    /**
     * Return array containing information needed for oauth exchange
     * - clientID: last part of merchant publishable key
     * - clientSecret: merchant api key
     * - boltPublicKey: public key needed for oauth exchange
     *
     * @return array
     */
    public function getOAuthConfiguration()
    {
        $storeId = $this->storeManager->getStore()->getId();

        $publishableKey = $this->configHelper->getPublishableKeyCheckout($storeId);
        $publishableKeySplit = explode('.', $publishableKey);
        $clientID = end($publishableKeySplit);

        $clientSecret = $this->configHelper->getApiKey($storeId);

        $boltPublicKey = $this->configHelper->getPublicKey($storeId);

        return [$clientID, $clientSecret, $boltPublicKey];
    }

    /**
     * Call Bolt's oauth token exchange endpoint and return the result
     *
     * @param string $code         the authorization code received
     * @param string $scope        scope for the oauth workflow, currently only openid is supported
     * @param string $clientId     client id for the oauth workflow, should be the last part of merchant publishable key
     * @param string $clientSecret client secret for the oauth workflow, should be the same as merchant API key
     *
     * @return mixed|string
     */
    public function exchangeToken($code, $scope, $clientId, $clientSecret)
    {
        try {
            $storeId = $this->storeManager->getStore()->getId();
            $apiKey = $this->configHelper->getApiKey($storeId);

            $requestData = $this->dataObjectFactory->create();
            $requestData->setDynamicApiUrl(ApiHelper::API_OAUTH_TOKEN);
            $requestData->setApiKey($apiKey);
            $requestData->setApiData("grant_type=authorization_code&code={$code}&scope={$scope}&client_id={$clientId}&client_secret={$clientSecret}");
            $requestData->setContentType('application/x-www-form-urlencoded');

            $request = $this->apiHelper->buildRequest($requestData);
            $result = $this->apiHelper->sendRequest($request);
            $response = $result->getResponse();

            return empty($response) ? 'empty response' : $response;
        } catch (Exception $exception) {
            return $exception->getMessage();
        }
    }

    /**
     * Parse and validate JWT token
     *
     * @param string $token    the JWT token
     * @param string $audience the token audience
     * @param string $pubkey   the pubkey to verify the token signature
     *
     * @return mixed|string object in JWT token body, return error message if validation fails
     */
    public function parseAndValidateJWT($token, $audience, $pubkey)
    {
        try {
            $payload = $this->getPayload($token, $pubkey);
        } catch (Exception $e) {
            return $e->getMessage();
        }

        // Issuing authority should be https://bolt.com
        if (!isset($payload['iss'])) {
            return 'iss must be set';
        }
        if ($payload['iss'] !== 'https://bolt.com') {
            return 'incorrect iss ' . $payload['iss'];
        }

        // The aud field should contain $audience
        if (!isset($payload['aud'])) {
            return 'aud must be set';
        }
        if (!in_array($audience, $payload['aud'])) {
            return 'aud ' . implode(',', $payload['aud']) . ' does not contain audience ' . $audience;
        }

        // Validate other expected Bolt fields
        if (!isset($payload['sub'])) {
            return 'sub must be set';
        }
        if (!isset($payload['first_name'])) {
            return 'first_name must be set';
        }
        if (!isset($payload['last_name'])) {
            return 'last_name must be set';
        }
        if (!isset($payload['email'])) {
            return 'email must be set';
        }
        if (!isset($payload['email_verified'])) {
            return 'email_verified must be set';
        }

        return $payload;
    }

    /**
     * Decode token and return payload, added so unit tests work
     *
     * @param string $token
     * @param string $pubkey
     *
     * @return mixed
     *
     * @throws Exception
     *
     * @codeCoverageIgnore
     */
    protected function getPayload($token, $pubkey)
    {
        $multiLineKey = chunk_split($pubkey, 64, "\n");
        $formattedKey = "-----BEGIN PUBLIC KEY-----\n$multiLineKey-----END PUBLIC KEY-----";
        return (array) JWT::decode($token, $formattedKey, array('RS256'));
    }
}
