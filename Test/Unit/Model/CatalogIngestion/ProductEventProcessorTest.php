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
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\Model\CatalogIngestion\ProductEventProcessor;
use Bolt\Boltpay\Model\CatalogIngestion\ProductEventManager;
use Bolt\Boltpay\Helper\Config as BoltConfig;
use Bolt\Boltpay\Api\Data\ProductEventInterface;
use Bolt\Boltpay\Plugin\Magento\Inventory\Model\SourceItem\Command\DecrementSourceItemQtyPlugin;
use Bolt\Boltpay\Plugin\Magento\Inventory\Model\SourceItem\Command\SourceItemSavePlugin;
use Bolt\Boltpay\Plugin\Magento\Inventory\Model\SourceItem\Command\SourceItemsDeletePlugin;
use Bolt\Boltpay\Plugin\Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventPlugin;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryApi\Api\Data\StockInterfaceFactory;
use Magento\Framework\Module\Manager;
use Magento\Framework\Api\DataObjectHelper;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\Data\SourceInterfaceFactory;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterface;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterfaceFactory;
use Magento\InventoryApi\Api\StockSourceLinksSaveInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use Magento\InventoryIndexer\Test\Integration\Indexer\RemoveIndexData;
use Magento\InventoryApi\Api\StockSourceLinksDeleteInterface;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class ProductEventManagerTest
 * @coversDefaultClass \Bolt\Boltpay\Model\CatalogIngestion\ProductEventProcessor
 * @magentoDbIsolation disabled
 */
class ProductEventProcessorTest extends BoltTestCase
{
    private const PRODUCT_SKU = 'ci_simple';

    private const PRODUCT_NAME = 'Catalog Ingestion Simple Product';

    private const PRODUCT_PRICE = 100;

    private const PRODUCT_QTY = 100;

    /**
     * @var array
     */
    private $stocksData = [
        [
            'stock_id' => 10,
            'name' => 'EU-stock',
        ]
    ];

    /**
     * @var array
     */
    private $sourcesData = [
        [
            'source_code' => 'eu-1',
            'name' => 'EU-source-1',
            'enabled' => true,
            'postcode' => 'postcode',
            'country_id' => 'FR',
        ],
        [
            'source_code' => 'eu-2',
            'name' => 'EU-source-2',
            'enabled' => true,
            'postcode' => 'postcode',
            'country_id' => 'FR',
        ]
    ];

    /**
     * @var array
     */
    private $linksData = [
        [
            'stock_id' => 10,
            'source_code' => 'eu-1',
            'priority' => 1,
        ],
        [
            'stock_id' => 10,
            'source_code' => 'eu-2',
            'priority' => 2,
        ]
    ];

    /**
     * @var array
     */
    private $sourcesItemsData = [
        [
            'source_code' => 'eu-1',
            'sku' => 'ci_simple',
            'quantity' => 50,
            'status' => 1,
        ],
        [
            'source_code' => 'eu-2',
            'sku' => 'ci_simple',
            'quantity' => 60,
            'status' => 1,
        ]
    ];

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductEventProcessor
     */
    private $productEventProcessor;

    /**
     * @var ProductEventManager
     */
    private $productEventManager;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductEventRepositoryInterface
     */
    private $productEventRepository;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var ApiHelper|MockObject
     */
    private $apiHelper;

    /**
     * @var Manager
     */
    private $moduleManger;

