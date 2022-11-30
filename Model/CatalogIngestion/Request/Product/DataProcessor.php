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

namespace Bolt\Boltpay\Model\CatalogIngestion\Request\Product;

use Bolt\Boltpay\Api\Data\ProductEventInterface;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Catalog\Model\Product\Visibility;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\CatalogInventory\Model\Stock;
use Magento\Downloadable\Model\Product\Type as DownloadableProductType;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\Catalog\Model\Product\Gallery\ReadHandler as GalleryReadHandler;
use Magento\Framework\File\Mime;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\InputException;
use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavAttribute;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;
use Magento\Bundle\Api\ProductLinkManagementInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableProductType;
use Magento\Bundle\Model\Product\Type as BundleProductType;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedProductType;

/**
 * Product event product data processor to collect product data for bolt request
 */
class DataProcessor
{
    private const PRIMARY_IMAGE_TYPE_CODE = 'image';

    private const PRODUCT_VISIBILITY_VISIBLE = 'visible';

    private const PRODUCT_VISIBILITY_NOT_VISIBLE = 'not_visible';

    private const PRODUCT_IMAGE_SIZENAME = 'standard';

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var LocaleResolver
     */
    private $localeResolver;

    /**
     * @var string|null
     */
    private $getSalableQuantityDataBySkuClass;

    /**
     * @var GalleryReadHandler
     */
    private $galleryReadHandler;

    /**
     * @var Mime
     */
    private $mime;

    /**
     * @var Emulation
     */
    private $emulation;

    /**
     * @var GetSalableQuantityDataBySku
     */
    private $getSalableQuantityDataBySku;

    /**
     * @var ProductLinkManagementInterface
     */
    private $productLinkManagement;

    /**
     * @var ConfigurableProductType
     */
    private $configurableProductType;

    /**
     * @var BundleProductType
     */
    private $bundleProductType;

    /**
     * @var GroupedProductType
     */
    private $groupedProductType;

    /**
     * @var array
     */
    private $availableProductTypesForVariants = [
        Configurable::TYPE_CODE,
        ProductType::TYPE_BUNDLE,
        Grouped::TYPE_CODE
    ];

    /**
     * @param Config $config
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     * @param LocaleResolver $localeResolver
     * @param GalleryReadHandler $galleryReadHandler
     * @param Mime $mime
     * @param Emulation $emulation
     * @param ModuleManager $moduleManager
     * @param ProductLinkManagementInterface $productLinkManagement
     * @param ConfigurableProductType $configurableProductType
     * @param BundleProductType $bundleProductType
     * @param GroupedProductType $groupedProductType
     */
    public function __construct(
        Config $config,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        LocaleResolver $localeResolver,
        GalleryReadHandler $galleryReadHandler,
        Mime $mime,
        Emulation $emulation,
        ModuleManager $moduleManager,
        ProductLinkManagementInterface $productLinkManagement,
        ConfigurableProductType $configurableProductType,
        BundleProductType $bundleProductType,
        GroupedProductType $groupedProductType
    ) {
        $this->objectManager = ObjectManager::getInstance();
        $this->config = $config;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->localeResolver = $localeResolver;
        $this->galleryReadHandler = $galleryReadHandler;
        $this->mime = $mime;
        $this->emulation = $emulation;
        $this->productLinkManagement = $productLinkManagement;
        $this->moduleManager = $moduleManager;
        $this->configurableProductType = $configurableProductType;
        $this->bundleProductType = $bundleProductType;
        $this->groupedProductType = $groupedProductType;
        if ($this->moduleManager->isEnabled('Magento_InventoryCatalog')) {
            $this->getSalableQuantityDataBySku = $this->objectManager
                ->get('Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku');
        }
    }

    /**
     * Returns product data for bolt request
     *
     * @param int $productId
     * @param int $websiteId
     * @param string $productEventType
     * @return array
     * @throws LocalizedException
     * @throws FileSystemException
     * @throws NoSuchEntityException
     */
    public function getRequestProductData(int $productId, int $websiteId, string $productEventType): array
    {
        $requestProductData = [];
        $website = $this->storeManager->getWebsite($websiteId);
        $defaultStore = $website->getDefaultStore();
        if ($productEventType != ProductEventInterface::TYPE_DELETE) {
            $defaultStoreViewBoltProductData = $this->getBoltProductData($productId, (int)$defaultStore->getId());
            $requestProductData['product'] = $defaultStoreViewBoltProductData;
            $requestProductData['variants'] = $this->getVariants($productId, $website);
        } else {
            $requestProductData['product'] = [
                'MerchantProductID' => (string)$productId
            ];
        }
        return $requestProductData;
    }

