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

namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Api\Data\ShipToStoreOptionInterface;

/**
 * Class ShipToStoreOption.
 *
 * @package Bolt\Boltpay\Model\Api\Data
 */
class ShipToStoreOption implements ShipToStoreOptionInterface, \JsonSerializable
{
    /**
     * @var string
     */
    private $reference;

    /**
     * @var int
     */
    private $cost;

    /**
     * @var string
     */
    private $storeName;

    /**
     * @var \Bolt\Boltpay\Api\Data\StoreAddressInterface
     */
    private $address;
    
    /**
     * @var float
     */
    private $distance;
    
    /**
     * @var string
     */
    private $distanceUnit;
    
    /**
     * @var int
     */
    private $taxAmount;

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
     * @return $this
     */
    public function setCost($cost)
    {
        $this->cost = $cost;
        return $this;
    }
    
    /**
     * Get store name.
     *
     * @api
     * @return string
     */
    public function getStoreName()
    {
        return $this->storeName;
    }

    /**
     * Set store name.
     *
     * @api
     * @param string $storeName
     * @return $this
     */
    public function setStoreName($storeName)
    {
        $this->storeName = $storeName;
        return $this;
    }
    
    /**
     * Get store address.
     *
     * @api
     * @return \Bolt\Boltpay\Api\Data\StoreAddressInterface
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Set store address.
     *
     * @api
     * @param \Bolt\Boltpay\Api\Data\StoreAddressInterface $storeAddress
     * @return $this
     */
    public function setAddress($address)
    {
        $this->address = $address;
        return $this;
    }

    /**
     * Get distance.
     *
     * @api
     * @return float
     */
    public function getDistance()
    {
        return $this->distance;
    }

    /**
     * Set distance.
     *
     * @api
     * @param float $distance
     * @return $this
     */
    public function setDistance($distance)
    {
        $this->distance = $distance;
        return $this;
    }

    /**
     * Get distance unit.
     *
     * @api
     * @return string
     */
    public function getDistanceUnit()
    {
        return $this->distanceUnit;
    }

    /**
     * Set distance unit.
     *
     * @api
     * @param string $distanceUnit
     *
     * @return $this
     */
    public function setDistanceUnit($distanceUnit)
    {
        $this->distanceUnit = $distanceUnit;
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
        return $this->taxAmount;
    }

    /**
     * Set shipping tax.
     *
     * @api
     * @param $taxAmount
     *
     * @return $this
     */
    public function setTaxAmount($taxAmount)
    {
        $this->taxAmount = $taxAmount;
        return $this;
    }
    
    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'reference' => $this->reference,
            'cost' => $this->cost,
            'store_name' => $this->storeName,
            'address' => $this->address,
            'distance' => $this->distance,
            'distance_unit' => $this->distanceUnit,
            'tax_amount' => $this->taxAmount
        ];
    }
}
