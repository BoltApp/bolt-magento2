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
 *
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Block\Checkout;

use Bolt\Boltpay\Block\Checkout\Success;
use Bolt\Boltpay\Helper\Config as HelperConfig;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * Class SuccessTest
 *
 * @package Bolt\Boltpay\Test\Unit\Block\Checkout
 */
class SuccessTest extends BoltTestCase
{
    /**
     * @var HelperConfig
     */
    protected $success;

    /**
     * @var Success
     */
    protected $block;

    /**
     * @var StoreManagerInterface
     */
    protected $storeId;

    /**
     * @var WebsiteRepositoryInterface
     */
    protected $websiteId;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->success = $this->objectManager->create(Success::class);
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $this->storeId = $store->getStore()->getId();

        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $this->websiteId = $websiteRepository->get('base')->getId();
    }

    /**
     * @test
     * @dataProvider shouldTrackCheckoutFunnelData
     *
     * @param mixed $config
     * @param mixed $expected
     */
    public function shouldTrackCheckoutFunnel_getValueFromConfig($config, $expected)
    {
        $configData = [
            [
                'path' => HelperConfig::XML_PATH_TRACK_CHECKOUT_FUNNEL,
                'value' => $config,
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $result = $this->success->shouldTrackCheckoutFunnel();

        $this->assertEquals($expected, $result);
    }

    public function shouldTrackCheckoutFunnelData()
    {
        return [
            [true, true],
            [false, false]
        ];
    }


}
