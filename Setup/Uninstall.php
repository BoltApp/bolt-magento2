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
        $this->removeFeatureSwitchTable($setup);
        $this->removeExternalCustomerEntityTable($setup);
        $this->removeBoltWebhookLogTable($setup);
        $this->removeBoltCustomerCreditCardsTable($setup);
        $this->removeBoltColumns($setup);
        $setup->endSetup();
    }

    /**
     * Remove all Bolt Plugin configurations from the config table
     * 
     * @return void
     */
    private function removeBoltConfig(SchemaSetupInterface $setup){
        $setup->getConnection()->delete($setup->getTable('core_config_data'), "path like '%payment/boltpay%'");
    }

    /**
     * Remove the feature switch table if it exists.
     * 
     * @param SchemSetupInterface $setup
     * 
     * @return void
     */
    private function removeFeatureSwitchTable(SchemaSetupInterface $setup){
        $tableExists = $setup->getConnection()->isTableExists('bolt_feature_switches');
        if (!$tableExists) {
            return;
        }
        $setup->getConnection()->dropTable('bolt_feature_switches');
    }

    /**
     * Remove the Bolt External Customer Entity table if it exists.
     * 
     * @param SchemSetupInterface $setup
     * 
     * @return void
     */
    private function removeExternalCustomerEntityTable(SchemaSetupInterface $setup){
        $tableExists = $setup->getConnection()->isTableExists('bolt_external_customer_entity');
        if (!$tableExists) {
            return;
        }
        $setup->getConnection()->dropTable('bolt_external_customer_entity');
    }

    /**
     * Remove the Bolt Webhook Log table if it exists.
     * 
     * @param SchemSetupInterface $setup
     * 
     * @return void
     */
    private function removeBoltWebhookLogTable(SchemaSetupInterface $setup){
        $tableExists = $setup->getConnection()->isTableExists('bolt_webhook_log');
        if (!$tableExists) {
            return;
        }
        $setup->getConnection()->dropTable('bolt_webhook_log');
    }

    /**
     * Remove the Bolt Customer Credit Cards table if it exists.
     * 
     * @param SchemSetupInterface $setup
     * 
     * @return void
     */
    private function removeBoltCustomerCreditCardsTable(SchemaSetupInterface $setup){
        $tableExists = $setup->getConnection()->isTableExists('bolt_customer_credit_cards');
        if (!$tableExists) {
            return;
        }
        $setup->getConnection()->dropTable('bolt_customer_credit_cards');
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