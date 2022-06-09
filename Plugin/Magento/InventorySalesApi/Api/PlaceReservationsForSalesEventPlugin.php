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

use Bolt\Boltpay\Model\CatalogIngestion\ProductEventProcessor;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\InventorySalesApi\Api\GetStockBySalesChannelInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\ObjectManager;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;

/**
 * Triggering catalog ingestion process during product inventory reservation update
 */
class PlaceReservationsForSalesEventPlugin
{
    /**
     * @var ProductEventProcessor
     */
    private $productEventProcessor;

    /**
     * @var GetStockBySalesChannelInterface
     */
    private $getStockBySalesChannel;

    /**
     * @var string
     */
    private $areProductsSalableClass;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @param ProductEventProcessor $productEventProcessor
     * @param GetStockBySalesChannelInterface $getStockBySalesChannel
     * @param string|null $areProductsSalable
     */
    public function __construct(
        ProductEventProcessor $productEventProcessor,
        GetStockBySalesChannelInterface $getStockBySalesChannel,
        string $areProductsSalableClass = null
    ) {
        $this->objectManager = ObjectManager::getInstance();
        $this->productEventProcessor = $productEventProcessor;
        $this->getStockBySalesChannel = $getStockBySalesChannel;
        $this->areProductsSalableClass = $areProductsSalableClass;
    }


    /**
     * @param PlaceReservationsForSalesEventInterface $subject
     * @param callable $proceed
     * @param array $items
     * @param SalesChannelInterface $salesChannel
     * @param SalesEventInterface $salesEvent
     * @return void
     * @throws NoSuchEntityException
     */
    public function aroundExecute(
        PlaceReservationsForSalesEventInterface $subject,
        callable $proceed,
        array $items,
        SalesChannelInterface $salesChannel,
        SalesEventInterface $salesEvent
    ): void {
        /** @var AreProductsSalableInterface $areProductsSalable */
        $areProductsSalable = $this->initAreProductsSalableClass();
        if (empty($items) || !$areProductsSalable) {
            $proceed($items, $salesChannel, $salesEvent);
            return;
        }
        $stockId = $this->getStockBySalesChannel->execute($salesChannel)->getStockId();
        $skus = [];
        foreach ($items as $item) {
            $skus[] = $item->getSku();
        }
        $productsSalableStatusOld = $areProductsSalable->execute($skus, $stockId);
        $proceed($items, $salesChannel, $salesEvent);
        $productsSalableStatus = $areProductsSalable->execute($skus, $stockId);
        $this->productEventProcessor->processProductEventSalableResultItemsBased(
            $productsSalableStatus,
            $productsSalableStatusOld
        );
    }

    /**
     * Init areProductsSalableClass instance, for Magento 2.2 support
     *
     * @return mixed|null
     */
    private function initAreProductsSalableClass()
    {
        if (!$this->areProductsSalableClass) {
            return null;
        }
        return (class_exists($this->areProductsSalableClass) || interface_exists($this->areProductsSalableClass))
            ? $this->objectManager->get($this->areProductsSalableClass) : null;
    }
}
