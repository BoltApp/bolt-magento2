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

use Bolt\Boltpay\Api\Data\ProductEventInterface;
use Bolt\Boltpay\Api\ProductEventManagerInterface;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Logger\Logger;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Inventory\Model\SourceItem;
use Magento\InventoryApi\Api\SourceItemsDeleteInterface;

/**
 * Catalog ingestion product event processor after source items removing
 */
class SourceItemsDeletePlugin
{
    /**
     * @var ProductEventManagerInterface
     */
    private $productEventManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param ProductEventManagerInterface $productEventManager
     * @param Config $config
     * @param ProductRepositoryInterface $productRepository
     * @param Logger $logger
     */
    public function __construct(
        ProductEventManagerInterface $productEventManager,
        Config $config,
        ProductRepositoryInterface $productRepository,
        Logger $logger
    ) {
        $this->productEventManager = $productEventManager;
        $this->config = $config;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    /**
     * Publish bolt catalog product event after source items removing
     *
     * @param SourceItemsDeleteInterface $subject
     * @param $result
     * @param array $sourceItems
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        SourceItemsDeleteInterface $subject,
        $result,
        array $sourceItems
    ): void
    {
        try {
            foreach ($sourceItems as $sourceItem) {
                /** @var SourceItem $sourceItem */
                $product = $this->productRepository->get($sourceItem->getSku());
                if ($this->config->getIsCatalogIngestionInstantEnabled()) {
                    $this->productEventManager->publishProductEventAsyncJob(
                        (int)$product->getId(),
                        ProductEventInterface::TYPE_UPDATE
                    );
                } elseif ($this->config->getIsCatalogIngestionScheduleEnabled()) {
                    $this->productEventManager->publishProductEvent(
                        (int)$product->getId(),
                        ProductEventInterface::TYPE_UPDATE
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }
}