    /**
     * Return product variants for website
     *
     * @param int $productId
     * @param WebsiteInterface $website
     * @return array
     * @throws FileSystemException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getVariants(int $productId, WebsiteInterface $website): array
    {
        $variants = [];
        $variantProductIds = [];
        $defaultStore = $website->getDefaultStore();

        $product = $this->productRepository->getById(
            $productId,
            false,
            $defaultStore->getId()
        );

        if (in_array($product->getTypeId(), $this->availableProductTypesForVariants) && empty($variantProductIds)) {
            $variantProductIds = $this->getChildProductIds($product);
            foreach ($variantProductIds as $variantProductId) {
                $variants[] = $this->getBoltProductData($variantProductId, (int)$defaultStore->getId());
            }
        }

        return $variants;
    }

    /**
     * Get child product ids from diff product types
     *
     * @param ProductInterface $product
     * @return array
     * @throws InputException
     * @throws NoSuchEntityException
     */
    private function getChildProductIds(ProductInterface $product): array
    {
        $childProductIds = [];
        switch ($product->getTypeId()) {
            case Configurable::TYPE_CODE:
                $childProductIds = $product->getTypeInstance()->getUsedProductIds($product);
                break;
            case ProductType::TYPE_BUNDLE:
                $childProductIds = $this->getBundleChildProductIds($product);
                break;
            case Grouped::TYPE_CODE:
                $childProductIds = $this->getGroupedChildProductIds($product);
                break;
        }
        return $childProductIds;
    }

    /**
     * Get bundle product type child products
     *
     * @param ProductInterface $product
     * @return array
     * @throws NoSuchEntityException
     * @throws InputException
     */
    private function getBundleChildProductIds(ProductInterface $product): array
    {
        if ($product->getTypeId() != ProductType::TYPE_BUNDLE) {
            return [];
        }
        $childProductIds = [];
        $productLinks =  $this->productLinkManagement->getChildren($product->getSku());
        if (!empty($productLinks)) {
            foreach ($productLinks as $productLink) {
                $childProductIds[] = $productLink->getEntityId();
            }
        }
        return $childProductIds;
    }

    /**
     * Get grouped product type child products
     *
     * @param ProductInterface $product
     * @return array
     * @throws NoSuchEntityException
     * @throws InputException
     */
    private function getGroupedChildProductIds(ProductInterface $product): array
    {
        if ($product->getTypeId() != Grouped::TYPE_CODE) {
            return [];
        }
        $childProductIds = [];
        $associatedProducts =  $product->getTypeInstance()->getAssociatedProducts($product);
        if (!empty($associatedProducts)) {
            foreach ($associatedProducts as $associatedProduct) {
                $childProductIds[] = $associatedProduct->getEntityId();
            }
        }
        return $childProductIds;
    }
    /**
     * Returns base product data for bolt request
     *
     * @param int $productId
     * @param int $storeId
     * @return array
     * @throws LocalizedException
     * @throws FileSystemException
     */
    private function getBoltProductData(int $productId, int $storeId): array
    {
        //using env. emulation for correct store view stock data
        $this->emulation->startEnvironmentEmulation($storeId);
        $product = $this->productRepository->getById($productId, false, $storeId);
        $this->galleryReadHandler->execute($product);
        $productData = [
            'MerchantProductID' => (string)$product->getId(),
            'ProductType' => $product->getTypeId(),
            'SKU' => $product->getSku(),
            'URL' => $product->getProductUrl(),
            'Name' => $product->getName(),
            'ManageInventory' => $this->isManageStock($product),
            'Visibility' => $this->getVisibility($product),
            'Backorder' => $this->getBackorderStatus($product),
            'Availability' => $this->getProductAvailability($product),
            'ShippingRequired' => $this->isShippingRequired($product),
            'Prices' => $this->getPrices($product),
            'Inventories' => $this->getInventories($product),
            'Media' => $this->getMedia($product),
            'Options' => $this->getOptions($product),
            'Properties' => $this->getProperties($product)
        ];

        if ($weight = $product->getWeight()) {
            $productData['Weight'] = $weight;
        }

        if ($description = $product->getDescription()) {
            $productData['Description'] = $description;
        }

        if ($weight = $product->getWeight()) {
            $productData['Weight'] = $weight;
        }

        // Magento don't have default properties for GTIN, Width, Depth and Height
        // but if there are custom ones we will set them
        if ($gtin = $product->getGtin()) {
            $productData['GTIN'] = $gtin;
        }
        if ($width = $product->getWidth()) {
            $productData['Width'] = $width;
        }
        if ($depth = $product->getDepth()) {
            $productData['Depth'] = $depth;
        }
        if ($height = $product->getHeight()) {
            $productData['Height'] = $height;
        }

        $parentProductIds = $this->getParentProductIds($product);
        if (!empty($parentProductIds)) {
            $productData['ParentProductIDs'] = $parentProductIds;
        }

        $this->emulation->stopEnvironmentEmulation();
        return $productData;
    }

