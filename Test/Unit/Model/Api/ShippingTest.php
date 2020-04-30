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
use Bolt\Boltpay\Model\Api\ShippingTaxContext;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Bolt\Boltpay\Model\Api\Shipping;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Api\Data\ShippingOptionInterface;

/**
 * Class ShippingTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\Shipping
 */
class ShippingTest extends TestCase
{
    /**
     * @var ShippingDataInterfaceFactory|MockObject
     */
    private $shippingDataFactory;

    /**
     * @var ShippingMethodManagementInterface|MockObject
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
        $this->shippingMethodManagement = $this->createMock(ShippingMethodManagementInterface::class);
    }

    /**
     * @param array $methods
     * @param bool $enableOriginalConstructor
     * @param bool $enableProxyingToOriginalMethods
     */
    private function initCurrentMock(
        $methods = [],
        $enableOriginalConstructor = true,
        $enableProxyingToOriginalMethods = false
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
            ShippingMethodManagementInterface::class, 'shippingMethodManagement', $this->currentMock
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

        $shippingOptions = [$this->createMock(ShippingOptionInterface::class)];
        $this->currentMock->expects(self::once())->method('getShippingOptions')->with($addressData)
            ->willReturn($shippingOptions);

        $shippingData =$this->createMock(ShippingDataInterface::class);
        $this->shippingDataFactory->expects(self::once())->method('create')
            ->willReturn($shippingData);

        $shippingData->expects(self::once())->method('setShippingOptions')->with($shippingOptions);

        $this->assertEquals($shippingData, $this->currentMock->generateResult($addressData, null));
    }
}