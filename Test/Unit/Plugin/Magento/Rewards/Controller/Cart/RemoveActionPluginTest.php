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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Plugin\Magento\Rewards\Controller\Cart;

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Plugin\Magento\Rewards\Controller\Cart\RemoveActionPlugin;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\TestFramework\Helper\Bootstrap;
/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\Magento\Rewards\Controller\Cart\RemoveActionPlugin
 */
class RemoveActionPluginTest extends BoltTestCase
{
    /**
     * @var \Bolt\Boltpay\Helper\Config
     */
    private $configHelper;

    /**
     * @var \Magento\Reward\Controller\Cart\Remove|\PHPUnit\Framework\MockObject\MockObject
     */
    private $subject;

    /**
     * @var \Bolt\Boltpay\Plugin\Magento\Rewards\Controller\Cart\RemoveActionPlugin
     */
    private $removeActionPlugin;

    /**
     * @var \Magento\Framework\HTTP\PhpEnvironment\Response
     */
    private $response;
    private $objectManager;

    /**
     * Setup method, called before each test
     */
    protected function setUpInternal()
    {
        $this->subject = $this->getMockBuilder('\Magento\Reward\Controller\Cart\Remove')
            ->setMethods(['getRequest', 'getResponse','isAjax','clearHeader','setStatusHeader'])
            ->disableOriginalConstructor()
            ->getMock();

        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->removeActionPlugin = $this->objectManager->create(RemoveActionPlugin::class);
        $this->response = $this->objectManager->create(\Magento\Framework\HTTP\PhpEnvironment\Response::class);
        $this->configHelper = $this->objectManager->create(Config::class);
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
        $store = $this->objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();
        $configData = [
            [
                'path' => Config::XML_PATH_REWARD_POINTS_MINICART,
                'value' => $displayRewardPointsInMinicartConfig,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $this->subject->method('getRequest')->willReturnSelf();
        $this->subject->method('getResponse')->willReturnSelf();
        $this->subject->expects($displayRewardPointsInMinicartConfig ? static::once() : static::never())
            ->method('isAjax')->willReturn($isAjax);
        $this->subject->expects($expectRemoveRedirect ? static::once() : static::never())
            ->method('clearHeader')->with('Location');
        $this->subject->expects($expectRemoveRedirect ? static::once() : static::never())
            ->method('setStatusHeader')->with(200);
        static::assertEquals(
            $this->response,
            $this->removeActionPlugin->afterExecute($this->subject, $this->response)
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
