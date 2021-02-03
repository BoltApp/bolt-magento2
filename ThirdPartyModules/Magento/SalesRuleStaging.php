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

namespace Bolt\Boltpay\ThirdPartyModules\Magento;

/**
 * Class SalesRuleStaging
 * @package Bolt\Boltpay\ThirdPartyModules\Magento
 */
class SalesRuleStaging
{
    /**
     * If Magento EE SalesRuleStaging module is present and active, fromDate and toDate fields are ignored and
     * rules are activated / deactivated by changing the active flag via scheduler.
     * In this case we need to skip time window verification set in our module.
     * @return false
     */
    public function verifyRuleTimeFrame()
    {
        return false;
    }
}