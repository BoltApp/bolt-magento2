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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Api;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use \Magento\Catalog\Api\Data\ProductInterface;

interface GetProductInterface
{
    /**
     * Get user account associated with email
     *
     * @api
     *
     * @param string $sku
     *
     * @return ProductInterface
     *
     * @throws NoSuchEntityException
     * @throws WebapiException
     */
    public function execute($sku = ''): ProductInterface;
}
