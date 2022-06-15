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
use Magento\InventoryApi\Api\SourceItemsDeleteInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryIndexer\Plugin\InventoryApi\ReindexAfterSourceItemsDeletePlugin;

/**
 * Catalog ingestion product event processor after source items removing
 */
class SourceItemsDeletePlugin
{
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
     * @var ReindexAfterSourceItemsDeletePlugin
     */
    private $reindexAfterSourceItemsDeletePlugin;

    /**
     * @param ProductEventProcessor $productEventProcessor
     * @param ProductFactory $productFactory
     * @param ProductWebsiteLink $productWebsiteLink
     * @param Decider $featureSwitches
     * @param ReindexAfterSourceItemsDeletePlugin $reindexAfterSourceItemsDeletePlugin
     */
    public function __construct(
        ProductEventProcessor $productEventProcessor,
        ProductFactory $productFactory,
        ProductWebsiteLink $productWebsiteLink,
        Decider $featureSwitches,
        ReindexAfterSourceItemsDeletePlugin $reindexAfterSourceItemsDeletePlugin
    ) {
        $this->productEventProcessor = $productEventProcessor;
        $this->productFactory = $productFactory;
        $this->productWebsiteLink = $productWebsiteLink;
        $this->featureSwitches = $featureSwitches;
        $this->reindexAfterSourceItemsDeletePlugin = $reindexAfterSourceItemsDeletePlugin;
    }

    /**
     * Publish bolt catalog product event after source items removing
     *
     * @param SourceItemsDeleteInterface $subject
     * @param callable $proceed
     * @param array $sourceItems
     * @return void
     * @throws LocalizedException
     */
    public function aroundExecute(
        SourceItemsDeleteInterface $subject,
        callable $proceed,
        array $sourceItems
    ): void
    {
        if (empty($sourceItems) || !$this->featureSwitches->isCatalogIngestionEnabled()) {
            $proceed($sourceItems);
            return;
        }

        $beforeProductStatuses = $this->productEventProcessor->getProductStatusesSourceItemsBased($sourceItems);
        $proceed($sourceItems);
        $this->reindexAfterSourceItemsDeletePlugin->aroundExecute($subject, $proceed, $sourceItems);
        $afterProductStatuses = $this->productEventProcessor->getProductStatusesSourceItemsBased($sourceItems);
        foreach ($sourceItems as $sourceItem) {
            //set quantity to 0, because we are removing the source item
            $sourceItem->setData('quantity', 0);
        }
        $this->productEventProcessor->processProductEventSourceItemsBased(
            $beforeProductStatuses,
            $afterProductStatuses,
            $sourceItems
        );
    }
}
