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
namespace Bolt\Boltpay\Plugin\Magento\CatalogInventory\Api;

use Bolt\Boltpay\Api\Data\ProductEventInterface;
use Bolt\Boltpay\Api\ProductEventManagerInterface;
use Bolt\Boltpay\Api\Data\ProductEventInterfaceFactory;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Model\Config\Source\Catalog\Ingestion\Events;
use Bolt\Boltpay\Logger\Logger;
use Bolt\Boltpay\Model\CatalogIngestion\Command\RunInstantProductEvent;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Catalog ingestion product event processor after stock item saving process
 * (required if MSI is disabled)
 */
class StockItemRepositoryPlugin
{
    /**
     * @var ProductEventManagerInterface
     */
    private $productEventManager;

    /**
     * @var ProductEventInterfaceFactory
     */
    private $productEventFactory;

    /**
     * @var RunInstantProductEvent
     */
    private $runInstantProductEvent;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param ProductEventManagerInterface $productEventManager
     * @param ProductEventInterfaceFactory $productEventFactory
     * @param RunInstantProductEvent $runInstantProductEvent
     * @param ProductRepositoryInterface $productRepository
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(
        ProductEventManagerInterface $productEventManager,
        ProductEventInterfaceFactory $productEventFactory,
        RunInstantProductEvent $runInstantProductEvent,
        ProductRepositoryInterface $productRepository,
        Config $config,
        Logger $logger
    ) {
        $this->productEventManager = $productEventManager;
        $this->productEventFactory = $productEventFactory;
        $this->runInstantProductEvent = $runInstantProductEvent;
        $this->productRepository = $productRepository;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Publish product event after stock item save process
     *
     * @param StockItemRepositoryInterface $subject
     * @param callable $proceed
     * @param StockItemInterface $stockItem
     * @return StockItemInterface
     */
    public function aroundSave(
        StockItemRepositoryInterface $subject,
        callable $proceed,
        StockItemInterface $stockItem
    ): StockItemInterface {
        $currentStock = ($stockItem->getItemId()) ?
            $subject->get($stockItem->getItemId()) : null;

        /** @var StockItemInterface $stockItemUpdated */
        $stockItemUpdated = $proceed($stockItem);
        try {
            if (!$currentStock ||
                $currentStock->getQty() != $stockItemUpdated->getQty() ||
                $currentStock->getIsInStock() != $stockItemUpdated->getIsInStock() ||
                $currentStock->getManageStock() != $stockItemUpdated->getManageStock()
            ) {
                if ($currentStock &&
                    $this->config->getIsCatalogIngestionInstantEnabled() &&
                    in_array(Events::STOCK_STATUS_CHANGES, $this->config->getCatalogIngestionEvents()) &&
                    $currentStock->getIsInStock() != $stockItemUpdated->getIsInStock()
                ) {
                    $this->runInstantProductEvent->execute(
                        $stockItem->getProductId(),
                        ProductEventInterface::TYPE_UPDATE
                    );
                } elseif ($this->config->getIsCatalogIngestionScheduleEnabled()) {
                    $this->productEventManager->publishProductEvent(
                        (int)$stockItem->getProductId(),
                        (!$currentStock) ? ProductEventInterface::TYPE_CREATE
                            : ProductEventInterface::TYPE_UPDATE
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

        return $stockItemUpdated;
    }
}
