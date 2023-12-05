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

namespace Bolt\Boltpay\Test\Unit\Block;

use Bolt\Boltpay\Block\Info;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Model\Payment as BoltPayment;
use Magento\Sales\Model\Order;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class InfoTest
 *
 * @coversDefaultClass \Bolt\Boltpay\Block\Info
 */
class InfoTest extends BoltTestCase
{
    /**
     * @var Info|MockObject
     */
    protected $mock;

    protected function setUpInternal()
    {
        $this->mock = $this->createPartialMock(
            Info::class,
            [
                'getArea',
                'getInfo',
                'getMethod',
                'getCcType',
                'getCcLast4',
                'getAdditionalInformation',
                'getOrder',
                'getAdditionalData',
                'setTemplate',
                'toHtml',
            ]
        );
    }

    /**
     * @test
     */
    public function prepareSpecificInformationCreditCard()
    {
        $this->mock->expects(self::once())->method('getInfo')->willReturnSelf();
        $this->mock->expects(self::once())->method('getArea')->willReturn('frontend');
        $this->mock->expects(self::once())->method('getCcType')->willReturn('visa');
        $this->mock->expects(self::once())->method('getCcLast4')->willReturn('1111');
        $data = TestHelper::invokeMethod($this->mock, '_prepareSpecificInformation', [null]);
        $this->assertEquals(
            [
                'Credit Card Type' => 'VISA',
                'Credit Card Number' => 'xxxx-1111'
            ],
            $data->getData()
        );
    }

    /**
     * @test
     */
    public function prepareSpecificInformationPaypal()
    {
        $this->mock->expects(self::once())->method('getInfo')->willReturnSelf();
        $this->mock->expects(self::once())->method('getArea')->willReturn('frontend');
        $this->mock->expects(self::once())->method('getCcType')->willReturn('');
        $this->mock->expects(self::once())->method('getCcLast4')->willReturn('');
        $data = TestHelper::invokeMethod($this->mock, '_prepareSpecificInformation', [null]);
        $this->assertEquals(
            [],
            $data->getData()
        );
    }

    /**
     * @test
     */
    public function displayPaymentMethodTitleCreditCard()
    {
        $this->mock->expects(self::once())->method('getInfo')->willReturnSelf();
        $this->mock->expects(self::once())->method('getAdditionalInformation')->willReturn('vantiv');
        $paymentMock = $this->createPartialMock(
            BoltPayment::class,
            [
                'getConfigData',
            ]
        );
        $orderMock = $this->createPartialMock(
            Order::class,
            [
                'getStoreId',
            ]
        );
        $orderMock->expects(self::once())->method('getStoreId')->willReturn(1);
        $paymentMock->expects(self::once())->method('getConfigData')->willReturn('Bolt Pay');
        $this->mock->expects(self::once())->method('getMethod')->willReturn($paymentMock);
        $this->mock->expects(self::once())->method('getOrder')->willReturn($orderMock);
        $data = TestHelper::invokeMethod($this->mock, 'displayPaymentMethodTitle', [null]);
        $this->assertEquals(
            'Bolt Pay',
            $data
        );
    }

    /**
     * @test
     */
    public function displayPaymentMethodTitlePayPal()
    {
        $this->mock->expects(self::once())->method('getInfo')->willReturnSelf();
        $this->mock->expects(self::once())->method('getAdditionalInformation')->willReturn('paypal');
        $data = TestHelper::invokeMethod($this->mock, 'displayPaymentMethodTitle', [null]);
        $this->assertEquals(
            'Bolt-PayPal',
            $data
        );
    }

    /**
     * @test
     */
    public function displayPaymentMethodTitleApplePay()
    {
        $this->mock->expects(self::once())->method('getInfo')->willReturnSelf();
        $this->mock->expects(self::once())->method('getAdditionalData')->willReturn('applepay');
        $data = TestHelper::invokeMethod($this->mock, 'displayPaymentMethodTitle', [null]);
        $this->assertEquals(
            'Bolt-Applepay',
            $data
        );
    }

    /**
     * @test
     * that toPdf will set custom template and call toHtml
     *
     * @covers ::toPdf
     */
    public function toPdf_always_setsTemplateAndRendersHTML()
    {
        $testHTML = <<<'HTML'
Bolt-Braintree{{pdf_row_separator}}

            Credit Card Type:
        VISA        {{pdf_row_separator}}
            Credit Card Number:
        xxxx-1111        {{pdf_row_separator}}
HTML;
        $this->mock->expects(static::once())->method('setTemplate')->with('Bolt_Boltpay::info/pdf/default.phtml');
        $this->mock->expects(static::once())->method('toHtml')->willReturn($testHTML);
        static::assertEquals($testHTML, $this->mock->toPdf());
    }
}
