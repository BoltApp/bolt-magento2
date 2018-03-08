<?php
/**
 * Copyright © 2013-2017 Bold, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */


namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Api\Data\ShippingTaxInterface;

/**
 * Class ShippingTax. Tax property of the Shipping and Tax.
 * @package Bolt\Boltpay\Model\Api\Data
 */
class ShippingTax implements ShippingTaxInterface
{
	/**
	 * @var int
	 */
	protected $amount;

	/**
	 * Get tax amount.
	 *
	 * @api
	 * @return int
	 */
	public function getAmount() {
		return $this->amount;
	}

	/**
	 * Set tax amount.
	 *
	 * @api
	 * @param int $amount
	 *
	 * @return $this
	 */
	public function setAmount( $amount ) {
		$this->amount = $amount;
		return $this;
	}
}
