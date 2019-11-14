<?php

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

class PrefetchTest extends TestCase
{
    const COUNTRY = 'Canada';
    const REGION = 'Arctic';
    const POSTAL_CODE = 'H0H 0H0';
    const LOCALITY = 'North Pole';
    const QUOTE_ID = '1234';

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
     * @var Geolocation
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

    public function testExecute_noGeoLocation()
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

}