    /**
     * @var DataObjectHelper
     */
    private $dataObjectHelper;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @inheritDoc
     */
    protected function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->productEventProcessor = $this->objectManager->get(ProductEventProcessor::class);
        $this->productEventManager = $this->objectManager->get(ProductEventManager::class);
        $this->productRepository = $this->objectManager->create(ProductRepositoryInterface::class);
        $this->productEventRepository = $this->objectManager->get(ProductEventRepositoryInterface::class);
        $this->moduleManger = $this->objectManager->get(Manager::class);
        $this->dataObjectHelper = $this->objectManager->get(DataObjectHelper::class);
        $this->resource = $this->objectManager->get(ResourceConnection::class);
        $this->cartManagement = $this->objectManager->get(CartManagementInterface::class);
        $this->quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $featureSwitches = $this->createMock(Decider::class);
        TestHelper::setProperty($this->productEventProcessor, 'featureSwitches', $featureSwitches);
        $featureSwitches->method('isCatalogIngestionEnabled')->willReturn(true);
        $this->apiHelper = $this->createPartialMock(ApiHelper::class, ['sendRequest']);
        TestHelper::setProperty($this->productEventManager, 'apiHelper', $this->apiHelper);
        if ($this->moduleManger->isEnabled('Magento_InventoryCatalog')) {
            $decrementSourceItemQtyPlugin = $this->objectManager->get(DecrementSourceItemQtyPlugin::class);
            $sourceItemSavePlugin = $this->objectManager->get(SourceItemSavePlugin::class);
            $sourceItemsDeletePlugin = $this->objectManager->get(SourceItemsDeletePlugin::class);
            $placeReservationsForSalesEventPlugin = $this->objectManager->get(PlaceReservationsForSalesEventPlugin::class);
            TestHelper::setProperty($decrementSourceItemQtyPlugin, 'featureSwitches', $featureSwitches);
            TestHelper::setProperty($sourceItemSavePlugin, 'featureSwitches', $featureSwitches);
            TestHelper::setProperty($sourceItemsDeletePlugin, 'featureSwitches', $featureSwitches);
            TestHelper::setProperty($placeReservationsForSalesEventPlugin, 'featureSwitches', $featureSwitches);
        }
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
    public function testProcessProductEventUpdateByProduct_newProduct()
    {
        $product = $this->createProduct(self::PRODUCT_QTY);
        $productEvent = $this->productEventRepository->getByProductId($product->getId());
        $this->assertEquals($product->getId(), $productEvent->getProductId());
        $this->assertEquals($productEvent->getType(), ProductEventInterface::TYPE_CREATE);
    }

    /**
     * @test
     */
    public function testProcessProductEventUpdateByProduct_changedProductName()
    {
        $product = $this->createProduct(self::PRODUCT_QTY, true);
        $product->setName('Updated Product Name');
        $this->productRepository->save($product);
        $productEvent = $this->productEventRepository->getByProductId($product->getId());
        $this->assertEquals($product->getId(), $productEvent->getProductId());
        $this->assertEquals($productEvent->getType(), ProductEventInterface::TYPE_UPDATE);
    }

    /**
     * @test
     */
    public function testProcessProductEventUpdateByProduct_deleteProduct()
    {
        $product = $this->createProduct(self::PRODUCT_QTY, true);
        $this->productRepository->deleteById($product->getSku());
        $productEvent = $this->productEventRepository->getByProductId($product->getId());
        $this->assertEquals($product->getId(), $productEvent->getProductId());
        $this->assertEquals($productEvent->getType(), ProductEventInterface::TYPE_DELETE);
    }

    /**
     * @test
     */
    public function testProcessProductEventUpdateByProduct_noDataChanges()
    {
        $this->expectExceptionMessage("The bolt product event that was requested doesn't exist.");
        $product = $this->createProduct(self::PRODUCT_QTY, true);
        $this->productRepository->save($product);
        $this->productEventRepository->getByProductId($product->getId());
    }

    /**
     * @test
     */
    public function testProcessProductEventStockItemBased_updateStockItemQty()
    {
        $product = $this->createProduct(self::PRODUCT_QTY, true);
        $stockItem = $this->objectManager->create(StockItemInterface::class);
        $stockItem->setQty(13)
            ->setIsInStock(true);
        $extensionAttributes = $product->getExtensionAttributes();
        $extensionAttributes->setStockItem($stockItem);
        $this->productRepository->save($product);
        $productEvent = $this->productEventRepository->getByProductId($product->getId());
        $this->assertEquals($product->getId(), $productEvent->getProductId());
        $this->assertEquals($productEvent->getType(), ProductEventInterface::TYPE_UPDATE);
    }

    /**
     * @test
     */
    public function testProcessProductEventStockItemBased_updateStockItemStatus()
    {
        $this->setCatalogIngestionInstantUpdateConfig(0);
        $product = $this->createProduct(self::PRODUCT_QTY, true);
        $stockItem = $this->objectManager->create(StockItemInterface::class);
        $stockItem->setIsInStock(0);
        $extensionAttributes = $product->getExtensionAttributes();
        $extensionAttributes->setStockItem($stockItem);
        $this->productRepository->save($product);
        $productEvent = $this->productEventRepository->getByProductId($product->getId());
        $this->assertEquals($product->getId(), $productEvent->getProductId());
        $this->assertEquals($productEvent->getType(), ProductEventInterface::TYPE_UPDATE);
    }

