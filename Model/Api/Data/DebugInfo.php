<?php

namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Api\Data\DebugInfoInterface;
use Bolt\Boltpay\Api\Data\PluginVersionInterface;
use Bolt\Boltpay\Api\Data\BoltConfigSettingInterface;


class DebugInfo implements DebugInfoInterface
{
	/**
	 * @var string
	 */
	private $phpVersion;

	/**
	 * @var string
	 */
	private $platformVersion;

	/**
	 * @var BoltConfigSettingInterface[]
	 */
	private $boltConfigSettings;

	/**
	 * @var PluginVersionInterface[]
	 */
	private $otherPluginVersions;

	/**
	 * @return string
	 */
	public function getPhpVersion()
	{
		return $this->phpVersion;
	}

	/**
	 * @param string $phpVersion
	 * @return $this
	 */
	public function setPhpVersion($phpVersion)
	{
		$this->phpVersion = $phpVersion;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getPlatformVersion()
	{
		return $this->platformVersion;
	}

	/**
	 * @param string $platformVersion
	 * @return $this
	 */
	public function setPlatformVersion($platformVersion)
	{
		$this->platformVersion = $platformVersion;
		return $this;
	}

	/**
	 * @return BoltConfigSettingInterface[]
	 */
	public function getBoltConfigSettings(){
		return $this->boltConfigSettings;
	}

	/**
	 * @param BoltConfigSettingInterface[]
	 * @return $this
	 */
	public function setBoltConfigSettings($boltConfigSettings)
	{
		$this->boltConfigSettings = $boltConfigSettings;
		return $this;
	}

	/**
	 * @return PluginVersionInterface[]
	 */
	public function getOtherPluginVersions(){
		return $this->otherPluginVersions;
	}

	/**
	 * @param PluginVersionInterface[]
	 * @return $this
	 */
	public function setOtherPluginVersions($otherPluginVersions)
	{
		$this->otherPluginVersions = $otherPluginVersions;
		return $this;
	}
}