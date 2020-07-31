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

namespace Bolt\Boltpay\Test\Unit\Setup;

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Setup\UpgradeSchema;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

/**
 * Class PaymentTest
 * @coversDefaultClass \Bolt\Boltpay\Model\Payment
 */
class UpgradeSchemaTest extends TestCase
{
    /**
     * @var \Bolt\Boltpay\Setup\UpgradeSchema
     */
    private $upgradeObject;

    /**
     * @var AdapterInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $dbAdapter;

    /**
     * @var SchemaSetupInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $schemaSetup;

    /**
     * @var Table|\PHPUnit_Framework_MockObject_MockObject
     */
    private $customTable;

    protected function setUp()
    {
        $this->dbAdapter = $this->getMockBuilder(AdapterInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->schemaSetup = $this->getMockBuilder(SchemaSetupInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->customTable = $this->getMockBuilder(Table::class)
            ->disableOriginalConstructor()
            ->getMock();

        $helper = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        $this->upgradeObject = $helper->getObject(UpgradeSchema::class);
    }


    /**
     * @test
     * @throws \ReflectionException
     */
    public function testSetupFeatureBoltCustomerCreditCardsTable()
    {
        $this->dbAdapter
            ->expects($this->once())
            ->method('newTable')
            ->with('bolt_customer_credit_cards')
            ->willReturn($this->customTable);
        $this->schemaSetup
            ->expects($this->any())
            ->method('getConnection')
            ->willReturn($this->dbAdapter);
        $this->schemaSetup
            ->expects($this->any())
            ->method('getTable')
            ->will(
                $this->returnCallback(
                    [$this, 'getTableCallback']
                )
            );

        $this->customTable
            ->expects($this->once())
            ->method('setComment')
            ->with('Bolt customer credit cards')
            ->willReturnSelf();
        $this->customTable
            ->expects($this->exactly(5))
            ->method('addColumn')
            ->willReturnSelf();
        $this->customTable
            ->expects($this->once())
            ->method('addForeignKey')
            ->willReturnSelf();
        $this->dbAdapter
            ->expects($this->once())
            ->method('createTable')
            ->with($this->customTable);

        $testMethod = new \ReflectionMethod(UpgradeSchema::class, 'setupFeatureBoltCustomerCreditCardsTable');
        $testMethod->setAccessible(true);
        $testMethod->invokeArgs($this->upgradeObject, [$this->schemaSetup]);
    }

    /**
     * Callback for SetupSchemaInterface::getTable
     *
     * @param string $tableName
     * @return string
     */
    public function getTableCallback(string $tableName): string
    {
        return $tableName;
    }
}
