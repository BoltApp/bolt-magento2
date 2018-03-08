<?php
/**
 * Copyright © 2013-2017 Bold, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */


namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Api\Data\ShippingOptionInterface;

/**
 * Class ShippingOption. Represents a single shipping option object.
 *
 * @package Bolt\Boltpay\Model\Api\Data
 */
class ShippingOption implements ShippingOptionInterface
{
	/**
	 * @var string
	 */
	protected $service;

	/**
	 * @var int
	 */
	protected $cost;


	/**
	 * @var string
	 */
	protected $carrier;

	/**
	 * @var int
	 */
	protected $tax_amount;

	/**
	 * Get shipping service.
	 *
	 * @api
	 * @return string
	 */
	public function getService() {
		return $this->service;
	}

	/**
	 * Set shipping service.
	 *
	 * @api
	 * @param string $service
	 *
	 * @return $this
	 */
	public function setService( $service ) {
		$this->service = $service;
		return $this;
	}

	/**
	 * Get shipping cost.
	 *
	 * @api
	 * @return int
	 */
	public function getCost() {
		return $this->cost;
	}

	/**
	 * Set shipping cost.
	 *
	 * @api
	 * @param int $cost
	 *
	 * @return $this
	 */
	public function setCost( $cost ) {
		$this->cost = $cost;
		return $this;
	}

	/**
	 * Get shipping carrier.
	 *
	 * @api
	 * @return string
	 */
	public function getCarrier() {
		return $this->carrier;
	}

	/**
	 * Set shipping carrier.
	 *
	 * @api
	 * @param $carrier
	 *
	 * @return $this
	 */
	public function setCarrier( $carrier ) {
		$this->carrier = $carrier;
		return $this;
	}

	/**
	 * Get shipping tax.
	 *
	 * @api
	 * @return int
	 */
	public function getTaxAmount() {
		return $this->tax_amount;
	}

	/**
	 * Set shipping tax.
	 *
	 * @api
	 * @param $tax_amount
	 *
	 * @return $this
	 */
	public function setTaxAmount( $tax_amount ) {
		$this->tax_amount = $tax_amount;
		return $this;
}}
