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

namespace Bolt\Boltpay\Model\ResourceModel;

use \Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class WebhookLog extends AbstractDb
{
    /**
     * Core Date
     *
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $_coreDate;

    public function __construct(
        \Magento\Framework\Stdlib\DateTime\DateTime $coreDate,
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        $connectionName = null
    ) {
        $this->_coreDate = $coreDate;
        parent::__construct($context, $connectionName);
    }

    /**
     * WebhookLog Resource initialization
     * @return void
     */
    protected function _construct()
    {
        $this->_init('bolt_webhook_log', 'id');
    }

    /**
     * Delete attempts older than 30 days
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteOldAttempts()
    {
        $this->getConnection()->delete(
            $this->getMainTable(),
            ['updated_at < ?' => $this->_coreDate->gmtDate(null, time() - 86400 * 30)]
        );
    }
}
