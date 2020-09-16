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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Api\Data\CartDataInterface;

class CartData implements CartDataInterface
{
    /**
     * @var string
     */
    private $displayId;

    /**
     * @var string
     */
    private $currency;

    /**
     * @var array
     */
    private $items;

    /**
     * @var array
     */
    private $discounts;
    
    /**
     * @var int
     */
    private $totalAmount;
    
    /**
     * @var int
     */
    private $taxAmount;
    
    /**
     * @var string
     */
    private $orderReference;
    
    /**
     * @var array
     */
    private $shipments;
    
    /**
     * Get display id.
     *
     * @api
     * @return string
     */
    public function getDisplayId()
    {
        return $this->displayId;
    }

    /**
     * Set display id.
     *
     * @api
     * @param $displayId
     *
     * @return $this
     */
    public function setDisplayId($displayId)
    {
        $this->displayId = $displayId;
        return $this;
    }
    
    /**
     * Get currency.
     *
     * @api
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set currency.
     *
     * @api
     * @param $currency
     *
     * @return $this
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }
    
    /**
     * Get items.
     *
     * @api
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Set items.
     *
     * @api
     * @param $items
     *
     * @return $this
     */
    public function setItems($items)
    {
        $this->items = $items;
        return $this;
    }
    
    /**
     * Get discounts.
     *
     * @api
     * @return array
     */
    public function getDiscounts()
    {
        return $this->discounts;
    }

    /**
     * Set discounts.
     *
     * @api
     * @param $discounts
     *
     * @return $this
     */
    public function setDiscounts($discounts)
    {
        $this->discounts = $discounts;
        return $this;
    }
    
    /**
     * Get total amount.
     *
     * @api
     * @return int
     */
    public function getTotalAmount()
    {
        return $this->totalAmount;
    }

    /**
     * Set total amount.
     *
     * @api
     * @param $totalAmount
     *
     * @return $this
     */
    public function setTotalAmount($totalAmount)
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }
    
    /**
     * Get tax amount.
     *
     * @api
     * @return int
     */
    public function getTaxAmount()
    {
        return $this->taxAmount;
    }

    /**
     * Set tax amount.
     *
     * @api
     * @param $taxAmount
     *
     * @return $this
     */
    public function setTaxAmount($taxAmount)
    {
        $this->taxAmount = $taxAmount;
        return $this;
    }
    
    /**
     * Get order reference.
     *
     * @api
     * @return string
     */
    public function getOrderReference()
    {
        return $this->orderReference;
    }

    /**
     * Set order reference.
     *
     * @api
     * @param $orderReference
     *
     * @return $this
     */
    public function setOrderReference($orderReference)
    {
        $this->orderReference = $orderReference;
        return $this;
    }
    
    /**
     * Get shipments.
     *
     * @api
     * @return array
     */
    public function getShipments()
    {
        return $this->shipments;
    }

    /**
     * Set shipments.
     *
     * @api
     * @param $shipments
     *
     * @return $this
     */
    public function setShipments($shipments)
    {
        $this->shipments = $shipments;
        return $this;
    }

    /**
     * Get cart data.
     *
     * @api
     * @return array
     */
    public function getCartData()
    {
        return [
            'display_id' => $this->displayId,
            'currency' => $this->currency,
            'items' => $this->items,
            'discounts' => $this->discounts,
            'shipments' => $this->shipments,
            'total_amount' => $this->totalAmount,
            'tax_amount' => $this->taxAmount,
            'order_reference' => $this->orderReference,
        ];
    }
}