    /**
     * @test
     */
    public function testProcessProductEventStockItemBased_updateStockItemStatusInstant()
    {
        $this->setCatalogIngestionInstantUpdateConfig(1);
        $this->apiHelper->method('sendRequest')->willReturn(true);
        $this->apiHelper->expects(self::once())->method('sendRequest');
        $product = $this->createProduct(self::PRODUCT_QTY, true);
        $stockItem = $this->objectManager->create(StockItemInterface::class);
        $stockItem->setIsInStock(0);
        $extensionAttributes = $product->getExtensionAttributes();
        $extensionAttributes->setStockItem($stockItem);
        $this->productRepository->save($product);
    }

    /**
     * @test
     */
    public function testProcessProductEventStockItemBased_notUpdateStockItem()
    {
        $this->expectExceptionMessage("The bolt product event that was requested doesn't exist.");
        $this->setCatalogIngestionInstantUpdateConfig(1);
        $product = $this->createProduct(self::PRODUCT_QTY, true);
        $stockItem = $this->objectManager->create(StockItemInterface::class);
        $stockItem->setIsInStock(1);
        $stockItem->setQty(self::PRODUCT_QTY);
        $extensionAttributes = $product->getExtensionAttributes();
        $extensionAttributes->setStockItem($stockItem);
        $this->productRepository->save($product);
        $this->productEventRepository->getByProductId($product->getId());
    }

    /**
     * @test
     */
    public function testProcessProductEventSourceItemsBased_updateSourceItemQty()
    {
        if ($this->moduleManger->isEnabled('Magento_InventoryCatalog')) {
            $product = $this->createProductWithSourceItems();
            $getSourceItemsBySku = $this->objectManager->get(GetSourceItemsBySkuInterface::class);
            $sourceItemsSave = $this->objectManager->get(SourceItemsSaveInterface::class);
            $sourceItems = $getSourceItemsBySku->execute($product->getSku());
            foreach ($sourceItems as $sourceItem) {
                $sourceItem->setQuantity(10);
                break;
            }
            $sourceItemsSave->execute($sourceItems);
            $productEvent = $this->productEventRepository->getByProductId($product->getId());
            $this->assertEquals($product->getId(), $productEvent->getProductId());
            $this->assertEquals($productEvent->getType(), ProductEventInterface::TYPE_UPDATE);
        }
    }

    /**
     * @test
     */
    public function testProcessProductEventSourceItemsBased_updateSourceItemStatus()
    {
        if ($this->moduleManger->isEnabled('Magento_InventoryCatalog')) {
            $this->setCatalogIngestionInstantUpdateConfig(1);
            $this->apiHelper->method('sendRequest')->willReturn(true);
            $this->apiHelper->expects(self::once())->method('sendRequest');
            $product = $this->createProductWithSourceItems();
            $getSourceItemsBySku = $this->objectManager->get(GetSourceItemsBySkuInterface::class);
            $sourceItemsSave = $this->objectManager->get(SourceItemsSaveInterface::class);
            $sourceItems = $getSourceItemsBySku->execute($product->getSku());
            foreach ($sourceItems as $sourceItem) {
                $sourceItem->setStatus(0);
                break;
            }
            $sourceItemsSave->execute($sourceItems);
        }
    }

    /**
     * @test
     */
    public function testProcessProductEvent_afterPlacingOrderUpdatedQty()
    {
        $product = $this->createProduct(self::PRODUCT_QTY, true);
        $quote = $this->createQuote();
        $quote->addProduct($product, 1);
        $quote->collectTotals();
        $this->quoteRepository->save($quote);
        $this->cartManagement->placeOrder($quote->getId());
        $productEvent = $this->productEventRepository->getByProductId($product->getId());
        $this->assertEquals($product->getId(), $productEvent->getProductId());
        $this->assertEquals($productEvent->getType(), ProductEventInterface::TYPE_UPDATE);
    }

    /**
     * @test
     */
    public function testProcessProductEvent_afterPlacingOrderUpdatedStatus()
    {
        $this->setCatalogIngestionInstantUpdateConfig(1);
        $this->apiHelper->method('sendRequest')->willReturn(true);
        $this->apiHelper->expects(self::once())->method('sendRequest');
        $product = $this->createProduct(1, true);
        $quote = $this->createQuote();
        $quote->addProduct($product, 1);
        $quote->collectTotals();
        $this->quoteRepository->save($quote);
        $this->cartManagement->placeOrder($quote->getId());
    }

