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
     * @var array
     */
    private $instantProductEventUpdatedList = [];

    /**
     * @param ProductEventManagerInterface $productEventManager
     * @param RunInstantProductEvent $runInstantProductEvent
     * @param Config $config
     * @param ProductFactory $productFactory
     * @param Logger $logger
     */
    public function __construct(
        ProductEventManagerInterface $productEventManager,
        RunInstantProductEvent $runInstantProductEvent,
        Config $config,
        ProductFactory $productFactory,
        Logger $logger
    ) {
        $this->productEventManager = $productEventManager;
        $this->runInstantProductEvent = $runInstantProductEvent;
        $this->config = $config;
        $this->productFactory = $productFactory;
        $this->logger = $logger;
    }

    /**
     * Update product events based on source items
     *
     * @param array $sourceItems
     * @return void
     */
    public function execute(array $sourceItems): void
    {
        try {
            foreach ($sourceItems as $sourceItem) {
                /** @var SourceItem $sourceItem */
                $productId = $this->productFactory->create()->getIdBySku($sourceItem->getSku());
                if ($this->config->getIsCatalogIngestionInstantEnabled() &&
                    !in_array($productId, $this->instantProductEventUpdatedList) &&
                    in_array(Events::STOCK_STATUS_CHANGES, $this->config->getCatalogIngestionEvents()) &&
                    $sourceItem->getOrigData('status') != $sourceItem->getData('status')
                ) {
                    $this->runInstantProductEvent->execute(
                        (int)$productId,
                        ProductEventInterface::TYPE_UPDATE
                    );
                    $this->instantProductEventUpdatedList[] = $productId;
                }

                if (!in_array($productId, $this->instantProductEventUpdatedList) &&
                    $this->config->getIsCatalogIngestionScheduleEnabled() &&
                    (
                        $sourceItem->getOrigData('status') != $sourceItem->getData('status') ||
                        $sourceItem->getOrigData('quantity') != $sourceItem->getData('quantity')
                    )
                ) {
                    $this->productEventManager->publishProductEvent(
                        (int)$productId,
                        ProductEventInterface::TYPE_UPDATE
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }
}
