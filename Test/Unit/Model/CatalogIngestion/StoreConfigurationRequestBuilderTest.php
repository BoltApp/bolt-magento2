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

use Bolt\Boltpay\Model\CatalogIngestion\StoreConfigurationRequestBuilder;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Helper\Config as BoltConfig;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class ProductEventRequestBuilderTest
 * @coversDefaultClass \Bolt\Boltpay\Model\CatalogIngestion\StoreConfigurationRequestBuilder
 */
class StoreConfigurationRequestBuilderTest extends BoltTestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var StoreConfigurationRequestBuilder
     */
    private $storeConfigurationRequestBuilder;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @inheritDoc
     */
    protected function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeConfigurationRequestBuilder =$this->objectManager->get(StoreConfigurationRequestBuilder::class);
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $websiteId = $this->storeManager->getWebsite()->getId();
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_SYSTEM_CONFIGURATION_UPDATE_REQUEST,
                'value' => 1,
                'scope' => ScopeInterface::SCOPE_WEBSITES,
                'scopeId' => $websiteId,
            ],
            [
                'path'    => BoltConfig::XML_PATH_PUBLISHABLE_KEY_CHECKOUT,
                'value'   => 'publish_key',
                'scope'   => ScopeInterface::SCOPE_STORES,
                'scopeId' => $websiteId,
            ],
            [
                'path'    => BoltConfig::XML_PATH_API_KEY,
                'value'   => 'api_key',
                'scope'   => ScopeInterface::SCOPE_STORES,
                'scopeId' => $websiteId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
    }

    /**
     * @test
     */
    public function testGetRequest()
    {
        $request = $this->storeConfigurationRequestBuilder->getRequest($this->storeManager->getStore()->getCode());
        $apiData = $request->getApiData();
        $expectedApiData = [
            'store_code' => $this->storeManager->getStore()->getCode()
        ];
        $this->assertEquals($apiData, $expectedApiData);
    }

    /**
     * @test
     */
    public function testGetRequest_WithoutApiKeys()
    {
        $this->expectExceptionMessage('Bolt API Key or Publishable Key - Multi Step is not configured');
        $websiteId = $this->storeManager->getWebsite()->getId();
        $configData = [
            [
                'path'    => BoltConfig::XML_PATH_PUBLISHABLE_KEY_CHECKOUT,
                'value'   => '',
                'scope'   => ScopeInterface::SCOPE_STORES,
                'scopeId' => $websiteId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $this->storeConfigurationRequestBuilder->getRequest($this->storeManager->getStore()->getCode());
    }
}
