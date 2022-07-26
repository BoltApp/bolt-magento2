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
 * Class Rossignol
 *
 * @package Bolt\Boltpay\ThirdPartyModules\Rossignol
 */
class Rossignol
{

    /**
     * @var AttributeSetRepositoryInterface
     */
    private $attributeSetRepository;
    
    /**
     * @var ConfigHelper
     */
    private $configHelper;
    
    /**
     * @var CartHelper
     */
    private $cartHelper;
    
    /**
     * @var ProductRepository
     */
    private $productRepository;
    
    /**
     * @var BugsnagHelper
     */
    private $bugsnagHelper;

    /**
     * Rossignol constructor.
     *
     * @param AttributeSetRepositoryInterface $attributeSetRepository
     * @param ConfigHelper $configHelper
     * @param ProductRepository $productRepository
     * @param CartHelper $cartHelper
     * @param BugsnagHelper $bugsnagHelper
     */
    public function __construct(
        AttributeSetRepositoryInterface $attributeSetRepository,
        ConfigHelper $configHelper,
        ProductRepository $productRepository,
        CartHelper $cartHelper,
        BugsnagHelper $bugsnagHelper
    ) {
        $this->attributeSetRepository = $attributeSetRepository;
        $this->configHelper = $configHelper;
        $this->productRepository = $productRepository;
        $this->cartHelper = $cartHelper;
        $this->bugsnagHelper = $bugsnagHelper;
    }

    /**
     * @param bool $result
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @return bool
     */
    public function filterPPCSupportableType(
        $result,
        $product,
        $storeId
    ) {
        try {
            $itemGroups = $this->configHelper->getRossignolExcludeItemAttributesFromPPCConfig($storeId);
            if (!empty($itemGroups)) {
                $attributeSet = $this->attributeSetRepository->get($product->getAttributeSetId());
                if (in_array($attributeSet->getAttributeSetName(), $itemGroups)) {
                    $result = false;
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
    }
    
    /**
     * @param string $result
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @return string
     */
    public function filterCartItemShipmentType(
        $result,
        $product,
        $storeId
    ) {
        try {
            if ($result !== 'ship_to_store') {
                $itemGroups = $this->configHelper->getRossignolExcludeItemAttributesFromPPCConfig($storeId);
                if (!empty($itemGroups)) {
                    $attributeSet = $this->attributeSetRepository->get($product->getAttributeSetId());
                    if (in_array($attributeSet->getAttributeSetName(), $itemGroups)) {
                        $result = 'ship_to_store';
                    }
                }    
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
    }
    
    /**
     * @param array $result
     * @param \Magento\Quote\Model\Quote $quote
     * @return array
     */
    public function filterCartAndHints(
        $result,
        $quote
    ) {
        try {
            $storeId = $quote->getStoreId();
            //$itemGroups = $this->configHelper->getRossignolExcludeItemAttributesFromPPCConfig($storeId);
            $itemGroups = [];
            if (!empty($itemGroups) && !empty($result['cart']['items'])) {
                foreach ($result['cart']['items'] as $item) {
                    $product = $this->productRepository->get($item['sku']);
                    $attributeSet = $this->attributeSetRepository->get($product->getAttributeSetId());
                    if (in_array($attributeSet->getAttributeSetName(), $itemGroups)) {
                        $result['restrict'] = true;
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        } 
    }
    
    /**
     * @param string $result
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @return string
     */
    public function filterAdditionalCheckJavascriptForPPC(
        $result,
        $product,
        $storeId
    ) {
        return $result.= 'var boltCart = customerData.get("boltcart")();
                    if (boltCart && boltCart.status && boltCart.restrict) {
                        $("#product-addtocart-button").click();
                        onElementReady(".modal-popup.ajaxcart-modal .modal-inner-wrap .modal-footer button", function(element) {
                            element.click();
                        });
                        return false;
                    }';
    }
    
    /**
     * @param string $result
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @return string
     */
    public function filterShouldDisableBoltCheckout(
        $result,
        $block,
        $quote
    ) {
        try {
            $disableTemplateList = ['Bolt_Boltpay::button.phtml', 'Bolt_Boltpay::js/replacejs.phtml', 'Bolt_Boltpay::css/boltcss.phtml', 'Bolt_Boltpay::js/boltglobaljs.phtml'];
            if (!$result
                && 'checkout_index_index' === $block->getRequest()->getFullActionName()
                && in_array($block->getTemplate(), $disableTemplateList)) {
                $storeId = $block->getStoreId();
                $itemGroups = $this->configHelper->getRossignolExcludeItemAttributesFromPPCConfig($storeId);           
                list ($quoteItems, ,) = $this->cartHelper->getCartItems($quote, $storeId);    
                foreach ($quoteItems as $item) {
                    $product = $this->productRepository->get($item['sku']);
                    $attributeSet = $this->attributeSetRepository->get($product->getAttributeSetId());
                    if (in_array($attributeSet->getAttributeSetName(), $itemGroups)) {
                        $result = true;
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
    }
}
