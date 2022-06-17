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
use Bolt\Boltpay\Helper\Config;
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

/**
 * Class ProductEventManagerTest
 * @coversDefaultClass \Bolt\Boltpay\Model\CatalogIngestion\ProductEventManager
 * @magentoDbIsolation disabled
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
        $websiteId = $this->storeManager->getWebsite()->getId();
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_ENABLED,
                'value' => 1,
                'scope' => ScopeInterface::SCOPE_WEBSITES,
                'scopeId' => $websiteId,
            ],
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
            $this->assertNotEmpty($bulkId);
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
