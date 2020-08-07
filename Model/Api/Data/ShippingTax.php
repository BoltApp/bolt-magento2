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

namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Api\Data\ShippingTaxInterface;

/**
 * Class ShippingTax. Tax property of the Shipping and Tax.
 * @package Bolt\Boltpay\Model\Api\Data
 */
class ShippingTax implements ShippingTaxInterface
{
    /**
     * @var int
     */
    private $amount;

    /**
     * Get tax amount.
     *
     * @api
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Set tax amount.
     *
     * @api
     * @param int $amount
     *
     * @return $this
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
        return $this;
    }
}
