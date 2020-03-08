<?php

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\Data\DebugInfoInterface;
use Bolt\Boltpay\Api\DebugInterface;
use Bolt\Boltpay\Api\Data\DebugInfoInterfaceFactory;

class Debug implements DebugInterface {

	/**
	 * @var DebugInfoInterfaceFactory
	 */
	private $debugInfoInterfaceFactory;

	/**
	 * @param DebugInfoInterfaceFactory $debugInfoInterfaceFactory
	 */
	public function __construct(DebugInfoInterfaceFactory $debugInfoInterfaceFactory) {
		$this->debugInfoInterfaceFactory = $debugInfoInterfaceFactory;
	}

	/**
	 * This request handler will return relevant information for Bolt for debugging purpose.
	 *
	 * @api
	 * @return DebugInfoInterface
	 */
	public function debug() {
		$result = $this->debugInfoInterfaceFactory->create();
		$result->setPhpVersion("todo");
		$result->setPlatformVersion("todo");
		return $result;
	}
}