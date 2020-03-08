<?php

namespace Bolt\Boltpay\Api;

interface DebugInterface {

	/**
	 * @api
	 * @return Bolt\Boltpay\Api\Data\DebugInfoInterface
	 */
	public function debug();
}