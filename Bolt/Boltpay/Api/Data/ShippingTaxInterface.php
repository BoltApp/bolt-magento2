<?php
/**
 * Copyright © 2013-2017 Bold, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */


namespace Bolt\Boltpay\Api\Data;


/**
 * Shipping tax interface. Tax field of the Shipping and Tax response object.
 *
 * @api
 */
interface ShippingTaxInterface {

	/**
	 * Get shipping service.
	 *
	 * @api
	 * @return int
	 */
	public function getAmount();

	/**
	 * Set shipping service.
	 *
	 * @api
	 * @param int $amount
	 * @return $this
	 */
	public function setAmount($amount);
}
