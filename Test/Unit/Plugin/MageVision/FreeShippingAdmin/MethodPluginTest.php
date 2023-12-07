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
 * @copyright  Copyright (c) 2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Plugin\MageVision\FreeShippingAdmin;

use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Framework\App\State;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Framework\Webapi\Rest\Request;
use Bolt\Boltpay\Plugin\MageVision\FreeShippingAdmin\MethodPlugin;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\MageVision\FreeShippingAdmin\MethodPlugin
 */
class MethodPluginTest extends BoltTestCase
{
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
    private $methodPlugin;

    private $subject;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    public function setUpInternal()
    {
        $this->subject = $this->getMockBuilder('\MageVision\FreeShippingAdmin\Model\Carrier\Method')
            ->disableOriginalConstructor()
            ->setMethods(['getConfigFlag', 'getConfigData'])
            ->getMock();

        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->methodPlugin = $this->objectManager->create(MethodPlugin::class);
        $this->appState = $this->objectManager->create(State::class);
        $this->restRequest = $this->objectManager->create(Request::class);
    }

    /**
     * @test
     * @dataProvider dataProvider_isBoltCalculation
     * @covers ::isBoltCalculation
     *
     * @param $appState
     * @param $content
     * @param $expected
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \ReflectionException
     */
    public function isBoltCalculation($appState, $content, $expected)
    {
        $this->restRequest->setContent($content);
        $this->appState->setAreaCode($appState);
        TestHelper::setProperty($this->methodPlugin, '_appState', $this->appState);
        TestHelper::setProperty($this->methodPlugin, '_restRequest', $this->restRequest);
        $this->assertEquals($expected, TestHelper::invokeMethod($this->methodPlugin, 'isBoltCalculation'));
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
        $this->assertTrue($this->methodPlugin->afterCollectRates($this->subject, true));
    }

    /**
     * @test
     * @covers ::afterCollectRates
     */
    public function afterCollectRates_withSubjectConfigFlagIsFalse_returnFalse()
    {
        $this->subject->expects(self::once())->method('getConfigFlag')->with('active')->willReturn(false);
        $this->assertFalse($this->methodPlugin->afterCollectRates($this->subject, false));
    }

    /**
     * @test
     * @covers ::afterCollectRates
     */
    public function afterCollectRates_withBoltCalculationIsFalse_returnFalse()
    {
        $this->restRequest->setContent('{"order":{"cart":{"shipments":[{"reference":"freeshippingadmin_freeshippingadmin"}]}}}');
        $this->appState->setAreaCode('non_webapi_rest');
        TestHelper::setProperty($this->methodPlugin, '_appState', $this->appState);
        TestHelper::setProperty($this->methodPlugin, '_restRequest', $this->restRequest);
        $this->subject->expects(self::once())->method('getConfigFlag')->with('active')->willReturn(true);
        $this->assertFalse($this->methodPlugin->afterCollectRates($this->subject, false));
    }

    /**
     * @test
     * @covers ::afterCollectRates
     */
    public function afterCollectRates_willResetRateResult()
    {
        $this->restRequest->setContent('{"order":{"cart":{"shipments":[{"reference":"freeshippingadmin_freeshippingadmin"}]}}}');
        $this->appState->setAreaCode('webapi_rest');
        $this->subject->expects(self::once())->method('getConfigFlag')->with('active')->willReturn(true);

        $this->subject->expects(self::any())->method('getConfigData')
            ->withConsecutive(
                ['title'],
                ['name']
            )
            ->willReturnOnConsecutiveCalls('title', 'name');
        TestHelper::setProperty($this->methodPlugin, '_appState', $this->appState);
        TestHelper::setProperty($this->methodPlugin, '_restRequest', $this->restRequest);
        $result = $this->methodPlugin->afterCollectRates($this->subject, false);
        $rate = $result->getAllRates()[0];
        $this->assertEquals('freeshippingadmin', $rate->getData('carrier'));
        $this->assertEquals('title', $rate->getData('carrier_title'));
        $this->assertEquals('0.00', $rate->getData('cost'));
        $this->assertEquals('freeshippingadmin', $rate->getData('method'));
        $this->assertEquals('name', $rate->getData('method_title'));
        $this->assertEquals(0, $rate->getData('price'));
    }
}
