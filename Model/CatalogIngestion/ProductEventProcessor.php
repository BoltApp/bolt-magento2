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

namespace Bolt\Boltpay\Model\CatalogIngestion;

use Bolt\Boltpay\Api\Data\ProductEventInterface;
use Bolt\Boltpay\Api\ProductEventManagerInterface;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Logger\Logger;
use Bolt\Boltpay\Model\Config\Source\Catalog\Ingestion\Events;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Inventory\Model\SourceItem;
use Magento\Catalog\Model\ResourceModel\Product\Website\Link as ProductWebsiteLink;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Helper\Image;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\InventorySalesApi\Api\IsProductSalableInterface;;
use Magento\InventorySalesApi\Api\StockResolverInterface;

/**
 * Product Event Processor
 */
class ProductEventProcessor
{
    private const REMOVED_IMAGES_KEY = 'removed';

    private const CUSTOM_OPTIONS_KEY = 'options';

    private const MEDIA_GALLERY_ATTR = 'media_gallery';

    /**
     * Disabled attributes for triggering sync.
     * @var []
     */
    private $disabledAttrCodes = [
        'has_options',
        'required_options',
        'media_gallery',
        'updated_at',
        'quantity_and_stock_status'
    ];

    private $objectManager;


    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductEventManagerInterface
     */
    private $productEventManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var ProductWebsiteLink
     */
    private $productWebsiteLink;

    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * @var Decider
     */
    private $featureSwitches;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var StockResolverInterface
     */
    private $stockResolver;

