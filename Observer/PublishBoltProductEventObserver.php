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
 *
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Bolt\Boltpay\Observer;

use Bolt\Boltpay\Model\CatalogIngestion\ProductEventProcessor;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Catalog\Model\Product;

/**
 * Publish product event after the product is saved
 */
class PublishBoltProductEventObserver implements ObserverInterface
{
    /**
     * @var ProductEventProcessor
     */
    private $productEventProcessor;

    /**
     * @param ProductEventProcessor $productEventProcessor
     */
    public function __construct(
        ProductEventProcessor $productEventProcessor
    ) {
        $this->productEventProcessor = $productEventProcessor;
    }

    /**
     * Process event on 'save_commit_after' event
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var Product $product */
        $product = $observer->getEvent()->getProduct();
        $this->productEventProcessor->processProductEventUpdateByProduct($product);
    }
}
