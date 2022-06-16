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

use Bolt\Boltpay\Api\Data\ProductEventInterface;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Model\Request;
use Bolt\Boltpay\Model\CatalogIngestion\Request\Product\DataProcessor as ProductDataProcessor;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

/**
 * Bolt event request builder main class
 */
class ProductEventRequestBuilder
{
    private CONST API_REQUEST_METHOD_TYPE = 'POST';

    public CONST API_REQUEST_API_URL = 'catalog/m2/product';

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
     * @var ProductDataProcessor
     */
    private $productDataProcessor;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Config $config
     * @param ApiHelper $apiHelper
     * @param DataObjectFactory $dataObjectFactory
     * @param ProductDataProcessor $productDataProcessor
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Config $config,
        ApiHelper $apiHelper,
        DataObjectFactory $dataObjectFactory,
        ProductDataProcessor $productDataProcessor,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->apiHelper = $apiHelper;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->productDataProcessor = $productDataProcessor;
        $this->storeManager = $storeManager;
    }

    /**
     * Returns product events request
     *
     * @param ProductEventInterface $productEvent
     * @param int $websiteId
     * @return Request
     * @throws LocalizedException
     */
    public function getRequest(ProductEventInterface $productEvent, int $websiteId): Request
    {
        $requestData = $this->getRequestData($productEvent, $websiteId);
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

    /**
     * Returns request, product event based
     *
     * @param ProductEventInterface $productEvent
     * @param int $websiteId
     * @return DataObject
     */
    private function getRequestData(ProductEventInterface $productEvent, int $websiteId): DataObject
    {
        try {
            $defaultStore = $this->storeManager->getWebsite($websiteId)->getDefaultStore();;
            $requestData = $this->dataObjectFactory->create();
            $requestData->setDynamicApiUrl(self::API_REQUEST_API_URL);
            $requestData->setApiKey($this->config->getApiKey($defaultStore->getId()));
            $requestData->setRequestMethod(self::API_REQUEST_METHOD_TYPE);
            $requestData->setStatusOnly(true);
            $requestData->setHeaders(
                [
                    'X-Publishable-Key' => $this->config->getPublishableKeyCheckout($defaultStore->getId())
                ]
            );
            $requestProductData = $this->productDataProcessor->getRequestProductData(
                $productEvent->getProductId(),
                $websiteId,
                $productEvent->getType()
            );
            $requestData->setApiData([
               'operation' => $productEvent->getType(),
               'timestamp' => strtotime($productEvent->getCreatedAt()),
               'product' => $requestProductData
            ]);
            return $requestData;
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Error during sending product event request, product_id: %1. Error message: %2',
                    $productEvent->getProductId(),
                    $e->getMessage()
                )
            );
        }
    }
}
