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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\ResourceModel;
use \Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class CustomerCreditCard
 * @package Bolt\Boltpay\Model\ResourceModel
 */
class CustomerCreditCard extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('bolt_customer_credit_cards', 'id');
    }
}