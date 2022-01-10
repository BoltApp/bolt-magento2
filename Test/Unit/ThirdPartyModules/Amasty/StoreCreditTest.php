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

namespace Bolt\Boltpay\Test\Unit\ThirdPartyModules\Amasty;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\ThirdPartyModules\Amasty\StoreCredit;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * @coversDefaultClass \Bolt\Boltpay\ThirdPartyModules\Amasty\StoreCredit
 */
class StoreCreditTest extends BoltTestCase
{
    private $objectManager;

    /**
     * @var StoreCredit
     */
    private $storeCredit;

    /**
     * @var Discount
     */
    private $discountHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeCredit = $this->objectManager->create(StoreCredit::class);
        $this->discountHelper = $this->objectManager->create(Discount::class);
        $this->bugsnagHelper = $this->objectManager->create(Bugsnag::class);
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
        $instance = new StoreCredit($this->discountHelper, $this->bugsnagHelper, $this->configHelper);
        static::assertAttributeEquals($this->discountHelper, 'discountHelper', $instance);
        static::assertAttributeEquals($this->bugsnagHelper, 'bugsnagHelper', $instance);
        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
    }

    /**
     * @test
     * that filterProcessLayout will not add layout if Amasty Store Credit on Shopping Cart
     * is not enabled in the config
     *
     * @covers ::filterProcessLayout
     */
    public function filterProcessLayout_notEnabledInConfig_doesNotAddLayout()
    {
        static::assertEquals([], $this->storeCredit->filterProcessLayout([]));
    }

    /**
     * @test
     * that filterProcessLayout adds Amasty Store Credit total and button layout if enabled in the config
     *
     * @covers ::filterProcessLayout
     */
    public function filterProcessLayout_ifEnabledInConfig_addsModuleSpecificLayout()
    {
        $store = $this->objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();
        $configData = [
            [
                'path' => Config::XML_PATH_AMASTY_STORE_CREDIT,
                'value' => true,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $result = $this->storeCredit->filterProcessLayout([]);
        static::assertEquals(
            [
                'component' => 'Amasty_StoreCredit/js/view/checkout/totals/store-credit',
                'sortOrder' => '90'
            ],
            $result['components']['block-totals']['children']['amstorecredit_total']
        );
        static::assertEquals(
            [
                'component' => 'Amasty_StoreCredit/js/view/checkout/payment/store-credit'
            ],
            $result['components']['block-totals']['children']['amstorecredit_form']
        );
    }
}
