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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\Rossignol\Project;

use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Bugsnag as BugsnagHelper;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Catalog\Model\ProductRepository;

/**
 * Class Core
 *
 * @package Bolt\Boltpay\ThirdPartyModules\Rossignol
 */
class Core
{    
    /**
     * @var BugsnagHelper
     */
    private $bugsnagHelper;
    
    /**
     * @var AttributeSetRepositoryInterface
     */
    private $attributeSetRepository;

    /**
     * Core constructor.
     *
     * @param AttributeSetRepositoryInterface $attributeSetRepository
     * @param BugsnagHelper $bugsnagHelper
     */
    public function __construct(
        AttributeSetRepositoryInterface $attributeSetRepository,
        BugsnagHelper $bugsnagHelper
    ) {
        $this->attributeSetRepository = $attributeSetRepository;
        $this->bugsnagHelper = $bugsnagHelper;
    }
    
    /**
     * @param bool $result
     * @param \Project\Core\Helper\Sales $projectSalesHelper
     * @param \Magento\Framework\View\Element\Template $block
     * @param \Magento\Quote\Model\Quote $quote
     * @return bool
     */
    public function filterShouldDisableBoltCheckout(
        $result,
        $projectSalesHelper,
        $synoliaConfigHelper,
        $block
    ) {
        try {
            if (method_exists($block,'getCheckoutSession')) {
                $quote = $block->getCheckoutSession()->getQuote();
                $storeCode = $quote->getStore()->getCode();
                $rev = $synoliaConfigHelper->getHandlesByEntityType('catalog_product', $storeCode);
                $apparelItemGroups = [];
                $skiItemGroups = [];
                if (isset($rev['catalog_product_view_apparel'])) {
                    $catalogProductViewApparelReverse = array_reverse($rev['catalog_product_view_apparel']);
                    $catalogProductViewApparel = array_pop($catalogProductViewApparelReverse);
                    $apparelItemGroups = $catalogProductViewApparel['attribute_set_id']['value'];
                }
                if (isset($rev['catalog_product_view_ski'])) {
                    $catalogProductViewSkiReverse = array_reverse($rev['catalog_product_view_ski']);
                    $catalogProductViewSki = array_pop($catalogProductViewSkiReverse);
                    $skiItemGroups = $catalogProductViewSki['attribute_set_id']['value'];
                }
                $disableTemplateList = ['Bolt_Boltpay::button_product_page.phtml'];
                if (!$result
                    && 'catalog_product_view' === $block->getRequest()->getFullActionName()
                    && in_array($block->getTemplate(), $disableTemplateList)) {
                    $product = $block->getProduct();
                    $attributeSetId = $product->getAttributeSetId();
                    if (in_array($attributeSetId, explode(',', $skiItemGroups))) {
                        $isKit = $projectSalesHelper->isProductWithKit($product);
                        $canSell = $projectSalesHelper->isEcommFeaturesEnabled() && ($isKit || $projectSalesHelper->canSell($product));
                    } elseif (in_array($attributeSetId, explode(',', $apparelItemGroups))) {
                        $canSell = $projectSalesHelper->canSell($product);
                    } else {
                        $canSell = $product->isSaleable();
                    }
    
                    $result = !$canSell;
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
    }
}
