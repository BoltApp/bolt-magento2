<?php
namespace Bolt\Boltpay\Test\Unit;

class TestUtils {
    public static function createSimpleProduct()
    {
        /*$cart = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create(\Magento\Checkout\Model\Cart::class);*/
        /*$productRepository = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create(\Magento\Catalog\Api\ProductRepositoryInterface::class);*/
        /** @var $product \Magento\Catalog\Model\Product */
        $product = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create(\Magento\Catalog\Model\Product::class);
        error_log("#1.1");
        $product->setTypeId(
            \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE
        )->setAttributeSetId(
            4
        )->setWebsiteIds(
            [1]
        )->setName(
            'Simple Product 1'
        )->setSku(
            'Simple Product 1 sku'
        )->setPrice(
            10
        )->setDescription(
            'Description with <b>html tag</b>'
        )->setMetaTitle(
            'meta title'
        )->setMetaKeyword(
            'meta keyword'
        )->setMetaDescription(
            'meta description'
        )->setVisibility(
            \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH
        )->setStatus(
            \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED
        )->setCategoryIds(
            [2]
        )->setStockData(
            ['use_config_manage_stock' => 0]
        )->setCanSaveCustomOptions(
            true
        )->setHasOptions(
            true
        );
        return $product;
    }
}