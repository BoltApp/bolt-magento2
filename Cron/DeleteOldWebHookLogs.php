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

class DeleteOldWebHookLogs
{
    /**
     * @var \Bolt\Boltpay\Model\ResourceModel\WebhookLogFactory
     */
    protected $webhookLogFactory;

    /**
     * DeleteOldWebHookLogs constructor.
     * @param \Bolt\Boltpay\Model\ResourceModel\WebhookLogFactory $webhookLogFactory
     */
    public function __construct(
        \Bolt\Boltpay\Model\ResourceModel\WebhookLogFactory $webhookLogFactory
    ) {
        $this->webhookLogFactory = $webhookLogFactory;
    }

    /**
     * Delete attempts older than 30 days
     * @return $this
     */
    public function execute()
    {
        $this->webhookLogFactory->create()->deleteOldAttempts();
        return $this;
    }
}
