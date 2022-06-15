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

use Bolt\Boltpay\Api\ProductEventRepositoryInterface;
use Bolt\Boltpay\Helper\Config as BoltConfig;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Model\CatalogIngestion\ProductEventProcessor;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\Model\CatalogIngestion\ProductEventRequestBuilder;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Class ProductEventRequestBuilderTest
 * @coversDefaultClass \Bolt\Boltpay\Model\CatalogIngestion\ProductEventRequestBuilder
 * @magentoDbIsolation disabled
 */
class ProductEventRequestBuilderTest extends BoltTestCase
{
    private const PRODUCT_SKU = 'ci_simple';

    private const PRODUCT_NAME = 'Catalog Ingestion Simple Product';

    private const PRODUCT_PRICE = 100;

    private const PRODUCT_QTY = 100;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var ProductEventRequestBuilder
     */
    private $productEventRequestBuilder;

    /**
     * @var ProductEventRepositoryInterface
     */
    private $productEventRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @inheritDoc
     */
    protected function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->productEventRequestBuilder =$this->objectManager->get(ProductEventRequestBuilder::class);
        $this->resource = $this->objectManager->get(ResourceConnection::class);
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->productEventRepository = $this->objectManager->get(ProductEventRepositoryInterface::class);
        $productEventProcessor = $this->objectManager->get(ProductEventProcessor::class);
        $featureSwitches = $this->createMock(Decider::class);
        $featureSwitches->method('isCatalogIngestionEnabled')->willReturn(true);
        TestHelper::setProperty($productEventProcessor, 'featureSwitches', $featureSwitches);
        $websiteId = $this->storeManager->getWebsite()->getId();
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_ENABLED,
                'value' => 1,
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
     */
    public function testGetRequest()
    {
        $product = $this->createProduct();
        $productEvent = $this->productEventRepository->getByProductId($product->getId());
        $request = $this->productEventRequestBuilder->getRequest($productEvent, $this->storeManager->getWebsite()->getId());
        $apiData = $request->getApiData();
        $expectedApiData = [
            'operation' => $productEvent->getType(),
            'timestamp' => strtotime($productEvent->getCreatedAt()),
            'product' => [
                'product' =>
                    [
                        'MerchantProductID' => $product->getId(),
                        'ProductType' => 'simple',
                        'SKU' => 'ci_simple',
                        'URL' => $product->getProductUrl(),
                        'Name' => 'Catalog Ingestion Simple Product',
                        'ManageInventory' => false,
                        'Visibility' => 'visible',
                        'Backorder' => 'no',
                        'Availability' => 'in_stock',
                        'ShippingRequired' => true,
                        'Prices' => [
                            [
                                'ListPrice' => 100.0,
                                'SalePrice' => 100.0,
                                'Currency' => 'USD',
                                'Locale' => 'en_US',
                                'Unit' => ''
                            ]
                        ],
                        'Inventories' => [
                            [
                                'FulfillmentCenterID' => 'Default Stock',
                                'InventoryLevel' => 0
                            ]
                        ],
                        'Media' => [],
                        'Options' => [],
                        'Properties' => [
                            [
                                'Name' => 'cost',
                                'NameID' => 81,
                                'Value' => NULL,
                                'ValueID' => NULL,
                                'DisplayType' => 'price',
                                'DisplayName' => 'cost',
                                'DisplayValue' => NULL,
                                'Visibility' => 'visible',
                                'TextLabel' => 'Cost',
                                'ImageURL' => NULL,
                                'Position' => 0
                            ]
                        ],
                        'Description' => 'Product Description'
                    ],
                'variants' => []
            ]
        ];
        $this->assertEquals($apiData, $expectedApiData);
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
}
