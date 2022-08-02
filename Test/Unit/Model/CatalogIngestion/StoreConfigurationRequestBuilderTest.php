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
use Magento\Config\Model\ResourceModel\Config as ResourceConfig;
use Magento\Framework\Encryption\EncryptorInterface;
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
    private const API_KEY = '3c2d5104e7f9d99b66e1c9c550f6566677bf81de0d6f25e121fdb57e47c2eafc';

    private const PUBLISH_KEY = 'ifssM6pxV64H.FXY3JhSL7w9f.c243fecf459ed259019ea58d7a30307edf2f65442c305f086105b2f66fe6c006';

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
        $encryptor = $this->objectManager->get(EncryptorInterface::class);
        $apikey = $encryptor->encrypt(self::API_KEY);
        $publishKey = $encryptor->encrypt(self::PUBLISH_KEY);
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_SYSTEM_CONFIGURATION_UPDATE_REQUEST,
                'value' => 1,
                'scope' => ScopeInterface::SCOPE_WEBSITES,
                'scopeId' => $websiteId,
            ],
            [
                'path'    => BoltConfig::XML_PATH_PUBLISHABLE_KEY_CHECKOUT,
                'value'   => $publishKey,
                'scope'   => ScopeInterface::SCOPE_STORES,
                'scopeId' => $websiteId,
            ],
            [
                'path'    => BoltConfig::XML_PATH_API_KEY,
                'value'   => $apikey,
                'scope'   => ScopeInterface::SCOPE_STORES,
                'scopeId' => $websiteId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
    }

    /**
     * @inheritdoc
     */
    protected function tearDownInternal(): void
    {
        $websiteId = $this->storeManager->getWebsite()->getId();
        $configResource = $this->objectManager->get(ResourceConfig::class);
        $configResource->deleteConfig(BoltConfig::XML_PATH_CATALOG_INGESTION_ENABLED, ScopeInterface::SCOPE_WEBSITES, $websiteId);
        $configResource->deleteConfig(BoltConfig::XML_PATH_PUBLISHABLE_KEY_CHECKOUT, ScopeInterface::SCOPE_STORES, $websiteId);
        $configResource->deleteConfig(BoltConfig::XML_PATH_API_KEY, ScopeInterface::SCOPE_STORES, $websiteId);
        parent::tearDownInternal();
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
