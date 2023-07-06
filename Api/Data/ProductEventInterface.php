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

namespace Bolt\Boltpay\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * Bolt product event data interface
 * @api
 */
interface ProductEventInterface extends ExtensibleDataInterface
{
    public const ID = 'id';

    public const PRODUCT_ID = 'product_id';

    public const TYPE = 'type';

    public const CREATED_AT = 'created_at';

    /**
     * Allowed product event operation types
     */
    public const TYPE_CREATE = 'create';
    public const TYPE_UPDATE = 'update';
    public const TYPE_DELETE = 'delete';

    /**
     * Get product event id.
     *
     * @api
     * @return string
     */
    public function getId();

    /**
     * Set product event id.
     *
     * @api
     * @param $id
     *
     * @return ProductEventInterface
     */
    public function setId($id): ProductEventInterface;

    /**
     * Get product id.
     *
     * @api
     * @return int
     */
    public function getProductId(): int;

    /**
     * Set product id.
     *
     * @api
     * @param int $productId
     *
     * @return ProductEventInterface
     */
    public function setProductId(int $productId): ProductEventInterface;

    /**
     * Get operation type.
     *
     * @api
     * @return string
     */
    public function getType(): string;

    /**
     * Set operation type.
     *
     * @api
     * @param string $type
     *
     * @return ProductEventInterface
     */
    public function setType(string $type): ProductEventInterface;

    /**
     * Get created at.
     *
     * @api
     * @return string
     */
    public function getCreatedAt(): string;

    /**
     * Set created at.
     *
     * @api
     * @param string $createdAt
     *
     * @return ProductEventInterface
     */
    public function setCreatedAt(string $createdAt): ProductEventInterface;

    /**
     * Retrieve existing extension attributes object.
     *
     * @return \Bolt\Boltpay\Api\Data\ProductEventExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set an extension attributes object.
     *
     * @param \Bolt\Boltpay\Api\Data\ProductEventExtensionInterface $extensionAttributes
     * @return ProductEventInterface
     */
    public function setExtensionAttributes(
        \Bolt\Boltpay\Api\Data\ProductEventExtensionInterface $extensionAttributes
    );
}
