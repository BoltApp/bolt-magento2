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

namespace Bolt\Boltpay\Model\ThirdPartyEvents;

/**
 * Trait FiltersCartBeforeCreateOrder
 *
 * @package Bolt\Boltpay\Model\ThirdPartyEvents
 */
trait FiltersCartBeforeCreateOrder
{
    /**
     * Filter the transaction before Create Order functionality is executed
     *
     * @see \Bolt\Boltpay\Model\Api\CreateOrder::createOrder
     *
     * @param array $cart the transaction object from Bolt
     *
     * @return array either changed or unchanged $transaction object
     */
    abstract public function filterCartBeforeCreateOrder($transaction);
}
