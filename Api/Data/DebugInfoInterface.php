<?php

namespace Bolt\Boltpay\Api\Data;

use Bolt\Boltpay\Model\Api\Data\DebugInfo;

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
	 * @return \Bolt\Boltpay\Api\Data\BoltConfigSettingInterface[]
	 */
	public function getBoltConfigSettings();

	/**
	 * @param \Bolt\Boltpay\Api\Data\BoltConfigSettingInterface[]
	 * @return $this
	 */
	public function setBoltConfigSettings($boltConfigSettings);

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