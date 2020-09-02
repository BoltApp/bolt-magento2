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

namespace Bolt\Boltpay\Api\Data;

/**
 * Cart data interface.
 *
 * @api
 */
interface CartDataInterface
{
    /**
     * Get display id.
     *
     * @api
     * @return string
     */
    public function getDisplayId();

    /**
     * Set display id.
     *
     * @api
     * @param $displayId
     *
     * @return $this
     */
    public function setDisplayId($displayId);
    
    /**
     * Get currency.
     *
     * @api
     * @return string
     */
    public function getCurrency();

    /**
     * Set currency.
     *
     * @api
     * @param $currency
     *
     * @return $this
     */
    public function setCurrency($currency);
    
    /**
     * Get items.
     *
     * @api
     * @return array
     */
    public function getItems();

    /**
     * Set items.
     *
     * @api
     * @param $items
     *
     * @return $this
     */
    public function setItems($items);
    
    /**
     * Get discounts.
     *
     * @api
     * @return array
     */
    public function getDiscounts();

    /**
     * Set discounts.
     *
     * @api
     * @param $discounts
     *
     * @return $this
     */
    public function setDiscounts($discounts);
    
    /**
     * Get total amount.
     *
     * @api
     * @return int
     */
    public function getTotalAmount();

    /**
     * Set total amount.
     *
     * @api
     * @param $totalAmount
     *
     * @return $this
     */
    public function setTotalAmount($totalAmount);
    
    /**
     * Get tax amount.
     *
     * @api
     * @return int
     */
    public function getTaxAmount();

    /**
     * Set tax amount.
     *
     * @api
     * @param $taxAmount
     *
     * @return $this
     */
    public function setTaxAmount($taxAmount);
    
    /**
     * Get order reference.
     *
     * @api
     * @return string
     */
    public function getOrderReference();

    /**
     * Set order reference.
     *
     * @api
     * @param $orderReference
     *
     * @return $this
     */
    public function setOrderReference($orderReference);
    
    /**
     * Get shipments.
     *
     * @api
     * @return array
     */
    public function getShipments();

    /**
     * Set shipments.
     *
     * @api
     * @param $shipments
     *
     * @return $this
     */
    public function setShipments($shipments);
    
    /**
     * Get cart data.
     *
     * @api
     * @return array
     */
    public function getCartData();
}
