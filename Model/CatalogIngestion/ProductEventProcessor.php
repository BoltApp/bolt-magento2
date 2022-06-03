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
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Inventory\Model\SourceItem;
use Magento\Catalog\Model\ResourceModel\Product\Website\Link as ProductWebsiteLink;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Helper\Image;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Exception\LocalizedException;

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
        'updated_at'
    ];

    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * @param ProductEventManagerInterface $productEventManager
     * @param Config $config
     * @param ProductFactory $productFactory
     * @param ProductWebsiteLink $productWebsiteLink
     * @param EavConfig $eavConfig
     * @param Logger $logger
     */
    public function __construct(
        ProductEventManagerInterface $productEventManager,
        Config $config,
        ProductFactory $productFactory,
        ProductWebsiteLink $productWebsiteLink,
        EavConfig $eavConfig,
        Logger $logger
    ) {
        $this->productEventManager = $productEventManager;
        $this->config = $config;
        $this->productFactory = $productFactory;
        $this->productWebsiteLink = $productWebsiteLink;
        $this->eavConfig = $eavConfig;
        $this->logger = $logger;
    }

    /**
     * Update product events based on source items
     *
     * @param array $sourceItems
     * @return void
     */
    public function processProductEventSourceItemsBased(array $sourceItems, $forcePublish = false): void
    {
        try {
            foreach ($sourceItems as $sourceItem) {
                /** @var SourceItem $sourceItem */
                $productId = $this->productFactory->create()->getIdBySku($sourceItem->getSku());
                $websiteIds = $this->productWebsiteLink->getWebsiteIdsByProductId($productId);
                foreach ($websiteIds as $websiteId) {
                    if ($forcePublish && $this->config->getIsCatalogIngestionEnabled($websiteId)) {
                        $this->productEventManager->publishProductEvent(
                            $productId,
                            ProductEventInterface::TYPE_UPDATE
                        );
                        break;
                    } else {
                        $this->processProductEventByInventoryData(
                            (int)$productId,
                            $sourceItem->getData('status'),
                            (int)$sourceItem->getData('quantity'),
                            (int)$websiteId,
                            $sourceItem->getOrigData('status'),
                            (int)$sourceItem->getOrigData('quantity')
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }

    /**
     * Process stock item product events
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
        $websiteIds = $this->productWebsiteLink->getWebsiteIdsByProductId($stockItem->getProductId());
        if (!$oldStockItem) {
            return;
        }
        foreach ($websiteIds as $websiteId) {
            try {
                $this->processProductEventByInventoryData(
                    (int)$stockItem->getProductId(),
                    $oldStockItem->getIsInStock(),
                    (int)$stockItem->getQty(),
                    (int)$websiteId,
                    $oldStockItem->getIsInStock(),
                    (int)$oldStockItem->getQty()
                );
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }
        }
    }

    /**
     * Process product event for product
     *
     * @param Product $product
     * @return void
     */
    public function processProductEventUpdateByProduct(Product $product, $forcePublish = false)
    {
        $websiteIds = $product->getWebsiteIds();
        foreach ($websiteIds as $websiteId) {
            try {
                if ($this->config->getIsCatalogIngestionEnabled($websiteId) &&
                    ($forcePublish || $this->hasProductChanged($product, $product->getOrigData()))
                ) {
                    $this->productEventManager->publishProductEvent(
                        (int)$product->getId(),
                        $this->getProductEventType($product)
                    );
                    //break, because product event already created and future websites check is not needed
                    break;
                }
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }
        }
    }

    /**
     * Process product event by inventory main data
     *
     * @param int $productId
     * @param string $newStatus
     * @param int $newQty
     * @param int $websiteId
     * @param string|null $oldStatus
     * @param int|null $oldQty
     * @return void
     * @throws LocalizedException
     * @throws \Zend_Http_Client_Exception
     */
    private function processProductEventByInventoryData(
        int $productId,
        string $newStatus,
        int $newQty,
        int $websiteId,
        string $oldStatus = null,
        int $oldQty = null
    ): void {
        if (!$this->config->getIsCatalogIngestionEnabled($websiteId)) {
            return;
        }
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
     * Check if inventory main data was changed
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
     * Check if instant update is available for "product_stock_status" catalog ingestion event
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
     * Returns product event type
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
                !in_array($newValue, $oldValues, true)
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
     * Check if system product attributes was changed like "custom options"
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
     * Check if product type was changed
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
     * Check if media gallery was changed
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
     * Check if custom options was changed
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
