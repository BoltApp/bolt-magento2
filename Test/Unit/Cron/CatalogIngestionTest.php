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

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Api\Data\ProductEventInterface;
use Bolt\Boltpay\Api\ProductEventRepositoryInterface;
use Bolt\Boltpay\Cron\CatalogIngestion as CatalogIngestionCron;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Config as BoltConfig;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Model\CatalogIngestion\ProductEventManager;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use Bolt\Boltpay\Model\CatalogIngestion\ProductEventProcessor;

/**
 * Class CatalogIngestionTest
 * @coversDefaultClass \Bolt\Boltpay\Cron\CatalogIngestion
 */
class CatalogIngestionTest extends BoltTestCase
{
    private const CRON_MAX_ITEMS_OPTION = 3;

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
     * @var ProductEventManager
     */
    private $productEventManager;

    /**
     * @var CatalogIngestionCron
     */
    private $catalogIngestionCron;

    /**
     * @var ProductEventRepositoryInterface
     */
    private $productEventRepository;

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
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->productEventManager = $this->objectManager->get(ProductEventManager::class);
        $this->catalogIngestionCron = $this->objectManager->get(CatalogIngestionCron::class);
        $this->productEventRepository = $this->objectManager->get(ProductEventRepositoryInterface::class);
        $this->resource = $this->objectManager->get(ResourceConnection::class);
        $productEventProcessor = $this->objectManager->get(ProductEventProcessor::class);
        $featureSwitches = $this->createMock(Decider::class);
        TestHelper::setProperty($this->catalogIngestionCron, 'featureSwitches', $featureSwitches);
        TestHelper::setProperty($productEventProcessor, 'featureSwitches', $featureSwitches);
        $featureSwitches->method('isCatalogIngestionEnabled')->willReturn(true);
        $websiteId = $this->storeManager->getWebsite()->getId();
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_ENABLED,
                'value' => 1,
                'scope' => ScopeInterface::SCOPE_WEBSITES,
                'scopeId' => $websiteId,
            ],
            [
                'path'    => BoltConfig::XML_PATH_PUBLISHABLE_KEY_CHECKOUT,
                'value'   => 'publish_key',
                'scope'   => ScopeInterface::SCOPE_WEBSITES,
                'scopeId' => $websiteId,
            ],
            [
                'path'    => BoltConfig::XML_PATH_API_KEY,
                'value'   => 'api_key',
                'scope'   => ScopeInterface::SCOPE_WEBSITES,
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
    public function testExecute_sendCorrectRequestsCountEventsRemovedAfterSuccessCalls_withDefaultCronMaxItemsParam()
    {
        $apiHelper = $this->createPartialMock(ApiHelper::class, ['sendRequest']);
        TestHelper::setProperty($this->productEventManager, 'apiHelper', $apiHelper);
        $apiHelper->expects(self::exactly(2))->method('sendRequest')->willReturn(self::RESPONSE_SUCCESS_STATUS);
        $product1 = TestUtils::createSimpleProduct();
        $product2 = TestUtils::createSimpleProduct();
        $this->catalogIngestionCron->execute();
        $this->assertFalse($this->isProductEventExist($product1->getId()));
        $this->assertFalse($this->isProductEventExist($product2->getId()));
    }

    /**
     * @test
     */
    public function testExecute_sendCorrectRequestsCountEventsRemovedAfterSuccessCalls_withCronMaxItemsParam()
    {
        $configData = [
            [
                'path' => BoltConfig::XML_PATH_CATALOG_INGESTION_CRON_MAX_ITEMS,
                'value' => self::CRON_MAX_ITEMS_OPTION,
                'scope' => ScopeInterface::SCOPE_WEBSITES,
                'scopeId' => $this->storeManager->getWebsite()->getId(),
            ]
        ];
        $apiHelper = $this->createPartialMock(ApiHelper::class, ['sendRequest']);
        TestHelper::setProperty($this->productEventManager, 'apiHelper', $apiHelper);
        $apiHelper->expects(self::exactly(self::CRON_MAX_ITEMS_OPTION))->method('sendRequest')->willReturn(self::RESPONSE_SUCCESS_STATUS);
        TestUtils::setupBoltConfig($configData);
        $product1 = TestUtils::createSimpleProduct();
        $product2 = TestUtils::createSimpleProduct();
        $product3 = TestUtils::createSimpleProduct();
        $product4 = TestUtils::createSimpleProduct();
        $this->catalogIngestionCron->execute();
        $this->assertFalse($this->isProductEventExist($product1->getId()));
        $this->assertFalse($this->isProductEventExist($product2->getId()));
        $this->assertFalse($this->isProductEventExist($product3->getId()));
        $this->assertTrue($this->isProductEventExist($product4->getId()));
    }

    /**
     * @test
     */
    public function testExecute_sendCorrectRequestsCountEventsWasNotRemovedAfterFailCalls_withDefaultCronMaxItemsParam()
    {
        $apiHelper = $this->createPartialMock(ApiHelper::class, ['sendRequest']);
        TestHelper::setProperty($this->productEventManager, 'apiHelper', $apiHelper);
        $apiHelper->expects(self::exactly(1))->method('sendRequest')->willReturn(self::RESPONSE_FAIL_STATUS);
        $product1 = TestUtils::createSimpleProduct();
        $this->catalogIngestionCron->execute();
        $this->assertTrue($this->isProductEventExist($product1->getId()));
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
     * Checking if product event for product id is exist
     *
     * @param int $productId
     * @return bool
     */
    private function isProductEventExist(int $productId): bool
    {
        try {
            $this->productEventRepository->getByProductId($productId);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }
}
