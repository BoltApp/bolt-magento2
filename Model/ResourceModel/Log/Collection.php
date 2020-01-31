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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\ResourceModel\Log;

use \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Class Collection
 * @package Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard
 */
class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            'Bolt\Boltpay\Model\Log',
            'Bolt\Boltpay\Model\ResourceModel\Log'
        );
    }

    /**
     * @param $transactionId
     * @param $hookType
     * @return bool|\Magento\Framework\DataObject
     */
    public function getLogByTransactionId($transactionId, $hookType)
    {
        $logCollection = $this->addFilter('transaction_id', $transactionId)
            ->addFilter('hook_type', $hookType);
        if ($logCollection->getSize() > 0) {
            return $logCollection->getFirstItem();
        }

        return false;
    }
}