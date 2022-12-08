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

use Bolt\Boltpay\Helper\Api as ApiHelper;
use Magento\Config\Model\ResourceModel\Config as ResourceConfig;
use Magento\Framework\Amqp\Config as AmqpConfig;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\Api\ProductEventRepositoryInterface;
use Bolt\Boltpay\Model\CatalogIngestion\ProductEventManager;
use Bolt\Boltpay\Api\Data\ProductEventInterface;
use Bolt\Boltpay\Helper\Config as BoltConfig;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Class ProductEventManagerTest
 * @coversDefaultClass \Bolt\Boltpay\Model\CatalogIngestion\ProductEventManager
 */
class ProductEventManagerTest extends BoltTestCase
{
    private const PRODUCT_ID = 1;

    private const PRODUCT_SKU = 'ci_simple';

    private const PRODUCT_NAME = 'Catalog Ingestion Simple Product';

    private const PRODUCT_PRICE = 100;

    private const PRODUCT_QTY = 100;

    private const RESPONSE_SUCCESS_STATUS = 200;

    private const RESPONSE_FAIL_STATUS = 404;

    private const API_KEY = '3c2d5104e7f9d99b66e1c9c550f6566677bf81de0d6f25e121fdb57e47c2eafc';

    private const PUBLISH_KEY = 'ifssM6pxV64H.FXY3JhSL7w9f.c243fecf459ed259019ea58d7a30307edf2f65442c305f086105b2f66fe6c006';

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var ProductEventRepositoryInterface
     */
    private $productEventRepository;

    /**
     * @var ProductEventManager
     */
    private $productEventManager;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Manager
     */
    private $moduleManger;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @var BoltConfig
     */
    private $boltConfig;

    /**
     * @inheritDoc
     */
    protected function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->productEventRepository = $this->objectManager->get(ProductEventRepositoryInterface::class);
        $this->productEventManager = $this->objectManager->get(ProductEventManager::class);
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->moduleManger = $this->objectManager->get(Manager::class);
        $this->resource = $this->objectManager->get(ResourceConnection::class);
        $this->deploymentConfig = $this->objectManager->get(DeploymentConfig::class);;
        $this->boltConfig = $this->objectManager->get(BoltConfig::class);;
        $websiteId = $this->storeManager->getWebsite()->getId();

