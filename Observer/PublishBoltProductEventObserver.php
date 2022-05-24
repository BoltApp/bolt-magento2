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
 *
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Bolt\Boltpay\Observer;

use Bolt\Boltpay\Api\ProductEventManagerInterface;
use Bolt\Boltpay\Api\Data\ProductEventInterface;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Logger\Logger;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Helper\Image;

/**
 * Publish product event after the product is saved
 */
class PublishBoltProductEventObserver implements ObserverInterface
{
    private const REMOVED_IMAGES_KEY = 'removed';

    private const CUSTOM_OPTIONS_KEY = 'options';

    /**
     * @var ProductEventManagerInterface
     */
    private $productEventManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param ProductEventManagerInterface $productEventManager
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(
        ProductEventManagerInterface $productEventManager,
        Config $config,
        Logger $logger
    ) {
        $this->productEventManager = $productEventManager;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Process event on 'save_commit_after' event
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var Product $product */
            $product = $observer->getEvent()->getProduct();
            if ($this->config->getIsCatalogIngestionScheduleEnabled($product->getStore()->getWebsiteId()) &&
                $this->hasProductChanged($product, $product->getOrigData())
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
        $requiredAttributes = $this->config->getCatalogIngestionProductTriggerAttributes();
        $attributes = $product->getAttributes();

        foreach ($requiredAttributes as $requiredAttribute) {
            if (!array_key_exists($requiredAttribute, $attributes)) {
                continue;
            }
            $attribute = $attributes[$requiredAttribute];
            $oldValues = $this->fetchOldValues($attribute, $origData);
            try {
                $newValue = $this->extractAttributeValue($product, $attribute);
            } catch (\RuntimeException $exception) {
                //No new value
                continue;
            }
            if (!is_array($newValue) && $newValue !== null && !in_array($newValue, $oldValues, true)) {
                return true;
            } elseif (is_array($newValue)) {
                if ((!isset($oldValues[0]) && !empty($newValue)) ||
                    (isset($oldValues[0]) && count($newValue) != count($oldValues[0]))
                ) {
                    return true;
                }
                if (empty($newValue)) {
                    return false;
                }
                foreach ($newValue as $key => $valueArr) {
                   if (!isset($oldValues[0][$key])) {
                       return true;
                   } else {
                       foreach ($valueArr as $field => $value) {
                           if (!isset($oldValues[0][$key][$field])) {
                               continue;
                           }
                           if ($value != $oldValues[0][$key][$field]) {
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
        $mediaGallery = $product->getData(Product::MEDIA_GALLERY);
        $origMediaGallery = $origData[Product::MEDIA_GALLERY];
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
