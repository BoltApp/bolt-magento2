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
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Config\Model\ResourceModel\Config as ResourceConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\Manager;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterfaceFactory;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Model\Config;
use Magento\Eav\Setup\EavSetup;
use Magento\Catalog\Setup\CategorySetup;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory;
use Vertex\Tax\Model\ModuleManager;

/**
 * Class ProductEventRequestBuilderTest
 * @coversDefaultClass \Bolt\Boltpay\Model\CatalogIngestion\ProductEventRequestBuilder
 */
class ProductEventRequestBuilderTest extends BoltTestCase
{
    private const PRODUCT_SKU = 'ci_simple';

    private const PRODUCT_SKU_CONFIGURABLE = 'ci_configurable';

    private const PRODUCT_NAME = 'Catalog Ingestion Simple Product';

    private const PRODUCT_NAME_CONFIGURABLE = 'Catalog Ingestion Configurable Product';

    private const PRODUCT_PRICE = 100;

    private const PRODUCT_QTY = 100;

    private const API_KEY = '3c2d5104e7f9d99b66e1c9c550f6566677bf81de0d6f25e121fdb57e47c2eafc';

    private const PUBLISH_KEY = 'ifssM6pxV64H.FXY3JhSL7w9f.c243fecf459ed259019ea58d7a30307edf2f65442c305f086105b2f66fe6c006';

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
     * @var Manager
     */
    private $moduleManger;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductAttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var Config
     */
    private $eavConfig;

    /**
     * @var int
     */
    private $configurableAttributeId;

    /**
     * @var array
     */
    private $confProductAttributes = [];

    /**
     * @var array
     */
    private $childProductSkuPostfix = [10, 20];

    /**
     * @var array
     */
    private $associatedProductIds = [];

    /**
     * @var array
     */
    private $associatedProducts = [];

    /**
     * @var ProductInterface|null
     */
    private $bundleChildProduct;

    /**
     * @var ProductInterface|null
     */
    private $groupedChildProduct;

