<?php
/**
 * Copyright © 2013-2017 Bold, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */


namespace Bolt\Boltpay\Api\Data;

/**
 * Shipping options interface. Shipping options field of the Shipping and Tax response object.
 *
 * Get shipping options available.
 * @api
 */
interface ShippingOptionsInterface
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

    /**
     * Get order tax result.
     *
     * @api
     * @return \Bolt\Boltpay\Api\Data\ShippingTaxInterface
     */
    public function getTaxResult();

    /**
     * Set available shipping options.
     *
     * @api
     * @param \Bolt\Boltpay\Api\Data\ShippingTaxInterface
     * @return $this
     */
    public function setTaxResult($taxResult);
}
