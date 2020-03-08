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

	/**
	 * @return \Bolt\Boltpay\Api\Data\PluginVersionInterface[]
	 */
	public function getOtherPluginVersions();

	/**
	 * @param \Bolt\Boltpay\Api\Data\PluginVersionInterface[]
	 * @return $this
	 */
	public function setOtherPluginVersions($otherPluginVersions);
}