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

namespace Bolt\Boltpay\Test\Unit\Controller\Shipping;

use Bolt\Boltpay\Controller\Shipping\Prefetch;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Geolocation;
use Bolt\Boltpay\Model\Api\ShippingMethods;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Zend\Log\Filter\Mock;

/**
 * Class PrefetchTest
 * @package Bolt\Boltpay\Test\Unit\Controller\Shipping
 * @coversDefaultClass \Bolt\Boltpay\Controller\Shipping\Prefetch
 */
class PrefetchTest extends TestCase
{
    const COUNTRY = 'Canada';
    const REGION = 'Arctic';
    const POSTAL_CODE = 'H0H 0H0';
    const LOCALITY = 'North Pole';
    const QUOTE_ID = '1234';
    const CITY = 'Santaville';
    const COUNTRY_CODE = 'NP';
    const STREET1 = '123 Candy Cane Way';
    const STREET2 = 'Unit 456';

    /**
     * @var Context
     */
    private $context;

    /**
     * @var MockObject|ShippingMethods
     */
    private $shippingMethods;

    /**
     * @var MockObject|Cart
     */
    private $cartHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var MockObject|Config
     */
    private $configHelper;

    /**
     * @var MockObject|Session
     */
    private $customerSession;

    /**
     * @var MockObject|Geolocation
     */
    private $geolocation;

    /**
     * @var MockObject|Prefetch
     */
    private $currentMock;