    /**
     * @var IsProductSalableInterface
     */
    private $isProductSalable;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ProductEventManagerInterface $productEventManager
     * @param Config $config
     * @param ProductFactory $productFactory
     * @param ProductWebsiteLink $productWebsiteLink
     * @param EavConfig $eavConfig
     * @param Decider $featureSwitches
     * @param Logger $logger
     * @param ModuleManager $moduleManager
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ProductEventManagerInterface $productEventManager,
        Config $config,
        ProductFactory $productFactory,
        ProductWebsiteLink $productWebsiteLink,
        EavConfig $eavConfig,
        Decider $featureSwitches,
        Logger $logger,
        ModuleManager $moduleManager
    ) {
        $this->objectManager = ObjectManager::getInstance();
        $this->storeManager = $storeManager;
        $this->productEventManager = $productEventManager;
        $this->config = $config;
        $this->productFactory = $productFactory;
        $this->productWebsiteLink = $productWebsiteLink;
        $this->eavConfig = $eavConfig;
        $this->featureSwitches = $featureSwitches;
        $this->logger = $logger;
        $this->moduleManager = $moduleManager;
        // Magento_InventorySalesApi introduced in Magento 2.3.x
        if ($this->moduleManager->isEnabled('Magento_InventorySalesApi')) {
            $this->stockResolver = $this->objectManager
                ->get('Magento\InventorySalesApi\Api\StockResolverInterface');
            $this->isProductSalable = $this->objectManager
                ->get('Magento\InventorySalesApi\Api\IsProductSalableInterface');
        }
    }

    /**
     * Publishing product event based on magento product inventory source items.
     * Checking previous values of qty/status of source item and publishing or not catalog ingestion product event.
     *
     * @param array $beforeProductStatuses, array[product_id...][website_id...] = bool isSalable
     * @param array $afterProductStatuses, array[product_id...][website_id...] = bool isSalable
     * @param SourceItemInterface[] $sourceItems
     * @return void
     */
    public function processProductEventSourceItemsBased(
        array $beforeProductStatuses,
        array $afterProductStatuses,
        array $sourceItems
    ): void
    {
        if (!$this->featureSwitches->isCatalogIngestionEnabled()) {
            return;
        }
        try {
            foreach ($sourceItems as $sourceItem) {
                /** @var SourceItem $sourceItem */
                $productId = $this->productFactory->create()->getIdBySku($sourceItem->getSku());
                $websiteIds = $this->productWebsiteLink->getWebsiteIdsByProductId($productId);
                foreach ($websiteIds as $websiteId) {
                    $isProductSalableBefore = (isset($beforeProductStatuses[$productId][$websiteId])) ?
                        $beforeProductStatuses[$productId][$websiteId] : false;
                    $isProductSalableAfter = (isset($afterProductStatuses[$productId][$websiteId])) ?
                        $afterProductStatuses[$productId][$websiteId] : false;
                    $this->processProductEventByInventoryData(
                        (int)$productId,
                        $isProductSalableAfter,
                        (int)$sourceItem->getData('quantity'),
                        (int)$websiteId,
                        $isProductSalableBefore,
                        (!is_null($sourceItem->getOrigData('quantity'))) ? (int)$sourceItem->getOrigData('quantity') : null
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }

    /**
     * Get product statuses based on source items
     *
     * @param SourceItemInterface[] $sourceItems
     * @return array, returns stock statuses as array[product_id...][website_id...] = bool isSalable
     * @throws LocalizedException
     */
    public function getProductStatusesSourceItemsBased(array $sourceItems): array
    {
        $result = [];
        foreach ($sourceItems as $sourceItem) {
            $sku = $sourceItem->getSku();
            $productId = $this->productFactory->create()->getIdBySku($sku);
            $websiteIds = $this->productWebsiteLink->getWebsiteIdsByProductId($productId);
            foreach ($websiteIds as $websiteId) {
                $websiteCode = $this->storeManager->getWebsite($websiteId)->getCode();
                $stock = $this->stockResolver->execute('website', $websiteCode);
                $stockId = (int)$stock->getStockId();
                $isSalable = $this->isProductSalable->execute($sku, $stockId);
                $result[$productId][$websiteId] = $isSalable;
            }
        }
        return $result;
    }

    /**
     * Publishing product event based on magento product inventory stock item.
     * Checking previous values of qty/status of product inventory item and publishing or not catalog ingestion product event.
     *
     * @param StockItemInterface $stockItem
     * @param StockItemInterface|null $oldStockItem
     * @return void
     */
    public function processProductEventStockItemBased(
        StockItemInterface $stockItem,
        StockItemInterface $oldStockItem = null
    ): void
    {
        if (!$this->featureSwitches->isCatalogIngestionEnabled()) {
            return;
        }
        $websiteIds = $this->productWebsiteLink->getWebsiteIdsByProductId($stockItem->getProductId());
        if (!$oldStockItem) {
            return;
        }
        foreach ($websiteIds as $websiteId) {
            try {
                $this->processProductEventByInventoryData(
                    (int)$stockItem->getProductId(),
                    (bool)$stockItem->getIsInStock(),
                    (int)$stockItem->getQty(),
                    (int)$websiteId,
                    (!is_null($oldStockItem->getIsInStock())) ? (bool)$oldStockItem->getIsInStock() : null,
                    (!is_null($oldStockItem->getQty())) ? (int)$oldStockItem->getQty() : null
                );
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }
        }
    }

    /**
     * Publishing product event based on magento product attributes update.
     * Checking previous values of product attributes and publishing or not catalog ingestion product event
     *
     * @param Product $product
     * @param bool $forcePublish force publishing flag to prevent attributes checking
     * @return void
     */
    public function processProductEventUpdateByProduct(Product $product, $forcePublish = false)
    {
        if (!$this->featureSwitches->isCatalogIngestionEnabled()) {
            return;
        }
        try {
            if (($forcePublish || $this->hasProductChanged($product, $product->getOrigData()))
            ) {
                $this->productEventManager->publishProductEvent(
                    (int)$product->getId(),
                    $this->getProductEventType($product)
                );
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }

    /**
     * Publishing product event based on magento products is salable result data.
     * Checking previous values of status of salable result data and publishing or not catalog ingestion product event.
     *
     * @param array $afterStatuses
     * @param array $beforeStatuses
     * @return void
     */
    public function processProductEventSalableItemsBased(array $afterStatuses, array $beforeStatuses): void
    {
        if (!$this->featureSwitches->isCatalogIngestionEnabled()) {
            return;
        }
        foreach ($afterStatuses as $sku => $salableStatus) {
            try {
                $productId = $this->productFactory->create()->getIdBySku($sku);
                $websiteIds = $this->productWebsiteLink->getWebsiteIdsByProductId($productId);
                foreach ($websiteIds as $websiteId) {
                    if ($this->isCatalogIngestionInstantUpdateByStockStatusAvailable(
                        $salableStatus,
                        $websiteId,
                        $beforeStatuses[$sku]
                    )) {
                        $this->productEventManager->runInstantProductEvent(
                            $productId,
                            ProductEventInterface::TYPE_UPDATE,
                            $websiteId
                        );
                    } else {
                        $this->productEventManager->publishProductEvent(
                            $productId,
                            ProductEventInterface::TYPE_UPDATE
                        );
                    }
                }
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }
        }
    }

    /**
     * Process of publishing instant/scheduled product event based on inventory status / inventory qty
     *
     * @param int $productId
     * @param bool $newStatus
     * @param int $newQty
     * @param int $websiteId
     * @param bool|null $oldStatus
     * @param int|null $oldQty
     * @return void
     * @throws LocalizedException
     * @throws \Zend_Http_Client_Exception
     */
    private function processProductEventByInventoryData(
        int $productId,
        bool $newStatus,
        int $newQty,
        int $websiteId,
        bool $oldStatus = null,
        int $oldQty = null
    ): void {
        if ($this->isCatalogIngestionInstantUpdateByStockStatusAvailable($newStatus, $websiteId, $oldStatus)) {
            $this->productEventManager->runInstantProductEvent(
                $productId,
                ProductEventInterface::TYPE_UPDATE,
                $websiteId
            );
        } elseif($this->isInventoryChanged($newStatus, $newQty, $oldStatus, $oldQty)) {
            $this->productEventManager->publishProductEvent(
                $productId,
                ProductEventInterface::TYPE_UPDATE
            );
        }
    }

    /**
     * Checking if inventory status / inventory qty were changed
     *
     * @param string $newStatus
     * @param int $newQty
     * @param string|null $oldStatus
     * @param int|null $oldQty
     * @return bool
     */
    private function isInventoryChanged(
        string $newStatus,
        int $newQty,
        string $oldStatus = null,
        int $oldQty = null
    ): bool {
        return ($oldQty != $newQty) || ($oldStatus != $newStatus);
    }

    /**
     * Checking if instant update is available for "product_stock_status" catalog ingestion event
     *
     * @param string $newStatus
     * @param int $websiteId
     * @param string|null $oldStatus
     * @return bool
     */
    private function isCatalogIngestionInstantUpdateByStockStatusAvailable(
        string $newStatus,
        int $websiteId,
        string $oldStatus = null
    ): bool {
        if (!$this->config->getIsCatalogIngestionInstantEnabled($websiteId) ||
            !in_array(Events::STOCK_STATUS_CHANGES, $this->config->getCatalogIngestionEvents($websiteId))
        ) {
            return false;
        }
        return $oldStatus != $newStatus;
    }

    /**
     * Returns product event type based on product entity_id.
     * If product don't have original entity_id therefore this product is new and product event type should be "create"
     *
     * @param Product $product
     * @return string
     */
    private function getProductEventType(Product $product): string
    {
        return ($product->getOrigData('entity_id') !== null) ?
            ProductEventInterface::TYPE_UPDATE : ProductEventInterface::TYPE_CREATE;
    }

    /**
     * Check whether the product has changed.
     *
     * @param Product $product
     * @param array|null $origData
     * @return bool
     */
    private function hasProductChanged(Product $product, ?array $origData = null): bool
    {
        $requiredAttributes = $this->eavConfig->getEntityAttributes(ProductAttributeInterface::ENTITY_TYPE_CODE);
        $attributes = $product->getAttributes();

        foreach ($requiredAttributes as $requiredAttribute) {
            if (!array_key_exists($requiredAttribute->getAttributeCode(), $attributes) ||
                in_array($requiredAttribute->getAttributeCode(), $this->disabledAttrCodes)
            ) {
                continue;
            }
            $attribute = $attributes[$requiredAttribute->getAttributeCode()];
            $oldValues = $this->fetchOldValues($attribute, $origData);
            try {
                $newValue = $this->extractAttributeValue($product, $attribute);
            } catch (\RuntimeException $exception) {
                //No new value
                continue;
            }
            if (!is_array($newValue) && $newValue !== null && $newValue !== '' && $newValue !== '0' &&
                !in_array($newValue, $oldValues, true) &&
                !($newValue == 'no_selection' && !empty($oldValues) && $oldValues[0] == null)
            ) {
                return true;
            } elseif (is_array($newValue)) {
                if ((!isset($oldValues[0]) && !empty($newValue)) ||
                    (isset($oldValues[0]) && count($newValue) != count($oldValues[0]))
                ) {
                    return true;
                }
                if (empty($newValue)) {
                    continue;
                }
                foreach ($newValue as $key => $valueArr) {
                    if (!isset($oldValues[0][$key])) {
                        return true;
                    } else {
                        if(is_array($valueArr)) {
                            foreach ($valueArr as $field => $value) {
                                if (!isset($oldValues[0][$key][$field])) {
                                    continue;
                                }
                                if ($value != $oldValues[0][$key][$field]) {
                                    return true;
                                }
                            }
                        } else {
                            if ($valueArr != $oldValues[0][$key]) {
                                return true;
                            }
                        }
                    }
                }
            }
        }
        return $this->isSystemProductAttributesChanged($product, $origData);
    }

    /**
     * Checking if system product attributes were changed:
     * "custom options"
     * "media gallery"
     * "product type"
     *
     * @param Product $product
     * @param array|null $origData
     * @return bool
     */
    private function isSystemProductAttributesChanged(Product $product, ?array $origData = null): bool
    {
        return $this->isCustomOptionsChanged($product, $origData) ||
            $this->isMediaGalleryChanged($product, $origData) ||
            $this->isProductTypeChanged($product, $origData);
    }

    /**
     * Checking if product type was changed
     *
     * @param Product $product
     * @param array|null $origData
     * @return bool
     */
    private function isProductTypeChanged(Product $product, ?array $origData = null): bool
    {
        return $product->getData(Product::TYPE_ID) != $origData[Product::TYPE_ID];
    }

    /**
     * Checking if media gallery was changed
     *
     * @param Product $product
     * @param array|null $origData
     * @return bool
     */
    private function isMediaGalleryChanged(Product $product, ?array $origData = null): bool
    {
        $mediaGallery = $product->getData(self::MEDIA_GALLERY_ATTR);
        $origMediaGallery = $origData[self::MEDIA_GALLERY_ATTR];
        $mediaGalleryImages = $mediaGallery[Image::MEDIA_TYPE_CONFIG_NODE];
        $origMediaGalleryImages = $origMediaGallery[Image::MEDIA_TYPE_CONFIG_NODE];

        if (count($mediaGalleryImages) != count($origMediaGalleryImages)) {
            return true;
        }
        if (!empty($mediaGalleryImages)) {
            foreach ($mediaGalleryImages as $key => $image) {
                if (isset($origMediaGalleryImages[$key])) {
                    if (isset($image[self::REMOVED_IMAGES_KEY]) && $image[self::REMOVED_IMAGES_KEY]) {
                        return true;
                    }
                    $origImageData = $origMediaGalleryImages[$key];
                    foreach ($image as $field => $value) {
                        if (!isset($origImageData[$field])) {
                            continue;
                        }
                        if ($origImageData[$field] != $value) {
                            return true;
                        }
                    }
                } else {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Checking if custom options were changed
     *
     * @param Product $product
     * @param array|null $origData
     * @return bool
     */
    private function isCustomOptionsChanged(Product $product, ?array $origData = null): bool
    {
        $options = $product->getData(self::CUSTOM_OPTIONS_KEY);
        $origOptions = $origData[self::CUSTOM_OPTIONS_KEY];
        if (count($options) != count($origOptions)) {
            return true;
        }
        if (!empty($options)) {
            foreach ($options as $key => $option) {
                if (isset($origOptions[$key])) {
                    $optionData = $option->getData();
                    $origOptionData = $origOptions[$key]->getData();
                    foreach ($optionData as $field => $value) {
                        if (!isset($origOptionData[$field])) {
                            continue;
                        }
                        if ($origOptionData[$field] != $value) {
                            return true;
                        }
                    }
                } else {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Prepare old values to compare to.
     *
     * @param AttributeInterface $attribute
     * @param array|null $origData
     * @return array
     */
    private function fetchOldValues(AttributeInterface $attribute, ?array $origData): array
    {
        $attrCode = $attribute->getAttributeCode();
        if ($origData) {
            //New value may only be the saved value
            $oldValues = [!empty($origData[$attrCode]) ? $origData[$attrCode] : null];
            if (empty($oldValues[0])) {
                $oldValues[0] = null;
            }
        } else {
            //New value can be empty or default
            $oldValues[] = $attribute->getDefaultValue();
        }

        return $oldValues;
    }

    /**
     * Extract attribute value from the model.
     *
     * @param Product $product
     * @param AttributeInterface $attr
     * @return mixed
     * @throws \RuntimeException When no new value is present.
     */
    private function extractAttributeValue(Product $product, AttributeInterface $attr)
    {
        if ($product->hasData($attr->getAttributeCode())) {
            $newValue = $product->getData($attr->getAttributeCode());
        } elseif ($product->hasData(Product::CUSTOM_ATTRIBUTES)
            && $attrValue = $product->getCustomAttribute($attr->getAttributeCode())
        ) {
            $newValue = $attrValue->getValue();
        } else {
            throw new \RuntimeException('No new value is present');
        }
        return $newValue;
    }
}
