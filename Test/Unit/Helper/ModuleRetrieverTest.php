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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\ModuleRetriever;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;

class ModuleRetrieverTest extends BoltTestCase
{
    /**
     * @var ModuleRetriever
     */
    private $moduleRetriever;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->moduleRetriever = $this->objectManager->create(ModuleRetriever::class);
    }

    /**
     * @test
     */
    public function getInstalledModules_success()
    {
        $actual = $this->moduleRetriever->getInstalledModules();
        $this->assertGreaterThan(1, count($actual));
    }

    /**
     * @test
     */
    public function getInstalledModules_fail()
    {
        $dbConnection = $this->createMock(AdapterInterface::class);
        $dbConnection->method('fetchAll')->willThrowException(new \Exception());
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($dbConnection);
        $bugsnag = $this->createMock(Bugsnag::class);
        $bugsnag->expects($this->once())->method('notifyException');
        TestHelper::setInaccessibleProperty($this->moduleRetriever, 'bugsnag', $bugsnag);
        TestHelper::setInaccessibleProperty($this->moduleRetriever, 'resource', $resource);
        $this->moduleRetriever->getInstalledModules();
    }
}
