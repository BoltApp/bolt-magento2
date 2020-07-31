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
