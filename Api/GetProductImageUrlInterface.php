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

namespace Bolt\Boltpay\Api;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;

interface GetProductImageUrlInterface
{
    const DEFAULT_IMAGE_ID = 'product_page_image_small';

    /**
     * Get product image url and stock information for specified product
     *
     * @api
     *
     * @param int $productId
     * @param string $imageId
     *
     * @return string
     *
     * @throws NoSuchEntityException
     * @throws WebapiException
     */
    public function execute(int $productId, string $imageId = self::DEFAULT_IMAGE_ID): string;
}
