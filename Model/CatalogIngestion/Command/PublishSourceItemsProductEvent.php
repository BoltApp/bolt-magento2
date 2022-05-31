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

namespace Bolt\Boltpay\Model\CatalogIngestion\Command;

use Bolt\Boltpay\Api\Data\ProductEventInterface;
use Bolt\Boltpay\Api\ProductEventManagerInterface;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Logger\Logger;
use Bolt\Boltpay\Model\Config\Source\Catalog\Ingestion\Events;
use Magento\Catalog\Model\ProductFactory;
use Magento\Inventory\Model\SourceItem;
use Magento\Catalog\Model\ResourceModel\Product\Website\Link as ProductWebsiteLink;

/**
 * Source items based product event publisher
 */
class PublishSourceItemsProductEvent
{
    /**
     * @var ProductEventManagerInterface
     */
    private $productEventManager;

    /**
     * @var RunInstantProductEvent
     */
    private $runInstantProductEvent;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ProductWebsiteLink
     */
    private $productWebsiteLink;

    /**
     * @var array
     */
    private $instantProductEventUpdatedList = [];

    /**
     * @param ProductEventManagerInterface $productEventManager
     * @param RunInstantProductEvent $runInstantProductEvent
     * @param Config $config
     * @param ProductFactory $productFactory
     * @param ProductWebsiteLink $productWebsiteLink
     * @param Logger $logger
     */
    public function __construct(
        ProductEventManagerInterface $productEventManager,
        RunInstantProductEvent $runInstantProductEvent,
        Config $config,
        ProductFactory $productFactory,
        ProductWebsiteLink $productWebsiteLink,
        Logger $logger
    ) {
        $this->productEventManager = $productEventManager;
        $this->runInstantProductEvent = $runInstantProductEvent;
        $this->config = $config;
        $this->productFactory = $productFactory;
        $this->productWebsiteLink = $productWebsiteLink;
        $this->logger = $logger;
    }

    /**
     * Update product events based on source items
     *
     * @param array $sourceItems
     * @param bool $isDelete
     * @return void
     */
    public function execute(array $sourceItems, bool $isDelete = false): void
    {
        try {
            foreach ($sourceItems as $sourceItem) {
                /** @var SourceItem $sourceItem */
                $productId = $this->productFactory->create()->getIdBySku($sourceItem->getSku());
                $websiteIds = $this->productWebsiteLink->getWebsiteIdsByProductId($productId);
                foreach ($websiteIds as $websiteId) {
                    $this->processProductEvent($sourceItem, (int)$productId, (int)$websiteId, $isDelete);
                }
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }

    /**
     * Process product event for source item and website
     *
     * @param SourceItem $sourceItem
     * @param int $productId
     * @param int $websiteId
     * @param bool $isDelete
     * @return void
     */
    private function processProductEvent(SourceItem $sourceItem, int $productId, int $websiteId, bool $isDelete): void
    {
        if (!$this->config->getIsCatalogIngestionEnabled($websiteId)) {
            return;
        }

        if ($this->config->getIsCatalogIngestionInstantEnabled($websiteId) &&
            !in_array($productId, $this->instantProductEventUpdatedList) &&
            in_array(Events::STOCK_STATUS_CHANGES, $this->config->getCatalogIngestionEvents($websiteId)) &&
            ($sourceItem->getOrigData('status') != $sourceItem->getData('status') || $isDelete)
        ) {
            $this->runInstantProductEvent->execute(
                $productId,
                ProductEventInterface::TYPE_UPDATE,
                $websiteId
            );
            $this->instantProductEventUpdatedList[] = $productId;
        }

        if (!in_array($productId, $this->instantProductEventUpdatedList) &&
        (
            $sourceItem->getOrigData('status') != $sourceItem->getData('status') ||
            $sourceItem->getOrigData('quantity') != $sourceItem->getData('quantity') ||
            $isDelete
        )
        ) {
            $this->productEventManager->publishProductEvent(
                $productId,
                ProductEventInterface::TYPE_UPDATE
            );
        }
    }
}
