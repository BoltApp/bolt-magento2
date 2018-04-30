<?php
/**
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Webapi\Rest\Request;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\Webapi\Exception as WebapiException;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Api as ApiHelper;

/**
 * Boltpay web hook helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Hook extends AbstractHelper
{
    const HMAC_HEADER = 'X-Bolt-Hmac-Sha256';

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @param Context $context
     * @param Request $request
     * @param Config  $configHelper
     * @param Log     $logHelper
     * @param Api     $apiHelper
     * @param DataObjectFactory $dataObjectFactory
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        Request $request,
        ConfigHelper $configHelper,
        LogHelper $logHelper,
        ApiHelper $apiHelper,
        DataObjectFactory $dataObjectFactory
    ) {
        parent::__construct($context);
        $this->request      = $request;
        $this->configHelper = $configHelper;
        $this->logHelper    = $logHelper;
        $this->apiHelper    = $apiHelper;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * Verifying Hook Requests via API call.
     *
     * @param string $payload
     * @param string $hmac_header
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function verifyWebhookApi($payload, $hmac_header)
    {
        //Request Data
        $requestData = $this->dataObjectFactory->create();
        $requestData->setApiData(json_decode($payload));
        $requestData->setDynamicApiUrl(ApiHelper::API_VERIFY_SIGNATURE);
        $requestData->setApiKey($this->configHelper->getApiKey());

        $headers = [
            self::HMAC_HEADER => $hmac_header
        ];

        $requestData->setHeaders($headers);

        $requestData->setStatusOnly(true);

        //Build Request
        $request = $this->apiHelper->buildRequest($requestData);
        try {
            $result = $this->apiHelper->sendRequest($request);
        } catch (\Exception $e) {
            return false;
        }

        return $result == 200;
    }

    /**
     * Verifying Hook Request using pre-exchanged signing secret key.
     *
     * @param string $payload
     * @param string $hmac_header
     *
     * @return bool
     */
    public function verifyWebhookSecret($payload, $hmac_header)
    {
        $signing_secret = $this->configHelper->getSigningSecret();
        $computed_hmac  = base64_encode(hash_hmac('sha256', $payload, $signing_secret, true));

        return $computed_hmac == $hmac_header;
    }

    /**
     * Verifying Hook Request. If signing secret is not defined or fails fallback to api call.
     *
     * @throws WebapiException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function verifyWebhook()
    {
        $payload     = $this->request->getContent();
        $hmac_header = $this->request->getHeader(self::HMAC_HEADER);

        if (!$this->verifyWebhookSecret($payload, $hmac_header) && !$this->verifyWebhookApi($payload, $hmac_header)) {
            throw new WebapiException(__('Unauthorized'), 0, WebapiException::HTTP_UNAUTHORIZED);
        }
    }
}
