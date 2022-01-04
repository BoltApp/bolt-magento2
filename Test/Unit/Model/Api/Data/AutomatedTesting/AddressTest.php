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

namespace Bolt\Boltpay\Test\Unit\Model\Api\Data\AutomatedTesting;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\Address;

/**
 * Class AddressTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api\Data\AutomatedTesting
 */
class AddressTest extends BoltTestCase
{
    /**
     * @var Address
     */
    protected $address;

    protected function setUpInternal()
    {
        $this->address = new Address();
        $this->address->setFirstName('firstName');
        $this->address->setLastName('lastName');
        $this->address->setStreet('street');
        $this->address->setCity('city');
        $this->address->setPostalCode('11111');
        $this->address->setCountry('US');
        $this->address->setRegion('region');
        $this->address->setTelephone('111111');
    }

    /**
     * @test
     */
    public function setAndGetFirstName()
    {
        $this->assertEquals('firstName', $this->address->getFirstName());
    }

    /**
     * @test
     */
    public function setAndGetLastName()
    {
        $this->assertEquals('lastName', $this->address->getLastName());
    }

    /**
     * @test
     */
    public function setAndGetStreet()
    {
        $this->assertEquals('street', $this->address->getStreet());
    }

    /**
     * @test
     */
    public function setAndGetCity()
    {
        $this->assertEquals('city', $this->address->getCity());
    }

    /**
     * @test
     */
    public function setAndGetPostalCode()
    {
        $this->assertEquals('11111', $this->address->getPostalCode());
    }

    /**
     * @test
     */
    public function setAndGetCountry()
    {
        $this->assertEquals('US', $this->address->getCountry());
    }

    /**
     * @test
     */
    public function setAndGetTelephone()
    {
        $this->assertEquals('111111', $this->address->getTelephone());
    }

    /**
     * @test
     */
    public function jsonSerialize()
    {
        $result = $this->address->jsonSerialize();
        $this->assertEquals([
            'firstName' => 'firstName',
            'lastName' => 'lastName',
            'street' => 'street',
            'city' => 'city',
            'region' => 'region',
            'postalCode' => '11111',
            'telephone' => '111111',
            'country' => 'US',

        ], $result);
    }
}
