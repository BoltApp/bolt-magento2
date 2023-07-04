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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Plugin\Magento\Inventory\Model\SourceItem\Command;

use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Model\CatalogIngestion\ProductEventProcessor;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ResourceConnection;

/**
 * Catalog ingestion product event processor after source items update
 */
class SourceItemSavePlugin
{
    /**
     * @var ProductEventProcessor
     */
    private $productEventProcessor;

    /**
     * @var Decider
     */
    private $featureSwitches;

    /**
     * @var array
     */
    private $beforeProductStatuses;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param ProductEventProcessor $productEventProcessor
     * @param Decider $featureSwitches
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ProductEventProcessor $productEventProcessor,
        Decider $featureSwitches,
        ResourceConnection $resourceConnection
    ) {
        $this->productEventProcessor = $productEventProcessor;
        $this->featureSwitches = $featureSwitches;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Save product salable statuses before source items save
     *
     * @param SourceItemsSaveInterface $subject
     * @param array $sourceItems
     * @return array[]
     * @throws LocalizedException
     */
    public function beforeExecute(
        SourceItemsSaveInterface $subject,
        array $sourceItems
    ): array
    {
        if (!$this->featureSwitches->isCatalogIngestionEnabled() || empty($sourceItems)) {
            return [$sourceItems];
        }
        $this->beforeProductStatuses = $this->productEventProcessor->getProductStatusesSourceItemsBased($sourceItems);
        return [$sourceItems];
    }

    /**
     * Process catalog ingestion product event after source items save
     *
     * @param SourceItemsSaveInterface $subject
     * @param $result
     * @param array $sourceItems
     * @return void
     * @throws LocalizedException
     */
    public function afterExecute(
        SourceItemsSaveInterface $subject,
        $result,
        array $sourceItems
    ): void
    {
        if (!$this->featureSwitches->isCatalogIngestionEnabled() ||
            empty($sourceItems) ||
            empty($this->beforeProductStatuses)
        ) {
            return;
        }

        /**
         * Magento use two ways to store the stock data legacy(stock items based) and native(source item based with MSI)
         * In magento 2.4.4 for case when magento updating source items based on legacy stock item in plugin:
         * https://github.com/magento/inventory/blob/f32b8f5e20627bdbf18e581727e3d5a7ccc17756/InventoryCatalog/Plugin/CatalogInventory/UpdateSourceItemAtLegacyStockItemSavePlugin.php
         * We have issue with non-actual source items data, because of transaction inside plugin above.
         * In this case we should ignore this plugin and actual data will be fetched based on stock item(legacy) in another plugin
         * https://github.com/BoltApp/bolt-magento2/blob/21fb4991fba6cadc428e3da6a0725d691f67ce1b/Plugin/Magento/CatalogInventory/Api/StockItemRepositoryPlugin.php
         */
        if ($this->resourceConnection->getConnection()->getTransactionLevel() > 0 &&
            !$this->featureSwitches->isCatalogIngestionInstancePipelineDisabled()
        ) {
            return;
        }

        $afterProductStatuses = $this->productEventProcessor->getProductStatusesSourceItemsBased($sourceItems);
        $this->productEventProcessor->processProductEventSourceItemsBased(
            $this->beforeProductStatuses,
            $afterProductStatuses,
            $sourceItems
        );
    }
}
