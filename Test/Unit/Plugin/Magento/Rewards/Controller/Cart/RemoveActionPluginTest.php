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

namespace Bolt\Boltpay\Test\Unit\Plugin\Magento\Rewards\Controller\Cart;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\Magento\Rewards\Controller\Cart\RemoveActionPlugin
 */
class RemoveActionPluginTest extends TestCase
{
    /**
     * @var \Bolt\Boltpay\Helper\Config|\PHPUnit\Framework\MockObject\MockObject
     */
    private $configHelper;

    /**
     * @var \Magento\Reward\Controller\Cart\Remove|\PHPUnit\Framework\MockObject\MockObject
     */
    private $subject;

    /**
     * @var \Bolt\Boltpay\Plugin\Magento\Rewards\Controller\Cart\RemoveActionPlugin
     */
    private $currentMock;

    /**
     * @var \Magento\Framework\App\Request\Http|\PHPUnit\Framework\MockObject\MockObject
     */
    private $requestMock;

    /**
     * @var \Magento\Framework\HTTP\PhpEnvironment\Response|\PHPUnit\Framework\MockObject\MockObject
     */
    private $responseMock;

    /**
     * Setup method, called before each test
     */
    protected function setUp()
    {
        $this->configHelper = $this->createMock(\Bolt\Boltpay\Helper\Config::class);
        $this->subject = $this->getMockBuilder('\Magento\Reward\Controller\Cart\Remove')
            ->setMethods(['getRequest', 'getResponse'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->currentMock = new \Bolt\Boltpay\Plugin\Magento\Rewards\Controller\Cart\RemoveActionPlugin(
            $this->configHelper
        );
        $this->requestMock = $this->createMock(\Magento\Framework\App\Request\Http::class);
        $this->responseMock = $this->createMock(\Magento\Framework\HTTP\PhpEnvironment\Response::class);
        $this->subject->method('getRequest')->willReturn($this->requestMock);
        $this->subject->method('getResponse')->willReturn($this->responseMock);
    }

    /**
     * @test
     * that afterExecute will remove redirect from response
     * only if request is AJAX and reward points on minicart is enabled
     *
     * @dataProvider afterExecute_withVariousStatesProvider
     *
     * @covers ::afterExecute
     *
     * @param bool $displayRewardPointsInMinicartConfig flag value
     * @param bool $isAjax current request flag
     * @param bool $expectRemoveRedirect whether or not to expect response alteration
     */
    public function afterExecute_withVariousStates_removesRedirectIfFeatureEnabledAndRequestIsAjax(
        $displayRewardPointsInMinicartConfig,
        $isAjax,
        $expectRemoveRedirect
    ) {
        $this->configHelper->expects(static::once())->method('displayRewardPointsInMinicartConfig')
            ->willReturn($displayRewardPointsInMinicartConfig);
        $this->requestMock->expects($displayRewardPointsInMinicartConfig ? static::once() : static::never())
            ->method('isAjax')->willReturn($isAjax);
        $this->responseMock->expects($expectRemoveRedirect ? static::once() : static::never())
            ->method('clearHeader')->with('Location');
        $this->responseMock->expects($expectRemoveRedirect ? static::once() : static::never())
            ->method('setStatusHeader')->with(200);
        static::assertEquals(
            $this->responseMock,
            $this->currentMock->afterExecute($this->subject, $this->responseMock)
        );
    }

    /**
     * Data provider for {@see afterExecute_withVariousStates_removesRedirectIfFeatureEnabledAndRequestIsAjax}
     */
    public function afterExecute_withVariousStatesProvider()
    {
        return [
            ['displayRewardPointsInMinicartConfig' => true, 'isAjax' => true, 'expectRemoveRedirect' => true],
            ['displayRewardPointsInMinicartConfig' => true, 'isAjax' => false, 'expectRemoveRedirect' => false],
            ['displayRewardPointsInMinicartConfig' => false, 'isAjax' => true, 'expectRemoveRedirect' => false],
            ['displayRewardPointsInMinicartConfig' => false, 'isAjax' => false, 'expectRemoveRedirect' => false],
        ];
    }

    /**
     * @test
     * that __construct sets properties to provided values
     *
     * @covers ::__construct
     */
    public function __construct_always_setsProperty()
    {
        $instance = new \Bolt\Boltpay\Plugin\Magento\Rewards\Controller\Cart\RemoveActionPlugin($this->configHelper);
        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
    }
}
