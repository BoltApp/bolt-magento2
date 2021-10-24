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

namespace Bolt\Boltpay\ThirdPartyModules\Mexbs;

class Tieredcoupon
{
    /**
     * @param Observer $observer
     *
     * @return bool
     */
    public function loadCouponCodeData($result, $mexbsTieredcouponHelperData, $couponCode)
    {
        try {
            if (!$result) {
                $tieredcoupon = $mexbsTieredcouponHelperData->getTieredCouponByCouponCode($couponCode);
                if($tieredcoupon && $tieredcoupon->getId() && $tieredcoupon->getIsActive()){
                    return $tieredcoupon;
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return $result;
    }
}
