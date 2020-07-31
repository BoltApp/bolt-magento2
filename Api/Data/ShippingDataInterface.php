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
 * Shipping options interface. Shipping options field of the Shipping response object.
 *
 * Get shipping options available.
 * @api
 */
interface ShippingDataInterface extends ShippingTaxDataInterface
{
    /**
     * Get all available shipping options.
     *
     * @api
     * @return \Bolt\Boltpay\Api\Data\ShippingOptionInterface[]
     */
    public function getShippingOptions();

    /**
     * Set available shipping options.
     *
     * @api
     * @param \Bolt\Boltpay\Api\Data\ShippingOptionInterface[]
     * @return $this
     */
    public function setShippingOptions($shippingOptions);
}