    /**
     * Create quote
     *
     * @return Quote
     * @throws NoSuchEntityException
     */
    private function createQuote(): Quote
    {
        $quote = TestUtils::createQuote();

        $addressData = [
            'firstname' => 'John',
            'lastname' => 'McCombs',
            'street' => "4553 Annalee Way",
            'city' => 'Knoxville',
            'postcode' => '37921',
            'telephone' => '111111111',
            'country_id' => 'US',
            'region_id' => '56',
            'email'     => 'test@bolt.com'
        ];


        $billingAddress = $this->objectManager->create(QuoteAddress::class, ['data' => $addressData]);
        $billingAddress->setAddressType('billing');
        $shippingAddress = $this->objectManager->create(QuoteAddress::class, ['data' => $addressData]);
        $shippingAddress->setAddressType('shipping');

        $quote->setCustomerIsGuest(true)
            ->setStoreId($this->storeManager->getStore()->getId())
            ->setReservedOrderId('test01')
            ->setBillingAddress($billingAddress)
            ->setShippingAddress($shippingAddress);

        /** @var Rate $rate */
        $rate = $this->objectManager->create(Rate::class);
        $rate->setCode('freeshipping_freeshipping');
        $rate->getPrice(1);

        $quote->getShippingAddress()->setShippingMethod('freeshipping_freeshipping');
        $quote->getShippingAddress()->addShippingRate($rate);
        $quote->getPayment()->setMethod('checkmo');
        $quote->setCustomerEmail('test@bolt.com');
        $quote->collectTotals();
        $this->quoteRepository->save($quote);
        /** @var QuoteIdMask $quoteIdMask */
        $quoteIdMask = $this->objectManager
            ->create(QuoteIdMaskFactory::class)
            ->create();
        $quoteIdMask->setQuoteId($quote->getId());
        $quoteIdMask->setDataChanges(true);
        $quoteIdMask->save();
        return $quote;
    }

    /**
     * Create product with catalog inventory source items
     *
     * @return ProductInterface|null
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws StateException
     * @throws ValidationException
     */
    private function createProductWithSourceItems(): ?ProductInterface
    {
        if ($this->moduleManger->isEnabled('Magento_InventoryCatalog')) {
            $stockRepository = $this->objectManager->get(StockRepositoryInterface::class);
            $stockFactory = $this->objectManager->get(StockInterfaceFactory::class);
            $sourceFactory = $this->objectManager->get(SourceInterfaceFactory::class);
            $sourceRepository = $this->objectManager->get(SourceRepositoryInterface::class);
            $stockSourceLinkFactory = $this->objectManager->get(StockSourceLinkInterfaceFactory::class);
            $stockSourceLinksSave = $this->objectManager->get(StockSourceLinksSaveInterface::class);
            $sourceItemFactory = $this->objectManager->get(SourceItemInterfaceFactory::class);
            $sourceItemsSave = $this->objectManager->get(SourceItemsSaveInterface::class);
            $salesChannelFactory = $this->objectManager->get(SalesChannelInterfaceFactory::class);
            $indexerRegistry = $this->objectManager->get(IndexerRegistry::class);

            $product = $this->createProduct(self::PRODUCT_QTY, true);
            foreach ($this->sourcesData as $sourceData) {
                /** @var SourceInterface $source */
                $source = $sourceFactory->create();
                $this->dataObjectHelper->populateWithArray($source, $sourceData, SourceInterface::class);
                $sourceRepository->save($source);
            }

            foreach ($this->stocksData as $stockData) {
                /** @var StockInterface $stock */
                $stock = $stockFactory->create();
                $this->dataObjectHelper->populateWithArray($stock, $stockData, StockInterface::class);
                $stockRepository->save($stock);
            }

            $links = [];
            foreach ($this->linksData as $linkData) {
                /** @var StockSourceLinkInterface $link */
                $link = $stockSourceLinkFactory->create();
                $this->dataObjectHelper->populateWithArray($link, $linkData, StockSourceLinkInterface::class);
                $links[] = $link;
            }
            $stockSourceLinksSave->execute($links);

            $sourceItems = [];
            foreach ($this->sourcesItemsData as $sourceItemData) {
                /** @var SourceItemInterface $source */
                $sourceItem = $sourceItemFactory->create();
                $this->dataObjectHelper->populateWithArray($sourceItem, $sourceItemData, SourceItemInterface::class);
                $sourceItems[] = $sourceItem;
            }
            $sourceItemsSave->execute($sourceItems);

            $stock = $stockRepository->get(10);
            $extensionAttributes = $stock->getExtensionAttributes();
            $salesChannels = $extensionAttributes->getSalesChannels();

            //assign stock to website
            /** @var SalesChannelInterface $salesChannel */
            $salesChannel = $salesChannelFactory->create();
            $salesChannel->setCode($this->storeManager->getWebsite()->getCode());
            $salesChannel->setType(SalesChannelInterface::TYPE_WEBSITE);
            $salesChannels[] = $salesChannel;

            $extensionAttributes->setSalesChannels($salesChannels);
            $stockRepository->save($stock);

            $indexer = $indexerRegistry->get(InventoryIndexer::INDEXER_ID);
            $indexer->reindexAll();

            return $product;
        }
    }

