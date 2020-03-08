<?php

namespace Bolt\Boltpay\Api\Data;

interface DebugInfoInterface {
	/**
	 * @return string
	 */
	public function getPhpVersion();

	/**
	 * @param string $phpVersion
	 * @return $this
	 */
	public function setPhpVersion($phpVersion);

	/**
	 * @return string
	 */
	public function getPlatformVersion();

	/**
	 * @param string $platformVersion
	 * @return $this
	 */
	public function setPlatformVersion($platformVersion);
}