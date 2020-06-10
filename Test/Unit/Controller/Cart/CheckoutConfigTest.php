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

namespace Bolt\Boltpay\Test\Unit\Controller\Cart;

use PHPUnit\Framework\TestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

/**
 * @coversDefaultClass \Bolt\Boltpay\Controller\Cart\CheckoutConfig
 */
class CheckoutConfigTest extends TestCase
{
    /**
     * @var \Magento\Framework\App\Action\Context
     */
    private $context;

    /**
     * @var \Magento\Checkout\Model\Session|\PHPUnit_Framework_MockObject_MockObject
     */
    private $checkoutSession;

    /**
     * @var \Bolt\Boltpay\Controller\Cart\CheckoutConfig
     */
    private $currentMock;

    /**
     * @var \Magento\Checkout\Model\CompositeConfigProvider|\PHPUnit_Framework_MockObject_MockObject
     */
    private $configProvider;

    /**
     * Setup method, called before each test
     */
    protected function setUp()
    {
        $om = new ObjectManager($this);
        $this->context = $om->getObject(\Magento\Framework\App\Action\Context::class);
        $this->checkoutSession = $this->createMock(\Magento\Checkout\Model\Session::class);
        $this->configProvider = $this->createMock(\Magento\Checkout\Model\CompositeConfigProvider::class);
        $this->currentMock = $om->getObject(
            \Bolt\Boltpay\Controller\Cart\CheckoutConfig::class,
            [
                'context'         => $this->context,
                'checkoutSession' => $this->checkoutSession,
                'configProvider' => $this->configProvider
            ]
        );
    }

    /**
     * @test
     * that __construct sets properties to provided values
     *
     * @covers ::__construct
     */
    public function __construct_always_setsProperties()
    {
        $instance = new \Bolt\Boltpay\Controller\Cart\CheckoutConfig(
            $this->context,
            $this->checkoutSession,
            $this->configProvider
        );
        static::assertAttributeEquals($this->checkoutSession, 'checkoutSession', $instance);
        static::assertAttributeEquals($this->configProvider, 'configProvider', $instance);
    }

    /**
     * @test
     * that execute doesn't collect configuration from config provider and returns an empty response
     * if session quote id is not defined
     *
     * @covers ::execute
     */
    public function execute_withEmptySessionQuoteId_returnsEmptyResponse()
    {
        $this->checkoutSession->expects(static::once())->method('getQuoteId')->willReturn(null);
        $jsonResultMock = $this->createMock(\Magento\Framework\Controller\Result\Json::class);
        $resultFactory = static::getObjectAttribute($this->currentMock, 'resultFactory');
        $resultFactory->expects(static::once())->method('create')
            ->with(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)
            ->willReturn($jsonResultMock);
        $jsonResultMock->expects(static::once())->method('setData')->with([])->willReturnSelf();
        $this->configProvider->expects(static::never())->method('getConfig');
        static::assertEquals($jsonResultMock, $this->currentMock->execute());
    }

    /**
     * @test
     * that execute returns checkout config from composite provider if session quote id is set
     *
     * @covers ::execute
     */
    public function execute_withVariousConfigProviders_returnsConfigJSONResponse()
    {
        $this->checkoutSession->expects(static::once())->method('getQuoteId')->willReturn(10001);
        $jsonResultMock = $this->createMock(\Magento\Framework\Controller\Result\Json::class);
        $resultFactory = static::getObjectAttribute($this->currentMock, 'resultFactory');
        $resultFactory->expects(static::once())->method('create')
            ->with(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)
            ->willReturn($jsonResultMock);
        $config = [
            'authentication' => [
                'reward' => [
                    'isAvailable'         => true,
                    'tooltipLearnMoreUrl' => 'http://m2ee.local/reward-points',
                    'tooltipMessage'      => 'Sign in now and earn 0 Reward points (<span class="price">$1.00</span>) for this order.'
                ]
            ],
            'review'         => [
                'reward' => [
                    'removeUrl' => 'http://m2ee.local/reward/cart/remove/'
                ]
            ]
        ];
        $this->configProvider->expects(static::once())->method('getConfig')
            ->willReturn($config);
        $jsonResultMock->expects(static::once())->method('setData')->with($config)->willReturnSelf();

        static::assertEquals($jsonResultMock, $this->currentMock->execute());
    }
}
