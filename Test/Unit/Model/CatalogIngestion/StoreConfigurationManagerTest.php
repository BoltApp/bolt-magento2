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

namespace Bolt\Boltpay\Test\Unit\Model\CatalogIngestion;

use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Model\CatalogIngestion\StoreConfigurationManager;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\Helper\Config as BoltConfig;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Config\Model\Config;

/**
 * Class StoreConfigurationManagerTest
 * @coversDefaultClass \Bolt\Boltpay\Model\CatalogIngestion\StoreConfigurationManager
 */
class StoreConfigurationManagerTest extends BoltTestCase
{
    private const RESPONSE_SUCCESS_STATUS = 200;

    private const RESPONSE_FAIL_STATUS = 404;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var StoreConfigurationManager
     */
    private $storeConfigurationManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @inheritDoc
     */
    protected function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->storeConfigurationManager = $this->objectManager->get(StoreConfigurationManager::class);
        $this->config = $this->objectManager->get(Config::class);

        $featureSwitches = $this->createMock(Decider::class);
        $featureSwitches->method('isStoreConfigurationWebhookEnabled')->willReturn(true);

        $websiteId = $this->storeManager->getWebsite()->getId();
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_SYSTEM_CONFIGURATION_UPDATE_REQUEST,
                'value' => 1,
                'scope' => ScopeInterface::SCOPE_WEBSITES,
                'scopeId' => $websiteId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
    }

    /**
     * @test
     */
    public function testRequestStoreConfigurationUpdated()
    {
        $apiHelper = $this->createPartialMock(ApiHelper::class, ['sendRequest']);
        $apiHelper->expects(self::once())->method('sendRequest')->willReturn(self::RESPONSE_SUCCESS_STATUS);
        TestHelper::setProperty($this->storeConfigurationManager, 'apiHelper', $apiHelper);
        $this->config->setDataByPath(BoltConfig::XML_PATH_CATALOG_INGESTION_INSTANT_EVENT, 0);
        $this->config->save();
    }
}
