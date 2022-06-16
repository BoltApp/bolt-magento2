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
namespace Bolt\Boltpay\Plugin\Magento\InventorySalesApi\Api;

use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Model\CatalogIngestion\ProductEventProcessor;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\InventorySalesApi\Api\GetStockBySalesChannelInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventorySalesApi\Api\IsProductSalableInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Triggering catalog ingestion process during product inventory reservation update
 */
class PlaceReservationsForSalesEventPlugin
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
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var Decider
     */
    private $featureSwitches;

    /**
     * @var GetStockBySalesChannelInterface
     */
    private $getStockBySalesChannel;

    /**
     * @var IsProductSalableInterface
     */
    private $isProductsSalable;

    /**
     * @var array
     */
    private $beforeProductStatuses;

    /**
     * @param ProductEventProcessor $productEventProcessor
     * @param ModuleManager $moduleManager
     * @param Decider $featureSwitches
     */
    public function __construct(
        ProductEventProcessor $productEventProcessor,
        ModuleManager $moduleManager,
        Decider $featureSwitches
    ) {
        $this->objectManager = ObjectManager::getInstance();
        $this->productEventProcessor = $productEventProcessor;
        $this->moduleManager = $moduleManager;
        $this->featureSwitches = $featureSwitches;
        // Initialisation of Magento_InventorySalesApi classes for magento >= 2.3.*, which are missing in magento  <= 2.2.*
        // To prevent di compilation fails
        if ($this->moduleManager->isEnabled('Magento_InventorySalesApi')) {
            $this->getStockBySalesChannel = $this->objectManager
                ->get('Magento\InventorySalesApi\Api\GetStockBySalesChannelInterface');
            $this->isProductsSalable = $this->objectManager
                ->get('Magento\InventorySalesApi\Api\IsProductSalableInterface');
        }
    }

    /**
     * Save product salable statuses before placing inventory reservation
     *
     * @param PlaceReservationsForSalesEventInterface $subject
     * @param array $items
     * @param SalesChannelInterface $salesChannel
     * @param SalesEventInterface $salesEvent
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function beforeExecute(
        PlaceReservationsForSalesEventInterface $subject,
        array $items,
        SalesChannelInterface $salesChannel,
        SalesEventInterface $salesEvent
    ): array {
        if (!$this->featureSwitches->isCatalogIngestionEnabled() || empty($items)) {
            return [$items, $salesChannel, $salesEvent];
        }
        $stockId = $this->getStockBySalesChannel->execute($salesChannel)->getStockId();
        $this->beforeProductStatuses = $this->getProductStatusesByItems($items, (int)$stockId);
        return [$items, $salesChannel, $salesEvent];
    }

    /**
     * Process catalog ingestion product event after placing inventory reservation
     *
     * @param PlaceReservationsForSalesEventInterface $subject
     * @param $result
     * @param array $items
     * @param SalesChannelInterface $salesChannel
     * @param SalesEventInterface $salesEvent
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function afterExecute(
        PlaceReservationsForSalesEventInterface $subject,
        $result,
        array $items,
        SalesChannelInterface $salesChannel,
        SalesEventInterface $salesEvent
    ): void {
        if (!$this->featureSwitches->isCatalogIngestionEnabled() ||
            empty($items) ||
            empty($this->beforeProductStatuses)
        ) {
            return;
        }
        $stockId = $this->getStockBySalesChannel->execute($salesChannel)->getStockId();
        $afterStatuses = $this->getProductStatusesByItems($items, (int)$stockId);
        $this->productEventProcessor->processProductEventSalableItemsBased(
            $afterStatuses,
            $this->beforeProductStatuses
        );
    }

    /**
     * Get product statuses by reservation items
     *
     * @param array $items
     * @param int $stockId
     * @return array
     */
    private function getProductStatusesByItems(array $items, int $stockId): array
    {
        $statuses = [];
        foreach ($items as $item) {
            $statuses[$item->getSku()] = $this->isProductsSalable->execute($item->getSku(), $stockId);
        }
        return $statuses;
    }
}
