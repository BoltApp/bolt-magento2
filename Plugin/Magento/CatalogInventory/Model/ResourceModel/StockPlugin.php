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
namespace Bolt\Boltpay\Plugin\Magento\CatalogInventory\Model\ResourceModel;

use Bolt\Boltpay\Api\Data\ProductEventInterface;
use Bolt\Boltpay\Api\ProductEventManagerInterface;
use Bolt\Boltpay\Api\Data\ProductEventInterfaceFactory;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Logger\Logger;
use Magento\CatalogInventory\Model\ResourceModel\Stock;
use Magento\Catalog\Model\ResourceModel\Product\Website\Link as ProductWebsiteLink;

/**
 * Catalog ingestion product event processor after catalog inventory qty correction
 * (required if MSI is disabled)
 */
class StockPlugin
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
     * @var Config
     */
    private $config;

    /**
     * @var ProductWebsiteLink
     */
    private $productWebsiteLink;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param ProductEventManagerInterface $productEventManager
     * @param ProductEventInterfaceFactory $productEventFactory
     * @param Config $config
     * @param ProductWebsiteLink $productWebsiteLink
     * @param Logger $logger
     */
    public function __construct(
        ProductEventManagerInterface $productEventManager,
        ProductEventInterfaceFactory $productEventFactory,
        Config $config,
        ProductWebsiteLink $productWebsiteLink,
        Logger $logger
    ) {
        $this->productEventManager = $productEventManager;
        $this->productEventFactory = $productEventFactory;
        $this->config = $config;
        $this->productWebsiteLink = $productWebsiteLink;
        $this->logger = $logger;
    }


    /**
     * Publish bolt catalog product event after catalog inventory correction
     *
     * @param Stock $subject
     * @param $result
     * @param array $items
     * @param $websiteId
     * @param $operator
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCorrectItemsQty(
        Stock $subject,
        $result,
        array $items,
        $websiteId,
        $operator
    ): void {
        foreach ($items as $productId => $qty) {
            $this->processProductEvent((int)$productId);
        }
    }

    /**
     * Process product event
     *
     * @param int $productId
     * @return void
     */
    private function processProductEvent(int $productId): void
    {
        $websiteIds = $this->productWebsiteLink->getWebsiteIdsByProductId($productId);
        foreach ($websiteIds as $websiteId) {
            if ($this->config->getIsCatalogIngestionEnabled($websiteId)) {
                $this->productEventManager->publishProductEvent(
                    $productId,
                    ProductEventInterface::TYPE_UPDATE
                );
                //break, because product event already created and future websites check is not needed
                break;
            }
        }
    }
}
