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
namespace Bolt\Boltpay\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * @codeCoverageIgnore
 */
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

        $setup->getConnection()->addColumn(
            $setup->getTable('quote'),
            'bolt_is_backend_order',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                'unsigned' => true,
                'default' => '0',
                'comment' => '0 - frontend order, 1 - backend order'
            ]
        );

        $setup->getConnection()->addIndex(
            $setup->getTable('quote'),
            $setup->getIdxName('quote', ['bolt_parent_quote_id']),
            ['bolt_parent_quote_id']
        );

        $this->setupFeatureSwitchTable($setup);

        $setup->endSetup();
    }

    private function setupFeatureSwitchTable(SchemaSetupInterface $setup) {
        // If the table exists we do nothing. In the future, we can add migrations here based on
        // current version from context, however for now just move on.
        // The reason we do ugrade schema instead of install schema is that install schema is triggered *only*
        // once during install. This makes debugging harder.
        // However upgrade schema is triggered on every update and we get a chance to make changes as needed.
        $tableCreated = $setup->getConnection()->isTableExists('bolt_feature_switches');

        if ($tableCreated) {
            return;
        }

        // Create table now that we know it doesnt already exists.
        $table = $setup->getConnection()
          ->newTable($setup->getTable('bolt_feature_switches'))
          ->addColumn(
            'id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
            'ID'
        )
        ->addColumn(
            'switch_name',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Switch name'
        )
        ->addColumn(
            'switch_value',
            \Magento\Framework\DB\Ddl\Table::TYPE_BOOLEAN,
            null,
            ['nullable' => false, 'default' => '0'],
            'switch value'
        )->addColumn(
            'default_value',
            \Magento\Framework\DB\Ddl\Table::TYPE_BOOLEAN,
            null,
            ['nullable' => false, 'default' => '0'],
            'default value'
        )->addColumn(
            'rollout_percentage',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['nullable' => false, 'default' => '0'],
            'rollout percentage'
        )->setComment("Bolt feature switch table");
          $setup->getConnection()->createTable($table);
    }
}
