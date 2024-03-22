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
namespace Bolt\Boltpay\Plugin\Magento\Framework\View\Element\UiComponent\DataProvider;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OrderGridCollection;
use Magento\Framework\View\Element\UiComponent\DataProvider\Reporting;

/**
 * Modifying magento order grid collection data
 */
class ReportingPlugin
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Join payment data to collection
     *
     * @param Reporting $subject
     * @param $result
     * @return OrderGridCollection|mixed
     */
    public function afterSearch(
        Reporting $subject,
        $result
    ) {
        if ($result instanceof OrderGridCollection) {
            $collectionSelect = $result->getSelect();
            $collectionSelect->joinLeft(
                ['payment' => $this->resourceConnection->getTableName('sales_order_payment')],
                'main_table.entity_id = payment.parent_id',
                []
            );
            $collectionSelect->columns([
                'cc_type' => 'LOWER(payment.cc_type)',
                'cc_trans_id' => 'LOWER(payment.cc_trans_id)',
                'additional_data' => 'payment.additional_data'
            ]);
        }
        return $result;
    }
}