    public function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    private function initRequiredMocks()
    {
        $this->context = $this->createMock(Context::class);
        $this->shippingMethods = $this->createMock(ShippingMethods::class);
        $this->cartHelper = $this->createMock(Cart::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->configHelper = $this->createMock(Config::class);
        $this->customerSession = $this->createMock(Session::class);
        $this->geolocation = $this->createMock(Geolocation::class);
    }

    private function initCurrentMock()
    {
        $this->currentMock = $this->getMockBuilder(Prefetch::class)
            ->setMethods(null)
            ->setConstructorArgs([
                $this->context,
                $this->shippingMethods,
                $this->cartHelper,
                $this->bugsnag,
                $this->configHelper,
                $this->customerSession,
                $this->geolocation,
            ])
            ->getMock();

        return $this->currentMock;
    }

    /**
     * @test
     * that constructor sets internal properties
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $instance = new Prefetch(
            $this->context,
            $this->shippingMethods,
            $this->cartHelper,
            $this->bugsnag,
            $this->configHelper,
            $this->customerSession,
            $this->geolocation
        );
        
        $this->assertAttributeEquals($this->shippingMethods, 'shippingMethods', $instance);
        $this->assertAttributeEquals($this->cartHelper, 'cartHelper', $instance);
        $this->assertAttributeEquals($this->bugsnag, 'bugsnag', $instance);
        $this->assertAttributeEquals($this->configHelper, 'configHelper', $instance);
    }

    /**
     * @test
     */
    public function execute_PrefetchFalse()
    {
        $this->configHelper->method('getPrefetchShipping')
            ->willReturn(false);

        $prefetch = $this->getMockBuilder(Prefetch::class)
            ->setMethods(['getRequest'])
            ->setConstructorArgs([
                $this->context,
                $this->shippingMethods,
                $this->cartHelper,
                $this->bugsnag,
                $this->configHelper,
                $this->customerSession,
                $this->geolocation
            ])
            ->getMock();

        $prefetch->expects($this->never())
            ->method('getRequest');
        $prefetch->execute();
    }

    /**
     * @test
     */
    public function execute_noQuote()
    {
        $this->configHelper->method('getPrefetchShipping')
            ->wilLReturn(true);

        $this->shippingMethods->expects($this->never())
            ->method('shippingEstimation');

        $this->cartHelper->method('getQuoteById')
            ->willReturn(null);

        $this->bugsnag->expects($this->never())
            ->method('notifyException');

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')
            ->willReturn(self::QUOTE_ID);

        $prefetch = $this->getMockBuilder(Prefetch::class)
            ->setMethods(['getRequest'])
            ->setConstructorArgs([
                $this->context,
                $this->shippingMethods,
                $this->cartHelper,
                $this->bugsnag,
                $this->configHelper,
                $this->customerSession,
                $this->geolocation
            ])
            ->getMock();

        $prefetch->method('getRequest')
            ->willReturn($request);
        $prefetch->execute();
    }

    /**
     * @test
     */
    public function execute_noGeoLocation()
    {
        $expected = [
            'country_code' => self::COUNTRY,
            'postal_code' =>  self::POSTAL_CODE,
            'region' => self::REGION,
        ];

        $map = [
            ['cartReference', null, self::QUOTE_ID],
            ['country', null, self::COUNTRY],
            ['region', null, self::REGION],
            ['postcode', null, self::POSTAL_CODE]
        ];

        $request = $this->createMock(RequestInterface::class);
        $quote = $this->createMock(Quote::class);

        $shippingMethods = $this->createMock(ShippingMethods::class);
        $shippingMethods->expects($this->once())
            ->method('shippingEstimation')
            ->with($quote, $expected);

        $this->configHelper->method('getPrefetchShipping')->willReturn(true);

        $this->cartHelper->method('getQuoteById')->willReturn($quote);
        $request->method('getParam')->will($this->returnValueMap($map));

        $prefetch = $this->getMockBuilder(Prefetch::class)
            ->setMethods(['getRequest'])
            ->setConstructorArgs([
                $this->context,
                $shippingMethods,
                $this->cartHelper,
                $this->bugsnag,
                $this->configHelper,
                $this->customerSession,
                $this->geolocation
            ])
            ->getMock();

        $prefetch->method('getRequest')->willReturn($request);
        $prefetch->execute();
    }

    /**
     * @test
     */
    public function execute_geoLocation()
    {
        $expected = [
            'country_code' => self::COUNTRY_CODE,
            'postal_code' => self::POSTAL_CODE,
            'region' => self::REGION,
            'locality' => self::CITY
        ];

        $location = [
            'country_code' => self::COUNTRY_CODE,
            'zip' => self::POSTAL_CODE,
            'region_name' => self::REGION,
            'city' => self::CITY
        ];
        $locationJson = json_encode($location);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturn(null);

        $quote = $this->createMock(Quote::class);
        $this->cartHelper->method('getQuoteById')->willReturn($quote);

        $this->configHelper->method('getPrefetchShipping')->willReturn(true);

        $this->geolocation->method('getLocation')->willReturn($locationJson);

        $this->shippingMethods->expects($this->once())
            ->method('shippingEstimation')
            ->with($this->equalTo($quote), $this->equalTo($expected));

        $prefetch = $this->getMockBuilder(Prefetch::class)
            ->setMethods(['getRequest'])
            ->setConstructorArgs([
                $this->context,
                $this->shippingMethods,
                $this->cartHelper,
                $this->bugsnag,
                $this->configHelper,
                $this->customerSession,
                $this->geolocation
            ])
            ->getMock();

        $prefetch->method('getRequest')->willReturn($request);
        $prefetch->execute();
    }

    /**
     * @test
     */
    public function execute_geoLocationMissingElements()
    {
        $expected = [
            'country_code' => self::COUNTRY_CODE,
            'postal_code' => self::POSTAL_CODE,
            'region' => '',
            'locality' => ''
        ];

        $location = [
            'country_code' => self::COUNTRY_CODE,
            'zip' => self::POSTAL_CODE
        ];
        $locationJson = json_encode($location);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturn(null);

        $quote = $this->createMock(Quote::class);
        $this->cartHelper->method('getQuoteById')->willReturn($quote);

        $this->configHelper->method('getPrefetchShipping')->willReturn(true);

        $this->geolocation->method('getLocation')->willReturn($locationJson);

        $this->shippingMethods->expects($this->once())
            ->method('shippingEstimation')
            ->with($this->equalTo($quote), $this->equalTo($expected));

        $prefetch = $this->getMockBuilder(Prefetch::class)
            ->setMethods(['getRequest'])
            ->setConstructorArgs([
                $this->context,
                $this->shippingMethods,
                $this->cartHelper,
                $this->bugsnag,
                $this->configHelper,
                $this->customerSession,
                $this->geolocation,
            ])
            ->getMock();

        $prefetch->method('getRequest')->willReturn($request);
        $prefetch->execute();
    }

    /**
     * @test
     */
    public function execute_storedShippingAddress()
    {
        $expected = [
            'country_code' => self::COUNTRY_CODE,
            'postal_code' => self::POSTAL_CODE,
            'region' => self::REGION,
            'locality' => self::CITY,
            'street_address1' => self::STREET1,
            'street_address2' => self::STREET2
        ];

        $addressLineMap = [
            [1, self::STREET1],
            [2, self::STREET2]
        ];

        $shippingAddress = $this->createMock(Quote\Address::class);
        $shippingAddress->method('getCountryId')->willReturn(self::COUNTRY_CODE);
        $shippingAddress->method('getPostcode')->willReturn(self::POSTAL_CODE);
        $shippingAddress->method('getRegion')->willReturn(self::REGION);
        $shippingAddress->method('getCity')->willReturn(self::CITY);
        $shippingAddress->method('getStreetLine')->will($this->returnValueMap($addressLineMap));

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturn(null);

        $quote = $this->createMock(Quote::class);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $this->cartHelper->method('getQuoteById')->willReturn($quote);

        $this->configHelper->method('getPrefetchShipping')->willReturn(true);

        $this->shippingMethods->expects($this->once())
            ->method('shippingEstimation')
            ->with($this->equalTo($quote), $this->equalTo($expected));

        $prefetch = $this->getMockBuilder(Prefetch::class)
            ->setMethods(['getRequest'])
            ->setConstructorArgs([
                $this->context,
                $this->shippingMethods,
                $this->cartHelper,
                $this->bugsnag,
                $this->configHelper,
                $this->customerSession,
                $this->geolocation
            ])
            ->getMock();

        $prefetch->method('getRequest')->willReturn($request);
        $prefetch->execute();
    }
}
