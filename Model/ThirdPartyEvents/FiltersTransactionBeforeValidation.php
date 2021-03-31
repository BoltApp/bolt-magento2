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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\ThirdPartyEvents;

/**
 * Trait FiltersTransactionBeforeValidation
 *
 * @package Bolt\Boltpay\Model\ThirdPartyEvents
 */
trait FiltersTransactionBeforeValidation
{
    /**
     * Filters Bolt transaction received during the order creation hook
     *
     * @see \Bolt\Boltpay\Model\Api\CreateOrder::validateQuoteData
     *
     * @param \stdClass $transaction Bolt transaction object
     *
     * @return \stdClass either changed or unchanged transaction object
     */
    abstract public function filterTransactionBeforeOrderCreateValidation($transaction);
}
