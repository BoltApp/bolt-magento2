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

use Bolt\Boltpay\Model\CatalogIngestion\ProductEventProcessor;
use Magento\Inventory\Model\SourceItem\Command\DecrementSourceItemQty;

/**
 * Catalog ingestion product event processor after source items decrement
 */
class DecrementSourceItemQtyPlugin
{
    /**
     * @var ProductEventProcessor
     */
    private $productEventProcessor;

    /**
     * @param ProductEventProcessor $productEventProcessor
     */
    public function __construct(ProductEventProcessor $productEventProcessor)
    {
        $this->productEventProcessor = $productEventProcessor;
    }

    /**
     * Publish bolt catalog product event after source items decrement update
     *
     * @param DecrementSourceItemQty $subject
     * @param $result
     * @param array $sourceItemDecrementData
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        DecrementSourceItemQty $subject,
        $result,
        array $sourceItemDecrementData
    ): void
    {
        $sourceItems = array_column($sourceItemDecrementData, 'source_item');
        if (!empty($sourceItems)) {
            $this->productEventProcessor->processProductEventSourceItemsBased($sourceItems);
        }
    }
}
