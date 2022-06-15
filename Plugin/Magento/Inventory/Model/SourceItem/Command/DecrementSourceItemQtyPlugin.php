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
namespace Bolt\Boltpay\Plugin\Magento\Inventory\Model\SourceItem\Command;

use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Model\CatalogIngestion\ProductEventProcessor;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\Website\Link as ProductWebsiteLink;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Inventory\Model\SourceItem\Command\DecrementSourceItemQty;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryIndexer\Plugin\InventoryApi\ReindexAfterDecrementSourceItemQty;

/**
 * Catalog ingestion product event processor after source items decrement
 */
class DecrementSourceItemQtyPlugin
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var ProductEventProcessor
     */
    private $productEventProcessor;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var ProductWebsiteLink
     */
    private $productWebsiteLink;

    /**
     * @var Decider
     */
    private $featureSwitches;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var ReindexAfterDecrementSourceItemQty
     */
    private $reindexAfterDecrementSourceItemQty;

    /**
     * @param ProductEventProcessor $productEventProcessor
     * @param ProductFactory $productFactory
     * @param ProductWebsiteLink $productWebsiteLink
     * @param Decider $featureSwitches
     * @param ModuleManager $moduleManager
     */
    public function __construct(
        ProductEventProcessor $productEventProcessor,
        ProductFactory $productFactory,
        ProductWebsiteLink $productWebsiteLink,
        Decider $featureSwitches,
        ModuleManager $moduleManager
    ) {
        $this->objectManager = ObjectManager::getInstance();
        $this->productEventProcessor = $productEventProcessor;
        $this->productFactory = $productFactory;
        $this->productWebsiteLink = $productWebsiteLink;
        $this->featureSwitches = $featureSwitches;
        $this->moduleManager = $moduleManager;
        if ($this->moduleManager->isEnabled('Magento_InventoryIndexer') &&
            class_exists('Magento\InventoryIndexer\Plugin\InventoryApi\ReindexAfterDecrementSourceItemQty')
        ) {
            $this->reindexAfterDecrementSourceItemQty = $this->objectManager
                ->get('Magento\InventoryIndexer\Plugin\InventoryApi\ReindexAfterDecrementSourceItemQty');
        }
    }

    /**
     * Publish bolt catalog product event after source items decrement update
     *
     * @param DecrementSourceItemQty $subject
     * @param callable $proceed
     * @param array $sourceItemDecrementData
     * @return void
     * @throws LocalizedException
     */
    public function aroundExecute(
        DecrementSourceItemQty $subject,
        callable $proceed,
        array $sourceItemDecrementData
    ): void
    {
        if (!$this->featureSwitches->isCatalogIngestionEnabled()) {
            $proceed($sourceItemDecrementData);
            return;
        }

        $sourceItems = array_column($sourceItemDecrementData, 'source_item');
        if (!empty($sourceItems)) {
            $beforeProductStatuses = $this->productEventProcessor->getProductStatusesSourceItemsBased($sourceItems);
            $proceed($sourceItemDecrementData);
            if ($this->reindexAfterDecrementSourceItemQty) {
                $this->reindexAfterDecrementSourceItemQty->afterExecute($subject, null, $sourceItemDecrementData);
            }
            $afterProductStatuses = $this->productEventProcessor->getProductStatusesSourceItemsBased($sourceItems);
            $this->productEventProcessor->processProductEventSourceItemsBased(
                $beforeProductStatuses,
                $afterProductStatuses,
                $sourceItems
            );
        }
    }
}
