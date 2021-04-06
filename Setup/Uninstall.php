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
    ){
        $setup->startSetup();
        // Remove Configurations 
        $this->removeBoltConfig($setup);
        
        // Remove Bolt Tables
        $tables = ['bolt_feature_switches',
                   'bolt_external_customer_entity',
                   'bolt_webhook_log',
                   'bolt_customer_credit_cards'];
        $this->removeBoltTables($setup, $tables);

        $this->removeBoltColumns($setup);
        $setup->endSetup();
    }


    /** 
     * Remove all tables in provided array if they exist
     * 
     * @param SchemSetupInterface $setup
     * @param array $tables
     * 
     * @return void
     */

    private function removeBoltTables(SchemaSetupInterface $setup, $tables){
        foreach ($tables as $table){
            $tableExists = $setup->getConnection()->isTableExists($table);
            if ($tableExists) {
                $setup->getConnection()->dropTable($table); 
            }
        }
    }

    /**
     * Remove all Bolt Plugin configurations from the config table
     * 
     * @param SchemSetupInterface $setup
     * 
     * @return void
     */
    private function removeBoltConfig(SchemaSetupInterface $setup){
        $setup->getConnection()->delete($setup->getTable('core_config_data'), "path like '%payment/boltpay%'");
    }

    /**
     * Clean Up Quote and Sales Order tables.
     * 
     * @param SchemSetupInterface $setup
     * 
     * @return void
     */
    private function removeBoltColumns(SchemaSetupInterface $setup){
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