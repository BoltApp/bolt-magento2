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
                'comment' => '0 - frontend order, 1 - backent order'
            ]
        );

        $setup->getConnection()->addIndex(
            $setup->getTable('quote'),
            $setup->getIdxName('quote', ['bolt_parent_quote_id']),
            ['bolt_parent_quote_id']
        );

        $setup->endSetup();
    }
}
