<?php
/**
 * Bolt magento2 plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api\Data;

class DebugInfo
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
	 * @var BoltConfigSetting[]
	 */
	private $boltConfigSettings;

	/**
	 * @var PluginVersion[]
	 */
	private $otherPluginVersions;

    /**
     * @var string
     */
    private $logInfo;

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
	 * @return \Bolt\Boltpay\Model\Api\Data\BoltConfigSetting[]
	 */
	public function getBoltConfigSettings(){
		return $this->boltConfigSettings;
	}

	/**
	 * @param \Bolt\Boltpay\Model\Api\Data\BoltConfigSetting[]
	 * @return $this
	 */
	public function setBoltConfigSettings($boltConfigSettings)
	{
		$this->boltConfigSettings = $boltConfigSettings;
		return $this;
	}

	/**
	 * @return \Bolt\Boltpay\Model\Api\Data\PluginVersion[]
	 */
	public function getOtherPluginVersions(){
		return $this->otherPluginVersions;
	}

	/**
	 * @param \Bolt\Boltpay\Model\Api\Data\PluginVersion[]
	 * @return $this
	 */
	public function setOtherPluginVersions($otherPluginVersions)
	{
		$this->otherPluginVersions = $otherPluginVersions;
		return $this;
	}

    /**
     * @param string $logInfo
     * @return $this
     */

	public function setLogInfo($logInfo)
    {
        $this->logInfo = $logInfo;
        return $this;
    }
}