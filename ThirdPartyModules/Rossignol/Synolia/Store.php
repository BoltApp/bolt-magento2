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

namespace Bolt\Boltpay\ThirdPartyModules\Rossignol\Synolia;

use Synolia\Store\Model\Carrier as InStorePickup;
use Bolt\Boltpay\Helper\Bugsnag as BugsnagHelper;

/**
 * Class Store
 *
 * @package Bolt\Boltpay\ThirdPartyModules\Rossignol\Synolia
 */
class Store
{
    /**
     * @var BugsnagHelper
     */
    private $bugsnagHelper;

    /**
     * Store constructor.
     *
     * @param BugsnagHelper $bugsnagHelper
     */
    public function __construct(
        BugsnagHelper $bugsnagHelper
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
    }

    /**
     * @param array $result
     * @param Magento\Quote\Model\Quote $quote
     * @param array $shippingOptions
     * @param array $addressData
     * @return array
     */
    public function getShipToStoreOptions(
        $result,
        $quote,
        $shippingOptions,
        $addressData
    ) {
        try {
            if (!empty($shippingOptions)) {
                $tmpShippingOptions = [];
                foreach ($shippingOptions as $shippingOption) {
                    if ($shippingOption->getReference() !== InStorePickup::CARRIER_CODE.'_'.InStorePickup::CARRIER_CODE) {
                        $tmpShippingOptions[] = $shippingOption;
                    }
                }                
                $result = [[], $tmpShippingOptions];
            }            
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
    }
}
