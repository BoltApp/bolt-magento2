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

use Bolt\Boltpay\Model\CatalogIngestion\Command\PublishSourceItemsProductEvent;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;

/**
 * Catalog ingestion product event processor after source items update
 */
class SourceItemSavePlugin
{
    /**
     * @var PublishSourceItemsProductEvent
     */
    private $publishSourceItemsProductEvent;

    /**
     * @param PublishSourceItemsProductEvent $publishSourceItemsProductEvent
     */
    public function __construct(PublishSourceItemsProductEvent $publishSourceItemsProductEvent)
    {
        $this->publishSourceItemsProductEvent = $publishSourceItemsProductEvent;
    }

    /**
     * Publish bolt catalog product event after source items update
     *
     * @param SourceItemsSaveInterface $subject
     * @param $result
     * @param array $sourceItems
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        SourceItemsSaveInterface $subject,
        $result,
        array $sourceItems
    ): void
    {
        $this->publishSourceItemsProductEvent->execute($sourceItems);
    }
}
