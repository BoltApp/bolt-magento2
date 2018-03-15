<?php
/**
 * Copyright © 2013-2017 Bold, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */


namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Api\Data\ShippingOptionsInterface;
use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Bolt\Boltpay\Api\Data\ShippingTaxInterface;

/**
 * Class ShippingOptions. Shipping options property of the Shipping and Tax.
 *
 * @package Bolt\Boltpay\Model\Api\Data
 */
class ShippingOptions implements ShippingOptionsInterface
{
	/**
	 * @var array
	 */
	protected $shippingOptions = [];

	/**
	 * @var ShippingTaxInterface
	 */
	protected $taxResult;

	/**
     * Get all available shipping options.
     *
     * @api
     * @return ShippingOptionInterface[]
     */
    public function getShippingOptions()
    {
	    return $this->shippingOptions;
    }

    /**
     * Set available shipping options.
     *
     * @api
     * @param ShippingOptionInterface[]
     * @return $this
     */
    public function setShippingOptions($shippingOptions)
    {
        $this->shippingOptions = $shippingOptions;
        return $this;
    }

	/**
	 * Get order tax result.
	 *
	 * @api
	 * @return ShippingTaxInterface
	 */
	public function getTaxResult() {
		return $this->taxResult;
	}

	/**
	 * Set available shipping options.
	 *
	 * @api
	 * @param ShippingTaxInterface
	 *
	 * @return $this
	 */
	public function setTaxResult( $taxResult ) {
		$this->taxResult = $taxResult;
		return $this;
	}
}
