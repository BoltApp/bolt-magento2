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

namespace Bolt\Boltpay\Test\Unit\Plugin\Magento\Framework\View\Element\UiComponent\DataProvider;

use Bolt\Boltpay\Plugin\Magento\Framework\View\Element\UiComponent\DataProvider\DataProviderPlugin;
use Bolt\Boltpay\Test\Unit\BoltTestCase;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\Magento\Framework\View\Element\UiComponent\DataProvider\DataProviderPlugin
 */
class DataProviderPluginTest extends BoltTestCase
{
    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Payment\CollectionFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    private $paymentCollectionFactoryMock;

    /**
     * @var DataProviderPlugin|\PHPUnit_Framework_MockObject_MockObject
     */
    private $currentMock;

    /**
     * @var \Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider|\PHPUnit_Framework_MockObject_MockObject
     */
    private $subjectMock;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Payment\Collection|\PHPUnit_Framework_MockObject_MockObject
     */
    private $paymentCollectionMock;

    /**
     * @var \Magento\Sales\Model\Order\Payment|\PHPUnit_Framework_MockObject_MockObject
     */
    private $paymentMock;

    /**
     * @var \Bolt\Boltpay\Helper\Config|\PHPUnit\Framework\MockObject\MockObject
     */
    private $configHelperMock;

    protected function setUpInternal()
    {
        $this->paymentCollectionFactoryMock = $this->createPartialMock(
            \Magento\Sales\Model\ResourceModel\Order\Payment\CollectionFactory::class,
            ['create']
        );
        $this->configHelperMock = $this->createMock(\Bolt\Boltpay\Helper\Config::class);
        $this->currentMock = $this->getMockBuilder(DataProviderPlugin::class)
            ->setConstructorArgs([$this->paymentCollectionFactoryMock, $this->configHelperMock])
            ->setMethods(null)
            ->getMock();
        $this->subjectMock = $this->createMock(
            \Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider::class
        );
        $this->paymentCollectionMock = $this->createMock(
            \Magento\Sales\Model\ResourceModel\Order\Payment\Collection::class
        );
        $this->paymentMock = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
    }

    /**
     * @test
     *
     * @covers ::__construct
     */
    public function __construct_always_setsProperty()
    {
        $instance = new DataProviderPlugin($this->paymentCollectionFactoryMock, $this->configHelperMock);
        static::assertAttributeEquals(
            $this->paymentCollectionFactoryMock,
            'paymentCollectionFactory',
            $instance
        );
        static::assertAttributeEquals(
            $this->configHelperMock,
            'configHelper',
            $instance
        );
    }

    /**
     * @test
     * that afterGetData appends payment processor to the Bolt orders payment method code for the following grids:
     * 1. Sales Order Grid
     * 2. Order Invoice Grid
     * 3. Creditmemo Grid
     * 4. Shipments Grid
     *
     * @covers ::afterGetData
     *
     * @dataProvider afterGetData_withVariousOrderGridDataProvider
     *
     * @param string $name
     * @param array  $resultBefore
     * @param array  $expectedIds
     * @param array  $processors
     * @param array  $additionalData
     * @param array  $resultAfter
     * @param array  $ccTypes
     * @param bool   $showCcTypeInOrderGrid
     */
    public function afterGetData_withVariousOrderGridData_appendsProcessorToPaymentMethod(
        $name,
        $resultBefore,
        $expectedIds,
        $processors,
        $additionalData,
        $resultAfter,
        $ccTypes = [],
        $showCcTypeInOrderGrid = false
    ) {
        $this->subjectMock->expects(static::once())->method('getName')->willReturn($name);
        $this->paymentCollectionFactoryMock->method('create')
            ->willReturn($this->paymentCollectionMock);
        $this->paymentCollectionMock->method('addFieldToFilter')
            ->with('parent_id', ['in' => $expectedIds])->willReturnSelf();
        $this->paymentCollectionMock
            ->method('getItemByColumnValue')->willReturnCallback(
                function ($column, $value) use ($expectedIds) {
                    return in_array($value, $expectedIds) ? $this->paymentMock : null;
                }
            );
        $this->paymentMock->method('getData')->with('additional_information/processor')
            ->willReturnOnConsecutiveCalls(...$processors);
        $this->paymentMock->method('getAdditionalData')
            ->willReturnOnConsecutiveCalls(...$additionalData);
        $this->paymentMock->method('getCcType')->willReturnOnConsecutiveCalls(...$ccTypes);
        $this->configHelperMock->method('getShowCcTypeInOrderGrid')->willReturn($showCcTypeInOrderGrid);
        $resultActual = $this->currentMock->afterGetData($this->subjectMock, $resultBefore);
        static::assertEquals($resultAfter, $resultActual);
    }

