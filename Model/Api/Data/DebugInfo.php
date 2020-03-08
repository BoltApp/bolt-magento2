<?php

namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Api\Data\DebugInfoInterface;

class DebugInfo implements DebugInfoInterface {

	/**
	 * @var string
	 */
	private $phpVersion;

	/**
	 * @var string
	 */
	private $platformVersion;

	/**
	 * @return string
	 */
	public function getPhpVersion() {
		return $this->phpVersion;
	}

	/**
	 * @param string $phpVersion
	 * @return $this
	 */
	public function setPhpVersion($phpVersion){
		$this->phpVersion = $phpVersion;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getPlatformVersion(){
		return $this->platformVersion;
	}

	/**
	 * @param string $platformVersion
	 * @return $this
	 */
	public function setPlatformVersion($platformVersion){
		$this->platformVersion = $platformVersion;
		return $this;
	}
}