    /**
     * Get parent product ids
     *
     * @param ProductInterface $product
     * @return array
     */
    private function getParentProductIds(ProductInterface $product): array
    {
        $configParentIds = $this->configurableProductType->getParentIdsByChild($product->getId());
        $bundleParentIds = $this->bundleProductType->getParentIdsByChild($product->getId());
        $groupedParentIds = $this->groupedProductType->getParentIdsByChild($product->getId());
        return array_merge($configParentIds, $bundleParentIds, $groupedParentIds);
    }

    /**
     * Returns product options
     *
     * @param ProductInterface $product
     * @return array
     */
    private function getOptions(ProductInterface $product): array
    {
        $customOptions = $this->getCustomOptions($product);
        $bundleOptions = $this->getBundleOptions($product);
        $configurableOptions = $this->getConfigurableOptions($product);
        return array_merge($customOptions, $bundleOptions, $configurableOptions);
    }

    /**
     * Returns bundle product options
     *
     * @param ProductInterface $product
     * @return array
     */
    private function getBundleOptions(ProductInterface $product): array
    {
        if ($product->getTypeId() !== ProductType::TYPE_BUNDLE) {
            return [];
        }

        $bundleProductOptions = $product->getExtensionAttributes()->getBundleProductOptions();
        if (empty($bundleProductOptions)) {
           return [];
        }

        $result = [];
        foreach ($bundleProductOptions as $bundleOption) {
            $optionData = [
                'Name' => $bundleOption->getDefaultTitle(),
                'DisplayType' => $bundleOption->getType(),
                'DisplayName' => $bundleOption->getTitle(),
                'BundleValues' => [],
                'Visibility' => 'true',
                'SortOrder' => (int)$bundleOption->getPosition(),
                'IsRequired' => (bool)$bundleOption->getRequired(),
                'MagentoOptionType' => 'BundleProductOption',
            ];

            if ($productLinks = $bundleOption->getProductLinks()) {
                foreach ($productLinks as $productLink) {
                    $optionData['BundleValues'][] = [
                        'MerchantProductID' => $productLink->getEntityId(),
                        'SKU' => $productLink->getSku(),
                        'SortOrder' => (int)$productLink->getPosition(),
                        'Qty' => (int)$productLink->getQty(),
                        'SelectionCanChangeQuantity' => (bool)$productLink->getSelectionCanChangeQuantity(),
                    ];
                }
            }
            $result[] = $optionData;
        }

        return $result;
    }

