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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Api\Data\ShippingOptionInterface;

/**
 * Class ShippingOption. Represents a single shipping option object.
 *
 * @package Bolt\Boltpay\Model\Api\Data
 */
class ShippingOption implements ShippingOptionInterface
{
    /**
     * @var string
     */
    private $service;

    /**
     * @var int
     */
    private $cost;

    /**
     * @var string
     */
    private $reference;

    /**
     * @var int
     */
    private $tax_amount;

    /**
     * Get shipping service.
     *
     * @api
     * @return string
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * Set shipping service.
     *
     * @api
     * @param string $service
     *
     * @return $this
     */
    public function setService($service)
    {
        $this->service = $service;
        return $this;
    }

    /**
     * Get shipping cost.
     *
     * @api
     * @return int
     */
    public function getCost()
    {
        return $this->cost;
    }

    /**
     * Set shipping cost.
     *
     * @api
     * @param int $cost
     *
     * @return $this
     */
    public function setCost($cost)
    {
        $this->cost = $cost;
        return $this;
    }

    /**
     * Get shipping reference.
     *
     * @api
     * @return string
     */
    public function getReference()
    {
        return $this->reference;
    }

    /**
     * Set shipping reference.
     *
     * @api
     * @param $reference
     *
     * @return $this
     */
    public function setReference($reference)
    {
        $this->reference = $reference;
        return $this;
    }

    /**
     * Get shipping tax.
     *
     * @api
     * @return int
     */
    public function getTaxAmount()
    {
        return $this->tax_amount;
    }

    /**
     * Set shipping tax.
     *
     * @api
     * @param $tax_amount
     *
     * @return $this
     */
    public function setTaxAmount($tax_amount)
    {
        $this->tax_amount = $tax_amount;
        return $this;
    }
}
