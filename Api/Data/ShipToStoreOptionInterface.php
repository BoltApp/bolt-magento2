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

namespace Bolt\Boltpay\Api\Data;

/**
 * @api
 */
interface ShipToStoreOptionInterface
{
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
     * Get store name.
     *
     * @api
     * @return string
     */
    public function getStoreName();

    /**
     * Set store name.
     *
     * @api
     * @param string $storeName
     * @return $this
     */
    public function setStoreName($storeName);
    
    /**
     * Get store address.
     *
     * @api
     * @return \Bolt\Boltpay\Api\Data\StoreAddressInterface
     */
    public function getAddress();

    /**
     * Set store address.
     *
     * @api
     * @param \Bolt\Boltpay\Api\Data\StoreAddressInterface $storeAddress
     * @return $this
     */
    public function setAddress($address);

    /**
     * Get distance.
     *
     * @api
     * @return float
     */
    public function getDistance();

    /**
     * Set distance.
     *
     * @api
     * @param float $distance
     * @return $this
     */
    public function setDistance($distance);

    /**
     * Get distance unit.
     *
     * @api
     * @return string
     */
    public function getDistanceUnit();

    /**
     * Set distance unit.
     *
     * @api
     * @param string $distanceUnit
     *
     * @return $this
     */
    public function setDistanceUnit($distanceUnit);
    
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
     * @param $taxAmount
     *
     * @return $this
     */
    public function setTaxAmount($taxAmount);

    /**
     * Set description
     * @api
     * @param string $description
     *
     * @return $this
     */
    public function setDescription($description);

    /**
     * Get description
     *
     * @api
     * @return string
     */
    public function getDescription();
}
