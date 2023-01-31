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

namespace Bolt\Boltpay\Model\StoreConfiguration;

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Model\Request;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;

/**
 * Bolt stores configuration request builder class
 */
class StoreConfigurationRequestBuilder
{
    private CONST API_REQUEST_METHOD_TYPE = 'POST';

    public CONST API_REQUEST_API_URL = 'catalog/m2/store';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @param Config $config
     * @param ApiHelper $apiHelper
     * @param DataObjectFactory $dataObjectFactory
     * @param StoreRepositoryInterface $storeRepository
     */
    public function __construct(
        Config $config,
        ApiHelper $apiHelper,
        DataObjectFactory $dataObjectFactory,
        StoreRepositoryInterface $storeRepository
    ) {
        $this->config = $config;
        $this->apiHelper = $apiHelper;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->storeRepository = $storeRepository;
    }

    /**
     * Returns store configuration updated request
     *
     * @param string $storeCode
     * @return Request
     * @throws LocalizedException
     */
    public function getRequest(string $storeCode): Request
    {
        $store = $this->storeRepository->get($storeCode);
        $apiKey = $this->config->getApiKey($store->getId());
        $publishKey = $this->config->getPublishableKeyCheckout($store->getId());
        if (!$apiKey || !$publishKey) {
            throw new LocalizedException(
                __('Bolt API Key or Publishable Key - Multi Step is not configured')
            );
        }
        $requestData = $this->dataObjectFactory->create();
        $requestData->setDynamicApiUrl(self::API_REQUEST_API_URL);
        $requestData->setApiKey($apiKey);
        $requestData->setStatusOnly(true);
        $requestData->setHeaders(
            [
                'X-Publishable-Key' => $publishKey
            ]
        );
        $requestData->setRequestMethod(self::API_REQUEST_METHOD_TYPE);
        $requestData->setApiData([
            'store_code' => $storeCode
        ]);
        return $this->apiHelper->buildRequest($requestData);
    }

    /**
     * Check whether the response in successful
     *
     * @param int $responseStatus
     * @return boolean
     */
    public function isSuccessfulResponseStatus(int $responseStatus)
    {
        $restype = floor($responseStatus / 100);
        if ($restype == 2 || $restype == 1) { // Shouldn't 3xx count as success as well ???
            return true;
        }

        return false;
    }
}
