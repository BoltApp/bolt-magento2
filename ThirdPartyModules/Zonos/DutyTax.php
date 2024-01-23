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

namespace Bolt\Boltpay\ThirdPartyModules\Zonos;

use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Model\Quote;

class DutyTax
{
    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resourceConnection;

    /**
     * DutyTax constructor.
     * @param Bugsnag $bugsnagHelper
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        ResourceConnection $resourceConnection
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Zonos_DutyTax adds a foreign key constraint to the quote table,
     * so we have to delete the related rows in zonos_shipping_quotes before deleting redundant quotes
     *
     * @param Quote|mixed $quote
     */
    public function beforeOrderDeleteRedundantQuotes($quote)
    {
        try {
            if ($quote && $quote->getId()) {
                $connection = $this->resourceConnection->getConnection();
                
                $zonosShippingQuotesTable = $this->resourceConnection->getTableName('zonos_shipping_quotes');
                $quoteTable = $this->resourceConnection->getTableName('quote');
                // phpcs:ignore
                $sql = "DELETE FROM {$zonosShippingQuotesTable} WHERE quote_id IN
                    (SELECT entity_id FROM {$quoteTable}
                    WHERE bolt_parent_quote_id = :bolt_parent_quote_id AND entity_id != :entity_id)";
                $bind = [
                    'bolt_parent_quote_id' => $quote->getBoltParentQuoteId(),
                    'entity_id' => $quote->getBoltParentQuoteId()
                ];
                
                $connection->query($sql, $bind);
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
    
    /**
     * Zonos_DutyTax adds a foreign key constraint to the quote table,
     * so we have to delete the related rows in zonos_shipping_quotes before deleting quote
     *
     * @param Quote $quote
     */
    public function beforeCartDeleteQuote($quote)
    {
        try {
            if ($quote && $quote->getId()) {
                $connection = $this->resourceConnection->getConnection();
                
                $zonosShippingQuotesTable = $this->resourceConnection->getTableName('zonos_shipping_quotes');
                // phpcs:ignore
                $sql = "DELETE FROM {$zonosShippingQuotesTable} WHERE quote_id = :quote_id";
                $bind = [
                    'quote_id' => $quote->getId()
                ];
                
                $connection->query($sql, $bind);
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
}
