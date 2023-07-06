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
namespace Bolt\Boltpay\Plugin\Magento\CatalogInventory\Model\ResourceModel;

use Bolt\Boltpay\Model\CatalogIngestion\ProductEventProcessor;
use Magento\CatalogInventory\Model\ResourceModel\Stock;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Catalog ingestion product event processor after catalog inventory qty correction
 * (required if MSI is disabled)
 */
class StockPlugin
{
    /**
     * @var ProductEventProcessor
     */
    private $productEventProcessor;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @param ProductEventProcessor $productEventProcessor
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        ProductEventProcessor $productEventProcessor,
        ProductRepositoryInterface $productRepository
    ) {
        $this->productEventProcessor = $productEventProcessor;
        $this->productRepository = $productRepository;
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
        if (!empty($items)) {
            foreach ($items as $productId => $qty) {
                try {
                    $product = $this->productRepository->getById($productId);
                    //force update without changes check, because on this place we know that qty was changed
                    $this->productEventProcessor->processProductEventUpdateByProduct($product, true);
                } catch (NoSuchEntityException $e) {
                    continue;
                }
            }
        }
    }
}
