<?php


namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\ResourceConnection;
use Bolt\Boltpay\Model\Api\Data\PluginVersion;
use Bolt\Boltpay\Model\Api\Data\PluginVersionFactory;

class ModuleRetriever
{
	/**
	 * @var ResourceConnection $resource
	 */
	private $resource;

	/**
	 * @var PluginVersionFactory
	 */
	private $pluginVersionFactory;

	/**
	 * @var Bugsnag
	 */
	private $bugsnag;

	/**
	 *
	 * @param ResourceConnection $resource
	 * @param PluginVersionFactory $pluginVersionFactory
	 * @param Bugsnag $bugsnag
	 *
	 */
	public function __construct(
		ResourceConnection $resource,
		PluginVersionFactory $pluginVersionFactory,
		Bugsnag $bugsnag
	) {
		$this->resource = $resource;
		$this->pluginVersionFactory = $pluginVersionFactory;
		$this->bugsnag = $bugsnag;
	}

	public function getInstalledModules()
	{
		$connection = $this->resource->getConnection();
		try {
			$installedModules = [];
			$rows = $connection->fetchAll('SELECT module, schema_version FROM setup_module');
			foreach ($rows AS $row) {
				$installedModules[] = $this->pluginVersionFactory->create()
				                                                 ->setName($row['module'])
				                                                 ->setVersion($row['schema_version']);
			}

			return $installedModules;
		} catch (\Zend_Db_Statement_Exception $e) {
			$this->bugsnag->notifyException($e);
		}
	}
}