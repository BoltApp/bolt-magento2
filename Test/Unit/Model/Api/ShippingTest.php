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

use Bolt\Boltpay\Api\Data\ShippingDataInterface;
use Bolt\Boltpay\Api\Data\ShippingDataInterfaceFactory;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Model\Api\ShippingTaxContext;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use Bolt\Boltpay\Model\Api\Shipping;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory;

/**
 * Class ShippingTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\Shipping
 */
class ShippingTest extends TestCase
{
    const IMMUTABLE_QUOTE_ID = 1001;
    const PARENT_QUOTE_ID = 1000;
    const CURRENCY_CODE = 'USD';
    const INCREMENT_ID = 100050001;
    const STORE_ID = 1;

    /**
     * @var ShippingDataInterfaceFactory|MockObject
     */
    private $shippingDataFactory;

    /**
     * @var ShipmentEstimationInterface|MockObject
     */
    private $shippingMethodManagement;

    /**
     * @var ShippingTaxContext|MockObject
     */
    private $shippingTaxContext;

    /**
     * @var Shipping|MockObject
     */
    private $currentMock;

    protected function setUp()
    {
        $this->shippingTaxContext = $this->createMock(ShippingTaxContext::class);
        $this->shippingDataFactory = $this->createMock(ShippingDataInterfaceFactory::class);
        $this->shippingMethodManagement = $this->createMock(ShipmentEstimationInterface::class);
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
        $builder = $this->getMockBuilder(Shipping::class)
            ->setConstructorArgs(
                [
                    $this->shippingTaxContext,
                    $this->shippingDataFactory,
                    $this->shippingMethodManagement
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
            ShippingDataInterfaceFactory::class, 'shippingDataFactory', $this->currentMock
        );
        $this->assertAttributeInstanceOf(
            ShipmentEstimationInterface::class, 'shippingMethodManagement', $this->currentMock
        );
    }

    /**
     * @test
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

        $this->initCurrentMock(['getShippingOptions']);

        $shippingOption1 = $this->createMock(ShippingOptionInterface::class);
        $shippingOption2 = $this->createMock(ShippingOptionInterface::class);

        $shippingOptions = [$shippingOption1, $shippingOption2];
        $this->currentMock->expects(self::once())->method('getShippingOptions')->with($addressData)
            ->willReturn($shippingOptions);

        $shippingData =$this->createMock(ShippingDataInterface::class);
        $this->shippingDataFactory->expects(self::once())->method('create')
            ->willReturn($shippingData);

        $shippingData->expects(self::once())->method('setShippingOptions')->with($shippingOptions);

        $this->assertEquals($shippingData, $this->currentMock->generateResult($addressData, null));
    }

    /**
     * @test
     * @covers ::formatResult
     */
    public function formatResult()
    {
        $shippingOptionFactory = $this->createMock(ShippingOptionInterfaceFactory::class);
        $this->shippingTaxContext->method('getShippingOptionFactory')
            ->willReturn($shippingOptionFactory);

        $this->initCurrentMock([], true);

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

        $shippingOption = $this->createMock(ShippingOptionInterface::class);
        $shippingOption->expects(self::once())->method('setService')
            ->with('carrierTitle - methodTitle')
            ->willReturnSelf();
        $shippingOption->expects(self::once())->method('setCost')
            ->with(2000)
            ->willReturnSelf();
        $shippingOption->expects(self::once())->method('setReference')
            ->with('carrierCode_methodCode')
            ->willReturnSelf();

        $shippingOptionFactory->expects(self::once())->method('create')
            ->willReturn($shippingOption);

        $expected = [[
            $shippingOption
        ], [['service' => 'carrierTitleError - methodTitleError',
            'reference' => 'carrierCodeError_methodCodeError',
            'cost' => 1000,
            'error' => 'Error Message'
        ]]];

        $this->assertEquals($expected, $this->currentMock->formatResult($shippingOptionsArray, self::CURRENCY_CODE));
    }

    /**
     * @test
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

        $bugsnag = $this->createMock(Bugsnag::class);
        $this->shippingTaxContext->method('getBugsnag')
            ->willReturn($bugsnag);

        $this->initCurrentMock(['populateAddress', 'formatResult']);

        $this->currentMock->expects(self::once())->method('populateAddress')
            ->with($addressData);

        $quote = $this->getQuoteMock(['getShippingAddress']);

        $address = $this->createMock(\Magento\Quote\Model\Quote\Address::class);
        $quote->expects(self::once())->method('getId');
        $quote->expects(self::once())->method('getShippingAddress')->willReturn($address);
        $quote->expects(self::once())->method('getQuoteCurrencyCode');
        TestHelper::setProperty($this->currentMock, 'quote', $quote);

        $shippingMethodError = $this->createMock(ShippingMethodInterface::class);
        $shippingMethodValid = $this->createMock(ShippingMethodInterface::class);
        $shippingOptionsArray = [$shippingMethodError, $shippingMethodValid];


        $this->shippingMethodManagement->expects(self::once())->method('estimateByExtendedAddress')
            ->willReturn($shippingOptionsArray);

        $shippingOptions = [$this->createMock(ShippingOptionInterface::class)];
        $errors = [[
            'service' => 'carrierTitleError - methodTitleError',
            'reference' => 'carrierCodeError_methodCodeError',
            'cost' => 1000,
            'error' => 'Error Message'
        ]];

        $this->currentMock->expects(self::once())->method('formatResult')
            ->with($shippingOptionsArray, self::CURRENCY_CODE)
            ->willReturn([$shippingOptions, $errors]);

        $bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function (callable $callback) use ($errors, $addressData) {
                $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                $reportMock->expects(self::once())
                    ->method('setMetaData')->with(
                        [
                            'SHIPPING ERRORS' => [
                                'address' => $addressData,
                                'errors'  => $errors
                            ]
                        ]
                    );
                $callback($reportMock);
            }
        );
        $bugsnag->expects(self::once())->method('notifyError')
            ->with('SHIPPING ERRORS', 'Shipping Method Errors');

        $this->assertEquals($shippingOptions, $this->currentMock->getShippingOptions($addressData));
    }

    /**
     * @test
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
            'email' => 'integration@bolt.com',
            'company' => 'Bolt'
        ];

        $bugsnag = $this->createMock(Bugsnag::class);
        $this->shippingTaxContext->method('getBugsnag')
            ->willReturn($bugsnag);

        $this->initCurrentMock(['populateAddress', 'formatResult']);

        $this->currentMock->expects(self::once())->method('populateAddress')
            ->with($addressData);

        $quote = $this->getQuoteMock(['getShippingAddress']);

        $address = $this->createMock(\Magento\Quote\Model\Quote\Address::class);
        $quote->expects(self::exactly(2))->method('getId');
        $quote->expects(self::once())->method('getShippingAddress')->willReturn($address);
        $quote->expects(self::once())->method('getQuoteCurrencyCode');

        TestHelper::setProperty($this->currentMock, 'quote', $quote);

        $shippingMethodError = $this->createMock(ShippingMethodInterface::class);
        $shippingMethodValid = $this->createMock(ShippingMethodInterface::class);
        $shippingOptionsArray = [$shippingMethodError, $shippingMethodValid];

        $this->shippingMethodManagement->expects(self::once())->method('estimateByExtendedAddress')
            ->willReturn($shippingOptionsArray);

        $shippingOptions = [];
        $errors = [];

        $this->currentMock->expects(self::once())->method('formatResult')
            ->with($shippingOptionsArray, self::CURRENCY_CODE)
            ->willReturn([$shippingOptions, $errors]);

        $bugsnag->expects(self::once())->method('registerCallback')->willReturnCallback(
            function (callable $callback) use ($addressData) {
                $reportMock = $this->createPartialMock(\stdClass::class, ['setMetaData']);
                $reportMock->expects(self::once())
                    ->method('setMetaData')->with(
                        [
                            'NO SHIPPING' => [
                                'address' => $addressData,
                                'immutable quote ID' => self::IMMUTABLE_QUOTE_ID,
                                'parent quote ID' => self::PARENT_QUOTE_ID,
                                'order increment ID' => self::INCREMENT_ID,
                                'Store Id' => self::STORE_ID
                            ]
                        ]
                    );
                $callback($reportMock);
            }
        );
        $bugsnag->expects(self::never())->method('notifyError');

        $this->expectException(BoltException::class);
        $this->expectExceptionCode(BoltErrorResponse::ERR_SERVICE);
        $this->expectExceptionMessage(__('No Shipping Methods retrieved')->render());

        $this->assertNull($this->currentMock->getShippingOptions($addressData));
    }

    /**
     * @param int $quoteId
     * @param int $parentQuoteId
     * @param array $methods
     * @return MockObject
     */
    private function getQuoteMock(
        $methods = [],
        $quoteId = self::IMMUTABLE_QUOTE_ID,
        $parentQuoteId = self::PARENT_QUOTE_ID
    ) {
        $quoteMethods = array_merge([
            'getId', 'getBoltParentQuoteId', 'getReservedOrderId', 'getStoreId', 'getQuoteCurrencyCode'
        ], $methods);

        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();
        $quote->method('getId')
            ->willReturn($quoteId);
        $quote->method('getBoltParentQuoteId')
            ->willReturn($parentQuoteId);
        $quote->method('getReservedOrderId')
            ->willReturn(self::INCREMENT_ID);
        $quote->method('getStoreId')
            ->willReturn(self::STORE_ID);
        $quote->method('getQuoteCurrencyCode')
            ->willReturn(self::CURRENCY_CODE);
        return $quote;
    }


}