    /**
     * @inheritDoc
     */
    protected function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->productEventRequestBuilder =$this->objectManager->get(ProductEventRequestBuilder::class);
        $this->resource = $this->objectManager->get(ResourceConnection::class);
        $this->moduleManger = $this->objectManager->get(ModuleManager::class);
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->productEventRepository = $this->objectManager->get(ProductEventRepositoryInterface::class);
        $productEventProcessor = $this->objectManager->get(ProductEventProcessor::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->attributeRepository = $this->objectManager->get(ProductAttributeRepositoryInterface::class);
        $this->eavConfig = $this->objectManager->get(Config::class);
        $featureSwitches = $this->createMock(Decider::class);
        $featureSwitches->method('isCatalogIngestionEnabled')->willReturn(true);
        TestHelper::setProperty($productEventProcessor, 'featureSwitches', $featureSwitches);
        $websiteId = $this->storeManager->getWebsite()->getId();
        $encryptor = $this->objectManager->get(EncryptorInterface::class);
        $apikey = $encryptor->encrypt(self::API_KEY);
        $publishKey = $encryptor->encrypt(self::PUBLISH_KEY);
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_ENABLED,
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
        $fulfillmentCenterID = ($this->moduleManger->isEnabled('Magento_InventoryCatalog')) ?
            'Default Stock' : 'default';
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
                        'ManageInventory' => true,
                        'Visibility' => 'visible',
                        'Backorder' => 'no',
                        'Availability' => 'in_stock',
                        'ShippingRequired' => true,
                        'Prices' => [
                            [
                                'ListPrice' => 10000,
                                'SalePrice' => 10000,
                                'Currency' => 'USD',
                                'Locale' => 'en_US',
                                'Unit' => ''
                            ]
                        ],
                        'Inventories' => [
                            [
                                'FulfillmentCenterID' => $fulfillmentCenterID,
                                'InventoryLevel' => self::PRODUCT_QTY
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
     * @test
     */
    public function testGetRequest_WithConfigurableProduct()
    {
        $product = $this->createConfigurableProduct();
        $productEvent = $this->productEventRepository->getByProductId($product->getId());
        $request = $this->productEventRequestBuilder->getRequest($productEvent, $this->storeManager->getWebsite()->getId());
        $apiData = $request->getApiData();
        $fulfillmentCenterID = ($this->moduleManger->isEnabled('Magento_InventoryCatalog')) ?
            'Default Stock' : 'default';
        $expectedApiData = [
            'operation' => $productEvent->getType(),
            'timestamp' => strtotime($productEvent->getCreatedAt()),
            'product' => [
                'product' =>
                    [
                        'MerchantProductID' => $product->getId(),
                        'ProductType' => 'configurable',
                        'SKU' => self::PRODUCT_SKU_CONFIGURABLE,
                        'URL' => $product->getProductUrl(),
                        'Name' => self::PRODUCT_NAME_CONFIGURABLE,
                        'ManageInventory' => true,
                        'Visibility' => 'visible',
                        'Backorder' => 'no',
                        'Availability' => 'in_stock',
                        'ShippingRequired' => true,
                        'Prices' => [
                            [
                                'ListPrice' => 10000,
                                'SalePrice' => 10000,
                                'Currency' => 'USD',
                                'Locale' => 'en_US',
                                'Unit' => ''
                            ]
                        ],
                        'Inventories' => [
                            [
                                'FulfillmentCenterID' => 'default',
                                'InventoryLevel' => 0
                            ]
                        ],
                        'Media' => [],
                        'Properties' => [
                            [
                                'Name' => 'test_configurable',
                                'NameID' => $this->configurableAttributeId,
                                'Value' => NULL,
                                'ValueID' => NULL,
                                'DisplayType' => 'select',
                                'DisplayName' => 'test_configurable',
                                'DisplayValue' => '',
                                'Visibility' => 'visible',
                                'TextLabel' => 'Test Configurable',
                                'ImageURL' => NULL,
                                'Position' => 0
                            ],
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
        foreach ($this->associatedProducts as $index => $associatedProduct) {
            $expectedApiData['product']['variants'][] = [
                'MerchantProductID' => $associatedProduct->getId(),
                'ProductType' => 'simple',
                'SKU' => self::PRODUCT_SKU . '_' . $this->childProductSkuPostfix[$index],
                'URL' => $associatedProduct->getProductUrl(),
                'Name' => self::PRODUCT_NAME,
                'ManageInventory' => true,
                'Visibility' => 'not_visible',
                'Backorder' => 'no',
                'Availability' => 'in_stock',
                'ShippingRequired' => true,
                'Prices' => [
                    [
                        'ListPrice' => 10000,
                        'SalePrice' => 10000,
                        'Currency' => 'USD',
                        'Locale' => 'en_US',
                        'Unit' => ''
                    ]
                ],
                'Inventories' => [
                    [
                        'FulfillmentCenterID' => $fulfillmentCenterID,
                        'InventoryLevel' => self::PRODUCT_QTY
                    ]
                ],
                'Media' => [],
                'Options' => [],
                'Properties' => [
                    [
                        'Name' => 'test_configurable',
                        'NameID' => $this->configurableAttributeId,
                        'DisplayType' => 'select',
                        'DisplayName' => 'test_configurable',
                        'Visibility' => 'visible',
                        'TextLabel' => 'Test Configurable',
                        'ImageURL' => NULL,
                        'Position' => 0
                    ],
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
                'Description' => 'Product Description',
                'ParentProductIDs' => [(string)$product->getId()]
            ];
            unset($apiData['product']['variants'][$index]['Properties'][0]['Value']);
            unset($apiData['product']['variants'][$index]['Properties'][0]['ValueID']);
            unset($apiData['product']['variants'][$index]['Properties'][0]['DisplayValue']);
        }
        foreach ($product->getExtensionAttributes()->getConfigurableProductOptions() as $confOption) {
            $optionData = [
                'Name' => $confOption->getProductAttribute()->getAttributeCode(),
                'DisplayType' => 'select',
                'DisplayName' => $confOption->getLabel(),
                'Values' => [],
                'Visibility' => 'true',
                'SortOrder' => (int)$confOption->getPosition(),
                'MagentoOptionType' => 'ConfigurableProductOption'
            ];

            if ($options = $confOption->getOptions()) {
                foreach ($options as $position => $option) {
                    if (!isset($option['value_index']) || !isset($option['store_label'])) {
                        continue;
                    }
                    $optionData['Values'][] = [
                        'Value' => $option['value_index'],
                        'DisplayValue' => $option['store_label'],
                        'SortOrder' => (int)$position
                    ];
                }
            }
            $expectedApiData['product']['product']['Options'][] = $optionData;
        }
        $this->assertEquals($apiData, $expectedApiData);
    }

    /**
     * @test
     */
    public function testGetRequest_WithBundleProduct()
    {
        $product = $this->createBundleProduct();
        $productEvent = $this->productEventRepository->getByProductId($product->getId());
        $request = $this->productEventRequestBuilder->getRequest($productEvent, $this->storeManager->getWebsite()->getId());
        $apiData = $request->getApiData();
        $fulfillmentCenterID = ($this->moduleManger->isEnabled('Magento_InventoryCatalog')) ?
            'Default Stock' : 'default';
        $expectedApiData = [
            'operation' => $productEvent->getType(),
            'timestamp' => strtotime($productEvent->getCreatedAt()),
            'product' => [
                'product' =>
                    [
                        'MerchantProductID' => $product->getId(),
                        'ProductType' => ProductType::TYPE_BUNDLE,
                        'SKU' => 'bundle-product',
                        'URL' => $product->getProductUrl(),
                        'Name' => 'Bundle Product',
                        'ManageInventory' => true,
                        'Visibility' => 'visible',
                        'Backorder' => 'no',
                        'Availability' => 'in_stock',
                        'ShippingRequired' => true,
                        'Prices' => [
                            [
                                'ListPrice' => 1275,
                                'SalePrice' => 1275,
                                'Currency' => 'USD',
                                'Locale' => 'en_US',
                                'Unit' => ''
                            ]
                        ],
                        'Inventories' => [
                            [
                                'FulfillmentCenterID' => 'default',
                                'InventoryLevel' => 0
                            ]
                        ],
                        'Media' => [],
                        'Options' => [
                            [
                                'Name' => 'Bundle Product Items',
                                'DisplayType' => 'select',
                                'DisplayName' => 'Bundle Product Items',
                                'BundleValues' => [
                                    [
                                        'MerchantProductID' => $this->bundleChildProduct->getId(),
                                        'SKU' => $this->bundleChildProduct->getSku(),
                                        'SortOrder' => 0,
                                        'Qty' => 1,
                                        'SelectionCanChangeQuantity' => true,
                                    ]
                                ],
                                'Visibility' => 'true',
                                'SortOrder' => 0,
                                'IsRequired' => true,
                                'MagentoOptionType' => 'BundleProductOption'
                            ]
                        ],
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
                    ],
                'variants' => [
                    [
                        'MerchantProductID' => $this->bundleChildProduct->getId(),
                        'ProductType' => 'simple',
                        'SKU' => 'ci_simple',
                        'URL' => $this->bundleChildProduct->getProductUrl(),
                        'Name' => 'Catalog Ingestion Simple Product',
                        'ManageInventory' => true,
                        'Visibility' => 'visible',
                        'Backorder' => 'no',
                        'Availability' => 'in_stock',
                        'ShippingRequired' => true,
                        'Prices' => [
                            [
                                'ListPrice' => 10000,
                                'SalePrice' => 10000,
                                'Currency' => 'USD',
                                'Locale' => 'en_US',
                                'Unit' => ''
                            ]
                        ],
                        'Inventories' => [
                            [
                                'FulfillmentCenterID' => $fulfillmentCenterID,
                                'InventoryLevel' => self::PRODUCT_QTY
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
                        'Description' => 'Product Description',
                        'ParentProductIDs' => [(string)$product->getId()]
                    ]
                ]
            ]
        ];
        $this->assertEquals($apiData, $expectedApiData);
    }

    /**
     * @test
     */
    public function testGetRequest_WithGroupedProduct()
    {
        $product = $this->createGroupedProduct();
        $productEvent = $this->productEventRepository->getByProductId($product->getId());
        $request = $this->productEventRequestBuilder->getRequest($productEvent, $this->storeManager->getWebsite()->getId());
        $apiData = $request->getApiData();
        $fulfillmentCenterID = ($this->moduleManger->isEnabled('Magento_InventoryCatalog')) ?
            'Default Stock' : 'default';
        $expectedApiData = [
            'operation' => $productEvent->getType(),
            'timestamp' => strtotime($productEvent->getCreatedAt()),
            'product' => [
                'product' =>
                    [
                        'MerchantProductID' => $product->getId(),
                        'ProductType' => Grouped::TYPE_CODE,
                        'SKU' => 'grouped-product',
                        'URL' => $product->getProductUrl(),
                        'Name' => 'Grouped Product',
                        'ManageInventory' => true,
                        'Visibility' => 'visible',
                        'Backorder' => 'no',
                        'Availability' => 'in_stock',
                        'ShippingRequired' => true,
                        'Prices' => [
                            [
                                'ListPrice' => 10000,
                                'SalePrice' => 10000,
                                'Currency' => 'USD',
                                'Locale' => 'en_US',
                                'Unit' => ''
                            ]
                        ],
                        'Inventories' => [
                            [
                                'FulfillmentCenterID' => 'default',
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
                    ],
                'variants' => [
                    [
                        'MerchantProductID' => $this->groupedChildProduct->getId(),
                        'ProductType' => 'simple',
                        'SKU' => 'ci_simple',
                        'URL' => $this->groupedChildProduct->getProductUrl(),
                        'Name' => 'Catalog Ingestion Simple Product',
                        'ManageInventory' => true,
                        'Visibility' => 'visible',
                        'Backorder' => 'no',
                        'Availability' => 'in_stock',
                        'ShippingRequired' => true,
                        'Prices' => [
                            [
                                'ListPrice' => 10000,
                                'SalePrice' => 10000,
                                'Currency' => 'USD',
                                'Locale' => 'en_US',
                                'Unit' => ''
                            ]
                        ],
                        'Inventories' => [
                            [
                                'FulfillmentCenterID' => $fulfillmentCenterID,
                                'InventoryLevel' => self::PRODUCT_QTY
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
                        'Description' => 'Product Description',
                        'ParentProductIDs' => [(string)$product->getId()]
                    ]
                ]
            ]
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

        $product = $this->createProduct();
        $productEvent = $this->productEventRepository->getByProductId($product->getId());
        $this->productEventRequestBuilder->getRequest($productEvent, $this->storeManager->getWebsite()->getId());
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
        $configResource->deleteConfig(BoltConfig::XML_PATH_CATALOG_INGESTION_ENABLED, ScopeInterface::SCOPE_WEBSITES, $websiteId);
        $configResource->deleteConfig(BoltConfig::XML_PATH_PUBLISHABLE_KEY_CHECKOUT, ScopeInterface::SCOPE_STORES, $websiteId);
        $configResource->deleteConfig(BoltConfig::XML_PATH_API_KEY, ScopeInterface::SCOPE_STORES, $websiteId);
        $connection = $this->resource->getConnection('default');
        $connection->truncateTable($this->resource->getTableName('bolt_product_event'));
        if (!empty($this->confProductAttributes)) {
            foreach ($this->confProductAttributes as $attribute) {
                try {
                    $this->attributeRepository->delete($attribute);
                } catch (\Exception $e) {
                    //do nothing, attribute already removed
                }
            }
        }
        $connection->delete($connection->getTableName('catalog_product_entity'));
        $connection->delete($connection->getTableName('url_rewrite'), ['entity_type = ?' => 'product']);
        $connection->delete($connection->getTableName('eav_attribute'), ['attribute_code = ?' => 'test_configurable']);
        $this->eavConfig->clear();
        $this->productRepository->cleanCache();
    }

    /**
     * Create simple product
     *
     * @return ProductInterface
     */
    private function createProduct(): ProductInterface
    {
        $product = Bootstrap::getObjectManager()->create(Product::class);
        $product->setTypeId(ProductType::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setName(self::PRODUCT_NAME)
            ->setSku(self::PRODUCT_SKU)
            ->setPrice(self::PRODUCT_PRICE)
            ->setDescription('Product Description')
            ->setMetaTitle('meta title')
            ->setMetaKeyword('meta keyword')
            ->setMetaDescription('meta description')
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setCategoryIds([2])
            ->setStoreId($this->storeManager->getStore()->getId())
            ->setWebsiteIds([$this->storeManager->getStore()->getWebsiteId()])
            ->setStockData(
                [
                    'qty' => self::PRODUCT_QTY,
                    'is_in_stock' => 1,
                    'manage_stock' => 1,
                ]
            )
            ->setTaxClassId(0)
            ->setCanSaveCustomOptions(true)
            ->setHasOptions(true)
            ->setUrlKey('test-simple-product-'.round(microtime(true) * 1000));
        return $this->productRepository->save($product);
    }

    /**
     * Create configurable product
     *
     * @return ProductInterface
     */
    private function createConfigurableProduct(): ProductInterface
    {
        $this->createConfigurableProductAttribute();
        $installer = $this->objectManager->create(CategorySetup::class);
        $eavConfig = $this->objectManager->get(Config::class);
        $attribute = $eavConfig->getAttribute(Product::ENTITY, 'test_configurable');
        /* Create simple products per each option value*/
        /** @var AttributeOptionInterface[] $options */
        $options = $attribute->getOptions();
        $attributeSetId = $installer->getAttributeSetId(Product::ENTITY, 'Default');
        $attributeValues = [];
        $this->associatedProductIds = [];
        $this->associatedProducts = [];
        foreach ($options as $index => $option) {
            if (!$option->getValue()) {
                continue;
            }
            $product = Bootstrap::getObjectManager()->create(Product::class);
            $product->setTypeId(ProductType::TYPE_SIMPLE)
                ->setAttributeSetId($attributeSetId)
                ->setName(self::PRODUCT_NAME)
                ->setSku(self::PRODUCT_SKU . '_' . $this->childProductSkuPostfix[$index - 1])
                ->setPrice(self::PRODUCT_PRICE)
                ->setDescription('Product Description')
                ->setMetaTitle('meta title')
                ->setMetaKeyword('meta keyword')
                ->setMetaDescription('meta description')
                ->setTestConfigurable($option->getValue())
                ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE)
                ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
                ->setCategoryIds([2])
                ->setStoreId($this->storeManager->getStore()->getId())
                ->setWebsiteIds([$this->storeManager->getStore()->getWebsiteId()])
                ->setStockData(
                    [
                        'qty' => self::PRODUCT_QTY,
                        'is_in_stock' => 1,
                        'manage_stock' => 1,
                    ]
                )
                ->setTaxClassId(0)
                ->setCanSaveCustomOptions(true)
                ->setHasOptions(true)
                ->setUrlKey('test-simple-product-'.round(microtime(true) * 1000));
            $product = $this->productRepository->save($product);
            $attributeValues[] = [
                'label' => 'test',
                'attribute_id' => $attribute->getId(),
                'value_index' => $option->getValue(),
            ];
            $this->associatedProductIds[] = $product->getId();
            $this->associatedProducts[] = $product;
        }

        $configurableProduct = Bootstrap::getObjectManager()->create(Product::class);

        /** @var Factory $optionsFactory */
        $optionsFactory = Bootstrap::getObjectManager()->create(Factory::class);

        $configurableAttributesData = [
            [
                'attribute_id' => $attribute->getId(),
                'code' => $attribute->getAttributeCode(),
                'label' => $attribute->getStoreLabel(),
                'position' => '0',
                'values' => $attributeValues,
            ],
        ];

        $configurableOptions = $optionsFactory->create($configurableAttributesData);

        $configurableProduct->setTypeId(Configurable::TYPE_CODE)
            ->setAttributeSetId(4)
            ->setName(self::PRODUCT_NAME_CONFIGURABLE)
            ->setSku(self::PRODUCT_SKU_CONFIGURABLE)
            ->setPrice(self::PRODUCT_PRICE)
            ->setDescription('Product Description')
            ->setMetaTitle('meta title')
            ->setMetaKeyword('meta keyword')
            ->setMetaDescription('meta description')
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setCategoryIds([2])
            ->setStoreId($this->storeManager->getStore()->getId())
            ->setWebsiteIds([$this->storeManager->getStore()->getWebsiteId()])
            ->setStockData(['use_config_manage_stock' => 1, 'is_in_stock' => 1])
            ->setTaxClassId(0)
            ->setCanSaveCustomOptions(true)
            ->setHasOptions(true)
            ->setUrlKey('test-configurable-product-'.round(microtime(true) * 1000));

        $extensionConfigurableAttributes = $configurableProduct->getExtensionAttributes();
        $extensionConfigurableAttributes->setConfigurableProductOptions($configurableOptions);
        $extensionConfigurableAttributes->setConfigurableProductLinks($this->associatedProductIds);
        $configurableProduct->setExtensionAttributes($extensionConfigurableAttributes);
        return $this->productRepository->save($configurableProduct);
    }

    /**
     * Create bundle product
     *
     * @return ProductInterface
     */
    private function createBundleProduct(): ProductInterface
    {
        $this->bundleChildProduct = $this->createProduct();
        $bundleProduct = $this->objectManager->create(Product::class);
        $bundleProduct->setTypeId(ProductType::TYPE_BUNDLE)
            ->setId(3)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Bundle Product')
            ->setSku('bundle-product')
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_qty_decimal' => 0, 'is_in_stock' => 1])
            ->setPriceView(1)
            ->setSkuType(1)
            ->setPriceType(1)
            ->setShipmentType(0)
            ->setPrice(10.0)
            ->setBundleOptionsData(
                [
                    [
                        'title' => 'Bundle Product Items',
                        'default_title' => 'Bundle Product Items',
                        'type' => 'select', 'required' => 1,
                        'delete' => '',
                    ],
                ]
            )
            ->setBundleSelectionsData(
                [
                    [
                        [
                            'product_id' => $this->bundleChildProduct->getId(),
                            'selection_price_value' => 2.75,
                            'selection_qty' => 1,
                            'selection_can_change_qty' => 1,
                            'delete' => '',

                        ],
                    ],
                ]
            );

        if ($bundleProduct->getBundleOptionsData()) {
            $options = [];
            foreach ($bundleProduct->getBundleOptionsData() as $key => $optionData) {
                if (!(bool)$optionData['delete']) {
                    $option = $this->objectManager->create(\Magento\Bundle\Api\Data\OptionInterfaceFactory::class)
                        ->create(['data' => $optionData]);
                    $option->setSku($bundleProduct->getSku());
                    $option->setOptionId(null);

                    $links = [];
                    $bundleLinks = $bundleProduct->getBundleSelectionsData();
                    if (!empty($bundleLinks[$key])) {
                        foreach ($bundleLinks[$key] as $linkData) {
                            if (!(bool)$linkData['delete']) {
                                /** @var \Magento\Bundle\Api\Data\LinkInterface$link */
                                $link = $this->objectManager->create(\Magento\Bundle\Api\Data\LinkInterfaceFactory::class)
                                    ->create(['data' => $linkData]);
                                $linkProduct = $this->productRepository->getById($linkData['product_id']);
                                $link->setSku($linkProduct->getSku());
                                $link->setQty($linkData['selection_qty']);
                                $link->setPrice($linkData['selection_price_value']);
                                if (isset($linkData['selection_can_change_qty'])) {
                                    $link->setCanChangeQuantity($linkData['selection_can_change_qty']);
                                }
                                $links[] = $link;
                            }
                        }
                        $option->setProductLinks($links);
                        $options[] = $option;
                    }
                }
            }
            $extension = $bundleProduct->getExtensionAttributes();
            $extension->setBundleProductOptions($options);
            $bundleProduct->setExtensionAttributes($extension);
        }
        return $this->productRepository->save($bundleProduct, true);
    }

    /**
     * Create grouped product
     *
     * @return ProductInterface
     */
    private function createGroupedProduct(): ProductInterface
    {
        $this->groupedChildProduct = $this->createProduct();
        $groupedProduct = $this->objectManager->create(Product::class);
        $groupedProduct->setTypeId(Grouped::TYPE_CODE)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Grouped Product')
            ->setSku('grouped-product')
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_qty_decimal' => 0, 'is_in_stock' => 1])
            ->setPrice(10.0);

        $productLinkFactory = $this->objectManager->get(\Magento\Catalog\Api\Data\ProductLinkInterfaceFactory::class);
        $productLink = $productLinkFactory->create();
        $productLink->setSku($groupedProduct->getSku())
            ->setLinkType('associated')
            ->setLinkedProductSku($this->groupedChildProduct->getSku())
            ->setLinkedProductType($this->groupedChildProduct->getTypeId())
            ->setPosition(1)
            ->getExtensionAttributes()
            ->setQty(1);
        $groupedProduct->setProductLinks([$productLink]);
        return $this->productRepository->save($groupedProduct, true);
    }

    /**
     * Create required configurable product attribute with options
     *
     * @return void
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\StateException
     */
    private function createConfigurableProductAttribute()
    {
        /** @var ProductAttributeInterfaceFactory $attributeFactory */
        $attributeFactory = $this->objectManager->get(ProductAttributeInterfaceFactory::class);

        /** @var $installer EavSetup */
        $installer = $this->objectManager->get(EavSetup::class);
        $attributeSetId = $installer->getAttributeSetId(Product::ENTITY, 'Default');
        $groupId = $installer->getDefaultAttributeGroupId(Product::ENTITY, $attributeSetId);
        /** @var ProductAttributeInterface $attributeModel */
        $attributeModel = $attributeFactory->create();
        $attributeModel->setData(
            [
                'attribute_code' => 'test_configurable',
                'entity_type_id' => $installer->getEntityTypeId(Product::ENTITY),
                'is_global' => 1,
                'is_user_defined' => 1,
                'frontend_input' => 'select',
                'is_unique' => 0,
                'is_required' => 0,
                'is_searchable' => 0,
                'is_visible_in_advanced_search' => 0,
                'is_comparable' => 0,
                'is_filterable' => 0,
                'is_filterable_in_search' => 0,
                'is_used_for_promo_rules' => 0,
                'is_html_allowed_on_front' => 1,
                'is_visible_on_front' => 0,
                'used_in_product_listing' => 0,
                'used_for_sort_by' => 0,
                'frontend_label' => ['Test Configurable'],
                'backend_type' => 'int',
                'option' => [
                    'value' => ['option_0' => ['Option 1'], 'option_1' => ['Option 2']],
                    'order' => ['option_0' => 1, 'option_1' => 2],
                ],
            ]
        );

        $attribute = $this->attributeRepository->save($attributeModel);
        $this->confProductAttributes[] = $attribute;
        $this->configurableAttributeId = $attribute->getAttributeId();

        $installer->addAttributeToGroup(Product::ENTITY, $attributeSetId, $groupId, $attribute->getId());
        $this->eavConfig->clear();
    }
}