    /**
     * Returns configurable product options
     *
     * @param ProductInterface $product
     * @return array
     */
    private function getConfigurableOptions(ProductInterface $product): array
    {
        if ($product->getTypeId() !== Configurable::TYPE_CODE) {
            return [];
        }

        $configurableProductOptions = $product->getExtensionAttributes()->getConfigurableProductOptions();
        if (empty($configurableProductOptions)) {
            return [];
        }

        $result = [];
        foreach ($configurableProductOptions as $confOption) {
            $optionData = [
                'Name' => $confOption->getProductAttribute()->getAttributeCode(),
                'DisplayType' => 'select',
                'DisplayName' => $confOption->getLabel(),
                'Values' => [],
                'Visibility' => 'true',
                'SortOrder' => (int)$confOption->getPosition(),
                'MagentoOptionType' => 'ConfigurableProductOption',
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
            $result[] = $optionData;
        }

        return $result;
    }

    /**
     * Returns product custom options
     *
     * @param ProductInterface $product
     * @return array
     */
    private function getCustomOptions(ProductInterface $product): array
    {
        $customOptions = $product->getOptions();
        if (empty($customOptions)) {
            return [];
        }
        $result = [];
        foreach ($customOptions as $option) {
            $optionData = [
                'Name' => $option->getDefaultTitle(),
                'DisplayType' => $option->getType(),
                'DisplayName' => $option->getTitle(),
                'Visibility' => 'true',
                'SortOrder' => (int)$option->getSortOrder(),
            ];

            if ($values = $option->getValues()) {
                foreach ($values as $valueId => $value) {
                    $optionData['Values'][] = [
                        'Value' => string($valueId),
                        'DisplayValue' => $value->getTitle(),
                        'SortOrder' => (int)$value->getSortOrder()
                    ];
                }
            }
            $result[] = $optionData;
        }
        return $result;
    }

    /**
     * Returns product properties
     *
     * @param ProductInterface $product
     * @return array
     */
    private function getProperties(ProductInterface $product): array
    {
        $properties = [];
        $productAttributes = $product->getAttributes();
        foreach ($productAttributes as $productAttribute) {
            if (!$productAttribute->getIsUserDefined()) {
                continue;
            }
            $productAttributeData = [
                'Name' => $productAttribute->getAttributeCode(),
                'Value' => $product->getData($productAttribute->getAttributeCode()),
                'ValueID' => $this->getAttributeValueId($product, $productAttribute),
                'DisplayType' => $productAttribute->getFrontendInput(),
                'DisplayName' => $productAttribute->getAttributeCode(),
                'DisplayValue' => ($this->getAttributeDisplayValue($product, $productAttribute)),
                'TextLabel' => $productAttribute->getFrontendLabel(),
                'Position' => (int)$productAttribute->getPosition(),
            ];
            $attributeValue = $product->getData($productAttribute->getAttributeCode());
            if (is_numeric($attributeValue)) {
                $productAttributeData["ValueID"] = (int)$attributeValue;
            }
            if ($productAttribute->getAttributeId()) {
                $productAttributeData['NameID'] = (int)$productAttribute->getAttributeId();
            }
            if ($productAttribute->getIsVisible()) {
                $productAttributeData['Visibility'] = 'visible';
            }

            $properties[] = $productAttributeData;
        }

        return $properties;
    }

    /**
     * Returns product attribute display value
     *
     * @param ProductInterface $product
     * @param EavAttribute $attribute
     * @return string|null
     */
    private function getAttributeDisplayValue(ProductInterface $product, EavAttribute $attribute): ?string
    {
        try {
            $value = $product->getResource()
                ->getAttribute($attribute->getAttributeCode())
                ->getFrontend()
                ->getValue($product);
            if (is_array($value)) {
                $value = $product->getAttributeText($attribute->getAttributeCode());
            }
            return $value;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Returns media gallery data
     *
     * @param ProductInterface $product
     * @return array
     * @throws FileSystemException
     */
    private function getMedia(ProductInterface $product): array
    {
        $media = [];
        $mediaGalleryImages = $product->getMediaGalleryImages();

        if (!empty($mediaGalleryImages)) {
            foreach ($mediaGalleryImages as $imageId => $mediaImage) {
                $imageSize = getimagesize($mediaImage->getPath());
                $mediaData = [
                    'MediaFile' => $mediaImage->getPath(),
                    'MediaType' => $this->mime->getMimeType($mediaImage->getPath()),
                    'URL' => $mediaImage->getUrl(),
                    'SizeName' => self::PRODUCT_IMAGE_SIZENAME,
                    'Width' => $imageSize[0],
                    'Height' => $imageSize[1],
                ];
                if ($mediaImage->getVideoDescription()) {
                    $mediaData['Description'] = $mediaImage->getVideoDescription();
                }
                $media[] = $mediaData;
            }
        }
        return $media;
    }

    /**
     * Returns product image media entry
     *
     * @param ProductInterface $product
     * @param int $imageId
     * @return ProductAttributeMediaGalleryEntryInterface|null
     */
    private function getMediaImageEntry(ProductInterface $product, int $imageId): ?ProductAttributeMediaGalleryEntryInterface
    {
        $mediaGalleryEntries = $product->getMediaGalleryEntries();
        foreach ($mediaGalleryEntries as $mediaEntry) {
            if ($mediaEntry->getId() == $imageId) {
                return $mediaEntry;
            }
        }
    }

    /**
     * Returns product inventories data
     *
     * @param ProductInterface $product
     * @return array
     */
    private function getInventories(ProductInterface $product): array
    {
        $inventories = [];
        $stockItems = [];

        if ($this->getSalableQuantityDataBySku) {
            $stockItems = $this->getSalableQuantityDataBySku->execute($product->getSku());
        }
        if (!empty($stockItems)) {
            foreach ($stockItems as $stockItem) {
                $sourceItemData['FulfillmentCenterID'] = $stockItem['stock_name'];
                $sourceItemData['InventoryLevel'] = (int)$stockItem['qty'];
                $inventories[] = $sourceItemData;
            }
        } else {
            $stockItem = $product->getExtensionAttributes()->getStockItem();
            $inventories[] = [
                'FulfillmentCenterID' => 'default',
                'InventoryLevel' => (int)$stockItem->getQty(),
            ];
        }
        return $inventories;
    }

    /**
     * Returns product prices data
     *
     * @param ProductInterface $product
     * @return array[]
     * @throws LocalizedException
     */
    private function getPrices(ProductInterface $product): array
    {
        $currencyCode = $this->storeManager->getStore()->getCurrentCurrency()->getCode();
        $price = CurrencyUtils::toMinor($product->getPriceInfo()->getPrice('final_price')->getValue(), $currencyCode);
        $prices = [
            [
                'ListPrice' => $price,
                'SalePrice' => $price,
                'Currency' => $currencyCode,
                'Locale' => $this->localeResolver->emulate($this->storeManager->getStore()->getId()),
            ]
        ];
        return $prices;
    }

    /**
     * Returns product backorder status
     *
     * @param ProductInterface $product
     * @return string
     */
    private function getBackorderStatus(ProductInterface $product): string
    {
        $backOrders = $product->getExtensionAttributes()->getStockItem()->getBackorders();
        $result = '';
        switch ($backOrders) {
            case Stock::BACKORDERS_NO:
                $result = 'no';
                break;
            case Stock::BACKORDERS_YES_NONOTIFY:
                $result = 'yes';
                break;
            case Stock::BACKORDERS_YES_NOTIFY:
                $result = 'notify';
                break;
        }
        return $result;
    }

    /**
     * Returns if product requires shipping
     *
     * @param ProductInterface $product
     * @return bool
     */
    private function isShippingRequired(ProductInterface $product): bool
    {
        return !(($product->getTypeId() == DownloadableProductType::TYPE_DOWNLOADABLE ||
            $product->getTypeId() == ProductType::TYPE_VIRTUAL));
    }

    /**
     * Returns product stock status
     *
     * @param ProductInterface $product
     * @return string
     */
    private function getProductAvailability(ProductInterface $product): string
    {
        $isAvailable = $product->isAvailable();
        if ($product->getTypeId() == Configurable::TYPE_CODE) {
            $stockItem = $product->getExtensionAttributes()->getStockItem();
            $isAvailable = (bool)$stockItem->getIsInStock() && $product->getQuantityAndStockStatus()['is_in_stock'];
        }
        return ($isAvailable) ? 'in_stock' : 'out_of_stock';
    }

    /**
     * Returns if product is manage stock enabled
     *
     * @param ProductInterface $product
     * @return bool
     */
    private function isManageStock(ProductInterface $product): bool
    {
        return (bool)$product->getExtensionAttributes()->getStockItem()->getManageStock();
    }

    /**
     * Returns visibility of product
     *
     * @param ProductInterface $product
     * @return string
     */
    private function getVisibility(ProductInterface $product): string
    {
        return (!(($product->getVisibility() == Visibility::VISIBILITY_NOT_VISIBLE))) ?
            self::PRODUCT_VISIBILITY_VISIBLE : self::PRODUCT_VISIBILITY_NOT_VISIBLE;
    }
}
