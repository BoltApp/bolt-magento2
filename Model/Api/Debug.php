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

use Bolt\Boltpay\Api\DebugInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Bolt\Boltpay\Model\Api\Data\DebugInfoFactory;
use Bolt\Boltpay\Model\Api\Data\BoltConfigSettingFactory;
use Bolt\Boltpay\Model\Api\Data\PluginVersionFactory;

class Debug implements DebugInterface
{
	/**
	 * @var DebugInfoFactory
	 */
	private $debugInfoFactory;

	/**
	 * @var BoltConfigSettingFactory
	 */
	private $boltConfigSettingFactory;

	/**
	 * @var PluginVersionFactory
	 */
	private $pluginVersionFactory;

	/**
	 * @var ProductMetadataInterface
	 */
	private $productMetadata;

	/**
	 * @param DebugInfoFactory $debugInfoFactory
	 * @param BoltConfigSettingFactory $boltConfigSettingFactory
	 * @param PluginVersionFactory $pluginVersionFactory
	 * @param ProductMetadataInterface $productMetadata
	 */
	public function __construct(
		DebugInfoFactory $debugInfoFactory,
		BoltConfigSettingFactory $boltConfigSettingFactory,
		PluginVersionFactory $pluginVersionFactory,
		ProductMetadataInterface $productMetadata
	) {
		$this->debugInfoFactory = $debugInfoFactory;
		$this->boltConfigSettingFactory = $boltConfigSettingFactory;
		$this->pluginVersionFactory = $pluginVersionFactory;
		$this->productMetadata = $productMetadata;
	}

	/**
	 * This request handler will return relevant information for Bolt for debugging purpose.
	 *
	 * @return DebugInfo
	 * @api
	 */
	public function debug()
	{
		$result = $this->debugInfoFactory->create();

		# populate php version
		$result->setPhpVersion(PHP_VERSION);

		# populate platform version
		$result->setPlatformVersion($this->productMetadata->getVersion());

		$boltSettings = [];
		$boltSettings[] = $this->boltConfigSettingFactory->create();
		$result->setBoltConfigSettings($boltSettings);

		$otherPluginVersions = [];
		$otherPluginVersions[] = $this->pluginVersionFactory->create();
		$result->setOtherPluginVersions($otherPluginVersions);

		return $result;
	}
}