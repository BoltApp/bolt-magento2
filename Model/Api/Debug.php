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

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\Data\DebugInfoInterface;
use Bolt\Boltpay\Api\DebugInterface;
use Bolt\Boltpay\Api\Data\DebugInfoInterfaceFactory;
use Bolt\Boltpay\Api\Data\PluginVersionInterfaceFactory;
use Bolt\Boltpay\Api\Data\BoltConfigSettingInterfaceFactory;

class Debug implements DebugInterface
{
	/**
	 * @var DebugInfoInterfaceFactory
	 */
	private $debugInfoInterfaceFactory;

	/**
	 * @var BoltConfigSettingInterfaceFactory
	 */
	private $boltConfigSettingInterfaceFactory;

	/**
	 * @var PluginVersionInterfaceFactory
	 */
	private $pluginVersionInterfaceFactory;

	/**
	 * @param DebugInfoInterfaceFactory $debugInfoInterfaceFactory
	 * @param BoltConfigSettingInterfaceFactory $boltConfigSettingInterfaceFactory
	 * @param PluginVersionInterfaceFactory $pluginVersionInterfaceFactory
	 */
	public function __construct(
		DebugInfoInterfaceFactory $debugInfoInterfaceFactory,
		BoltConfigSettingInterfaceFactory $boltConfigSettingInterfaceFactory,
		PluginVersionInterfaceFactory $pluginVersionInterfaceFactory
	) {
		$this->debugInfoInterfaceFactory = $debugInfoInterfaceFactory;
		$this->boltConfigSettingInterfaceFactory = $boltConfigSettingInterfaceFactory;
		$this->pluginVersionInterfaceFactory = $pluginVersionInterfaceFactory;
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

		$boltSettings = [];
		$boltSettings[] = $this->boltConfigSettingInterfaceFactory->create();
		$result->setBoltConfigSettings($boltSettings);

		$otherPluginVersions = [];
		$otherPluginVersions[] = $this->pluginVersionInterfaceFactory->create();
		$result->setOtherPluginVersions($otherPluginVersions);

		return $result;
	}
}