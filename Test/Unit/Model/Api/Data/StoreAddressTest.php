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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api\Data;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\Api\Data\StoreAddress;

/**
 * Class StoreAddressTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\Data\StoreAddress
 */
class StoreAddressTest extends BoltTestCase
{
    const STREET_ADDRESS_1 = '2233 Flatbush Ave';
    const STREET_ADDRESS_2 = '#6';
    const LOCALITY = 'Brooklyn';
    const REGION = 'New York';
    const POSTALCODE = '11234';
    const COUNTRYCODE = 'US';

    /**
     * @var StoreAddress
     */
    protected $storeAddress;

    protected function setUpInternal()
    {
        $this->storeAddress = new StoreAddress();
        $this->storeAddress->setStreetAddress1(self::STREET_ADDRESS_1);
        $this->storeAddress->setStreetAddress2(self::STREET_ADDRESS_2);
        $this->storeAddress->setLocality(self::LOCALITY);
        $this->storeAddress->setRegion(self::REGION);
        $this->storeAddress->setPostalCode(self::POSTALCODE);
        $this->storeAddress->setCountryCode(self::COUNTRYCODE);
    }

    /**
     * @test
     * that getStreetAddress1 would return region.
     * @covers ::getStreetAddress1
     */
    public function getStreetAddress1_always_returnsStreetAddress1()
    {
        $this->assertEquals(self::STREET_ADDRESS_1, $this->storeAddress->getStreetAddress1());
    }
    
    /**
     * @test
     * that getStreetAddress2 would return region.
     * @covers ::getStreetAddress2
     */
    public function getStreetAddress2_always_returnsStreetAddress2()
    {
        $this->assertEquals(self::STREET_ADDRESS_2, $this->storeAddress->getStreetAddress2());
    }
    
    /**
     * @test
     * that getLocality would return region.
     * @covers ::getLocality
     */
    public function getLocality_always_returnsLocality()
    {
        $this->assertEquals(self::LOCALITY, $this->storeAddress->getLocality());
    }
    
    /**
     * @test
     * that getRegion would return region.
     * @covers ::getRegion
     */
    public function getRegion_always_returnsRegion()
    {
        $this->assertEquals(self::REGION, $this->storeAddress->getRegion());
    }
    
    /**
     * @test
     * that getPostalCode would return postal code.
     * @covers ::getPostalCode
     */
    public function getPostalCode_always_returnsPostalCode()
    {
        $this->assertEquals(self::POSTALCODE, $this->storeAddress->getPostalCode());
    }
    
    /**
     * @test
     * that getCountryCode would return country code
     * @covers ::getCountryCode
     */
    public function getCountryCode_always_returnsCountryCode()
    {
        $this->assertEquals(self::COUNTRYCODE, $this->storeAddress->getCountryCode());
    }

    /**
     * @test
     * that jsonSerialize would return an associative array of values
     * @covers ::jsonSerialize
     */
    public function jsonSerialize_always_returnsValues()
    {
        $result = $this->storeAddress->jsonSerialize();
        $this->assertEquals([
            'street_address1' => self::STREET_ADDRESS_1,
            'street_address2' => self::STREET_ADDRESS_2,
            'locality' => self::LOCALITY,
            'region' => self::REGION,
            'postal_code' => self::POSTALCODE,
            'country_code' => self::COUNTRYCODE
        ], $result);
    }
}
