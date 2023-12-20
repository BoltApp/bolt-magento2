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

use Bolt\Boltpay\Api\Data\TaxDataInterface;
use Bolt\Boltpay\Api\Data\TaxResultInterface;
use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Bolt\Boltpay\Api\Data\ShipToStoreOptionInterface;

/**
 * Class TaxData. Tax result and Shipping option properties of Tax.
 *
 * @package Bolt\Boltpay\Model\Api\Data
 */
class TaxData implements TaxDataInterface, \JsonSerializable
{
    /**
     * @var TaxResultInterface
     */
    private $taxResult;

    /**
     * @var ShippingOptionInterface
     */
    private $shippingOption;
    
    /**
     * @var ShipToStoreOptionInterface
     */
    private $shipToStoreOption;

    /**
     * Get order tax result.
     *
     * @api
     * @return TaxResultInterface
     */
    public function getTaxResult()
    {
        return $this->taxResult;
    }

    /**
     * Set available shipping options.
     *
     * @api
     * @param TaxResultInterface $taxResult
     *
     * @return $this
     */
    public function setTaxResult($taxResult)
    {
        $this->taxResult = $taxResult;
        return $this;
    }

    /**
     * Get selected shipping option.
     *
     * @api
     * @return ShippingOptionInterface
     */
    public function getShippingOption()
    {
        return $this->shippingOption;
    }

    /**
     * Set selected shipping option.
     *
     * @api
     * @param ShippingOptionInterface $shippingOption
     * @return $this
     */
    public function setShippingOption($shippingOption)
    {
        $this->shippingOption = $shippingOption;
        return $this;
    }
    
    /**
     * Get all available ship to store option.
     *
     * @api
     * @return \Bolt\Boltpay\Api\Data\ShipToStoreOptionInterface
     */
    public function getShipToStoreOption()
    {
        return $this->shipToStoreOption;
    }

    /**
     * Set available ship to store option.
     *
     * @api
     * @param \Bolt\Boltpay\Api\Data\ShipToStoreOptionInterface $shipToStoreOption
     * @return $this
     */
    public function setShipToStoreOption($shipToStoreOption)
    {
        $this->shipToStoreOption = $shipToStoreOption;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'tax_result' => $this->taxResult,
            'shipping_option' => $this->shippingOption,
            'ship_to_store_option' => $this->shipToStoreOption
        ];
    }
}
