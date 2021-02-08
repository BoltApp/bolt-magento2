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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Plugin\Magento\Ui\Component\Form\Element;

use Bolt\Boltpay\Test\Unit\BoltTestCase;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\Magento\Ui\Component\Form\Element\SelectPlugin
 */
class SelectPluginTest extends BoltTestCase
{
    /**
     * @var \Bolt\Boltpay\Plugin\Magento\Ui\Component\Form\Element\SelectPlugin|\PHPUnit_Framework_MockObject_MockObject
     */
    private $currentMock;

    /**
     * @var \Magento\Ui\Component\Form\Element\Select|\PHPUnit_Framework_MockObject_MockObject
     */
    private $subjectMock;

    /**
     * @var \Magento\Framework\View\Element\UiComponent\Context|\PHPUnit_Framework_MockObject_MockObject
     */
    private $contextMock;

    protected function setUpInternal()
    {
        $this->subjectMock = $this->createMock(\Magento\Ui\Component\Form\Element\Select::class);
        $this->contextMock = $this->createMock(\Magento\Framework\View\Element\UiComponent\Context::class);
        $this->currentMock = $this->createPartialMock(
            \Bolt\Boltpay\Plugin\Magento\Ui\Component\Form\Element\SelectPlugin::class,
            []
        );
    }

    /**
     * @test
     * that afterPrepare appends individual Boltpay processors as payment method options
     *
     * @dataProvider afterPrepareDataProvider
     *
     * @covers ::afterPrepare
     *
     * @param string $namespace
     * @param string $name
     * @param array  $configBefore
     * @param array  $configAfter
     */
    public function afterPrepare($namespace, $name, $configBefore, $configAfter)
    {
        $this->subjectMock->expects(static::once())->method('getContext')->willReturn($this->contextMock);
        $this->contextMock->expects(static::once())->method('getNamespace')->willReturn($namespace);
        $this->subjectMock->expects(static::once())->method('getName')->willReturn($name);
        $this->subjectMock->expects($configBefore != $configAfter ? static::once() : static::never())
            ->method('getData')->with('config')->willReturn($configBefore);
        $this->subjectMock->expects($configBefore != $configAfter ? static::once() : static::never())
            ->method('setData')->with('config', $configAfter);
        static::assertNull($this->currentMock->afterPrepare($this->subjectMock, null));
    }

    /**
     * Data provider for @see afterPrepare
     */
    public function afterPrepareDataProvider()
    {
        $appendedOptions = [
            [
                'value'         => 'boltpay_paypal',
                'label'         => 'Bolt-PayPal',
                '__disableTmpl' => true,
            ],
            [
                'value'         => 'boltpay_afterpay',
                'label'         => 'Bolt-Afterpay',
                '__disableTmpl' => true,
            ],
            [
                'value'         => 'boltpay_affirm',
                'label'         => 'Bolt-Affirm',
                '__disableTmpl' => true,
            ],
            [
                'value'         => 'boltpay_braintree',
                'label'         => 'Bolt-Braintree',
                '__disableTmpl' => true,
            ],
            [
                'value'         => 'boltpay_applepay',
                'label'         => 'Bolt-ApplePay',
                '__disableTmpl' => true,
            ],
            [
                'value'         => 'boltpay_amex',
                'label'         => 'Bolt-Amex',
                '__disableTmpl' => true,
            ],
            [
                'value'         => 'boltpay_discover',
                'label'         => 'Bolt-Discover',
                '__disableTmpl' => true,
            ],
            [
                'value'         => 'boltpay_mastercard',
                'label'         => 'Bolt-MC',
                '__disableTmpl' => true,
            ],
            [
                'value'         => 'boltpay_visa',
                'label'         => 'Bolt-Visa',
                '__disableTmpl' => true,
            ],
        ];
        return [
            'Order grid, payment method column - appends options' => [
                'namespace'    => 'sales_order_grid',
                'name'         => 'payment_method',
                'configBefore' => [],
                'configAfter'  => [
                    'options' => $appendedOptions,
                ]
            ],
            'Invoice grid, payment method column - appends options' => [
                'namespace'    => 'sales_order_invoice_grid',
                'name'         => 'payment_method',
                'configBefore' => [],
                'configAfter'  => [
                    'options' => $appendedOptions,
                ]
            ],
            'Order grid, status column - does not append options' => [
                'namespace'    => 'sales_order_grid',
                'name'         => 'status',
                'configBefore' => [],
                'configAfter'  => []
            ],
        ];
    }
}