    /**
     * Data provider for {@see afterGetData_withVariousOrderGridData}
     *
     * @return array[]
     */
    public function afterGetData_withVariousOrderGridDataProvider()
    {
        return [
            [
                'dataSourceName' => 'sales_order_grid_data_source',
                'resultBefore'   => [
                    'items'        => [
                        ['entity_id' => '1', 'payment_method' => 'checkmo'],
                        ['entity_id' => '2', 'payment_method' => 'boltpay'],
                        ['entity_id' => '3', 'payment_method' => 'boltpay'],
                        ['entity_id' => '4', 'payment_method' => 'boltpay'],
                        ['entity_id' => '5', 'payment_method' => 'boltpay'],
                        ['entity_id' => '6', 'payment_method' => 'boltpay'],
                        ['entity_id' => '7', 'payment_method' => 'cashondelivery'],
                    ],
                    'totalRecords' => 170,
                ],
                'expectedIds'    => [2, 3, 4, 5, 6],
                'processors'     => [
                    'paypal',
                    'affirm',
                    'afterpay',
                    null,
                    'paypal',
                ],
                'additionalData' => [
                    null,
                    null,
                    null,
                    'applepay',
                    null
                ],
                'resultAfter'    => [
                    'items'        => [
                        ['entity_id' => '1', 'payment_method' => 'checkmo'],
                        ['entity_id' => '2', 'payment_method' => 'boltpay_paypal'],
                        ['entity_id' => '3', 'payment_method' => 'boltpay_affirm'],
                        ['entity_id' => '4', 'payment_method' => 'boltpay_afterpay'],
                        ['entity_id' => '5', 'payment_method' => 'boltpay_applepay'],
                        ['entity_id' => '6', 'payment_method' => 'boltpay_paypal'],
                        ['entity_id' => '7', 'payment_method' => 'cashondelivery'],
                    ],
                    'totalRecords' => 170,
                ]
            ],
            [
                'dataSourceName' => 'sales_order_invoice_grid_data_source',
                'resultBefore'   => [
                    'items'        => [
                        ['order_id' => '1', 'payment_method' => 'checkmo'],
                        ['order_id' => '2', 'payment_method' => 'boltpay'],
                        ['order_id' => '3', 'payment_method' => 'boltpay'],
                        ['order_id' => '4', 'payment_method' => 'boltpay'],
                        ['order_id' => '5', 'payment_method' => 'boltpay'],
                        ['order_id' => '6', 'payment_method' => 'cashondelivery'],
                    ],
                    'totalRecords' => 170,
                ],
                'expectedIds'    => [2, 3, 4, 5],
                'processors'     => [
                    'paypal',
                    'affirm',
                    'afterpay',
                    'paypal',
                ],
                'additionalData' => [],
                'resultAfter'    => [
                    'items'        => [
                        ['order_id' => '1', 'payment_method' => 'checkmo'],
                        ['order_id' => '2', 'payment_method' => 'boltpay_paypal'],
                        ['order_id' => '3', 'payment_method' => 'boltpay_affirm'],
                        ['order_id' => '4', 'payment_method' => 'boltpay_afterpay'],
                        ['order_id' => '5', 'payment_method' => 'boltpay_paypal'],
                        ['order_id' => '6', 'payment_method' => 'cashondelivery'],
                    ],
                    'totalRecords' => 170,
                ]
            ],
            [
                'dataSourceName' => 'sales_order_creditmemo_grid_data_source',
                'resultBefore'   => [
                    'items'        => [
                        ['order_id' => '1', 'payment_method' => 'checkmo'],
                        ['order_id' => '2', 'payment_method' => 'boltpay'],
                        ['order_id' => '3', 'payment_method' => 'boltpay'],
                        ['order_id' => '4', 'payment_method' => 'boltpay'],
                        ['order_id' => '5', 'payment_method' => 'boltpay'],
                        ['order_id' => '6', 'payment_method' => 'cashondelivery'],
                    ],
                    'totalRecords' => 170,
                ],
                'expectedIds'    => [2, 3, 4, 5],
                'processors'     => [
                    'paypal',
                    'affirm',
                    'afterpay',
                    'paypal',
                ],
                'additionalData' => [],
                'resultAfter'    => [
                    'items'        => [
                        ['order_id' => '1', 'payment_method' => 'checkmo'],
                        ['order_id' => '2', 'payment_method' => 'boltpay_paypal'],
                        ['order_id' => '3', 'payment_method' => 'boltpay_affirm'],
                        ['order_id' => '4', 'payment_method' => 'boltpay_afterpay'],
                        ['order_id' => '5', 'payment_method' => 'boltpay_paypal'],
                        ['order_id' => '6', 'payment_method' => 'cashondelivery'],
                    ],
                    'totalRecords' => 170,
                ]
            ],
            [
                'dataSourceName' => 'sales_order_shipment_grid_data_source',
                'resultBefore'   => [
                    'items'        => [
                        ['order_id' => '1', 'payment_method' => 'checkmo'],
                        ['order_id' => '2', 'payment_method' => 'boltpay'],
                        ['order_id' => '3', 'payment_method' => 'boltpay'],
                        ['order_id' => '4', 'payment_method' => 'boltpay'],
                        ['order_id' => '5', 'payment_method' => 'boltpay'],
                        ['order_id' => '6', 'payment_method' => 'cashondelivery'],
                    ],
                    'totalRecords' => 170,
                ],
                'expectedIds'    => [2, 3, 4, 5],
                'processors'     => [
                    'paypal',
                    'affirm',
                    'afterpay',
                    'paypal',
                ],
                'additionalData' => [],
                'resultAfter'    => [
                    'items'        => [
                        ['order_id' => '1', 'payment_method' => 'checkmo'],
                        ['order_id' => '2', 'payment_method' => 'boltpay_paypal'],
                        ['order_id' => '3', 'payment_method' => 'boltpay_affirm'],
                        ['order_id' => '4', 'payment_method' => 'boltpay_afterpay'],
                        ['order_id' => '5', 'payment_method' => 'boltpay_paypal'],
                        ['order_id' => '6', 'payment_method' => 'cashondelivery'],
                    ],
                    'totalRecords' => 170,
                ]
            ],
            [
                'dataSourceName' => 'sales_order_transaction_grid_data_source',
                'resultBefore'   => [
                    'items'        => [
                        ['order_id' => '1', 'payment_method' => 'checkmo'],
                        ['order_id' => '2', 'payment_method' => 'boltpay'],
                        ['order_id' => '3', 'payment_method' => 'boltpay'],
                        ['order_id' => '4', 'payment_method' => 'boltpay'],
                        ['order_id' => '5', 'payment_method' => 'boltpay'],
                        ['order_id' => '6', 'payment_method' => 'cashondelivery'],
                    ],
                    'totalRecords' => 170,
                ],
                'expectedIds'    => [],
                'processors'     => [],
                'additionalData' => [],
                'resultAfter'    => [
                    'items'        => [
                        ['order_id' => '1', 'payment_method' => 'checkmo'],
                        ['order_id' => '2', 'payment_method' => 'boltpay'],
                        ['order_id' => '3', 'payment_method' => 'boltpay'],
                        ['order_id' => '4', 'payment_method' => 'boltpay'],
                        ['order_id' => '5', 'payment_method' => 'boltpay'],
                        ['order_id' => '6', 'payment_method' => 'cashondelivery'],
                    ],
                    'totalRecords' => 170,
                ]
            ],
            'Valid CC Types but disabled in configuration - does not append' => [
                'dataSourceName'        => 'sales_order_grid_data_source',
                'resultBefore'          => [
                    'items'        => [
                        ['order_id' => '1', 'payment_method' => 'checkmo'],
                        ['order_id' => '2', 'payment_method' => 'boltpay'],
                        ['order_id' => '3', 'payment_method' => 'boltpay'],
                        ['order_id' => '4', 'payment_method' => 'boltpay'],
                        ['order_id' => '5', 'payment_method' => 'boltpay'],
                        ['order_id' => '6', 'payment_method' => 'cashondelivery'],
                    ],
                    'totalRecords' => 170,
                ],
                'expectedIds'           => [2, 3, 4, 5],
                'processors'            => [],
                'additionalData'        => [],
                'resultAfter'           => [
                    'items'        => [
                        ['order_id' => '1', 'payment_method' => 'checkmo'],
                        ['order_id' => '2', 'payment_method' => 'boltpay'],
                        ['order_id' => '3', 'payment_method' => 'boltpay'],
                        ['order_id' => '4', 'payment_method' => 'boltpay'],
                        ['order_id' => '5', 'payment_method' => 'boltpay'],
                        ['order_id' => '6', 'payment_method' => 'cashondelivery'],
                    ],
                    'totalRecords' => 170,
                ],
                'ccTypes'               => [
                    'amex',
                    'mastercard',
                    'visa',
                    'discover',
                    'mastercard',
                ],
                'showCcTypeInOrderGrid' => false,
            ],
            'Valid CC Types and enabled in configuration - does append'  => [
                'dataSourceName'        => 'sales_order_grid_data_source',
                'resultBefore'          => [
                    'items'        => [
                        ['order_id' => '1', 'payment_method' => 'checkmo'],
                        ['order_id' => '2', 'payment_method' => 'boltpay'],
                        ['order_id' => '3', 'payment_method' => 'boltpay'],
                        ['order_id' => '4', 'payment_method' => 'boltpay'],
                        ['order_id' => '5', 'payment_method' => 'boltpay'],
                        ['order_id' => '6', 'payment_method' => 'cashondelivery'],
                    ],
                    'totalRecords' => 170,
                ],
                'expectedIds'           => [2, 3, 4, 5],
                'processors'            => [],
                'additionalData'        => [],
                'resultAfter'           => [
                    'items'        => [
                        ['order_id' => '1', 'payment_method' => 'checkmo'],
                        ['order_id' => '2', 'payment_method' => 'boltpay_amex'],
                        ['order_id' => '3', 'payment_method' => 'boltpay_mastercard'],
                        ['order_id' => '4', 'payment_method' => 'boltpay_visa'],
                        ['order_id' => '5', 'payment_method' => 'boltpay_discover'],
                        ['order_id' => '6', 'payment_method' => 'cashondelivery'],
                    ],
                    'totalRecords' => 170,
                ],
                'ccTypes'               => [
                    'amex',
                    'mastercard',
                    'visa',
                    'discover',
                    'mastercard',
                ],
                'showCcTypeInOrderGrid' => true,
            ],
        ];
    }
}
