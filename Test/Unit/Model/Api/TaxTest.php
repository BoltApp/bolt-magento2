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

use Bolt\Boltpay\Model\Api\Data\TaxResult;
use Bolt\Boltpay\Model\Api\ShippingTaxContext;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Checkout\Api\Data\TotalsInformationInterface;
use Magento\Checkout\Api\TotalsInformationManagementInterface;
use Bolt\Boltpay\Api\Data\TaxDataInterfaceFactory;
use Bolt\Boltpay\Api\Data\TaxDataInterface;
use Bolt\Boltpay\Api\Data\TaxResultInterfaceFactory;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Model\Api\Tax;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\Quote\Api\Data\TotalsInterface;
use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory;
use Magento\Quote\Model\Quote;

/**
 * Class TaxTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\Tax
 */
class TaxTest extends TestCase
{
    const CURRENCY_CODE = 'USD';
    const IMMUTABLE_QUOTE_ID = 1001;
    const PARENT_QUOTE_ID = 1000;
    const INCREMENT_ID = 100050001;
    const STORE_ID = 1;

    /**
     * @var TaxDataInterfaceFactory|MockObject
     */
    protected $taxDataFactory;

    /**
     * @var TaxResultInterfaceFactory|MockObject
     */
    protected $taxResultFactory;

    /**
     * @var TotalsInformationManagementInterface|MockObject
     */
    protected $totalsInformationManagement;

    /**
     * @var ShippingTaxContext|MockObject
     */
    protected $shippingTaxContext;

    /**
     * @var TotalsInformationInterface|MockObject
     */
    protected $addressInformation;

    /**
     * @var Tax|MockObject
     */
    private $currentMock;

    protected function setUp()
    {
        $this->shippingTaxContext = $this->createMock(ShippingTaxContext::class);

        $this->taxDataFactory = $this->createMock(TaxDataInterfaceFactory::class);
        $this->taxResultFactory = $this->createMock(TaxResultInterfaceFactory::class);
        $this->totalsInformationManagement = $this->createMock(
            TotalsInformationManagementInterface::class
        );
        $this->addressInformation = $this->createMock(TotalsInformationInterface::class);
    }

    /**
     * @param array $methods
     * @param bool $enableOriginalConstructor
     * @param bool $enableProxyingToOriginalMethods
     */
    private function initCurrentMock(
        $methods = [],
        $enableProxyingToOriginalMethods = false,
        $enableOriginalConstructor = true
    ) {
        $builder = $this->getMockBuilder(Tax::class)
            ->setConstructorArgs(
                [
                    $this->shippingTaxContext,
                    $this->taxDataFactory,
                    $this->taxResultFactory,
                    $this->totalsInformationManagement,
                    $this->addressInformation
                ]
            )
            ->setMethods($methods);

        if ($enableOriginalConstructor) {
            $builder->enableOriginalConstructor();
        } else {
            $builder->disableOriginalConstructor();
        }

        if ($enableProxyingToOriginalMethods) {
            $builder->enableProxyingToOriginalMethods();
        } else {
            $builder->disableProxyingToOriginalMethods();
        }
        $this->currentMock = $builder->getMock();
    }

    /**
     * @test
     * that sets internal properties
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $this->initCurrentMock();

        $this->assertAttributeInstanceOf(
            TaxDataInterfaceFactory::class,
            'taxDataFactory',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            TaxResultInterfaceFactory::class,
            'taxResultFactory',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            TotalsInformationManagementInterface::class,
            'totalsInformationManagement',
            $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            TotalsInformationInterface::class,
            'addressInformation',
            $this->currentMock
        );
    }

    /**
     * @test
     * that setAddressInformation would return set shipping method code and set shipping carrier code
     *
     * @dataProvider provider_setAddressInformation_happyPath
     * @covers ::setAddressInformation
     * @param $shippingReference
     * @param $carrierCode
     * @param $methodCode
     *
     */
    public function setAddressInformation_happyPath($shippingReference, $carrierCode, $methodCode)
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

        $shipping_option = [
            'reference' => $shippingReference
        ];

        $this->initCurrentMock(['populateAddress']);

        $address = $this->createMock(\Magento\Quote\Model\Quote\Address::class);
        $this->currentMock->expects(self::once())->method('populateAddress')
            ->with($addressData)->willReturn($address);

        $this->addressInformation->expects(self::once())->method('setAddress')
            ->with($address);

        $this->addressInformation->expects(self::once())->method('setShippingCarrierCode')
            ->with($carrierCode);
        $this->addressInformation->expects(self::once())->method('setShippingMethodCode')->with($methodCode);

