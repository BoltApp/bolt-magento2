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

namespace Bolt\Boltpay\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Class WebhookLog
 * @package Bolt\Boltpay\Model
 */
class WebhookLog extends AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'bolt_webhook_log';

    protected $_cacheTag = self::CACHE_TAG;

    /**
     * Core Date
     *
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $_coreDate;

    /**
     * WebhookLog constructor.
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $coreDate
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Stdlib\DateTime\DateTime $coreDate,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_coreDate = $coreDate;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    protected function _construct()
    {
        $this->_init('Bolt\Boltpay\Model\ResourceModel\WebhookLog');
    }

    /**
     * @return array|string[]
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * @param $transactionId
     * @param $hookType
     *
     * @return WebhookLog
     */
    public function recordAttempt($transactionId, $hookType)
    {
        $this->setTransactionId($transactionId)
            ->setHookType($hookType)
            ->setNumberOfMissingQuoteFailedHooks(1)
            ->setUpdatedAt($this->_coreDate->gmtDate())
            ->save();

        return $this;
    }

    /**
     * @return $this
     */
    public function incrementAttemptCount()
    {
        $this->setNumberOfMissingQuoteFailedHooks($this->getNumberOfMissingQuoteFailedHooks() + 1)
             ->setUpdatedAt($this->_coreDate->gmtDate())
             ->save();

        return $this;
    }
}
