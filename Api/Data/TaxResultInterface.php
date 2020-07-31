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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Api\Data;

/**
 * Tax result interface. Subtotal amount field of the Tax response object.
 *
 * @api
 */
interface TaxResultInterface
{
    /**
     * Get subtotal tax amount.
     *
     * @api
     * @return int
     */
    public function getSubtotalAmount();

    /**
     * Set subtotal tax amount.
     *
     * @api
     * @param int $amount
     * @return $this
     */
    public function setSubtotalAmount($amount);
}
