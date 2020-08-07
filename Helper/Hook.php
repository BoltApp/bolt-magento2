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

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Webapi\Rest\Request;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\Webapi\Exception as WebapiException;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Magento\Framework\Webapi\Rest\Response;

/**
 * Boltpay web hook helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Hook extends AbstractHelper
{
    public static $fromBolt = false;

    const HMAC_HEADER = 'X-Bolt-Hmac-Sha256';

    // hook types
    const HT_PENDING = 'pending';
    const HT_PAYMENT = 'payment';
    const HT_VOID = 'void';
    const HT_CREDIT = 'credit';
    const HT_CAPTURE = 'capture';
    const HT_AUTH = 'auth';
    const HT_REJECTED_REVERSIBLE = 'rejected_reversible';
    const HT_REJECTED_IRREVERSIBLE = 'rejected_irreversible';
    const HT_FAILED_PAYMENT = 'failed_payment';

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

    /** @var Bugsnag */
    private $bugsnag;

    /** @var Response */
    private $response;

    /**
     * @var null|int
     */
    private $storeId = null;

    /**
     * @param Context $context
     * @param Request $request
     * @param Config  $configHelper
     * @param LogHelper $logHelper
     * @param Api     $apiHelper
     * @param DataObjectFactory $dataObjectFactory
     * @param Bugsnag $bugsnag
     * @param Response $response
     */
    public function __construct(
        Context $context,
        Request $request,
        ConfigHelper $configHelper,
        LogHelper $logHelper,
        ApiHelper $apiHelper,
        DataObjectFactory $dataObjectFactory,
        Bugsnag $bugsnag,
        Response $response
    ) {
        parent::__construct($context);
        $this->request      = $request;
        $this->configHelper = $configHelper;
        $this->logHelper    = $logHelper;
        $this->apiHelper    = $apiHelper;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->bugsnag = $bugsnag;
        $this->response = $response;
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
        $requestData->setApiKey($this->configHelper->getApiKey($this->getStoreId()));

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
     * Verifying Signature using pre-exchanged signing secret key.
     *
     * @param string $payload
     * @param string $hmac_header
     *
     * @return bool
     */
    public function verifySignature($payload, $hmac_header)
    {
        return $this->computeSignature($payload) == $hmac_header;
    }

    /**
     * Compute signature using payment secret key
     *
     * @param $payload a string for which a signature is required
     *
     * @return string
     */
    public function computeSignature($payload)
    {
        $signing_secret = $this->configHelper->getSigningSecret($this->getStoreId());
        $computed_signature  = base64_encode(hash_hmac('sha256', $payload, $signing_secret, true));

        return $computed_signature;
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

        if (!$this->verifySignature($payload, $hmac_header) && !$this->verifyWebhookApi($payload, $hmac_header)) {
            throw new WebapiException(__('Precondition Failed'), 6001, 412);
        }
    }

    /**
     * Set bugsnag metadata bolt_trace_id
     */
    public function setCommonMetaData()
    {
        if ($boltTraceId = $this->request->getHeader(ConfigHelper::BOLT_TRACE_ID_HEADER)) {
            $this->bugsnag->registerCallback(function ($report) use ($boltTraceId) {
                $report->setMetaData([
                    'META DATA' => [
                        'bolt_trace_id' => $boltTraceId,
                    ]
                ]);
            });
        }
    }

    /**
     * Set additional response headers
     */
    public function setHeaders()
    {
        $this->response->getHeaders()->addHeaders([
            'User-Agent' => 'BoltPay/Magento-'.$this->configHelper->getStoreVersion() . '/' . $this->configHelper->getModuleVersion(),
            'X-Bolt-Plugin-Version' => $this->configHelper->getModuleVersion(),
        ]);
    }

    /**
     * @param null|int $storeId
     */
    public function setStoreId($storeId = null)
    {
        $this->storeId = $storeId;
    }

    /**
     * @return null|int
     */
    public function getStoreId()
    {
        return $this->storeId;
    }

    /**
     * @param null|int $storeId
     * @throws WebapiException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function preProcessWebhook($storeId = null)
    {
        $this->setStoreId($storeId);

        $this->setCommonMetaData();
        $this->setHeaders();
        $this->verifyWebhook();
    }
}
