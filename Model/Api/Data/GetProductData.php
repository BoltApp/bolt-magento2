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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Api\Data\GetProductDataInterface;
use Bolt\Boltpay\Api\Data\ProductInventoryInfoInterface;

/**
 * Class GetProductData. Represents a product info object containing products info and stock.
 *
 * @package Bolt\Boltpay\Model\Api\Data
 */
class GetProductData implements GetProductDataInterface, \JsonSerializable
{
    /**
     * @var ProductInventoryInfoInterface
     */
    private $product;

    /**
     * @var ProductInventoryInfoInterface[]
     */
    private $children;

    /**
     * @var ProductInventoryInfoInterface
     */
    private $parent;

    /**
     * @var array
     */
    private $options;
    
    /**
     * @var string
     */
    private $baseImageUrl;

    /**
     * @var integer
     */
    private $storeID;
    
    /**
     * @var array
     */
    private $bundleOptions;


    /**
     * Get product info.
     *
     * @api
     * @return ProductInventoryInfoInterface
     */
    public function getProductInventory()
    {
        return $this->product;
    }

    /**
     * Set product info.
     *
     * @api
     * @param ProductInventoryInfoInterface $product
     *
     * @return $this
     */
    public function setProductInventory($product)
    {
        $this->product = $product;
        return $this;
    }

    /**
     * Get parent info.
     *
     * @api
     * @return ProductInventoryInfoInterface
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Set parent info.
     *
     * @api
     * @param ProductInventoryInfoInterface $parent
     *
     * @return $this
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
        return $this;
    }


    /**
     * Get children info.
     *
     * @api
     * @return ProductInventoryInfoInterface[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Set children info.
     *
     * @api
     * @param ProductInventoryInfoInterface[] $children
     *
     * @return $this
     */
    public function setChildren($children)
    {
        $this->children = $children;
        return $this;
    }

    /**
     * Get children info.
     *
     * @api
     * @return string
     */
    public function getBaseImageUrl()
    {
        return $this->baseImageUrl;
    }

    /**
     * Set children info.
     *
     * @param string $baseImageUrl
     *
     * @return $this
     * @api
     */
    public function setBaseImageUrl($baseImageUrl)
    {
        $this->baseImageUrl = $baseImageUrl;
        return $this;
    }

    /**
     * Get store ID.
     *
     * @api
     * @return integer
     */
    public function getStoreID(){
        return $this->storeID;
    }

    /**
     * Set store ID.
     *
     * @api
     * @param integer $storeID
     *
     * @return $this
     */
    public function setStoreID($storeID){
        $this->storeID = $storeID;
        return $this;
    }


    /**
     * Get children info.
     *
     * @api
     * @return \Magento\ConfigurableProduct\Api\Data\OptionInterface[]
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Set children info.
     *
     * @api
     * @param \Magento\ConfigurableProduct\Api\Data\OptionInterface[] $options
     *
     * @return $this
     */
    public function setOptions($options)
    {
        $this->options = $options;
        return $this;
    }
    
    /**
     * Get bundle options of bundle product.
     *
     * @api
     * @return \Magento\Bundle\Api\Data\OptionInterface[]
     */
    public function getBundleOptions()
    {
        return $this->bundleOptions;
    }

    /**
     * set bundle options of bundle product.
     *
     * @api
     * @param \Magento\Bundle\Api\Data\OptionInterface[] $options
     *
     * @return $this
     */
    public function setBundleOptions($options)
    {
        $this->bundleOptions = $options;
        return $this;
    }


    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'product' => $this->product,
            'children' => $this->children,
            'parent' => $this->parent,
            'options' => $this->options,
            'baseImageUrl' => $this->baseImageUrl,
            'storeID' => $this->storeID,
            'bundleOptions' => $this->bundleOptions
        ];
    }
}
