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
use Magento\Catalog\Model\Product\Visibility;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\CatalogInventory\Model\Stock;
use Magento\Downloadable\Model\Product\Type as DownloadableProductType;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\Catalog\Model\Product\Gallery\ReadHandler as GalleryReadHandler;
use Magento\Framework\File\Mime;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Framework\App\ObjectManager;

/**
 * Product event product data processor to collect product data for bolt request
 */
class DataProcessor
{
    private const PRIMARY_IMAGE_TYPE_CODE = 'image';

    private const PRODUCT_VISIBILITY_VISIBLE = 'visible';

    private const PRODUCT_VISIBILITY_NOT_VISIBLE = 'not_visible';

    /**
     * @var ObjectManager
     */
    private $objectManager;

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
     * @var GetSourceItemsBySkuInterface|null
     */
    private $getSourceItemsBySku;

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
     * @param Config $config
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     * @param LocaleResolver $localeResolver
     * @param GalleryReadHandler $galleryReadHandler
     * @param Mime $mime
     * @param Emulation $emulation
     * @param string|null $getSourceItemsBySkuClass
     */
    public function __construct(
        Config $config,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        LocaleResolver $localeResolver,
        GalleryReadHandler $galleryReadHandler,
        Mime $mime,
        Emulation $emulation,
        string $getSourceItemsBySkuClass = null
    ) {
        $this->objectManager = ObjectManager::getInstance();
        $this->config = $config;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->localeResolver = $localeResolver;
        $this->galleryReadHandler = $galleryReadHandler;
        $this->mime = $mime;
        $this->emulation = $emulation;
        $this->getSourceItemsBySku = ($getSourceItemsBySkuClass) ?
            $this->initGetSourceItemsBySku($getSourceItemsBySkuClass) : null;
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
        $storeViewCollection = $website->getStoreCollection();
        foreach ($storeViewCollection as $storeView) {
            $product = $this->productRepository->getById(
                $productId,
                false,
                $storeView->getId()
            );

            if ($product->getTypeId() === Configurable::TYPE_CODE && empty($variantProductIds)) {
                $variantProductIds = $product->getTypeInstance()->getUsedProductIds($product);
            }

            //adds main product data into variants for non-default store view
            if ($storeView->getId() != $defaultStore->getId()) {
                $variants[] = $this->getBoltProductData($productId, (int)$storeView->getId());
            }

            if (empty($variantProductIds)) {
                continue;
            }

            foreach ($variantProductIds as $variantProductId) {
                $variants[] = $this->getBoltProductData($variantProductId, (int)$storeView->getId());
            }
        }

        return $variants;
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
        $storeView = $this->storeManager->getStore($storeId);
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
            'Properties' => $this->getProperties($product, (int)$storeView->getWebsiteId())
        ];

        if ($merchantVariantId = $product->getMerchantVariantId()) {
            $productData['MerchantVariantID'] = $merchantVariantId;
        }

        if ($weight = $product->getWeight()) {
            $productData['Weight'] = $weight;
        }

        if ($gtin = $product->getGtin()) {
            $productData['GTIN'] = $gtin;
        }

        if ($description = $product->getDescription()) {
            $productData['Description'] = $description;
        }

        if ($primaryImageId = $this->getProductPrimaryImageId($product)) {
            $productData['PrimaryImageID'] = $primaryImageId;
        }

        if ($weight = $product->getWeight()) {
            $productData['Weight'] = $weight;
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

        $this->emulation->stopEnvironmentEmulation();
        return $productData;
    }

