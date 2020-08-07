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

use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Framework\Setup\ModuleContextInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Setup\UpgradeSchema;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;
use ReflectionException;

/**
 * @coversDefaultClass \Bolt\Boltpay\Setup\UpgradeSchema
 */
class UpgradeSchemaTest extends TestCase
{

    /** @var AdapterInterface|MockObject mocked instance of the database connection class */
    private $dbAdapter;

    /**
     * @var SchemaSetupInterface|MockObject mocked instance of the setup class, provided to
     * {@see \Bolt\Boltpay\Setup\UpgradeSchema::upgrade} 
     */
    private $schemaSetup;

    /** @var Table|MockObject mocked instance of the database table model */
    private $customTable;

    /** @var MockObject|UpgradeSchema mocked instance of the class tested */
    private $currentMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->dbAdapter = $this->getMockBuilder(AdapterInterface::class)
            ->disableOriginalConstructor()
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

        $this->initCurrentMock();
    }

    /**
     * Sets mocked instance of the tested class
     *
     * @param array $methods to be stubbed
     */
    private function initCurrentMock($methods = [])
    {
        $mockBuilder = $this->getMockBuilder(UpgradeSchema::class);
        if ($methods) {
            $mockBuilder->setMethods($methods);
        } else {
            $mockBuilder->enableProxyingToOriginalMethods();
        }
        $this->currentMock = $mockBuilder->getMock();
    }

    /**
     * @test
     * that upgrade will:
     * 1. Start setup
     * 2. Add bolt_parent_quote_id, bolt_reserved_order_id, bolt_is_backend_order, bolt_checkout_type columns to quote table
     * 3. Add index for bolt_parent_quote_id column to quote table
     * 4. Setup feature switch table by calling {@see \Bolt\Boltpay\Setup\UpgradeSchema::setupFeatureSwitchTable}
     * 5. Setup customer credit cards table by calling {@see \Bolt\Boltpay\Setup\UpgradeSchema::setupFeatureBoltCustomerCreditCardsTable}
     * 6. Setup webhook log table by calling {@see \Bolt\Boltpay\Setup\UpgradeSchema::setupWebhookLogTable}
     * 7. Update webhook log table by calling {@see \Bolt\Boltpay\Setup\UpgradeSchema::updateWebhookLogTable}
     * 8. End setup
     *
     * @covers ::upgrade
     *
     * @throws ReflectionException if unable to create ModuleContextInterface mock
     */
    public function upgrade_always_upgradesDatabase()
    {
        $moduleContextMock = $this->createMock(ModuleContextInterface::class);

        $this->schemaSetup->expects(static::once())->method('startSetup');
        $this->schemaSetup->expects(static::atLeastOnce())->method('getConnection')->willReturnSelf();
        $quoteTable = 'quote';
        $boltWebhookTable = 'bolt_webhook_log';
        $this->schemaSetup->expects(static::atLeastOnce())->method('getTable')
            ->willReturnCallback(
                function ($tableName) {
                    return $tableName;
                }
            )
        ;

        $this->schemaSetup->expects(static::atLeastOnce())->method('addColumn')->withConsecutive(
            [
                $quoteTable,
                'bolt_parent_quote_id',
                [
                    'type'     => Table::TYPE_INTEGER,
                    'nullable' => true,
                    'default'  => null,
                    'unsigned' => true,
                    'comment'  => 'Original Quote ID'
                ],
            ],
            [
                $quoteTable,
                'bolt_reserved_order_id',
                [
                    'type'     => Table::TYPE_TEXT,
                    'length'   => 64,
                    'nullable' => true,
                    'comment'  => 'Bolt Reserved Order Id'
                ],
            ],
            [
                $quoteTable,
                'bolt_checkout_type',
                [
                    'type'     => Table::TYPE_SMALLINT,
                    'unsigned' => true,
                    'nullable' => false,
                    'default'  => '1',
                    'comment'  => '1 - multi-step, 2 - PPC, 3 - back office, 4 - PPC complete'
                ],
            ],
            [
                'sales_order',
                'bolt_transaction_reference',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 64,
                    'nullable' => true,
                    'comment' => 'Bolt Transaction Reference'
                ],
            ],
            [
                $boltWebhookTable,
                'updated_at',
                [
                    'type'    => Table::TYPE_TIMESTAMP,
                    'comment' => 'Updated At'
                ],
            ]
        );

        $this->schemaSetup->expects(static::once())
            ->method('dropColumn')
            ->with($quoteTable, 'bolt_is_backend_order')
            ->willReturnSelf();

        $quoteUniqueHash = '98156307323252d52c6683671a73dff3';
        $this->schemaSetup->expects(static::once())
            ->method('getIdxName')
            ->with('quote', ['bolt_parent_quote_id'])
            ->willReturn($quoteUniqueHash);

        $this->schemaSetup->expects(static::once())
            ->method('addIndex')
            ->with($quoteTable, $quoteUniqueHash, ['bolt_parent_quote_id'])
            ->willReturnSelf();

        $this->schemaSetup->expects(static::atLeastOnce())
            ->method('isTableExists')
            ->willReturn(true);

        $this->schemaSetup->expects(static::once())->method('endSetup');

        $this->currentMock->upgrade($this->schemaSetup, $moduleContextMock);
    }

    /**
     * Data provider for {@see setupMethods_withTablesAlreadyCreated_doNotAlterTheDatabase}
     *
     * @return array[] containing tested method name and table name associated to the method
     */
    public function setupMethods_withTablesAlreadyCreatedProvider()
    {
        return [
            [
                'methodName' => 'setupFeatureSwitchTable',
                'tableName'  => 'bolt_feature_switches',
            ],
            [
                'methodName' => 'setupWebhookLogTable',
                'tableName'  => 'bolt_webhook_log',
            ],
            [
                'methodName' => 'setupFeatureBoltCustomerCreditCardsTable',
                'tableName'  => 'bolt_customer_credit_cards',
            ],
        ];
    }

    /**
     * @test
     * that
     * @see UpgradeSchema::setupFeatureSwitchTable
     * @see UpgradeSchema::setupWebhookLogTable
     * @see UpgradeSchema::setupFeatureBoltCustomerCreditCardsTable
     * don't alter the database if the tables were already created
     *
     * @covers ::setupFeatureSwitchTable
     * @covers ::setupWebhookLogTable
     * @covers ::setupFeatureBoltCustomerCreditCardsTable
     *
     * @dataProvider setupMethods_withTablesAlreadyCreatedProvider
     *
     * @param string $methodName method name to be tested
     * @param string $tableName associated with the tested method
     *
     * @throws ReflectionException if $methodName method doesn't exist
     */
    public function setupMethods_withTablesAlreadyCreated_doNotAlterTheDatabase($methodName, $tableName) {
        $this->schemaSetup->expects(static::once())->method('getConnection')->willReturnSelf();
        $this->schemaSetup->expects(static::once())->method('isTableExists')->with($tableName)
            ->willReturn(true);
        $this->schemaSetup->expects(static::never())
            ->method(
                static::logicalAnd(
                    static::logicalNot(static::equalTo('getConnection')),
                    static::logicalNot(static::equalTo('isTableExists'))
                )
            );
        TestHelper::invokeMethod($this->currentMock, $methodName, [$this->schemaSetup]);
    }

    /**
     * @test
     * that setupFeatureSwitchTable creates bolt_feature_switches table if it was not already created
     *
     * @covers ::setupFeatureSwitchTable
     *
     * @throws ReflectionException if setupFeatureSwitchTable method doesn't exist
     */
    public function setupFeatureSwitchTable_ifBoltFeatureSwitchesTableDoesNotExist_createsTheTable()
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
     * that setupWebhookLogTable creates bolt_webhook_log table if it does not exist already
     *
     * @covers ::setupWebhookLogTable
     *
     * @throws ReflectionException if setupWebhookLogTable method doesn't exist
     */
    public function setupWebhookLogTable_ifBoltWebhookLogTableDoesNotExist_createsTable()
    {
        $this->schemaSetup->expects(static::exactly(3))->method('getConnection')->willReturnSelf();
        $this->schemaSetup->expects(static::once())
            ->method('isTableExists')
            ->with('bolt_webhook_log')
            ->willReturn(false);

        $boltWebhookTable = 'bolt_webhook_log';
        $this->schemaSetup->expects(static::once())
            ->method('getTable')
            ->with('bolt_webhook_log')
            ->willReturn($boltWebhookTable);
        $this->schemaSetup->expects(static::once())->method('newTable')->with($boltWebhookTable)->willReturnSelf();
        $this->schemaSetup->expects(static::exactly(4))->method('addColumn')
            ->withConsecutive(
                [
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                    'ID',
                ],
                [
                    'transaction_id',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'transaction id',
                ],
                [
                    'hook_type',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'Hook type',
                ],
                [
                    'number_of_missing_quote_failed_hooks',
                    Table::TYPE_INTEGER,
                    null,
                    ['nullable' => false, 'default' => '0'],
                    'number of the missing quote failed hooks',
                ]
            )->willReturnSelf();

        $this->schemaSetup->expects(static::once())
            ->method('setComment')
            ->with('Bolt Webhook Log table')
            ->willReturn($this->customTable);

        $this->schemaSetup->expects(static::once())->method('createTable')->with($this->customTable)->willReturnSelf();

        TestHelper::invokeMethod($this->currentMock, 'setupWebhookLogTable', [$this->schemaSetup]);
    }

    /**
     * @test
     * that updateWebhookLogTable adds updated_at column to bolt_webhook_log table if it exists
     *
     * @covers ::updateWebhookLogTable
     *
     * @throws ReflectionException if updateWebhookLogTable method doesn't exist
     */
    public function updateWebhookLogTable_ifBoltWebhookLogTableExists_updatesWebhookLogTable()
    {
        $this->schemaSetup->expects(static::exactly(2))->method('getConnection')->willReturnSelf();

        $this->schemaSetup->expects(static::once())
            ->method('isTableExists')
            ->with('bolt_webhook_log')
            ->willReturn(true);

        $this->schemaSetup->expects(static::once())->method('getTable')->with('bolt_webhook_log')
            ->willReturn('bolt_webhook_log');
        $this->schemaSetup->expects(static::once())->method('addColumn')->with(
            'bolt_webhook_log',
            'updated_at',
            [
                'type'    => Table::TYPE_TIMESTAMP,
                'comment' => 'Updated At',
            ]
        );

        TestHelper::invokeMethod($this->currentMock, 'updateWebhookLogTable', [$this->schemaSetup]);
    }

    /**
     * @test
     * that setupFeatureBoltCustomerCreditCardsTable creates bolt_customer_credit_cards table if it doesn't exist already
     *
     * @covers ::setupFeatureBoltCustomerCreditCardsTable
     *
     * @throws ReflectionException if setupFeatureBoltCustomerCreditCardsTable method doesn't exist
     */
    public function setupFeatureBoltCustomerCreditCardsTable_ifTableDoesNotExist_createsTheTable()
    {
        $this->dbAdapter
            ->expects(static::once())
            ->method('newTable')
            ->with('bolt_customer_credit_cards')
            ->willReturn($this->customTable);
        $this->schemaSetup->expects(static::any())->method('getConnection')->willReturn($this->dbAdapter);
        $this->schemaSetup->method('getTable')->willReturnArgument(0);

        $this->customTable->expects(static::once())->method('setComment')->with('Bolt customer credit cards')->willReturnSelf();
        $this->customTable->expects(static::exactly(5))->method('addColumn')
            ->withConsecutive(
                [
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                    'ID'
                ],
                [
                    'card_info',
                    Table::TYPE_TEXT,
                    Table::MAX_TEXT_SIZE,
                    ['nullable' => false],
                    'Card Info'
                ],
                [
                    'customer_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['identity' => false, 'unsigned' => true, 'nullable' => false, 'primary' => false],
                    'Customer ID'
                ],
                [
                    'consumer_id',
                    Table::TYPE_TEXT,
                    Table::DEFAULT_TEXT_SIZE,
                    ['nullable' => false],
                    'Consumer Id'
                ],
                [
                    'credit_card_id',
                    Table::TYPE_TEXT,
                    Table::DEFAULT_TEXT_SIZE,
                    ['nullable' => false],
                    'Credit Card ID'
                ]
            )
            ->willReturnSelf();
        $this->customTable->expects(static::once())->method('addForeignKey')->with(
                $this->schemaSetup->getFkName(
                    'bolt_customer_credit_cards',
                    'customer_id',
                    'customer_entity',
                    'entity_id'
                ),
                'customer_id',
                'customer_entity',
                'entity_id',
                Table::ACTION_CASCADE
            )
            ->willReturnSelf();
        $this->dbAdapter->expects(static::once())->method('createTable')->with($this->customTable);
        TestHelper::invokeMethod($this->currentMock, 'setupFeatureBoltCustomerCreditCardsTable', [$this->schemaSetup]);
    }

    /**
     * @test
     * that updateWebhookLogTable will not try to alter bolt_webhook_log table if it doesn't exist
     *
     * @covers ::updateWebhookLogTable
     *
     * @throws ReflectionException if updateWebhookLogTable method doesn't exist
     */
    public function updateWebhookLogTable_ifTableDoesNotExist_doesNotAlterTheDatabase()
    {
        $this->schemaSetup->expects(static::once())->method('getConnection')->willReturnSelf();
        $this->schemaSetup->expects(static::once())->method('isTableExists')->with('bolt_webhook_log')
            ->willReturn(false);
        // expect nothing apart from getConnection and isTableExists to be called
        $this->schemaSetup->expects(static::never())
            ->method(
                static::logicalAnd(
                    static::logicalNot(static::equalTo('getConnection')),
                    static::logicalNot(static::equalTo('isTableExists'))
                )
            );
        TestHelper::invokeMethod($this->currentMock, 'updateWebhookLogTable', [$this->schemaSetup]);
    }
}
