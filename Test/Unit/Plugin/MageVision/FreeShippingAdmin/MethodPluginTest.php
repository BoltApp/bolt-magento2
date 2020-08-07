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

namespace Bolt\Boltpay\Test\Unit\Plugin\MageVision\FreeShippingAdmin;

use Bolt\Boltpay\Test\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Magento\Framework\App\State;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Framework\Webapi\Rest\Request;
use Bolt\Boltpay\Plugin\MageVision\FreeShippingAdmin\MethodPlugin;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\MageVision\FreeShippingAdmin\MethodPlugin
 */
class MethodPluginTest extends TestCase
{
    /**
     * @var ResultFactory
     */
    private $rateResultFactory;

    /**
     * @var MethodFactory
     */
    protected $resultMethodFactory;

    /**
     * @var State
     */
    private $appState;

    /**
     * @var Request
     */
    private $restRequest;

    /**
     * @var MethodPlugin
     */
    private $currentMock;

    private $subject;

    public function setUp()
    {
        $this->rateResultFactory = $this->createPartialMock(ResultFactory::class, ['create', 'append']);
        $this->resultMethodFactory = $this->createPartialMock(MethodFactory::class, [
            'create', 'setCarrier',
            'setCarrierTitle', 'setMethod',
            'setMethodTitle', 'setPrice', 'setCost'
        ]);
        $this->appState = $this->createPartialMock(State::class, ['getAreaCode']);
        $this->restRequest = $this->createPartialMock(Request::class, ['getContent']);

        $this->subject = $this->getMockBuilder('\MageVision\FreeShippingAdmin\Model\Carrier\Method')
            ->disableOriginalConstructor()
            ->setMethods(['getConfigFlag', 'getConfigData'])
            ->getMock();

        $this->currentMock = $this->getMockBuilder(MethodPlugin::class)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->rateResultFactory,
                    $this->resultMethodFactory,
                    $this->appState,
                    $this->restRequest,
                ]
            )
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }


    /**
     * @test
     * @dataProvider dataProvider_isBoltCalculation
     * @covers ::isBoltCalculation
     * @param $appState
     * @param $content
     * @param $expected
     *
     *
     * @throws \ReflectionException
     */
    public function isBoltCalculation($appState, $content, $expected)
    {
        $this->restRequest->method('getContent')->willReturn($content);
        $this->appState->expects(self::once())->method('getAreaCode')->willReturn($appState);
        $this->assertEquals($expected, TestHelper::invokeMethod($this->currentMock, 'isBoltCalculation'));
    }

    public function dataProvider_isBoltCalculation()
    {
        return [
            ['webapi_rest', '{"order":{"cart":{"shipments":[{"reference":"freeshippingadmin_freeshippingadmin"}]}}}', true],
            ['non_webapi_rest', '{"order":{"cart":{"shipments":[{"reference":"freeshippingadmin_freeshippingadmin"}]}}}', false],
            ['webapi_rest', '', false],
        ];
    }

    /**
     * @test
     * @covers ::afterCollectRates
     */
    public function afterCollectRates_withResultIsTrue_returnResultObject()
    {
        $this->assertTrue($this->currentMock->afterCollectRates($this->subject, true));
    }

    /**
     * @test
     * @covers ::afterCollectRates
     */
    public function afterCollectRates_withSubjectConfigFlagIsFalse_returnFalse()
    {
        $this->subject->expects(self::once())->method('getConfigFlag')->with('active')->willReturn(false);
        $this->assertFalse($this->currentMock->afterCollectRates($this->subject, false));
    }

    /**
     * @test
     * @covers ::afterCollectRates
     */
    public function afterCollectRates_withBoltCalculationIsFalse_returnFalse()
    {
        $this->restRequest->method('getContent')->willReturn('{"order":{"cart":{"shipments":[{"reference":"freeshippingadmin_freeshippingadmin"}]}}}');
        $this->appState->expects(self::once())->method('getAreaCode')->willReturn('non_webapi_rest');
        $this->subject->expects(self::once())->method('getConfigFlag')->with('active')->willReturn(true);
        $this->assertFalse($this->currentMock->afterCollectRates($this->subject, false));
    }

    /**
     * @test
     * @covers ::afterCollectRates
     */
    public function afterCollectRates_willResetRateResult()
    {
        $this->restRequest->method('getContent')->willReturn('{"order":{"cart":{"shipments":[{"reference":"freeshippingadmin_freeshippingadmin"}]}}}');
        $this->appState->expects(self::once())->method('getAreaCode')->willReturn('webapi_rest');
        $this->subject->expects(self::once())->method('getConfigFlag')->with('active')->willReturn(true);

        $this->subject->expects(self::any())->method('getConfigData')
            ->withConsecutive(
                ['title'],
                ['name']
            )
            ->willReturnOnConsecutiveCalls(
                'title',
                'name'
            );

        $this->rateResultFactory->expects(self::once())->method('create')->willReturnSelf();

        $this->resultMethodFactory->expects(self::once())->method('create')->willReturnSelf();
        $this->resultMethodFactory->expects(self::once())->method('setCarrier')->willReturnSelf();
        $this->resultMethodFactory->expects(self::once())->method('setCarrierTitle')->willReturnSelf();
        $this->resultMethodFactory->expects(self::once())->method('setMethod')->willReturnSelf();
        $this->resultMethodFactory->expects(self::once())->method('setMethodTitle')->willReturnSelf();
        $this->resultMethodFactory->expects(self::once())->method('setPrice')->willReturnSelf();
        $this->resultMethodFactory->expects(self::once())->method('setCost')->willReturnSelf();

        $this->rateResultFactory->expects(self::once())->method('append')->willReturnSelf();
        $this->currentMock->afterCollectRates($this->subject, false);
    }
}