    /**
     * Returns product options
     *
     * @param ProductInterface $product
     * @return array
     */
    private function getOptions(ProductInterface $product): array
    {
        $options = $product->getOptions();
        if (empty($options)) {
            return [];
        }
        $result = [];
        foreach ($options as $option) {
            $optionData = [
                'name' => $option->getDefaultTitle(),
                'display_type' => $option->getType(),
                'display_name' => $option->getTitle(),
                'values' => [],
                'visibility' => 'visible',
                'sort_order' => $option->getSortOrder(),
            ];

            if ($values = $option->getValues()) {
                foreach ($values as $valueId => $value) {
                    $optionData['values'][] = [
                        'value' => $valueId,
                        'display_value' => $value->getTitle(),
                        'sort_order' => $value->getSortOrder()
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
     * @param int $websiteId
     * @return array
     */
    private function getProperties(ProductInterface $product, int $websiteId): array
    {
        $requiredAttributes = $this->config->getCatalogIngestionProductPropertiesAttributes($websiteId);
        if (empty($requiredAttributes)) {
            return [];
        }
        $properties = [];
        $productAttributes = $product->getAttributes();
        foreach ($requiredAttributes as $requiredAttrCode) {
            if (!isset($productAttributes[$requiredAttrCode])) {
                continue;
            }
            $productAttribute = $productAttributes[$requiredAttrCode];
            $productAttributeData = [
                'name' => $productAttribute->getAttributeCode(),
                'name_id' => $productAttribute->getAttributeId(),
                'value' => $product->getData($productAttribute->getAttributeCode()),
                'value_id' => $product->getData($productAttribute->getAttributeCode()),
                'display_type' => $productAttribute->getFrontendInput(),
                'display_name' => $productAttribute->getAttributeCode(),
                'display_value' => $product->getAttributeText($productAttribute->getAttributeCode()),
                'visibility' => ($productAttribute->getIsVisible()) ? 'visible' : '',
                'text_label' => $productAttribute->getFrontendLabel(),
                'image_url' => '',
                'position' => (int)$productAttribute->getPosition(),
            ];
            $properties[] = $productAttributeData;
        }

        return $properties;
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
                    'media_file' => $mediaImage->getPath(),
                    'media_type' => $this->mime->getMimeType($mediaImage->getPath()),
                    'url' => $mediaImage->getUrl(),
                    'size_name' => implode(',', $this->getMediaImageEntry($product, $imageId)->getTypes()),
                    'width' => $imageSize[0],
                    'height' => $imageSize[1],
                    'length' => null,
                    'description' => ($mediaImage->getVideoDescription()) ?: '',
                ];
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
        if ($this->getSourceItemsBySku) {
            $stockItems = $this->getSourceItemsBySku->execute($product->getSku());
        }
        if (!empty($stockItems)) {
            foreach ($stockItems as $stockItem) {
                $sourceItemData['fullfillment_center_id'] = $stockItem->getSourceCode();
                $sourceItemData['inventory_level'] = $stockItem->getQuantity();
                $inventories[] = $sourceItemData;
            }
        } else {
            $stockItem = $product->getExtensionAttributes()->getStockItem();
            $inventories[] = [
                'fullfillment_center_id' => 'default',
                'inventory_level' => $stockItem->getQty(),
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
        $prices = [
            [
                'list_price' => $product->getPriceInfo()->getPrice('final_price')->getValue(),
                'sale_price' => $product->getPriceInfo()->getPrice('final_price')->getValue(),
                'currency' => $this->storeManager->getDefaultStoreView()->getCurrentCurrency()->getCode(),
                'locale' => $this->localeResolver->emulate($this->storeManager->getDefaultStoreView()->getId()),
                'unit' => '',
            ]
        ];
        return $prices;
    }

    /**
     * Returns product primary image id
     *
     * @param ProductInterface $product
     * @return int|null
     */
    private function getProductPrimaryImageId(ProductInterface $product): ?int
    {
        $mediaGallery = $product->getMediaGalleryEntries();
        if (empty($mediaGallery)) {
            return null;
        }
        $imageId = null;
        foreach ($mediaGallery as $image) {
            if (in_array(self::PRIMARY_IMAGE_TYPE_CODE, $image->getTypes())) {
                $imageId = $image->getId();
            }
        }
        if (!$imageId) {
            $imageId = $mediaGallery[0]->getId();
        }
        return (int)$imageId;
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

    /**
     * Init get source items cmd, for Magento 2.2 support
     *
     * @param string $getSourceItemsBySkuClass
     * @return mixed|null
     */
    private function initGetSourceItemsBySku(string $getSourceItemsBySkuClass)
    {
        return (class_exists($getSourceItemsBySkuClass))
            ? $this->objectManager->get($getSourceItemsBySkuClass) : null;
    }
}
