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

namespace Bolt\Boltpay\Cron;

use Magento\Framework\App\ResourceConnection;
use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Helper\Bugsnag;

class DeactivateQuote
{

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * DeactivateQuote constructor.
     * @param ResourceConnection $resourceConnection
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        Bugsnag $bugsnag
    ) {
        $this->bugsnag = $bugsnag;
        $this->resourceConnection = $resourceConnection;
    }

    public function execute()
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $sql = sprintf(
                'UPDATE %s SET is_active = 0 ' .
                        'WHERE is_active = 1 ' .
                        'AND entity_id IN ' .
                            '(SELECT so.quote_id '.
                            'FROM %s AS so '.
                            'INNER JOIN %s AS sop '.
                            'ON so.entity_id = sop.parent_id '.
                            'WHERE sop.method = "%s")',
                $this->resourceConnection->getTableName('quote'),
                $this->resourceConnection->getTableName('sales_order'),
                $this->resourceConnection->getTableName('sales_order_payment'),
                Payment::METHOD_CODE
            );

            $connection->query($sql);
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }
}
