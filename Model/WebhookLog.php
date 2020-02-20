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
            ->save();

        return $this;
    }

    /**
     * @return $this
     */
    public function incrementAttemptCount()
    {
        $this->setNumberOfMissingQuoteFailedHooks($this->getNumberOfMissingQuoteFailedHooks() + 1)->save();

        return $this;
    }
}