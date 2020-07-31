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

namespace Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard;

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
            'Bolt\Boltpay\Model\CustomerCreditCard',
            'Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard'
        );
    }

    /**
     * @param $customerId
     * @param $boltConsumerId
     * @param $boltCreditCardId
     * @return Collection
     */
    public function getCreditCards($customerId, $boltConsumerId, $boltCreditCardId)
    {
        return $this->addFilter('customer_id', $customerId)
            ->addFilter('consumer_id', $boltConsumerId)
            ->addFilter('credit_card_id', $boltCreditCardId);
    }

    /**
     * @param $customerId
     * @param $boltConsumerId
     * @param $boltCreditCardId
     * @return bool
     */
    public function doesCardExist($customerId, $boltConsumerId, $boltCreditCardId)
    {
        return $this->getCreditCards($customerId, $boltConsumerId, $boltCreditCardId)->getSize() > 0;
    }

    /**
     * @param $customerId
     * @return Collection
     */
    public function getCreditCardInfosByCustomerId($customerId)
    {
        return $this->addFilter('customer_id', $customerId);
    }
}
