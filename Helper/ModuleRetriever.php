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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\ResourceConnection;
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
            foreach ($rows as $row) {
                $installedModules[] = $this->pluginVersionFactory->create()
                                                                 ->setName($row['module'])
                                                                 ->setVersion($row['schema_version']);
            }

            return $installedModules;
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }
}