    /**
     * Create simple product
     *
     * @param bool $preventCatalogProductEventCreation
     * @return ProductInterface
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws StateException
     */
    private function createProduct(int $qty, bool $preventCatalogProductEventCreation = false): ProductInterface
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
                    'qty' => $qty,
                    'is_in_stock' => 1,
                    'manage_stock' => 1,
                ]
            )
            ->setTaxClassId(0)
            ->setCanSaveCustomOptions(true)
            ->setHasOptions(true)
            ->setUrlKey('test-simple-product-'.round(microtime(true) * 1000));
        $product = $this->productRepository->save($product);
        if ($preventCatalogProductEventCreation) {
            $this->productEventRepository->deleteByProductId($product->getId());
        }
        return $product;
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
        $connection->delete($connection->getTableName('quote'));
        $connection->delete($connection->getTableName('sales_order'));

        if ($this->moduleManger->isEnabled('Magento_InventoryCatalog')) {
            $stockRepository = $this->objectManager->get(StockRepositoryInterface::class);
            $stockSourceLinkFactory = $this->objectManager->get(StockSourceLinkInterfaceFactory::class);
            $salesChannelFactory = $this->objectManager->get(SalesChannelInterfaceFactory::class);
            $removeIndexData = $this->objectManager->get(RemoveIndexData::class);
            $stockSourceLinksDelete = $this->objectManager->get(StockSourceLinksDeleteInterface::class);
            //sources
            $connection->delete(
                $connection->getTableName('inventory_source'),
                [
                    SourceInterface::SOURCE_CODE . ' IN (?)' => ['eu-1', 'eu-2'],
                ]
            );
            //stock
            try {
                $stockId = 10;
                $stock = $stockRepository->get($stockId);
                $extensionAttributes = $stock->getExtensionAttributes();
                $extensionAttributes->setSalesChannels([]);
                $stockRepository->save($stock);
                //Delete stock.
                $stockRepository->deleteById($stockId);

                //links
                $links = [];
                $linksData = [
                    10 => ['eu-1', 'eu-2'],
                ];
                foreach ($linksData as $stockID => $sourceCodes) {
                    foreach ($sourceCodes as $sourceCode) {
                        /** @var StockSourceLinkInterface $link */
                        $link = $stockSourceLinkFactory->create();

                        $link->setStockId($stockID);
                        $link->setSourceCode($sourceCode);

                        $links[] = $link;
                    }
                }

                $defaultStock = $stockRepository->get(1);
                $salesChannel = $salesChannelFactory->create();
                $salesChannel->setCode($this->storeManager->getWebsite()->getCode());
                $salesChannel->setType(SalesChannelInterface::TYPE_WEBSITE);
                $salesChannels[] = $salesChannel;
                $extensionAttributes->setSalesChannels($salesChannels);
                $stockRepository->save($defaultStock);
                $stockSourceLinksDelete->execute($links);
                $removeIndexData->execute([$stockId]);
            } catch (\Exception $e) {
                //Stock already removed
            }
            $connection->delete($connection->getTableName('inventory_reservation'));
        }
    }

    /**
     * Enable/Disable instant catalog ingestion update config value
     *
     * @param int $value
     * @return void
     * @throws LocalizedException
     */
    private function setCatalogIngestionInstantUpdateConfig(int $value): void
    {
        $websiteId = $this->storeManager->getWebsite()->getId();
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_INSTANT_ENABLED,
                'value' => $value,
                'scope' => ScopeInterface::SCOPE_WEBSITES,
                'scopeId' => $websiteId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
    }
}
