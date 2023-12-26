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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

/**
 * @codeCoverageIgnore
 */
class Uninstall implements UninstallInterface
{
    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function uninstall(
        SchemaSetupInterface $setup,
        ModuleContextinterface $context
    ) {
        $setup->startSetup();
        // Remove Configurations
        $this->removeBoltConfig($setup);
        // Remove Bolt Tables
        $this->removeBoltTables($setup);
        // Remove Bolt Columns
        $this->removeBoltColumns($setup);
        $setup->endSetup();
    }

    /**
     * @param SchemaSetupInterface $setup
     * @return void
     */
    private function removeBoltTables(SchemaSetupInterface $setup)
    {
        try {
            /** @var SchemaSetupInterface|mixed $tables */
            $tables = $setup->getConnection()->fetchAll(
                "SELECT TABLE_NAME FROM information_schema.tables WHERE table_name LIKE 'bolt_%'"
            );
            foreach ($tables as $table) {
                $tableExists = $setup->getConnection()->isTableExists($table['TABLE_NAME']);
                if ($tableExists) {
                    $setup->getConnection()->dropTable($table['TABLE_NAME']);
                }
            }
        } catch (\Exception $e) {
            // If we run into an exception while fetching the tables due to the query failing
            // return and continue with rest of cleanup
            return;
        }
    }

    /**
     * Remove all Bolt Plugin configurations from the config table
     *
     * @param SchemaSetupInterface $setup
     *
     * @return void
     */
    private function removeBoltConfig(SchemaSetupInterface $setup)
    {
        /** @var SchemaSetupInterface|mixed $setup */
        $setup->getConnection()->delete($setup->getTable('core_config_data'), "path like '%payment/boltpay%'");
    }

    /**
     * Clean Up Quote and Sales Order tables.
     *
     * @param SchemaSetupInterface $setup
     *
     * @return void
     */
    private function removeBoltColumns(SchemaSetupInterface $setup)
    {
        /** @var SchemaSetupInterface|mixed $setup */
        // Clean up quote table
        $setup->getConnection()->dropColumn(
            $setup->getTable('quote'),
            'bolt_parent_quote_id'
        );
        $setup->getConnection()->dropColumn(
            $setup->getTable('quote'),
            'bolt_reserved_order_id'
        );
        $setup->getConnection()->dropColumn(
            $setup->getTable('quote'),
            'bolt_checkout_type'
        );
        $setup->getConnection()->dropColumn(
            $setup->getTable('quote'),
            'bolt_dispatched'
        );
        // Clean up sales_order table
        $setup->getConnection()->dropColumn(
            $setup->getTable('sales_order'),
            'bolt_transaction_reference'
        );
    }
}
