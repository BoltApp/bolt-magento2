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

namespace Bolt\Boltpay\Model\Api\Data\AutomatedTesting;

class OrderItem implements \JsonSerializable
{
    /**
     * @var string
     */
    private $productName;

    /**
     * @var string
     */
    private $productSku;

    /**
     * @var string
     */
    private $productUrl;

    /**
     * @var string
     */
    private $price;

    /**
     * @var string
     */
    private $quantityOrdered;

    /**
     * @var string
     */
    private $subtotal;

    /**
     * @var string
     */
    private $taxAmount;

    /**
     * @var string
     */
    private $taxPercent;

    /**
     * @var string
     */
    private $discountAmount;

    /**
     * @var string
     */
    private $total;

    /**
     * @return string
     */
    public function getProductName()
    {
        return $this->productName;
    }

    /**
     * @return string
     */
    public function getProductSku()
    {
        return $this->productSku;
    }

    /**
     * @return string
     */
    public function getProductUrl()
    {
        return $this->productUrl;
    }

    /**
     * @return string
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @return string
     */
    public function getQuantityOrdered()
    {
        return $this->quantityOrdered;
    }

    /**
     * @return string
     */
    public function getSubtotal()
    {
        return $this->subtotal;
    }

    /**
     * @return string
     */
    public function getTaxAmount()
    {
        return $this->taxAmount;
    }

    /**
     * @return string
     */
    public function getTaxPercent()
    {
        return $this->taxPercent;
    }

    /**
     * @return string
     */
    public function getDiscountAmount()
    {
        return $this->discountAmount;
    }

    /**
     * @return string
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * @param string $productName
     * @return $this
     */
    public function setProductName($productName)
    {
        $this->productName = $productName;
        return $this;
    }

    /**
     * @param string $productSku
     * @return $this
     */
    public function setProductSku($productSku)
    {
        $this->productSku = $productSku;
        return $this;
    }

    /**
     * @param string $productUrl
     * @return $this
     */
    public function setProductUrl($productUrl)
    {
        $this->productUrl = $productUrl;
        return $this;
    }

    /**
     * @param string $price
     * @return $this
     */
    public function setPrice($price)
    {
        $this->price = $price;
        return $this;
    }

    /**
     * @param string $quantityOrdered
     * @return $this
     */
    public function setQuantityOrdered($quantityOrdered)
    {
        $this->quantityOrdered = $quantityOrdered;
        return $this;
    }

    /**
     * @param string $subtotal
     * @return $this
     */
    public function setSubtotal($subtotal)
    {
        $this->subtotal = $subtotal;
        return $this;
    }

    /**
     * @param string $taxAmount
     * @return $this
     */
    public function setTaxAmount($taxAmount)
    {
        $this->taxAmount = $taxAmount;
        return $this;
    }

    /**
     * @param string $taxPercent
     * @return $this
     */
    public function setTaxPercent($taxPercent)
    {
        $this->taxPercent = $taxPercent;
        return $this;
    }

    /**
     * @param string $discountAmount
     * @return $this
     */
    public function setDiscountAmount($discountAmount)
    {
        $this->discountAmount = $discountAmount;
        return $this;
    }

    /**
     * @param string $total
     * @return $this
     */
    public function setTotal($total)
    {
        $this->total = $total;
        return $this;
    }

    /**
     * @return array|mixed
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}