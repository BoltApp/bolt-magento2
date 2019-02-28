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
use Bolt\Boltpay\Model\ResourceModel\MerchantDivisionUrls as ResourceModelMerchantDivisionUrls;

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

        $setup->getConnection()->addIndex(
            $setup->getTable('quote'),
            $setup->getIdxName('quote', ['bolt_parent_quote_id']),
            ['bolt_parent_quote_id']
        );

        if (version_compare($context->getVersion(), '1.1.10', '>=')) {
            if (!$setup->tableExists(ResourceModelMerchantDivisionUrls::TABLE_NAME)) {
                $table = $setup->getConnection()
                    ->newTable(ResourceModelMerchantDivisionUrls::TABLE_NAME)
                    ->addColumn(
                        'id',
                        \Magento\Framework\Db\Ddl\Table::TYPE_INTEGER,
                        8,
                        ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
                        'Id'
                    )
                    ->addColumn(
                        'division_id',
                        \Magento\Framework\Db\Ddl\Table::TYPE_INTEGER,
                        8,
                        ['nullable' => false, 'unsigned' => true],
                        'Division Id'
                    )
                    ->addColumn(
                        'type',
                        \Magento\Framework\Db\Ddl\Table::TYPE_TEXT,
                        20,
                        ['nullable' => false],
                        'Type'
                    )
                    ->addColumn(
                        'url',
                        \Magento\Framework\Db\Ddl\Table::TYPE_TEXT,
                        4096,
                        ['nullable' => false],
                        'Url'
                    );

                $setup->getConnection()->createTable($table);
            }
        }

        $setup->endSetup();
    }
}
