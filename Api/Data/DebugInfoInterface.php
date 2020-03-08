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

namespace Bolt\Boltpay\Api\Data;

interface DebugInfoInterface
{
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