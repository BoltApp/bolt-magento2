<?php
namespace Bolt\Boltpay\Test\Unit;

use Magento\TestFramework\Helper\Bootstrap;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Quote\Model\Quote;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class TestUtils {

    public static function setQuoteToSession($quote)
    {
        Bootstrap::getObjectManager()->get(SessionManagerInterface::class)->setQuote($quote);
    }

    public static function createQuote()
    {
        $quote = Bootstrap::getObjectManager()->create(Quote::class);
        $quote->setQuoteCurrencyCode("USD");
        $quote->save();
        return $quote;
    }

    public static function getQuoteById($quote_id)
    {
        return Bootstrap::getObjectManager()->get(CartRepositoryInterface::class)->get($quote_id);
    }

    public static function createProduct($quote)
    {
        $quote = self::createQuote();
        $product = self::createSimpleProduct();
        $product->save();
        $quote->addProduct($product, 1);
        return $quote;
    }

    public static function createSimpleProduct()
    {
        $product = Bootstrap::getObjectManager()->create(Product::class);
        $product->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Test Product')
            ->setSku('TestProduct')
            ->setPrice(100)
            ->setDescription('Product Description')
            ->setMetaTitle('meta title')
            ->setMetaKeyword('meta keyword')
            ->setMetaDescription('meta description')
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setCategoryIds([2])
            ->setStockData(['use_config_manage_stock' => 0])
            ->setCanSaveCustomOptions(true)
            ->setHasOptions(true);
        $product->save();
        return $product;
    }

    private static function setSecureAreaIfNeeded()
    {
        $registry = Bootstrap::getObjectManager()->get("\Magento\Framework\Registry");
        if ($registry->registry('isSecureArea') === null) {
            $registry->register('isSecureArea', true);
        }
    }

    private static function isMagentoIntegrationMode()
    {
        return class_exists('\Magento\TestFramework\Helper\Bootstrap');
    }

    public static function cleanupSharedFixtures($objects)
    {
        // don't need to clean up on unit test mode
        if (!self::isMagentoIntegrationMode()) {
            return;
        }
        session_unset();
        self::setSecureAreaIfNeeded();
        foreach ($objects as $object) {
            switch (get_class($object)) {
                case "Magento\Catalog\Model\Product\Interceptor":
                    $object->delete();
                    break;
                default:
                    throw new \Exception("Unexpected type for delete:".get_class($object));
            }
        }
    }
}
