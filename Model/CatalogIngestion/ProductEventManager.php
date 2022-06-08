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

use Bolt\Boltpay\Api\ProductEventManagerInterface;
use Bolt\Boltpay\Api\Data\ProductEventInterface;
use Bolt\Boltpay\Api\ProductEventRepositoryInterface;
use Bolt\Boltpay\Api\Data\ProductEventInterfaceFactory;
use Bolt\Boltpay\Model\CatalogIngestion\ProductEventPublisher;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Logger\Logger;
use Bolt\Boltpay\Model\CatalogIngestion\ProductEventRequestBuilder;
use Bolt\Boltpay\Helper\Config;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Product Event
 */
class ProductEventManager implements ProductEventManagerInterface
{
    /**
     * @var ProductEventInterfaceFactory
     */
    private $productEventFactory;

    /**
     * @var ProductEventRepositoryInterface
     */
    private $productEventRepository;

    /**
     * @var ProductEventPublisher
     */
    private $productEventPublisher;

    /**
     * @var ProductEventRequestBuilder
     */
    private $productEventRequestBuilder;

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param ProductEventInterfaceFactory $productEventFactory
     * @param ProductEventRepositoryInterface $productEventRepository
     * @param ProductEventPublisher $productEventPublisher
     * @param ProductEventRequestBuilder $productEventRequestBuilder
     * @param ApiHelper $apiHelper
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     * @param DateTime $dateTime
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(
        ProductEventInterfaceFactory $productEventFactory,
        ProductEventRepositoryInterface $productEventRepository,
        ProductEventPublisher $productEventPublisher,
        ProductEventRequestBuilder $productEventRequestBuilder,
        ApiHelper $apiHelper,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        DateTime $dateTime,
        Config $config,
        Logger $logger
    ) {
        $this->productEventFactory = $productEventFactory;
        $this->productEventRepository = $productEventRepository;
        $this->productEventPublisher = $productEventPublisher;
        $this->productEventRequestBuilder = $productEventRequestBuilder;
        $this->apiHelper = $apiHelper;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->dateTime = $dateTime;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function publishProductEvent(int $productId,  string $type): ProductEventInterface
    {
        try {
            $productEvent = $this->productEventRepository->getByProductId($productId);
        } catch (NoSuchEntityException $e) {
            $productEvent = $this->productEventFactory->create();
        }

        if (!$productEvent->getId()) {
            $productEvent->setProductId($productId);
            $productEvent->setType($type);
            $this->productEventRepository->save($productEvent);
        }

        return $productEvent;
    }

    /**
     * @inheritDoc
     */
    public function deleteProductEvent(int $productId): bool
    {
        $this->productEventRepository->deleteByProductId($productId);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function publishProductEventAsyncJob(int $productId,  string $type): string
    {
        return $this->productEventPublisher->publishBulk(
            $productId,
            $type,
            $this->dateTime->formatDate(true)
        );
    }

    /**
     * @inheritDoc
     */
    public function sendProductEvent(ProductEventInterface $productEvent): bool
    {
        if ($productEvent->getType() == ProductEventInterface::TYPE_DELETE) {
            $websites = $this->storeManager->getWebsites();
            foreach ($websites as $website) {
                $websiteIds[] = $website->getId();
            }
        } else {
            $product = $this->productRepository->getById($productEvent->getProductId());
            $websiteIds = $product->getWebsiteIds();
        }

        foreach ($websiteIds as $websiteId) {
            if (!$this->config->getIsCatalogIngestionEnabled($websiteId)) {
                continue;
            }
            $request = $this->productEventRequestBuilder->getRequest($productEvent, (int)$websiteId);
            $this->apiHelper->sendRequest($request);
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function runInstantProductEvent(int $productId, string $type, int $websiteId = null): void
    {
        if ($this->config->getIsCatalogIngestionInstantAsyncEnabled($websiteId)) {
            $this->publishProductEventAsyncJob($productId, $type);
        } else {
            $productEvent = $this->productEventFactory->create();
            $productEvent->setProductId($productId);
            $productEvent->setType($type);
            $productEvent->setCreatedAt($this->dateTime->formatDate(true));
            $this->sendProductEvent($productEvent);
        }
    }
}
