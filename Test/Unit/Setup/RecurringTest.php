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
use Bolt\Boltpay\Setup\Recurring;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;
use Bolt\Boltpay\Test\Unit\TestHelper;

/**
 * Class RecurringTest
 * @coversDefaultClass \Bolt\Boltpay\Setup\Recurring
 */
class RecurringTest extends TestCase
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $setup;

    /**
     * @var SchemaSetupInterface
     */
    private $schemaSetup;

    /**
     * @var ModuleContextInterface
     */
    private $context;

    /**
     * @var Recurring
     */
    private $currentMock;

    /**
     * @var Table
     */
    private $customTable;

    protected function setUp()
    {
        $this->setup = $this->createMock(ModuleDataSetupInterface::class);
        $this->context = $this->createMock(ModuleContextInterface::class);

        $this->currentMock = $this->getMockBuilder(Recurring::class)
            ->enableProxyingToOriginalMethods()
            ->getMock();


        $this->schemaSetup = $this->getMockBuilder(SchemaSetupInterface::class)
            ->setMethods(
                [
                    'addColumn',
                    'dropColumn',
                    'addIndex',
                    'isTableExists',
                    'setComment',
                    'createTable',
                    'newTable',
                ]
            )
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->customTable = $this->getMockBuilder(Table::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @test
     * @throws \ReflectionException
     */
    public function setupFeatureSwitchTable_withNonExistenceTable_createsTable()
    {
        $this->schemaSetup->expects(static::exactly(3))->method('getConnection')->willReturnSelf();
        $this->schemaSetup->expects(static::once())
            ->method('isTableExists')
            ->with('bolt_feature_switches')
            ->willReturn(false);

        $boltFeatureSwitchTable = 'bolt_feature_switches';
        $this->schemaSetup->expects(static::once())
            ->method('getTable')
            ->with('bolt_feature_switches')
            ->willReturn($boltFeatureSwitchTable);
        $this->schemaSetup->expects(static::once())
            ->method('newTable')
            ->with($boltFeatureSwitchTable)
            ->willReturnSelf();
        $this->schemaSetup->expects(static::exactly(5))->method('addColumn')
            ->withConsecutive(
                [
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                    'ID',
                ],
                [
                    'switch_name',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'Switch name',
                ],
                [
                    'switch_value',
                    Table::TYPE_BOOLEAN,
                    null,
                    ['nullable' => false, 'default' => '0'],
                    'switch value',
                ],
                [
                    'default_value',
                    Table::TYPE_BOOLEAN,
                    null,
                    ['nullable' => false, 'default' => '0'],
                    'default value',
                ],
                [
                    'rollout_percentage',
                    Table::TYPE_INTEGER,
                    null,
                    ['nullable' => false, 'default' => '0'],
                    'rollout percentage',
                ]
            )->willReturnSelf();
        $this->schemaSetup->expects(static::once())
            ->method('setComment')
            ->with('Bolt feature switch table')
            ->willReturn($this->customTable);

        $this->schemaSetup->expects(static::once())->method('createTable')->with($this->customTable)->willReturnSelf();

        TestHelper::invokeMethod($this->currentMock, 'setupFeatureSwitchTable', [$this->schemaSetup]);
    }

    /**
     * @test
     * that
     * @see Recurring::setupFeatureSwitchTable()
     * don't alter the database if the tables were already created
     *
     * @throws \ReflectionException if $methodName method doesn't exist
     */
    public function setupFeatureSwitchTable_withExistenceTable_DoesNotCreateTable()
    {
        $this->schemaSetup->expects(static::once())->method('getConnection')->willReturnSelf();
        $this->schemaSetup->expects(static::once())->method('isTableExists')->with('bolt_feature_switches')
            ->willReturn(true);
        $this->schemaSetup->expects(static::never())
            ->method(
                static::logicalAnd(
                    static::logicalNot(static::equalTo('getConnection')),
                    static::logicalNot(static::equalTo('isTableExists')),
                    static::logicalNot(static::equalTo('newTable')),
                    static::logicalNot(static::equalTo('addColumn')),
                    static::logicalNot(static::equalTo('createTable'))
                )
            );

        TestHelper::invokeMethod($this->currentMock, 'setupFeatureSwitchTable', [$this->schemaSetup]);
    }
}
