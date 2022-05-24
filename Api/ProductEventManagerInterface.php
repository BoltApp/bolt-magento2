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

namespace Bolt\Boltpay\Api;

use Bolt\Boltpay\Api\Data\ProductEventInterface;

/**
 * Product event manager interface
 * @api
 */
interface ProductEventManagerInterface
{
    /**
     * Publish new product event
     *
     * @param int $productId
     * @param string $type
     * @return ProductEventInterface
     */
    public function publishProductEvent(int $productId, string $type): ProductEventInterface;

    /**
     * Delete product event
     *
     * @param int $productId
     * @return bool
     */
    public function deleteProductEvent(int $productId): bool;

    /**
     * Create async. job for product event consumer
     *
     * @param int $productId
     * @param string $type
     * @return string|null
     */
    public function publishProductEventAsyncJob(int $productId, string $type): ?string;

    /**
     * Request catalog product event to bolt
     *
     * @param ProductEventInterface $productEvent
     * @return bool
     */
    public function requestProductEvent(ProductEventInterface $productEvent): bool;
}
