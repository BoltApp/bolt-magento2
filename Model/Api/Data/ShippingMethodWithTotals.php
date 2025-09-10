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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Api\Data\ShippingMethodWithTotalsInterface;

/**
 * Quote shipping method data.
 *
 * @codeCoverageIgnore
 */
class ShippingMethodWithTotals extends \Magento\Framework\Model\AbstractExtensibleModel implements ShippingMethodWithTotalsInterface
{
    /**
     * @inheriDoc
     */
    public function getTotals()
    {
        return $this->getData(self::KEY_TOTAL_TAX_AMOUNT);
    }

    /**
     * @inheriDoc
     */
    public function setTotals($totals)
    {
        return $this->setData(self::KEY_TOTAL_TAX_AMOUNT, $totals);
    }

    /**
     * @inheriDoc
     */
    public function getShippingMethod()
    {
        return $this->getData(self::KEY_SHIPPING_METHOD);
    }

    /**
     * @inheriDoc
     */
    public function setShippingMethod($shippingMethod)
    {
        return $this->setData(self::KEY_SHIPPING_METHOD, $shippingMethod);
    }
}
