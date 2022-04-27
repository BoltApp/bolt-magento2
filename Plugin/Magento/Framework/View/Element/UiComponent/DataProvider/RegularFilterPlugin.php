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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Plugin\Magento\Framework\View\Element\UiComponent\DataProvider;

use Magento\Framework\View\Element\UiComponent\DataProvider\RegularFilter;
use Magento\Framework\Data\Collection;
use Magento\Framework\Api\Filter;
use Magento\Framework\App\ResourceConnection;
use \Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OrderGridCollection;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Model\Payment;

/**
 * Ui component filter plugin to implement filter sales order grid by bolt payment methods
 */
class RegularFilterPlugin
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param Config $config
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Config $config,
        ResourceConnection $resourceConnection
    ) {
        $this->config = $config;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Sales order grid filtering by bolt credit card type
     *
     * @param RegularFilter $subject
     * @param callable $proceed
     * @param Collection $collection
     * @param Filter $filter
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundApply(
        RegularFilter $subject,
        callable $proceed,
        Collection $collection,
        Filter $filter
    ) {
        if ($collection instanceof OrderGridCollection &&
            $filter->getField() == 'payment_method' &&
            strpos($filter->getValue(), Payment::METHOD_CODE . '_') !== false
        ) {
            $collectionSelect = $collection->getSelect();
            $collectionAdapter = $collectionSelect->getConnection();
            $collectionSelect->joinLeft(
                ['payment' => $this->resourceConnection->getTableName('sales_order_payment')],
                'main_table.entity_id = payment.parent_id',
                []
            );
            $paymentMethod = str_replace(Payment::METHOD_CODE . '_', '', $filter->getValue());
            $collectionSelect->where(
                'main_table.payment_method = "'. Payment::METHOD_CODE .'"
                AND ('. $collectionAdapter->quoteInto('LOWER(payment.additional_information) like ?', '%' . $paymentMethod . '%') .' OR '. $collectionAdapter->quoteInto('LOWER(payment.cc_type) = ?', $paymentMethod) .')'
            );
        } else {
            return $proceed($collection, $filter);
        }
    }
}
