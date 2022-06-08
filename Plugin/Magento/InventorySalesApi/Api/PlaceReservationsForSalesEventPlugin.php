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
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\GetStockBySalesChannelInterface;
use Magento\Framework\Exception\NoSuchEntityException;

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
     * @var AreProductsSalableInterface
     */
    private $areProductsSalable;

    /**
     * @param ProductEventProcessor $productEventProcessor
     * @param GetStockBySalesChannelInterface $getStockBySalesChannel
     * @param AreProductsSalableInterface $areProductsSalable
     */
    public function __construct(
        ProductEventProcessor $productEventProcessor,
        GetStockBySalesChannelInterface $getStockBySalesChannel,
        AreProductsSalableInterface $areProductsSalable
    ) {
        $this->productEventProcessor = $productEventProcessor;
        $this->getStockBySalesChannel = $getStockBySalesChannel;
        $this->areProductsSalable = $areProductsSalable;
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
        if (empty($items)) {
            return;
        }
        $stockId = $this->getStockBySalesChannel->execute($salesChannel)->getStockId();
        $skus = [];
        foreach ($items as $item) {
            $skus[] = $item->getSku();
        }

        $productsSalableStatusOld = $this->areProductsSalable->execute($skus, $stockId);
        $proceed($items, $salesChannel, $salesEvent);
        $productsSalableStatus = $this->areProductsSalable->execute($skus, $stockId);
        $this->productEventProcessor->processProductEventSalableResultItemsBased(
            $productsSalableStatus,
            $productsSalableStatusOld
        );
    }
}
