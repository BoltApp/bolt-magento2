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

namespace Bolt\Boltpay\Api\Data;

/**
 * Tax data interface. Tax response object fields.
 *
 * Get subtotal and shipping tax.
 * @api
 */
interface TaxDataInterface extends ShippingTaxDataInterface
{
    /**
     * Get order tax result.
     *
     * @return \Bolt\Boltpay\Api\Data\TaxResultInterface
     * @api
     */
    public function getTaxResult();

    /**
     * Set tax result.
     *
     * @param \Bolt\Boltpay\Api\Data\TaxResultInterface
     * @return $this
     * @api
     */
    public function setTaxResult($taxResult);

    /**
     * Get selected shipping option tax data.
     *
     * @return \Bolt\Boltpay\Api\Data\ShippingOptionInterface
     * @api
     */
    public function getShippingOption();

    /**
     * Set selected shipping option tax data.
     *
     * @param \Bolt\Boltpay\Api\Data\ShippingOptionInterface
     * @return $this
     * @api
     */
    public function setShippingOption($shippingOption);
}
