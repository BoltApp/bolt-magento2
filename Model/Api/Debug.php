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
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Model\Api\Data\BoltConfigSettingFactory;
use Bolt\Boltpay\Model\Api\Data\DebugInfoFactory;
use Bolt\Boltpay\Model\Api\Data\PluginVersionFactory;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Store\Model\StoreManagerInterface;

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
	 * @var HookHelper
	 */
	private $hookHelper;

	/**
	 * @var StoreManagerInterface
	 */
	private $storeManager;

	/**
	 * @var ProductMetadataInterface
	 */
	private $productMetadata;

	/**
	 * @var ConfigHelper
	 */
	private $configHelper;

	/**
	 * @param DebugInfoFactory $debugInfoFactory
	 * @param BoltConfigSettingFactory $boltConfigSettingFactory
	 * @param PluginVersionFactory $pluginVersionFactory
	 * @param StoreManagerInterface $storeManager
	 * @param HookHelper $hookHelper
	 * @param ProductMetadataInterface $productMetadata
	 * @param ConfigHelper $configHelper
	 */
	public function __construct(
		DebugInfoFactory $debugInfoFactory,
		BoltConfigSettingFactory $boltConfigSettingFactory,
		PluginVersionFactory $pluginVersionFactory,
		StoreManagerInterface $storeManager,
		HookHelper $hookHelper,
		ProductMetadataInterface $productMetadata,
		ConfigHelper $configHelper
	) {
		$this->debugInfoFactory = $debugInfoFactory;
		$this->boltConfigSettingFactory = $boltConfigSettingFactory;
		$this->pluginVersionFactory = $pluginVersionFactory;
		$this->storeManager = $storeManager;
		$this->hookHelper = $hookHelper;
		$this->productMetadata = $productMetadata;
		$this->configHelper = $configHelper;
	}

	/**
	 * This request handler will return relevant information for Bolt for debugging purpose.
	 *
	 * @return DebugInfo
	 * @api
	 */
	public function debug()
	{
		# verify request
		$this->hookHelper->preProcessWebhook($this->storeManager->getStore()->getId());

		$result = $this->debugInfoFactory->create();

		# populate php version
		$result->setPhpVersion(PHP_VERSION);

		# populate platform version
		$result->setPlatformVersion($this->productMetadata->getVersion());

		# populate bolt config settings
		$result->setBoltConfigSettings($this->configHelper->getAllConfigSettings());

		$otherPluginVersions = [];
		$otherPluginVersions[] = $this->pluginVersionFactory->create();
		$result->setOtherPluginVersions($otherPluginVersions);

		return $result;
	}
}