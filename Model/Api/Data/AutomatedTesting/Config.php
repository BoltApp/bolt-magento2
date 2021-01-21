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

namespace Bolt\Boltpay\Model\Api\Data\AutomatedTesting;

class Config implements \JsonSerializable
{
    /**
     * @var StoreItem[]
     */
    private $storeItems;

    /**
     * @var Cart
     */
    private $cart;

    /**
     * @return StoreItem[]
     */
    public function getStoreItems()
    {
        return $this->storeItems;
    }

    /**
     * @param StoreItem[] $storeItems
     * @return $this
     */
    public function setStoreItems($storeItems)
    {
        $this->storeItems = $storeItems;
        return $this;
    }

    /**
     * @return Cart
     */
    public function getCart()
    {
        return $this->cart;
    }

    /**
     * @param Cart $cart
     * @return $this
     */
    public function setCart($cart)
    {
        $this->cart = $cart;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'storeItems' => $this->storeItems,
            'cart' => $this->cart
        ];
    }
}
