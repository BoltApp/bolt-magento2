<?php
/**
 * Copyright © 2013-2017 Bold, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */


namespace Bolt\Boltpay\Api\Data;

/**
 * Shipping option interface. Defines shipping option object.
 *
 * @api
 */
interface ShippingOptionInterface
{

    /**
     * Get shipping service.
     *
     * @api
     * @return string
     */
    public function getService();

    /**
     * Set shipping service.
     *
     * @api
     * @param string $service
     * @return $this
     */
    public function setService($service);

    /**
     * Get shipping cost.
     *
     * @api
     * @return int
     */
    public function getCost();

    /**
     * Set shipping cost.
     *
     * @api
     * @param int $cost
     * @return $this
     */
    public function setCost($cost);

    /**
     * Get shipping reference.
     *
     * @api
     * @return string
     */
    public function getReference();

    /**
     * Set shipping reference.
     *
     * @api
     * @param $reference
     *
     * @return $this
     */
    public function setReference($reference);

    /**
     * Get shipping tax.
     *
     * @api
     * @return int
     */
    public function getTaxAmount();

    /**
     * Set shipping tax.
     *
     * @api
     * @param $tax_amount
     *
     * @return $this
     */
    public function setTaxAmount($tax_amount);
}