        $encryptor = $this->objectManager->get(EncryptorInterface::class);
        $apikey = $encryptor->encrypt(self::API_KEY);
        $publishKey = $encryptor->encrypt(self::PUBLISH_KEY);
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_INSTANT_ENABLED,
                'value' => 1,
                'scope' => ScopeInterface::SCOPE_WEBSITES,
                'scopeId' => $websiteId,
            ],
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_INSTANT_ASYNC_ENABLED,
                'value' => 0,
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
        $this->cleanDataBase();
        parent::tearDownInternal();
    }

    /**
     * @test
     * @dataProvider productEventTypes
     */
    public function testPublishProductEvent($productEventType)
    {
        $this->productEventManager->publishProductEvent(self::PRODUCT_ID, $productEventType);
        $productEvent = $this->productEventRepository->getByProductId(self::PRODUCT_ID);
        $this->assertEquals(self::PRODUCT_ID, $productEvent->getProductId());
        $this->assertEquals($productEventType, $productEvent->getType());
        $this->assertNotEmpty($productEvent->getCreatedAt());
    }

    /**
     * @test
     * @dataProvider productEventTypes
     */
    public function testDeleteProductEvent($productEventType)
    {
        $this->productEventManager->publishProductEvent(self::PRODUCT_ID, $productEventType);
        $result = $this->productEventManager->deleteProductEvent(self::PRODUCT_ID);
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function testPublishProductEventAsyncJob()
    {
        $amqpConfigExist = false;

        if (class_exists('Magento\Framework\Amqp\Config')) {
            $queueConfig = $this->deploymentConfig->getConfigData(AmqpConfig::QUEUE_CONFIG);
            $amqpConfigExist = isset($queueConfig[AmqpConfig::AMQP_CONFIG]) ? true : false;
        }

        if ($this->moduleManger->isEnabled('Magento_AsynchronousOperations') && !$amqpConfigExist) {
            $this->expectExceptionMessage('Unknown connection name amqp');
        }

        if (!$this->moduleManger->isEnabled('Magento_AsynchronousOperations') && !$amqpConfigExist) {
            $this->expectExceptionMessage('Magento Asynchronous Operations is not supported on your magento version, please verify.');
        }

        $bulkId = $this->productEventManager->publishProductEventAsyncJob(
            self::PRODUCT_ID,
            ProductEventInterface::TYPE_UPDATE
        );

        if ($this->moduleManger->isEnabled('Magento_AsynchronousOperations') && $amqpConfigExist) {
            $amqpConfig = $this->objectManager->get(AmqpConfig::class);
            $channel = $amqpConfig->getChannel();
            $latestMsg = $channel->basic_get('async.operations.all', true);

            $topicName = (version_compare($this->boltConfig->getStoreVersion(), '2.4.0', '<'))
                ? 'async.V1.bolt.boltpay.producteventrequest.POST'
                : 'async.bolt.boltpay.api.producteventmanagerinterface.sendproductevent.post';

            $this->assertNotEmpty($bulkId);
            $this->assertStringContainsString('"bulk_uuid":"'.$bulkId.'"', $latestMsg->getBody());
            $this->assertStringContainsString('"topic_name":"'.$topicName.'"', $latestMsg->getBody());
            $this->assertStringContainsString('{\\\\\\"productEvent\\\\\\":{\\\\\\"product_id\\\\\\":'.self::PRODUCT_ID.',\\\\\\"type\\\\\\":\\\\\\"update\\\\\\"', $latestMsg->getBody());
            $this->assertStringContainsString('"status":4,"result_message":null,"error_code":null', $latestMsg->getBody());
        }
    }

    /**
     * @test
     */
    public function testSendProductEvent()
    {
        $apiHelper = $this->createPartialMock(ApiHelper::class, ['sendRequest']);
        $apiHelper->expects(self::once())->method('sendRequest')->willReturn(self::RESPONSE_SUCCESS_STATUS);
        TestHelper::setProperty($this->productEventManager, 'apiHelper', $apiHelper);
        $product = $this->createProduct();

        $this->productEventManager->publishProductEvent($product->getId(), ProductEventInterface::TYPE_UPDATE);
        $productEvent = $this->productEventRepository->getByProductId($product->getId());
        $this->productEventManager->sendProductEvent($productEvent);
    }

    /**
     * @test
     */
    public function testRunInstantProductEvent_withoutAsync()
    {
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $websiteId = $store->getWebsite()->getId();
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_INSTANT_ENABLED,
                'value' => 1,
                'scope' => ScopeInterface::SCOPE_WEBSITES,
                'scopeId' => $websiteId,
            ],
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_INSTANT_ASYNC_ENABLED,
                'value' => 0,
                'scope' => ScopeInterface::SCOPE_WEBSITES,
                'scopeId' => $websiteId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $apiHelper = $this->createPartialMock(ApiHelper::class, ['sendRequest']);
        $apiHelper->expects(self::once())->method('sendRequest')->willReturn(self::RESPONSE_SUCCESS_STATUS);
        TestHelper::setProperty($this->productEventManager, 'apiHelper', $apiHelper);
        $product = $this->createProduct();

        $this->productEventManager->runInstantProductEvent(
            $product->getId(),
            ProductEventInterface::TYPE_UPDATE
        );
    }

    /**
     * @test
     */
    public function testRunInstantProductEvent_withTwiceCallsSendingOne_withoutAsync()
    {
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $websiteId = $store->getWebsite()->getId();
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_INSTANT_ENABLED,
                'value' => 1,
                'scope' => ScopeInterface::SCOPE_WEBSITES,
                'scopeId' => $websiteId,
            ],
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_INSTANT_ASYNC_ENABLED,
                'value' => 0,
                'scope' => ScopeInterface::SCOPE_WEBSITES,
                'scopeId' => $websiteId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $apiHelper = $this->createPartialMock(ApiHelper::class, ['sendRequest']);
        $apiHelper->expects(self::once())->method('sendRequest')->willReturn(self::RESPONSE_SUCCESS_STATUS);
        TestHelper::setProperty($this->productEventManager, 'apiHelper', $apiHelper);
        $product = $this->createProduct();

        $this->productEventManager->runInstantProductEvent(
            $product->getId(),
            ProductEventInterface::TYPE_UPDATE
        );

        $this->productEventManager->runInstantProductEvent(
            $product->getId(),
            ProductEventInterface::TYPE_UPDATE
        );
    }

    /**
     * @test
     */
    public function testRunInstantProductEvent_withAsync()
    {
        $amqpConfigExist = false;
        if (class_exists('Magento\Framework\Amqp\Config')) {
            $queueConfig = $this->deploymentConfig->getConfigData(AmqpConfig::QUEUE_CONFIG);
            $amqpConfigExist = isset($queueConfig[AmqpConfig::AMQP_CONFIG])? true : false;
        }

        if ($this->moduleManger->isEnabled('Magento_AsynchronousOperations') && !$amqpConfigExist) {
            $this->expectExceptionMessage('Unknown connection name amqp');
        }

        if (!$this->moduleManger->isEnabled('Magento_AsynchronousOperations') && !$amqpConfigExist) {
            $this->expectExceptionMessage('Magento Asynchronous Operations is not supported on your magento version, please verify.');
        }

        $store = $this->objectManager->get(StoreManagerInterface::class);
        $websiteId = $store->getWebsite()->getId();
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_INSTANT_ENABLED,
                'value' => 1,
                'scope' => ScopeInterface::SCOPE_WEBSITES,
                'scopeId' => $websiteId,
            ],
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_INSTANT_ASYNC_ENABLED,
                'value' => 1,
                'scope' => ScopeInterface::SCOPE_WEBSITES,
                'scopeId' => $websiteId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);

        $product = $this->createProduct();

        $this->productEventManager->runInstantProductEvent(
            $product->getId(),
            ProductEventInterface::TYPE_UPDATE
        );

        if ($this->moduleManger->isEnabled('Magento_AsynchronousOperations') && $amqpConfigExist) {
            $amqpConfig = $this->objectManager->get(AmqpConfig::class);
            $channel = $amqpConfig->getChannel();
            $latestMsg = $channel->basic_get('async.operations.all', true);
            $topicName = (version_compare($this->boltConfig->getStoreVersion(), '2.4.0', '<'))
                ? 'async.V1.bolt.boltpay.producteventrequest.POST'
                : 'async.bolt.boltpay.api.producteventmanagerinterface.sendproductevent.post';

            $this->assertStringContainsString('"topic_name":"'.$topicName.'"', $latestMsg->getBody());
            $this->assertStringContainsString('{\\\\\\"productEvent\\\\\\":{\\\\\\"product_id\\\\\\":'.$product->getId().',\\\\\\"type\\\\\\":\\\\\\"update\\\\\\"', $latestMsg->getBody());
            $this->assertStringContainsString('"status":4,"result_message":null,"error_code":null', $latestMsg->getBody());
        }
    }

    /**
     * Create simple product
     *
     * @return ProductInterface
     */
    private function createProduct(): ProductInterface
    {
        $product = TestUtils::createSimpleProduct();
        $productRepository = $this->objectManager->create(ProductRepositoryInterface::class);

        $product->setName(self::PRODUCT_NAME)
            ->setSku(self::PRODUCT_SKU)
            ->setPrice(self::PRODUCT_PRICE)
            ->setStoreId($this->storeManager->getStore()->getId())
            ->setIsObjectNew(true);

        TestUtils::createStockItemForProduct($product, self::PRODUCT_QTY);

        return $productRepository->save($product);
    }

    /**
     * Cleaning test data from database
     *
     * @return void
     */
    private function cleanDataBase(): void
    {
        $websiteId = $this->storeManager->getWebsite()->getId();
        $configResource = $this->objectManager->get(ResourceConfig::class);
        $configResource->deleteConfig(BoltConfig::XML_PATH_PUBLISHABLE_KEY_CHECKOUT, ScopeInterface::SCOPE_STORES, $websiteId);
        $configResource->deleteConfig(BoltConfig::XML_PATH_API_KEY, ScopeInterface::SCOPE_STORES, $websiteId);
        $connection = $this->resource->getConnection('default');
        $connection->truncateTable($this->resource->getTableName('bolt_product_event'));
        $connection->delete($connection->getTableName('catalog_product_entity'));
    }

    /**
     * Available product event types data provider
     *
     * @return array
     */
    public function productEventTypes(): array
    {
        return [
            [ProductEventInterface::TYPE_CREATE],
            [ProductEventInterface::TYPE_UPDATE],
            [ProductEventInterface::TYPE_DELETE],
        ];
    }
}
