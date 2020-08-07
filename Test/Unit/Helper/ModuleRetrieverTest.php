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

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\ModuleRetriever;
use Bolt\Boltpay\Model\Api\Data\PluginVersionFactory;
use Bolt\Boltpay\Model\Api\Data\PluginVersion;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class ModuleRetrieverTest extends TestCase
{
    /**
     * @var ModuleRetriever
     */
    private $moduleRetriever;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var AdapterInterface
     */
    private $dbConnection;

    /**
     * @var PluginVersionFactory
     */
    private $pluginVersionFactory;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var array
     */
    private $dbResult;


    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->dbResult = [
            [
                'module' => 'bolt',
                'schema_version' => '2.3.0'
            ],
            [
                'module' => 'amazon',
                'schema_version' => '1.3.0'
            ]
        ];

        // prepare resource and dbConnection
        $this->dbConnection = $this->createMock(AdapterInterface::class);
        $this->resource = $this->createMock(ResourceConnection::class);
        $this->resource->method('getConnection')->willReturn($this->dbConnection);
        $this->dbConnection->method('fetchAll')->willReturn($this->dbResult);

        // prepare plugin version factory
        $this->pluginVersionFactory = $this->createMock(PluginVersionFactory::class);
        $this->pluginVersionFactory->method('create')->willReturnCallback(function () {
            return new PluginVersion();
        });

        // prepare bugsnag
        $this->bugsnag = $this->createMock(Bugsnag::class);

        // initialize test object
        $objectManager = new ObjectManager($this);
        $this->moduleRetriever = $objectManager->getObject(
            ModuleRetriever::class,
            [
                'resource' => $this->resource,
                'pluginVersionFactory' => $this->pluginVersionFactory,
                'bugsnag' => $this->bugsnag
            ]
        );
    }

    /**
     * @test
     */
    public function getInstalledModules_success()
    {
        $actual = $this->moduleRetriever->getInstalledModules();
        $this->assertEquals(count($this->dbResult), count($actual));
        for ($i = 0; $i < count($this->dbResult); $i++) {
            $this->assertEquals($this->dbResult[$i]['module'], $actual[$i]->getName());
            $this->assertEquals($this->dbResult[$i]['schema_version'], $actual[$i]->getVersion());
        }
    }

    /**
     * @test
     */
    public function getInstalledModules_fail()
    {
        $this->dbConnection->method('fetchAll')->willThrowException(new \Exception());
        $this->bugsnag->expects($this->once())->method('notifyException');
        $this->moduleRetriever->getInstalledModules();
    }
}
