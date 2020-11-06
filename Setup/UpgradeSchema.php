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
namespace Bolt\Boltpay\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $setup->getConnection()->addColumn(
            $setup->getTable('quote'),
            'bolt_parent_quote_id',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                'nullable' => true,
                'default' => null,
                'unsigned' => true,
                'comment' => 'Original Quote ID'
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('quote'),
            'bolt_reserved_order_id',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length' => 64,
                'nullable' => true,
                'comment' => 'Bolt Reserved Order Id'
            ]
        );

        $setup->getConnection()->dropColumn(
            $setup->getTable('quote'),
            'bolt_is_backend_order'
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('quote'),
            'bolt_checkout_type',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                'unsigned' => true,
                'nullable' => false,
                'default' => '1',
                'comment' => '1 - multi-step, 2 - PPC, 3 - back office, 4 - PPC complete'
            ]
        );

        $setup->getConnection()->addIndex(
            $setup->getTable('quote'),
            $setup->getIdxName('quote', ['bolt_parent_quote_id']),
            ['bolt_parent_quote_id']
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            'bolt_transaction_reference',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length' => 64,
                'nullable' => true,
                'comment' => 'Bolt Transaction Reference'
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            'bolt_failed_payment',
            [
                'type'     => \Magento\Framework\DB\Ddl\Table::TYPE_BOOLEAN,
                'nullable' => false,
                'default'  => false,
                'comment'  => 'Is Bolt Failed Payment'
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('quote'),
            'bolt_dispatched',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_BOOLEAN,
                'nullable' => true,
                'comment' => 'Order dispatched flag'
            ]
        );

        $this->setUpFeatureBoltCustomerCreditCardsTable($setup);

        $this->setupWebhookLogTable($setup);

        $this->updateWebhookLogTable($setup);

        $setup->endSetup();
    }

    private function setupWebhookLogTable($setup)
    {
        $tableCreated = $setup->getConnection()->isTableExists('bolt_webhook_log');
        if ($tableCreated) {
            return;
        }

        $table = $setup->getConnection()
            ->newTable($setup->getTable('bolt_webhook_log'))
            ->addColumn(
                'id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'ID'
            )
            ->addColumn(
                'transaction_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'transaction id'
            )->addColumn(
                'hook_type',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Hook type'
            )
            ->addColumn(
                'number_of_missing_quote_failed_hooks',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'default' => '0'],
                'number of the missing quote failed hooks'
            )->setComment("Bolt Webhook Log table");

        $setup->getConnection()->createTable($table);
    }

    private function setupFeatureBoltCustomerCreditCardsTable(SchemaSetupInterface $setup)
    {
        // If the table exists we do nothing. In the future, we can add migrations here based on
        // current version from context, however for now just move on.
        // The reason we do upgrade schema instead of install schema is that install schema is triggered *only*
        // once during install. This makes debugging harder.
        // However upgrade schema is triggered on every update and we get a chance to make changes as needed.
        $tableCreated = $setup->getConnection()->isTableExists('bolt_customer_credit_cards');

        if ($tableCreated) {
            return;
        }

        // Create table now that we know it doesnt already exist.
        $table = $setup->getConnection()
            ->newTable($setup->getTable('bolt_customer_credit_cards'))
            ->addColumn(
                'id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'ID'
            )
            ->addColumn(
                'card_info',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                \Magento\Framework\DB\Ddl\Table::MAX_TEXT_SIZE,
                ['nullable' => false],
                'Card Info'
            )->addColumn(
                'customer_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => false, 'unsigned' => true, 'nullable' => false, 'primary' => false],
                'Customer ID'
            )
            ->addColumn(
                'consumer_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                \Magento\Framework\DB\Ddl\Table::DEFAULT_TEXT_SIZE,
                ['nullable' => false],
                'Consumer Id'
            )
            ->addColumn(
                'credit_card_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                \Magento\Framework\DB\Ddl\Table::DEFAULT_TEXT_SIZE,
                ['nullable' => false],
                'Credit Card ID'
            )->addForeignKey(
                $setup->getFkName(
                    'bolt_customer_credit_cards',
                    'customer_id',
                    'customer_entity',
                    'entity_id'
                ),
                'customer_id',
                $setup->getTable('customer_entity'),
                'entity_id',
                \Magento\Framework\DB\Ddl\Table::ACTION_CASCADE
            )
            ->setComment("Bolt customer credit cards");
        $setup->getConnection()->createTable($table);
    }

    private function updateWebhookLogTable($setup)
    {
        $tableCreated = $setup->getConnection()->isTableExists('bolt_webhook_log');

        if (!$tableCreated) {
            return;
        }

        $connection = $setup->getConnection();

        $connection->addColumn(
            $setup->getTable('bolt_webhook_log'),
            'updated_at',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                'comment' => 'Updated At'
            ]
        );
    }
}