        $this->assertNull($this->currentMock->setAddressInformation($addressData, $shipping_option));
    }

    public function provider_setAddressInformation_happyPath(){
        return [
          ['carrierCode_methodCode','carrierCode', 'methodCode'],
          ['shqshared_GROUND_HOME_DELIVERY', 'shqshared', 'GROUND_HOME_DELIVERY']
        ];
    }

    /**
     * @test
     * that setAddressInformation would return null if no shipping option was specified
     *
     * @covers ::setAddressInformation
     */
    public function setAddressInformation_noShippingOption()
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

        $shipping_option = null;

        $this->initCurrentMock(['populateAddress']);

        $address = $this->createMock(\Magento\Quote\Model\Quote\Address::class);
        $this->currentMock->expects(self::once())->method('populateAddress')
            ->with($addressData)->willReturn($address);

        $this->addressInformation->expects(self::once())->method('setAddress')
            ->with($address);

        $this->addressInformation->expects(self::never())->method('setShippingCarrierCode');
        $this->addressInformation->expects(self::never())->method('setShippingMethodCode');

        $this->assertNull($this->currentMock->setAddressInformation($addressData, $shipping_option));
    }

    /**
     * @test
     * that createTaxResult would return tax result interface instance
     *
     * @covers ::createTaxResult
     */
    public function createTaxResult()
    {
        $this->initCurrentMock([], true);

        $totalsInformation = $this->createMock(TotalsInterface::class);
        $totalsInformation->expects(self::once())->method('getTaxAmount')->willReturn(10);

        $taxResult = $this->createMock(TaxResult::class);
        $this->taxResultFactory->expects(self::once())->method('create')->willReturn($taxResult);

        $taxResult->expects(self::once())->method('setSubtotalAmount')->with(1000);

        $this->assertEquals(
            $taxResult,
            $this->currentMock->createTaxResult($totalsInformation, self::CURRENCY_CODE)
        );
    }

    /**
     * @test
     * that createShippingOption would return shipping option interface instance
     *
     * @covers ::createShippingOption
     */
    public function createShippingOption()
    {
        $shippingOptionFactory = $this->createMock(ShippingOptionInterfaceFactory::class);
        $this->shippingTaxContext->method('getShippingOptionFactory')
            ->willReturn($shippingOptionFactory);

        $this->initCurrentMock([], true);

        $shipping_option = [
            'service' => 'carrierTitle - methodTitle',
            'reference' => 'carrierCode_methodCode'
        ];

        $shippingOption = $this->createMock(ShippingOptionInterface::class);

        $shippingOptionFactory->expects(self::once())->method('create')
            ->willReturn($shippingOption);

        $totalsInformation = $this->createMock(TotalsInterface::class);
        $totalsInformation->expects(self::once())->method('getShippingTaxAmount')->willReturn(10);
        $totalsInformation->expects(self::once())->method('getShippingAmount')->willReturn(100);

        $shippingOption->expects(self::once())->method('setTaxAmount')->with(1000);
        $shippingOption->expects(self::once())->method('setService')->with('carrierTitle - methodTitle');
        $shippingOption->expects(self::once())->method('setCost')->with(10000);
        $shippingOption->expects(self::once())->method('setReference')->with('carrierCode_methodCode');

        $this->assertEquals(
            $shippingOption,
            $this->currentMock->createShippingOption(
                $totalsInformation,
                self::CURRENCY_CODE,
                $shipping_option
            )
        );
    }

    /**
     * @test
     * that createShippingOption would return "empty" shipping option,
     * with zero cost and tax amount and without service and reference,
     * if no $shipping_option input is specified
     *
     * @covers ::createShippingOption
     */
    public function createShippingOption_noShippingOption()
    {
        $shippingOptionFactory = $this->createMock(ShippingOptionInterfaceFactory::class);
        $this->shippingTaxContext->method('getShippingOptionFactory')
            ->willReturn($shippingOptionFactory);

        $this->initCurrentMock([], true);

        $shipping_option = null;

        $shippingOption = $this->createMock(ShippingOptionInterface::class);

        $shippingOptionFactory->expects(self::once())->method('create')
            ->willReturn($shippingOption);

        $totalsInformation = $this->createMock(TotalsInterface::class);
        $totalsInformation->expects(self::once())->method('getShippingTaxAmount')->willReturn(0);
        $totalsInformation->expects(self::once())->method('getShippingAmount')->willReturn(0);

        $shippingOption->expects(self::once())->method('setTaxAmount')->with(0);
        $shippingOption->expects(self::once())->method('setService')->with(null);
        $shippingOption->expects(self::once())->method('setCost')->with(0);
        $shippingOption->expects(self::once())->method('setReference')->with(null);

        $this->assertEquals(
            $shippingOption,
            $this->currentMock->createShippingOption(
                $totalsInformation,
                self::CURRENCY_CODE,
                $shipping_option
            )
        );
    }

    /**
     * @test
     * that generateResult would return tax data interface instance
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

        $shipping_option = [
            'service' => 'carrierTitle - methodTitle',
            'reference' => 'carrierCode_methodCode'
        ];

        $this->initCurrentMock(['setAddressInformation', 'createTaxResult', 'createShippingOption']);

        $this->currentMock->expects(self::once())->method('setAddressInformation')
            ->with($addressData, $shipping_option);

        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(['getId', 'getQuoteCurrencyCode'])
            ->disableOriginalConstructor()
            ->getMock();

        $quote->expects(self::once())->method('getId')->willReturn(self::PARENT_QUOTE_ID);
        $quote->expects(self::once())->method('getQuoteCurrencyCode')->willReturn(self::CURRENCY_CODE);
        TestHelper::setProperty($this->currentMock, 'quote', $quote);

        $totalsInformation = $this->createMock(TotalsInterface::class);
        $this->totalsInformationManagement->expects(self::once())->method('calculate')
            ->with(self::PARENT_QUOTE_ID, $this->addressInformation)->willReturn($totalsInformation);

        $taxResult = $this->createMock(TaxResult::class);
        $this->currentMock->expects(self::once())->method('createTaxResult')
            ->with($totalsInformation, self::CURRENCY_CODE)->willReturn($taxResult);

        $shippingOption = $this->createMock(ShippingOptionInterface::class);
        $this->currentMock->expects(self::once())->method('createShippingOption')
            ->with($totalsInformation, self::CURRENCY_CODE, $shipping_option)->willReturn($shippingOption);

        $taxData = $this->createMock(TaxDataInterface::class);
        $this->taxDataFactory->expects(self::once())->method('create')->willReturn($taxData);
        $taxData->expects(self::once())->method('setTaxResult')->with($taxResult);
        $taxData->expects(self::once())->method('setShippingOption')->with($shippingOption);

        $this->assertEquals($taxData, $this->currentMock->generateResult($addressData, $shipping_option));
    }
}
