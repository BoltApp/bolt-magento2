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

use Bolt\Boltpay\Api\Data\ShippingOptionsInterface;
use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Bolt\Boltpay\Api\Data\ShippingTaxInterface;

/**
 * Class ShippingOptions. Shipping options property of the Shipping and Tax.
 *
 * @package Bolt\Boltpay\Model\Api\Data
 */
class ShippingOptions implements ShippingOptionsInterface
{
    /**
     * @var array
     */
    private $shippingOptions = [];

    /**
     * @var ShippingTaxInterface
     */
    private $taxResult;

    /**
     * Get all available shipping options.
     *
     * @api
     * @return ShippingOptionInterface[]
     */
    public function getShippingOptions()
    {
        return $this->shippingOptions;
    }

    /**
     * Set available shipping options.
     *
     * @api
     * @param ShippingOptionInterface[]
     * @return $this
     */
    public function setShippingOptions($shippingOptions)
    {
        $this->shippingOptions = $shippingOptions;
        return $this;
    }

    /**
     * Get order tax result.
     *
     * @api
     * @return ShippingTaxInterface
     */
    public function getTaxResult()
    {
        return $this->taxResult;
    }

    /**
     * Set available shipping options.
     *
     * @api
     * @param ShippingTaxInterface
     *
     * @return $this
     */
    public function setTaxResult($taxResult)
    {
        $this->taxResult = $taxResult;
        return $this;
    }

    /**
     * Add amount to shipping options.
     *
     * @api
     * @param int $amount
     *
     * @return $this
     */
    public function addAmountToShippingOptions($amount)
    {
        foreach ($this->getShippingOptions() as $option) {
            $option->setCost($option->getCost() + $amount);
        }

        return $this;
    }
}
