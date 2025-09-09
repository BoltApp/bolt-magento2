<?php
namespace Bolt\Boltpay\Api;

use Magento\Quote\Api\Data\AddressInterface;
use Bolt\Boltpay\Api\Data\ShippingMethodWithTotalsInterface;

/**
 * Interface ShipmentManagementInterface
 * @api
 */
interface ShipmentEstimationInterface
{
    /**
     * Estimate shipping and taxes by address and return list of available shipping methods
     *
     * @param mixed $cartId
     * @param AddressInterface $address
     * @return ShippingMethodWithTotalsInterface[] An array of shipping methods with related totals.
     * @throws \Magento\Framework\Exception\InputException The specified input is not valid.
     * @since 100.0.7
     */
    public function estimateMethodsWithTaxesByExtendedAddress($cartId, AddressInterface $address);
}
