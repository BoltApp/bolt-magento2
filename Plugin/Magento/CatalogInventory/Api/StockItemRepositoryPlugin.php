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

use Bolt\Boltpay\Model\CatalogIngestion\ProductEventProcessor;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;

/**
 * Catalog ingestion product event processor after stock item saving process
 * (required if MSI is disabled)
 */
class StockItemRepositoryPlugin
{
    /**
     * @var ProductEventProcessor
     */
    private $productEventProcessor;

    /**
     * @param ProductEventProcessor $productEventProcessor
     */
    public function __construct(
        ProductEventProcessor $productEventProcessor
    ) {
        $this->productEventProcessor = $productEventProcessor;
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
        $oldStockItem = ($stockItem->getItemId()) ?
            $subject->get($stockItem->getItemId()) : null;

        /** @var StockItemInterface $stockItemUpdated */
        $stockItemUpdated = $proceed($stockItem);
        $this->productEventProcessor->processProductEventStockItemBased($stockItemUpdated, $oldStockItem);
        return $stockItemUpdated;
    }
}
