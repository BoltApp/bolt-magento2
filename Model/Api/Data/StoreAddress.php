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

use Bolt\Boltpay\Api\Data\StoreAddressInterface;

/**
 * Class StoreAddress.
 *
 * @package Bolt\Boltpay\Model\Api\Data
 */
class StoreAddress implements StoreAddressInterface, \JsonSerializable
{
    /**
     * @var string
     */
    private $streetAddress1;

    /**
     * @var string|null
     */
    private $streetAddress2;

    /**
     * @var string
     */
    private $locality;

    /**
     * @var string
     */
    private $region;
    
    /**
     * @var string
     */
    private $postalCode;
    
    /**
     * @var string
     */
    private $countryCode;
    
    
    /**
     * Get street address1.
     *
     * @api
     * @return string
     */
    public function getStreetAddress1()
    {
        return $this->streetAddress1;
    }

    /**
     * Set street address1.
     *
     * @api
     * @param $streetAddress1
     *
     * @return $this
     */
    public function setStreetAddress1($streetAddress1)
    {
        $this->streetAddress1 = $streetAddress1;
        return $this;
    }

    /**
     * Get street address2.
     *
     * @api
     * @return string|null
     */
    public function getStreetAddress2()
    {
        return $this->streetAddress2;
    }

    /**
     * Set street address2.
     *
     * @api
     * @param $streetAddress2
     *
     * @return $this
     */
    public function setStreetAddress2($streetAddress2)
    {
        $this->streetAddress2 = $streetAddress2;
        return $this;
    }
    
    /**
     * Get locality.
     *
     * @api
     * @return string
     */
    public function getLocality()
    {
        return $this->locality;
    }

    /**
     * Set locality.
     *
     * @api
     * @param $locality
     *
     * @return $this
     */
    public function setLocality($locality)
    {
        $this->locality = $locality;
        return $this;
    }
    
    /**
     * Get region.
     *
     * @api
     * @return string
     */
    public function getRegion()
    {
        return $this->region;
    }

    /**
     * Set region.
     *
     * @api
     * @param $region
     *
     * @return $this
     */
    public function setRegion($region)
    {
        $this->region = $region;
        return $this;
    }
    
    /**
     * Get postal code.
     *
     * @api
     * @return string
     */
    public function getPostalCode()
    {
        return $this->postalCode;
    }

    /**
     * Set postal code.
     *
     * @api
     * @param $postalCode
     *
     * @return $this
     */
    public function setPostalCode($postalCode)
    {
        $this->postalCode = $postalCode;
        return $this;
    }
    
    /**
     * Get country code.
     *
     * @api
     * @return string
     */
    public function getCountryCode()
    {
        return $this->countryCode;
    }

    /**
     * Set country code.
     *
     * @api
     * @param $countryCode
     *
     * @return $this
     */
    public function setCountryCode($countryCode)
    {
        $this->countryCode = $countryCode;
        return $this;
    }
    
    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'street_address1' => $this->streetAddress1,
            'street_address2' => $this->streetAddress2,
            'locality' => $this->locality,
            'region' => $this->region,
            'postal_code' => $this->postalCode,
            'country_code' => $this->countryCode,
        ];
    }
}
