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

namespace Bolt\Boltpay\Model\CatalogIngestion;

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

    private CONST API_REQUEST_API_URL = 'catalog/m2/store';

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
        $requestData = $this->dataObjectFactory->create();
        $requestData->setDynamicApiUrl(self::API_REQUEST_API_URL);
        $requestData->setApiKey($this->config->getApiKey($store->getId()));
        $requestData->setStatusOnly(true);
        $requestData->setHeaders(
            [
                'X-Publishable-Key' => $this->config->getPublishableKeyCheckout($store->getId())
            ]
        );
        $requestData->setRequestMethod(self::API_REQUEST_METHOD_TYPE);
        $requestData->setApiData([
            'store_code' => $storeCode
        ]);
        return $this->apiHelper->buildRequest($requestData);
    }
}
