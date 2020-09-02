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
use Bolt\Boltpay\Api\Data\UpdateCartResultInterface;

/**
 * Class UpdateCartResult.
 * 
 * @package Bolt\Boltpay\Model\Api\Data
 */
class UpdateCartResult implements UpdateCartResultInterface
{
    /**
     * @var string
     */
    private $status;
    
    /**
     * @var \Bolt\Boltpay\Api\Data\CartDataInterface
     */
    private $orderCreate;
    
    /**
     * @var string
     */
    private $orderReference;


    /**
     * Get cart data.
     *
     * @api
     * @return \Bolt\Boltpay\Api\Data\CartDataInterface
     */
    public function getOrderCreate()
    {
        return $this->orderCreate;
    }

    /**
     * Set cart data.
     *
     * @api
     * @param \Bolt\Boltpay\Api\Data\CartDataInterface $orderCreate
     *
     * @return $this
     */
    public function setOrderCreate($orderCreate)
    {
        $this->orderCreate = $orderCreate;
        return $this;
    }
    
    /**
     * Get status.
     *
     * @api
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set status.
     *
     * @api
     * @param string $status
     *
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;
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
     * @param string $orderReference
     * @return $this
     */
    public function setOrderReference($orderReference)
    {
        $this->orderReference = $orderReference;
        return $this;
    }
    
    /**
     * Get result.
     *
     * @api
     * @return array
     */
    public function getCartResult()
    {
        return [
            'status' => $this->status,
            'order_reference' => $this->orderReference,
            'order_create' => [
                'cart' => $this->orderCreate->getCartData()
            ]
        ];
    }
}
