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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Api\Data;

interface StoreAddressInterface
{
    /**
     * Get street address1.
     *
     * @api
     * @return string
     */
    public function getStreetAddress1();

    /**
     * Set street address1.
     *
     * @api
     * @param $streetAddress1
     *
     * @return $this
     */
    public function setStreetAddress1($streetAddress1);
    
    /**
     * Get street address2.
     *
     * @api
     * @return string|null
     */
    public function getStreetAddress2();

    /**
     * Set street address2.
     *
     * @api
     * @param $streetAddress2
     *
     * @return $this
     */
    public function setStreetAddress2($streetAddress2);
    
    /**
     * Get locality.
     *
     * @api
     * @return string
     */
    public function getLocality();

    /**
     * Set locality.
     *
     * @api
     * @param $locality
     *
     * @return $this
     */
    public function setLocality($locality);
    
    /**
     * Get region.
     *
     * @api
     * @return string
     */
    public function getRegion();

    /**
     * Set region.
     *
     * @api
     * @param $region
     *
     * @return $this
     */
    public function setRegion($region);
    
    /**
     * Get postal code.
     *
     * @api
     * @return string
     */
    public function getPostalCode();

    /**
     * Set postal code.
     *
     * @api
     * @param $postalCode
     *
     * @return $this
     */
    public function setPostalCode($postalCode);
    
    /**
     * Get country code.
     *
     * @api
     * @return string
     */
    public function getCountryCode();
    
    /**
     * Set country code.
     *
     * @api
     * @param $countryCode
     *
     * @return $this
     */
    public function setCountryCode($countryCode);
}
