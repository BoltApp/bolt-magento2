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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\ThirdPartyModules\Magento;

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\ThirdPartyModules\Magento\CustomerBalance;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * @coversDefaultClass \Bolt\Boltpay\ThirdPartyModules\Magento\CustomerBalance
 */
class CustomerBalanceTest extends BoltTestCase
{
    private $configHelper;

    private $objectManager;

    private $customerBalance;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->customerBalance = $this->objectManager->create(CustomerBalance::class);
        $this->configHelper = $this->objectManager->create(Config::class);
    }

    /**
     * @test
     * that constructor sets the expected internal properties
     *
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperties()
    {
        $instance = new CustomerBalance($this->configHelper);
        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
    }

    /**
     * @test
     * that filterProcessLayout will not add third party layout if Magento EE Customer Balance on Shopping Cart
     * is not enabled in the config
     *
     * @covers ::filterProcessLayout
     */
    public function filterProcessLayout_notEnabledInConfig_doesNotAddLayout()
    {
        static::assertEquals([], $this->customerBalance->filterProcessLayout([]));
    }

    /**
     * @test
     * that collectCartDiscountJsLayout adds Magento EE Customer Balance button layout
     * if enabled in the config
     *
     * @covers ::filterProcessLayout
     */
    public function filterProcessLayout_ifEnabledInConfig_addsModuleSpecificLayout()
    {
        $store = $this->objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();
        $configData = [
            [
                'path' => Config::XML_PATH_STORE_CREDIT,
                'value' => true,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->customerBalance->filterProcessLayout([]);
        static::assertEquals(
            [
                'component' => 'Magento_CustomerBalance/js/view/payment/customer-balance'
            ],
            $result['components']['block-totals']['children']['storeCredit']
        );
    }
}
