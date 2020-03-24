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
use Bolt\Boltpay\Model\Api\Data\DebugInfoFactory;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Module\FullModuleList;
use Bolt\Boltpay\Helper\ModuleRetriever;
use Bolt\Boltpay\Helper\LogRetriever;


class Debug implements DebugInterface
{
	/**
	 * @var DebugInfoFactory
	 */
	private $debugInfoFactory;

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
	 * @var ModuleRetriever
	 */
	private $moduleRetriever;

    /**
     * @var LogRetriever
     */
    private $logRetriever;

	/**
	 * @param DebugInfoFactory $debugInfoFactory
	 * @param StoreManagerInterface $storeManager
	 * @param HookHelper $hookHelper
	 * @param ProductMetadataInterface $productMetadata
	 * @param ConfigHelper $configHelper
	 * @param ModuleRetriever $moduleRetriever
	 */
	public function __construct(
		DebugInfoFactory $debugInfoFactory,
		StoreManagerInterface $storeManager,
		HookHelper $hookHelper,
		ProductMetadataInterface $productMetadata,
		ConfigHelper $configHelper,
		ModuleRetriever $moduleRetriever,
        LogRetriever $logRetriever
	) {
		$this->debugInfoFactory = $debugInfoFactory;
		$this->storeManager = $storeManager;
		$this->hookHelper = $hookHelper;
		$this->productMetadata = $productMetadata;
		$this->configHelper = $configHelper;
		$this->moduleRetriever = $moduleRetriever;
		$this->logRetriever = $logRetriever;
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

		# populate bolt config settings
		$result->setBoltConfigSettings($this->configHelper->getAllConfigSettings());

		# populate other plugin info
		$result->setOtherPluginVersions($this->moduleRetriever->getInstalledModules());

		# populate log
        # for now only $MAGENTO_ROOT/var/log/exception.php
        $result->setLogInfo($this->logRetriever->getExceptionLog());

		return $result;
	}
}