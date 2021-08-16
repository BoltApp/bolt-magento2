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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Model\Api\Shipping;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\Helper\Bootstrap;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Store\Model\ScopeInterface;

/**
 * Class ShippingTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\Shipping
 */
class ShippingTest extends BoltTestCase
{
    const IMMUTABLE_QUOTE_ID = 1001;
    const PARENT_QUOTE_ID = 1000;
    const CURRENCY_CODE = 'USD';
    const INCREMENT_ID = 100050001;
    const STORE_ID = 1;
    const TRACE_ID_HEADER = 'KdekiEGku3j1mU21Mnsx5g==';
    const SECRET = '42425f51e0614482e17b6e913d74788eedb082e2e6f8067330b98ffa99adc809';
    const APIKEY = '3c2d5104e7f9d99b66e1c9c550f6566677bf81de0d6f25e121fdb57e47c2eafc';

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var ScopeInterface
     */
    private $storeId;

    /**
     * @var Shipping
     */
    private $shipping;

    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->shipping = $this->objectManager->create(Shipping::class);
    }

    /**
     * @test
     * that generateResult returns shipping data
     *
     * @covers ::generateResult
     */
    public function generateResult()
    {
        $addressData = [
            'region' => 'California',
            'country_code' => 'US',
            'postal_code' => '90210',
            'locality' => 'San Franciso',
            'street_address1' => '123 Sesame St.',
            'email' => 'integration@bolt.com',
            'company' => 'Bolt'
        ];

        $shippingMethodManagement = $this->createMock(\Magento\Quote\Api\Data\ShippingMethodInterface::class);
        $shippingMethodManagement->method('getCarrierTitle')->willReturn('Carrier Title');
        $shippingMethodManagement->method('getMethodTitle')->willReturn('Method Title');
        $shippingMethodManagement->method('getCarrierCode')->willReturn('carrier_code');
        $shippingMethodManagement->method('getMethodCode')->willReturn('method_code');
        $shippingMethodManagement->method('getAmount')->willReturn(100);
        $shipmentEstimationInterface = $this->getMockBuilder(\Magento\Quote\Api\ShipmentEstimationInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['estimateByExtendedAddress'])
            ->getMock();
        $shipmentEstimationInterface->method('estimateByExtendedAddress')->willReturn([$shippingMethodManagement]);
        $shippingMethodManagement = new \ReflectionProperty(
            Shipping::class,
            'shippingMethodManagement'
        );
        $shippingMethodManagement->setAccessible(true);
        $shippingMethodManagement->setValue($this->shipping, $shipmentEstimationInterface);

        $shippingOptionData = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $shippingOptionData
            ->setService('Carrier Title - Method Title')
            ->setCost(10000)
            ->setReference('carrier_code_method_code')
            ->setTaxAmount(0);
        $quote = TestUtils::createQuote(['store_id' => $this->storeId]);
        TestHelper::setProperty($this->shipping, 'quote', $quote);
        $result = $this->shipping->generateResult($addressData, [], null);
        $this->assertEquals(
            [$shippingOptionData],
            $result->getShippingOptions()
        );
    }
    
    /**
     * @test
     * that generateResult for virtual quote returns shipping data
     *
     * @covers ::generateResult
     */
    public function generateResult_virtualQuote()
    {
        $addressData = [
            'region' => 'California',
            'country_code' => 'US',
            'postal_code' => '90210',
            'locality' => 'San Franciso',
            'street_address1' => '123 Sesame St.',
            'email' => 'integration@bolt.com',
            'company' => 'Bolt'
        ];
        $shippingOptionData = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $shippingOptionData
            ->setService('No Shipping Required')
            ->setCost(0)
            ->setReference('noshipping');
        $quote = TestUtils::createQuote(['store_id' => $this->storeId, 'is_virtual' => '1']);
        TestHelper::setProperty($this->shipping, 'quote', $quote);
        $result = $this->shipping->generateResult($addressData, [], null);
        $this->assertEquals(
            [$shippingOptionData],
            $result->getShippingOptions()
        );
    }

    /**
     * @test
     * that formatResult would return shipping options according to shipping options factory
     *
     * @covers ::formatResult
     */
    public function formatResult()
    {
        $shippingMethodError = $this->createMock(ShippingMethodInterface::class);
        $shippingMethodValid = $this->createMock(ShippingMethodInterface::class);
        $shippingOptionsArray = [$shippingMethodError, $shippingMethodValid];

        $shippingMethodError->expects(self::once())->method('getCarrierTitle')
            ->willReturn('carrierTitleError');
        $shippingMethodError->expects(self::once())->method('getMethodTitle')
            ->willReturn('methodTitleError');
        $shippingMethodError->expects(self::once())->method('getCarrierCode')
            ->willReturn('carrierCodeError');
        $shippingMethodError->expects(self::once())->method('getMethodCode')
            ->willReturn('methodCodeError');
        $shippingMethodError->expects(self::once())->method('getAmount')
            ->willReturn(10);
        $shippingMethodError->expects(self::once())->method('getErrorMessage')
            ->willReturn('Error Message');

        $shippingMethodValid->expects(self::once())->method('getCarrierTitle')
            ->willReturn('carrierTitle');
        $shippingMethodValid->expects(self::once())->method('getMethodTitle')
            ->willReturn('methodTitle');
        $shippingMethodValid->expects(self::once())->method('getCarrierCode')
            ->willReturn('carrierCode');
        $shippingMethodValid->expects(self::once())->method('getMethodCode')
            ->willReturn('methodCode');
        $shippingMethodValid->expects(self::once())->method('getAmount')
            ->willReturn(20);
        $shippingMethodValid->expects(self::once())->method('getErrorMessage')
            ->willReturn(false);

        $shippingOptionData = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $shippingOptionData
            ->setService('carrierTitle - methodTitle')
            ->setCost(2000)
            ->setReference('carrierCode_methodCode')
            ->setTaxAmount(null);

        $expected = [[
            $shippingOptionData
        ], [['service' => 'carrierTitleError - methodTitleError',
            'reference' => 'carrierCodeError_methodCodeError',
            'cost' => 1000,
            'error' => 'Error Message'
        ]]];

        $this->assertEquals($expected, $this->shipping->formatResult($shippingOptionsArray, self::CURRENCY_CODE));
    }

    /**
     * @test
     * that getShippingOptions would return shipping options
     *
     * @covers ::getShippingOptions
     */
    public function getShippingOptions_happyPath()
    {
        $addressData = [
            'region' => 'California',
            'country_code' => 'US',
            'postal_code' => '90210',
            'locality' => 'San Franciso',
            'street_address1' => '123 Sesame St.',
            'email' => 'integration@bolt.com',
            'company' => 'Bolt'
        ];

        $shippingMethodManagement = $this->createMock(\Magento\Quote\Api\Data\ShippingMethodInterface::class);
        $shippingMethodManagement->method('getCarrierTitle')->willReturn('Carrier Title');
        $shippingMethodManagement->method('getMethodTitle')->willReturn('Method Title');
        $shippingMethodManagement->method('getCarrierCode')->willReturn('carrier_code');
        $shippingMethodManagement->method('getMethodCode')->willReturn('method_code');
        $shippingMethodManagement->method('getAmount')->willReturn(100);
        $shipmentEstimationInterface = $this->getMockBuilder(\Magento\Quote\Api\ShipmentEstimationInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['estimateByExtendedAddress'])
            ->getMock();
        $shipmentEstimationInterface->method('estimateByExtendedAddress')->willReturn([$shippingMethodManagement]);
        $shippingMethodManagement = new \ReflectionProperty(
            Shipping::class,
            'shippingMethodManagement'
        );
        $shippingMethodManagement->setAccessible(true);
        $shippingMethodManagement->setValue($this->shipping, $shipmentEstimationInterface);

        $shippingOptionData = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $shippingOptionData
            ->setService('Carrier Title - Method Title')
            ->setCost(10000)
            ->setReference('carrier_code_method_code')
            ->setTaxAmount(0);
        $quote = TestUtils::createQuote(['store_id' => $this->storeId]);
        TestHelper::setProperty($this->shipping, 'quote', $quote);
        $result = $this->shipping->getShippingOptions($addressData);
        $this->assertEquals(
            [$shippingOptionData],
            $result
        );
    }

    /**
     * @test
     * that getShippingOptions would return bolt exception if there is no shipping options
     *
     * @covers ::getShippingOptions
     */
    public function getShippingOptions_noShippingOptions()
    {
        $addressData = [
            'region' => 'California',
            'country_code' => 'US',
            'postal_code' => '90210',
            'locality' => 'San Franciso',
            'street_address1' => '123 Sesame St.',
            'email' => 'integration@bolt.com'
        ];

        $this->expectException(BoltException::class);
        $this->expectExceptionCode(BoltErrorResponse::ERR_SERVICE);
        $this->expectExceptionMessage(__('No Shipping Methods retrieved')->render());
        $quote = TestUtils::createQuote(['store_id' => $this->storeId]);
        TestHelper::setProperty($this->shipping, 'quote', $quote);
        $this->assertNull($this->shipping->getShippingOptions($addressData));
    }
}
